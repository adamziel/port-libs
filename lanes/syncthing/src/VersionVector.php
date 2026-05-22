<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class VersionVector
{
    public const ORDER_EQUAL = 'equal';
    public const ORDER_GREATER = 'greater';
    public const ORDER_LESSER = 'lesser';
    public const ORDER_CONCURRENT_LESSER = 'concurrent_lesser';
    public const ORDER_CONCURRENT_GREATER = 'concurrent_greater';

    /**
     * @var array<int, int>
     */
    private array $counters;

    /**
     * @param array<int, int>|list<array{id:int, value:int}|array{0:int, 1:int}> $counters
     */
    public function __construct(array $counters = [])
    {
        $this->counters = self::normalizeCounters($counters);
    }

    /**
     * @param array<int, int>|list<array{id:int, value:int}|array{0:int, 1:int}> $counters
     */
    public static function fromCounters(array $counters): self
    {
        return new self($counters);
    }

    public function update(int $id, ?int $now = null): self
    {
        self::assertNonNegative($id, 'Counter ID');
        $now ??= time();
        self::assertNonNegative($now, 'Counter timestamp');

        $counters = $this->counters;
        $current = $counters[$id] ?? null;
        $next = $current === null ? 1 : self::increment($current);
        $counters[$id] = max($next, $now);

        return new self($counters);
    }

    public function merge(self $other): self
    {
        $counters = $this->counters;
        foreach ($other->counters as $id => $value) {
            $counters[$id] = max($counters[$id] ?? 0, $value);
        }

        return new self($counters);
    }

    public function counter(int $id): int
    {
        self::assertNonNegative($id, 'Counter ID');

        return $this->counters[$id] ?? 0;
    }

    public function isEmpty(): bool
    {
        return $this->counters === [];
    }

    public function dropOthers(int $id): self
    {
        self::assertNonNegative($id, 'Counter ID');

        if (!array_key_exists($id, $this->counters)) {
            return new self();
        }

        return new self([$id => $this->counters[$id]]);
    }

    public function compare(self $other): string
    {
        $ids = array_keys($this->counters + $other->counters);
        sort($ids, SORT_NUMERIC);

        $direction = self::ORDER_EQUAL;
        foreach ($ids as $id) {
            $ours = $this->counters[$id] ?? 0;
            $theirs = $other->counters[$id] ?? 0;

            if ($ours === $theirs) {
                continue;
            }

            if ($ours > $theirs) {
                if ($direction === self::ORDER_LESSER) {
                    return self::ORDER_CONCURRENT_LESSER;
                }

                $direction = self::ORDER_GREATER;
                continue;
            }

            if ($direction === self::ORDER_GREATER) {
                return self::ORDER_CONCURRENT_GREATER;
            }

            $direction = self::ORDER_LESSER;
        }

        return $direction;
    }

    public function equal(self $other): bool
    {
        return $this->compare($other) === self::ORDER_EQUAL;
    }

    public function lesserEqual(self $other): bool
    {
        $ordering = $this->compare($other);

        return $ordering === self::ORDER_EQUAL || $ordering === self::ORDER_LESSER;
    }

    public function greaterEqual(self $other): bool
    {
        $ordering = $this->compare($other);

        return $ordering === self::ORDER_EQUAL || $ordering === self::ORDER_GREATER;
    }

    public function concurrent(self $other): bool
    {
        $ordering = $this->compare($other);

        return $ordering === self::ORDER_CONCURRENT_LESSER || $ordering === self::ORDER_CONCURRENT_GREATER;
    }

    public function humanString(): string
    {
        $parts = [];
        foreach ($this->counters as $id => $value) {
            $parts[] = $id . ':' . $value;
        }

        return implode(',', $parts);
    }

    public function __toString(): string
    {
        $parts = [];
        foreach ($this->counters as $id => $value) {
            $parts[] = sprintf('%016x:%d', $id, $value);
        }

        return implode(',', $parts);
    }

    /**
     * @return array<int, int>
     */
    public function toArray(): array
    {
        return $this->counters;
    }

    /**
     * @param array<int, int>|list<array{id:int, value:int}|array{0:int, 1:int}> $counters
     *
     * @return array<int, int>
     */
    private static function normalizeCounters(array $counters): array
    {
        $normalized = [];
        foreach ($counters as $id => $value) {
            if (is_array($value)) {
                if (array_key_exists('id', $value) && array_key_exists('value', $value)) {
                    $id = $value['id'];
                    $value = $value['value'];
                } elseif (array_key_exists(0, $value) && array_key_exists(1, $value)) {
                    $id = $value[0];
                    $value = $value[1];
                } else {
                    throw new \InvalidArgumentException('Counter tuples must include an ID and a value');
                }
            }

            if (!is_int($id) || !is_int($value)) {
                throw new \InvalidArgumentException('Counter IDs and values must be integers');
            }

            self::assertNonNegative($id, 'Counter ID');
            self::assertNonNegative($value, 'Counter value');

            $normalized[$id] = isset($normalized[$id]) ? max($normalized[$id], $value) : $value;
        }

        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    private static function assertNonNegative(int $value, string $label): void
    {
        if ($value < 0) {
            throw new \InvalidArgumentException($label . ' must not be negative');
        }
    }

    private static function increment(int $value): int
    {
        if ($value === PHP_INT_MAX) {
            return $value;
        }

        return $value + 1;
    }
}
