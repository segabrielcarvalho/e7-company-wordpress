<?php
declare(strict_types=1);

use APMG\Commerce\Leads\Crypto\SodiumEncryptor;
use APMG\Commerce\Leads\Domain\LeadInputValidator;
use APMG\Commerce\Leads\Domain\ValidationException;
use APMG\Commerce\Leads\Forms\FormRenderer;
use APMG\Commerce\Leads\Http\SubmissionHandler;
use APMG\Commerce\Leads\Http\PrgRedirect;
use APMG\Commerce\Leads\Infrastructure\LeadSchema;
use APMG\Commerce\Leads\Infrastructure\WpLeadRepository;
use APMG\Commerce\Leads\Module;
use APMG\Commerce\Leads\Security\RateLimiter;
use APMG\Commerce\Leads\Security\TurnstileVerifier;
use APMG\Commerce\Leads\Service\LeadService;
use APMG\Commerce\Leads\Service\MailNotifier;
use APMG\Commerce\Leads\Service\RetentionManager;
use APMG\Commerce\Leads\Uploads\ImageUploadProcessor;
use APMG\Commerce\Leads\Uploads\UploadException;

spl_autoload_register(static function (string $class): void {
    $prefix = 'APMG\\Commerce\\Leads\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = dirname(__DIR__) . '/src/Leads/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

$tests = [];
$test = static function (string $name, callable $callback) use (&$tests): void {
    $tests[$name] = $callback;
};
$assert = static function (bool $condition, string $message = 'Assertion failed'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$same = static function (mixed $expected, mixed $actual, string $message = ''): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message !== '' ? $message : sprintf(
            "Expected %s, got %s",
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
};
$throws = static function (callable $callback, string $exceptionClass) use ($assert): void {
    try {
        $callback();
    } catch (Throwable $error) {
        $assert($error instanceof $exceptionClass, 'Unexpected exception: ' . $error::class);
        return;
    }

    throw new RuntimeException('Expected exception ' . $exceptionClass);
};

$test('lead validator sanitizes the enquire allowlist', static function () use ($same): void {
    $validator = new LeadInputValidator();
    $payload = $validator->validate('enquire', [
        'name' => '  <b>Jane Doe</b> ',
        'email' => ' JANE@example.COM ',
        'phone' => ' +353 (66) 710-2545 ext. 9 ',
        'vehicle_id' => ' vehicle-123 ',
        'message' => " <script>alert(1)</script>Interested\r\nplease call ",
        'consent' => '1',
        'contact_preference' => 'email',
        'ignored' => 'must not persist',
    ]);

    $same([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '+353 (66) 710-2545 9',
        'vehicle_id' => 'vehicle-123',
        'message' => 'alert(1)Interested' . "\n" . 'please call',
        'contact_preference' => 'email',
    ], $payload);
});

$test('lead validator rejects invalid required fields', static function () use ($throws): void {
    $validator = new LeadInputValidator();
    $throws(static fn() => $validator->validate('enquire', [
        'name' => '',
        'email' => 'not-an-email',
        'phone' => 'abc',
    ]), ValidationException::class);
});

$test('finance rejects financial and identity-sensitive fields', static function () use ($throws): void {
    $validator = new LeadInputValidator();
    foreach (['pps_number', 'iban', 'card_number', 'monthly_income', 'date_of_birth'] as $field) {
        $throws(static fn() => $validator->validate('finance', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        'phone' => '+353 66 710 2545',
            'consent' => '1',
            $field => 'sensitive',
        ]), ValidationException::class);
    }
});

$test('finance rejects obfuscated sensitive field names and nested scalar fields', static function () use ($throws): void {
    $validator = new LeadInputValidator();
    $base = [
        'name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '+353 66 710 2545', 'consent' => '1',
    ];
    foreach (['IBAN ', 'pps-number', 'Monthly Income'] as $field) {
        $throws(static fn() => $validator->validate('finance', $base + [$field => 'sensitive']), ValidationException::class);
    }
    $throws(static fn() => $validator->validate('finance', $base + ['message' => ['nested']]), ValidationException::class);
});

$test('exchange validates vehicle fields without accepting arbitrary keys', static function () use ($same): void {
    $validator = new LeadInputValidator();
    $payload = $validator->validate('exchange', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '+353 66 710 2545',
        'consent' => '1',
        'registration' => '  241-KY-123 ',
        'make' => ' Volkswagen ',
        'model' => ' Golf ',
        'version' => ' 2.0 TDI ',
        'odometer' => '123456',
        'condition' => ' Good ',
        'details' => '<b>One owner</b>',
        'admin' => '1',
    ]);

    $same('241-KY-123', $payload['registration']);
    $same('Volkswagen', $payload['make']);
    $same('Golf', $payload['model']);
    $same('2.0 TDI', $payload['version']);
    $same(123456, $payload['odometer']);
    $same('Good', $payload['condition']);
    $same('One owner', $payload['details']);
    $same(false, array_key_exists('admin', $payload));
});

$test('consent is mandatory but is not persisted in encrypted payload', static function () use ($throws, $same): void {
    $validator = new LeadInputValidator();
    $base = ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '+353 66 710 2545'];
    $throws(static fn() => $validator->validate('enquire', $base), ValidationException::class);
    $payload = $validator->validate('enquire', $base + ['consent' => '1', 'contact_preference' => 'email']);
    $same(false, array_key_exists('consent', $payload));
});

