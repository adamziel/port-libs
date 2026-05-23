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

function rclone_list_directory_directory(string $path): ObjectInfo
{
    return ListDirectory::directory($path);
}

function rclone_list_directory_paths(array $entries): array
{
    return array_map(static fn (ObjectInfo $entry): string => $entry->path, $entries);
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
];
