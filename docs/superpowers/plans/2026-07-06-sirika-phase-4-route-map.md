# SIRIKA Phase 4 Route Map Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the Phase 4 VDNI route map: static map asset, Leaflet image overlay, manual road segment coordinate editor, route preview in admin permit pages, and route map data for valid QR scan results.

**Architecture:** Map metadata is read from config, coordinate validation and normalization live in route services, controllers stay thin, and Blade views receive ready-to-render DTOs. Coordinates are stored as local image pixel points in `road_segments.polyline_json`, then converted to Leaflet `[y, x]` pairs only at render time.

**Tech Stack:** PHP 7.4, Laravel 8, Blade, Alpine.js, Leaflet.js, MySQL/PostgreSQL, Laravel Mix.

## Global Constraints

- Baseline branch is `main` after Phase 3 merge and Phase 4 design spec commit.
- Use an isolated implementation branch named `sirika-phase-4-route-map`.
- Follow TDD: write the failing test, verify it fails, implement minimal code, verify it passes.
- Do not add new database tables for Phase 4.
- Store route coordinates only in `road_segments.polyline_json`.
- Store coordinates as image pixel points: `{ "x": number, "y": number }`.
- Render Leaflet points as `[y, x]` because `L.CRS.Simple` uses lat/lng ordering.
- Use the VDNI map image as a static asset under `public/images/maps`.
- Do not use OpenStreetMap, GPS, georeferencing, or external map tiles.
- Reject points outside configured map bounds. Do not clamp silently.
- `complete` route segments require at least 2 valid points.
- `draft` route segments require at least 1 valid point.
- Reset is the only supported way to clear all points.
- Only `admin_hr` and `super_admin` can edit, update, and reset road segment coordinates.
- `auditor` can view Master Rute and previews but cannot mutate coordinates.
- `security` cannot access the road segment editor.
- QR scan results include route map data only for result `valid`.
- Expired, revoked, inactive, and invalid scan results must not include route map data.
- A map rendering failure must not make QR scan validation fail.
- Keep Phase 3 token safety intact: do not expose raw QR tokens, hashes, NIK, contact number, or unnecessary personal data through route map DTOs.
- Keep UI copy concise and operational. Avoid tutorial text inside the app.

---

## File Structure

- Modify `config/sirika.php`
  - Add `route_map` metadata.
- Add `public/images/maps/vdni-road-map-v1.png`
  - Static image exported from the VDNI road map PDF.
- Add `app/Support/RouteMapConfig.php`
  - Central typed reader for map key, URL, dimensions, and bounds.
- Add `app/Services/Routes/RoadSegmentPolylineService.php`
  - Normalize, validate, summarize, and render segment polylines.
- Add `app/Services/Routes/PermitRouteMapService.php`
  - Build permit route map DTOs from ordered route segments.
- Add `app/Http/Requests/UpdateRoadSegmentPolylineRequest.php`
  - Validate editor submissions.
- Modify `app/Http/Controllers/RoadSegmentController.php`
  - Add editor, update, reset, and index summary.
- Add `app/Http/Controllers/PermitRouteMapController.php`
  - Show route map preview for one permit.
- Modify `app/Http/Controllers/PermitController.php`
  - Eager-load route segment summary needs.
- Modify `app/Services/Permits/PermitScanService.php`
  - Attach route map DTO for valid scan results only.
- Modify `app/Models/User.php`
  - Add Phase 4 route role mappings.
- Modify `routes/web.php`
  - Add road segment map routes and permit route map preview route.
- Modify `resources/js/app.js`
  - Import the route map module.
- Add `resources/js/route-map.js`
  - Register Alpine helpers for editor, preview, and scan map render.
- Modify `resources/css/app.css`
  - Add Leaflet map, editor, route preview, and scan map styles.
- Modify `resources/views/road-segments/index.blade.php`
  - Add status summary, all-segment preview, coordinate status, and actions.
- Add `resources/views/road-segments/map.blade.php`
  - Segment coordinate editor.
- Modify `resources/views/permits/index.blade.php`
  - Add route map preview action and route status column.
- Add `resources/views/permits/route-map/show.blade.php`
  - Permit route map preview page.
- Modify `resources/views/scan/index.blade.php`
  - Render route map when a valid scan returns map data.
- Modify `package.json` and `package-lock.json`
  - Add `leaflet`.
- Add `tests/Feature/RouteMapConfigTest.php`
  - Config and asset dimension checks.
- Add `tests/Feature/RoadSegmentPolylineServiceTest.php`
  - Service validation and DTO tests.
- Add `tests/Feature/RoadSegmentMapHttpTest.php`
  - Editor access, save, validation, and reset tests.
- Add `tests/Feature/PermitRouteMapServiceTest.php`
  - Permit route sequence and missing coordinate tests.
- Add `tests/Feature/PermitRouteMapHttpTest.php`
  - Admin permit route preview authorization and rendering tests.
- Modify `tests/Feature/PermitScanServiceTest.php`
  - Valid scan route map payload and expired scan no-map assertions.
- Modify `tests/Feature/ScanQrHttpTest.php`
  - JSON response assertions for valid scan route map payload.
- Modify `tests/Feature/SirikaModuleAccessTest.php`
  - Phase 4 navigation and authorization coverage.

---

### Task 1: Create Route Map Config and Polyline Service Foundation

**Files:**
- Modify: `config/sirika.php`
- Add: `app/Support/RouteMapConfig.php`
- Add: `app/Services/Routes/RoadSegmentPolylineService.php`
- Add: `tests/Feature/RoadSegmentPolylineServiceTest.php`

**Interfaces:**
- `RouteMapConfig::toArray(): array`
- `RouteMapConfig::key(): string`
- `RouteMapConfig::imageUrl(): string`
- `RouteMapConfig::width(): int`
- `RouteMapConfig::height(): int`
- `RouteMapConfig::bounds(): array`
- `RoadSegmentPolylineService::buildPayload(array $points, string $saveMode, ?User $user): array`
- `RoadSegmentPolylineService::status(?array $polyline): string`
- `RoadSegmentPolylineService::pointCount(?array $polyline): int`
- `RoadSegmentPolylineService::isComplete(?array $polyline): bool`
- `RoadSegmentPolylineService::toLeafletLatLngs(?array $polyline): array`
- `RoadSegmentPolylineService::toSegmentDto(RoadSegment $segment): array`
- `RoadSegmentPolylineService::summary($segments): array`

- [ ] **Step 1: Create implementation branch**

Run:

```bash
git switch -c sirika-phase-4-route-map
```

Expected: work continues on `sirika-phase-4-route-map`.

- [ ] **Step 2: Write failing service tests**

