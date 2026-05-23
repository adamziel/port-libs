<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

/**
 * Native slice of rclone's fs/list.Sorter.
 *
 * The Go implementation can switch to an external on-disk sorter after
 * --list-cutoff. This PHP port keeps the data in memory, but preserves the
 * public ordering, callback, cleanup, cutoff transition, and temp-dir failure
 * boundary relevant to callers that depend on Sorter semantics.
 */
final class ListSorter
{
    public const DEFAULT_LIST_CUTOFF = 100000;
    public const LIST_HELPER_BATCH_SIZE = 100;

    /**
     * @var list<ObjectInfo>
     */
    private array $entries = [];

    private bool $externalSort = false;

    /**
     * @param callable(list<ObjectInfo>): void $callback
     * @param null|callable(ObjectInfo): string $keyFn
     */
    public function __construct(
        private readonly mixed $callback,
        private readonly mixed $keyFn = null,
        private readonly int $cutoff = self::DEFAULT_LIST_CUTOFF,
        private readonly ?string $tempDir = null,
        private readonly int $batchSize = self::LIST_HELPER_BATCH_SIZE,
    ) {
        if (!is_callable($this->callback)) {
            throw new \InvalidArgumentException('list sorter callback must be callable');
        }
        if ($this->keyFn !== null && !is_callable($this->keyFn)) {
            throw new \InvalidArgumentException('list sorter key function must be callable or null');
        }
        if ($this->cutoff < 1) {
            throw new \InvalidArgumentException('list sorter cutoff must be positive');
        }
        if ($this->batchSize < 1) {
            throw new \InvalidArgumentException('list sorter batch size must be positive');
        }
    }

    /**
     * @param iterable<ObjectInfo> $entries
     */
    public function add(iterable $entries): void
    {
        foreach ($entries as $entry) {
            if (!$entry instanceof ObjectInfo) {
                $type = get_debug_type($entry);
                throw new \InvalidArgumentException("list sorter entries must be ObjectInfo instances, got {$type}");
            }
            $this->entries[] = $entry;
        }

        if (!$this->externalSort && count($this->entries) >= $this->cutoff) {
            $this->startExternalSort();
        }
    }

    public function send(): void
    {
        $this->entries = self::stableSort($this->entries, $this->keyFn);

        if (!$this->externalSort) {
            ($this->callback)($this->entries);

            return;
        }

        foreach (array_chunk($this->entries, $this->batchSize) as $chunk) {
            ($this->callback)($chunk);
        }
    }

    public function cleanUp(): void
    {
        $this->entries = [];
        $this->externalSort = false;
    }

    /**
     * @return list<ObjectInfo>
     */
    public function pending(): array
    {
        return $this->entries;
    }

    public function usesExternalSort(): bool
    {
        return $this->externalSort;
    }

    /**
     * @param iterable<ObjectInfo> $entries
     * @param null|callable(ObjectInfo): string $keyFn
     * @return list<ObjectInfo>
     */
    public static function sorted(iterable $entries, ?callable $keyFn = null): array
    {
        $list = [];
        foreach ($entries as $entry) {
            if (!$entry instanceof ObjectInfo) {
                $type = get_debug_type($entry);
                throw new \InvalidArgumentException("list sorter entries must be ObjectInfo instances, got {$type}");
            }
            $list[] = $entry;
        }

        return self::stableSort($list, $keyFn);
    }

    private function startExternalSort(): void
    {
        if ($this->tempDir !== null) {
            if ((file_exists($this->tempDir) && !is_dir($this->tempDir)) || !@mkdir($this->tempDir, 0777, true) && !is_dir($this->tempDir)) {
                throw new \RuntimeException('sorter: failed to initialise on-disk sort');
            }
            if (!is_writable($this->tempDir)) {
                throw new \RuntimeException('sorter: failed to initialise on-disk sort');
            }
        }

        $this->externalSort = true;
    }

    /**
     * @param list<ObjectInfo> $entries
     * @param null|callable(ObjectInfo): string $keyFn
     * @return list<ObjectInfo>
     */
    private static function stableSort(array $entries, ?callable $keyFn): array
    {
        $decorated = [];
        foreach ($entries as $index => $entry) {
            $decorated[] = [
                'index' => $index,
                'key' => $keyFn === null ? $entry->path : (string) $keyFn($entry),
                'entry' => $entry,
            ];
        }

        usort(
            $decorated,
            static fn (array $a, array $b): int => $a['key'] <=> $b['key']
                ?: $a['index'] <=> $b['index'],
        );

        return array_map(static fn (array $item): ObjectInfo => $item['entry'], $decorated);
    }
}
