<?php

return [
    'enabled' => (bool) env('BACKUP_ENABLED', false),
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),
    'disk' => env('BACKUP_DISK', 'local'),
    'directory' => trim((string) env('BACKUP_DIRECTORY', 'backups'), '/'),
    'retention_days' => max(1, (int) env('BACKUP_RETENTION_DAYS', 14)),
    'minimum_copies' => max(1, (int) env('BACKUP_MINIMUM_COPIES', 3)),
    'time' => env('BACKUP_TIME', '02:30'),
];