Create `tests/Feature/RoadSegmentPolylineServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\RoadSegment;
use App\Models\User;
use App\Services\Routes\RoadSegmentPolylineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RoadSegmentPolylineServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sirika.route_map' => [
                'key' => 'vdni-road-map-v1',
                'image_url' => '/images/maps/vdni-road-map-v1.png',
                'width' => 1600,
                'height' => 1000,
            ],
        ]);
    }

    /** @test */
    public function it_builds_complete_polyline_payload_from_valid_points()
    {
        $user = User::factory()->create();
        $service = app(RoadSegmentPolylineService::class);

        $payload = $service->buildPayload([
            ['x' => '10.1234', 'y' => '20.9876'],
            ['x' => 100, 'y' => 200],
        ], 'complete', $user);

        $this->assertSame(1, $payload['version']);
        $this->assertSame('vdni-road-map-v1', $payload['map_key']);
        $this->assertSame('complete', $payload['status']);
        $this->assertSame($user->id, $payload['updated_by']);
        $this->assertCount(2, $payload['points']);
        $this->assertSame(10.12, $payload['points'][0]['x']);
        $this->assertSame(20.99, $payload['points'][0]['y']);
    }

    /** @test */
    public function it_allows_draft_with_one_valid_point()
    {
        $service = app(RoadSegmentPolylineService::class);

        $payload = $service->buildPayload([
            ['x' => 10, 'y' => 20],
        ], 'draft', null);

        $this->assertSame('draft', $payload['status']);
        $this->assertCount(1, $payload['points']);
    }

    /** @test */
    public function it_rejects_complete_payload_with_less_than_two_points()
    {
        $this->expectException(ValidationException::class);

        app(RoadSegmentPolylineService::class)->buildPayload([
            ['x' => 10, 'y' => 20],
        ], 'complete', null);
    }

    /** @test */
    public function it_rejects_empty_draft_payload()
    {
        $this->expectException(ValidationException::class);

        app(RoadSegmentPolylineService::class)->buildPayload([], 'draft', null);
    }

    /** @test */
    public function it_rejects_points_outside_map_bounds()
    {
        $this->expectException(ValidationException::class);

        app(RoadSegmentPolylineService::class)->buildPayload([
            ['x' => 1601, 'y' => 20],
            ['x' => 100, 'y' => 200],
        ], 'complete', null);
    }

    /** @test */
    public function it_converts_stored_points_to_leaflet_lat_lng_pairs()
    {
        $service = app(RoadSegmentPolylineService::class);

        $latLngs = $service->toLeafletLatLngs([
            'points' => [
                ['x' => 10.5, 'y' => 20.25],
                ['x' => 30.75, 'y' => 40.5],
            ],
        ]);

        $this->assertSame([[20.25, 10.5], [40.5, 30.75]], $latLngs);
    }

    /** @test */
    public function it_summarizes_segment_coordinate_statuses()
    {
        $complete = RoadSegment::create([
            'code' => 'Y1',
            'name' => 'Y1',
            'start_location' => 'A',
            'end_location' => 'B',
            'status' => 'active',
            'polyline_json' => [
                'status' => 'complete',
                'points' => [
                    ['x' => 10, 'y' => 20],
                    ['x' => 30, 'y' => 40],
                ],
            ],
        ]);

        $draft = RoadSegment::create([
            'code' => 'D2',
            'name' => 'D2',
            'start_location' => 'B',
            'end_location' => 'C',
            'status' => 'active',
            'polyline_json' => [
                'status' => 'draft',
                'points' => [
                    ['x' => 50, 'y' => 60],
                ],
            ],
        ]);

        $empty = RoadSegment::create([
            'code' => 'H2',
            'name' => 'H2',
            'start_location' => 'C',
            'end_location' => 'D',
            'status' => 'active',
        ]);

        $summary = app(RoadSegmentPolylineService::class)->summary(collect([$complete, $draft, $empty]));

        $this->assertSame(3, $summary['total']);
        $this->assertSame(1, $summary['complete']);
        $this->assertSame(1, $summary['draft']);
        $this->assertSame(1, $summary['empty']);
    }
}
```

Run and verify failure:

```bash
php artisan test --filter=RoadSegmentPolylineServiceTest
```

- [ ] **Step 3: Add route map config**

Modify `config/sirika.php`:

```php
<?php

return [
    'seed_user_password' => env('SIRIKA_SEED_USER_PASSWORD'),

    'route_map' => [
        'key' => env('SIRIKA_ROUTE_MAP_KEY', 'vdni-road-map-v1'),
        'image_url' => env('SIRIKA_ROUTE_MAP_IMAGE_URL', '/images/maps/vdni-road-map-v1.png'),
        'width' => (int) env('SIRIKA_ROUTE_MAP_WIDTH', 1600),
        'height' => (int) env('SIRIKA_ROUTE_MAP_HEIGHT', 1000),
    ],
];
```

- [ ] **Step 4: Add config reader**

Create `app/Support/RouteMapConfig.php`:

```php
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
```

- [ ] **Step 5: Add polyline service**

Create `app/Services/Routes/RoadSegmentPolylineService.php`:

```php
<?php

namespace App\Services\Routes;

use App\Models\RoadSegment;
use App\Models\User;
use App\Support\RouteMapConfig;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RoadSegmentPolylineService
{
    public const STATUS_EMPTY = 'empty';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_COMPLETE = 'complete';

    public function buildPayload(array $points, string $saveMode, ?User $user): array
    {
        if (! in_array($saveMode, [self::STATUS_DRAFT, self::STATUS_COMPLETE], true)) {
            throw ValidationException::withMessages([
                'save_mode' => 'Mode simpan tidak valid.',
            ]);
        }

        $normalizedPoints = $this->normalizePoints($points);

        if (count($normalizedPoints) === 0) {
            throw ValidationException::withMessages([
                'points' => 'Minimal satu titik diperlukan. Gunakan reset untuk menghapus koordinat.',
            ]);
        }

        if ($saveMode === self::STATUS_COMPLETE && count($normalizedPoints) < 2) {
            throw ValidationException::withMessages([
                'points' => 'Status lengkap membutuhkan minimal dua titik.',
            ]);
        }

        return [
            'version' => 1,
            'map_key' => RouteMapConfig::key(),
            'status' => $saveMode,
            'points' => $normalizedPoints,
            'updated_by' => $user ? $user->id : null,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    public function status(?array $polyline): string
    {
        if (! $polyline || $this->pointCount($polyline) === 0) {
            return self::STATUS_EMPTY;
        }

        return ($polyline['status'] ?? self::STATUS_DRAFT) === self::STATUS_COMPLETE
            && $this->pointCount($polyline) >= 2
                ? self::STATUS_COMPLETE
                : self::STATUS_DRAFT;
    }

    public function pointCount(?array $polyline): int
    {
        return isset($polyline['points']) && is_array($polyline['points'])
            ? count($polyline['points'])
            : 0;
    }

    public function isComplete(?array $polyline): bool
    {
        return $this->status($polyline) === self::STATUS_COMPLETE;
    }

    public function toLeafletLatLngs(?array $polyline): array
    {
        if (! isset($polyline['points']) || ! is_array($polyline['points'])) {
            return [];
        }

        return collect($polyline['points'])
            ->filter(function ($point) {
                return isset($point['x'], $point['y']) && is_numeric($point['x']) && is_numeric($point['y']);
            })
            ->map(function ($point) {
                return [(float) $point['y'], (float) $point['x']];
            })
            ->values()
            ->all();
    }

    public function toSegmentDto(RoadSegment $segment): array
    {
        $polyline = $segment->polyline_json;

        return [
            'id' => $segment->id,
            'code' => $segment->code,
            'name' => $segment->name,
            'start_location' => $segment->start_location,
            'end_location' => $segment->end_location,
            'coordinate_status' => $this->status($polyline),
            'point_count' => $this->pointCount($polyline),
            'map_key' => $polyline['map_key'] ?? null,
            'points' => $polyline['points'] ?? [],
            'lat_lngs' => $this->toLeafletLatLngs($polyline),
        ];
    }

    public function summary($segments): array
    {
        $collection = $segments instanceof Collection ? $segments : collect($segments);

        return [
            'total' => $collection->count(),
            'complete' => $collection->filter(function (RoadSegment $segment) {
                return $this->isComplete($segment->polyline_json);
            })->count(),
            'draft' => $collection->filter(function (RoadSegment $segment) {
                return $this->status($segment->polyline_json) === self::STATUS_DRAFT;
            })->count(),
            'empty' => $collection->filter(function (RoadSegment $segment) {
                return $this->status($segment->polyline_json) === self::STATUS_EMPTY;
            })->count(),
        ];
    }

    private function normalizePoints(array $points): array
    {
        $width = RouteMapConfig::width();
        $height = RouteMapConfig::height();

        return collect($points)
            ->map(function ($point, $index) use ($width, $height) {
                if (! is_array($point) || ! isset($point['x'], $point['y']) || ! is_numeric($point['x']) || ! is_numeric($point['y'])) {
                    throw ValidationException::withMessages([
                        'points.' . $index => 'Format titik koordinat tidak valid.',
                    ]);
                }

                $x = round((float) $point['x'], 2);
                $y = round((float) $point['y'], 2);

                if ($x < 0 || $x > $width || $y < 0 || $y > $height) {
                    throw ValidationException::withMessages([
                        'points.' . $index => 'Titik koordinat berada di luar batas peta.',
                    ]);
                }

                return [
                    'x' => $x,
                    'y' => $y,
                ];
            })
            ->values()
            ->all();
    }
}
```

