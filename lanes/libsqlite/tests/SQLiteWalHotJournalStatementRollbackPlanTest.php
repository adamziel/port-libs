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
$databasePath = '/wp-content/database/wp-statement-rollback.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$clean = [
    1 => $page('statement-rollback clean sqlite header before crashed import'),
    2 => $page('statement-rollback clean wp_options root before crashed import'),
    3 => $page('statement-rollback clean active_plugins before statement'),
    4 => $page('statement-rollback clean transient before failed statement'),
];
$dirtyDatabase = $page('statement-rollback dirty sqlite header after crash')
    . $page('statement-rollback dirty wp_options root after crash')
    . $page('statement-rollback dirty active_plugins after failed statement')
    . $page('statement-rollback dirty transient after failed statement');
$statementBefore = $page('statement-rollback statement before active_plugins insert');
$nextBefore = $page('statement-rollback retry before plugin option insert');

$makeJournalBytes = static function (array $pages, int $nonce = 0x20260591) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, 4, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $salt2 = 91) use ($pageSize): string {
    $salt1 = 0x20260528;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 91, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $label] = $frame;
        $image = str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$makeStack = static function () use ($statementBefore): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wordpress-import');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch');
    $stack->beginStatementJournal('insert-active-plugin');
    $stack->recordStatementPageImageWrite('insert-active-plugin', 3, $statementBefore);
    $stack->recordStatementWalFrameWrite('insert-active-plugin', 3, 3);
    $stack->recordStatementWalFrameWrite('insert-active-plugin', 4, 4, true);

    return $stack;
};

