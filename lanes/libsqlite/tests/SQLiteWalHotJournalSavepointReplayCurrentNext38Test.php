<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointReplayPlan;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/wp-content/database/.ht.sqlite';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$cleanHeader = $page('clean-header-before-hot-journal');
$cleanOptions = $page('clean-wp-options-before-hot-journal');
$cleanIndex = $page('clean-autoload-index-before-hot-journal');
$dirtyHeader = $page('dirty-header-after-crash');
$dirtyOptions = $page('dirty-options-after-crash');
$dirtyIndex = $page('dirty-index-after-crash');
$databaseBytes = $dirtyHeader . $dirtyOptions . $dirtyIndex;

$makeJournalBytes = static function (array $pages, int $initialPageCount = 3) use ($sectorSize, $pageSize): string {
    $nonce = 0x13572468;
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames) use ($pageSize): string {
    $salt1 = 0x20260527;
    $salt2 = 0x00000038;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 38, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $label] = $frame;
        $image = $label;
        if (strlen($image) !== $pageSize) {
            $image = str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
        }
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$journalBytes = $makeJournalBytes([
    1 => $cleanHeader,
    2 => $cleanOptions,
    3 => $cleanIndex,
]);
$journal = SQLiteRollbackJournal::parse($journalBytes, true);
$walBytes = $makeWalBytes([
    [1, 0, 'wal-schema-after-hot-journal'],
    [2, 3, 'wal-options-commit-before-plugin'],
    [3, 0, 'plugin-draft-after-savepoint'],
    [2, 3, 'plugin-commit-to-discard'],
    [1, 0, 'nested-draft-after-plugin'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('application_import');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin_batch');
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 2, true);
    $stack->savepoint('nested_retry');
    $stack->recordWalFrameWrite(5, 1);

    return $stack;
};

$plan = static fn (array $pages = [1, 2, 3], string $savepoint = 'plugin_batch', string $journalInput = null, bool $reservedLock = false, bool $requiresSuper = false, ?bool $superExists = null): array => SQLiteWalHotJournalSavepointReplayPlan::replayCurrentNext(
    $journal,
    $databaseBytes,
    $journalInput ?? $journalBytes,
    $makeStack(),
    $savepoint,
    $wal,
    $walBytes,
    $databasePath,
    $pages,
    $reservedLock,
    $requiresSuper,
    $superExists
);

$nestedPlan = static fn (): array => $plan([1, 2, 3], 'nested_retry');
$shortJournal = substr($journalBytes, 0, 512);

$cases = [
    'status recovers hot journal before savepoint wal replay' => static fn (): mixed => $plan()['status'],
    'reason records replay ordering' => static fn (): mixed => $plan()['reason'],
    'database path is preserved' => static fn (): mixed => $plan()['database_path'],
    'journal path is derived' => static fn (): mixed => $plan()['journal_path'],
    'wal path is derived' => static fn (): mixed => $plan()['wal_path'],
    'savepoint name is reported' => static fn (): mixed => $plan()['savepoint'],
    'hot journal is recovered' => static fn (): mixed => $plan()['hot_recovered'],
    'journal action deletes recovered journal' => static fn (): mixed => $plan()['journal_action'],
    'rollback target keeps committed pre-plugin frame' => static fn (): mixed => $plan()['rollback_to_frame'],
    'original wal frame count includes plugin and nested frames' => static fn (): mixed => $plan()['original_frame_count'],
    'retained frame count stops before plugin savepoint' => static fn (): mixed => $plan()['retained_frame_count'],
    'discarded frame count includes plugin commit and nested draft' => static fn (): mixed => $plan()['discarded_frame_count'],
    'current wal byte length is retained prefix' => static fn (): mixed => $plan()['current_wal_bytes_length'],
    'current wal preserves committed option frame' => static fn (): mixed => str_contains($plan()['current_wal_bytes'], 'wal-options-commit-before-plugin'),
    'current wal discards plugin draft frame' => static fn (): mixed => str_contains($plan()['current_wal_bytes'], 'plugin-draft-after-savepoint'),
    'current wal discards plugin commit frame' => static fn (): mixed => str_contains($plan()['current_wal_bytes'], 'plugin-commit-to-discard'),
    'current wal discards nested draft frame' => static fn (): mixed => str_contains($plan()['current_wal_bytes'], 'nested-draft-after-plugin'),
    'current reader ends at retained frame' => static fn (): mixed => $plan()['current_reader_end_frame'],
    'next reader ends at committed retained frame' => static fn (): mixed => $plan()['next_reader_end_frame'],
    'current reader sources include hot recovered base fallback' => static fn (): mixed => $plan()['current_reader_sources'],
    'next reader sources match current reader sources' => static fn (): mixed => $plan()['next_reader_sources'],
    'current reader frame indexes are stable' => static fn (): mixed => $plan()['current_reader_frame_indexes'],
    'next reader frame indexes are stable' => static fn (): mixed => $plan()['next_reader_frame_indexes'],
    'current reader has no errors' => static fn (): mixed => $plan()['current_reader_errors'],
    'next reader has no errors' => static fn (): mixed => $plan()['next_reader_errors'],
    'current and next images match' => static fn (): mixed => $plan()['images_match'],
    'next reader can use checkpoint database' => static fn (): mixed => $plan()['next_uses_checkpoint_database'],
    'retained prefix can checkpoint' => static fn (): mixed => $plan()['can_checkpoint'],
    'checkpoint page count comes from hot recovered database' => static fn (): mixed => $plan()['checkpoint_database_page_count'],
    'no valid wal tail remains after savepoint truncation' => static fn (): mixed => $plan()['discarded_valid_tail_frame_count'],
    'no corrupt wal tail remains after savepoint truncation' => static fn (): mixed => $plan()['discarded_corrupt_tail_frame_count'],
    'operations restore hot journal before checkpoint' => static fn (): mixed => $plan()['operations'][0]['reason'],
    'operations delete hot journal before wal prefix write' => static fn (): mixed => $plan()['operations'][2]['reason'],
    'operations checkpoint retained prefix' => static fn (): mixed => $plan()['operations'][3]['reason'],
    'operations write retained wal prefix' => static fn (): mixed => $plan()['operations'][5]['reason'],
    'operations truncate discarded wal frames' => static fn (): mixed => $plan()['operations'][6]['reason'],
    'payloads include hot journal database image' => static fn (): mixed => array_key_exists($databasePath . '#hot-journal', $plan()['payloads']),
    'payloads include checkpointed database image' => static fn (): mixed => array_key_exists($databasePath . '#savepoint-wal-checkpoint', $plan()['payloads']),
    'payloads include retained wal bytes' => static fn (): mixed => array_key_exists($databasePath . '-wal', $plan()['payloads']),
    'checkpoint image replays retained schema wal over hot journal' => static fn (): mixed => str_contains((string) $plan()['wal_recovery']['checkpoint_database_bytes'], 'wal-schema-after-hot-journal'),
    'checkpoint image keeps wal committed option page' => static fn (): mixed => str_contains((string) $plan()['wal_recovery']['checkpoint_database_bytes'], 'wal-options-commit-before-plugin'),
    'checkpoint image excludes dirty crashed option page' => static fn (): mixed => str_contains((string) $plan()['wal_recovery']['checkpoint_database_bytes'], 'dirty-options-after-crash'),
    'checkpoint image excludes plugin savepoint commit' => static fn (): mixed => str_contains((string) $plan()['wal_recovery']['checkpoint_database_bytes'], 'plugin-commit-to-discard'),
    'page three falls back to hot recovered clean index' => static fn (): mixed => str_contains($plan()['current_reader'][2]['image'], 'clean-autoload-index-before-hot-journal'),
    'page two reads committed retained wal frame' => static fn (): mixed => str_contains($plan()['current_reader'][1]['image'], 'wal-options-commit-before-plugin'),
    'dependencies include new replay marker' => static fn (): mixed => in_array('sqlite-hot-journal-savepoint-wal-replay-current-next', $plan()['dependencies'], true),
    'dependencies include rollback journal recovery' => static fn (): mixed => in_array('sqlite-rollback-journal-recovery', $plan()['dependencies'], true),
    'dependencies include savepoint prefix marker' => static fn (): mixed => in_array('sqlite-savepoint-wal-current-prefix', $plan()['dependencies'], true),
    'dependencies include transaction recovery marker' => static fn (): mixed => in_array('sqlite-wal-transaction-recovery-boundary', $plan()['dependencies'], true),
    'short journal skips hot journal recovery' => static fn (): mixed => $plan([1, 2, 3], 'plugin_batch', $shortJournal)['status'],
    'short journal preserves journal sidecar' => static fn (): mixed => $plan([1, 2, 3], 'plugin_batch', $shortJournal)['journal_action'],
    'short journal uses dirty database fallback for page three' => static fn (): mixed => str_contains($plan([3], 'plugin_batch', $shortJournal)['current_reader'][0]['image'], 'dirty-index-after-crash'),
    'reserved lock skips hot recovery' => static fn (): mixed => $plan([1], 'plugin_batch', null, true)['hot_recovered'],
    'missing super journal skips hot recovery' => static fn (): mixed => $plan([1], 'plugin_batch', null, false, true, false)['hot_journal']['reason'],
    'present super journal recovers hot recovery' => static fn (): mixed => $plan([1], 'plugin_batch', null, false, true, true)['hot_recovered'],
    'nested rollback retains plugin commit frame' => static fn (): mixed => $nestedPlan()['retained_frame_count'],
    'nested rollback discards only nested draft' => static fn (): mixed => $nestedPlan()['discarded_frame_count'],
    'nested page two sees plugin commit frame' => static fn (): mixed => $nestedPlan()['current_reader_frame_indexes'][1],
    'nested checkpoint includes plugin commit frame' => static fn (): mixed => str_contains((string) $nestedPlan()['wal_recovery']['checkpoint_database_bytes'], 'plugin-commit-to-discard'),
    'single page reader source is wal' => static fn (): mixed => $plan([2])['current_reader_sources'],
    'single page frame index is retained commit frame' => static fn (): mixed => $plan([2])['current_reader_frame_indexes'],
    'empty database path rejected' => static function () use ($journal, $databaseBytes, $journalBytes, $makeStack, $wal, $walBytes): mixed {
        try {
            SQLiteWalHotJournalSavepointReplayPlan::replayCurrentNext($journal, $databaseBytes, $journalBytes, $makeStack(), 'plugin_batch', $wal, $walBytes, '', [1]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'empty savepoint rejected' => static function () use ($journal, $databaseBytes, $journalBytes, $makeStack, $wal, $walBytes, $databasePath): mixed {
        try {
            SQLiteWalHotJournalSavepointReplayPlan::replayCurrentNext($journal, $databaseBytes, $journalBytes, $makeStack(), '', $wal, $walBytes, $databasePath, [1]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'empty page list rejected' => static function () use ($journal, $databaseBytes, $journalBytes, $makeStack, $wal, $walBytes, $databasePath): mixed {
        try {
            SQLiteWalHotJournalSavepointReplayPlan::replayCurrentNext($journal, $databaseBytes, $journalBytes, $makeStack(), 'plugin_batch', $wal, $walBytes, $databasePath, []);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'non integer page rejected' => static function () use ($plan): mixed {
        try {
            $plan([1, '2']);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'mismatched wal bytes rejected' => static function () use ($journal, $databaseBytes, $journalBytes, $makeStack, $wal, $walBytes, $databasePath): mixed {
        try {
            SQLiteWalHotJournalSavepointReplayPlan::replayCurrentNext($journal, $databaseBytes, $journalBytes, $makeStack(), 'plugin_batch', $wal, substr($walBytes, 0, -1) . 'x', $databasePath, [1]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'missing savepoint rejected' => static function () use ($plan): mixed {
        try {
            $plan([1], 'missing');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'status recovers hot journal before savepoint wal replay' => 'hot_journal_recovered_savepoint_wal_replayed',
    'reason records replay ordering' => 'hot_journal_recovered_before_savepoint_wal_replay',
    'database path is preserved' => $databasePath,
    'journal path is derived' => $databasePath . '-journal',
    'wal path is derived' => $databasePath . '-wal',
    'savepoint name is reported' => 'plugin_batch',
    'hot journal is recovered' => true,
    'journal action deletes recovered journal' => 'delete_journal_after_recovery',
    'rollback target keeps committed pre-plugin frame' => 2,
    'original wal frame count includes plugin and nested frames' => 5,
    'retained frame count stops before plugin savepoint' => 2,
    'discarded frame count includes plugin commit and nested draft' => 3,
    'current wal byte length is retained prefix' => 1104,
    'current wal preserves committed option frame' => true,
    'current wal discards plugin draft frame' => false,
    'current wal discards plugin commit frame' => false,
    'current wal discards nested draft frame' => false,
    'current reader ends at retained frame' => 2,
    'next reader ends at committed retained frame' => 2,
    'current reader sources include hot recovered base fallback' => ['wal', 'wal', 'database'],
    'next reader sources match current reader sources' => ['wal', 'wal', 'database'],
    'current reader frame indexes are stable' => [1, 2, null],
    'next reader frame indexes are stable' => [1, 2, null],
    'current reader has no errors' => [],
    'next reader has no errors' => [],
    'current and next images match' => true,
    'next reader can use checkpoint database' => true,
    'retained prefix can checkpoint' => true,
    'checkpoint page count comes from hot recovered database' => 3,
    'no valid wal tail remains after savepoint truncation' => 0,
    'no corrupt wal tail remains after savepoint truncation' => 0,
    'operations restore hot journal before checkpoint' => 'restore_hot_journal_database_before_savepoint_wal_replay',
    'operations delete hot journal before wal prefix write' => 'delete_hot_journal_before_savepoint_wal_replay',
    'operations checkpoint retained prefix' => 'checkpoint_retained_wal_prefix_after_hot_journal',
    'operations write retained wal prefix' => 'restore_savepoint_retained_wal_prefix',
    'operations truncate discarded wal frames' => 'discard_savepoint_wal_frames_before_next_open',
    'payloads include hot journal database image' => true,
    'payloads include checkpointed database image' => true,
    'payloads include retained wal bytes' => true,
    'checkpoint image replays retained schema wal over hot journal' => true,
    'checkpoint image keeps wal committed option page' => true,
    'checkpoint image excludes dirty crashed option page' => false,
    'checkpoint image excludes plugin savepoint commit' => false,
    'page three falls back to hot recovered clean index' => true,
    'page two reads committed retained wal frame' => true,
    'dependencies include new replay marker' => true,
    'dependencies include rollback journal recovery' => true,
    'dependencies include savepoint prefix marker' => true,
    'dependencies include transaction recovery marker' => true,
    'short journal skips hot journal recovery' => 'hot_journal_skipped_savepoint_wal_replayed',
    'short journal preserves journal sidecar' => 'preserve_journal',
    'short journal uses dirty database fallback for page three' => true,
    'reserved lock skips hot recovery' => false,
    'missing super journal skips hot recovery' => 'missing_super_journal',
    'present super journal recovers hot recovery' => true,
    'nested rollback retains plugin commit frame' => 4,
    'nested rollback discards only nested draft' => 1,
    'nested page two sees plugin commit frame' => 4,
    'nested checkpoint includes plugin commit frame' => true,
    'single page reader source is wal' => ['wal'],
    'single page frame index is retained commit frame' => [2],
    'empty database path rejected' => 'rejected',
    'empty savepoint rejected' => 'rejected',
    'empty page list rejected' => 'rejected',
    'non integer page rejected' => 'rejected',
    'mismatched wal bytes rejected' => 'rejected',
    'missing savepoint rejected' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['wal hot journal savepoint replay current next38 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