- [ ] **Step 6: Verify service tests**

Run:

```bash
php artisan test --filter=RoadSegmentPolylineServiceTest
```

- [ ] **Step 7: Commit Task 1**

Run:

```bash
git add config/sirika.php app/Support/RouteMapConfig.php app/Services/Routes/RoadSegmentPolylineService.php tests/Feature/RoadSegmentPolylineServiceTest.php
git commit -m "feat: add route map polyline foundation"
```

---

### Task 2: Add Static VDNI Map Asset and Leaflet Frontend Foundation

**Files:**
- Add: `public/images/maps/vdni-road-map-v1.png`
- Add: `tests/Feature/RouteMapConfigTest.php`
- Modify: `config/sirika.php`
- Modify: `package.json`
- Modify: `package-lock.json`
- Modify: `resources/js/app.js`
- Add: `resources/js/route-map.js`
- Modify: `resources/css/app.css`

**Interfaces:**
- `window.sirikaRoutePreview({ map, segments })`
- `window.sirikaRoadSegmentEditor({ map, initialPoints, segmentCode })`
- `window.sirikaRenderRouteMap(element, map, segments, options)`

- [ ] **Step 1: Install Leaflet**

Run:

```bash
npm install leaflet@1.9.4 --save
```

Expected: `package.json` and `package-lock.json` include `leaflet`.

- [ ] **Step 2: Export the VDNI road map PDF to PNG**

Source file:

```text
C:\Users\New Owner\Downloads\附件4：VDNI道路示意图.pdf
```

Create the asset directory:

```bash
mkdir public\images\maps
```

Run with Poppler when `pdftoppm` is available on `PATH`:

```bash
pdftoppm -png -singlefile -r 180 "C:\Users\New Owner\Downloads\附件4：VDNI道路示意图.pdf" "public\images\maps\vdni-road-map-v1"
```

If `pdftoppm` is not available on `PATH`, call `codex_app.load_workspace_dependencies`, locate bundled Poppler, and run the same command using the absolute `pdftoppm.exe` path.

Check the image dimensions:

```bash
php -r "$s=getimagesize('public/images/maps/vdni-road-map-v1.png'); echo $s[0].PHP_EOL.$s[1].PHP_EOL;"
```

Update `config/sirika.php` so `width` and `height` match the command output exactly. Keep `key` as `vdni-road-map-v1`.

- [ ] **Step 3: Write failing asset config test**

Create `tests/Feature/RouteMapConfigTest.php`:

```php
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
```

Run:

```bash
php artisan test --filter=RouteMapConfigTest
```

- [ ] **Step 4: Add frontend route map module**

Create `resources/js/route-map.js`:

```js
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

function createBaseMap(element, mapConfig, options = {}) {
    if (!element || !mapConfig) {
        return null;
    }

    const bounds = [[0, 0], [Number(mapConfig.height), Number(mapConfig.width)]];
    const map = L.map(element, {
        crs: L.CRS.Simple,
        minZoom: options.minZoom || -2,
        maxZoom: options.maxZoom || 2,
        zoomControl: options.zoomControl !== false,
        attributionControl: false,
    });

    L.imageOverlay(mapConfig.image_url, bounds).addTo(map);
    map.fitBounds(bounds);
    map.setMaxBounds(bounds);

    return { leaflet: map, bounds };
}

function drawSegments(instance, segments, options = {}) {
    if (!instance || !Array.isArray(segments)) {
        return [];
    }

    const color = options.color || '#1e4fd6';

    return segments
        .filter((segment) => Array.isArray(segment.lat_lngs) && segment.lat_lngs.length >= 2)
        .map((segment) => {
            const line = L.polyline(segment.lat_lngs, {
                color,
                weight: options.weight || 4,
                opacity: options.opacity || 0.9,
            }).addTo(instance.leaflet);

            if (segment.code) {
                line.bindTooltip(segment.code, { permanent: false, direction: 'top' });
            }

            return line;
        });
}

window.sirikaRenderRouteMap = function (element, mapConfig, segments, options = {}) {
    const instance = createBaseMap(element, mapConfig, options);

    if (!instance) {
        return null;
    }

    drawSegments(instance, segments, options);

    return instance.leaflet;
};

window.sirikaRoutePreview = function ({ map, segments }) {
    return {
        map,
        segments,
        leaflet: null,

        init() {
            this.leaflet = window.sirikaRenderRouteMap(this.$refs.map, this.map, this.segments, {
                color: '#166534',
                weight: 4,
            });
        },
    };
};

window.sirikaRoadSegmentEditor = function ({ map, initialPoints, segmentCode }) {
    return {
        map,
        segmentCode,
        points: Array.isArray(initialPoints) ? initialPoints : [],
        leaflet: null,
        layerGroup: null,
        saveMode: 'draft',
        dirty: false,

        init() {
            const instance = createBaseMap(this.$refs.map, this.map, { maxZoom: 3 });
            this.leaflet = instance.leaflet;
            this.layerGroup = L.layerGroup().addTo(this.leaflet);
            this.leaflet.on('click', (event) => this.addPoint(event.latlng));
            this.redraw();

            window.addEventListener('beforeunload', (event) => {
                if (!this.dirty) {
                    return;
                }

                event.preventDefault();
                event.returnValue = '';
            });
        },

        addPoint(latlng) {
            this.points.push({
                x: Number(latlng.lng.toFixed(2)),
                y: Number(latlng.lat.toFixed(2)),
            });
            this.dirty = true;
            this.redraw();
        },

        undoPoint() {
            if (!this.points.length) {
                return;
            }

            this.points.pop();
            this.dirty = true;
            this.redraw();
        },

        clearPoints() {
            this.points = [];
            this.dirty = true;
            this.redraw();
        },

        submit(mode) {
            this.saveMode = mode;
            this.dirty = false;
            this.$nextTick(() => this.$refs.form.submit());
        },

        latLngs() {
            return this.points.map((point) => [Number(point.y), Number(point.x)]);
        },

        pointsJson() {
            return JSON.stringify(this.points);
        },

        redraw() {
            this.layerGroup.clearLayers();

            const latLngs = this.latLngs();

            if (latLngs.length >= 2) {
                L.polyline(latLngs, {
                    color: '#1e4fd6',
                    weight: 5,
                    opacity: 0.95,
                }).addTo(this.layerGroup);
            }

            latLngs.forEach((latLng, index) => {
                L.circleMarker(latLng, {
                    radius: 6,
                    color: '#122033',
                    fillColor: '#ffffff',
                    fillOpacity: 1,
                    weight: 2,
                })
                    .bindTooltip(String(index + 1), { permanent: true, direction: 'center', className: 'route-point-label' })
                    .addTo(this.layerGroup);
            });
        },
    };
};
```

Modify `resources/js/app.js`:

```js
require('./bootstrap');

import Alpine from 'alpinejs';
import { Html5Qrcode } from 'html5-qrcode';
import './route-map';
```

Keep the existing scanner component in the same file.

- [ ] **Step 5: Add map styles**

Append to `resources/css/app.css`:

