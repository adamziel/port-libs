<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

/**
 * Native slice of rclone's fs/list direct directory filtering.
 */
final class ListDirectory
{
    public const ERROR_SKIP_DIR = 'skip this directory';
    public const LIST_OBJECTS = 1;
    public const LIST_DIRS = 2;
    public const LIST_ALL = self::LIST_OBJECTS | self::LIST_DIRS;

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
     * Model fs/walk.Walk over the DirSorted provider listing path.
     *
     * The callback receives the listed directory, its direct entries, and a
     * provider/filter error if the directory could not be listed. Returning
     * ERROR_SKIP_DIR suppresses recursion into that directory without becoming
     * the final error, matching upstream walk.ErrorSkipDir.
     *
     * @param callable(string): iterable<ObjectInfo> $list
     * @param callable(string, list<ObjectInfo>, ?\Throwable): (null|string|\Throwable) $callback
     * @param null|callable(ObjectInfo): bool $includeObject
     * @param null|callable(string): bool $includeDirectory
     * @param list<string> $excludeIfPresent
     * @return array{visited: int, listed: int, excluded: int, skipped: int}
     */
    public static function walk(
        callable $list,
        bool $includeAll,
        string $path,
        int $maxLevel,
        callable $callback,
        ?callable $includeObject = null,
        ?callable $includeDirectory = null,
        array $excludeIfPresent = [],
    ): array {
        $stats = [
            'visited' => 0,
            'listed' => 0,
            'excluded' => 0,
            'skipped' => 0,
        ];
        $jobs = [[
            'dir' => self::normalizeDirectory($path),
            'depth' => $maxLevel - 1,
        ]];

        while ($jobs !== []) {
            $job = array_shift($jobs);
            $dir = $job['dir'];
            $depth = $job['depth'];
            $entries = [];
            $error = null;

            try {
                $result = self::dirSortedResult(
                    $list,
                    $includeAll,
                    $dir,
                    $includeObject,
                    $includeDirectory,
                    $excludeIfPresent,
                );
                $entries = $result['entries'];
                $stats['listed'] += $result['listed'];
                if ($result['excluded']) {
                    $stats['excluded']++;
                }
            } catch (\Throwable $throwable) {
                $error = $throwable;
            }

            $stats['visited']++;
            $childJobs = [];
            if ($error === null && $depth !== 0) {
                foreach ($entries as $entry) {
                    if (self::isDirectory($entry)) {
                        $childJobs[] = [
                            'dir' => self::normalizeDirectory($entry->path),
                            'depth' => $depth - 1,
                        ];
                    }
                }
            }

            if (self::invokeWalkCallback($callback, $dir, $entries, $error)) {
                $stats['skipped']++;
                continue;
            }

            foreach ($childJobs as $childJob) {
                $jobs[] = $childJob;
            }
        }

        return $stats;
    }

    /**
     * Model fs/walk.ListR fallback through Walk when direct ListR cannot be used.
     *
     * Directory listing errors are remembered and returned after traversal, while
     * callback errors stop immediately. This matches listRwalk's "carry on
     * listing but return the error at the end" boundary.
     *
     * @param callable(string): iterable<ObjectInfo> $list
     * @param callable(list<ObjectInfo>): (null|\Throwable) $callback
     * @param null|callable(ObjectInfo): bool $includeObject
     * @param null|callable(string): bool $includeDirectory
     * @param list<string> $excludeIfPresent
     * @return array{visited: int, listed: int, excluded: int, skipped: int, sent: int}
     */
    public static function listRecursiveFallback(
        callable $list,
        bool $includeAll,
        string $path,
        int $maxLevel,
        int $listType,
        callable $callback,
        ?callable $includeObject = null,
        ?callable $includeDirectory = null,
        array $excludeIfPresent = [],
    ): array {
        $firstListError = null;
        $sent = 0;

        $stats = self::walk(
            $list,
            $includeAll,
            $path,
            $maxLevel,
            static function (string $dir, array $entries, ?\Throwable $error) use (
                &$firstListError,
                &$sent,
                $listType,
                $callback,
            ): mixed {
                if ($error !== null) {
                    $firstListError ??= $error;

                    return null;
                }

                $entries = self::filterListType($entries, $listType);
                $sent += count($entries);
                $result = $callback($entries);
                if ($result instanceof \Throwable) {
                    return $result;
                }
                if ($result !== null) {
                    throw new \InvalidArgumentException('recursive list callback must return null or Throwable');
                }

                return null;
            },
            $includeObject,
            $includeDirectory,
            $excludeIfPresent,
        );

        if ($firstListError !== null) {
            throw $firstListError;
        }

        return $stats + ['sent' => $sent];
    }

