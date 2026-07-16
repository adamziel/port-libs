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
     * Model fs/walk.ListR's direct ListR vs Walk fallback selection.
     *
     * Upstream falls back to ordinary Walk/List traversal when recursive ListR
     * is unavailable, disabled by config, blocked by files-from, bounded by
     * maxLevel, or unsafe with exclude-file/directory-filter configuration.
     *
     * @param callable(string): iterable<ObjectInfo> $list
     * @param null|callable(string, callable(list<ObjectInfo>): (null|\Throwable)): (null|\Throwable) $listR
     * @param callable(list<ObjectInfo>): (null|\Throwable) $callback
     * @param null|callable(ObjectInfo): bool $includeObject
     * @param null|callable(string): bool $includeDirectory
     * @param list<string> $excludeIfPresent
     * @return array{source: string, reason: string, stats: array<string, int>}
     */
    public static function listRecursive(
        callable $list,
        ?callable $listR,
        bool $includeAll,
        string $path,
        int $maxLevel,
        int $listType,
        callable $callback,
        ?callable $includeObject = null,
        ?callable $includeDirectory = null,
        array $excludeIfPresent = [],
        bool $haveFilesFrom = false,
        bool $useListR = true,
        bool $synthesizeDirs = false,
    ): array {
        self::assertListType($listType);

        $fallbackReason = self::listRecursiveFallbackReason(
            $listR,
            $maxLevel,
            $excludeIfPresent,
            $includeDirectory,
            $haveFilesFrom,
            $useListR,
        );

        if ($fallbackReason === null) {
            return [
                'source' => 'listR',
                'reason' => 'direct-listR',
                'stats' => self::listRecursiveDirect(
                    $listR,
                    $includeAll,
                    $path,
                    $listType,
                    $callback,
                    $includeObject,
                    $includeDirectory,
                    $synthesizeDirs,
                ),
            ];
        }

        return [
            'source' => 'walk',
            'reason' => $fallbackReason,
            'stats' => self::listRecursiveFallback(
                $list,
                $includeAll,
                $path,
                $maxLevel,
                $listType,
                $callback,
                $includeObject,
                $includeDirectory,
                $excludeIfPresent,
            ),
        ];
    }

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
     * Model fs/walk.GetAll by collecting ListR output into object and directory lists.
     *
     * Upstream GetAll calls ListR with ListAll, which uses direct provider ListR
     * only for unbounded recursive listings that do not require exclude-file
     * fallback. Bounded maxLevel or exclude-if-present rules fall back through
     * Walk over DirSorted, preserving maxLevel and delayed listing-error
     * behavior from listRwalk.
     *
     * @param callable(string): iterable<ObjectInfo> $list
     * @param null|callable(string, callable(list<ObjectInfo>): (null|\Throwable)): (null|\Throwable) $listR
     * @param null|callable(ObjectInfo): bool $includeObject
     * @param null|callable(string): bool $includeDirectory
     * @param list<string> $excludeIfPresent
     * @return array{objects: list<ObjectInfo>, directories: list<ObjectInfo>, source: string, stats: array<string, int>}
     */
    public static function getAll(
        callable $list,
        ?callable $listR,
        bool $includeAll,
        string $path,
        int $maxLevel,
        ?callable $includeObject = null,
        ?callable $includeDirectory = null,
        array $excludeIfPresent = [],
        bool $synthesizeDirs = false,
    ): array {
        $objects = [];
        $directories = [];

        $collector = static function (array $entries) use (&$objects, &$directories): null {
            foreach ($entries as $entry) {
                if (self::isDirectory($entry)) {
                    $directories[] = $entry;
                } else {
                    $objects[] = $entry;
                }
            }

            return null;
        };

        $result = self::listRecursive(
            $list,
            $listR,
            $includeAll,
            $path,
            $maxLevel,
            self::LIST_ALL,
            $collector,
            $includeObject,
            $includeDirectory,
            $excludeIfPresent,
            synthesizeDirs: $synthesizeDirs,
        );

        return [
            'objects' => $objects,
            'directories' => $directories,
            'source' => $result['source'],
            'reason' => $result['reason'],
            'stats' => $result['stats'],
        ];
    }

    /**
     * Model fs/walk.walkRDirTree over a recursive-capable provider.
     *
     * Recursive provider batches may arrive in arbitrary order. This builds the
     * same directory-to-entries tree shape rclone uses for WalkR/NewDirTree:
     * parent directories are synthesized, entries are sorted after the whole
     * recursive listing, maxLevel truncates deep objects to boundary
     * directories, ordinary filters preserve excluded object parents, and
     * exclude-file marker directories are pruned after listing.
     *
     * @param callable(string, callable(list<ObjectInfo>): (null|\Throwable)): (null|\Throwable) $listR
     * @param null|callable(ObjectInfo): bool $includeObject
     * @param null|callable(string): bool $includeDirectory
     * @param list<string> $excludeIfPresent
     * @return array{tree: array<string, list<ObjectInfo>>, listed: int, batches: int, pruned: list<string>}
     */
    public static function dirTreeFromListR(
        callable $listR,
        bool $includeAll,
        string $path,
        int $maxLevel,
        ?callable $includeObject = null,
        ?callable $includeDirectory = null,
        array $excludeIfPresent = [],
    ): array {
        $root = self::normalizeDirectory($path);
        $tree = [];
        $toPrune = [];
        $markers = array_fill_keys($excludeIfPresent, true);
        $stats = [
            'listed' => 0,
            'batches' => 0,
        ];

        $result = $listR(
            $root,
            static function (array $entries) use (
                $includeAll,
                $maxLevel,
                $includeObject,
                $includeDirectory,
                $markers,
                &$tree,
                &$toPrune,
                &$stats,
            ): null {
                $entries = self::validateEntries($entries);
                $stats['batches']++;
                $stats['listed'] += count($entries);

                foreach ($entries as $entry) {
                    $remote = self::normalizeDirectory($entry->path);
                    $slashes = substr_count($remote, '/');

                    if (self::isDirectory($entry)) {
                        $include = $includeAll || $includeDirectory === null || (bool) $includeDirectory($remote);
                        if ($include && ($maxLevel < 0 || $slashes <= $maxLevel - 1)) {
                            if ($slashes === $maxLevel - 1) {
                                self::dirTreeAdd($tree, self::directory($remote));
                            } else {
                                self::dirTreeAddDir($tree, self::directory($remote));
                            }
                        }

                        continue;
                    }

                    $excluded = true;
                    $include = $includeAll || $includeObject === null || (bool) $includeObject($entry);
                    if ($include && ($maxLevel < 0 || $slashes <= $maxLevel - 1)) {
                        self::dirTreeAdd($tree, $entry);
                        $excluded = false;
                    }

                    if ($excluded) {
                        $dirPath = self::parentDirectory($remote);
                        $parentSlashes = $slashes - 1;
                        if ($maxLevel >= 0) {
                            while ($parentSlashes > $maxLevel - 1) {
                                $dirPath = self::parentDirectory($dirPath);
                                $parentSlashes--;
                            }
                        }

                        $includeDir = $includeAll || $includeDirectory === null || (bool) $includeDirectory($dirPath);
                        if ($includeDir && self::dirTreeFind($tree, $dirPath) === null) {
                            self::dirTreeAddDir($tree, self::directory($dirPath));
                        }
                    }

                    if (!$includeAll && isset($markers[self::baseName($remote)])) {
                        $toPrune[self::parentDirectory($remote)] = true;
                    }
                }

                return null;
            },
        );

        if ($result instanceof \Throwable) {
            throw $result;
        }
        if ($result !== null) {
            throw new \InvalidArgumentException('recursive ListR provider must return null or Throwable');
        }

        self::dirTreeCheckParents($tree, $root);
        if ($tree === []) {
            $tree[$root] = [];
        }

        $pruned = array_keys($toPrune);
        sort($pruned, \SORT_STRING);
        self::dirTreePrune($tree, $toPrune);
        self::sortDirTree($tree);

        return [
            'tree' => $tree,
            'listed' => $stats['listed'],
            'batches' => $stats['batches'],
            'pruned' => $pruned,
        ];
    }

    /**
     * Model fs/walk.walkR over the direct ListR DirTree path.
     *
     * ListR builds a sorted directory tree first, then walkR calls the callback
     * once per directory in sorted order. ErrorSkipDir skips descendants by
     * prefix and does not suppress similarly-prefixed siblings such as "a2".
     *
     * @param callable(string, callable(list<ObjectInfo>): (null|\Throwable)): (null|\Throwable) $listR
     * @param callable(string, list<ObjectInfo>, ?\Throwable): (null|string|\Throwable) $callback
     * @param null|callable(ObjectInfo): bool $includeObject
     * @param null|callable(string): bool $includeDirectory
     * @param list<string> $excludeIfPresent
     * @return array{visited: int, listed: int, batches: int, skipped: int, pruned: int}
     */
    public static function walkRecursiveTree(
        callable $listR,
        bool $includeAll,
        string $path,
        int $maxLevel,
        callable $callback,
        ?callable $includeObject = null,
        ?callable $includeDirectory = null,
        array $excludeIfPresent = [],
    ): array {
        $result = self::dirTreeFromListR(
            $listR,
            $includeAll,
            $path,
            $maxLevel,
            $includeObject,
            $includeDirectory,
            $excludeIfPresent,
        );

        $visited = 0;
        $skipped = 0;
        $skipping = false;
        $skipPrefix = '';

        foreach ($result['tree'] as $dir => $entries) {
            if ($skipping) {
                if (str_starts_with($dir, $skipPrefix)) {
                    continue;
                }
                $skipping = false;
            }

            $visited++;
            if (self::invokeWalkCallback($callback, $dir, $entries, null)) {
                $skipped++;
                $skipping = true;
                $skipPrefix = $dir === '' ? '' : $dir . '/';
            }
        }

        return [
            'visited' => $visited,
            'listed' => $result['listed'],
            'batches' => $result['batches'],
            'skipped' => $skipped,
            'pruned' => count($result['pruned']),
        ];
    }

    /**
     * Model fs/walk.NewDirTree selection between files-from, direct ListR, and WalkN.
     *
     * Upstream first uses the --no-traverse plus --files-from branch when both
     * are set, building the tree from explicit object lookups only. Otherwise it
     * uses ListR when a recursive provider listing exists and the caller asks
     * for unbounded recursion or more than one level. Level-one calls and
     * non-recursive providers fall back to Walk over DirSorted.
     *
     * @param callable(string): iterable<ObjectInfo> $list
     * @param null|callable(string, callable(list<ObjectInfo>): (null|\Throwable)): (null|\Throwable) $listR
     * @param null|callable(ObjectInfo): bool $includeObject
     * @param null|callable(string): bool $includeDirectory
     * @param list<string> $excludeIfPresent
     * @param null|list<string> $filesFrom
     * @param null|callable(string): (?ObjectInfo) $newObject
     * @return array{tree: array<string, list<ObjectInfo>>, source: string, listed: int, batches: int, pruned: list<string>, requested?: int}
     */
    public static function newDirTree(
        callable $list,
        ?callable $listR,
        bool $includeAll,
        string $path,
        int $maxLevel,
        ?callable $includeObject = null,
        ?callable $includeDirectory = null,
        array $excludeIfPresent = [],
        bool $noTraverse = false,
        ?array $filesFrom = null,
        ?callable $newObject = null,
    ): array {
        if ($noTraverse && $filesFrom !== null) {
            if ($newObject === null) {
                throw new \InvalidArgumentException('files-from no-traverse requires a new-object callback');
            }

            return self::newDirTreeFromFiles(
                $filesFrom,
                $newObject,
                $includeAll,
                $path,
                $maxLevel,
                $includeObject,
                $includeDirectory,
                $excludeIfPresent,
            );
        }

        if ($listR !== null && ($maxLevel < 0 || $maxLevel > 1) && $filesFrom === null) {
            $result = self::dirTreeFromListR(
                $listR,
                $includeAll,
                $path,
                $maxLevel,
                $includeObject,
                $includeDirectory,
                $excludeIfPresent,
            );

            return [
                'tree' => $result['tree'],
                'source' => 'listR',
                'listed' => $result['listed'],
                'batches' => $result['batches'],
                'pruned' => $result['pruned'],
            ];
        }

        $tree = [];
        $stats = self::walk(
            $list,
            $includeAll,
            $path,
            $maxLevel,
            static function (string $dir, array $entries, ?\Throwable $error) use (&$tree): ?\Throwable {
                if ($error !== null) {
                    return $error;
                }
                $tree[$dir] = $entries;

                return null;
            },
            $includeObject,
            $includeDirectory,
            $excludeIfPresent,
        );
        self::sortDirTree($tree);

        return [
            'tree' => $tree,
            'source' => 'walk',
            'listed' => $stats['listed'],
            'batches' => 0,
            'pruned' => [],
        ];
    }

    /**
     * Model fs/walk.NewDirTree's --no-traverse plus --files-from branch.
     *
     * The upstream filter builds a synthetic ListR from explicit remotes by
     * calling NewObject for each files-from path. Missing objects are skipped,
     * ordinary lookup errors stop the tree construction, and provider traversal
     * is not used.
     *
     * @param list<string> $filesFrom
     * @param callable(string): (?ObjectInfo) $newObject
     * @param null|callable(ObjectInfo): bool $includeObject
     * @param null|callable(string): bool $includeDirectory
     * @param list<string> $excludeIfPresent
     * @return array{tree: array<string, list<ObjectInfo>>, source: string, listed: int, batches: int, pruned: list<string>, requested: int}
     */
    public static function newDirTreeFromFiles(
        array $filesFrom,
        callable $newObject,
        bool $includeAll,
        string $path,
        int $maxLevel,
        ?callable $includeObject = null,
        ?callable $includeDirectory = null,
        array $excludeIfPresent = [],
    ): array {
        $requested = 0;
        $result = self::dirTreeFromListR(
            self::listRFromFiles($filesFrom, $newObject, $requested),
            $includeAll,
            $path,
            $maxLevel,
            $includeObject,
            $includeDirectory,
            $excludeIfPresent,
        );

        return [
            'tree' => $result['tree'],
            'source' => 'filesFrom',
            'listed' => $result['listed'],
            'batches' => $result['batches'],
            'pruned' => $result['pruned'],
            'requested' => $requested,
        ];
    }

    /**
     * @param array<string, list<ObjectInfo>> $tree
     */
    public static function formatDirTree(array $tree): string
    {
        self::sortDirTree($tree);

        $out = '';
        foreach ($tree as $dir => $entries) {
            $out .= $dir . "/\n";
            foreach ($entries as $entry) {
                $out .= '  ' . self::baseName($entry->path) . (self::isDirectory($entry) ? '/' : '') . "\n";
            }
        }

        return $out;
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
     * @param null|callable(string, callable(list<ObjectInfo>): (null|\Throwable)): (null|\Throwable) $listR
     * @param list<string> $excludeIfPresent
     */
    private static function listRecursiveFallbackReason(
        ?callable $listR,
        int $maxLevel,
        array $excludeIfPresent,
        ?callable $includeDirectory,
        bool $haveFilesFrom,
        bool $useListR,
    ): ?string {
        if (!$useListR) {
            return 'listR-disabled';
        }
        if ($listR === null) {
            return 'provider-listR-unavailable';
        }
        if ($haveFilesFrom) {
            return 'files-from';
        }
        if ($maxLevel >= 0) {
            return 'bounded-recursion';
        }
        if ($excludeIfPresent !== []) {
            return 'exclude-if-present';
        }
        if ($includeDirectory !== null) {
            return 'directory-filters';
        }

        return null;
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
     * @param list<string> $filesFrom
     * @param callable(string): (?ObjectInfo) $newObject
     * @return callable(string, callable(list<ObjectInfo>): (null|\Throwable)): null
     */
    private static function listRFromFiles(array $filesFrom, callable $newObject, int &$requested): callable
    {
        $remotes = self::normalizeFilesFrom($filesFrom);

        return static function (string $dir, callable $callback) use ($remotes, $newObject, &$requested): null {
            unset($dir);

            foreach ($remotes as $remote) {
                $requested++;
                try {
                    $entry = $newObject($remote);
                } catch (\Throwable $throwable) {
                    if (self::isObjectNotFound($throwable)) {
                        continue;
                    }

                    throw $throwable;
                }

                if ($entry === null) {
                    continue;
                }
                if (!$entry instanceof ObjectInfo) {
                    $type = get_debug_type($entry);
                    throw new \RuntimeException("new-object callback must return ObjectInfo or null, got {$type}");
                }

                self::invokeRecursiveListCallback($callback, [$entry]);
            }

            return null;
        };
    }

    /**
     * @param list<string> $filesFrom
     * @return list<string>
     */
    private static function normalizeFilesFrom(array $filesFrom): array
    {
        $normalized = [];
        foreach ($filesFrom as $remote) {
            if (!is_string($remote)) {
                $type = get_debug_type($remote);
                throw new \InvalidArgumentException("files-from remote must be a string, got {$type}");
            }

            $remote = self::normalizeDirectory($remote);
            $normalized[$remote] = $remote;
        }

        return array_values($normalized);
    }

    private static function isObjectNotFound(\Throwable $throwable): bool
    {
        $message = strtolower($throwable->getMessage());

        return $message === 'object not found' || str_starts_with($message, 'object not found:');
    }

    /**
     * @param array<string, list<ObjectInfo>> $tree
     */
    private static function dirTreeAdd(array &$tree, ObjectInfo $entry): void
    {
        $parent = self::parentDirectory($entry->path);
        $tree[$parent] ??= [];
        $tree[$parent][] = $entry;
    }

    /**
     * @param array<string, list<ObjectInfo>> $tree
     */
    private static function dirTreeAddDir(array &$tree, ObjectInfo $entry): void
    {
        $dir = self::normalizeDirectory($entry->path);
        if ($dir === '') {
            return;
        }

        self::dirTreeAdd($tree, self::directory($dir));
        $tree[$dir] ??= [];
    }

    /**
     * @param array<string, list<ObjectInfo>> $tree
     */
    private static function dirTreeFind(array $tree, string $path): ?ObjectInfo
    {
        $parent = self::parentDirectory($path);
        foreach ($tree[$parent] ?? [] as $entry) {
            if ($entry->path === $path) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param array<string, list<ObjectInfo>> $tree
     */
    private static function dirTreeCheckParents(array &$tree, string $root): void
    {
        $dirs = [];
        foreach ($tree as $entries) {
            foreach ($entries as $entry) {
                if (self::isDirectory($entry)) {
                    $dirs[$entry->path] = true;
                }
            }
        }

        foreach (array_keys($tree) as $dirPath) {
            self::dirTreeCheckParent($tree, $root, $dirPath, $dirs);
        }
    }

    /**
     * @param array<string, list<ObjectInfo>> $tree
     * @param array<string, bool> $dirs
     */
    private static function dirTreeCheckParent(array &$tree, string $root, string $dirPath, array &$dirs): void
    {
        while (true) {
            if ($dirPath === $root) {
                return;
            }
            if (isset($dirs[$dirPath])) {
                return;
            }

            $parent = self::parentDirectory($dirPath);
            $tree[$parent] ??= [];
            $tree[$parent][] = self::directory($dirPath);
            $dirs[$dirPath] = true;
            $dirPath = $parent;
        }
    }

    /**
     * @param array<string, list<ObjectInfo>> $tree
     * @param array<string, bool> $dirNames
     */
    private static function dirTreePrune(array &$tree, array $dirNames): void
    {
        foreach (array_keys($dirNames) as $dirName) {
            if ($dirName === '') {
                continue;
            }

            $parent = self::parentDirectory($dirName);
            $tree[$parent] = array_values(array_filter(
                $tree[$parent] ?? [],
                static fn (ObjectInfo $entry): bool => !self::isDirectory($entry) || $entry->path !== $dirName,
            ));
        }

        while ($dirNames !== []) {
            foreach (array_keys($dirNames) as $dirName) {
                foreach ($tree[$dirName] ?? [] as $entry) {
                    if (self::isDirectory($entry)) {
                        $dirNames[$entry->path] = true;
                    }
                }
                unset($tree[$dirName], $dirNames[$dirName]);
            }
        }
    }

    /**
     * @param array<string, list<ObjectInfo>> $tree
     */
    private static function sortDirTree(array &$tree): void
    {
        foreach ($tree as &$entries) {
            $entries = ListSorter::sorted($entries);
        }
        unset($entries);

        ksort($tree, \SORT_STRING);
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
