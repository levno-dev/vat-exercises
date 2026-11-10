<?php

declare(strict_types=1);

final class Reading
{
    public function __construct(
        public readonly int $ts,        // epoch seconds
        public readonly float $volumeL, // litres of milk in the vat
    ) {
    }
}

final class Milking
{
    public function __construct(
        public readonly int $startTs,   // last reading before the volume starts rising
        public readonly int $endTs,     // first reading at the highest point
        public readonly float $volumeL, // litres added
    ) {
    }
}

/**
 * Find the milkings in a vat's readings.
 *
 * @param Reading[] $readings
 * @return Milking[]
 */
function detectMilkings(array $readings, float $minRiseL = 500): array
{
    throw new LogicException('Not implemented');
}