```css
.route-map-panel {
    display: grid;
    gap: 16px;
}

.route-map-canvas {
    width: 100%;
    min-height: 520px;
    border: 1px solid var(--sirika-border);
    background: #ffffff;
    overflow: hidden;
}

.route-map-canvas--compact {
    min-height: 360px;
}

.route-editor-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    gap: 20px;
    align-items: start;
}

.route-editor-side {
    display: grid;
    gap: 14px;
}

.route-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.route-stat {
    border: 1px solid var(--sirika-border);
    background: var(--sirika-surface);
    border-radius: 8px;
    padding: 14px;
}

.route-stat__value {
    display: block;
    font-size: 24px;
    font-weight: 700;
    line-height: 1.1;
}

.route-warning {
    border-left: 4px solid var(--sirika-warning);
    background: #fff7ed;
    color: var(--sirika-warning);
    padding: 12px 14px;
}

.route-point-label {
    border: 0;
    background: transparent;
    color: #122033;
    font-weight: 700;
    box-shadow: none;
}

@media (max-width: 960px) {
    .route-editor-layout,
    .route-stat-grid {
        grid-template-columns: 1fr;
    }

    .route-map-canvas {
        min-height: 360px;
    }
}
```

- [ ] **Step 6: Build frontend assets**

Run:

```bash
npm.cmd run dev
```

Expected: `public/js/app.js` and `public/css/app.css` compile without Webpack errors.

- [ ] **Step 7: Verify Task 2 tests**

Run:

```bash
php artisan test --filter=RouteMapConfigTest
```

- [ ] **Step 8: Commit Task 2**

Run:

```bash
git add package.json package-lock.json config/sirika.php public/images/maps/vdni-road-map-v1.png resources/js/app.js resources/js/route-map.js resources/css/app.css public/js/app.js public/css/app.css tests/Feature/RouteMapConfigTest.php
git commit -m "feat: add vdni route map frontend foundation"
```

---

### Task 3: Implement Road Segment Coordinate Editor HTTP Flow

**Files:**
- Add: `app/Http/Requests/UpdateRoadSegmentPolylineRequest.php`
- Modify: `app/Http/Controllers/RoadSegmentController.php`
- Modify: `app/Models/User.php`
- Modify: `routes/web.php`
- Add: `resources/views/road-segments/map.blade.php`
- Add: `tests/Feature/RoadSegmentMapHttpTest.php`
- Modify: `tests/Feature/SirikaModuleAccessTest.php`

**Interfaces:**
- Route `road-segments.map`
- Route `road-segments.map.update`
- Route `road-segments.map.reset`
- Request field `points_json`
- Request field `save_mode`

- [ ] **Step 1: Write failing HTTP tests**

Create `tests/Feature/RoadSegmentMapHttpTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\RoadSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoadSegmentMapHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sirika.route_map' => [
                'key' => 'vdni-road-map-v1',
                'image_url' => '/images/maps/vdni-road-map-v1.png',
                'width' => 1600,
                'height' => 1000,
            ],
        ]);
    }

    /** @test */
    public function admin_hr_can_open_road_segment_map_editor()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_HR, 'status' => User::STATUS_ACTIVE]);
        $segment = $this->segment();

        $response = $this->actingAs($admin)->get(route('road-segments.map', $segment));

        $response->assertOk();
        $response->assertSee('Editor Koordinat');
        $response->assertSee($segment->code);
    }

    /** @test */
    public function auditor_cannot_save_or_reset_road_segment_map()
    {
        $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR, 'status' => User::STATUS_ACTIVE]);
        $segment = $this->segment();

        $this->actingAs($auditor)
            ->post(route('road-segments.map.update', $segment), [
                'save_mode' => 'complete',
                'points_json' => json_encode([
                    ['x' => 10, 'y' => 20],
                    ['x' => 30, 'y' => 40],
                ]),
            ])
            ->assertForbidden();

        $this->actingAs($auditor)
            ->delete(route('road-segments.map.reset', $segment))
            ->assertForbidden();
    }

    /** @test */
    public function security_cannot_open_road_segment_map_editor()
    {
        $security = User::factory()->create(['role' => User::ROLE_SECURITY, 'status' => User::STATUS_ACTIVE]);

        $this->actingAs($security)
            ->get(route('road-segments.map', $this->segment()))
            ->assertForbidden();
    }

    /** @test */
    public function admin_hr_can_save_complete_polyline()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_HR, 'status' => User::STATUS_ACTIVE]);
        $segment = $this->segment();

        $response = $this->actingAs($admin)
            ->post(route('road-segments.map.update', $segment), [
                'save_mode' => 'complete',
                'points_json' => json_encode([
                    ['x' => 10, 'y' => 20],
                    ['x' => 30, 'y' => 40],
                ]),
            ]);

        $response->assertRedirect(route('road-segments.map', $segment));
        $this->assertSame('complete', $segment->fresh()->polyline_json['status']);
        $this->assertSame($admin->id, $segment->fresh()->polyline_json['updated_by']);
    }

    /** @test */
    public function complete_polyline_requires_two_points()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_HR, 'status' => User::STATUS_ACTIVE]);
        $segment = $this->segment();

        $this->actingAs($admin)
            ->from(route('road-segments.map', $segment))
            ->post(route('road-segments.map.update', $segment), [
                'save_mode' => 'complete',
                'points_json' => json_encode([
                    ['x' => 10, 'y' => 20],
                ]),
            ])
            ->assertRedirect(route('road-segments.map', $segment))
            ->assertSessionHasErrors('points');
    }

    /** @test */
    public function out_of_bounds_points_are_rejected()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_HR, 'status' => User::STATUS_ACTIVE]);
        $segment = $this->segment();

        $this->actingAs($admin)
            ->from(route('road-segments.map', $segment))
            ->post(route('road-segments.map.update', $segment), [
                'save_mode' => 'complete',
                'points_json' => json_encode([
                    ['x' => 1601, 'y' => 20],
                    ['x' => 30, 'y' => 40],
                ]),
            ])
            ->assertRedirect(route('road-segments.map', $segment))
            ->assertSessionHasErrors('points.0.x');
    }

    /** @test */
    public function admin_hr_can_reset_polyline()
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_HR, 'status' => User::STATUS_ACTIVE]);
        $segment = $this->segment([
            'polyline_json' => [
                'status' => 'complete',
                'points' => [
                    ['x' => 10, 'y' => 20],
                    ['x' => 30, 'y' => 40],
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->delete(route('road-segments.map.reset', $segment))
            ->assertRedirect(route('road-segments.index'));

        $this->assertNull($segment->fresh()->polyline_json);
    }

    private function segment(array $overrides = []): RoadSegment
    {
        return RoadSegment::create(array_merge([
            'code' => 'Y1',
            'name' => 'Y1',
            'start_location' => 'Start',
            'end_location' => 'End',
            'status' => 'active',
        ], $overrides));
    }
}
```

Run:

```bash
php artisan test --filter=RoadSegmentMapHttpTest
```

- [ ] **Step 2: Add request validation**

Create `app/Http/Requests/UpdateRoadSegmentPolylineRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Support\RouteMapConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRoadSegmentPolylineRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'save_mode' => ['required', 'in:draft,complete'],
            'points_json' => ['required', 'json'],
            'points' => ['array', 'max:200'],
            'points.*.x' => ['required', 'numeric', 'min:0', 'max:' . RouteMapConfig::width()],
            'points.*.y' => ['required', 'numeric', 'min:0', 'max:' . RouteMapConfig::height()],
        ];
    }

    protected function prepareForValidation()
    {
        $decoded = json_decode((string) $this->input('points_json'), true);

        $this->merge([
            'points' => is_array($decoded) ? $decoded : [],
        ]);
    }

    public function withValidator($validator)
    {
        $validator->after(function (Validator $validator) {
            $points = $this->input('points', []);

            if (count($points) === 0) {
                $validator->errors()->add('points', 'Minimal satu titik diperlukan. Gunakan reset untuk menghapus koordinat.');
            }

            if ($this->input('save_mode') === 'complete' && count($points) < 2) {
                $validator->errors()->add('points', 'Status lengkap membutuhkan minimal dua titik.');
            }
        });
    }
}
```

