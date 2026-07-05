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
        $raw = ['1', 'DT 4423 CI', 'FITRIAWATI', '200115677', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', 'GA-MES1-P01', 'Y1â†’D2â†’Z1â†’D3â†’GA-MES1-P01', 'OFFICE', 'BIRU è“è‰²', '0812', 'âˆš', 'JUBIR ADMIN', 'GENERAL AFFAIR'];

        $result = (new PermitImportRowNormalizer(new RouteSegmentParser()))->normalize($raw, $this->columns(), ['Y1', 'D2', 'Z1', 'D3'], 5);

        $this->assertSame(ImportRow::STATUS_VALID, $result['status']);
        $this->assertSame('200115677', $result['normalized_data']['nik']);
        $this->assertSame('biru', $result['normalized_data']['permit_color']);
        $this->assertSame(['Y1', 'D2', 'Z1', 'D3'], $result['normalized_data']['route_segment_codes']);
        $this->assertSame([], $result['errors']);
    }

    /** @test */
    public function it_marks_blank_plate_as_invalid()
    {
        $raw = ['1', '', 'FITRIAWATI', '200115677', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', 'GA-MES1-P01', 'Y1â†’D2', 'OFFICE', 'BIRU è“è‰²', '0812', 'âˆš', '', 'GENERAL AFFAIR'];

        $result = (new PermitImportRowNormalizer(new RouteSegmentParser()))->normalize($raw, $this->columns(), ['Y1', 'D2'], 5);

        $this->assertSame(ImportRow::STATUS_INVALID, $result['status']);
        $this->assertContains('Plat motor wajib diisi', $result['errors']);
    }

    /** @test */
    public function it_marks_blank_route_as_needs_review()
    {
        $raw = ['1', 'DT 4423 CI', 'FITRIAWATI', '200115677', 'GENERAL AFFAIR', 'GA KANTOR', 'ADMIN', 'GA-MES1-P01', '', 'OFFICE', 'BIRU è“è‰²', '0812', 'âˆš', '', 'GENERAL AFFAIR'];

        $result = (new PermitImportRowNormalizer(new RouteSegmentParser()))->normalize($raw, $this->columns(), ['Y1', 'D2'], 5);

        $this->assertSame(ImportRow::STATUS_NEEDS_REVIEW, $result['status']);
        $this->assertContains('Rute kendaraan kosong', $result['warnings']);
    }

    /** @test */
    public function it_marks_multiple_plates_as_needs_review()
    {
        $raw = ['1', "DT 5224 AA/\nDT 2119 WA", 'MUH IRAWAN', '17011544', 'GENERAL AFFAIR', 'GA KANTOR', 'DRIVER', 'GA-MES1-P01', 'Y1â†’D2', 'OFFICE', 'BIRU è“è‰²', '0812', 'âˆš', '', 'GENERAL AFFAIR'];

        $result = (new PermitImportRowNormalizer(new RouteSegmentParser()))->normalize($raw, $this->columns(), ['Y1', 'D2'], 5);

        $this->assertSame(ImportRow::STATUS_NEEDS_REVIEW, $result['status']);
        $this->assertContains('Plat motor berisi lebih dari satu nilai', $result['warnings']);
    }
}