    /**
     * Model the direct fs/walk ListR path used by recursive-capable providers.
     *
     * Backend ListR batches are forwarded in provider order. If $synthesizeDirs
     * is true, raw objects and directories are first recorded in an upstream
     * dirMap-style structure so bucket-based remotes can receive missing parent
     * directories after the provider's recursive listing completes.
     *
     * @param callable(string, callable(list<ObjectInfo>): (null|\Throwable)): (null|\Throwable) $listR
     * @param callable(list<ObjectInfo>): (null|\Throwable) $callback
     * @param null|callable(ObjectInfo): bool $includeObject
     * @param null|callable(string): bool $includeDirectory
     * @return array{listed: int, batches: int, sent: int, synthesized: int, syntheticBatches: int}
     */
    public static function listRecursiveDirect(
        callable $listR,
        bool $includeAll,
        string $path,
        int $listType,
        callable $callback,
        ?callable $includeObject = null,
        ?callable $includeDirectory = null,
        bool $synthesizeDirs = false,
    ): array {
        self::assertListType($listType);

        $root = self::normalizeDirectory($path);
        $stats = [
            'listed' => 0,
            'batches' => 0,
            'sent' => 0,
            'synthesized' => 0,
            'syntheticBatches' => 0,
        ];
        $dirMap = [];

        $result = $listR(
            $root,
            static function (array $entries) use (
                $includeAll,
                $listType,
                $callback,
                $includeObject,
                $includeDirectory,
                $synthesizeDirs,
                $root,
                &$dirMap,
                &$stats,
            ): null {
                $entries = self::validateEntries($entries);
                $stats['batches']++;
                $stats['listed'] += count($entries);

                if ($synthesizeDirs) {
                    self::addSyntheticEntries($dirMap, $root, $entries);
                }

                $entries = self::filterListType($entries, $listType);
                if (!$includeAll) {
                    $entries = self::filterRecursiveEntries($entries, $includeObject, $includeDirectory);
                }

                $stats['sent'] += count($entries);
                self::invokeRecursiveListCallback($callback, $entries);

                return null;
            },
        );

        if ($result instanceof \Throwable) {
            throw $result;
        }
        if ($result !== null) {
            throw new \InvalidArgumentException('recursive ListR provider must return null or Throwable');
        }

        if ($synthesizeDirs) {
            $helper = new ListHelper(
                static function (array $entries) use (&$stats, $callback): void {
                    $stats['syntheticBatches']++;
                    $stats['sent'] += count($entries);
                    self::invokeRecursiveListCallback($callback, $entries);
                },
            );

            foreach (self::unsentSyntheticDirectories($dirMap) as $dir) {
                $stats['synthesized']++;
                $helper->add(self::directory($dir));
            }
            $helper->flush();
        }

        return $stats;
    }

