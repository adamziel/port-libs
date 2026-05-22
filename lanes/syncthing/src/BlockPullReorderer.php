<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class BlockPullReorderer
{
    public const ORDER_STANDARD = 'standard';
    public const ORDER_RANDOM = 'random';
    public const ORDER_IN_ORDER = 'inOrder';

    /**
     * @param list<Block> $blocks
     * @param list<DeviceId> $otherDevices
     * @param null|callable(list<int>): list<int> $shuffleChunkIndexes
     *
     * @return list<Block>
     */
    public static function reorder(
        array $blocks,
        string $order,
        DeviceId $localDevice,
        array $otherDevices = [],
        ?callable $shuffleChunkIndexes = null,
    ): array {
        return match ($order) {
            self::ORDER_RANDOM => self::random($blocks, $shuffleChunkIndexes),
            self::ORDER_IN_ORDER => self::inOrder($blocks),
            self::ORDER_STANDARD => self::standard($blocks, $localDevice, $otherDevices, $shuffleChunkIndexes),
            default => self::standard($blocks, $localDevice, $otherDevices, $shuffleChunkIndexes),
        };
    }

    /**
     * @param list<Block> $blocks
     *
     * @return list<Block>
     */
    public static function inOrder(array $blocks): array
    {
        self::assertBlocks($blocks);

        return array_values($blocks);
    }

    /**
     * @param list<Block> $blocks
     * @param null|callable(list<int>): list<int> $shuffleIndexes
     *
     * @return list<Block>
     */
    public static function random(array $blocks, ?callable $shuffleIndexes = null): array
    {
        self::assertBlocks($blocks);

        $count = count($blocks);
        if ($count === 0) {
            return [];
        }

        $indexes = range(0, $count - 1);
        $indexes = $shuffleIndexes !== null ? self::validatedIndexes($shuffleIndexes($indexes), $count) : self::shuffledIndexes($indexes);

        $out = [];
        foreach ($indexes as $index) {
            $out[] = $blocks[$index];
        }

        return $out;
    }

    /**
     * @param list<Block> $blocks
     * @param list<DeviceId> $otherDevices
     * @param null|callable(list<int>): list<int> $shuffleChunkIndexes
     *
     * @return list<Block>
     */
    public static function standard(
        array $blocks,
        DeviceId $localDevice,
        array $otherDevices,
        ?callable $shuffleChunkIndexes = null,
    ): array {
        self::assertBlocks($blocks);
        self::assertDeviceIds($otherDevices);

        if ($blocks === []) {
            return [];
        }

        $devices = [...$otherDevices, $localDevice];
        usort($devices, static fn (DeviceId $a, DeviceId $b): int => $a->compare($b));

        $localIndex = null;
        foreach ($devices as $index => $device) {
            if ($device->equals($localDevice)) {
                $localIndex = $index;
                break;
            }
        }
        if ($localIndex === null) {
            throw new \LogicException('could not find local device index');
        }

        $chunks = self::chunk($blocks, count($devices));
        $out = [];
        if ($localIndex < count($chunks)) {
            array_push($out, ...$chunks[$localIndex]);
        }

        $chunkIndexes = [];
        foreach (array_keys($chunks) as $chunkIndex) {
            if ($chunkIndex !== $localIndex) {
                $chunkIndexes[] = (int) $chunkIndex;
            }
        }

        $chunkIndexes = $shuffleChunkIndexes !== null
            ? self::validatedIndexes($shuffleChunkIndexes($chunkIndexes), count($chunkIndexes), count($chunks))
            : self::shuffledIndexes($chunkIndexes);

        foreach ($chunkIndexes as $chunkIndex) {
            array_push($out, ...$chunks[$chunkIndex]);
        }

        return $out;
    }

    /**
     * @param list<Block> $blocks
     *
     * @return list<list<Block>>
     */
    public static function chunk(array $blocks, int $partCount): array
    {
        self::assertBlocks($blocks);
        if ($partCount < 0) {
            throw new \InvalidArgumentException('Part count must not be negative');
        }
        if ($partCount === 0) {
            return [$blocks];
        }

        $count = count($blocks);
        if ($count === 0) {
            return [];
        }

        $chunkSize = intdiv($count + $partCount - 1, $partCount);
        $parts = [];
        for ($offset = 0; $offset < $count; $offset += $chunkSize) {
            $parts[] = array_slice($blocks, $offset, $chunkSize);
        }

        return $parts;
    }

    /**
     * @param list<Block> $blocks
     */
    private static function assertBlocks(array $blocks): void
    {
        foreach ($blocks as $block) {
            if (!$block instanceof Block) {
                throw new \InvalidArgumentException('Block pull ordering expects Block instances');
            }
        }
    }

    /**
     * @param list<DeviceId> $devices
     */
    private static function assertDeviceIds(array $devices): void
    {
        foreach ($devices as $device) {
            if (!$device instanceof DeviceId) {
                throw new \InvalidArgumentException('Block pull ordering expects DeviceId instances');
            }
        }
    }

    /**
     * @param list<int> $indexes
     *
     * @return list<int>
     */
    private static function shuffledIndexes(array $indexes): array
    {
        shuffle($indexes);

        return array_values($indexes);
    }

    /**
     * @param list<int> $indexes
     *
     * @return list<int>
     */
    private static function validatedIndexes(array $indexes, int $count, ?int $upperBound = null): array
    {
        $upperBound ??= $count;
        if (count($indexes) !== $count) {
            throw new \UnexpectedValueException('Shuffle callback must return every index exactly once');
        }

        $seen = [];
        foreach ($indexes as $index) {
            if (!is_int($index) || $index < 0 || $index >= $upperBound || isset($seen[$index])) {
                throw new \UnexpectedValueException('Shuffle callback must return every index exactly once');
            }
            $seen[$index] = true;
        }

        return array_values($indexes);
    }
}
