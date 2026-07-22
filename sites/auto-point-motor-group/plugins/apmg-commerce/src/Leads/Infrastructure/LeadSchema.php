<?php
declare(strict_types=1);

namespace APMG\Commerce\Leads\Infrastructure;

final class LeadSchema
{
    public static function createSql(string $table, string $charsetCollate): string
    {
        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            type varchar(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'new',
            payload_cipher longtext NOT NULL,
            attachments_json longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY public_id (public_id),
            KEY status (status),
            KEY expires_at (expires_at)
        ) {$charsetCollate};";
    }
}