- [ ] **Step 3: Add role mappings**

Modify `app/Models/User.php` inside `routeRoles()`:

```php
'road-segments.index' => [
    self::ROLE_ADMIN_HR,
    self::ROLE_AUDITOR,
],
'road-segments.map' => [
    self::ROLE_ADMIN_HR,
    self::ROLE_AUDITOR,
],
'road-segments.map.update' => [
    self::ROLE_ADMIN_HR,
],
'road-segments.map.reset' => [
    self::ROLE_ADMIN_HR,
],
```

`super_admin` remains allowed through `canAccessRoute()`.

- [ ] **Step 4: Add routes**

Modify `routes/web.php`:

```php
Route::middleware('role:' . implode(',', User::rolesForRoute('road-segments.index')))->group(function () {
    Route::get('/road-segments', [RoadSegmentController::class, 'index'])->name('road-segments.index');
});

Route::get('/road-segments/{roadSegment}/map', [RoadSegmentController::class, 'map'])
    ->middleware('role:' . implode(',', User::rolesForRoute('road-segments.map')))
    ->name('road-segments.map');

Route::post('/road-segments/{roadSegment}/map', [RoadSegmentController::class, 'updateMap'])
    ->middleware('role:' . implode(',', User::rolesForRoute('road-segments.map.update')))
    ->name('road-segments.map.update');

Route::delete('/road-segments/{roadSegment}/map', [RoadSegmentController::class, 'resetMap'])
    ->middleware('role:' . implode(',', User::rolesForRoute('road-segments.map.reset')))
    ->name('road-segments.map.reset');
```

- [ ] **Step 5: Update controller**

Modify `app/Http/Controllers/RoadSegmentController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRoadSegmentPolylineRequest;
use App\Models\RoadSegment;
use App\Services\Routes\RoadSegmentPolylineService;
use App\Support\RouteMapConfig;

class RoadSegmentController extends Controller
{
    public function index(RoadSegmentPolylineService $polylines)
    {
        $allSegments = RoadSegment::query()
            ->orderBy('code')
            ->get();

        return view('road-segments.index', [
            'segments' => RoadSegment::query()
                ->orderBy('code')
                ->paginate(30),
            'summary' => $polylines->summary($allSegments),
            'routeMap' => RouteMapConfig::toArray(),
            'mapSegments' => $allSegments
                ->map(function (RoadSegment $segment) use ($polylines) {
                    return $polylines->toSegmentDto($segment);
                })
                ->filter(function (array $segment) {
                    return $segment['coordinate_status'] === RoadSegmentPolylineService::STATUS_COMPLETE;
                })
                ->values()
                ->all(),
            'canEditMap' => request()->user()->canAccessRoute('road-segments.map.update'),
        ]);
    }

    public function map(RoadSegment $roadSegment, RoadSegmentPolylineService $polylines)
    {
        return view('road-segments.map', [
            'segment' => $roadSegment,
            'routeMap' => RouteMapConfig::toArray(),
            'segmentMap' => $polylines->toSegmentDto($roadSegment),
            'canEditMap' => request()->user()->canAccessRoute('road-segments.map.update'),
        ]);
    }

    public function updateMap(
        UpdateRoadSegmentPolylineRequest $request,
        RoadSegment $roadSegment,
        RoadSegmentPolylineService $polylines
    ) {
        $roadSegment->update([
            'polyline_json' => $polylines->buildPayload(
                $request->input('points', []),
                $request->input('save_mode'),
                $request->user()
            ),
        ]);

        return redirect()
            ->route('road-segments.map', $roadSegment)
            ->with('status', 'Koordinat rute berhasil disimpan.');
    }

    public function resetMap(RoadSegment $roadSegment)
    {
        $roadSegment->update([
            'polyline_json' => null,
        ]);

        return redirect()
            ->route('road-segments.index')
            ->with('status', 'Koordinat rute berhasil direset.');
    }
}
```

- [ ] **Step 6: Add editor Blade**

Create `resources/views/road-segments/map.blade.php`:

```blade
@extends('layouts.app')

@php
    $pageTitle = 'Editor Koordinat Rute';
    $pageDescription = $segment->code . ' - ' . $segment->name;
@endphp

@section('content')
    <section
        class="page-section panel"
        x-data="sirikaRoadSegmentEditor({
            map: @js($routeMap),
            initialPoints: @js($segmentMap['points']),
            segmentCode: @js($segment->code)
        })"
    >
        <div class="panel-body route-editor-layout">
            <div class="route-map-panel">
                <div x-ref="map" class="route-map-canvas"></div>
            </div>

            <aside class="route-editor-side">
                <div>
                    <h2 class="panel-title">{{ $segment->code }}</h2>
                    <p class="panel-subtitle">{{ $segment->name }}</p>
                </div>

                <dl class="scan-result__details">
                    <div>
                        <dt>Awal</dt>
                        <dd>{{ $segment->start_location ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt>Akhir</dt>
                        <dd>{{ $segment->end_location ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd>{{ $segmentMap['coordinate_status'] }}</dd>
                    </div>
                    <div>
                        <dt>Titik</dt>
                        <dd x-text="points.length"></dd>
                    </div>
                </dl>

                @if ($canEditMap)
                    <form
                        x-ref="form"
                        method="POST"
                        action="{{ route('road-segments.map.update', $segment) }}"
                        class="form-stack"
                    >
                        @csrf
                        <input type="hidden" name="save_mode" x-bind:value="saveMode">
                        <input type="hidden" name="points_json" x-bind:value="pointsJson()">

                        <div class="quick-actions">
                            <button class="button button-primary" type="button" x-on:click="submit('complete')">Simpan Complete</button>
                            <button class="button" type="button" x-on:click="submit('draft')">Simpan Draft</button>
                            <button class="button" type="button" x-on:click="undoPoint" x-bind:disabled="points.length === 0">Undo</button>
                            <button class="button" type="button" x-on:click="clearPoints" x-bind:disabled="points.length === 0">Hapus Titik</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('road-segments.map.reset', $segment) }}">
                        @csrf
                        @method('DELETE')
                        <button class="button" type="submit">Reset Koordinat</button>
                    </form>
                @endif

                <a class="button" href="{{ route('road-segments.index') }}">Kembali</a>
            </aside>
        </div>
    </section>
@endsection
```

- [ ] **Step 7: Verify HTTP tests**

Run:

```bash
php artisan test --filter=RoadSegmentMapHttpTest
php artisan test --filter=SirikaModuleAccessTest
```

- [ ] **Step 8: Commit Task 3**

Run:

```bash
git add app/Http/Requests/UpdateRoadSegmentPolylineRequest.php app/Http/Controllers/RoadSegmentController.php app/Models/User.php routes/web.php resources/views/road-segments/map.blade.php tests/Feature/RoadSegmentMapHttpTest.php tests/Feature/SirikaModuleAccessTest.php
git commit -m "feat: add road segment coordinate editor"
```

---

### Task 4: Upgrade Master Rute Index with Status Summary and Preview

**Files:**
- Modify: `resources/views/road-segments/index.blade.php`
- Modify: `resources/css/app.css`
- Add assertions to: `tests/Feature/RoadSegmentMapHttpTest.php`

**Interfaces:**
- Index receives `$summary`, `$routeMap`, `$mapSegments`, `$canEditMap`.

- [ ] **Step 1: Add failing index assertions**

Append to `tests/Feature/RoadSegmentMapHttpTest.php`:

