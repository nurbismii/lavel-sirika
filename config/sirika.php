<?php

return [
    'seed_user_password' => env('SIRIKA_SEED_USER_PASSWORD'),

    'route_map' => [
        'key' => env('SIRIKA_ROUTE_MAP_KEY', 'vdni-road-map-v1'),
        'image_url' => env('SIRIKA_ROUTE_MAP_IMAGE_URL', '/images/maps/vdni-road-map-v1.png'),
        'width' => (int) env('SIRIKA_ROUTE_MAP_WIDTH', 3370),
        'height' => (int) env('SIRIKA_ROUTE_MAP_HEIGHT', 2384),
    ],
];
