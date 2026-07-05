<?php

namespace Tests\Unit;

use App\Services\Imports\RouteSegmentParser;
use PHPUnit\Framework\TestCase;

class RouteSegmentParserTest extends TestCase
{
    /** @test */
    public function it_extracts_known_segment_codes_in_order()
    {
        $result = (new RouteSegmentParser())->parse('Y1â†’D2â†’Z1â†’D3â†’GA-MES1-P01', ['Y1', 'D2', 'Z1', 'D3']);

        $this->assertSame(['Y1', 'D2', 'Z1', 'D3'], $result['codes']);
        $this->assertSame([], $result['warnings']);
    }

    /** @test */
    public function it_marks_unknown_route_tokens_as_warning()
    {
        $result = (new RouteSegmentParser())->parse('Y1â†’X99â†’D2', ['Y1', 'D2']);

        $this->assertSame(['Y1', 'D2'], $result['codes']);
        $this->assertContains('Rute mengandung token tidak dikenal: X99', $result['warnings']);
    }

    /** @test */
    public function it_marks_long_instruction_text_as_warning()
    {
        $result = (new RouteSegmentParser())->parse('Y1â†’D2 ï¼ˆæ ¹æ®é¢†å¯¼å®‰æŽ’çš„å·¥ä½œåŒºåŸŸï¼‰', ['Y1', 'D2']);

        $this->assertSame(['Y1', 'D2'], $result['codes']);
        $this->assertContains('Rute mengandung catatan teks yang perlu review', $result['warnings']);
    }
}
