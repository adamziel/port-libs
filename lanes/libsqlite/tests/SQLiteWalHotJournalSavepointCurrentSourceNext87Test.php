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

$cleanHeader = $page('next87-clean-header-before-hot-journal');
$cleanOptions = $page('next87-clean-options-before-hot-journal');
$cleanIndex = $page('next87-clean-index-before-hot-journal');
$dirtyDatabase = $page('next87-dirty-header-after-crash') . $page('next87-dirty-options-after-crash') . $page('next87-dirty-index-after-crash');

$makeJournalBytes = static function (array $pages, int $nonce = 0x20260528, int $initialPageCount = 3) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $salt2 = 87) use ($pageSize): string {
    $salt1 = 0x20260528;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 87, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $label] = $frame;
        $image = strlen($label) === $pageSize ? $label : str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
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
    [1, 0, 'next87-wal-schema-retained'],
    [2, 3, 'next87-wal-options-retained-commit'],
    [3, 0, 'next87-plugin-draft-after-savepoint'],
    [2, 3, 'next87-plugin-commit-discarded'],
    [1, 0, 'next87-nested-retry-discarded'],
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

$plan = static fn (array $pages = [1, 2, 3], string $savepoint = 'plugin_batch', string $journalBytesInput = null, SQLiteRollbackJournal $parsedJournal = null, string $walInput = null, SQLiteWal $parsedWal = null, bool $reservedLock = false, bool $requiresSuper = false, ?bool $superExists = null): array => SQLiteWalHotJournalSavepointReplayPlan::replayCurrentSourceNext(
    $parsedJournal ?? $journal,
    $dirtyDatabase,
    $journalBytesInput ?? $journalBytes,
    $makeStack(),
    $savepoint,
    $parsedWal ?? $wal,
    $walInput ?? $walBytes,
    $databasePath,
    $pages,
    $reservedLock,
    $requiresSuper,
    $superExists
);

$nestedPlan = static fn (): array => $plan([1, 2, 3], 'nested_retry');
$staleJournalBytes = $makeJournalBytes([
    1 => $page('next87-stale-header-before-hot-journal'),
    2 => $cleanOptions,
    3 => $cleanIndex,
], 0x20260529);
$staleWalBytes = $makeWalBytes([
    [1, 0, 'next87-stale-wal-schema-retained'],
    [2, 3, 'next87-wal-options-retained-commit'],
    [3, 0, 'next87-plugin-draft-after-savepoint'],
    [2, 3, 'next87-plugin-commit-discarded'],
    [1, 0, 'next87-nested-retry-discarded'],
], 88);

