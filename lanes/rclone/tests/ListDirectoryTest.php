<?php

declare(strict_types=1);

use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\ObjectInfo;

function rclone_list_directory_object(string $path, string $providerKey = ''): ObjectInfo
{
    return new ObjectInfo(
        $path,
        strlen($path),
        hash('sha256', $path . $providerKey),
        providerKey: $providerKey === '' ? null : $providerKey,
    );
}

function rclone_list_directory_object_with_size(string $path, int $size): ObjectInfo
{
    return new ObjectInfo($path, $size, hash('sha256', $path . ':' . $size));
}

function rclone_list_directory_directory(string $path): ObjectInfo
{
    return ListDirectory::directory($path);
}

function rclone_list_directory_paths(array $entries): array
{
    return array_map(static fn (ObjectInfo $entry): string => $entry->path, $entries);
}

function rclone_list_directory_names(array $entries): array
{
    return array_map(
        static fn (ObjectInfo $entry): string => $entry->path
            . ($entry->size < 0 && $entry->sha256 === '' && $entry->hashes === [] ? '/' : ''),
        $entries,
    );
}

return [
    'list directory includeAll bypasses filters then stable sorts by remote' => static function (TestRunner $t): void {
        $entries = [
            rclone_list_directory_directory('a'),
            rclone_list_directory_object('A'),
            rclone_list_directory_directory('b'),
            rclone_list_directory_object('B'),
            rclone_list_directory_directory('c'),
            rclone_list_directory_object('C'),
            rclone_list_directory_directory('d'),
            rclone_list_directory_object('D'),
        ];

        $includeObject = static fn (ObjectInfo $entry): bool => $entry->path !== 'B';
        $includeDirectory = static fn (string $remote): bool => $remote !== 'c';

        $unfiltered = ListDirectory::filterAndSortDir($entries, true, '', $includeObject, $includeDirectory);
        $filtered = ListDirectory::filterAndSortDir($entries, false, '', $includeObject, $includeDirectory);

        $t->same(['A', 'B', 'C', 'D', 'a', 'b', 'c', 'd'], rclone_list_directory_paths($unfiltered));
        $t->same(['A', 'C', 'D', 'a', 'b', 'd'], rclone_list_directory_paths($filtered));
    },
    'list directory drops entries outside requested subdirectory like upstream' => static function (TestRunner $t): void {
        $entries = [
            rclone_list_directory_directory('dir'),
            rclone_list_directory_directory('dir/'),
            rclone_list_directory_object('diR/a'),
            rclone_list_directory_directory('dir/b'),
            rclone_list_directory_object('dir/B/sub'),
            rclone_list_directory_directory('dir/c'),
            rclone_list_directory_object('dir/C'),
            rclone_list_directory_directory('dir/d'),
            rclone_list_directory_object('dir/D'),
        ];

        $filtered = ListDirectory::filterAndSortDir($entries, true, 'dir');

        $t->same(['dir/', 'dir/C', 'dir/D', 'dir/b', 'dir/c', 'dir/d'], rclone_list_directory_paths($filtered));
    },
    'list directory root drops empty root and nested entries but permits slash-only buckets' => static function (TestRunner $t): void {
        $entries = [
            rclone_list_directory_directory(''),
            rclone_list_directory_directory('/'),
            rclone_list_directory_object('A'),
            rclone_list_directory_directory('b'),
            rclone_list_directory_object('B/sub'),
            rclone_list_directory_directory('c'),
            rclone_list_directory_object('C'),
            rclone_list_directory_directory('d'),
            rclone_list_directory_object('D'),
        ];

        $filtered = ListDirectory::filterAndSortDir($entries, true, '');

        $t->same(['/', 'A', 'C', 'D', 'b', 'c', 'd'], rclone_list_directory_paths($filtered));
    },
    'list directory reports unknown entry types' => static function (TestRunner $t): void {
        $entries = [
            rclone_list_directory_directory(''),
            rclone_list_directory_object('A'),
            'b',
            rclone_list_directory_object('B/sub'),
        ];

        $t->throws(RuntimeException::class, static fn () => ListDirectory::filterAndSortDir($entries, true, ''));
    },
    'list directory stable sort preserves provider order for duplicate remotes' => static function (TestRunner $t): void {
        $entries = [
            rclone_list_directory_object('uploads/hero.jpg', 'first'),
            rclone_list_directory_object('uploads/hero.jpg', 'second'),
            rclone_list_directory_object('uploads/banner.jpg', 'third'),
        ];

        $filtered = ListDirectory::filterAndSortDir($entries, true, 'uploads');

        $t->same(['third', 'first', 'second'], array_map(
            static fn (ObjectInfo $entry): ?string => $entry->providerKey,
            $filtered,
        ));
    },
    'dir sorted over provider List matches upstream include and filter matrix' => static function (TestRunner $t): void {
        $listCalls = [];
        $list = static function (string $dir) use (&$listCalls): array {
            $listCalls[] = $dir;

            return match ($dir) {
                '' => [
                    rclone_list_directory_object_with_size('a.txt', strlen('hello world')),
                    rclone_list_directory_directory('sub dir'),
                    rclone_list_directory_object_with_size('zend.txt', strlen('hello')),
                ],
                'sub dir' => [
                    rclone_list_directory_object_with_size('sub dir/hello world', strlen('hello world')),
                    rclone_list_directory_object_with_size('sub dir/hello world2', strlen('hello world')),
                    rclone_list_directory_directory('sub dir/ignore dir'),
                    rclone_list_directory_directory('sub dir/sub sub dir'),
                ],
                default => throw new RuntimeException("unexpected List call for {$dir}"),
            };
        };
        $includeObject = static fn (ObjectInfo $entry): bool => $entry->size <= 10;
        $includeDirectory = static fn (string $remote): bool => true;

        $t->same(['a.txt', 'sub dir/', 'zend.txt'], rclone_list_directory_names(
            ListDirectory::dirSorted($list, true, '', $includeObject, $includeDirectory),
        ));
        $t->same(['sub dir/', 'zend.txt'], rclone_list_directory_names(
            ListDirectory::dirSorted($list, false, '', $includeObject, $includeDirectory),
        ));
        $t->same([
            'sub dir/hello world',
            'sub dir/hello world2',
            'sub dir/ignore dir/',
            'sub dir/sub sub dir/',
        ], rclone_list_directory_names(
            ListDirectory::dirSorted($list, true, 'sub dir', $includeObject, $includeDirectory),
        ));
        $t->same(['sub dir/ignore dir/', 'sub dir/sub sub dir/'], rclone_list_directory_names(
            ListDirectory::dirSorted($list, false, 'sub dir', $includeObject, $includeDirectory),
        ));
        $t->same(['', '', 'sub dir', 'sub dir'], $listCalls);
    },
    'dir sorted excludes marker directories and includeAll bypasses markers' => static function (TestRunner $t): void {
        $list = static function (string $dir): array {
            return match ($dir) {
                'sub dir' => [
                    rclone_list_directory_object_with_size('sub dir/hello world', strlen('hello world')),
                    rclone_list_directory_directory('sub dir/ignore dir'),
                    rclone_list_directory_directory('sub dir/sub sub dir'),
                ],
                'sub dir/ignore dir' => [
                    rclone_list_directory_object_with_size('sub dir/ignore dir/.ignore', 1),
                    rclone_list_directory_object_with_size('sub dir/ignore dir/should be ignored', strlen('to ignore')),
                ],
                default => throw new RuntimeException("unexpected List call for {$dir}"),
            };
        };
        $includeObject = static fn (ObjectInfo $entry): bool => $entry->size <= 10;
        $includeDirectory = static fn (string $remote): bool => $remote !== 'sub dir/ignore dir';

        $t->same(['sub dir/sub sub dir/'], rclone_list_directory_names(
            ListDirectory::dirSorted($list, false, 'sub dir', $includeObject, $includeDirectory, ['.ignore']),
        ));

        $excluded = ListDirectory::dirSortedResult(
            $list,
            false,
            'sub dir/ignore dir',
            $includeObject,
            static fn (string $remote): bool => true,
            ['.ignore'],
        );

        $t->same([], rclone_list_directory_paths($excluded['entries']));
        $t->same(2, $excluded['listed']);
        $t->same(true, $excluded['excluded']);
        $t->same([
            'sub dir/ignore dir/.ignore',
            'sub dir/ignore dir/should be ignored',
        ], rclone_list_directory_names(
            ListDirectory::dirSorted($list, true, 'sub dir/ignore dir', null, null, ['.ignore']),
        ));
    },
    'dir sorted propagates provider List and filter errors' => static function (TestRunner $t): void {
        $t->throws(RuntimeException::class, static fn () => ListDirectory::dirSorted(
            static fn (string $dir): array => throw new RuntimeException('provider List failed'),
            true,
            '',
        ));
        $t->throws(RuntimeException::class, static fn () => ListDirectory::dirSorted(
            static fn (string $dir): array => [rclone_list_directory_directory('sub dir')],
            false,
            '',
            static fn (ObjectInfo $entry): bool => true,
            static fn (string $remote): bool => throw new RuntimeException('directory filter failed'),
        ));
        $t->throws(RuntimeException::class, static fn () => ListDirectory::dirSorted(
            static fn (string $dir): array => ['not an entry'],
            true,
            '',
        ));
    },
    'dir sorted fn filters ListP pages then sends globally sorted entries' => static function (TestRunner $t): void {
        $sent = [];
        $stats = ListDirectory::dirSortedFn(
            static function (callable $callback): void {
                $callback([
                    rclone_list_directory_object('site-backups/export.wxr'),
                    rclone_list_directory_directory('site-backups/cache'),
                    rclone_list_directory_object('site-backups/cache/object-cache.php'),
                ]);
                $callback([
                    rclone_list_directory_object('site-backups/database.sql'),
                    rclone_list_directory_directory('site-backups/uploads'),
                    rclone_list_directory_object('other-site/export.wxr'),
                    rclone_list_directory_object('site-backups/debug.log'),
                ]);
            },
            false,
            'site-backups',
            static function (array $entries) use (&$sent): void {
                $sent[] = rclone_list_directory_paths($entries);
            },
            null,
            static fn (ObjectInfo $entry): bool => str_ends_with($entry->path, '.wxr') || str_ends_with($entry->path, '.sql'),
            static fn (string $remote): bool => $remote !== 'site-backups/cache',
        );

        $t->same([
            ['site-backups/database.sql', 'site-backups/export.wxr', 'site-backups/uploads'],
        ], $sent);
        $t->same(['listed' => 7, 'pages' => 2, 'excludedPages' => 0, 'sent' => 3], $stats);
    },
    'dir sorted fn applies caller key function across provider pages' => static function (TestRunner $t): void {
        $sent = [];
        $restoreKey = static function (ObjectInfo $entry): string {
            return match (true) {
                str_ends_with($entry->path, '.sql') => '0:' . $entry->path,
                $entry->path === 'site-backups/export.wxr' => '1:' . $entry->path,
                str_ends_with($entry->path, '.wxr') => '2:' . $entry->path,
                default => '9:' . $entry->path,
            };
        };

        ListDirectory::dirSortedFn(
            static function (callable $callback): void {
                $callback([
                    rclone_list_directory_object('site-backups/users.wxr'),
                    rclone_list_directory_object('site-backups/hero.jpg'),
                ]);
                $callback([
                    rclone_list_directory_object('site-backups/database.sql'),
                    rclone_list_directory_object('site-backups/export.wxr'),
                ]);
            },
            true,
            'site-backups',
            static function (array $entries) use (&$sent): void {
                $sent[] = rclone_list_directory_paths($entries);
            },
            $restoreKey,
        );

        $t->same([
            ['site-backups/database.sql', 'site-backups/export.wxr', 'site-backups/users.wxr', 'site-backups/hero.jpg'],
        ], $sent);
    },
    'dir sorted fn skips pages containing exclude-if-present markers' => static function (TestRunner $t): void {
        $sent = [];
        $stats = ListDirectory::dirSortedFn(
            static function (callable $callback): void {
                $callback([
                    rclone_list_directory_object('site-backups/cache/.rclone-ignore'),
                    rclone_list_directory_object('site-backups/cache/object-cache.php'),
                ]);
            },
            false,
            'site-backups/cache',
            static function (array $entries) use (&$sent): void {
                $sent[] = rclone_list_directory_paths($entries);
            },
            null,
            static fn (ObjectInfo $entry): bool => true,
            static fn (string $remote): bool => true,
            ['.rclone-ignore'],
        );

        $t->same([[]], $sent);
        $t->same(['listed' => 2, 'pages' => 1, 'excludedPages' => 1, 'sent' => 0], $stats);
    },
    'dir sorted fn includeAll bypasses exclude-if-present markers' => static function (TestRunner $t): void {
        $sent = [];
        $stats = ListDirectory::dirSortedFn(
            static function (callable $callback): void {
                $callback([
                    rclone_list_directory_object('site-backups/cache/object-cache.php'),
                    rclone_list_directory_object('site-backups/cache/.rclone-ignore'),
                ]);
            },
            true,
            'site-backups/cache',
            static function (array $entries) use (&$sent): void {
                $sent[] = rclone_list_directory_paths($entries);
            },
            null,
            null,
            null,
            ['.rclone-ignore'],
        );

        $t->same([[
            'site-backups/cache/.rclone-ignore',
            'site-backups/cache/object-cache.php',
        ]], $sent);
        $t->same(['listed' => 2, 'pages' => 1, 'excludedPages' => 0, 'sent' => 2], $stats);
    },
    'walk over DirSorted visits parents before children and honors max level' => static function (TestRunner $t): void {
        $listCalls = [];
        $list = static function (string $dir) use (&$listCalls): array {
            $listCalls[] = $dir;

            return match ($dir) {
                '' => [
                    rclone_list_directory_object('A'),
                    rclone_list_directory_directory('a'),
                ],
                'a' => [
                    rclone_list_directory_object('a/B'),
                    rclone_list_directory_directory('a/b'),
                ],
                'a/b' => [
                    rclone_list_directory_object('a/b/C'),
                    rclone_list_directory_directory('a/b/c'),
                ],
                default => throw new RuntimeException("unexpected List call for {$dir}"),
            };
        };
        $visited = [];

        $stats = ListDirectory::walk(
            $list,
            false,
            '',
            2,
            static function (string $dir, array $entries, ?Throwable $error) use (&$visited): null {
                $visited[$dir] = rclone_list_directory_names($entries);

                return null;
            },
            static fn (ObjectInfo $entry): bool => true,
            static fn (string $remote): bool => true,
        );

        $t->same(['', 'a'], array_keys($visited));
        $t->same(['A', 'a/'], $visited['']);
        $t->same(['a/B', 'a/b/'], $visited['a']);
        $t->same(['', 'a'], $listCalls);
        $t->same(['visited' => 2, 'listed' => 4, 'excluded' => 0, 'skipped' => 0], $stats);
    },
    'walk skip dir suppresses child traversal without returning an error' => static function (TestRunner $t): void {
        $listCalls = [];
        $list = static function (string $dir) use (&$listCalls): array {
            $listCalls[] = $dir;

            return match ($dir) {
                '' => [rclone_list_directory_directory('a')],
                'a' => [rclone_list_directory_directory('a/b')],
                'a/b' => [rclone_list_directory_object('a/b/C')],
                default => throw new RuntimeException("unexpected List call for {$dir}"),
            };
        };
        $visited = [];

        $stats = ListDirectory::walk(
            $list,
            true,
            '',
            -1,
            static function (string $dir, array $entries, ?Throwable $error) use (&$visited): ?string {
                $visited[] = $dir;

                return $dir === 'a' ? ListDirectory::ERROR_SKIP_DIR : null;
            },
        );

        $t->same(['', 'a'], $visited);
        $t->same(['', 'a'], $listCalls);
        $t->same(['visited' => 2, 'listed' => 2, 'excluded' => 0, 'skipped' => 1], $stats);
    },
    'walk passes provider errors to callback so they can be masked or returned' => static function (TestRunner $t): void {
        $maskedErrors = [];
        $masked = ListDirectory::walk(
            static fn (string $dir): array => throw new RuntimeException("provider List failed for {$dir}"),
            true,
            '',
            -1,
            static function (string $dir, array $entries, ?Throwable $error) use (&$maskedErrors): null {
                $maskedErrors[] = [$dir, $error?->getMessage(), count($entries)];

                return null;
            },
        );

        $t->same([['', 'provider List failed for ', 0]], $maskedErrors);
        $t->same(['visited' => 1, 'listed' => 0, 'excluded' => 0, 'skipped' => 0], $masked);
        $t->throws(RuntimeException::class, static fn () => ListDirectory::walk(
            static fn (string $dir): array => throw new RuntimeException("provider List failed for {$dir}"),
            true,
            '',
            -1,
            static fn (string $dir, array $entries, ?Throwable $error): ?Throwable => $error,
        ));
    },
    'walk prunes exclude marker directories while includeAll bypasses markers' => static function (TestRunner $t): void {
        $list = static function (string $dir): array {
            return match ($dir) {
                'site-backups' => [
                    rclone_list_directory_directory('site-backups/cache'),
                    rclone_list_directory_directory('site-backups/uploads'),
                ],
                'site-backups/cache' => [
                    rclone_list_directory_object('site-backups/cache/.rclone-ignore'),
                    rclone_list_directory_object('site-backups/cache/object-cache.php'),
                ],
                'site-backups/uploads' => [
                    rclone_list_directory_object('site-backups/uploads/hero.jpg'),
                ],
                default => throw new RuntimeException("unexpected List call for {$dir}"),
            };
        };
        $visited = [];
        $stats = ListDirectory::walk(
            $list,
            false,
            'site-backups',
            -1,
            static function (string $dir, array $entries, ?Throwable $error) use (&$visited): null {
                $visited[$dir] = rclone_list_directory_paths($entries);

                return null;
            },
            static fn (ObjectInfo $entry): bool => true,
            static fn (string $remote): bool => true,
            ['.rclone-ignore'],
        );

        $t->same([], $visited['site-backups/cache']);
        $t->same(['site-backups/uploads/hero.jpg'], $visited['site-backups/uploads']);
        $t->same(['visited' => 3, 'listed' => 5, 'excluded' => 1, 'skipped' => 0], $stats);

        $includeAll = [];
        ListDirectory::walk(
            $list,
            true,
            'site-backups/cache',
            -1,
            static function (string $dir, array $entries, ?Throwable $error) use (&$includeAll): null {
                $includeAll[$dir] = rclone_list_directory_paths($entries);

                return null;
            },
            null,
            null,
            ['.rclone-ignore'],
        );

        $t->same([
            'site-backups/cache/.rclone-ignore',
            'site-backups/cache/object-cache.php',
        ], $includeAll['site-backups/cache']);
    },
    'ListR fallback over Walk filters entry types and returns delayed list errors' => static function (TestRunner $t): void {
        $list = static function (string $dir): array {
            return match ($dir) {
                '' => [
                    rclone_list_directory_object('root.txt'),
                    rclone_list_directory_directory('good'),
                    rclone_list_directory_directory('broken'),
                ],
                'good' => [
                    rclone_list_directory_object('good/export.wxr'),
                    rclone_list_directory_directory('good/uploads'),
                ],
                'good/uploads' => [
                    rclone_list_directory_object('good/uploads/hero.jpg'),
                ],
                'broken' => throw new RuntimeException('provider List failed for broken'),
                default => throw new RuntimeException("unexpected List call for {$dir}"),
            };
        };
        $batches = [];
        $errorMessage = null;

        try {
            ListDirectory::listRecursiveFallback(
                $list,
                true,
                '',
                -1,
                ListDirectory::LIST_OBJECTS,
                static function (array $entries) use (&$batches): null {
                    $batches[] = rclone_list_directory_paths($entries);

                    return null;
                },
            );
        } catch (RuntimeException $throwable) {
            $errorMessage = $throwable->getMessage();
        }

        $t->same('provider List failed for broken', $errorMessage);
        $t->same([
            ['root.txt'],
            ['good/export.wxr'],
            ['good/uploads/hero.jpg'],
        ], array_values(array_filter($batches, static fn (array $batch): bool => $batch !== [])));
    },
    'ListR fallback propagates callback errors before completing traversal' => static function (TestRunner $t): void {
        $t->throws(RuntimeException::class, static fn () => ListDirectory::listRecursiveFallback(
            static fn (string $dir): array => [
                rclone_list_directory_object('root.txt'),
                rclone_list_directory_directory('next'),
            ],
            true,
            '',
            -1,
            ListDirectory::LIST_ALL,
            static fn (array $entries): RuntimeException => new RuntimeException('callback stopped traversal'),
        ));
    },
    'ListR selector falls back to Walk when OneDrive delta does not advertise ListR' => static function (TestRunner $t): void {
        $listCalls = [];
        $batches = [];
        $list = static function (string $dir) use (&$listCalls): array {
            $listCalls[] = $dir;

            return match ($dir) {
                'site-backups' => [
                    rclone_list_directory_object('site-backups/export.wxr'),
                    rclone_list_directory_directory('site-backups/cache'),
                    rclone_list_directory_directory('site-backups/uploads'),
                ],
                'site-backups/uploads' => [
                    rclone_list_directory_object('site-backups/uploads/hero.jpg'),
                ],
                default => throw new RuntimeException("unexpected List call for {$dir}"),
            };
        };

        $result = ListDirectory::listRecursive(
            $list,
            null,
            false,
            'site-backups',
            -1,
            ListDirectory::LIST_ALL,
            static function (array $entries) use (&$batches): null {
                $batches[] = rclone_list_directory_names($entries);

                return null;
            },
            static fn (ObjectInfo $entry): bool => str_ends_with($entry->path, '.wxr')
                || str_ends_with($entry->path, '.jpg'),
            static fn (string $remote): bool => $remote !== 'site-backups/cache',
        );

        $t->same('walk', $result['source']);
        $t->same('provider-listR-unavailable', $result['reason']);
        $t->same(['site-backups', 'site-backups/uploads'], $listCalls);
        $t->same([
            ['site-backups/export.wxr', 'site-backups/uploads/'],
            ['site-backups/uploads/hero.jpg'],
        ], $batches);
        $t->same(['visited' => 2, 'listed' => 4, 'excluded' => 0, 'skipped' => 0, 'sent' => 3], $result['stats']);
    },
    'ListR selector maps upstream fallback gates for bounded exclude and directory filters' => static function (TestRunner $t): void {
        $list = static fn (string $dir): array => match ($dir) {
            'site-backups' => [
                rclone_list_directory_object('site-backups/export.wxr'),
                rclone_list_directory_object('site-backups/.rclone-ignore'),
                rclone_list_directory_directory('site-backups/uploads'),
            ],
            default => [],
        };
        $listR = static fn (string $dir, callable $callback): null => throw new RuntimeException('direct ListR should not be used');
        $noop = static fn (array $entries): null => null;

        $bounded = ListDirectory::listRecursive(
            $list,
            $listR,
            true,
            'site-backups',
            2,
            ListDirectory::LIST_ALL,
            $noop,
        );
        $excluded = ListDirectory::listRecursive(
            $list,
            $listR,
            true,
            'site-backups',
            -1,
            ListDirectory::LIST_ALL,
            $noop,
            excludeIfPresent: ['.rclone-ignore'],
        );
        $directoryFiltered = ListDirectory::listRecursive(
            $list,
            $listR,
            false,
            'site-backups',
            -1,
            ListDirectory::LIST_ALL,
            $noop,
            static fn (ObjectInfo $entry): bool => true,
            static fn (string $remote): bool => $remote !== 'site-backups/uploads',
        );
        $filesFrom = ListDirectory::listRecursive(
            $list,
            $listR,
            true,
            'site-backups',
            -1,
            ListDirectory::LIST_ALL,
            $noop,
            haveFilesFrom: true,
        );

        $t->same(['walk', 'bounded-recursion'], [$bounded['source'], $bounded['reason']]);
        $t->same(['walk', 'exclude-if-present'], [$excluded['source'], $excluded['reason']]);
        $t->same(['walk', 'directory-filters'], [$directoryFiltered['source'], $directoryFiltered['reason']]);
        $t->same(['walk', 'files-from'], [$filesFrom['source'], $filesFrom['reason']]);
    },
    'direct ListR preserves provider batches while filtering list types and entries' => static function (TestRunner $t): void {
        $entries = [
            rclone_list_directory_object('a'),
            rclone_list_directory_object('b'),
            rclone_list_directory_directory('dir'),
            rclone_list_directory_object('dir/a'),
            rclone_list_directory_object('dir/b'),
            rclone_list_directory_object('dir/c'),
        ];
        $calls = [];
        $listR = static function (string $dir, callable $callback) use (&$calls, $entries): null {
            $calls[] = $dir;
            $callback($entries);

            return null;
        };
        $allFiltered = [];
        $stats = ListDirectory::listRecursiveDirect(
            $listR,
            false,
            '',
            ListDirectory::LIST_ALL,
            static function (array $batch) use (&$allFiltered): null {
                $allFiltered[] = rclone_list_directory_paths($batch);

                return null;
            },
            static fn (ObjectInfo $entry): bool => $entry->path === 'b' || $entry->path === 'dir/b',
            static fn (string $remote): bool => true,
        );

        $t->same([['b', 'dir', 'dir/b']], $allFiltered);
        $t->same(['listed' => 6, 'batches' => 1, 'sent' => 3, 'synthesized' => 0, 'syntheticBatches' => 0], $stats);

        $objectsOnly = [];
        ListDirectory::listRecursiveDirect(
            $listR,
            true,
            '',
            ListDirectory::LIST_OBJECTS,
            static function (array $batch) use (&$objectsOnly): null {
                $objectsOnly[] = rclone_list_directory_paths($batch);

                return null;
            },
        );

        $t->same([['a', 'b', 'dir/a', 'dir/b', 'dir/c']], $objectsOnly);
        $t->same(['', ''], $calls);
    },
    'direct ListR synthesizes bucket parents after raw recursive batches' => static function (TestRunner $t): void {
        $entries = [
            rclone_list_directory_object('a'),
            rclone_list_directory_object('b'),
            rclone_list_directory_object('dir/a'),
            rclone_list_directory_object('dir/b'),
            rclone_list_directory_object('dir/subdir/c'),
            rclone_list_directory_directory('dir/subdir'),
        ];
        $listR = static function (string $dir, callable $callback) use ($entries): null {
            $selected = array_values(array_filter(
                $entries,
                static fn (ObjectInfo $entry): bool => $dir === ''
                    || $entry->path === $dir
                    || str_starts_with($entry->path, $dir . '/'),
            ));
            $callback($selected);

            return null;
        };
        $batches = [];
        $stats = ListDirectory::listRecursiveDirect(
            $listR,
            true,
            '',
            ListDirectory::LIST_ALL,
            static function (array $batch) use (&$batches): null {
                $batches[] = rclone_list_directory_names($batch);

                return null;
            },
            synthesizeDirs: true,
        );

        $t->same([
            ['a', 'b', 'dir/a', 'dir/b', 'dir/subdir/c', 'dir/subdir/'],
            ['dir/'],
        ], $batches);
        $t->same(['listed' => 6, 'batches' => 1, 'sent' => 7, 'synthesized' => 1, 'syntheticBatches' => 1], $stats);

        $subdirBatches = [];
        $subdirStats = ListDirectory::listRecursiveDirect(
            $listR,
            false,
            'dir',
            ListDirectory::LIST_ALL,
            static function (array $batch) use (&$subdirBatches): null {
                $subdirBatches[] = rclone_list_directory_names($batch);

                return null;
            },
            static fn (ObjectInfo $entry): bool => $entry->path === 'dir/b',
            static fn (string $remote): bool => true,
            true,
        );

        $t->same([['dir/b', 'dir/subdir/']], $subdirBatches);
        $t->same(['listed' => 4, 'batches' => 1, 'sent' => 2, 'synthesized' => 0, 'syntheticBatches' => 0], $subdirStats);
    },
    'direct ListR batches synthesized missing parents at upstream helper threshold' => static function (TestRunner $t): void {
        $entries = [];
        for ($i = 1; $i <= 101; $i++) {
            $entries[] = rclone_list_directory_object(sprintf('site/parent-%03d/file.txt', $i));
        }
        $batches = [];

        $stats = ListDirectory::listRecursiveDirect(
            static function (string $dir, callable $callback) use ($entries): null {
                $callback($entries);

                return null;
            },
            true,
            'site',
            ListDirectory::LIST_ALL,
            static function (array $batch) use (&$batches): null {
                $batches[] = rclone_list_directory_paths($batch);

                return null;
            },
            synthesizeDirs: true,
        );

        $t->same(3, count($batches));
        $t->same(101, count($batches[0]));
        $t->same(100, count($batches[1]));
        $t->same(1, count($batches[2]));
        $t->same('site/parent-001', $batches[1][0]);
        $t->same('site/parent-100', $batches[1][99]);
        $t->same('site/parent-101', $batches[2][0]);
        $t->same(['listed' => 101, 'batches' => 1, 'sent' => 202, 'synthesized' => 101, 'syntheticBatches' => 2], $stats);
    },
    'direct ListR propagates provider and callback errors before synthetic parent flush' => static function (TestRunner $t): void {
        $afterProviderBatches = [];
        $t->throws(RuntimeException::class, static function () use (&$afterProviderBatches): void {
            ListDirectory::listRecursiveDirect(
                static function (string $dir, callable $callback): RuntimeException {
                    $callback([rclone_list_directory_object('site/uploads/hero.jpg')]);

                    return new RuntimeException('provider ListR failed');
                },
                true,
                'site',
                ListDirectory::LIST_ALL,
                static function (array $batch) use (&$afterProviderBatches): null {
                    $afterProviderBatches[] = rclone_list_directory_paths($batch);

                    return null;
                },
                synthesizeDirs: true,
            );
        });
        $t->same([['site/uploads/hero.jpg']], $afterProviderBatches);

        $afterCallbackBatches = [];
        $t->throws(RuntimeException::class, static function () use (&$afterCallbackBatches): void {
            ListDirectory::listRecursiveDirect(
                static function (string $dir, callable $callback): null {
                    $callback([rclone_list_directory_object('site/uploads/hero.jpg')]);

                    return null;
                },
                true,
                'site',
                ListDirectory::LIST_ALL,
                static function (array $batch) use (&$afterCallbackBatches): RuntimeException {
                    $afterCallbackBatches[] = rclone_list_directory_paths($batch);

                    return new RuntimeException('callback stopped traversal');
                },
                synthesizeDirs: true,
            );
        });
        $t->same([['site/uploads/hero.jpg']], $afterCallbackBatches);
    },
    'walkR dir tree normalizes arbitrary recursive batches into sorted parents' => static function (TestRunner $t): void {
        $result = ListDirectory::dirTreeFromListR(
            static function (string $dir, callable $callback): null {
                $callback([
                    rclone_list_directory_object('z/y/file'),
                    rclone_list_directory_object('a/b/c'),
                    rclone_list_directory_directory('m/n'),
                ]);
                $callback([
                    rclone_list_directory_object('a/a'),
                ]);

                return null;
            },
            true,
            '',
            -1,
        );

        $t->same(4, $result['listed']);
        $t->same(2, $result['batches']);
        $expected = <<<'TREE'
/
  a/
  m/
  z/
a/
  a
  b/
a/b/
  c
m/
  n/
m/n/
z/
  y/
z/y/
  file
TREE;
        $expected .= "\n";
        $t->same($expected, ListDirectory::formatDirTree($result['tree']));
    },
    'walkR dir tree honors upstream maxLevel truncation' => static function (TestRunner $t): void {
        $result = ListDirectory::dirTreeFromListR(
            static function (string $dir, callable $callback): null {
                $callback([
                    rclone_list_directory_object('A'),
                    rclone_list_directory_object('a/B'),
                    rclone_list_directory_object('a/b/C'),
                    rclone_list_directory_object('a/b/c/D'),
                    rclone_list_directory_object('a/b/c/d/E'),
                ]);

                return null;
            },
            true,
            '',
            2,
        );

        $expected = <<<'TREE'
/
  A
  a/
a/
  B
  b/
a/b/
TREE;
        $expected .= "\n";
        $t->same($expected, ListDirectory::formatDirTree($result['tree']));
    },
    'walkR dir tree preserves parents of filtered excluded objects' => static function (TestRunner $t): void {
        $result = ListDirectory::dirTreeFromListR(
            static function (string $dir, callable $callback): null {
                $callback([
                    rclone_list_directory_object('a/.bzEmpty'),
                    rclone_list_directory_object('a/b1/.bzEmpty'),
                    rclone_list_directory_object('a/b2/.bzEmpty'),
                ]);

                return null;
            },
            false,
            '',
            -1,
            static fn (ObjectInfo $entry): bool => !str_ends_with($entry->path, '.bzEmpty'),
            static fn (string $remote): bool => true,
        );

        $expected = <<<'TREE'
/
  a/
a/
  b1/
  b2/
a/b1/
a/b2/
TREE;
        $expected .= "\n";
        $t->same($expected, ListDirectory::formatDirTree($result['tree']));
    },
    'walkR dir tree prunes exclude-file directories after listing' => static function (TestRunner $t): void {
        $entries = [
            rclone_list_directory_object('a'),
            rclone_list_directory_object('b/b'),
            rclone_list_directory_object('b/c/d/e'),
            rclone_list_directory_object('b/c/ign'),
            rclone_list_directory_object('b/c/x'),
        ];

        $filtered = ListDirectory::dirTreeFromListR(
            static function (string $dir, callable $callback) use ($entries): null {
                $callback($entries);

                return null;
            },
            false,
            '',
            -1,
            static fn (ObjectInfo $entry): bool => true,
            static fn (string $remote): bool => true,
            ['ign'],
        );

        $t->same(['b/c'], $filtered['pruned']);
        $expectedFiltered = <<<'TREE'
/
  a
  b/
b/
  b
TREE;
        $expectedFiltered .= "\n";
        $t->same($expectedFiltered, ListDirectory::formatDirTree($filtered['tree']));

        $includeAll = ListDirectory::dirTreeFromListR(
            static function (string $dir, callable $callback) use ($entries): null {
                $callback($entries);

                return null;
            },
            true,
            '',
            -1,
            null,
            null,
            ['ign'],
        );

        $t->same([], $includeAll['pruned']);
        $expectedIncludeAll = <<<'TREE'
/
  a
  b/
b/
  b
  c/
b/c/
  d/
  ign
  x
b/c/d/
  e
TREE;
        $expectedIncludeAll .= "\n";
        $t->same($expectedIncludeAll, ListDirectory::formatDirTree($includeAll['tree']));
    },
    'walkR dir tree propagates ListR provider errors and invalid entries' => static function (TestRunner $t): void {
        $t->throws(RuntimeException::class, static fn () => ListDirectory::dirTreeFromListR(
            static function (string $dir, callable $callback): RuntimeException {
                $callback([rclone_list_directory_object('a')]);

                return new RuntimeException('provider ListR failed');
            },
            true,
            '',
            -1,
        ));

        $t->throws(RuntimeException::class, static fn () => ListDirectory::dirTreeFromListR(
            static function (string $dir, callable $callback): null {
                $callback(['not an entry']);

                return null;
            },
            true,
            '',
            -1,
        ));
    },
    'walkR direct tree visits sorted directories with sorted entries' => static function (TestRunner $t): void {
        $visited = [];
        $stats = ListDirectory::walkRecursiveTree(
            static function (string $dir, callable $callback): null {
                $callback([
                    rclone_list_directory_object('z/y/file'),
                    rclone_list_directory_object('a/b/c'),
                    rclone_list_directory_object('a/a'),
                ]);

                return null;
            },
            true,
            '',
            -1,
            static function (string $dir, array $entries, ?Throwable $error) use (&$visited): null {
                $visited[$dir] = rclone_list_directory_names($entries);

                return null;
            },
        );

        $t->same(['', 'a', 'a/b', 'z', 'z/y'], array_keys($visited));
        $t->same(['a/', 'z/'], $visited['']);
        $t->same(['a/a', 'a/b/'], $visited['a']);
        $t->same(['z/y/file'], $visited['z/y']);
        $t->same(['visited' => 5, 'listed' => 3, 'batches' => 1, 'skipped' => 0, 'pruned' => 0], $stats);
    },
    'walkR direct tree ErrorSkipDir suppresses only descendant prefixes' => static function (TestRunner $t): void {
        $visited = [];
        $stats = ListDirectory::walkRecursiveTree(
            static function (string $dir, callable $callback): null {
                $callback([
                    rclone_list_directory_object('a/file'),
                    rclone_list_directory_object('a/b/c'),
                    rclone_list_directory_object('a2/file'),
                    rclone_list_directory_object('b/file'),
                ]);

                return null;
            },
            true,
            '',
            -1,
            static function (string $dir, array $entries, ?Throwable $error) use (&$visited): ?string {
                $visited[] = $dir;

                return $dir === 'a' ? ListDirectory::ERROR_SKIP_DIR : null;
            },
        );

        $t->same(['', 'a', 'a2', 'b'], $visited);
        $t->same(['visited' => 4, 'listed' => 4, 'batches' => 1, 'skipped' => 1, 'pruned' => 0], $stats);
    },
    'NewDirTree selects direct ListR only for recursive depth' => static function (TestRunner $t): void {
        $listCalls = [];
        $listRCalls = [];
        $list = static function (string $dir) use (&$listCalls): array {
            $listCalls[] = $dir;

            return match ($dir) {
                '' => [
                    rclone_list_directory_object('root.txt'),
                    rclone_list_directory_directory('a'),
                ],
                default => throw new RuntimeException("unexpected List call for {$dir}"),
            };
        };
        $listR = static function (string $dir, callable $callback) use (&$listRCalls): null {
            $listRCalls[] = $dir;
            $callback([rclone_list_directory_object('a/file.txt')]);

            return null;
        };

        $direct = ListDirectory::newDirTree($list, $listR, true, '', -1);
        $directExpected = <<<'TREE'
/
  a/
a/
  file.txt
TREE;
        $directExpected .= "\n";
        $t->same('listR', $direct['source']);
        $t->same($directExpected, ListDirectory::formatDirTree($direct['tree']));
        $t->same([], $listCalls);
        $t->same([''], $listRCalls);

        $fallback = ListDirectory::newDirTree($list, $listR, true, '', 1);
        $fallbackExpected = <<<'TREE'
/
  a/
  root.txt
TREE;
        $fallbackExpected .= "\n";
        $t->same('walk', $fallback['source']);
        $t->same($fallbackExpected, ListDirectory::formatDirTree($fallback['tree']));
        $t->same([''], $listCalls);
        $t->same([''], $listRCalls);
    },
    'NewDirTree falls back to provider List when files-from filters are active without no-traverse' => static function (TestRunner $t): void {
        $listCalls = [];
        $listRCalls = [];
        $list = static function (string $dir) use (&$listCalls): array {
            $listCalls[] = $dir;

            return match ($dir) {
                'site-backups' => [
                    rclone_list_directory_object('site-backups/export.wxr'),
                    rclone_list_directory_directory('site-backups/uploads'),
                ],
                'site-backups/uploads' => [
                    rclone_list_directory_object('site-backups/uploads/hero.jpg'),
                ],
                default => [],
            };
        };
        $listR = static function (string $dir, callable $callback) use (&$listRCalls): null {
            $listRCalls[] = $dir;
            $callback([rclone_list_directory_object('site-backups/should-not-use-listR.wxr')]);

            return null;
        };

        $tree = ListDirectory::newDirTree(
            $list,
            $listR,
            true,
            'site-backups',
            -1,
            filesFrom: ['site-backups/export.wxr'],
        );

        $expected = <<<'TREE'
site-backups/
  export.wxr
  uploads/
site-backups/uploads/
  hero.jpg
TREE;
        $expected .= "\n";
        $t->same('walk', $tree['source']);
        $t->same(['site-backups', 'site-backups/uploads'], $listCalls);
        $t->same([], $listRCalls);
        $t->same($expected, ListDirectory::formatDirTree($tree['tree']));
    },
    'NewDirTree no-traverse files-from builds from explicit object lookups only' => static function (TestRunner $t): void {
        $provider = new MemoryProvider();
        $provider->put('site-backups/export.wxr', '<rss>site</rss>');
        $provider->put('site-backups/uploads/2026/05/hero.jpg', 'image bytes');
        $provider->put('site-backups/uploads/2026/05/generated/thumb.jpg', 'thumb');
        $listCalls = [];
        $listRCalls = [];
        $lookups = [];

        $result = ListDirectory::newDirTree(
            static function (string $dir) use (&$listCalls): array {
                $listCalls[] = $dir;

                return [];
            },
            static function (string $dir, callable $callback) use (&$listRCalls): null {
                $listRCalls[] = $dir;
                $callback([rclone_list_directory_object('should-not-be-used')]);

                return null;
            },
            true,
            'site-backups',
            -1,
            noTraverse: true,
            filesFrom: [
                '/site-backups/uploads/2026/05/hero.jpg',
                'site-backups/export.wxr',
                'site-backups/missing.sql',
                'site-backups/export.wxr',
            ],
            newObject: static function (string $remote) use ($provider, &$lookups): ObjectInfo {
                $lookups[] = $remote;

                return $provider->info($remote);
            },
        );

        $expected = <<<'TREE'
site-backups/
  export.wxr
  uploads/
site-backups/uploads/
  2026/
site-backups/uploads/2026/
  05/
site-backups/uploads/2026/05/
  hero.jpg
TREE;
        $expected .= "\n";
        $t->same('filesFrom', $result['source']);
        $t->same([], $listCalls);
        $t->same([], $listRCalls);
        $t->same([
            'site-backups/uploads/2026/05/hero.jpg',
            'site-backups/export.wxr',
            'site-backups/missing.sql',
        ], $lookups);
        $t->same($expected, ListDirectory::formatDirTree($result['tree']));
        $t->same(2, $result['listed']);
        $t->same(2, $result['batches']);
        $t->same(3, $result['requested']);
    },
    'NewDirTree no-traverse files-from propagates lookup and type errors' => static function (TestRunner $t): void {
        $t->throws(RuntimeException::class, static fn () => ListDirectory::newDirTreeFromFiles(
            ['site-backups/private.wxr'],
            static fn (string $remote): ObjectInfo => throw new RuntimeException("permission denied for {$remote}"),
            true,
            'site-backups',
            -1,
        ));

        $t->throws(RuntimeException::class, static fn () => ListDirectory::newDirTreeFromFiles(
            ['site-backups/export.wxr'],
            static fn (string $remote): string => $remote,
            true,
            'site-backups',
            -1,
        ));
    },
    'GetAll uses direct ListR for unbounded recursive listings and separates entries' => static function (TestRunner $t): void {
        $listRCalls = [];
        $listR = static function (string $dir, callable $callback) use (&$listRCalls): null {
            $listRCalls[] = $dir;
            $callback([
                rclone_list_directory_object('site-backups/database.sql'),
                rclone_list_directory_directory('site-backups/uploads'),
            ]);
            $callback([
                rclone_list_directory_directory('site-backups/uploads/2026'),
                rclone_list_directory_object('site-backups/uploads/2026/05/hero.jpg'),
            ]);

            return null;
        };

        $result = ListDirectory::getAll(
            static fn (string $dir): array => throw new RuntimeException("unexpected List fallback for {$dir}"),
            $listR,
            true,
            'site-backups',
            -1,
        );

        $t->same('listR', $result['source']);
        $t->same(['site-backups'], $listRCalls);
        $t->same([
            'site-backups/database.sql',
            'site-backups/uploads/2026/05/hero.jpg',
        ], rclone_list_directory_paths($result['objects']));
        $t->same([
            'site-backups/uploads',
            'site-backups/uploads/2026',
        ], rclone_list_directory_paths($result['directories']));
        $t->same([
            'listed' => 4,
            'batches' => 2,
            'sent' => 4,
            'synthesized' => 0,
            'syntheticBatches' => 0,
        ], $result['stats']);
    },
    'GetAll falls back to Walk for bounded maxLevel and preserves entry split' => static function (TestRunner $t): void {
        $listCalls = [];
        $listRCalls = [];
        $list = static function (string $dir) use (&$listCalls): array {
            $listCalls[] = $dir;

            return match ($dir) {
                '' => [
                    rclone_list_directory_directory('a'),
                    rclone_list_directory_object('root.txt'),
                ],
                'a' => [
                    rclone_list_directory_directory('a/b'),
                    rclone_list_directory_object('a/file.txt'),
                ],
                default => throw new RuntimeException("unexpected List call for {$dir}"),
            };
        };
        $listR = static function (string $dir, callable $callback) use (&$listRCalls): null {
            $listRCalls[] = $dir;
            $callback([rclone_list_directory_object('should-not-be-used')]);

            return null;
        };

        $result = ListDirectory::getAll($list, $listR, true, '', 2);

        $t->same('walk', $result['source']);
        $t->same(['', 'a'], $listCalls);
        $t->same([], $listRCalls);
        $t->same(['root.txt', 'a/file.txt'], rclone_list_directory_paths($result['objects']));
        $t->same(['a', 'a/b'], rclone_list_directory_paths($result['directories']));
        $t->same(['visited' => 2, 'listed' => 4, 'excluded' => 0, 'skipped' => 0, 'sent' => 4], $result['stats']);
    },
    'GetAll fallback delays provider list errors until sibling directories finish' => static function (TestRunner $t): void {
        $listCalls = [];
        $message = null;
        $list = static function (string $dir) use (&$listCalls): array {
            $listCalls[] = $dir;

            return match ($dir) {
                '' => [
                    rclone_list_directory_directory('good'),
                    rclone_list_directory_directory('broken'),
                ],
                'broken' => throw new RuntimeException('provider List failed for broken'),
                'good' => [rclone_list_directory_object('good/export.wxr')],
                default => throw new RuntimeException("unexpected List call for {$dir}"),
            };
        };

        try {
            ListDirectory::getAll($list, null, true, '', -1);
        } catch (RuntimeException $throwable) {
            $message = $throwable->getMessage();
        }

        $t->same('provider List failed for broken', $message);
        $t->same(['', 'broken', 'good'], $listCalls);
    },
    'GetAll direct ListR propagates provider errors' => static function (TestRunner $t): void {
        $callbackWasCalled = false;
        $message = null;
        $listR = static function (string $dir, callable $callback) use (&$callbackWasCalled): RuntimeException {
            $callback([rclone_list_directory_object('site-backups/export.wxr')]);
            $callbackWasCalled = true;

            return new RuntimeException('provider ListR failed');
        };

        try {
            ListDirectory::getAll(
                static fn (string $dir): array => [],
                $listR,
                true,
                'site-backups',
                -1,
            );
        } catch (RuntimeException $throwable) {
            $message = $throwable->getMessage();
        }

        $t->same(true, $callbackWasCalled);
        $t->same('provider ListR failed', $message);
    },
    'wordpress direct backup manifest example filters and sorts one listed directory' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-list-filter-sort.php';

        $t->same([
            'site-backups/database.sql',
            'site-backups/export.wxr',
            'site-backups/uploads',
        ], $example['directEntries']);
        $t->same(true, $example['cachePruned']);
        $t->same(true, $example['nestedLeakIgnored']);
        $t->same(3, $example['entryCount']);
    },
    'wordpress DirSortedFn restore manifest example filters paged provider entries' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-dirsortedfn-restore-manifest.php';

        $t->same([
            'site-backups/database.sql',
            'site-backups/export.wxr',
            'site-backups/users.wxr',
            'site-backups/uploads',
        ], $example['manifest']);
        $t->same([4], $example['batchSizes']);
        $t->same(['listed' => 7, 'pages' => 2, 'excludedPages' => 0, 'sent' => 4], $example['manifestStats']);
        $t->same([], $example['cacheManifest']);
        $t->same(['listed' => 2, 'pages' => 1, 'excludedPages' => 1, 'sent' => 0], $example['cacheStats']);
    },
    'wordpress DirSorted restore manifest example uses provider List fallback' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-dirsorted-restore-manifest.php';

        $t->same([
            'site-backups/database.sql',
            'site-backups/export.wxr',
            'site-backups/users.wxr',
            'site-backups/uploads',
        ], $example['manifest']);
        $t->same(7, $example['listed']);
        $t->same(false, $example['excluded']);
        $t->same([], $example['cacheManifest']);
        $t->same(2, $example['cacheListed']);
        $t->same(true, $example['cacheExcluded']);
        $t->same([
            'site-backups/cache/.rclone-ignore',
            'site-backups/cache/object-cache.php',
        ], $example['cacheIncludeAll']);
    },
    'wordpress recursive walk restore manifest example prunes cache and respects max depth' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-walk-recursive-restore-manifest.php';

        $t->same([
            'site-backups/database.sql',
            'site-backups/export.wxr',
            'site-backups/users.wxr',
            'site-backups/uploads/',
            'site-backups/uploads/2026/',
            'site-backups/uploads/2026/05/',
            'site-backups/uploads/2026/05/generated/',
            'site-backups/uploads/2026/05/hero.jpg',
            'site-backups/uploads/2026/05/hero.webp',
        ], $example['manifest']);
        $t->same(['visited' => 5, 'listed' => 12, 'excluded' => 1, 'skipped' => 0, 'sent' => 10], $example['stats']);
        $t->same(true, $example['cachePruned']);
        $t->same(true, $example['maxDepthStoppedBeforeGeneratedFiles']);
    },
    'wordpress direct ListR bucket manifest example synthesizes missing upload parents' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-direct-listr-bucket-manifest.php';

        $t->same([
            'site-backups/database.sql',
            'site-backups/export.wxr',
            'site-backups/users.wxr',
            'site-backups/uploads/',
            'site-backups/uploads/2026/',
            'site-backups/uploads/2026/05/',
            'site-backups/uploads/2026/05/hero.jpg',
        ], $example['manifest']);
        $t->same([3, 2, 2], $example['batchSizes']);
        $t->same(['listed' => 5, 'batches' => 2, 'sent' => 7, 'synthesized' => 2, 'syntheticBatches' => 1], $example['stats']);
        $t->same(true, $example['uploadsParentSynthesized']);
        $t->same(true, $example['providerOrderPreservedBeforePublishSort']);
    },
    'wordpress direct dir tree restore manifest example prunes cache after ListR' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-dirtree-direct-restore-manifest.php';

        $t->same([
            'site-backups/database.sql',
            'site-backups/export.wxr',
            'site-backups/users.wxr',
            'site-backups/uploads/',
            'site-backups/uploads/2026/',
            'site-backups/uploads/2026/05/',
            'site-backups/uploads/2026/05/generated/',
            'site-backups/uploads/2026/05/hero.jpg',
        ], $example['manifest']);
        $t->same(['site-backups/cache'], $example['pruned']);
        $t->same(true, $example['cachePruned']);
        $t->same(true, $example['generatedDirPreserved']);
        $t->same(true, $example['maxDepthStoppedBeforeGeneratedFiles']);
        $t->same(8, $example['treeEntryCount']);
    },
    'wordpress direct walkR restore manifest example skips cache subtree' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-walkr-direct-restore-manifest.php';

        $t->same([
            'site-backups/database.sql',
            'site-backups/export.wxr',
            'site-backups/users.wxr',
            'site-backups/uploads/',
            'site-backups/uploads/2026/',
            'site-backups/uploads/2026/05/',
            'site-backups/uploads/2026/05/hero.jpg',
        ], $example['manifest']);
        $t->same([
            'site-backups',
            'site-backups/cache',
            'site-backups/uploads',
            'site-backups/uploads/2026',
            'site-backups/uploads/2026/05',
        ], $example['visitedDirs']);
        $t->same(true, $example['cacheSubtreeSkipped']);
        $t->same(['visited' => 5, 'listed' => 6, 'batches' => 2, 'skipped' => 1, 'pruned' => 0], $example['stats']);
    },
    'wordpress GetAll restore catalog example separates upload dirs and portable artifacts' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-getall-restore-catalog.php';

        $t->same('listR', $example['source']);
        $t->same([
            'site-backups/database.sql',
            'site-backups/uploads/2026/05/hero.jpg',
            'site-backups/users.wxr',
            'site-backups/export.wxr',
        ], $example['objects']);
        $t->same([
            'site-backups/uploads',
            'site-backups/uploads/2026',
            'site-backups/uploads/2026/05',
        ], $example['directories']);
        $t->same([
            'site-backups/database.sql',
            'site-backups/export.wxr',
            'site-backups/users.wxr',
            'site-backups/uploads/',
            'site-backups/uploads/2026/',
            'site-backups/uploads/2026/05/',
            'site-backups/uploads/2026/05/hero.jpg',
        ], $example['manifest']);
        $t->same(['listed' => 7, 'batches' => 2, 'sent' => 7, 'synthesized' => 0, 'syntheticBatches' => 0], $example['stats']);
    },
    'wordpress onedrive disabled delta fallback example uses provider List walk' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-disabled-delta-fallback.php';

        $t->same('walk', $example['source']);
        $t->same('provider-listR-unavailable', $example['reason']);
        $t->same(false, $example['listRAdvertised']);
        $t->same(0, $example['deltaListRCalls']);
        $t->same([
            'site-backups',
            'site-backups/uploads',
            'site-backups/uploads/2026',
            'site-backups/uploads/2026/05',
        ], $example['listCalls']);
        $t->same([
            'site-backups/database.sql',
            'site-backups/export.wxr',
            'site-backups/users.wxr',
            'site-backups/uploads/',
            'site-backups/uploads/2026/',
            'site-backups/uploads/2026/05/',
            'site-backups/uploads/2026/05/hero.jpg',
        ], $example['manifest']);
        $t->same(true, $example['cachePruned']);
        $t->same(true, $example['generatedFilesBoundedOut']);
    },
    'wordpress files-from no-traverse restore example avoids provider traversal' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-files-from-no-traverse-restore.php';

        $t->same('filesFrom', $example['source']);
        $t->same([
            'site-backups/database.sql',
            'site-backups/export.wxr',
            'site-backups/uploads/',
            'site-backups/uploads/2026/',
            'site-backups/uploads/2026/05/',
            'site-backups/uploads/2026/05/hero.jpg',
        ], $example['manifest']);
        $t->same([
            'site-backups/database.sql',
            'site-backups/uploads/2026/05/hero.jpg',
            'site-backups/export.wxr',
            'site-backups/missing.wxr',
        ], $example['lookups']);
        $t->same(0, $example['providerListCalls']);
        $t->same(0, $example['providerListRCalls']);
        $t->same(3, $example['listed']);
        $t->same(4, $example['requested']);
        $t->same(true, $example['missingSkipped']);
    },
];
