<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointRecoveryPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('database-page-1-before') . $page('database-page-2-before') . $page('database-page-3-before');

$makeWal = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x30303030;
    $salt2 = 0x40404040;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 30, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $label] = $frame;
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $makeWal([
    [1, 0, 'committed-schema-before-plugin'],
    [2, 3, 'committed-options-before-plugin'],
    [3, 0, 'plugin-settings-draft'],
    [2, 3, 'plugin-settings-commit'],
    [1, 0, 'nested-after-plugin-draft'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('application_import');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin_current');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 2, true);
    $stack->savepoint('nested_after_plugin');
    $stack->recordWalFrameWrite(5, 1);

    return $stack;
};

$pluginPlan = static fn (array $pages = [1, 2, 3]): array => SQLiteWalSavepointRecoveryPlan::currentNextAfterRollbackTo(
    $makeStack(),
    'plugin_current',
    $wal,
    $walBytes,
    $databaseBytes,
    $pages
);
$nestedPlan = static fn (array $pages = [1, 2, 3]): array => SQLiteWalSavepointRecoveryPlan::currentNextAfterRollbackTo(
    $makeStack(),
    'nested_after_plugin',
    $wal,
    $walBytes,
    $databaseBytes,
    $pages
);

$cases = [
    'plugin status remains valid after rollback prefix' => static fn (): mixed => $pluginPlan()['status'],
    'plugin reason is all frames valid in retained prefix' => static fn (): mixed => $pluginPlan()['reason'],
    'plugin savepoint is reported' => static fn (): mixed => $pluginPlan()['savepoint'],
    'plugin rollback target frame keeps prior commit' => static fn (): mixed => $pluginPlan()['rollback_to_frame'],
    'plugin original frame count includes discarded current and next frames' => static fn (): mixed => $pluginPlan()['original_frame_count'],
    'plugin retained frame count stops before savepoint' => static fn (): mixed => $pluginPlan()['retained_frame_count'],
    'plugin discarded frame count includes nested draft' => static fn (): mixed => $pluginPlan()['discarded_frame_count'],
    'plugin truncation byte offset is retained prefix' => static fn (): mixed => $pluginPlan()['truncate_to_bytes'],
    'plugin current wal bytes length matches truncation' => static fn (): mixed => $pluginPlan()['current_wal_bytes_length'],
    'plugin current wal bytes preserve committed prefix' => static fn (): mixed => str_contains($pluginPlan()['current_wal_bytes'], 'committed-options-before-plugin'),
    'plugin current wal bytes omit savepoint draft' => static fn (): mixed => str_contains($pluginPlan()['current_wal_bytes'], 'plugin-settings-draft'),
    'plugin current wal bytes omit savepoint commit' => static fn (): mixed => str_contains($pluginPlan()['current_wal_bytes'], 'plugin-settings-commit'),
    'plugin current wal bytes omit nested draft' => static fn (): mixed => str_contains($pluginPlan()['current_wal_bytes'], 'nested-after-plugin-draft'),
    'plugin current reader end frame is retained frame' => static fn (): mixed => $pluginPlan()['current_reader_end_frame'],
    'plugin next reader end frame is committed frame' => static fn (): mixed => $pluginPlan()['next_reader_end_frame'],
    'plugin committed end offset equals recovery offset' => static fn (): mixed => $pluginPlan()['committed_end_offset'] === $pluginPlan()['recovery_end_offset'],
    'plugin current reader sources include prior wal frames' => static fn (): mixed => $pluginPlan()['current_reader_sources'],
    'plugin next reader sources match current sources' => static fn (): mixed => $pluginPlan()['next_reader_sources'],
    'plugin current reader frame indexes are stable' => static fn (): mixed => $pluginPlan()['current_reader_frame_indexes'],
    'plugin next reader frame indexes are stable' => static fn (): mixed => $pluginPlan()['next_reader_frame_indexes'],
    'plugin current reader has no recovery errors' => static fn (): mixed => $pluginPlan()['current_reader_errors'],
    'plugin next reader has no recovery errors' => static fn (): mixed => $pluginPlan()['next_reader_errors'],
    'plugin current next images match after recovery' => static fn (): mixed => $pluginPlan()['images_match'],
    'plugin next open can checkpoint retained transaction' => static fn (): mixed => $pluginPlan()['can_checkpoint'],
    'plugin next recovery has checkpoint database image' => static fn (): mixed => $pluginPlan()['next_uses_checkpoint_database'],
    'plugin checkpoint database page count is preserved' => static fn (): mixed => $pluginPlan()['checkpoint_database_page_count'],
    'plugin recovery discards no valid tail after rollback' => static fn (): mixed => $pluginPlan()['discarded_valid_tail_frame_count'],
    'plugin recovery discards no corrupt tail after rollback' => static fn (): mixed => $pluginPlan()['discarded_corrupt_tail_frame_count'],
    'plugin dependencies include recovery marker' => static fn (): mixed => in_array('sqlite-wal-savepoint-recovery-current-next', $pluginPlan()['dependencies'], true),
    'plugin dependencies include savepoint prefix marker' => static fn (): mixed => in_array('sqlite-savepoint-wal-current-prefix', $pluginPlan()['dependencies'], true),
    'plugin dependencies include transaction recovery marker' => static fn (): mixed => in_array('sqlite-wal-transaction-recovery-boundary', $pluginPlan()['dependencies'], true),
    'plugin page three falls back to database before savepoint' => static fn (): mixed => $pluginPlan()['current_reader_sources'][2],
    'plugin page three next also falls back to database' => static fn (): mixed => $pluginPlan()['next_reader_sources'][2],
    'plugin page three image is pre-savepoint database page' => static fn (): mixed => str_contains($pluginPlan()['current_reader'][2]['image'], 'database-page-3-before'),
    'plugin page two image is committed pre-savepoint wal frame' => static fn (): mixed => str_contains($pluginPlan()['current_reader'][1]['image'], 'committed-options-before-plugin'),
    'nested status remains valid after dropping only nested draft' => static fn (): mixed => $nestedPlan()['status'],
    'nested rollback target keeps plugin commit frame' => static fn (): mixed => $nestedPlan()['rollback_to_frame'],
    'nested retained frame count includes plugin commit' => static fn (): mixed => $nestedPlan()['retained_frame_count'],
    'nested discarded frame count drops only nested draft' => static fn (): mixed => $nestedPlan()['discarded_frame_count'],
    'nested truncation byte offset includes four frames' => static fn (): mixed => $nestedPlan()['truncate_to_bytes'],
    'nested current wal bytes preserve plugin commit' => static fn (): mixed => str_contains($nestedPlan()['current_wal_bytes'], 'plugin-settings-commit'),
    'nested current wal bytes omit nested draft' => static fn (): mixed => str_contains($nestedPlan()['current_wal_bytes'], 'nested-after-plugin-draft'),
    'nested reader page two sees plugin commit frame' => static fn (): mixed => $nestedPlan()['current_reader_frame_indexes'][1],
    'nested next reader page two sees plugin commit frame' => static fn (): mixed => $nestedPlan()['next_reader_frame_indexes'][1],
    'nested current next images match' => static fn (): mixed => $nestedPlan()['images_match'],
    'nested checkpoint page count is preserved' => static fn (): mixed => $nestedPlan()['checkpoint_database_page_count'],
    'single page source reports retained wal frame' => static fn (): mixed => $pluginPlan([2])['current_reader_sources'],
    'single page next source reports retained wal frame' => static fn (): mixed => $pluginPlan([2])['next_reader_sources'],
    'single page frame index reports retained commit frame' => static fn (): mixed => $pluginPlan([2])['current_reader_frame_indexes'],
    'empty page list is rejected' => static function () use ($makeStack, $wal, $walBytes, $databaseBytes): mixed {
        try {
            SQLiteWalSavepointRecoveryPlan::currentNextAfterRollbackTo($makeStack(), 'plugin_current', $wal, $walBytes, $databaseBytes, []);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'missing savepoint is rejected' => static function () use ($makeStack, $wal, $walBytes, $databaseBytes): mixed {
        try {
            SQLiteWalSavepointRecoveryPlan::currentNextAfterRollbackTo($makeStack(), 'missing', $wal, $walBytes, $databaseBytes, [1]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'mismatched wal bytes are rejected' => static function () use ($makeStack, $wal, $walBytes, $databaseBytes): mixed {
        try {
            SQLiteWalSavepointRecoveryPlan::currentNextAfterRollbackTo($makeStack(), 'plugin_current', $wal, substr($walBytes, 0, -1) . 'x', $databaseBytes, [1]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'non integer page is rejected' => static function () use ($makeStack, $wal, $walBytes, $databaseBytes): mixed {
        try {
            SQLiteWalSavepointRecoveryPlan::currentNextAfterRollbackTo($makeStack(), 'plugin_current', $wal, $walBytes, $databaseBytes, [1, '2']);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'plugin status remains valid after rollback prefix' => 'valid',
    'plugin reason is all frames valid in retained prefix' => 'all_frames_valid',
    'plugin savepoint is reported' => 'plugin_current',
    'plugin rollback target frame keeps prior commit' => 2,
    'plugin original frame count includes discarded current and next frames' => 5,
    'plugin retained frame count stops before savepoint' => 2,
    'plugin discarded frame count includes nested draft' => 3,
    'plugin truncation byte offset is retained prefix' => 1104,
    'plugin current wal bytes length matches truncation' => 1104,
    'plugin current wal bytes preserve committed prefix' => true,
    'plugin current wal bytes omit savepoint draft' => false,
    'plugin current wal bytes omit savepoint commit' => false,
    'plugin current wal bytes omit nested draft' => false,
    'plugin current reader end frame is retained frame' => 2,
    'plugin next reader end frame is committed frame' => 2,
    'plugin committed end offset equals recovery offset' => true,
    'plugin current reader sources include prior wal frames' => ['wal', 'wal', 'database'],
    'plugin next reader sources match current sources' => ['wal', 'wal', 'database'],
    'plugin current reader frame indexes are stable' => [1, 2, null],
    'plugin next reader frame indexes are stable' => [1, 2, null],
    'plugin current reader has no recovery errors' => [],
    'plugin next reader has no recovery errors' => [],
    'plugin current next images match after recovery' => true,
    'plugin next open can checkpoint retained transaction' => true,
    'plugin next recovery has checkpoint database image' => true,
    'plugin checkpoint database page count is preserved' => 3,
    'plugin recovery discards no valid tail after rollback' => 0,
    'plugin recovery discards no corrupt tail after rollback' => 0,
    'plugin dependencies include recovery marker' => true,
    'plugin dependencies include savepoint prefix marker' => true,
    'plugin dependencies include transaction recovery marker' => true,
    'plugin page three falls back to database before savepoint' => 'database',
    'plugin page three next also falls back to database' => 'database',
    'plugin page three image is pre-savepoint database page' => true,
    'plugin page two image is committed pre-savepoint wal frame' => true,
    'nested status remains valid after dropping only nested draft' => 'valid',
    'nested rollback target keeps plugin commit frame' => 4,
    'nested retained frame count includes plugin commit' => 4,
    'nested discarded frame count drops only nested draft' => 1,
    'nested truncation byte offset includes four frames' => 2176,
    'nested current wal bytes preserve plugin commit' => true,
    'nested current wal bytes omit nested draft' => false,
    'nested reader page two sees plugin commit frame' => 4,
    'nested next reader page two sees plugin commit frame' => 4,
    'nested current next images match' => true,
    'nested checkpoint page count is preserved' => 3,
    'single page source reports retained wal frame' => ['wal'],
    'single page next source reports retained wal frame' => ['wal'],
    'single page frame index reports retained commit frame' => [2],
    'empty page list is rejected' => 'rejected',
    'missing savepoint is rejected' => 'rejected',
    'mismatched wal bytes are rejected' => 'rejected',
    'non integer page is rejected' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['wal savepoint recovery current next30 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
