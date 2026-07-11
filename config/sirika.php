<?php

$trustedHosts = array_values(array_filter(array_map('trim', explode(',', env(
    'SIRIKA_TRUSTED_HOSTS',
    'sirika.vdnisite.com'
)))));

return [
    'seed_user_password' => env('SIRIKA_SEED_USER_PASSWORD'),

    'trusted_hosts' => $trustedHosts ?: ['sirika.vdnisite.com'],

    'route_map' => [
        'key' => env('SIRIKA_ROUTE_MAP_KEY', 'vdni-road-map-v1'),
        'image_url' => env('SIRIKA_ROUTE_MAP_IMAGE_URL', '/images/maps/vdni-road-map-v1.png'),
        'width' => (int) env('SIRIKA_ROUTE_MAP_WIDTH', 3370),
        'height' => (int) env('SIRIKA_ROUTE_MAP_HEIGHT', 2384),
    ],

    'import' => [
        'max_file_kilobytes' => 10240,
        'max_rows' => 5000,
        'extensions' => ['xlsx', 'xls'],
        'mime_types' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/zip',
            'application/x-ole-storage',
            'application/octet-stream',
        ],
    ],
];