```php
/** @test */
public function road_segment_index_shows_coordinate_summary_and_edit_action()
{
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN_HR, 'status' => User::STATUS_ACTIVE]);
    $this->segment([
        'polyline_json' => [
            'status' => 'complete',
            'points' => [
                ['x' => 10, 'y' => 20],
                ['x' => 30, 'y' => 40],
            ],
        ],
    ]);

    $response = $this->actingAs($admin)->get(route('road-segments.index'));

    $response->assertOk();
    $response->assertSee('Ringkasan Koordinat');
    $response->assertSee('Lengkap');
    $response->assertSee('Edit Peta');
}
```

Run:

```bash
php artisan test --filter=road_segment_index_shows_coordinate_summary_and_edit_action
```

- [ ] **Step 2: Replace road segment index view**

Modify `resources/views/road-segments/index.blade.php` so it contains:

```blade
@extends('layouts.app')

@php
    $pageTitle = 'Master Segmen Rute';
    $pageDescription = 'Koordinat rute internal VDNI berdasarkan peta resmi.';
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body">
            <div class="route-map-panel">
                <div>
                    <h2 class="panel-title">Ringkasan Koordinat</h2>
                </div>

                <div class="route-stat-grid">
                    <div class="route-stat">
                        <span class="route-stat__value">{{ $summary['total'] }}</span>
                        <span>Total</span>
                    </div>
                    <div class="route-stat">
                        <span class="route-stat__value">{{ $summary['complete'] }}</span>
                        <span>Lengkap</span>
                    </div>
                    <div class="route-stat">
                        <span class="route-stat__value">{{ $summary['draft'] }}</span>
                        <span>Draft</span>
                    </div>
                    <div class="route-stat">
                        <span class="route-stat__value">{{ $summary['empty'] }}</span>
                        <span>Belum Dibuat</span>
                    </div>
                </div>

                <div
                    x-data="sirikaRoutePreview({
                        map: @js($routeMap),
                        segments: @js($mapSegments)
                    })"
                >
                    <div x-ref="map" class="route-map-canvas route-map-canvas--compact"></div>
                </div>
            </div>

            <div class="table-wrap layout-gap">
                <table>
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Lokasi Awal</th>
                            <th>Lokasi Akhir</th>
                            <th>Status Data</th>
                            <th>Status Koordinat</th>
                            <th>Titik</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($segments as $segment)
                            @php
                                $polyline = $segment->polyline_json;
                                $pointCount = isset($polyline['points']) && is_array($polyline['points']) ? count($polyline['points']) : 0;
                                $coordinateStatus = $pointCount === 0
                                    ? 'empty'
                                    : (($polyline['status'] ?? 'draft') === 'complete' && $pointCount >= 2 ? 'complete' : 'draft');
                            @endphp
                            <tr>
                                <td><strong>{{ $segment->code }}</strong></td>
                                <td>{{ $segment->name }}</td>
                                <td>{{ $segment->start_location }}</td>
                                <td>{{ $segment->end_location }}</td>
                                <td><span class="status-pill">{{ $segment->status }}</span></td>
                                <td><span class="status-pill">{{ $coordinateStatus }}</span></td>
                                <td>{{ $pointCount }}</td>
                                <td>
                                    <div class="table-actions">
                                        <a class="button" href="{{ route('road-segments.map', $segment) }}">
                                            {{ $canEditMap ? 'Edit Peta' : 'Lihat Peta' }}
                                        </a>
                                        @if ($canEditMap && $pointCount > 0)
                                            <form method="POST" action="{{ route('road-segments.map.reset', $segment) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="button" type="submit">Reset</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">Belum ada data segmen rute.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="layout-gap">
                {{ $segments->links() }}
            </div>
        </div>
    </section>
@endsection
```

- [ ] **Step 3: Build frontend assets and verify index**

Run:

```bash
npm.cmd run dev
php artisan test --filter=RoadSegmentMapHttpTest
```

- [ ] **Step 4: Commit Task 4**

Run:

```bash
git add resources/views/road-segments/index.blade.php resources/css/app.css public/css/app.css tests/Feature/RoadSegmentMapHttpTest.php
git commit -m "feat: enhance master route map overview"
```

---

### Task 5: Add Permit Route Map DTO and Admin Preview Page

**Files:**
- Add: `app/Services/Routes/PermitRouteMapService.php`
- Add: `app/Http/Controllers/PermitRouteMapController.php`
- Modify: `app/Http/Controllers/PermitController.php`
- Modify: `app/Models/User.php`
- Modify: `routes/web.php`
- Modify: `resources/views/permits/index.blade.php`
- Add: `resources/views/permits/route-map/show.blade.php`
- Add: `tests/Feature/PermitRouteMapServiceTest.php`
- Add: `tests/Feature/PermitRouteMapHttpTest.php`

**Interfaces:**
- `PermitRouteMapService::forPermit(VehiclePermit $permit): array`
- Route `permits.route-map.show`

- [ ] **Step 1: Write failing service tests**

Create `tests/Feature/PermitRouteMapServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ParkingLocation;
use App\Models\RoadSegment;
use App\Models\Vehicle;
use App\Models\VehiclePermit;
use App\Services\Routes\PermitRouteMapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitRouteMapServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sirika.route_map' => [
                'key' => 'vdni-road-map-v1',
                'image_url' => '/images/maps/vdni-road-map-v1.png',
                'width' => 1600,
                'height' => 1000,
            ],
        ]);
    }

    /** @test */
    public function it_builds_route_map_data_in_permit_route_sequence()
    {
        $permit = $this->permit();
        $first = $this->segment('Y1', [
            'status' => 'complete',
            'points' => [
                ['x' => 10, 'y' => 20],
                ['x' => 30, 'y' => 40],
            ],
        ]);
        $second = $this->segment('D2', [
            'status' => 'complete',
            'points' => [
                ['x' => 50, 'y' => 60],
                ['x' => 70, 'y' => 80],
            ],
        ]);

        $permit->routeSegments()->attach($second->id, ['sequence' => 2]);
        $permit->routeSegments()->attach($first->id, ['sequence' => 1]);

        $dto = app(PermitRouteMapService::class)->forPermit($permit->fresh());

        $this->assertSame('Y1 -> D2', $dto['route_label']);
        $this->assertSame(['Y1', 'D2'], collect($dto['segments'])->pluck('code')->all());
        $this->assertSame([], $dto['missing_segments']);
        $this->assertSame([[20.0, 10.0], [40.0, 30.0]], $dto['segments'][0]['lat_lngs']);
    }

    /** @test */
    public function it_marks_segments_without_complete_coordinates_as_missing()
    {
        $permit = $this->permit();
        $complete = $this->segment('Y1', [
            'status' => 'complete',
            'points' => [
                ['x' => 10, 'y' => 20],
                ['x' => 30, 'y' => 40],
            ],
        ]);
        $missing = $this->segment('H2', null);

        $permit->routeSegments()->attach($complete->id, ['sequence' => 1]);
        $permit->routeSegments()->attach($missing->id, ['sequence' => 2]);

        $dto = app(PermitRouteMapService::class)->forPermit($permit->fresh());

        $this->assertSame(['H2'], $dto['missing_segments']);
        $this->assertCount(1, $dto['segments']);
        $this->assertFalse($dto['is_complete']);
    }

    private function permit(): VehiclePermit
    {
        $employee = Employee::create(['nik' => 'EMP-001', 'name' => 'Test User', 'status' => 'active']);
        $vehicle = Vehicle::create(['employee_id' => $employee->id, 'plate_number' => 'DT 1001 AA', 'vehicle_type' => 'truck', 'status' => 'active']);
        $parking = ParkingLocation::create(['code' => 'P1', 'name' => 'Parkir 1', 'status' => 'active']);

        return VehiclePermit::create([
            'employee_id' => $employee->id,
            'vehicle_id' => $vehicle->id,
            'parking_location_id' => $parking->id,
            'permit_color' => 'Blue',
            'valid_from' => now()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'status' => VehiclePermit::STATUS_ACTIVE,
            'source' => 'test',
            'route_raw' => 'Y1-D2',
        ]);
    }

    private function segment(string $code, ?array $polyline): RoadSegment
    {
        return RoadSegment::create([
            'code' => $code,
            'name' => $code,
            'start_location' => 'Start ' . $code,
            'end_location' => 'End ' . $code,
            'status' => 'active',
            'polyline_json' => $polyline ? array_merge([
                'version' => 1,
                'map_key' => 'vdni-road-map-v1',
                'updated_by' => null,
                'updated_at' => now()->toIso8601String(),
            ], $polyline) : null,
        ]);
    }
}
```

