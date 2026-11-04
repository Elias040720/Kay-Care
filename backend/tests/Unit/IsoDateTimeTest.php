<?php

namespace Tests\Unit;

use App\Support\IsoDateTime;
use App\Support\Num;
use PHPUnit\Framework\TestCase;

class IsoDateTimeTest extends TestCase
{
    public function test_accepts_iso_8601_with_timezone_only(): void
    {
        $this->assertTrue(IsoDateTime::isValid('2026-11-04T09:30:00-05:00'));
        $this->assertTrue(IsoDateTime::isValid('2026-11-04T09:30-05:00'));
        $this->assertTrue(IsoDateTime::isValid('2026-11-04T14:30:00Z'));
        $this->assertTrue(IsoDateTime::isValid('2026-11-04T14:30:00.123Z'));

        $this->assertFalse(IsoDateTime::isValid('2026-11-04T09:30:00'), 'no offset');
        $this->assertFalse(IsoDateTime::isValid('2026-11-04'), 'date only');
        $this->assertFalse(IsoDateTime::isValid('2026-02-30T09:30:00-05:00'), 'overflow');
        $this->assertFalse(IsoDateTime::isValid('04/11/2026 09:30'));
        $this->assertFalse(IsoDateTime::isValid(12345));
    }

    public function test_parse_keeps_offset_and_converts_to_utc(): void
    {
        $parsed = IsoDateTime::parse('2026-11-04T21:30:00-05:00');

        $this->assertSame('-05:00', $parsed['offset']);
        $this->assertSame('2026-11-05 02:30:00', $parsed['utc']->format('Y-m-d H:i:s'));
        // The LOCAL date is still the 4th (relevant for age calculation).
        $this->assertSame('2026-11-04', $parsed['local']->toDateString());
        $this->assertSame('2026-11-04T21:30:00-05:00', IsoDateTime::format($parsed['utc'], $parsed['offset']));
    }

    public function test_zulu_is_normalized_to_plus_zero(): void
    {
        $this->assertSame('+00:00', IsoDateTime::parse('2026-11-04T14:30:00Z')['offset']);
    }

    public function test_number_formatting(): void
    {
        $this->assertSame('100', Num::format('100'));
        $this->assertSame('12.5', Num::format('12.500'));
        $this->assertSame('3.25', Num::format(3.25));
        $this->assertNull(Num::format(null));
        $this->assertSame(7.0, Num::toFloat('7.000'));
        $this->assertNull(Num::toFloat(null));
    }
}