$journalBytes = $makeJournalBytes($clean);
$journal = SQLiteRollbackJournal::parse($journalBytes, true);
$walBytes = $makeWalBytes([
    [1, 0, 'statement-rollback retained schema frame'],
    [2, 4, 'statement-rollback retained wp_options root frame'],
    [3, 0, 'statement-rollback failed active_plugins draft'],
    [4, 4, 'statement-rollback failed transient commit'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$plan = static fn (
    ?SQLiteSavepointStack $stack = null,
    ?string $journalInput = null,
    ?SQLiteRollbackJournal $parsedJournal = null,
    ?string $walInput = null,
    ?SQLiteWal $parsedWal = null,
    bool $reservedLock = false,
    bool $requiresSuper = false,
    ?bool $superExists = null,
    ?array $sourcePages = null,
): array => SQLiteWalHotJournalSavepointReplayPlan::statementHotJournalRollbackPlan(
    $parsedJournal ?? $journal,
    $dirtyDatabase,
    $journalInput ?? $journalBytes,
    $stack ?? $makeStack(),
    'plugin-batch',
    'insert-active-plugin',
    'retry-plugin-option',
    5,
    $nextBefore,
    $parsedWal ?? $wal,
    $walInput ?? $walBytes,
    $databasePath,
    [1, 2, 3, 4],
    $sourcePages ?? [
        1 => $page('statement-rollback retained schema frame'),
        2 => $page('statement-rollback retained wp_options root frame'),
        3 => $page('statement-rollback failed active_plugins draft'),
        4 => $page('statement-rollback failed transient commit'),
    ],
    true,
    $reservedLock,
    $requiresSuper,
    $superExists,
);

$blocked = static fn (): array => $plan(null, null, null, null, null, false, true, false, [
    1 => $page('statement-rollback retained schema frame'),
    2 => $page('statement-rollback retained wp_options root frame'),
    3 => $page('statement-rollback failed active_plugins draft'),
    4 => $page('statement-rollback failed transient commit'),
]);

$cases = [
    'status recovered' => [static fn (): mixed => $plan()['status'], 'hot_journal_wal_statement_current_source_recovered_statement-rollback'],
    'reason recovered before statement' => [static fn (): mixed => $plan()['reason'], 'hot_journal_and_current_wal_precede_statement_rollback'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $databasePath . '-wal'],
    'savepoint' => [static fn (): mixed => $plan()['savepoint'], 'plugin-batch'],
    'current statement' => [static fn (): mixed => $plan()['current_statement'], 'insert-active-plugin'],
    'next statement' => [static fn (): mixed => $plan()['next_statement'], 'retry-plugin-option'],
    'hot recovered' => [static fn (): mixed => $plan()['hot_recovered'], true],
    'current source journal match' => [static fn (): mixed => $plan()['current_source']['journal_bytes_match'], true],
    'current source wal match' => [static fn (): mixed => $plan()['current_source']['wal_bytes_match'], true],
    'current source journal checksum' => [static fn (): mixed => $plan()['current_source']['journal_checksum_validated'], true],
    'current source wal checksum' => [static fn (): mixed => $plan()['current_source']['wal_checksum_validated'], true],
    'current source wal frame count' => [static fn (): mixed => $plan()['current_source']['wal_frame_count'], 4],
    'checkpoint bytes include retained root' => [static fn (): mixed => str_contains($plan()['checkpoint_database_bytes'], 'statement-rollback retained wp_options root frame'), true],
    'checkpoint bytes include failed statement page before statement rollback' => [static fn (): mixed => str_contains($plan()['checkpoint_database_bytes'], 'statement-rollback failed active_plugins draft'), true],
    'statement bytes restore statement page' => [static fn (): mixed => str_contains($plan()['statement_database_bytes'], 'statement-rollback statement before active_plugins insert'), true],
    'statement bytes keep retained root' => [static fn (): mixed => str_contains($plan()['statement_database_bytes'], 'statement-rollback retained wp_options root frame'), true],
    'statement bytes exclude failed active plugin draft' => [static fn (): mixed => str_contains($plan()['statement_database_bytes'], 'statement-rollback failed active_plugins draft'), false],
    'statement bytes exclude dirty root crash' => [static fn (): mixed => str_contains($plan()['statement_database_bytes'], 'statement-rollback dirty wp_options root after crash'), false],
    'statement wal prefix length' => [static fn (): mixed => $plan()['statement_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'statement wal keeps frame one' => [static fn (): mixed => str_contains($plan()['statement_wal_bytes'], 'statement-rollback retained schema frame'), true],
    'statement wal keeps frame two' => [static fn (): mixed => str_contains($plan()['statement_wal_bytes'], 'statement-rollback retained wp_options root frame'), true],
    'statement wal excludes frame three' => [static fn (): mixed => str_contains($plan()['statement_wal_bytes'], 'statement-rollback failed active_plugins draft'), false],
    'current source pages' => [static fn (): mixed => $plan()['current_source_pages'], [1, 2, 3, 4]],
    'current source prefix page one' => [static fn (): mixed => str_starts_with($plan()['current_source_prefixes'][1], 'statement-rollback retained schema frame'), true],
    'current source prefix page three' => [static fn (): mixed => str_starts_with($plan()['current_source_prefixes'][3], 'statement-rollback failed active_plugins draft'), true],
    'next source prefix page three' => [static fn (): mixed => str_starts_with($plan()['next_source_prefixes'][3], 'statement-rollback statement before active_plugi'), true],
    'rollback frame' => [static fn (): mixed => $plan()['rollback_to_frame'], 2],
    'next wal frame' => [static fn (): mixed => $plan()['next_wal_frame_index'], 3],
    'next page number' => [static fn (): mixed => $plan()['next_page_number'], 5],
    'next commit frame' => [static fn (): mixed => $plan()['next_commit_frame'], true],
    'rollback restored pages' => [static fn (): mixed => $plan()['rollback_restored_page_numbers'], [3]],
    'rollback discarded frames' => [static fn (): mixed => array_column($plan()['rollback_discarded_wal_frames'], 'frame_index'), [3, 4]],
    'statement journals after rollback empty' => [static fn (): mixed => $plan()['statement_journals_after_rollback'], []],
    'statement journals after next name' => [static fn (): mixed => $plan()['statement_journals_after_next'][0]['name'], 'retry-plugin-option'],
    'statement journals after next savepoint' => [static fn (): mixed => $plan()['statement_journals_after_next'][0]['savepoint'], 'plugin-batch'],
    'statement journals after next page' => [static fn (): mixed => $plan()['statement_journals_after_next'][0]['page_numbers'], [5]],
    'statement journals after next frame' => [static fn (): mixed => $plan()['statement_journals_after_next'][0]['wal_frame_indexes'], [3]],
    'pending pages after next' => [static fn (): mixed => $plan()['pending_page_numbers_after_next'], [1, 2, 5]],
    'pending wal after next' => [static fn (): mixed => $plan()['pending_wal_frame_indexes_after_next'], [1, 2, 3]],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 10],
    'operation hot restore first' => [static fn (): mixed => $plan()['operations'][0]['reason'], 'restore_hot_journal_database_before_statement_wal_current_source'],
    'operation checkpoint current wal' => [static fn (): mixed => $plan()['operations'][3]['reason'], 'checkpoint_current_wal_before_statement_rollback'],
    'operation statement restore' => [static fn (): mixed => $plan()['operations'][5]['reason'], 'restore_statement_subjournal_after_hot_journal_wal_current_source'],
    'operation statement wal write' => [static fn (): mixed => $plan()['operations'][7]['reason'], 'restore_statement_rollback_wal_prefix_before_next_statement'],
    'operation final sync' => [static fn (): mixed => $plan()['operations'][9]['reason'], 'sync_statement_current_source_after_hot_journal_wal_replay'],
    'payload statement exists' => [static fn (): mixed => array_key_exists($databasePath . '#statement-statement-rollback', $plan()['payloads']), true],
    'payload statement has before image' => [static fn (): mixed => str_contains($plan()['payloads'][$databasePath . '#statement-statement-rollback'], 'statement-rollback statement before active_plugins insert'), true],
    'payload wal exists' => [static fn (): mixed => array_key_exists($databasePath . '-wal#statement-statement-rollback', $plan()['payloads']), true],
    'payload wal excludes failed transient' => [static fn (): mixed => str_contains($plan()['payloads'][$databasePath . '-wal#statement-statement-rollback'], 'statement-rollback failed transient commit'), false],
    'dependency statement-rollback' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-statement-statement-rollback', $plan()['dependencies'], true), true],
    'dependency statement hot wal' => [static fn (): mixed => in_array('sqlite-statement-subjournal-after-hot-journal-wal-current-source', $plan()['dependencies'], true), true],
    'dependency current source guard' => [static fn (): mixed => in_array('sqlite-statement-journal-current-source-guard', $plan()['dependencies'], true), true],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'hot_journal_wal_statement_current_source_skipped_statement-rollback'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'statement_rollback_uses_current_wal_without_hot_journal_recovery'],
    'blocked hot recovered false' => [static fn (): mixed => $blocked()['hot_recovered'], false],
    'blocked page one wal source verified' => [static fn (): mixed => str_starts_with($blocked()['current_source_prefixes'][1], 'statement-rollback retained schema frame'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal statement current source statement-rollback ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty current statement rejected' => static fn () => SQLiteWalHotJournalSavepointReplayPlan::statementHotJournalRollbackPlan($journal, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-batch', '', 'next', 5, $nextBefore, $wal, $walBytes, $databasePath, [1], [1 => $clean[1]]),
    'empty next statement rejected' => static fn () => SQLiteWalHotJournalSavepointReplayPlan::statementHotJournalRollbackPlan($journal, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-batch', 'insert-active-plugin', '', 5, $nextBefore, $wal, $walBytes, $databasePath, [1], [1 => $clean[1]]),
    'zero next page rejected' => static fn () => SQLiteWalHotJournalSavepointReplayPlan::statementHotJournalRollbackPlan($journal, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-batch', 'insert-active-plugin', 'next', 0, $nextBefore, $wal, $walBytes, $databasePath, [1], [1 => $clean[1]]),
    'bad next image rejected' => static fn () => SQLiteWalHotJournalSavepointReplayPlan::statementHotJournalRollbackPlan($journal, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-batch', 'insert-active-plugin', 'next', 5, 'short', $wal, $walBytes, $databasePath, [1], [1 => $clean[1]]),
    'empty source pages rejected' => static fn () => SQLiteWalHotJournalSavepointReplayPlan::statementHotJournalRollbackPlan($journal, $dirtyDatabase, $journalBytes, $makeStack(), 'plugin-batch', 'insert-active-plugin', 'next', 5, $nextBefore, $wal, $walBytes, $databasePath, [1], []),
    'stale source page rejected' => static fn () => $plan(null, null, null, null, null, false, false, null, [1 => $clean[1]]),
    'journal bytes mismatch rejected' => static fn () => $plan(null, substr($journalBytes, 0, -1) . 'x'),
    'wal bytes mismatch rejected' => static fn () => $plan(null, null, null, substr($walBytes, 0, -1) . 'x'),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal statement current source statement-rollback ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
