<?php

declare(strict_types=1);

namespace PayTracker\Services\Pay;

/**
 * Bag of (variable → amount) strings. Wraps the array so the calculator
 * can swap stages without re-importing the whole map.
 */
final class VariableBag
{
    /** @param array<string,string> $map */
    public function __construct(private readonly array $map)
    {
    }

    public function get(string $name, string $default = '0'): string
    {
        return $this->map[$name] ?? $default;
    }
}