Run:

```bash
php artisan test --filter=PermitRouteMapServiceTest
```

- [ ] **Step 2: Add permit route map service**

Create `app/Services/Routes/PermitRouteMapService.php`:

```php
<?php

namespace App\Services\Routes;

use App\Models\RoadSegment;
use App\Models\VehiclePermit;
use App\Support\RouteMapConfig;

class PermitRouteMapService
{
    private RoadSegmentPolylineService $polylines;

    public function __construct(RoadSegmentPolylineService $polylines)
    {
        $this->polylines = $polylines;
    }

    public function forPermit(VehiclePermit $permit): array
    {
        $permit->loadMissing('routeSegments');

        $allSegments = $permit->routeSegments;
        $completeSegments = [];
        $missingSegments = [];

        foreach ($allSegments as $segment) {
            if (! $segment instanceof RoadSegment) {
                continue;
            }

            if ($this->polylines->isComplete($segment->polyline_json)) {
                $dto = $this->polylines->toSegmentDto($segment);
                $dto['sequence'] = (int) optional($segment->pivot)->sequence;
                $completeSegments[] = $dto;
                continue;
            }

            $missingSegments[] = $segment->code;
        }

        return [
            'map' => RouteMapConfig::toArray(),
            'route_label' => $allSegments->pluck('code')->implode(' -> '),
            'segments' => $completeSegments,
            'missing_segments' => $missingSegments,
            'has_route' => $allSegments->isNotEmpty(),
            'is_complete' => $allSegments->isNotEmpty() && count($missingSegments) === 0,
        ];
    }
}
```

- [ ] **Step 3: Write failing HTTP tests**

Create `tests/Feature/PermitRouteMapHttpTest.php` with assertions:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermitRouteMapHttpTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_hr_can_open_permit_route_map_preview_from_permit_list()
    {
        $this->artisan('db:seed');

        $admin = User::where('role', User::ROLE_ADMIN_HR)->first();

        $response = $this->actingAs($admin)->get(route('permits.index'));

        $response->assertOk();
        $response->assertSee('Rute');
        $response->assertSee('Lihat Rute');
    }

    /** @test */
    public function security_cannot_open_admin_permit_route_map_preview()
    {
        $this->artisan('db:seed');

        $security = User::where('role', User::ROLE_SECURITY)->first();

        $this->actingAs($security)
            ->get('/permits/1/route-map')
            ->assertForbidden();
    }
}
```

The first test relies on seeded permit data only if the current seeders create permits. If no permit exists after seeding, build one inside the test using the helper pattern from `PermitRouteMapServiceTest`.

- [ ] **Step 4: Add role mappings and route**

Modify `app/Models/User.php`:

```php
'permits.route-map.show' => [
    self::ROLE_ADMIN_HR,
],
```

Modify `routes/web.php`:

```php
use App\Http\Controllers\PermitRouteMapController;

Route::get('/permits/{permit}/route-map', [PermitRouteMapController::class, 'show'])
    ->middleware('role:' . implode(',', User::rolesForRoute('permits.route-map.show')))
    ->name('permits.route-map.show');
```

- [ ] **Step 5: Add controller**

Create `app/Http/Controllers/PermitRouteMapController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\VehiclePermit;
use App\Services\Routes\PermitRouteMapService;

class PermitRouteMapController extends Controller
{
    public function show(VehiclePermit $permit, PermitRouteMapService $routeMaps)
    {
        $permit->loadMissing(['employee', 'vehicle', 'parkingLocation', 'routeSegments']);

        return view('permits.route-map.show', [
            'permit' => $permit,
            'routeMapData' => $routeMaps->forPermit($permit),
        ]);
    }
}
```

- [ ] **Step 6: Update permit list**

Modify `app/Http/Controllers/PermitController.php`:

```php
'permits' => VehiclePermit::with([
    'employee',
    'vehicle',
    'parkingLocation',
    'activeToken',
    'latestToken',
    'routeSegments',
])
    ->latest()
    ->paginate(25),
```

Modify `resources/views/permits/index.blade.php`:

- Add a `Rute` column before `Aksi QR`.
- Display `{{ $permit->route_raw ?? '-' }}` and route segment count.
- Add `<a class="button" href="{{ route('permits.route-map.show', $permit) }}">Lihat Rute</a>` inside the action cell.
- Increase empty-state colspan from `9` to `10`.

- [ ] **Step 7: Add permit route map view**

Create `resources/views/permits/route-map/show.blade.php`:

```blade
@extends('layouts.app')

@php
    $pageTitle = 'Peta Rute Izin';
    $pageDescription = optional($permit->vehicle)->plate_number . ' - ' . optional($permit->employee)->name;
@endphp

