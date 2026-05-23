<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

/**
 * Native slice of rclone's fs/list direct directory filtering.
 */
final class ListDirectory
{
    /**
     * Model fs/list.DirSorted over a provider List call.
     *
     * Unlike DirSortedFn this returns the sorted entries directly. The provider
     * List callable is invoked once for the requested directory, its raw result
     * is checked for exclude-if-present markers before filtering, and the final
     * return value is sorted by Remote with stable duplicate handling.
     *
     * @param callable(string): iterable<ObjectInfo> $list
     * @param null|callable(ObjectInfo): bool $includeObject
     * @param null|callable(string): bool $includeDirectory
     * @param list<string> $excludeIfPresent
     * @return list<ObjectInfo>
     */
    public static function dirSorted(
        callable $list,
        bool $includeAll,
        string $dir,
        ?callable $includeObject = null,
        ?callable $includeDirectory = null,
        array $excludeIfPresent = [],
    ): array {
        return self::dirSortedResult(
            $list,
            $includeAll,
            $dir,
            $includeObject,
            $includeDirectory,
            $excludeIfPresent,
        )['entries'];
    }

    /**
     * Same as dirSorted(), but also exposes the upstream-style listed count.
     *
     * @param callable(string): iterable<ObjectInfo> $list
     * @param null|callable(ObjectInfo): bool $includeObject
     * @param null|callable(string): bool $includeDirectory
     * @param list<string> $excludeIfPresent
     * @return array{entries: list<ObjectInfo>, listed: int, excluded: bool}
     */
    public static function dirSortedResult(
        callable $list,
        bool $includeAll,
        string $dir,
        ?callable $includeObject = null,
        ?callable $includeDirectory = null,
        array $excludeIfPresent = [],
    ): array {
        $entries = self::validateEntries($list($dir));
        $listed = count($entries);

        if (!$includeAll && self::listContainsExcludeFile($entries, $excludeIfPresent)) {
            return [
                'entries' => [],
                'listed' => $listed,
                'excluded' => true,
            ];
        }

        return [
            'entries' => self::filterAndSortDir($entries, $includeAll, $dir, $includeObject, $includeDirectory),
            'listed' => $listed,
            'excluded' => false,
        ];
    }

    /**
     * Model fs/list.DirSortedFn over a paged ListP-style provider listing.
     *
     * The supplied $listP callable receives a page callback and may call it
     * repeatedly with raw provider entries. Each page is counted before
     * filtering, pages containing an exclude-if-present marker are skipped
     * when includeAll is false, remaining entries are direct-directory
     * filtered, and the final callback receives globally sorted batches.
     *
     * @param callable(callable(list<ObjectInfo>): void): void $listP
     * @param callable(list<ObjectInfo>): void $callback
     * @param null|callable(ObjectInfo): string $keyFn
     * @param null|callable(ObjectInfo): bool $includeObject
     * @param null|callable(string): bool $includeDirectory
     * @param list<string> $excludeIfPresent
     * @return array{listed: int, pages: int, excludedPages: int, sent: int}
     */
    public static function dirSortedFn(
        callable $listP,
        bool $includeAll,
        string $dir,
        callable $callback,
        ?callable $keyFn = null,
        ?callable $includeObject = null,
        ?callable $includeDirectory = null,
        array $excludeIfPresent = [],
        int $cutoff = ListSorter::DEFAULT_LIST_CUTOFF,
        ?string $tempDir = null,
    ): array {
        $stats = [
            'listed' => 0,
            'pages' => 0,
            'excludedPages' => 0,
            'sent' => 0,
        ];

        $sorter = new ListSorter(
            static function (array $entries) use ($callback, &$stats): void {
                $stats['sent'] += count($entries);
                $callback($entries);
            },
            $keyFn,
            $cutoff,
            $tempDir,
        );

        try {
            $listP(static function (array $entries) use (
                $sorter,
                $includeAll,
                $dir,
                $includeObject,
                $includeDirectory,
                $excludeIfPresent,
                &$stats,
            ): void {
                foreach ($entries as $entry) {
                    if (!$entry instanceof ObjectInfo) {
                        $type = get_debug_type($entry);
                        throw new \RuntimeException("unknown object type {$type}");
                    }
                }

                $stats['pages']++;
                $stats['listed'] += count($entries);

                if (!$includeAll && self::listContainsExcludeFile($entries, $excludeIfPresent)) {
                    $stats['excludedPages']++;

                    return;
                }

                $sorter->add(self::filterDir($entries, $includeAll, $dir, $includeObject, $includeDirectory));
            });

            $sorter->send();

            return $stats;
        } finally {
            $sorter->cleanUp();
        }
    }

