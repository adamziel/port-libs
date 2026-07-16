<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class SelectorBloomFilter
{
    public const BLOOM_HASH_MASK = 0x00ffffff;
    public const KEY_SIZE = 12;
    public const ARRAY_SIZE = 1 << self::KEY_SIZE;
    private const KEY_MASK = self::ARRAY_SIZE - 1;

    /** @var array<int, int> */
    private array $counters;

    public function __construct()
    {
        $this->counters = array_fill(0, self::ARRAY_SIZE, 0);
    }

    public static function hashString(string $value): int
    {
        $hash = (int) hexdec(substr(md5($value), 0, 8));

        return (($hash >> 16) ^ $hash) & self::BLOOM_HASH_MASK;
    }

    public function clear(): void
    {
        $this->counters = array_fill(0, self::ARRAY_SIZE, 0);
    }

    public function isZeroed(): bool
    {
        foreach ($this->counters as $counter) {
            if ($counter !== 0) {
                return false;
            }
        }

        return true;
    }

    public function insertHash(int $hash): void
    {
        $this->adjustSlot(self::firstSlotIndex($hash), true);
        $this->adjustSlot(self::secondSlotIndex($hash), true);
    }

    public function removeHash(int $hash): void
    {
        $this->adjustSlot(self::firstSlotIndex($hash), false);
        $this->adjustSlot(self::secondSlotIndex($hash), false);
    }

    public function mightContainHash(int $hash): bool
    {
        return $this->counters[self::firstSlotIndex($hash)] !== 0
            && $this->counters[self::secondSlotIndex($hash)] !== 0;
    }

    private function adjustSlot(int $index, bool $increment): void
    {
        $slot = $this->counters[$index];
        if ($slot === 0xff) {
            return;
        }

        if ($increment) {
            $this->counters[$index] = $slot + 1;
            return;
        }

        if ($slot === 0) {
            throw new \UnderflowException('Cannot remove an absent Bloom filter slot');
        }

        $this->counters[$index] = $slot - 1;
    }

    private static function firstSlotIndex(int $hash): int
    {
        return $hash & self::KEY_MASK;
    }

    private static function secondSlotIndex(int $hash): int
    {
        return ($hash >> self::KEY_SIZE) & self::KEY_MASK;
    }
}
