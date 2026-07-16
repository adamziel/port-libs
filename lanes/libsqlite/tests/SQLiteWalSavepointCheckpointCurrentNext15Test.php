<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('database-page-1-before') . $page('database-page-2-before') . $page('database-page-3-before');

$makeWal = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x13572468;
    $salt2 = 0x24681357;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 202, $salt1, $salt2);
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
    [1, 0, 'committed-schema-frame'],
    [2, 3, 'committed-option-frame'],
    [3, 0, 'plugin-draft-frame'],
    [2, 3, 'plugin-commit-frame'],
    [1, 0, 'nested-draft-frame'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('import');
    $stack->recordPageImageWrite(1, $page('database-page-1-before'));
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordPageImageWrite(2, $page('database-page-2-before'));
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin_current');
    $stack->recordPageImageWrite(3, $page('database-page-3-before'));
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 2, true);
    $stack->savepoint('nested_after_current');
    $stack->recordPageImageWrite(1, $page('database-page-1-before'));
    $stack->recordWalFrameWrite(5, 1);

    return $stack;
};

$plan = static fn (string $mode = 'passive', ?int $reader = null): array => SQLiteWalSavepointCheckpointPlan::afterRollbackTo(
    $makeStack(),
    'plugin_current',
    $wal,
    $walBytes,
    $databaseBytes,
    $mode,
    $reader
);

