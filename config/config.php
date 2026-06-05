<?php

// Абсолютный путь к папке uploads (корень проекта + /uploads/)
// dirname(__DIR__) — выходим из config/ в корень docviewer/
define('UPLOAD_DIR',  dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);

define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50 MB

define('ALLOWED_TYPES', [
    'pdf'  => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc'  => 'application/msword',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xls'  => 'application/vnd.ms-excel',
]);

define('TOKEN_TTL',  120);
define('TOKEN_SALT', 'change-this-in-production');