$cases = [
    'status recovers current source hot journal' => static fn (): mixed => $plan()['status'],
    'reason records hot journal before wal replay' => static fn (): mixed => $plan()['reason'],
    'database path is retained' => static fn (): mixed => $plan()['database_path'],
    'journal path is derived from database' => static fn (): mixed => $plan()['journal_path'],
    'wal path is derived from database' => static fn (): mixed => $plan()['wal_path'],
    'savepoint is reported' => static fn (): mixed => $plan()['savepoint'],
    'current source journal bytes match' => static fn (): mixed => $plan()['current_source']['journal_bytes_match'],
    'current source wal bytes match' => static fn (): mixed => $plan()['current_source']['wal_bytes_match'],
    'current source journal checksum validation is reported' => static fn (): mixed => $plan()['current_source']['journal_checksum_validated'],
    'current source wal checksum validation is reported' => static fn (): mixed => $plan()['current_source']['wal_checksum_validated'],
    'current source journal page count is reported' => static fn (): mixed => $plan()['current_source']['journal_page_count'],
    'current source wal frame count is reported' => static fn (): mixed => $plan()['current_source']['wal_frame_count'],
    'current source hot journal reason is reported' => static fn (): mixed => $plan()['current_source']['hot_journal_reason'],
    'current source reserved lock flag is reported' => static fn (): mixed => $plan()['current_source']['database_reserved_lock'],
    'current source super journal requirement is reported' => static fn (): mixed => $plan()['current_source']['requires_super_journal'],
    'current source super journal presence is reported' => static fn (): mixed => $plan([1], 'plugin_batch', null, null, null, null, false, true, true)['current_source']['super_journal_exists'],
    'hot journal recovered' => static fn (): mixed => $plan()['hot_recovered'],
    'journal action deletes recovered source' => static fn (): mixed => $plan()['journal_action'],
    'rollback target keeps pre savepoint commit' => static fn (): mixed => $plan()['rollback_to_frame'],
    'original frame count sees current wal' => static fn (): mixed => $plan()['original_frame_count'],
    'retained frame count keeps current prefix' => static fn (): mixed => $plan()['retained_frame_count'],
    'discarded frame count drops failed batch' => static fn (): mixed => $plan()['discarded_frame_count'],
    'current wal bytes are retained prefix length' => static fn (): mixed => $plan()['current_wal_bytes_length'],
    'retained wal contains committed option frame' => static fn (): mixed => str_contains($plan()['current_wal_bytes'], 'next87-wal-options-retained-commit'),
    'retained wal excludes failed plugin draft' => static fn (): mixed => str_contains($plan()['current_wal_bytes'], 'next87-plugin-draft-after-savepoint'),
    'retained wal excludes failed plugin commit' => static fn (): mixed => str_contains($plan()['current_wal_bytes'], 'next87-plugin-commit-discarded'),
    'current reader end frame is retained' => static fn (): mixed => $plan()['current_reader_end_frame'],
    'next reader end frame is retained' => static fn (): mixed => $plan()['next_reader_end_frame'],
    'current reader sources use wal and recovered database' => static fn (): mixed => $plan()['current_reader_sources'],
    'next reader sources match current source' => static fn (): mixed => $plan()['next_reader_sources'],
    'current reader frame indexes are stable' => static fn (): mixed => $plan()['current_reader_frame_indexes'],
    'next reader frame indexes are stable' => static fn (): mixed => $plan()['next_reader_frame_indexes'],
    'current reader has no source errors' => static fn (): mixed => $plan()['current_reader_errors'],
    'next reader has no source errors' => static fn (): mixed => $plan()['next_reader_errors'],
    'current and next images match' => static fn (): mixed => $plan()['images_match'],
    'next reader can use checkpoint database' => static fn (): mixed => $plan()['next_uses_checkpoint_database'],
    'checkpoint can apply retained prefix' => static fn (): mixed => $plan()['can_checkpoint'],
    'checkpoint page count is recovered database count' => static fn (): mixed => $plan()['checkpoint_database_page_count'],
    'no valid tail remains after savepoint truncation' => static fn (): mixed => $plan()['discarded_valid_tail_frame_count'],
    'no corrupt tail remains after savepoint truncation' => static fn (): mixed => $plan()['discarded_corrupt_tail_frame_count'],
    'first operation restores hot journal image' => static fn (): mixed => $plan()['operations'][0]['reason'],
    'third operation deletes hot journal' => static fn (): mixed => $plan()['operations'][2]['reason'],
    'checkpoint operation follows hot journal recovery' => static fn (): mixed => $plan()['operations'][3]['reason'],
    'retained wal write operation is present' => static fn (): mixed => $plan()['operations'][5]['reason'],
    'retained wal truncate operation is present' => static fn (): mixed => $plan()['operations'][6]['reason'],
    'hot journal payload is current source' => static fn (): mixed => array_key_exists($databasePath . '#hot-journal', $plan()['payloads']),
    'checkpoint payload is current source' => static fn (): mixed => array_key_exists($databasePath . '#savepoint-wal-checkpoint', $plan()['payloads']),
    'wal payload is current source' => static fn (): mixed => array_key_exists($databasePath . '-wal', $plan()['payloads']),
    'checkpoint image includes retained schema wal' => static fn (): mixed => str_contains((string) $plan()['wal_recovery']['checkpoint_database_bytes'], 'next87-wal-schema-retained'),
    'checkpoint image includes retained option wal' => static fn (): mixed => str_contains((string) $plan()['wal_recovery']['checkpoint_database_bytes'], 'next87-wal-options-retained-commit'),
    'checkpoint image excludes dirty crashed option page' => static fn (): mixed => str_contains((string) $plan()['wal_recovery']['checkpoint_database_bytes'], 'next87-dirty-options-after-crash'),
    'checkpoint image excludes discarded plugin commit' => static fn (): mixed => str_contains((string) $plan()['wal_recovery']['checkpoint_database_bytes'], 'next87-plugin-commit-discarded'),
    'page three falls back to recovered clean index' => static fn (): mixed => str_contains($plan()['current_reader'][2]['image'], 'next87-clean-index-before-hot-journal'),
    'page two reads retained current wal frame' => static fn (): mixed => str_contains($plan()['current_reader'][1]['image'], 'next87-wal-options-retained-commit'),
    'dependency includes current source marker' => static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-current-source-next87', $plan()['dependencies'], true),
    'dependency keeps replay marker' => static fn (): mixed => in_array('sqlite-hot-journal-savepoint-wal-replay-current-next', $plan()['dependencies'], true),
    'dependency keeps rollback marker' => static fn (): mixed => in_array('sqlite-rollback-journal-recovery', $plan()['dependencies'], true),
    'dependency keeps savepoint wal prefix marker' => static fn (): mixed => in_array('sqlite-savepoint-wal-current-prefix', $plan()['dependencies'], true),
    'nested rollback retains plugin commit frame' => static fn (): mixed => $nestedPlan()['retained_frame_count'],
    'nested rollback discards nested retry only' => static fn (): mixed => $nestedPlan()['discarded_frame_count'],
    'nested current source frame count is stable' => static fn (): mixed => $nestedPlan()['current_source']['wal_frame_count'],
    'reserved lock still records skipped hot reason' => static fn (): mixed => $plan([1], 'plugin_batch', null, null, null, null, true)['current_source']['hot_journal_reason'],
    'missing super journal records skipped reason' => static fn (): mixed => $plan([1], 'plugin_batch', null, null, null, null, false, true, false)['current_source']['hot_journal_reason'],
    'empty journal bytes rejected' => static function () use ($journal, $dirtyDatabase, $makeStack, $wal, $walBytes, $databasePath): mixed {
        try {
            SQLiteWalHotJournalSavepointReplayPlan::replayCurrentSourceNext($journal, $dirtyDatabase, '', $makeStack(), 'plugin_batch', $wal, $walBytes, $databasePath, [1]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'mutated journal bytes rejected before replay' => static function () use ($plan, $journalBytes): mixed {
        try {
            $plan([1], 'plugin_batch', substr($journalBytes, 0, -1) . 'x');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'stale parsed journal rejected before replay' => static function () use ($plan, $staleJournalBytes): mixed {
        try {
            $plan([1], 'plugin_batch', null, SQLiteRollbackJournal::parse($staleJournalBytes, true));
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'mutated wal bytes rejected before replay' => static function () use ($plan, $walBytes): mixed {
        try {
            $plan([1], 'plugin_batch', null, null, substr($walBytes, 0, -1) . 'x');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'stale parsed wal rejected before replay' => static function () use ($plan, $staleWalBytes): mixed {
        try {
            $plan([1], 'plugin_batch', null, null, null, SQLiteWal::parse($staleWalBytes, 512, true));
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'status recovers current source hot journal' => 'hot_journal_recovered_savepoint_wal_replayed',
    'reason records hot journal before wal replay' => 'hot_journal_recovered_before_savepoint_wal_replay',
    'database path is retained' => $databasePath,
    'journal path is derived from database' => $databasePath . '-journal',
    'wal path is derived from database' => $databasePath . '-wal',
    'savepoint is reported' => 'plugin_batch',
    'current source journal bytes match' => true,
    'current source wal bytes match' => true,
    'current source journal checksum validation is reported' => true,
    'current source wal checksum validation is reported' => true,
    'current source journal page count is reported' => 3,
    'current source wal frame count is reported' => 5,
    'current source hot journal reason is reported' => 'hot_journal_recovery_required',
    'current source reserved lock flag is reported' => false,
    'current source super journal requirement is reported' => false,
    'current source super journal presence is reported' => true,
    'hot journal recovered' => true,
    'journal action deletes recovered source' => 'delete_journal_after_recovery',
    'rollback target keeps pre savepoint commit' => 2,
    'original frame count sees current wal' => 5,
    'retained frame count keeps current prefix' => 2,
    'discarded frame count drops failed batch' => 3,
    'current wal bytes are retained prefix length' => 1104,
    'retained wal contains committed option frame' => true,
    'retained wal excludes failed plugin draft' => false,
    'retained wal excludes failed plugin commit' => false,
    'current reader end frame is retained' => 2,
    'next reader end frame is retained' => 2,
    'current reader sources use wal and recovered database' => ['wal', 'wal', 'database'],
    'next reader sources match current source' => ['wal', 'wal', 'database'],
    'current reader frame indexes are stable' => [1, 2, null],
    'next reader frame indexes are stable' => [1, 2, null],
    'current reader has no source errors' => [],
    'next reader has no source errors' => [],
    'current and next images match' => true,
    'next reader can use checkpoint database' => true,
    'checkpoint can apply retained prefix' => true,
    'checkpoint page count is recovered database count' => 3,
    'no valid tail remains after savepoint truncation' => 0,
    'no corrupt tail remains after savepoint truncation' => 0,
    'first operation restores hot journal image' => 'restore_hot_journal_database_before_savepoint_wal_replay',
    'third operation deletes hot journal' => 'delete_hot_journal_before_savepoint_wal_replay',
    'checkpoint operation follows hot journal recovery' => 'checkpoint_retained_wal_prefix_after_hot_journal',
    'retained wal write operation is present' => 'restore_savepoint_retained_wal_prefix',
    'retained wal truncate operation is present' => 'discard_savepoint_wal_frames_before_next_open',
    'hot journal payload is current source' => true,
    'checkpoint payload is current source' => true,
    'wal payload is current source' => true,
    'checkpoint image includes retained schema wal' => true,
    'checkpoint image includes retained option wal' => true,
    'checkpoint image excludes dirty crashed option page' => false,
    'checkpoint image excludes discarded plugin commit' => false,
    'page three falls back to recovered clean index' => true,
    'page two reads retained current wal frame' => true,
    'dependency includes current source marker' => true,
    'dependency keeps replay marker' => true,
    'dependency keeps rollback marker' => true,
    'dependency keeps savepoint wal prefix marker' => true,
    'nested rollback retains plugin commit frame' => 4,
    'nested rollback discards nested retry only' => 1,
    'nested current source frame count is stable' => 5,
    'reserved lock still records skipped hot reason' => 'database_has_reserved_lock',
    'missing super journal records skipped reason' => 'missing_super_journal',
    'empty journal bytes rejected' => 'rejected',
    'mutated journal bytes rejected before replay' => 'rejected',
    'stale parsed journal rejected before replay' => 'rejected',
    'mutated wal bytes rejected before replay' => 'rejected',
    'stale parsed wal rejected before replay' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['wal hot journal savepoint current source next87 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
