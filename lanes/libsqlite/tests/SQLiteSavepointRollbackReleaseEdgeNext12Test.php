<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('Wp_Import');
    $stack->recordPageImageWrite(1, $page('before-wp-options-root'));
    $stack->recordWalFrameWrite(1, 1);
    $stack->savepoint('Plugin_Settings');
    $stack->recordPageImageWrite(2, $page('before-plugin-settings'));
    $stack->recordWalFrameWrite(2, 2);
    $stack->savepoint('plugin_settings');
    $stack->recordPageImageWrite(3, $page('before-latest-plugin-settings'));
    $stack->recordWalFrameWrite(3, 3, true);
    $stack->savepoint('Option_Row');
    $stack->recordPageImageWrite(4, $page('before-option-row'));
    $stack->recordWalFrameWrite(4, 4);

    return $stack;
};

$cases = [
    'rollback plan finds latest duplicate name case insensitively' => static fn (): mixed => $makeStack()->rollbackToPlan('PLUGIN_SETTINGS')['found_index'],
    'rollback plan keeps latest duplicate frame depth' => static fn (): mixed => $makeStack()->rollbackToPlan('PLUGIN_SETTINGS')['retained_depth'],
    'rollback plan discards descendants after latest duplicate' => static fn (): mixed => $makeStack()->rollbackToPlan('PLUGIN_SETTINGS')['discarded_frame_names'],
    'rollback plan reports latest duplicate page set' => static fn (): mixed => $makeStack()->rollbackToPlan('PLUGIN_SETTINGS')['rollback_page_numbers'],
    'rollback page lookup uses case insensitive latest duplicate' => static fn (): mixed => $makeStack()->rollbackToPageNumbers('plugin_SETTINGS'),
    'rollback image lookup uses latest duplicate image' => static function () use ($makeStack): mixed {
        return array_keys($makeStack()->rollbackToPageImages('PLUGIN_SETTINGS'));
    },
    'rollback image plan reports latest duplicate source frame' => static fn (): mixed => $makeStack()->rollbackToImagePlan('PLUGIN_SETTINGS', 512)['restore_pages'][0]['source_frame'],
    'rollback database image restores latest duplicate page' => static function () use ($makeStack, $page): mixed {
        $rolledBack = $makeStack()->rollbackToDatabaseImage('PLUGIN_SETTINGS', $page('dirty-1') . $page('dirty-2') . $page('dirty-3') . $page('dirty-4'), 512);
        return substr($rolledBack, 1024, 29);
    },
    'wal rollback plan finds latest duplicate start frame' => static fn (): mixed => $makeStack()->walRollbackToPlan('PLUGIN_SETTINGS')['rollback_to_frame'],
    'wal rollback plan discards latest duplicate wal frames' => static fn (): mixed => array_column($makeStack()->walRollbackToPlan('PLUGIN_SETTINGS')['discarded_wal_frames'], 'frame_index'),
    'wal rollback plan reports latest duplicate discarded pages' => static fn (): mixed => $makeStack()->walRollbackToPlan('PLUGIN_SETTINGS')['discarded_page_numbers'],
    'rollback with plan clears latest duplicate frame only' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToWithPlan('PLUGIN_SETTINGS');
        return $stack->names();
    },
    'rollback with plan leaves earlier duplicate dirty page active' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackToWithPlan('PLUGIN_SETTINGS');
        return $stack->pendingPageNumbers();
    },
    'rollback with plan allows wal frame reuse after truncation' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->walRollbackToWithPlan('PLUGIN_SETTINGS');
        $stack->recordWalFrameWrite(3, 5);
        return $stack->pendingWalFrameIndexes();
    },
    'release plan finds latest duplicate name case insensitively' => static fn (): mixed => $makeStack()->releasePlan('PLUGIN_SETTINGS')['found_index'],
    'release plan releases latest duplicate and descendants only' => static fn (): mixed => $makeStack()->releasePlan('PLUGIN_SETTINGS')['released_frame_names'],
    'release plan merges latest duplicate pages only' => static fn (): mixed => $makeStack()->releasePlan('PLUGIN_SETTINGS')['merged_page_numbers'],
    'release plan leaves parent transaction active' => static fn (): mixed => $makeStack()->releasePlan('PLUGIN_SETTINGS')['transaction_active_after'],
    'release with plan removes latest duplicate frame' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->releaseWithPlan('PLUGIN_SETTINGS');
        return $stack->names();
    },
    'release with plan merges wal frames into earlier duplicate' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->releaseWithPlan('PLUGIN_SETTINGS');
        return $stack->walFrameState()[1]['wal_frame_indexes'];
    },
    'release with plan keeps rollback to earlier duplicate possible' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->releaseWithPlan('PLUGIN_SETTINGS');
        return $stack->rollbackToPageNumbers('plugin_settings');
    },
    'release leaf case insensitive lookup removes leaf' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('option_row');
        return $stack->names();
    },
    'outer release case insensitive lookup clears transaction' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('wp_import');
        return [$stack->transactionActive(), $stack->depth(), $stack->pendingWalFrameIndexes()];
    },
    'commit after duplicate release reports merged pages' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('PLUGIN_SETTINGS');
        return $stack->commitPlan()['committed_page_numbers'];
    },
    'full rollback after duplicate release reports one savepoint remaining' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('PLUGIN_SETTINGS');
        return $stack->rollbackPlan()['released_savepoint_count'];
    },
    'case insensitive missing name still rejects unknown savepoint' => static function () use ($makeStack): mixed {
        try {
            $makeStack()->rollbackTo('Plugin-Settings');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }

        return 'accepted';
    },
    'case insensitive release after rollback targets earlier duplicate' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->rollbackTo('PLUGIN_SETTINGS');
        $stack->release('plugin_settings');
        $stack->release('plugin_settings');
        return $stack->names();
    },
    'case insensitive rollback after leaf release targets latest duplicate' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('option_row');
        return $stack->rollbackToPlan('PLUGIN_SETTINGS')['found_index'];
    },
    'case insensitive wal rollback after leaf release discards merged frames' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('option_row');
        return array_column($stack->walRollbackToPlan('plugin_SETTINGS')['discarded_wal_frames'], 'frame_index');
    },
    'case insensitive release plan for outer transaction reports inactive' => static fn (): mixed => $makeStack()->releasePlan('wp_import')['transaction_active_after'],
];

