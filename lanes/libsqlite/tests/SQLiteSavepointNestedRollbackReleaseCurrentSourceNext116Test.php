<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import');
    $stack->recordPageImageWrite(1, $page('before-root-catalog'));
    $stack->recordWalFrameWrite(1, 1);
    $stack->savepoint('plugin-batch');
    $stack->recordPageImageWrite(2, $page('before-plugin-option'));
    $stack->recordWalFrameWrite(2, 2);
    $stack->savepoint('autoload-index');
    $stack->recordPageImageWrite(3, $page('before-autoload-index'));
    $stack->recordWalFrameWrite(3, 3, true);
    $stack->savepoint('transient-cache');
    $stack->recordPageImageWrite(4, $page('before-transient-cache'));
    $stack->recordWalFrameWrite(4, 4);

    return $stack;
};

$currentDatabase = $page('current-root-catalog')
    . $page('current-plugin-option')
    . $page('current-autoload-index')
    . $page('current-transient-cache');
$currentPages = [
    2 => $page('current-plugin-option'),
    3 => $page('current-autoload-index'),
    4 => $page('current-transient-cache'),
];

$plan = static fn (): array => $makeStack()->rollbackToCurrentSourceThenRelease('autoload-index', $currentDatabase, $currentPages, $pageSize);

