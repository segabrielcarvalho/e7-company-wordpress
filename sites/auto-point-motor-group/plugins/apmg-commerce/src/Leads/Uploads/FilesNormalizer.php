<?php
declare(strict_types=1);

namespace APMG\Commerce\Leads\Uploads;

final class FilesNormalizer
{
    /** @param array<string, mixed> $fileBag @return list<array<string, mixed>> */
    public static function normalize(array $fileBag): array
    {
        if (!isset($fileBag['name'])) {
            return [];
        }
        if (!is_array($fileBag['name'])) {
            return [$fileBag];
        }
        if (count($fileBag['name']) > 6) {
            throw new UploadException('A maximum of six vehicle photos is allowed.');
        }

        $files = [];
        foreach ($fileBag['name'] as $index => $name) {
            $files[] = [
                'name' => (string) $name,
                'tmp_name' => (string) ($fileBag['tmp_name'][$index] ?? ''),
                'size' => (int) ($fileBag['size'][$index] ?? 0),
                'error' => (int) ($fileBag['error'][$index] ?? UPLOAD_ERR_NO_FILE),
            ];
        }
        return $files;
    }
}
