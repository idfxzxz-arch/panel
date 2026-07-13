<?php

return [
    'apps_path' => env('HOSTING_APPS_PATH', storage_path('app/apps')),
    'proxy_network' => env('HOSTING_PROXY_NETWORK', 'hosting_proxy'),
    'acme_resolver' => env('HOSTING_ACME_RESOLVER', 'letsencrypt'),
    'command_timeout' => (int) env('HOSTING_COMMAND_TIMEOUT', 900),
    'log_lines' => (int) env('HOSTING_LOG_LINES', 300),
];
