<?php

namespace Database\Seeders;

use App\Models\RoadSegment;
use App\Services\Routes\RoadSegmentPolylineService;
use Illuminate\Database\Seeder;

class RoadSegmentSeeder extends Seeder
{
    public function run()
    {
        $segments = [
            ['code' => 'Y1', 'name' => 'Jalan Yingbin Y1', 'start_location' => 'Pos Gerbang Timur', 'end_location' => 'Pos Gerbang Barat 1'],
            ['code' => 'Y2', 'name' => 'Jalan Yingbin Y2', 'start_location' => 'Pos Gerbang Barat 1', 'end_location' => 'Pos Gerbang Barat 2'],
            ['code' => 'WL1', 'name' => 'Jalan Logistik WL1', 'start_location' => 'Bea Cukai Gerbang Timur', 'end_location' => 'Tempat Penimbunan Stockpile'],
            ['code' => 'WL2', 'name' => 'Jalan Logistik WL2', 'start_location' => 'Ujung Selatan Stockpile', 'end_location' => 'Ujung Utara Stockpile'],
            ['code' => 'WL3', 'name' => 'Jalan Logistik WL3', 'start_location' => 'Pos Gerbang Barat 2', 'end_location' => 'OSS'],
            ['code' => 'T1', 'name' => 'Jalan T1', 'start_location' => 'Depan Gudang Nikel Lempengan', 'end_location' => 'Workshop Lama'],
            ['code' => 'T2', 'name' => 'Jalan T2', 'start_location' => 'Pintu Barat Smelter 1', 'end_location' => 'Area Penumpukan Smelter 1'],
            ['code' => 'T3', 'name' => 'Jalan T3', 'start_location' => 'Pintu Barat Smelter 2', 'end_location' => 'Area Penumpukan Smelter 2'],
            ['code' => 'T4', 'name' => 'Jalan T4', 'start_location' => 'Pintu Barat Smelter 3', 'end_location' => 'Jalur Crusher'],
            ['code' => 'D1', 'name' => 'Jalan D1', 'start_location' => 'Sisi Timur Gardu Induk 220kV', 'end_location' => 'Penimbunan Limbah Ban Lama'],
            ['code' => 'D2', 'name' => 'Jalan D2', 'start_location' => 'Sisi Barat Masjid', 'end_location' => 'Pintu Barat Gudang Batu Bara 2 PLTU'],
            ['code' => 'D3', 'name' => 'Jalan D3', 'start_location' => 'Sisi Barat Asrama 1', 'end_location' => 'Pintu Barat Lapangan Olahraga PLTU'],
            ['code' => 'D4', 'name' => 'Jalan D4', 'start_location' => 'Ujung Timur PLTU', 'end_location' => 'Sisi Selatan Restoran Huimin'],
            ['code' => 'D5', 'name' => 'Jalan D5', 'start_location' => 'Utara Gudang Batu Bara No.1 PLTU', 'end_location' => 'Gedung Bulu Tangkis'],
            ['code' => 'D6', 'name' => 'Jalan D6', 'start_location' => 'Selatan Gudang Batu Bara No.2 PLTU', 'end_location' => 'Selatan Kolam Pengendapan'],
            ['code' => 'C1', 'name' => 'Jalan C1', 'start_location' => 'Persimpangan Gudang', 'end_location' => 'Gudang Material Tahan Panas'],
            ['code' => 'C2', 'name' => 'Jalan C2', 'start_location' => 'Pintu Masuk Alat Berat', 'end_location' => 'Kantor Alat Berat'],
            ['code' => 'C3', 'name' => 'Jalan C3', 'start_location' => 'Pintu Masuk BBM', 'end_location' => 'Tempat Pengisian BBM'],
            ['code' => 'XL1', 'name' => 'Jalan XL1', 'start_location' => 'Pintu Masuk Sisi Timur Workshop Baru', 'end_location' => 'Workshop Baru'],
            ['code' => 'Z1', 'name' => 'Jalan Z1', 'start_location' => 'Depan Politeknik', 'end_location' => 'Depan Pintu Utara Asrama 1'],
            ['code' => 'Z2', 'name' => 'Jalan Z2', 'start_location' => 'Sisi Barat Asrama 2', 'end_location' => 'Sisi Barat Politeknik'],
            ['code' => 'Z3', 'name' => 'Jalan Z3', 'start_location' => 'Taman Rusa', 'end_location' => 'Gedung Bulu Tangkis'],
            ['code' => 'Z4', 'name' => 'Jalan Z4', 'start_location' => 'Persimpangan Klinik', 'end_location' => 'Sisi Selatan Asrama 4'],
            ['code' => 'S1', 'name' => 'Jalan S1', 'start_location' => 'Persimpangan Sisi Timur Gudang Material Tahan Panas', 'end_location' => 'Pintu Selatan Workshop Manufaktur'],
            ['code' => 'H1', 'name' => 'Jalan H1', 'start_location' => 'Persimpangan Laboratorium Analis Smelter', 'end_location' => 'Pintu Barat Departemen Pemeliharaan PLTU'],
            ['code' => 'H2', 'name' => 'Jalan H2', 'start_location' => 'Persimpangan Laboratorium Ore', 'end_location' => 'Sisi Utara Pompa Air OSS'],
        ];

        $polylines = app(RoadSegmentPolylineService::class);
        $starterCoordinates = $this->starterCoordinates();

        foreach ($segments as $segment) {
            $roadSegment = RoadSegment::updateOrCreate(
                ['code' => $segment['code']],
                array_merge($segment, ['status' => 'active'])
            );

            if ($roadSegment->polyline_json === null && isset($starterCoordinates[$segment['code']])) {
                $roadSegment->update([
                    'polyline_json' => $polylines->buildPayload(
                        $starterCoordinates[$segment['code']],
                        RoadSegmentPolylineService::STATUS_COMPLETE,
                        null
                    ),
                ]);
            }
        }
    }

    private function starterCoordinates(): array
    {
        return [
            // Initial coordinates are based on attachment 4 VDNI road map.
            // They are starter data only; admin edits in Master Rute remain preserved.
            'Y1' => [
                ['x' => 1380, 'y' => 1600],
                ['x' => 1740, 'y' => 1560],
                ['x' => 2100, 'y' => 1530],
                ['x' => 2460, 'y' => 1505],
                ['x' => 2860, 'y' => 1490],
            ],
            'D2' => [
                ['x' => 1840, 'y' => 1665],
                ['x' => 1845, 'y' => 1815],
                ['x' => 1860, 'y' => 1990],
                ['x' => 1875, 'y' => 2190],
            ],
            'Z1' => [
                ['x' => 1710, 'y' => 1765],
                ['x' => 1850, 'y' => 1765],
                ['x' => 1990, 'y' => 1760],
                ['x' => 2150, 'y' => 1755],
            ],
            'D3' => [
                ['x' => 1700, 'y' => 1660],
                ['x' => 1700, 'y' => 1820],
                ['x' => 1710, 'y' => 2010],
                ['x' => 1715, 'y' => 2185],
            ],
        ];
    }
}
