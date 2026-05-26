<?php

declare(strict_types=1);

namespace PayTracker\Support;

/**
 * Minimal dot-notation configuration repository.
 *
 * The repository is populated once at boot from `config/*.php`. Reads return a
 * default when any segment of the dotted key is missing, so callers never need
 * to guard each lookup with `isset()`.
 */
final class Config
{
    /** @var array<string, mixed> */
    private array $items = [];

    public function set(string $key, mixed $value): void
    {
        $this->items[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value    = $this->items;
        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->items;
    }
}
