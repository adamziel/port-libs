<?php

declare(strict_types=1);

use PortLibs\Rclone\ListHelper;
use PortLibs\Rclone\ObjectInfo;

function rclone_list_helper_entry(string $path): ObjectInfo
{
    return new ObjectInfo($path, strlen($path), hash('sha256', $path));
}

return [
    'list helper ignores nil entries and waits below upstream batch threshold' => static function (TestRunner $t): void {
        $batches = [];
        $helper = new ListHelper(static function (array $entries) use (&$batches): void {
            $batches[] = array_map(static fn (ObjectInfo $entry): string => $entry->path, $entries);
        });

        $helper->add(null);
        $helper->add(rclone_list_helper_entry('exports/site.wxr'));

        $t->same([], $batches);
        $t->same(['exports/site.wxr'], array_map(static fn (ObjectInfo $entry): string => $entry->path, $helper->pending()));
    },
    'list helper sends and clears at one hundred entries like upstream ListR helper' => static function (TestRunner $t): void {
        $batches = [];
        $helper = new ListHelper(static function (array $entries) use (&$batches): void {
            $batches[] = array_map(static fn (ObjectInfo $entry): string => $entry->path, $entries);
        });

        for ($i = 1; $i <= 100; $i++) {
            $helper->add(rclone_list_helper_entry(sprintf('wp-content/uploads/2026/05/image-%03d.jpg', $i)));
        }

        $t->same(1, count($batches));
        $t->same(100, count($batches[0]));
        $t->same('wp-content/uploads/2026/05/image-001.jpg', $batches[0][0]);
        $t->same('wp-content/uploads/2026/05/image-100.jpg', $batches[0][99]);
        $t->same([], $helper->pending());
    },
    'list helper flush sends partial batches and clears them' => static function (TestRunner $t): void {
        $batches = [];
        $helper = new ListHelper(static function (array $entries) use (&$batches): void {
            $batches[] = array_map(static fn (ObjectInfo $entry): string => $entry->path, $entries);
        });

        $helper->add(rclone_list_helper_entry('database/site.sql'));
        $helper->add(rclone_list_helper_entry('exports/site.wxr'));
        $helper->flush();
        $helper->flush();

        $t->same([['database/site.sql', 'exports/site.wxr']], $batches);
        $t->same([], $helper->pending());
    },
    'list helper callback errors clear pending entries before propagating' => static function (TestRunner $t): void {
        $helper = new ListHelper(static function (): void {
            throw new RuntimeException('BOOM');
        });

        $helper->add(rclone_list_helper_entry('exports/site.wxr'));
        $t->throws(RuntimeException::class, static fn () => $helper->flush());
        $t->same([], $helper->pending());
    },
    'list helper collect with ListP preserves callback order' => static function (TestRunner $t): void {
        $result = ListHelper::collectWithListP(static function (callable $callback): void {
            $callback([
                rclone_list_helper_entry('database/site.sql'),
                rclone_list_helper_entry('exports/site.wxr'),
            ]);
            $callback([
                rclone_list_helper_entry('wp-content/uploads/2026/05/hero.jpg'),
            ]);
        });

        $t->same(null, $result['error']);
        $t->same([
            'database/site.sql',
            'exports/site.wxr',
            'wp-content/uploads/2026/05/hero.jpg',
        ], array_map(static fn (ObjectInfo $entry): string => $entry->path, $result['entries']));
    },
    'list helper collect with ListP returns partial entries and provider error' => static function (TestRunner $t): void {
        $result = ListHelper::collectWithListP(static function (callable $callback): void {
            $callback([
                rclone_list_helper_entry('database/site.sql'),
                rclone_list_helper_entry('exports/site.wxr'),
            ]);
            throw new RuntimeException('BOOM');
        });

        $t->true($result['error'] instanceof RuntimeException);
        $t->same('BOOM', $result['error']?->getMessage());
        $t->same([
            'database/site.sql',
            'exports/site.wxr',
        ], array_map(static fn (ObjectInfo $entry): string => $entry->path, $result['entries']));
    },
    'wordpress batched manifest example exposes ListR helper batching' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-listp-batched-manifest.php';

        $t->same([100, 4], $example['batchSizes']);
        $t->same('database/site.sql', $example['firstBatchFirstPath']);
        $t->same('wp-content/uploads/2026/05/image-096.jpg', $example['firstBatchLastPath']);
        $t->same('wp-content/uploads/2026/05/image-100.jpg', $example['lastBatchLastPath']);
        $t->same(104, $example['manifestCount']);
    },
];
