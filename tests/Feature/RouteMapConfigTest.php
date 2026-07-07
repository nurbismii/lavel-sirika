<?php

namespace Tests\Feature;

use App\Support\RouteMapConfig;
use Tests\TestCase;

class RouteMapConfigTest extends TestCase
{
    /** @test */
    public function route_map_config_matches_static_image_dimensions()
    {
        $path = public_path(ltrim(RouteMapConfig::imageUrl(), '/'));

        $this->assertFileExists($path);

        $size = getimagesize($path);

        $this->assertSame(RouteMapConfig::width(), $size[0]);
        $this->assertSame(RouteMapConfig::height(), $size[1]);
        $this->assertSame([[0, 0], [RouteMapConfig::height(), RouteMapConfig::width()]], RouteMapConfig::bounds());
    }
}
