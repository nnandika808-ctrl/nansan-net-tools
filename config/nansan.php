<?php

return [
    'indexable' => env('SITE_INDEXABLE', false),
    'turnstile' => [
        'enabled' => env('TURNSTILE_ENABLED', true), 'site_key' => env('TURNSTILE_SITE_KEY'), 'secret' => env('TURNSTILE_SECRET_KEY'),
        'hostnames' => array_filter(explode(',', env('TURNSTILE_HOSTNAMES', 'mikrotik.nansannet.cloud'))),
    ],
    'contact' => env('CONTACT_URL'),
    'backup_disk' => env('BACKUP_DISK'),
    'backup_key' => env('BACKUP_ENCRYPTION_KEY'),
    'mysql_dump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),
    'backup_retention_days' => max(7, min(30, (int) env('BACKUP_RETENTION_DAYS', 14))),
    'restore_mysql' => [
        'host' => env('RESTORE_MYSQL_HOST'), 'port' => env('RESTORE_MYSQL_PORT', 3306),
        'username' => env('RESTORE_MYSQL_USERNAME'), 'password' => env('RESTORE_MYSQL_PASSWORD'),
        'client' => env('MYSQL_CLIENT_PATH', 'mysql'),
    ],
];
