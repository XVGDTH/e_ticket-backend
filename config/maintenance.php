<?php

return [
    'backup_binary' => env('DB_DUMP_BINARY', 'mysqldump'),
    'backup_disk' => 'local',
    'backup_directory' => 'maintenance/backups',
    'backup_retention_days' => 30,
];
