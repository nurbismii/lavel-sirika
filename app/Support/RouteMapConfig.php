<?php

namespace App\Support;

use RuntimeException;

class RouteMapConfig
{
    public static function key(): string
    {
        return (string) config('sirika.route_map.key');
    }

    public static function imageUrl(): string
    {
        return (string) config('sirika.route_map.image_url');
    }

    public static function width(): int
    {
        return (int) config('sirika.route_map.width');
    }

    public static function height(): int
    {
        return (int) config('sirika.route_map.height');
    }

    public static function bounds(): array
    {
        return [[0, 0], [self::height(), self::width()]];
    }

    public static function toArray(): array
    {
        self::assertConfigured();

        return [
            'key' => self::key(),
            'image_url' => self::imageUrl(),
            'width' => self::width(),
            'height' => self::height(),
            'bounds' => self::bounds(),
        ];
    }

    private static function assertConfigured(): void
    {
        if (self::key() === '' || self::imageUrl() === '' || self::width() <= 0 || self::height() <= 0) {
            throw new RuntimeException('Konfigurasi peta rute belum valid.');
        }
    }
}
