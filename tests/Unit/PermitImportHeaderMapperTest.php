<?php

namespace Tests\Unit;

use App\Services\Imports\PermitImportHeaderMapper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PermitImportHeaderMapperTest extends TestCase
{
    /** @test */
    public function it_finds_bilingual_header_row_and_column_indexes()
    {
        $rows = [
            ['', '', ''],
            ['VDNI Formulir', '', ''],
            ['åºå· No', 'æ‘©æ‰˜è½¦ç‰Œå· Plat Motor', 'å§“å Nama', 'å·¥å· Nik', 'éƒ¨é—¨ Dep', 'ç§‘å®¤ Bagian', 'å²—ä½ Jabatan', 'åœæ”¾åœ°ç‚¹ Lokasi Parkir', 'è¡Œé©¶è·¯çº¿ Rute Kendaraan', 'è¿›åŽ‚åŽŸå›  Alasan Masuk', 'é€šè¡Œè¯é¢œè‰² Warna kartu izin masuk', 'è”ç³»æ–¹å¼ Nomor kontak', 'å®¡æ‰¹ç»“æžœ Hasil Persetujuan', 'KET', 'DIVISI'],
        ];

        $result = (new PermitImportHeaderMapper())->findHeader($rows);

        $this->assertSame(2, $result['row_index']);
        $this->assertSame(1, $result['columns']['plate_number']);
        $this->assertSame(3, $result['columns']['nik']);
        $this->assertSame(8, $result['columns']['route_raw']);
        $this->assertSame(12, $result['columns']['approval_status']);
    }

    /** @test */
    public function it_rejects_rows_without_required_headers()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Header Excel tidak valid');

        (new PermitImportHeaderMapper())->findHeader([
            ['Name', 'Plate'],
        ]);
    }
}
