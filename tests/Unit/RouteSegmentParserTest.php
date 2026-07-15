<?php

namespace Tests\Unit;

use App\Services\Imports\RouteSegmentParser;
use PHPUnit\Framework\TestCase;

class RouteSegmentParserTest extends TestCase
{
    /** @test */
    public function it_extracts_known_segment_codes_in_order()
    {
        $result = (new RouteSegmentParser())->parse('Y1Ã¢â€ â€™D2Ã¢â€ â€™Z1Ã¢â€ â€™D3Ã¢â€ â€™GA-MES1-P01', ['Y1', 'D2', 'Z1', 'D3']);

        $this->assertSame(['Y1', 'D2', 'Z1', 'D3'], $result['codes']);
        $this->assertSame([], $result['warnings']);
    }

    /** @test */
    public function it_marks_unknown_route_tokens_as_warning()
    {
        $result = (new RouteSegmentParser())->parse('Y1Ã¢â€ â€™X99Ã¢â€ â€™D2', ['Y1', 'D2']);

        $this->assertSame(['Y1', 'D2'], $result['codes']);
        $this->assertContains('Rute mengandung token tidak dikenal: X99', $result['warnings']);
    }

    /** @test */
    public function it_marks_long_instruction_text_as_warning()
    {
        $result = (new RouteSegmentParser())->parse('Y1Ã¢â€ â€™D2 Ã¯Â¼Ë†Ã¦Â Â¹Ã¦ÂÂ®Ã©Â¢â€ Ã¥Â¯Â¼Ã¥Â®â€°Ã¦Å½â€™Ã§Å¡â€žÃ¥Â·Â¥Ã¤Â½Å“Ã¥Å’ÂºÃ¥Å¸Å¸Ã¯Â¼â€°', ['Y1', 'D2']);

        $this->assertSame(['Y1', 'D2'], $result['codes']);
        $this->assertContains('Rute mengandung catatan teks yang perlu review', $result['warnings']);
    }

    /** @test */
    public function it_marks_free_text_between_known_codes_as_warning()
    {
        $result = (new RouteSegmentParser())->parse('Y1 -> jalan baru -> D2', ['Y1', 'D2']);

        $this->assertSame(['Y1', 'D2'], $result['codes']);
        $this->assertContains('Rute mengandung teks bebas yang perlu review: jalan baru', $result['warnings']);
    }

    /** @test */
    public function it_removes_supplied_and_hierarchical_parking_codes_from_arrow_and_spaced_dash_routes()
    {
        $result = (new RouteSegmentParser())->parse(
            'GA-MES1-P01 → Y1 - D2 → PLTU-PC-6-P10',
            ['Y1', 'D2'],
            ['GA-MES1-P01']
        );

        $this->assertSame(['Y1', 'D2'], $result['codes']);
        $this->assertSame([], $result['warnings']);
    }

    /** @test */
    public function it_keeps_hyphenated_free_text_for_review_when_it_is_not_a_parking_code()
    {
        $result = (new RouteSegmentParser())->parse('Y1-jalan-baru-akses-D2', ['Y1', 'D2']);

        $this->assertSame(['Y1', 'D2'], $result['codes']);
        $this->assertContains('Rute mengandung teks bebas yang perlu review: jalan baru akses', $result['warnings']);
    }
}