    /**
     * @param iterable<ObjectInfo> $entries
     * @return list<ObjectInfo>
     */
    public static function filterListType(iterable $entries, int $listType): array
    {
        self::assertListType($listType);

        $filtered = [];
        foreach ($entries as $entry) {
            if (!$entry instanceof ObjectInfo) {
                $type = get_debug_type($entry);
                throw new \RuntimeException("unknown object type {$type}");
            }
            if (self::isDirectory($entry)) {
                if (($listType & self::LIST_DIRS) !== 0) {
                    $filtered[] = $entry;
                }
                continue;
            }
            if (($listType & self::LIST_OBJECTS) !== 0) {
                $filtered[] = $entry;
            }
        }

        return $filtered;
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

    public static function isDirectory(ObjectInfo $entry): bool
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

    /**
     * @param callable(string, list<ObjectInfo>, ?\Throwable): (null|string|\Throwable) $callback
     * @param list<ObjectInfo> $entries
     */
    private static function invokeWalkCallback(
        callable $callback,
        string $dir,
        array $entries,
        ?\Throwable $error,
    ): bool {
        $result = $callback($dir, $entries, $error);
        if ($result === self::ERROR_SKIP_DIR) {
            return true;
        }
        if ($result instanceof \Throwable) {
            throw $result;
        }
        if ($result !== null) {
            throw new \InvalidArgumentException('walk callback must return null, Throwable, or ListDirectory::ERROR_SKIP_DIR');
        }

        return false;
    }

    private static function assertListType(int $listType): void
    {
        if (($listType & ~self::LIST_ALL) !== 0 || $listType === 0) {
            throw new \InvalidArgumentException("unknown recursive list type {$listType}");
        }
    }

    /**
     * @param list<ObjectInfo> $entries
     * @param null|callable(ObjectInfo): bool $includeObject
     * @param null|callable(string): bool $includeDirectory
     * @return list<ObjectInfo>
     */
    private static function filterRecursiveEntries(
        array $entries,
        ?callable $includeObject,
        ?callable $includeDirectory,
    ): array {
        $filtered = [];
        foreach ($entries as $entry) {
            if (self::isDirectory($entry)) {
                if ($includeDirectory === null) {
                    throw new \InvalidArgumentException('directory include callback is required when includeAll is false');
                }
                if ((bool) $includeDirectory($entry->path)) {
                    $filtered[] = $entry;
                }
                continue;
            }

            if ($includeObject === null) {
                throw new \InvalidArgumentException('object include callback is required when includeAll is false');
            }
            if ((bool) $includeObject($entry)) {
                $filtered[] = $entry;
            }
        }

        return $filtered;
    }

    /**
     * @param callable(list<ObjectInfo>): (null|\Throwable) $callback
     * @param list<ObjectInfo> $entries
     */
    private static function invokeRecursiveListCallback(callable $callback, array $entries): void
    {
        $result = $callback($entries);
        if ($result instanceof \Throwable) {
            throw $result;
        }
        if ($result !== null) {
            throw new \InvalidArgumentException('recursive list callback must return null or Throwable');
        }
    }

    /**
     * @param array<string, bool> $dirMap
     * @param list<ObjectInfo> $entries
     */
    private static function addSyntheticEntries(array &$dirMap, string $root, array $entries): void
    {
        foreach ($entries as $entry) {
            if (self::isDirectory($entry)) {
                self::addSyntheticDirectory($dirMap, $root, $entry->path, true);
                continue;
            }

            self::addSyntheticDirectory($dirMap, $root, self::parentDirectory($entry->path), false);
        }
    }

    /**
     * @param array<string, bool> $dirMap
     */
    private static function addSyntheticDirectory(array &$dirMap, string $root, string $dir, bool $sent): void
    {
        $root = self::normalizeDirectory($root);
        $dir = self::normalizeDirectory($dir);

        while (true) {
            if ($dir === $root || $dir === '') {
                return;
            }

            if (array_key_exists($dir, $dirMap)) {
                if ($dirMap[$dir]) {
                    return;
                }
                if (!$sent) {
                    return;
                }
            }

            $dirMap[$dir] = $sent;
            $dir = self::parentDirectory($dir);
            $sent = false;
        }
    }

    /**
     * @param array<string, bool> $dirMap
     * @return list<string>
     */
    private static function unsentSyntheticDirectories(array $dirMap): array
    {
        $dirs = [];
        foreach ($dirMap as $dir => $sent) {
            if (!$sent) {
                $dirs[] = $dir;
            }
        }
        sort($dirs, \SORT_STRING);

        return $dirs;
    }

    private static function parentDirectory(string $path): string
    {
        $path = self::normalizeDirectory($path);
        $slash = strrpos($path, '/');

        return $slash === false ? '' : substr($path, 0, $slash);
    }

    private static function normalizeDirectory(string $path): string
    {
        return trim(preg_replace('#/+#', '/', $path) ?? $path, '/');
    }
}
