<?php

namespace Tests\Unit;

use App\Models\ImportRow;
use App\Services\Imports\PermitImportRowNormalizer;
use App\Services\Imports\RouteSegmentParser;
use PHPUnit\Framework\TestCase;

class PermitImportRowNormalizerTest extends TestCase
{
    private function columns()
    {
        return [
            'plate_number' => 1,
            'employee_name' => 2,
            'nik' => 3,
            'department' => 4,
            'section' => 5,
            'position' => 6,
            'parking_location' => 7,
            'route_raw' => 8,
            'reason' => 9,
            'permit_color' => 10,
            'contact_number' => 11,
            'approval_status' => 12,
            'notes' => 13,
            'division' => 14,
        ];
    }

    /** @test */
    public function it_marks_complete_row_as_valid()
    {
        $raw = ['1', 'DT 4423 CI', 'FITRIAWATI', '200115677', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', 'GA-MES1-P01', 'Y1Ã¢â€ â€™D2Ã¢â€ â€™Z1Ã¢â€ â€™D3Ã¢â€ â€™GA-MES1-P01', 'OFFICE', 'BIRU Ã¨â€œÂÃ¨â€°Â²', '0812', 'Ã¢Ë†Å¡', 'JUBIR ADMIN', 'GENERAL AFFAIR'];

        $result = (new PermitImportRowNormalizer(new RouteSegmentParser()))->normalize($raw, $this->columns(), ['Y1', 'D2', 'Z1', 'D3'], 5);

        $this->assertSame(ImportRow::STATUS_VALID, $result['status']);
        $this->assertSame('200115677', $result['normalized_data']['nik']);
        $this->assertSame('biru', $result['normalized_data']['permit_color']);
        $this->assertSame(['Y1', 'D2', 'Z1', 'D3'], $result['normalized_data']['route_segment_codes']);
        $this->assertSame([], $result['errors']);
    }

    /** @test */
    public function it_normalizes_multiple_parking_locations_and_excludes_them_from_route_segments()
    {
        $raw = ['1', 'DT 4423 CI', 'FITRIAWATI', '200115677', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', "CY-CC-P02 /\nCY-CC-P03", 'GA-MES1-P01 → Y1 → D2 → PLTU-PC-6-P10', 'OFFICE', 'BIRU', '0812', 'approved', '', 'GENERAL AFFAIR'];

        $result = (new PermitImportRowNormalizer(new RouteSegmentParser()))->normalize($raw, $this->columns(), ['Y1', 'D2'], 5);

        $this->assertSame(ImportRow::STATUS_VALID, $result['status']);
        $this->assertSame(['CY-CC-P02', 'CY-CC-P03'], $result['normalized_data']['parking_location_codes']);
        $this->assertSame(['Y1', 'D2'], $result['normalized_data']['route_segment_codes']);
        $this->assertSame([], $result['warnings']);
    }

    /** @test */
    public function it_marks_blank_plate_as_invalid()
    {
        $raw = ['1', '', 'FITRIAWATI', '200115677', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', 'GA-MES1-P01', 'Y1Ã¢â€ â€™D2', 'OFFICE', 'BIRU Ã¨â€œÂÃ¨â€°Â²', '0812', 'Ã¢Ë†Å¡', '', 'GENERAL AFFAIR'];

        $result = (new PermitImportRowNormalizer(new RouteSegmentParser()))->normalize($raw, $this->columns(), ['Y1', 'D2'], 5);

        $this->assertSame(ImportRow::STATUS_INVALID, $result['status']);
        $this->assertContains('Plat motor wajib diisi', $result['errors']);
    }

    /** @test */
    public function it_marks_blank_route_as_needs_review()
    {
        $raw = ['1', 'DT 4423 CI', 'FITRIAWATI', '200115677', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', 'GA-MES1-P01', '', 'OFFICE', 'BIRU Ã¨â€œÂÃ¨â€°Â²', '0812', 'Ã¢Ë†Å¡', '', 'GENERAL AFFAIR'];

        $result = (new PermitImportRowNormalizer(new RouteSegmentParser()))->normalize($raw, $this->columns(), ['Y1', 'D2'], 5);

        $this->assertSame(ImportRow::STATUS_NEEDS_REVIEW, $result['status']);
        $this->assertContains('Rute kendaraan kosong', $result['warnings']);
    }

    /** @test */
    public function it_marks_multiple_plates_as_needs_review()
    {
        $raw = ['1', "DT 5224 AA/\nDT 2119 WA", 'MUH IRAWAN', '17011544', 'GENERAL AFFAIR', 'GA KANTOR', 'DRIVER', 'GA-MES1-P01', 'Y1Ã¢â€ â€™D2', 'OFFICE', 'BIRU Ã¨â€œÂÃ¨â€°Â²', '0812', 'Ã¢Ë†Å¡', '', 'GENERAL AFFAIR'];

        $result = (new PermitImportRowNormalizer(new RouteSegmentParser()))->normalize($raw, $this->columns(), ['Y1', 'D2'], 5);

        $this->assertSame(ImportRow::STATUS_NEEDS_REVIEW, $result['status']);
        $this->assertContains('Plat motor berisi lebih dari satu nilai', $result['warnings']);
    }

    /** @test */
    public function it_marks_tidak_setuju_as_invalid()
    {
        $raw = ['1', 'DT 4423 CI', 'FITRIAWATI', '200115677', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', 'GA-MES1-P01', 'Y1Ã¢â€ â€™D2', 'OFFICE', 'BIRU Ã¨â€œÂÃ¨â€°Â²', '0812', 'tidak setuju', '', 'GENERAL AFFAIR'];

        $result = (new PermitImportRowNormalizer(new RouteSegmentParser()))->normalize($raw, $this->columns(), ['Y1', 'D2'], 5);

        $this->assertSame(ImportRow::STATUS_INVALID, $result['status']);
        $this->assertContains('Hasil persetujuan harus disetujui', $result['errors']);
    }

    /** @test */
    public function it_marks_route_free_text_as_needs_review()
    {
        $raw = ['1', 'DT 4423 CI', 'FITRIAWATI', '200115677', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', 'GA-MES1-P01', 'Y1 -> jalan baru -> D2', 'OFFICE', 'BIRU Ã¨â€œÂÃ¨â€°Â²', '0812', 'Ã¢Ë†Å¡', '', 'GENERAL AFFAIR'];

        $result = (new PermitImportRowNormalizer(new RouteSegmentParser()))->normalize($raw, $this->columns(), ['Y1', 'D2'], 5);

        $this->assertSame(ImportRow::STATUS_NEEDS_REVIEW, $result['status']);
        $this->assertContains('Rute mengandung teks bebas yang perlu review: jalan baru', $result['warnings']);
    }
}
