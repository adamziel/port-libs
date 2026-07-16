<?php

declare(strict_types=1);

use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\ListSorter;
use PortLibs\Rclone\ObjectInfo;

function rclone_list_sorter_object(string $path, string $providerKey = ''): ObjectInfo
{
    return new ObjectInfo(
        $path,
        strlen($path),
        hash('sha256', $path . $providerKey),
        providerKey: $providerKey === '' ? null : $providerKey,
    );
}

function rclone_list_sorter_directory(string $path): ObjectInfo
{
    return ListDirectory::directory($path);
}

function rclone_list_sorter_paths(array $entries): array
{
    return array_map(static fn (ObjectInfo $entry): string => $entry->path, $entries);
}

return [
    'list sorter defaults to identity remote ordering and cleans up entries' => static function (TestRunner $t): void {
        $sent = [];
        $sorter = new ListSorter(static function (array $entries) use (&$sent): void {
            $sent[] = rclone_list_sorter_paths($entries);
        });

        $sorter->add([rclone_list_sorter_directory('a')]);
        $sorter->add([rclone_list_sorter_object('A')]);

        $t->same(['a', 'A'], rclone_list_sorter_paths($sorter->pending()));
        $sorter->send();
        $t->same([['A', 'a']], $sent);
        $t->same(['A', 'a'], rclone_list_sorter_paths($sorter->pending()));

        $sorter->cleanUp();
        $t->same([], $sorter->pending());
        $t->same(false, $sorter->usesExternalSort());
    },
    'list sorter identity sort handles reverse provider order' => static function (TestRunner $t): void {
        $sorted = [];
        $sorter = new ListSorter(static function (array $entries) use (&$sorted): void {
            $sorted = rclone_list_sorter_paths($entries);
        });

        for ($i = ord('z'); $i >= ord('a'); $i--) {
            $sorter->add([rclone_list_sorter_object(chr($i))]);
        }

        $t->same('z', $sorter->pending()[0]->path);
        $sorter->send();
        $t->same(range('a', 'z'), $sorted);
    },
    'list sorter key function can reverse alphabetical order' => static function (TestRunner $t): void {
        $keyFn = static function (ObjectInfo $entry): string {
            return sprintf('%03d', 255 - ord($entry->path[0]));
        };
        $sorted = [];
        $sorter = new ListSorter(static function (array $entries) use (&$sorted): void {
            $sorted = rclone_list_sorter_paths($entries);
        }, $keyFn);

        for ($i = ord('a'); $i <= ord('z'); $i++) {
            $sorter->add([rclone_list_sorter_object(chr($i))]);
        }

        $t->same('a', $sorter->pending()[0]->path);
        $sorter->send();
        $t->same(array_reverse(range('a', 'z')), $sorted);
    },
    'list sorter stable sort keeps provider order for equal keys' => static function (TestRunner $t): void {
        $sorted = ListSorter::sorted([
            rclone_list_sorter_object('uploads/Hero.jpg', 'first'),
            rclone_list_sorter_object('uploads/hero.jpg', 'second'),
            rclone_list_sorter_object('uploads/Banner.jpg', 'third'),
        ], static fn (ObjectInfo $entry): string => strtolower($entry->path));

        $t->same([
            'uploads/Banner.jpg',
            'uploads/Hero.jpg',
            'uploads/hero.jpg',
        ], rclone_list_sorter_paths($sorted));
        $t->same(['third', 'first', 'second'], array_map(
            static fn (ObjectInfo $entry): ?string => $entry->providerKey,
            $sorted,
        ));
    },
    'list sorter switches to cutoff mode and sends sorted batches' => static function (TestRunner $t): void {
        $batches = [];
        $sorter = new ListSorter(static function (array $entries) use (&$batches): void {
            $batches[] = rclone_list_sorter_paths($entries);
        }, cutoff: 3);

        for ($i = 100; $i >= 0; $i--) {
            $sorter->add([rclone_list_sorter_object(sprintf('entry-%03d', $i))]);
        }

        $t->same(true, $sorter->usesExternalSort());
        $sorter->send();
        $t->same([100, 1], array_map('count', $batches));
        $t->same('entry-000', $batches[0][0]);
        $t->same('entry-099', $batches[0][99]);
        $t->same('entry-100', $batches[1][0]);
    },
    'list sorter temp directory setup errors preserve upstream sorter prefix' => static function (TestRunner $t): void {
        $blocker = tempnam(sys_get_temp_dir(), 'rclone-sorter-blocker-');
        if ($blocker === false) {
            throw new RuntimeException('Unable to create sorter temp blocker');
        }

        try {
            $sorter = new ListSorter(
                static function (): void {
                },
                cutoff: 1,
                tempDir: $blocker . '/child',
            );

            try {
                $sorter->add([rclone_list_sorter_object('a')]);
            } catch (RuntimeException $throwable) {
                $t->contains('sorter:', $throwable->getMessage());

                return;
            }

            throw new RuntimeException('Expected sorter temp directory setup to fail');
        } finally {
            @unlink($blocker);
        }
    },
    'wordpress sorted backup manifest example groups restore-critical artifacts' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-sorter-backup-manifest.php';

        $t->same([
            'database/site.sql',
            'exports/site.wxr',
            'exports/site-users.wxr',
            'wp-content/uploads/2026/05/gallery.jpg',
            'wp-content/uploads/2026/05/hero.jpg',
            'wp-content/uploads/2026/05/thumbs',
        ], $example['sortedManifest']);
        $t->same(true, $example['usedCutoffMode']);
        $t->same([6], $example['batchSizes']);
        $t->same('database/site.sql', $example['restoreFirst']);
    },
];
