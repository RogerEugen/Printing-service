<?php

return [
    'max_upload_kilobytes' => (int) env('PRINT_MAX_UPLOAD_KB', 20480),
    'max_copies' => (int) env('PRINT_MAX_COPIES', 20),
    'allowed_extensions' => [
        'pdf',
        'doc', 'docx',
        'xls', 'xlsx',
        'ppt', 'pptx',
        'txt', 'rtf',
        'jpg', 'jpeg', 'png', 'bmp', 'gif', 'tif', 'tiff', 'webp',
    ],
    'offline_after_seconds' => (int) env('PRINTER_OFFLINE_AFTER_SECONDS', 90),
    'development_admin_password' => env('DEV_ADMIN_PASSWORD'),
];
