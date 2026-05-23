<?php

declare(strict_types=1);

use PortLibs\Rclone\ListDirectory;
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
];