    /**
     * Filter direct directory entries and return them in stable Remote order.
     *
     * This models fs/list.filterAndSortDir: includeAll bypasses object and
     * directory filters, entries outside the listed directory are ignored, and
     * duplicate remotes keep provider order after sorting.
     *
     * @param iterable<mixed> $entries
     * @param null|callable(ObjectInfo): bool $includeObject
     * @param null|callable(string): bool $includeDirectory
     * @return list<ObjectInfo>
     */
    public static function filterAndSortDir(
        iterable $entries,
        bool $includeAll,
        string $dir,
        ?callable $includeObject = null,
        ?callable $includeDirectory = null,
    ): array {
        $filtered = self::filterDir($entries, $includeAll, $dir, $includeObject, $includeDirectory);
        $decorated = [];

        foreach ($filtered as $index => $entry) {
            $decorated[] = ['index' => $index, 'entry' => $entry];
        }

        usort(
            $decorated,
            static fn (array $a, array $b): int => $a['entry']->path <=> $b['entry']->path
                ?: $a['index'] <=> $b['index'],
        );

        return array_map(static fn (array $item): ObjectInfo => $item['entry'], $decorated);
    }

    public static function directory(string $path): ObjectInfo
    {
        return new ObjectInfo($path, -1, '');
    }

    /**
     * @param iterable<ObjectInfo> $entries
     * @param list<string> $excludeIfPresent
     */
    public static function listContainsExcludeFile(iterable $entries, array $excludeIfPresent): bool
    {
        if ($excludeIfPresent === []) {
            return false;
        }

        $markers = array_fill_keys($excludeIfPresent, true);
        foreach ($entries as $entry) {
            if (!$entry instanceof ObjectInfo) {
                $type = get_debug_type($entry);
                throw new \RuntimeException("unknown object type {$type}");
            }
            if (self::isDirectory($entry)) {
                continue;
            }
            if (isset($markers[self::baseName($entry->path)])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $entries
     * @return list<ObjectInfo>
     */
    private static function validateEntries(mixed $entries): array
    {
        if (!is_iterable($entries)) {
            $type = get_debug_type($entries);
            throw new \RuntimeException("provider List must return iterable entries, got {$type}");
        }

        $validated = [];
        foreach ($entries as $entry) {
            if (!$entry instanceof ObjectInfo) {
                $type = get_debug_type($entry);
                throw new \RuntimeException("unknown object type {$type}");
            }
            $validated[] = $entry;
        }

        return $validated;
    }

    /**
     * @param iterable<mixed> $entries
     * @param null|callable(ObjectInfo): bool $includeObject
     * @param null|callable(string): bool $includeDirectory
     * @return list<ObjectInfo>
     */
    private static function filterDir(
        iterable $entries,
        bool $includeAll,
        string $dir,
        ?callable $includeObject,
        ?callable $includeDirectory,
    ): array {
        $prefix = '';
        if ($dir !== '') {
            $prefix = $dir;
            if (!self::isAllSlashes($dir)) {
                $prefix .= '/';
            }
        }

        $newEntries = [];
        foreach ($entries as $entry) {
            if (!$entry instanceof ObjectInfo) {
                $type = get_debug_type($entry);
                throw new \RuntimeException("unknown object type {$type}");
            }

            $ok = true;
            if (self::isDirectory($entry)) {
                if (!$includeAll) {
                    if ($includeDirectory === null) {
                        throw new \InvalidArgumentException('directory include callback is required when includeAll is false');
                    }
                    $ok = (bool) $includeDirectory($entry->path);
                }
            } elseif (!$includeAll) {
                if ($includeObject === null) {
                    throw new \InvalidArgumentException('object include callback is required when includeAll is false');
                }
                $ok = (bool) $includeObject($entry);
            }

            if (!$ok || !self::belongsInDirectory($entry->path, $dir, $prefix)) {
                continue;
            }

            $newEntries[] = $entry;
        }

        return $newEntries;
    }

    private static function isDirectory(ObjectInfo $entry): bool
    {
        return $entry->size < 0
            && $entry->sha256 === ''
            && $entry->tier === null
            && $entry->hashes === [];
    }

    private static function belongsInDirectory(string $remote, string $dir, string $prefix): bool
    {
        if (!str_starts_with($remote, $prefix)) {
            return false;
        }
        if ($remote === $dir) {
            return false;
        }

        $leaf = substr($remote, strlen($prefix));

        return !str_contains($leaf, '/') || self::isAllSlashes($leaf);
    }

    private static function isAllSlashes(string $value): bool
    {
        return $value !== '' && strspn($value, '/') === strlen($value);
    }

    private static function baseName(string $path): string
    {
        $path = rtrim($path, '/');
        if ($path === '') {
            return '';
        }

        $slash = strrpos($path, '/');

        return $slash === false ? $path : substr($path, $slash + 1);
    }
}