$cases = [
    'savepoint name' => static fn (): mixed => $plan()['savepoint'],
    'found index' => static fn (): mixed => $plan()['found_index'],
    'page size' => static fn (): mixed => $plan()['page_size'],
    'current source verified' => static fn (): mixed => $plan()['current_source_verified'],
    'current source pages sorted' => static fn (): mixed => $plan()['current_source_page_numbers'],
    'current source prefix page two' => static fn (): mixed => rtrim($plan()['current_source_prefixes'][2], '.'),
    'current source prefix page three' => static fn (): mixed => rtrim($plan()['current_source_prefixes'][3], '.'),
    'current source prefix page four' => static fn (): mixed => rtrim($plan()['current_source_prefixes'][4], '.'),
    'rollback to frame' => static fn (): mixed => $plan()['rollback_to_frame'],
    'discarded wal frame indexes' => static fn (): mixed => array_column($plan()['discarded_wal_frames'], 'frame_index'),
    'discarded wal pages' => static fn (): mixed => array_column($plan()['discarded_wal_frames'], 'page_number'),
    'discarded wal commit flags' => static fn (): mixed => array_column($plan()['discarded_wal_frames'], 'commit_frame'),
    'discarded wal frame names' => static fn (): mixed => array_column($plan()['discarded_wal_frames'], 'frame_name'),
    'discarded page numbers' => static fn (): mixed => $plan()['discarded_page_numbers'],
    'restored page numbers' => static fn (): mixed => $plan()['restored_page_numbers'],
    'restore source frames' => static fn (): mixed => array_column($plan()['restore_pages'], 'source_frame'),
    'restore offsets' => static fn (): mixed => array_column($plan()['restore_pages'], 'database_offset'),
    'restore bytes' => static fn (): mixed => array_column($plan()['restore_pages'], 'bytes'),
    'missing pages empty' => static fn (): mixed => $plan()['missing_page_numbers'],
    'names before' => static fn (): mixed => $plan()['names_before'],
    'names after rollback keeps target' => static fn (): mixed => $plan()['names_after_rollback'],
    'names after release closes target' => static fn (): mixed => $plan()['names_after_release'],
    'released frame names' => static fn (): mixed => $plan()['released_frame_names'],
    'released merged pages cleared by rollback' => static fn (): mixed => $plan()['released_merged_page_numbers'],
    'pending pages after release' => static fn (): mixed => $plan()['pending_page_numbers_after_release'],
    'pending wal after release' => static fn (): mixed => $plan()['pending_wal_frame_indexes_after_release'],
    'transaction active after release' => static fn (): mixed => $plan()['transaction_active_after'],
    'dependency tag next116' => static fn (): mixed => in_array('sqlite-savepoint-nested-rollback-release-current-source-next116', $plan()['dependencies'], true),
    'dependency tag rollback current' => static fn (): mixed => in_array('sqlite-savepoint-rollback-to-current-keeps-savepoint', $plan()['dependencies'], true),
    'dependency tag release after rollback' => static fn (): mixed => in_array('sqlite-savepoint-release-after-rollback', $plan()['dependencies'], true),
    'dependency tag count' => static fn (): mixed => count($plan()['dependencies']),
    'rolled back root remains current' => static fn (): mixed => rtrim(substr($plan()['rolled_back_database_bytes'], 0, $pageSize), '.'),
    'rolled back parent page remains current' => static fn (): mixed => rtrim(substr($plan()['rolled_back_database_bytes'], $pageSize, $pageSize), '.'),
    'rolled back target page restored' => static fn (): mixed => rtrim(substr($plan()['rolled_back_database_bytes'], $pageSize * 2, $pageSize), '.'),
    'rolled back child page restored' => static fn (): mixed => rtrim(substr($plan()['rolled_back_database_bytes'], $pageSize * 3, $pageSize), '.'),
    'stack after operation can commit parent pages' => static function () use ($makeStack, $currentDatabase, $currentPages, $pageSize): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentSourceThenRelease('autoload-index', $currentDatabase, $currentPages, $pageSize);
        return $stack->commitPlan()['committed_page_numbers'];
    },
    'stack after operation commit names' => static function () use ($makeStack, $currentDatabase, $currentPages, $pageSize): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentSourceThenRelease('autoload-index', $currentDatabase, $currentPages, $pageSize);
        return $stack->commitPlan()['committed_frame_names'];
    },
    'stack after operation can open retry savepoint' => static function () use ($makeStack, $currentDatabase, $currentPages, $pageSize, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentSourceThenRelease('autoload-index', $currentDatabase, $currentPages, $pageSize);
        $stack->savepoint('autoload-retry');
        $stack->recordPageImageWrite(3, $page('before-autoload-retry'));
        $stack->recordWalFrameWrite(3, 3);
        return $stack->names();
    },
    'retry savepoint reuses truncated wal frame' => static function () use ($makeStack, $currentDatabase, $currentPages, $pageSize, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentSourceThenRelease('autoload-index', $currentDatabase, $currentPages, $pageSize);
        $stack->savepoint('autoload-retry');
        $stack->recordPageImageWrite(3, $page('before-autoload-retry'));
        $stack->recordWalFrameWrite(3, 3);
        return $stack->pendingWalFrameIndexes();
    },
    'retry rollback restores retry image' => static function () use ($makeStack, $currentDatabase, $currentPages, $pageSize, $page): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentSourceThenRelease('autoload-index', $currentDatabase, $currentPages, $pageSize);
        $stack->savepoint('autoload-retry');
        $stack->recordPageImageWrite(3, $page('before-autoload-retry'));
        $stack->recordWalFrameWrite(3, 3);
        return rtrim(substr($stack->rollbackToDatabaseImage('autoload-retry', $currentDatabase, $pageSize), $pageSize * 2, $pageSize), '.');
    },
    'outer rollback after operation restores root' => static function () use ($makeStack, $currentDatabase, $currentPages, $pageSize): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentSourceThenRelease('autoload-index', $currentDatabase, $currentPages, $pageSize);
        return rtrim(substr($stack->rollbackDatabaseImage($currentDatabase, $pageSize), 0, $pageSize), '.');
    },
    'outer rollback after operation restores plugin' => static function () use ($makeStack, $currentDatabase, $currentPages, $pageSize): mixed {
        $stack = $makeStack();
        $stack->rollbackToCurrentSourceThenRelease('autoload-index', $currentDatabase, $currentPages, $pageSize);
        return rtrim(substr($stack->rollbackDatabaseImage($currentDatabase, $pageSize), $pageSize, $pageSize), '.');
    },
    'stale current source rejected' => static function () use ($makeStack, $currentDatabase, $currentPages, $pageSize, $page): mixed {
        $pages = $currentPages;
        $pages[3] = $page('stale-autoload-index');
        try {
            $makeStack()->rollbackToCurrentSourceThenRelease('autoload-index', $currentDatabase, $pages, $pageSize);
        } catch (RuntimeException) {
            return 'rejected';
        }

        return 'accepted';
    },
    'empty current source rejected' => static function () use ($makeStack, $currentDatabase, $pageSize): mixed {
        try {
            $makeStack()->rollbackToCurrentSourceThenRelease('autoload-index', $currentDatabase, [], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }

        return 'accepted';
    },
    'unaligned database rejected' => static function () use ($makeStack, $currentPages, $pageSize): mixed {
        try {
            $makeStack()->rollbackToCurrentSourceThenRelease('autoload-index', 'short', $currentPages, $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }

        return 'accepted';
    },
    'outside source page rejected' => static function () use ($makeStack, $currentDatabase, $pageSize, $page): mixed {
        try {
            $makeStack()->rollbackToCurrentSourceThenRelease('autoload-index', $currentDatabase, [5 => $page('missing')], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }

        return 'accepted';
    },
    'bad page size rejected' => static function () use ($makeStack, $currentDatabase, $currentPages): mixed {
        try {
            $makeStack()->rollbackToCurrentSourceThenRelease('autoload-index', $currentDatabase, $currentPages, 0);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }

        return 'accepted';
    },
    'bad page number rejected' => static function () use ($makeStack, $currentDatabase, $pageSize, $page): mixed {
        try {
            $makeStack()->rollbackToCurrentSourceThenRelease('autoload-index', $currentDatabase, [0 => $page('bad')], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }

        return 'accepted';
    },
    'bad source image size rejected' => static function () use ($makeStack, $currentDatabase, $pageSize): mixed {
        try {
            $makeStack()->rollbackToCurrentSourceThenRelease('autoload-index', $currentDatabase, [3 => 'bad'], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }

        return 'accepted';
    },
    'missing savepoint rejected' => static function () use ($makeStack, $currentDatabase, $currentPages, $pageSize): mixed {
        try {
            $makeStack()->rollbackToCurrentSourceThenRelease('missing-savepoint', $currentDatabase, $currentPages, $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }

        return 'accepted';
    },
];

$expected = [
    'savepoint name' => 'autoload-index',
    'found index' => 2,
    'page size' => 512,
    'current source verified' => true,
    'current source pages sorted' => [2, 3, 4],
    'current source prefix page two' => 'current-plugin-option',
    'current source prefix page three' => 'current-autoload-index',
    'current source prefix page four' => 'current-transient-cache',
    'rollback to frame' => 2,
    'discarded wal frame indexes' => [3, 4],
    'discarded wal pages' => [3, 4],
    'discarded wal commit flags' => [true, false],
    'discarded wal frame names' => ['autoload-index', 'transient-cache'],
    'discarded page numbers' => [3, 4],
    'restored page numbers' => [3, 4],
    'restore source frames' => ['autoload-index', 'transient-cache'],
    'restore offsets' => [1024, 1536],
    'restore bytes' => [512, 512],
    'missing pages empty' => [],
    'names before' => ['wp-import', 'plugin-batch', 'autoload-index', 'transient-cache'],
    'names after rollback keeps target' => ['wp-import', 'plugin-batch', 'autoload-index'],
    'names after release closes target' => ['wp-import', 'plugin-batch'],
    'released frame names' => ['autoload-index'],
    'released merged pages cleared by rollback' => [],
    'pending pages after release' => [1, 2],
    'pending wal after release' => [1, 2],
    'transaction active after release' => true,
    'dependency tag next116' => true,
    'dependency tag rollback current' => true,
    'dependency tag release after rollback' => true,
    'dependency tag count' => 3,
    'rolled back root remains current' => 'current-root-catalog',
    'rolled back parent page remains current' => 'current-plugin-option',
    'rolled back target page restored' => 'before-autoload-index',
    'rolled back child page restored' => 'before-transient-cache',
    'stack after operation can commit parent pages' => [1, 2],
    'stack after operation commit names' => ['wp-import', 'plugin-batch'],
    'stack after operation can open retry savepoint' => ['wp-import', 'plugin-batch', 'autoload-retry'],
    'retry savepoint reuses truncated wal frame' => [1, 2, 3],
    'retry rollback restores retry image' => 'before-autoload-retry',
    'outer rollback after operation restores root' => 'before-root-catalog',
    'outer rollback after operation restores plugin' => 'before-plugin-option',
    'stale current source rejected' => 'rejected',
    'empty current source rejected' => 'rejected',
    'unaligned database rejected' => 'rejected',
    'outside source page rejected' => 'rejected',
    'bad page size rejected' => 'rejected',
    'bad page number rejected' => 'rejected',
    'bad source image size rejected' => 'rejected',
    'missing savepoint rejected' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['savepoint nested rollback release current source next116 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