@section('content')
    <section class="page-section panel">
        <div class="panel-body route-map-panel">
            <div class="quick-actions">
                <a class="button" href="{{ route('permits.index') }}">Kembali</a>
            </div>

            <dl class="scan-result__details">
                <div>
                    <dt>Nama</dt>
                    <dd>{{ optional($permit->employee)->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Plat</dt>
                    <dd>{{ optional($permit->vehicle)->plate_number ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Parkir</dt>
                    <dd>{{ optional($permit->parkingLocation)->code ?? '-' }}</dd>
                </div>
                <div>
                    <dt>Rute</dt>
                    <dd>{{ $routeMapData['route_label'] ?: ($permit->route_raw ?? '-') }}</dd>
                </div>
            </dl>

            @if (! $routeMapData['has_route'])
                <div class="route-warning">Rute belum tersedia atau perlu review.</div>
            @elseif ($routeMapData['missing_segments'])
                <div class="route-warning">
                    Segmen belum dikurasi: {{ implode(', ', $routeMapData['missing_segments']) }}
                </div>
            @endif

            <div
                x-data="sirikaRoutePreview({
                    map: @js($routeMapData['map']),
                    segments: @js($routeMapData['segments'])
                })"
            >
                <div x-ref="map" class="route-map-canvas"></div>
            </div>
        </div>
    </section>
@endsection
```

- [ ] **Step 8: Verify permit route map**

Run:

```bash
php artisan test --filter=PermitRouteMapServiceTest
php artisan test --filter=PermitRouteMapHttpTest
php artisan test --filter=PermitListAfterImportTest
```

- [ ] **Step 9: Commit Task 5**

Run:

```bash
git add app/Services/Routes/PermitRouteMapService.php app/Http/Controllers/PermitRouteMapController.php app/Http/Controllers/PermitController.php app/Models/User.php routes/web.php resources/views/permits/index.blade.php resources/views/permits/route-map/show.blade.php tests/Feature/PermitRouteMapServiceTest.php tests/Feature/PermitRouteMapHttpTest.php tests/Feature/PermitListAfterImportTest.php
git commit -m "feat: add permit route map preview"
```

---

### Task 6: Add Route Map Data to Valid QR Scan Results

**Files:**
- Modify: `app/Services/Permits/PermitScanService.php`
- Modify: `resources/views/scan/index.blade.php`
- Modify: `resources/js/route-map.js`
- Modify: `resources/css/app.css`
- Modify: `tests/Feature/PermitScanServiceTest.php`
- Modify: `tests/Feature/ScanQrHttpTest.php`

**Interfaces:**
- Valid scan payload includes `permit.route_map`.
- Non-valid scan payload omits `permit.route_map`.

- [ ] **Step 1: Add failing scan service assertions**

Modify `tests/Feature/PermitScanServiceTest.php`:

- In the valid scan test, attach one complete `RoadSegment` to the active permit.
- Assert `permit.route_map.segments.0.code` exists.
- Assert `permit.route_map.map.key` equals `vdni-road-map-v1`.
- In the expired scan test, assert `route_map` is not present.

Use assertions:

```php
$this->assertArrayHasKey('route_map', $result['permit']);
$this->assertSame('vdni-road-map-v1', $result['permit']['route_map']['map']['key']);
$this->assertSame('Y1', $result['permit']['route_map']['segments'][0]['code']);
$this->assertArrayNotHasKey('route_map', $expiredResult['permit']);
```

Run:

```bash
php artisan test --filter=PermitScanServiceTest
```

- [ ] **Step 2: Inject permit route map service into scan service**

Modify `app/Services/Permits/PermitScanService.php`:

```php
use App\Services\Routes\PermitRouteMapService;
use Throwable;
```

Add constructor:

```php
private PermitRouteMapService $routeMaps;

public function __construct(PermitRouteMapService $routeMaps)
{
    $this->routeMaps = $routeMaps;
}
```

Update token query:

```php
$token = PermitToken::with([
    'permit.employee',
    'permit.vehicle',
    'permit.parkingLocation',
    'permit.routeSegments',
])
    ->where('token_hash', hash('sha256', $plainToken))
    ->first();
```

Update `fullPermitData()`:

```php
private function fullPermitData(VehiclePermit $permit): array
{
    $data = [
        'employee_name' => optional($permit->employee)->name,
        'plate_number' => optional($permit->vehicle)->plate_number,
        'parking_code' => optional($permit->parkingLocation)->code,
        'permit_color' => $permit->permit_color,
        'status' => $permit->status,
        'route_raw' => $permit->route_raw,
    ];

    try {
        $data['route_map'] = $this->routeMaps->forPermit($permit);
    } catch (Throwable $exception) {
        $data['route_map_warning'] = 'Peta rute tidak tersedia.';
    }

    return $data;
}
```

Do not add route map data to `limitedPermitData()`.

- [ ] **Step 3: Add failing scan HTTP assertion**

Modify `tests/Feature/ScanQrHttpTest.php` so valid scan JSON asserts:

```php
$response->assertJsonPath('permit.route_map.map.key', 'vdni-road-map-v1');
```

Expired scan JSON asserts:

```php
$response->assertJsonMissingPath('permit.route_map');
```

Run:

```bash
php artisan test --filter=ScanQrHttpTest
```

- [ ] **Step 4: Render route map in scanner UI**

Modify `resources/views/scan/index.blade.php` inside the `result.permit` template after the `<dl>`:

```blade
<template x-if="result.permit.route_map">
    <div class="scan-route-map">
        <template x-if="result.permit.route_map.missing_segments && result.permit.route_map.missing_segments.length">
            <div class="route-warning">
                <span>Segmen belum dikurasi: </span>
                <span x-text="result.permit.route_map.missing_segments.join(', ')"></span>
            </div>
        </template>

        <div
            x-data="sirikaRoutePreview({
                map: result.permit.route_map.map,
                segments: result.permit.route_map.segments
            })"
        >
            <div x-ref="map" class="route-map-canvas route-map-canvas--compact"></div>
        </div>
    </div>
</template>

<template x-if="result.permit.route_map_warning">
    <div class="route-warning" x-text="result.permit.route_map_warning"></div>
</template>
```

Add CSS:

```css
.scan-route-map {
    display: grid;
    gap: 12px;
    margin-top: 14px;
}
```

- [ ] **Step 5: Build frontend and verify tests**

Run:

```bash
npm.cmd run dev
php artisan test --filter=PermitScanServiceTest
php artisan test --filter=ScanQrHttpTest
```

- [ ] **Step 6: Commit Task 6**

Run:

```bash
git add app/Services/Permits/PermitScanService.php resources/views/scan/index.blade.php resources/js/route-map.js resources/css/app.css public/js/app.js public/css/app.css tests/Feature/PermitScanServiceTest.php tests/Feature/ScanQrHttpTest.php
git commit -m "feat: show route map for valid scans"
```

---

### Task 7: Browser Verification and Hardening

**Files:**
- Modify files only if verification reveals defects.

**Goal:** Prove the full Phase 4 flow works in the browser, including Leaflet rendering.

- [ ] **Step 1: Run full backend test suite**

Run:

```bash
php artisan test
```

- [ ] **Step 2: Build frontend assets**

Run:

```bash
npm.cmd run dev
```

- [ ] **Step 3: Refresh database and seed local data**

Run:

```bash
php artisan migrate:fresh --seed
```

Expected seeded accounts remain available from the existing seeder. Do not write test passwords into source code.

- [ ] **Step 4: Start local server**

Run:

```bash
php artisan serve --host=127.0.0.1 --port=8001
```

If port `8001` is already in use, use the next free port and report the URL.

- [ ] **Step 5: Browser smoke test**

Use the in-app browser to verify:

- Login as `admin_hr`.
- Open `/road-segments`.
- Open editor for segment `Y1`.
- Click two points on the VDNI map.
- Save as `complete`.
- Return to `/road-segments`.
- Confirm `Y1` status is `complete` and line appears on map preview.
- Open `/permits`.
- Open `Lihat Rute` for a permit that contains `Y1`.
- Confirm route map appears and missing segments warning is accurate.
- Open `/scan` as `security`.
- Scan or manually submit a valid token.
- Confirm route map appears for valid result.
- Submit an expired token.
- Confirm expired result does not render route map.

- [ ] **Step 6: Verify authorization manually**

Use seeded accounts:

- `auditor` can open `/road-segments`.
- `auditor` cannot submit update/reset.
- `security` cannot open `/road-segments/{id}/map`.
- `security` can open `/scan`.
- `super_admin` can update and reset coordinates.

- [ ] **Step 7: Run final git and artifact checks**

Run:

```bash
git status --short
git diff --check
php artisan route:list | findstr road-segments
php artisan route:list | findstr route-map
```

- [ ] **Step 8: Commit hardening fixes**

If verification required fixes:

```bash
git add -u
git commit -m "fix: harden phase 4 route map flow"
```

If no fixes were required, do not create an empty commit.

---

## Final Verification Matrix

Run before marking Phase 4 complete:

```bash
php artisan test
npm.cmd run dev
git diff --check
git status --short
```

Expected:

- All PHP tests pass.
- Laravel Mix build succeeds.
- No whitespace errors.
- Working tree contains only intentional changes before final commit, then clean after commit.

## Production Notes

- If the VDNI map image changes, update `SIRIKA_ROUTE_MAP_KEY`, image URL, width, and height together.
- Existing coordinates are tied to the map dimensions and `map_key`.
- Do not enable admin map upload in Phase 4.
- Route map DTOs are operational data and should remain limited to segment codes, map metadata, and point arrays.
- Scanner availability must not depend on route map availability.

## Rollback

Rollback is safe by reverting Phase 4 commits in reverse order:

```bash
git log --oneline --reverse main..HEAD
```

Use the concrete commit SHAs printed by `git log --oneline --reverse main..HEAD` and run `git revert` from newest to oldest. No new database table or destructive migration is introduced. Existing `road_segments.polyline_json` data can remain unused if route map UI is reverted.
