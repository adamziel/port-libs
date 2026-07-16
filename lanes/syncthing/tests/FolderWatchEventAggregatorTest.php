<?php

declare(strict_types=1);

use PortLibs\Syncthing\FolderWatchEventAggregator;

return [
    'watch aggregator rolls crowded directories and root overflows into parent scans' => static function (TestRunner $t): void {
        $aggregator = new FolderWatchEventAggregator(10, 30, maxFiles: 8, maxFilesPerDir: 2);

        $aggregator->recordEvent('parent/0.jpg', now: 1000);
        $aggregator->recordEvent('parent/1.jpg', now: 1000);
        $t->same(['parent/0.jpg', 'parent/1.jpg'], $aggregator->status(1000)['pendingPaths']);

        $aggregator->recordEvent('parent/2.jpg', now: 1001);
        $t->same(['parent'], $aggregator->status(1001)['pendingPaths']);
        $t->same(['parent' => FolderWatchEventAggregator::EVENT_NON_REMOVE], $aggregator->status(1001)['pendingTypes']);

        $aggregator->recordEvent('parent/3.jpg', FolderWatchEventAggregator::EVENT_REMOVE, 1002);
        $t->same(['parent'], $aggregator->status(1002)['pendingPaths']);
        $t->same(['parent' => FolderWatchEventAggregator::EVENT_MIXED], $aggregator->status(1002)['pendingTypes']);

        $rootOverflow = new FolderWatchEventAggregator(10, 30, maxFiles: 3, maxFilesPerDir: 8);
        $rootOverflow->recordEvent('a.jpg', now: 2000);
        $rootOverflow->recordEvent('b.jpg', now: 2000);
        $rootOverflow->recordEvent('c.jpg', now: 2000);
        $rootOverflow->recordEvent('d.jpg', now: 2000);

        $t->same(['.'], $rootOverflow->status(2000)['pendingPaths']);
        $t->same(['.' => FolderWatchEventAggregator::EVENT_NON_REMOVE], $rootOverflow->status(2000)['pendingTypes']);

        $rootOverflow->recordEvent('later.jpg', FolderWatchEventAggregator::EVENT_REMOVE, 2001);
        $t->same(['.' => FolderWatchEventAggregator::EVENT_NON_REMOVE], $rootOverflow->status(2001)['pendingTypes']);
    },
    'watch aggregator delays non-removes, ignores in-progress items, and orders remove batches last' => static function (TestRunner $t): void {
        $aggregator = new FolderWatchEventAggregator(10, 30);

        $aggregator->markItemStarted('wp-content/uploads/2026/05/hero.jpg');
        $aggregator->recordEvent('wp-content/uploads/2026/05/hero.jpg', now: 1000);
        $t->same(0, $aggregator->status(1000)['pendingEventCount']);
        $t->same(['wp-content/uploads/2026/05/hero.jpg'], $aggregator->status(1000)['inProgressPaths']);

        $aggregator->markItemFinished('wp-content/uploads/2026/05/hero.jpg');
        $aggregator->recordEvent('\\wp-content\\uploads\\2026\\05\\hero.jpg', now: 1000);
        $t->same(1010, $aggregator->status(1000)['nextScanAt']);
        $t->same(false, $aggregator->status(1009)['due']);
        $t->same([], $aggregator->dueBatches(1009));

        $due = $aggregator->dueBatches(1010);
        $t->same([
            [
                'eventType' => FolderWatchEventAggregator::EVENT_NON_REMOVE,
                'paths' => ['wp-content/uploads/2026/05/hero.jpg'],
                'count' => 1,
            ],
        ], $due);
        $t->same(0, $aggregator->status(1010)['pendingEventCount']);

        $mixed = new FolderWatchEventAggregator(10, 30);
        $mixed->recordEvent('wp-content/uploads/2026/05/caption.txt', now: 2000);
        $mixed->recordEvent('wp-content/uploads/2026/05/caption.txt', FolderWatchEventAggregator::EVENT_REMOVE, 2001);
        $mixed->recordEvent('wp-content/uploads/2026/05/old.jpg', FolderWatchEventAggregator::EVENT_REMOVE, 2001);

        $t->same(true, $mixed->status(2010)['due']);
        $t->same([
            [
                'eventType' => FolderWatchEventAggregator::EVENT_MIXED,
                'paths' => ['wp-content/uploads/2026/05/caption.txt'],
                'count' => 1,
            ],
            [
                'eventType' => FolderWatchEventAggregator::EVENT_REMOVE,
                'paths' => ['wp-content/uploads/2026/05/old.jpg'],
                'count' => 1,
            ],
        ], $mixed->dueBatches(2010));
    },
];
