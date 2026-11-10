<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MilkingsTest extends TestCase
{
    private const T0 = 1_700_000_000;
    private const TEN_MIN = 600;

    /** Readings ten minutes apart, starting at T0. */
    private static function series(float ...$volumes): array
    {
        return array_map(
            fn (int $i, float $volume) => new Reading(self::T0 + $i * self::TEN_MIN, $volume),
            array_keys($volumes),
            $volumes,
        );
    }

    private static function ts(int $i): int
    {
        return self::T0 + $i * self::TEN_MIN;
    }

    public function testNoReadings(): void
    {
        $this->assertEquals([], detectMilkings([]));
    }

    public function testLevelVatHasNoMilking(): void
    {
        $this->assertEquals([], detectMilkings(self::series(3000, 3000, 3000, 3000, 3000)));
    }

    public function testEmptyingVatHasNoMilking(): void
    {
        $this->assertEquals([], detectMilkings(self::series(3000, 3000, 2000, 0, 0)));
    }

    public function testSingleMilking(): void
    {
        $readings = self::series(800, 0, 0, 1000, 2500, 3000, 3000, 3000, 3000);

        $this->assertEquals([new Milking(self::ts(2), self::ts(5), 3000)], detectMilkings($readings));
    }
}