$test('enquire accepts only email or phone contact preference', static function () use ($same, $throws): void {
    $validator = new LeadInputValidator();
    $base = [
        'name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '+353 66 710 2545', 'consent' => '1',
    ];
    $same('phone', $validator->validate('enquire', $base + ['contact_preference' => 'phone'])['contact_preference']);
    $throws(static fn() => $validator->validate('enquire', $base + ['contact_preference' => 'sms']), ValidationException::class);
});

$test('sodium encryption round-trips and never embeds plaintext', static function () use ($same, $assert): void {
    $encryptor = new SodiumEncryptor(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    $payload = ['email' => 'jane@example.com', 'phone' => '+353667102545'];
    $ciphertext = $encryptor->encrypt($payload);

    $assert(!str_contains($ciphertext, 'jane@example.com'));
    $same($payload, $encryptor->decrypt($ciphertext));
    $assert($ciphertext !== $encryptor->encrypt($payload), 'Nonce must be random');
});

$test('sodium encryption rejects invalid keys and tampered envelopes', static function () use ($throws): void {
    $throws(static fn() => new SodiumEncryptor('short'), InvalidArgumentException::class);
    $encryptor = new SodiumEncryptor(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    $envelope = json_decode($encryptor->encrypt(['name' => 'Jane']), true, 512, JSON_THROW_ON_ERROR);
    $envelope['cipher'] = base64_encode(random_bytes(40));
    $throws(static fn() => $encryptor->decrypt(json_encode($envelope, JSON_THROW_ON_ERROR)), RuntimeException::class);
});

$test('lead schema defines encrypted payload retention and status indexes', static function () use ($assert): void {
    $sql = LeadSchema::createSql('wp_apmg_leads', 'DEFAULT CHARACTER SET utf8mb4');
    foreach (['public_id', 'payload_cipher', 'attachments_json', 'expires_at', 'KEY status', 'KEY expires_at'] as $fragment) {
        $assert(str_contains($sql, $fragment), 'Missing schema fragment: ' . $fragment);
    }
    $assert(!str_contains($sql, ' email '), 'PII must not have plaintext columns');
    $assert(!str_contains($sql, ' phone '), 'PII must not have plaintext columns');
});

$test('rate limiter allows configured attempts then blocks until window resets', static function () use ($same): void {
    $state = [];
    $now = 1_000;
    $limiter = new RateLimiter(
        static function (string $key) use (&$state): mixed { return $state[$key] ?? null; },
        static function (string $key, array $value, int $ttl) use (&$state): void { $state[$key] = $value; },
        static function () use (&$now): int { return $now; },
        2,
        60
    );

    $same(true, $limiter->consume('hash'));
    $same(true, $limiter->consume('hash'));
    $same(false, $limiter->consume('hash'));

    $now = 1_061;
    $same(true, $limiter->consume('hash'));
});

$test('turnstile bypass is permitted only when local and unconfigured', static function () use ($same): void {
    $post = static fn(string $url, array $body): array => ['success' => true];
    $same(true, (new TurnstileVerifier('', '', static fn() => 'local', $post))->verify('', '127.0.0.1'));
    $same(false, (new TurnstileVerifier('', '', static fn() => 'production', $post))->verify('', '127.0.0.1'));
    $same(false, (new TurnstileVerifier('site-only', '', static fn() => 'local', $post))->verify('', '127.0.0.1'));
});

$test('configured turnstile posts token and remote IP and fails closed', static function () use ($same): void {
    $captured = [];
    $post = static function (string $url, array $body) use (&$captured): array {
        $captured = [$url, $body];
        return ['success' => true];
    };
    $verifier = new TurnstileVerifier('site', 'secret', static fn() => 'production', $post);
    $same(true, $verifier->verify('token', '203.0.113.5'));
    $same('https://challenges.cloudflare.com/turnstile/v0/siteverify', $captured[0]);
    $same('secret', $captured[1]['secret']);
    $same('token', $captured[1]['response']);
    $same('203.0.113.5', $captured[1]['remoteip']);
    $same(false, $verifier->verify('', '203.0.113.5'));
});

$test('image uploads are reprocessed outside public root and metadata is stripped', static function () use ($assert, $same): void {
    $base = sys_get_temp_dir() . '/apmg-upload-' . bin2hex(random_bytes(5));
    $public = $base . '/public';
    $private = $base . '/private/leads';
    mkdir($public, 0700, true);
    $source = $base . '/source.jpg';
    $image = imagecreatetruecolor(20, 20);
    imagejpeg($image, $source, 90);
    imagedestroy($image);
    file_put_contents($source, 'SECRET_METADATA', FILE_APPEND);

    $processor = new ImageUploadProcessor($private, $public, 8 * 1024 * 1024, static fn(string $path) => is_file($path));
    $relative = $processor->store([
        'name' => 'car.jpg',
        'tmp_name' => $source,
        'size' => filesize($source),
        'error' => UPLOAD_ERR_OK,
    ], 'lead-public-id');
    $stored = $private . '/' . $relative;

    $assert(is_file($stored));
    $assert(!str_starts_with(realpath($stored), realpath($public)));
    $assert(!str_contains((string) file_get_contents($stored), 'SECRET_METADATA'));
    $same('image/jpeg', (new finfo(FILEINFO_MIME_TYPE))->file($stored));

    $processor->delete($relative);
    $same(false, is_file($stored));
});

$test('image uploads reject unsupported types oversized files and public storage', static function () use ($throws): void {
    $base = sys_get_temp_dir() . '/apmg-upload-reject-' . bin2hex(random_bytes(5));
    $public = $base . '/public';
    mkdir($public, 0700, true);
    $text = $base . '/payload.txt';
    file_put_contents($text, 'not an image');

    $processor = new ImageUploadProcessor($base . '/private', $public, 10, static fn(string $path) => true);
    $throws(static fn() => $processor->store([
        'name' => 'payload.txt', 'tmp_name' => $text, 'size' => 12, 'error' => UPLOAD_ERR_OK,
    ], 'lead'), UploadException::class);
    $throws(static fn() => new ImageUploadProcessor($public . '/uploads', $public), InvalidArgumentException::class);
});

$test('image uploads reject excessive pixel dimensions before reprocessing', static function () use ($throws): void {
    $base = sys_get_temp_dir() . '/apmg-upload-dimensions-' . bin2hex(random_bytes(5));
    $public = $base . '/public';
    mkdir($public, 0700, true);
    $source = $base . '/wide.jpg';
    $image = imagecreatetruecolor(12_001, 1);
    imagejpeg($image, $source, 75);
    imagedestroy($image);
    $processor = new ImageUploadProcessor($base . '/private', $public, 8 * 1024 * 1024, static fn(string $path) => true);
    $throws(static fn() => $processor->store([
        'name' => 'wide.jpg', 'tmp_name' => $source, 'size' => filesize($source), 'error' => UPLOAD_ERR_OK,
    ], 'lead'), UploadException::class);
});

$test('exchange upload batch accepts at most six photos', static function () use ($throws, $same): void {
    $base = sys_get_temp_dir() . '/apmg-upload-batch-' . bin2hex(random_bytes(5));
    $public = $base . '/public';
    mkdir($public, 0700, true);
    $source = $base . '/source.png';
    $image = imagecreatetruecolor(5, 5);
    imagepng($image, $source);
    imagedestroy($image);
    $processor = new ImageUploadProcessor($base . '/private', $public, 8 * 1024 * 1024, static fn(string $path) => true);
    $file = ['name' => 'car.png', 'tmp_name' => $source, 'size' => filesize($source), 'error' => UPLOAD_ERR_OK];
    $same(6, count($processor->storeMany(array_fill(0, 6, $file), 'lead-six')));
    $throws(static fn() => $processor->storeMany(array_fill(0, 7, $file), 'lead-seven'), UploadException::class);
});

$test('finance form excludes financial and identity-sensitive inputs', static function () use ($assert): void {
    $renderer = new FormRenderer(static fn(string $action) => '<input type="hidden" name="_nonce" value="nonce">');
    $html = strtolower($renderer->render('finance'));
    foreach (['pps', 'iban', 'income', 'bank', 'card', 'date_of_birth', 'dob'] as $forbidden) {
        $assert(!str_contains($html, $forbidden), 'Finance form contains forbidden field: ' . $forbidden);
    }
    foreach (['name="name"', 'name="email"', 'name="phone"', 'name="vehicle_id"', 'name="message"'] as $required) {
        $assert(str_contains($html, $required), 'Finance form missing field: ' . $required);
    }
});

$test('all forms post to a server action with nonce and no PII in URL', static function () use ($assert): void {
    $renderer = new FormRenderer(static fn(string $action) => '<input type="hidden" name="_nonce" value="nonce">');
    foreach (['enquire', 'finance', 'exchange'] as $type) {
        $html = $renderer->render($type);
        $assert(str_contains($html, 'method="post"'));
        $assert(str_contains($html, 'name="action" value="apmg_submit_lead"'));
        $assert(str_contains($html, 'name="lead_type" value="' . $type . '"'));
        $assert(str_contains($html, 'name="_nonce"'));
        $assert(!preg_match('/action="[^\"]*[?&](?:email|phone|name)=/i', $html));
    }
});

$test('vehicle context is prefilled without placing contact PII in the form URL', static function () use ($assert): void {
    $renderer = new FormRenderer(static fn(string $action) => '<input type="hidden" name="_nonce" value="nonce">');
    $html = $renderer->render('finance', ['vehicle_id' => '381']);
    $assert(str_contains($html, 'name="vehicle_id"'));
    $assert(str_contains($html, 'value="381"'));
});

$test('all forms require consent and expose the type-specific fields', static function () use ($assert): void {
    $renderer = new FormRenderer(static fn(string $action) => '<input type="hidden" name="_nonce" value="nonce">');
    foreach (['enquire', 'finance', 'exchange'] as $type) {
        $html = $renderer->render($type);
        $assert(str_contains($html, 'name="consent"'));
        $assert(str_contains($html, 'type="checkbox"'));
        $assert(str_contains($html, 'required'));
    }
    $enquire = $renderer->render('enquire');
    $assert(str_contains($enquire, 'name="contact_preference"'));
    $exchange = $renderer->render('exchange');
    foreach (['name="make"', 'name="model"', 'name="version"', 'name="registration"'] as $field) {
        $assert(str_contains($exchange, $field), 'Exchange form missing ' . $field);
    }
    $assert(str_contains($exchange, 'up to 6'));
});

$test('repository creates leads with ninety-day retention and encrypted-only payload columns', static function () use ($same, $assert): void {
    $wpdb = new class {
        public string $prefix = 'wp_';
        public array $lastInsert = [];
        public function insert(string $table, array $data, array $formats): int {
            $this->lastInsert = [$table, $data, $formats];
            return 1;
        }
    };
    $repository = new WpLeadRepository($wpdb, 'wp_apmg_leads');
    $created = new DateTimeImmutable('2026-07-21 12:00:00', new DateTimeZone('UTC'));
    $same(true, $repository->create('uuid', 'finance', 'encrypted-envelope', [], $created));
    $data = $wpdb->lastInsert[1];
    $same('2026-10-19 12:00:00', $data['expires_at']);
    $same('new', $data['status']);
    $same('encrypted-envelope', $data['payload_cipher']);
    $assert(!isset($data['email'], $data['phone'], $data['name']));
});

$test('submission handler enforces nonce rate limit and turnstile before accepting', static function () use ($same): void {
    $state = [];
    $rate = new RateLimiter(
        static function (string $key) use (&$state): mixed { return $state[$key] ?? null; },
        static function (string $key, array $value, int $ttl) use (&$state): void { $state[$key] = $value; },
        static fn(): int => 1000,
        1,
        60
    );
    $turnstile = new TurnstileVerifier('', '', static fn() => 'local', static fn() => ['success' => false]);
    $submissions = 0;
    $submit = static function (string $type, array $input, array $files) use (&$submissions): string {
        $submissions++;
        return 'public-id';
    };

    $invalidNonce = new SubmissionHandler(static fn(string $nonce): bool => false, $rate, $turnstile, $submit);
    $same('security_error', $invalidNonce->handle(['_nonce' => 'bad', 'lead_type' => 'enquire'], [], '203.0.113.1')->code);
    $same(0, $submissions);

    $handler = new SubmissionHandler(static fn(string $nonce): bool => true, $rate, $turnstile, $submit);
    $same('success', $handler->handle(['_nonce' => 'ok', 'lead_type' => 'enquire'], [], '203.0.113.2')->code);
    $same('rate_limited', $handler->handle(['_nonce' => 'ok', 'lead_type' => 'enquire'], [], '203.0.113.2')->code);
    $same(1, $submissions);
});

$test('lead service encrypts the allowlisted payload before persistence and sends metadata-only notification', static function () use ($same, $assert): void {
    $encryptor = new SodiumEncryptor(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    $persisted = [];
    $notified = [];
    $service = new LeadService(
        new LeadInputValidator(),
        $encryptor,
        null,
        static function (string $id, string $type, string $cipher, array $attachments, DateTimeImmutable $created) use (&$persisted): bool {
            $persisted = [$id, $type, $cipher, $attachments, $created];
            return true;
        },
        static function (string $type, string $id, array $payload) use (&$notified): void { $notified = [$type, $id, $payload['email']]; },
        static fn(): string => 'public-uuid',
        static fn(): DateTimeImmutable => new DateTimeImmutable('2026-07-21 12:00:00', new DateTimeZone('UTC'))
    );

    $id = $service->submit('finance', [
        'name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '+353 66 710 2545',
        'vehicle_id' => 'vehicle-42', 'message' => 'Please call', 'consent' => '1', '_nonce' => 'not persisted',
    ], []);
    $same('public-uuid', $id);
    $same('finance', $persisted[1]);
    $assert(!str_contains($persisted[2], 'jane@example.com'));
    $payload = $encryptor->decrypt($persisted[2]);
    $same(false, isset($payload['consent'], $payload['_nonce']));
    $same('jane@example.com', $payload['email']);
    $same([], $persisted[3]);
    $same(['finance', 'public-uuid', 'jane@example.com'], $notified);
});

$test('retention manager deletes expired private files and records', static function () use ($same): void {
    $deletedFiles = [];
    $deletedRecords = [];
    $expired = [[
        'public_id' => 'lead-1',
        'attachments_json' => json_encode(['lead-1/a.jpg', 'lead-1/b.webp'], JSON_THROW_ON_ERROR),
    ]];
    $manager = new RetentionManager(
        static fn(): array => $expired,
        static function (string $path) use (&$deletedFiles): void { $deletedFiles[] = $path; },
        static function (string $id) use (&$deletedRecords): void { $deletedRecords[] = $id; }
    );
    $same(1, $manager->purge());
    $same(['lead-1/a.jpg', 'lead-1/b.webp'], $deletedFiles);
    $same(['lead-1'], $deletedRecords);
});

$test('PRG redirect strips the original query and returns only a non-PII status', static function () use ($same, $assert): void {
    $target = PrgRedirect::target(
        'https://example.com/finance/?email=jane%40example.com&phone=123&name=Jane#form',
        'https://example.com/contact-us/',
        'success'
    );
    $same('https://example.com/finance/?lead_status=success', $target);
    $assert(!str_contains($target, 'jane'));
    $assert(!str_contains($target, 'phone'));
});

$test('mail notification contains no submitted PII', static function () use ($same, $assert): void {
    $sent = [];
    $notifier = new MailNotifier(
        'sales@example.com',
        'https://example.com/wp-admin/tools.php?page=apmg-leads',
        static function (string $to, string $subject, string $body) use (&$sent): bool {
            $sent[] = [$to, $subject, $body];
            return true;
        }
    );
    $same(true, $notifier->send('exchange', 'public-uuid', ['email' => 'jane@example.com', 'name' => 'Jane Doe']));
    $same('sales@example.com', $sent[0][0]);
    $assert(str_contains($sent[0][1], 'Exchange'));
    $assert(str_contains($sent[0][2], 'public-uuid'));
    foreach (['email', 'phone', 'name', 'registration'] as $piiField) {
        $assert(!str_contains(strtolower($sent[0][2]), $piiField));
    }
});

$test('mail notifier sends customer confirmation and keeps team alert PII-free', static function () use ($same, $assert): void {
    $sent = [];
    $notifier = new MailNotifier(
        'sales@example.com',
        'https://example.com/wp-admin/tools.php?page=apmg-leads',
        static function (string $to, string $subject, string $body) use (&$sent): bool {
            $sent[] = [$to, $subject, $body];
            return true;
        }
    );
    $same(true, $notifier->send('finance', 'public-uuid', ['email' => 'jane@example.com', 'name' => 'Jane Doe']));
    $same(2, count($sent));
    $same('sales@example.com', $sent[0][0]);
    $assert(!str_contains($sent[0][2], 'jane@example.com'));
    $same('jane@example.com', $sent[1][0]);
    $assert(str_contains($sent[1][1], 'received'));
});

$test('repository retention is configurable while defaulting to ninety days', static function () use ($same): void {
    $wpdb = new class {
        public array $lastInsert = [];
        public function insert(string $table, array $data, array $formats): int { $this->lastInsert = $data; return 1; }
    };
    $created = new DateTimeImmutable('2026-07-21 12:00:00', new DateTimeZone('UTC'));
    (new WpLeadRepository($wpdb, 'wp_apmg_leads', 30))->create('uuid', 'enquire', 'cipher', [], $created);
    $same('2026-08-20 12:00:00', $wpdb->lastInsert['expires_at']);
});

$test('lead module exposes integration lifecycle and public shortcodes', static function () use ($assert): void {
    foreach (['register', 'activate', 'deactivate', 'renderEnquire', 'renderFinance', 'renderExchange', 'handleSubmission', 'purgeExpired'] as $method) {
        $assert(method_exists(Module::class, $method), 'Missing module method ' . $method);
    }
});

$failures = 0;
foreach ($tests as $name => $callback) {
    try {
        $callback();
        fwrite(STDOUT, "PASS {$name}\n");
    } catch (Throwable $error) {
        $failures++;
        fwrite(STDERR, "FAIL {$name}: {$error->getMessage()}\n");
    }
}

fwrite(STDOUT, sprintf("\n%d tests, %d failures\n", count($tests), $failures));
exit($failures === 0 ? 0 : 1);
