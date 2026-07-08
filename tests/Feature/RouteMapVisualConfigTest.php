<?php

namespace Tests\Feature;

use Tests\TestCase;

class RouteMapVisualConfigTest extends TestCase
{
    /** @test */
    public function route_preview_uses_translucent_line_opacity_so_map_labels_remain_readable()
    {
        $script = file_get_contents(resource_path('js/route-map.js'));

        $this->assertStringContainsString('const ROUTE_PREVIEW_OPACITY = 0.45;', $script);
        $this->assertStringContainsString('opacity: options.opacity ?? ROUTE_PREVIEW_OPACITY', $script);
        $this->assertStringContainsString('opacity: ROUTE_PREVIEW_OPACITY', $script);
    }
}
