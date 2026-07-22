<?php
declare(strict_types=1);

namespace APMG\Commerce\Leads\Http;

final class SubmissionResult
{
    public function __construct(
        public readonly string $code,
        public readonly string $publicId = ''
    ) {
    }
}