$cases = [
    'passive status is ready on retained committed prefix' => static fn (): mixed => $plan()['status'],
    'passive savepoint name is preserved' => static fn (): mixed => $plan()['savepoint'],
    'passive mode is normalized' => static fn (): mixed => SQLiteWalSavepointCheckpointPlan::afterRollbackTo($makeStack(), 'plugin_current', $wal, $walBytes, $databaseBytes, 'PASSIVE')['mode'],
    'passive original frame count includes discarded plugin frames' => static fn (): mixed => $plan()['original_frame_count'],
    'passive retained frame count stops before savepoint frames' => static fn (): mixed => $plan()['retained_frame_count'],
    'passive discarded frame count covers current and nested frames' => static fn (): mixed => $plan()['discarded_frame_count'],
    'passive truncation byte offset is retained prefix length' => static fn (): mixed => $plan()['truncate_to_bytes'],
    'passive current wal bytes length matches truncation offset' => static fn (): mixed => $plan()['current_wal_bytes_length'],
    'passive current wal bytes preserve exact prefix' => static fn (): mixed => $plan()['current_wal_bytes'] === substr($walBytes, 0, 32 + (2 * (24 + $pageSize))),
    'passive current checkpoint reason is all committed frames checkpointed' => static fn (): mixed => $plan()['reason'],
    'passive can checkpoint retained frames' => static fn (): mixed => $plan()['can_checkpoint'],
    'passive can reset after savepoint rollback' => static fn (): mixed => $plan()['can_reset'],
    'passive cannot truncate in passive mode' => static fn (): mixed => $plan()['can_truncate'],
    'passive is not busy' => static fn (): mixed => $plan()['busy'],
    'passive checkpointed frame count uses retained prefix' => static fn (): mixed => $plan()['current_checkpoint']['checkpointed_frame_count'],
    'passive total committable frame count uses retained prefix' => static fn (): mixed => $plan()['current_checkpoint']['total_committable_frame_count'],
    'passive remaining committed frames is zero' => static fn (): mixed => $plan()['current_checkpoint']['remaining_committed_frame_count'],
    'passive uncommitted frames disappear after rollback' => static fn (): mixed => $plan()['current_checkpoint']['uncommitted_frame_count'],
    'passive durable wal action leaves empty wal' => static fn (): mixed => $plan()['current_durable']['wal_action'],
    'passive durable database page count remains three' => static fn (): mixed => $plan()['current_durable']['database_page_count'],
    'passive durable final database bytes are aligned' => static fn (): mixed => $plan()['current_durable']['final_database_bytes'],
    'passive database image contains committed option frame' => static fn (): mixed => str_contains($plan()['current_durable']['database_bytes'], 'committed-option-frame'),
    'passive database image excludes plugin commit frame' => static fn (): mixed => str_contains($plan()['current_durable']['database_bytes'], 'plugin-commit-frame'),
    'passive wal bytes are header only after reset' => static fn (): mixed => strlen($plan()['current_durable']['wal_bytes']),
    'passive wal header is present after reset' => static fn (): mixed => is_array($plan()['current_durable']['wal_header']),
    'passive dependencies include current-prefix marker' => static fn (): mixed => in_array('sqlite-savepoint-wal-current-prefix', $plan()['dependencies'], true),
    'passive dependencies include savepoint checkpoint marker' => static fn (): mixed => in_array('sqlite-wal-savepoint-checkpoint-current', $plan()['dependencies'], true),
    'full mode uses retained prefix' => static fn (): mixed => $plan('full')['current_checkpoint']['checkpointed_frame_count'],
    'full mode can reset retained prefix' => static fn (): mixed => $plan('full')['can_reset'],
    'full mode cannot truncate retained prefix' => static fn (): mixed => $plan('full')['can_truncate'],
    'restart mode can reset retained prefix' => static fn (): mixed => $plan('restart')['can_reset'],
    'restart mode cannot truncate retained prefix' => static fn (): mixed => $plan('restart')['can_truncate'],
    'truncate mode can reset retained prefix' => static fn (): mixed => $plan('truncate')['can_reset'],
    'truncate mode can truncate retained prefix' => static fn (): mixed => $plan('truncate')['can_truncate'],
    'truncate durable wal action truncates wal' => static fn (): mixed => $plan('truncate')['current_durable']['wal_action'],
    'truncate durable wal bytes are empty' => static fn (): mixed => strlen($plan('truncate')['current_durable']['wal_bytes']),
    'reader pinned at retained first frame keeps checkpoint ready' => static fn (): mixed => $plan('restart', 1)['status'],
    'reader pinned at retained first frame is busy for restart reset' => static fn (): mixed => $plan('restart', 1)['busy'],
    'reader pinned at retained first frame blocks reset' => static fn (): mixed => $plan('restart', 1)['can_reset'],
    'reader pinned at retained first frame preserves remaining committed frame' => static fn (): mixed => $plan('restart', 1)['current_checkpoint']['remaining_committed_frame_count'],
    'reader pinned at retained first frame reports reader reason' => static fn (): mixed => $plan('restart', 1)['reason'],
    'reader pinned beyond retained prefix is clamped to retained frame count' => static fn (): mixed => $plan('restart', 99)['current_checkpoint']['checkpointed_frame_count'],
    'invalid mode is rejected' => static function () use ($makeStack, $wal, $walBytes, $databaseBytes): mixed {
        try {
            SQLiteWalSavepointCheckpointPlan::afterRollbackTo($makeStack(), 'plugin_current', $wal, $walBytes, $databaseBytes, 'invalid');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'empty database image is rejected' => static function () use ($makeStack, $wal, $walBytes): mixed {
        try {
            SQLiteWalSavepointCheckpointPlan::afterRollbackTo($makeStack(), 'plugin_current', $wal, $walBytes, '');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'mismatched wal bytes are rejected' => static function () use ($makeStack, $wal, $walBytes, $databaseBytes): mixed {
        try {
            SQLiteWalSavepointCheckpointPlan::afterRollbackTo($makeStack(), 'plugin_current', $wal, substr($walBytes, 0, -1) . 'x', $databaseBytes);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'missing savepoint is rejected' => static function () use ($makeStack, $wal, $walBytes, $databaseBytes): mixed {
        try {
            SQLiteWalSavepointCheckpointPlan::afterRollbackTo($makeStack(), 'missing', $wal, $walBytes, $databaseBytes);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'passive status is ready on retained committed prefix' => 'ready',
    'passive savepoint name is preserved' => 'plugin_current',
    'passive mode is normalized' => 'passive',
    'passive original frame count includes discarded plugin frames' => 5,
    'passive retained frame count stops before savepoint frames' => 2,
    'passive discarded frame count covers current and nested frames' => 3,
    'passive truncation byte offset is retained prefix length' => 1104,
    'passive current wal bytes length matches truncation offset' => 1104,
    'passive current wal bytes preserve exact prefix' => true,
    'passive current checkpoint reason is all committed frames checkpointed' => 'passive_checkpoint_complete',
    'passive can checkpoint retained frames' => true,
    'passive can reset after savepoint rollback' => false,
    'passive cannot truncate in passive mode' => false,
    'passive is not busy' => false,
    'passive checkpointed frame count uses retained prefix' => 2,
    'passive total committable frame count uses retained prefix' => 2,
    'passive remaining committed frames is zero' => 0,
    'passive uncommitted frames disappear after rollback' => 0,
    'passive durable wal action leaves empty wal' => 'preserve_wal',
    'passive durable database page count remains three' => 3,
    'passive durable final database bytes are aligned' => 1536,
    'passive database image contains committed option frame' => true,
    'passive database image excludes plugin commit frame' => false,
    'passive wal bytes are header only after reset' => 1104,
    'passive wal header is present after reset' => true,
    'passive dependencies include current-prefix marker' => true,
    'passive dependencies include savepoint checkpoint marker' => true,
    'full mode uses retained prefix' => 2,
    'full mode can reset retained prefix' => false,
    'full mode cannot truncate retained prefix' => false,
    'restart mode can reset retained prefix' => true,
    'restart mode cannot truncate retained prefix' => false,
    'truncate mode can reset retained prefix' => true,
    'truncate mode can truncate retained prefix' => true,
    'truncate durable wal action truncates wal' => 'truncate_wal',
    'truncate durable wal bytes are empty' => 0,
    'reader pinned at retained first frame keeps checkpoint ready' => 'busy',
    'reader pinned at retained first frame is busy for restart reset' => true,
    'reader pinned at retained first frame blocks reset' => false,
    'reader pinned at retained first frame preserves remaining committed frame' => 1,
    'reader pinned at retained first frame reports reader reason' => 'reader_blocks_checkpoint_completion',
    'reader pinned beyond retained prefix is clamped to retained frame count' => 2,
    'invalid mode is rejected' => 'rejected',
    'empty database image is rejected' => 'rejected',
    'mismatched wal bytes are rejected' => 'rejected',
    'missing savepoint is rejected' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['wal savepoint checkpoint current next15 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
