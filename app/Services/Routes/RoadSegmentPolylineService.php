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
