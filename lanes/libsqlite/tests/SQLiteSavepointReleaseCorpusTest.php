<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('import');
    $stack->recordPageImageWrite(1, $page('before-root-page'));
    $stack->recordWalFrameWrite(1, 1);
    $stack->savepoint('plugin_settings');
    $stack->recordPageImageWrite(2, $page('before-plugin-row'));
    $stack->recordWalFrameWrite(2, 2);
    $stack->savepoint('autoload_index');
    $stack->recordPageImageWrite(3, $page('before-autoload-index'));
    $stack->recordWalFrameWrite(3, 3, true);
    $stack->savepoint('cache_warmup');
    $stack->recordPageImageWrite(4, $page('before-cache-row'));
    $stack->recordWalFrameWrite(4, 4);

    return $stack;
};

$cases = [
    'release nested reports target savepoint name' => static fn (): mixed => $makeStack()->releasePlan('autoload_index')['savepoint'],
    'release nested reports found index' => static fn (): mixed => $makeStack()->releasePlan('autoload_index')['found_index'],
    'release nested includes target and descendants' => static fn (): mixed => $makeStack()->releasePlan('autoload_index')['released_frame_names'],
    'release nested merges dirty pages in numeric order' => static fn (): mixed => $makeStack()->releasePlan('autoload_index')['merged_page_numbers'],
    'release nested keeps outer transaction active' => static fn (): mixed => $makeStack()->releasePlan('autoload_index')['transaction_active_after'],
    'release nested result depth is parent depth' => static fn (): mixed => $makeStack()->releasePlan('autoload_index')['result_depth'],
    'release nested target is not transaction' => static fn (): mixed => $makeStack()->releasePlan('autoload_index')['target_is_transaction'],
    'release nested removes released frame names' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('autoload_index');
        return $stack->names();
    },
    'release nested merges wal frame indexes into parent' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('autoload_index');
        return $stack->walFrameState()[1]['wal_frame_indexes'];
    },
    'release nested preserves all pending wal indexes' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('autoload_index');
        return $stack->pendingWalFrameIndexes();
    },
    'release nested merges pending page numbers' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('autoload_index');
        return $stack->pendingPageNumbers();
    },
    'release nested keeps first page image for rollback' => static function () use ($makeStack, $page, $pageSize): mixed {
        $stack = $makeStack();
        $stack->recordPageImageWrite(3, $page('dirty-second-image'));
        $stack->release('autoload_index');
        return substr($stack->rollbackToDatabaseImage('plugin_settings', str_repeat($page('dirty'), 4), $pageSize), $pageSize * 2, 21);
    },
    'release leaf only removes leaf frame' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('cache_warmup');
        return $stack->names();
    },
    'release leaf plan merges only leaf page' => static fn (): mixed => $makeStack()->releasePlan('cache_warmup')['merged_page_numbers'],
    'release parent after leaf release commits descendants into transaction' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('cache_warmup');
        $stack->release('plugin_settings');
        return $stack->names();
    },
    'release parent after leaf release preserves wal sequence' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('cache_warmup');
        $stack->release('plugin_settings');
        return $stack->pendingWalFrameIndexes();
    },
    'release outer transaction reports transaction target' => static fn (): mixed => $makeStack()->releasePlan('import')['target_is_transaction'],
    'release outer transaction reports inactive after release' => static fn (): mixed => $makeStack()->releasePlan('import')['transaction_active_after'],
    'release outer transaction includes every frame' => static fn (): mixed => $makeStack()->releasePlan('import')['released_frame_names'],
    'release outer transaction includes every dirty page' => static fn (): mixed => $makeStack()->releasePlan('import')['merged_page_numbers'],
    'release outer transaction clears stack' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('import');
        return [$stack->transactionActive(), $stack->depth(), $stack->names()];
    },
    'release outer transaction resets wal frame indexes' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('import');
        return $stack->pendingWalFrameIndexes();
    },
    'release outer transaction allows a new transaction' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('import');
        $stack->beginTransaction('next_import');
        return $stack->names();
    },
    'release unknown savepoint is rejected' => static function () use ($makeStack): mixed {
        try {
            $makeStack()->release('missing');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'release plan unknown savepoint is rejected' => static function () use ($makeStack): mixed {
        try {
            $makeStack()->releasePlan('missing');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'rollback to released savepoint is rejected' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('autoload_index');
        try {
            $stack->rollbackTo('cache_warmup');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'rollback to parent after release sees released child pages' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('autoload_index');
        return $stack->rollbackToPageNumbers('plugin_settings');
    },
    'rollback to parent after release restores released child images' => static function () use ($makeStack, $page, $pageSize): mixed {
        $stack = $makeStack();
        $stack->release('autoload_index');
        return substr($stack->rollbackToDatabaseImage('plugin_settings', str_repeat($page('dirty'), 4), $pageSize), $pageSize * 3, 16);
    },
    'rollback to parent after release discards merged wal frames' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('autoload_index');
        return $stack->walRollbackToPlan('plugin_settings')['discarded_wal_frames'];
    },
    'commit after release reports merged pages' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('autoload_index');
        return $stack->commitPlan()['committed_page_numbers'];
    },
];

$expected = [
    'release nested reports target savepoint name' => 'autoload_index',
    'release nested reports found index' => 2,
    'release nested includes target and descendants' => ['autoload_index', 'cache_warmup'],
    'release nested merges dirty pages in numeric order' => [3, 4],
    'release nested keeps outer transaction active' => true,
    'release nested result depth is parent depth' => 2,
    'release nested target is not transaction' => false,
    'release nested removes released frame names' => ['import', 'plugin_settings'],
    'release nested merges wal frame indexes into parent' => [2, 3, 4],
    'release nested preserves all pending wal indexes' => [1, 2, 3, 4],
    'release nested merges pending page numbers' => [1, 2, 3, 4],
    'release nested keeps first page image for rollback' => 'before-autoload-index',
    'release leaf only removes leaf frame' => ['import', 'plugin_settings', 'autoload_index'],
    'release leaf plan merges only leaf page' => [4],
    'release parent after leaf release commits descendants into transaction' => ['import'],
    'release parent after leaf release preserves wal sequence' => [1, 2, 3, 4],
    'release outer transaction reports transaction target' => true,
    'release outer transaction reports inactive after release' => false,
    'release outer transaction includes every frame' => ['import', 'plugin_settings', 'autoload_index', 'cache_warmup'],
    'release outer transaction includes every dirty page' => [1, 2, 3, 4],
    'release outer transaction clears stack' => [false, 0, []],
    'release outer transaction resets wal frame indexes' => [],
    'release outer transaction allows a new transaction' => ['next_import'],
    'release unknown savepoint is rejected' => 'rejected',
    'release plan unknown savepoint is rejected' => 'rejected',
    'rollback to released savepoint is rejected' => 'rejected',
    'rollback to parent after release sees released child pages' => [2, 3, 4],
    'rollback to parent after release restores released child images' => 'before-cache-row',
    'rollback to parent after release discards merged wal frames' => [
        ['frame_index' => 2, 'page_number' => 2, 'commit_frame' => false, 'frame_name' => 'plugin_settings'],
        ['frame_index' => 3, 'page_number' => 3, 'commit_frame' => true, 'frame_name' => 'plugin_settings'],
        ['frame_index' => 4, 'page_number' => 4, 'commit_frame' => false, 'frame_name' => 'plugin_settings'],
    ],
    'commit after release reports merged pages' => [1, 2, 3, 4],
];

foreach ($cases as $name => $callback) {
    $tests['savepoint release corpus ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
