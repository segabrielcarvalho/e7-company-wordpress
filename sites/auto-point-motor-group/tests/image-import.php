<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

$thumbnail_calls = [];
$deleted_attachments = [];
function set_post_thumbnail(int $post_id, int $attachment_id): bool {
    global $thumbnail_calls;
    $thumbnail_calls[] = [$post_id, $attachment_id];
    return true;
}
function wp_delete_attachment(int $attachment_id, bool $force_delete = false): bool {
    global $deleted_attachments;
    $deleted_attachments[] = [$attachment_id, $force_delete];
    return true;
}

require_once dirname(__DIR__) . '/inc/import-command.php';

$url = 'https://media.dealerhub.ie/520572/357256/13095033/41e9b36d-4f78-4361-8732-767c072f8df3';
$filename = apmg_image_filename_from_url($url, 'image/jpeg');
if ($filename !== '41e9b36d-4f78-4361-8732-767c072f8df3.jpg') {
    fwrite(STDERR, "DealerHub extensionless image did not receive a JPEG filename: {$filename}\n");
    exit(1);
}

if (apmg_image_filename_from_url('https://example.com/car.webp', 'image/webp') !== 'car.webp') {
    fwrite(STDERR, "Existing image extensions must be preserved.\n");
    exit(1);
}

apmg_replace_vehicle_thumbnail(41, 99, 88);
if ($thumbnail_calls !== [[41, 99]] || $deleted_attachments !== [[88, true]]) {
    fwrite(STDERR, "Refresh must replace the cover before deleting the prior attachment.\n");
    exit(1);
}

apmg_replace_vehicle_thumbnail(41, 99, 99);
if (count($deleted_attachments) !== 1) {
    fwrite(STDERR, "Refresh must not delete the active attachment when IDs match.\n");
    exit(1);
}

echo "Image import passed.\n";
