<?php

namespace Tests\Unit;

use App\Services\Analytics\DailyAnalysisWindowFactory;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DailyAnalysisWindowFactoryTest extends TestCase
{
    public function test_dhaka_midnight_maps_to_previous_utc_calendar_day(): void
    {
        $window = app(DailyAnalysisWindowFactory::class)->make(
            null,
            CarbonImmutable::parse('2026-08-31T18:00:00Z'),
        );

        $this->assertSame('2026-09-01', $window->date);
        $this->assertSame('2026-08-31T18:00:00.000000Z', $window->startsAt->format('Y-m-d\TH:i:s.u\Z'));
        $this->assertSame(0, $window->durationSeconds());
        $this->assertTrue($window->partial);
    }

    public function test_dhaka_2359_remains_same_local_partial_day(): void
    {
        $window = app(DailyAnalysisWindowFactory::class)->make(
            null,
            CarbonImmutable::parse('2026-09-01T17:59:59Z'),
        );

        $this->assertSame('2026-09-01', $window->date);
        $this->assertSame(86399, $window->durationSeconds());
        $this->assertSame('Asia/Dhaka', $window->timezone);
    }
}