$expected = [
    'rollback plan finds latest duplicate name case insensitively' => 2,
    'rollback plan keeps latest duplicate frame depth' => 3,
    'rollback plan discards descendants after latest duplicate' => ['Option_Row'],
    'rollback plan reports latest duplicate page set' => [3, 4],
    'rollback page lookup uses case insensitive latest duplicate' => [3, 4],
    'rollback image lookup uses latest duplicate image' => [3, 4],
    'rollback image plan reports latest duplicate source frame' => 'plugin_settings',
    'rollback database image restores latest duplicate page' => 'before-latest-plugin-settings',
    'wal rollback plan finds latest duplicate start frame' => 2,
    'wal rollback plan discards latest duplicate wal frames' => [3, 4],
    'wal rollback plan reports latest duplicate discarded pages' => [3, 4],
    'rollback with plan clears latest duplicate frame only' => ['Wp_Import', 'Plugin_Settings', 'plugin_settings'],
    'rollback with plan leaves earlier duplicate dirty page active' => [1, 2],
    'rollback with plan allows wal frame reuse after truncation' => [1, 2, 3],
    'release plan finds latest duplicate name case insensitively' => 2,
    'release plan releases latest duplicate and descendants only' => ['plugin_settings', 'Option_Row'],
    'release plan merges latest duplicate pages only' => [3, 4],
    'release plan leaves parent transaction active' => true,
    'release with plan removes latest duplicate frame' => ['Wp_Import', 'Plugin_Settings'],
    'release with plan merges wal frames into earlier duplicate' => [2, 3, 4],
    'release with plan keeps rollback to earlier duplicate possible' => [2, 3, 4],
    'release leaf case insensitive lookup removes leaf' => ['Wp_Import', 'Plugin_Settings', 'plugin_settings'],
    'outer release case insensitive lookup clears transaction' => [false, 0, []],
    'commit after duplicate release reports merged pages' => [1, 2, 3, 4],
    'full rollback after duplicate release reports one savepoint remaining' => 1,
    'case insensitive missing name still rejects unknown savepoint' => 'rejected',
    'case insensitive release after rollback targets earlier duplicate' => ['Wp_Import'],
    'case insensitive rollback after leaf release targets latest duplicate' => 2,
    'case insensitive wal rollback after leaf release discards merged frames' => [3, 4],
    'case insensitive release plan for outer transaction reports inactive' => false,
];

foreach ($cases as $name => $callback) {
    $tests['savepoint rollback release edge next12 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
