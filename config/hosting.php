<?php

return [
    'apps_path' => env('HOSTING_APPS_PATH', storage_path('app/apps')),
    'command_timeout' => (int) env('HOSTING_COMMAND_TIMEOUT', 900),
    'log_lines' => (int) env('HOSTING_LOG_LINES', 300),
];
