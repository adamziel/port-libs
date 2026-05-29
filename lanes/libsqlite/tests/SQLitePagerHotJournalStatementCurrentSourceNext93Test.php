<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalWalRecoveryPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/wp-content/database/wp.sqlite';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$dirtyPages = [
    1 => $page('next93 dirty sqlite schema after crashed plugin import'),
    2 => $page('next93 dirty wp_options root after crashed plugin import'),
    3 => $page('next93 dirty active_plugins after crashed plugin import'),
    4 => $page('next93 dirty transient statement page'),
    5 => $page('next93 dirty autoload index statement page'),
];
$cleanPages = [
    1 => $page('next93 clean sqlite schema before crashed plugin import'),
    2 => $page('next93 clean wp_options root before crashed plugin import'),
    3 => $page('next93 clean active_plugins before crashed plugin import'),
    4 => $page('next93 clean transient before statement'),
    5 => $page('next93 clean autoload index before statement'),
];
$databaseBytes = implode('', $dirtyPages);

$makeJournalBytes = static function () use ($sectorSize, $pageSize, $cleanPages): string {
    $nonce = 0x93000093;
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), $nonce, count($cleanPages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($cleanPages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (int $frames = 5, ?string $pageFour = null) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 93, 0x93939393, 0x39393939);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $allFrames = [
        [1, 0, 'next93 wal schema retained after hot journal'],
        [2, 3, 'next93 wal wp_options retained commit'],
        [3, 0, 'next93 wal active_plugins retained draft'],
        [4, 0, $pageFour ?? 'next93 wal dirty transient statement page'],
        [5, 5, 'next93 wal dirty autoload index statement page'],
    ];

    foreach (array_slice($allFrames, 0, $frames) as [$pageNumber, $commit, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commit, 0x93939393, 0x39393939);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$journalBytes = $makeJournalBytes();
$journal = SQLiteRollbackJournal::parse($journalBytes, true);
$walBytes = $makeWalBytes();
$staleWalBytes = $makeWalBytes(5, 'next93 stale different transient image');
$shortWalBytes = $makeWalBytes(4);
$currentPageImages = [
    4 => $page('next93 wal dirty transient statement page'),
    5 => $page('next93 wal dirty autoload index statement page'),
];
$nextBeforeImage = $page('next93 retry transient before next statement');

$makeStack = static function () use ($cleanPages): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next93');
    $stack->recordPageImageWrite(1, $cleanPages[1]);
    $stack->recordWalFrameWrite(1, 1, false);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-batch-next93');
    $stack->recordPageImageWrite(3, $cleanPages[3]);
    $stack->recordWalFrameWrite(3, 3, false);
    $stack->beginStatementJournal('insert-transient-next93');
    $stack->recordStatementPageImageWrite('insert-transient-next93', 4, $cleanPages[4]);
    $stack->recordStatementPageImageWrite('insert-transient-next93', 5, $cleanPages[5]);
    $stack->recordStatementWalFrameWrite('insert-transient-next93', 4, 4, false);
    $stack->recordStatementWalFrameWrite('insert-transient-next93', 5, 5, true);

    return $stack;
};

$plan = static fn (array $pages = null, string $walInput = null, bool $commit = true): array => SQLitePagerHotJournalWalRecoveryPlan::statementWalRecoveryCurrentSourceNext(
    $journal,
    $databaseBytes,
    $journalBytes,
    $walInput ?? $walBytes,
    $databasePath,
    $makeStack(),
    'insert-transient-next93',
    'retry-transient-next93',
    $pages ?? $currentPageImages,
    6,
    $nextBeforeImage,
    $pageSize,
    $commit,
    false,
    true,
    true
);
$result = static fn (): array => $plan();
$single = static fn (): array => $plan([4 => $currentPageImages[4]], $walBytes, false);

$cases = [
    'status' => [static fn (): mixed => $result()['status'], 'hot_journal_statement_current_source_next'],
    'reason' => [static fn (): mixed => $result()['reason'], 'hot_journal_recovery_then_statement_subjournal_current_source_retry'],
    'database path' => [static fn (): mixed => $result()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $result()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $result()['wal_path'], $databasePath . '-wal'],
    'current statement' => [static fn (): mixed => $result()['current_statement'], 'insert-transient-next93'],
    'next statement' => [static fn (): mixed => $result()['next_statement'], 'retry-transient-next93'],
    'savepoint' => [static fn (): mixed => $result()['savepoint'], 'plugin-batch-next93'],
    'page size' => [static fn (): mixed => $result()['page_size'], $pageSize],
    'hot recovered' => [static fn (): mixed => $result()['hot_recovered'], true],
    'journal action' => [static fn (): mixed => $result()['journal_action'], 'delete_journal_after_recovery'],
    'wal recovery status' => [static fn (): mixed => $result()['wal_recovery_status'], 'valid'],
    'current source verified' => [static fn (): mixed => $result()['current_source_verified'], true],
    'current source pages sorted' => [static fn (): mixed => $result()['current_source_page_numbers'], [4, 5]],
    'current reader sources' => [static fn (): mixed => $result()['current_reader_sources'], ['wal', 'wal']],
    'current reader frames' => [static fn (): mixed => $result()['current_reader_frame_indexes'], [4, 5]],
    'current database bytes' => [static fn (): mixed => $result()['current_database_bytes'], 2560],
    'rolled back database bytes' => [static fn (): mixed => $result()['rolled_back_database_bytes'], 2560],
    'rollback frame' => [static fn (): mixed => $result()['rollback_to_wal_frame'], 3],
    'next wal frame' => [static fn (): mixed => $result()['next_wal_frame_index'], 4],
    'next page number' => [static fn (): mixed => $result()['next_page_number'], 6],
    'next commit frame' => [static fn (): mixed => $result()['next_commit_frame'], true],
    'restored statement pages' => [static fn (): mixed => $result()['rollback_restored_page_numbers'], [4, 5]],
    'discarded wal frames' => [static fn (): mixed => array_column($result()['rollback_discarded_wal_frames'], 'frame_index'), [4, 5]],
    'discarded wal pages' => [static fn (): mixed => array_column($result()['rollback_discarded_wal_frames'], 'page_number'), [4, 5]],
    'discarded wal commits' => [static fn (): mixed => array_column($result()['rollback_discarded_wal_frames'], 'commit_frame'), [false, true]],
    'journals cleared after rollback' => [static fn (): mixed => $result()['statement_journals_after_rollback'], []],
    'next journal name' => [static fn (): mixed => $result()['statement_journals_after_next'][0]['name'], 'retry-transient-next93'],
    'next journal savepoint' => [static fn (): mixed => $result()['statement_journals_after_next'][0]['savepoint'], 'plugin-batch-next93'],
    'next journal wal start' => [static fn (): mixed => $result()['statement_journals_after_next'][0]['wal_start_frame'], 3],
    'next journal page' => [static fn (): mixed => $result()['statement_journals_after_next'][0]['page_numbers'], [6]],
    'next journal wal frame' => [static fn (): mixed => $result()['statement_journals_after_next'][0]['wal_frame_indexes'], [4]],
    'pending pages after rollback' => [static fn (): mixed => $result()['pending_page_numbers_after_rollback'], [1, 2, 3]],
    'pending wal after rollback' => [static fn (): mixed => $result()['pending_wal_frame_indexes_after_rollback'], [1, 2, 3]],
    'pending pages after next' => [static fn (): mixed => $result()['pending_page_numbers_after_next'], [1, 2, 3, 6]],
    'pending wal after next' => [static fn (): mixed => $result()['pending_wal_frame_indexes_after_next'], [1, 2, 3, 4]],
    'current prefix transient' => [static fn (): mixed => str_contains($result()['current_source_prefixes'][4], 'dirty transient statement'), true],
    'current prefix index' => [static fn (): mixed => str_contains($result()['current_source_prefixes'][5], 'dirty autoload index'), true],
    'next prefix transient clean' => [static fn (): mixed => str_contains($result()['next_source_prefixes'][4], 'clean transient before'), true],
    'next prefix index clean' => [static fn (): mixed => str_contains($result()['next_source_prefixes'][5], 'clean autoload index'), true],
    'current reader image transient' => [static fn (): mixed => str_contains((string) $result()['current_reader'][0]['image'], 'wal dirty transient'), true],
    'hot recovery clean base schema' => [static fn (): mixed => str_contains($result()['hot_recovery']['payloads'][$databasePath . '#hot-journal'], 'clean sqlite schema'), true],
    'hot recovery excludes dirty base schema' => [static fn (): mixed => str_contains($result()['hot_recovery']['payloads'][$databasePath . '#hot-journal'], 'dirty sqlite schema'), false],
    'statement recovery rolled back clean transient' => [static fn (): mixed => str_contains($result()['statement_recovery']['rolled_back_database_bytes'], 'clean transient before statement'), true],
    'statement recovery excludes dirty transient' => [static fn (): mixed => str_contains($result()['statement_recovery']['rolled_back_database_bytes'], 'dirty transient statement'), false],
    'operation hot restore first' => [static fn (): mixed => $result()['operations'][0]['reason'], 'restore_hot_journal_database_before_wal_recovery'],
    'operation statement rollback last' => [static fn (): mixed => $result()['operations'][count($result()['operations']) - 1]['reason'], 'restore_statement_subjournal_after_hot_journal_recovery'],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-hot-journal-savepoint-statement-current-source-next93', $result()['dependencies'], true), true],
    'dependency hot recovery' => [static fn (): mixed => in_array('sqlite-pager-hot-journal-wal-recovery', $result()['dependencies'], true), true],
    'dependency statement current source' => [static fn (): mixed => in_array('sqlite-pager-statement-journal-savepoint-current-source', $result()['dependencies'], true), true],
    'single page source pages' => [static fn (): mixed => $single()['current_source_page_numbers'], [4]],
    'single page next commit false' => [static fn (): mixed => $single()['next_commit_frame'], false],
    'single page reader source' => [static fn (): mixed => $single()['current_reader_sources'], ['wal']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager hot journal savepoint statement current source next93 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty current statement rejected' => static fn () => SQLitePagerHotJournalWalRecoveryPlan::statementWalRecoveryCurrentSourceNext($journal, $databaseBytes, $journalBytes, $walBytes, $databasePath, $makeStack(), '', 'retry', $currentPageImages, 6, $nextBeforeImage, $pageSize),
    'empty next statement rejected' => static fn () => SQLitePagerHotJournalWalRecoveryPlan::statementWalRecoveryCurrentSourceNext($journal, $databaseBytes, $journalBytes, $walBytes, $databasePath, $makeStack(), 'insert-transient-next93', '', $currentPageImages, 6, $nextBeforeImage, $pageSize),
    'empty current images rejected' => static fn () => SQLitePagerHotJournalWalRecoveryPlan::statementWalRecoveryCurrentSourceNext($journal, $databaseBytes, $journalBytes, $walBytes, $databasePath, $makeStack(), 'insert-transient-next93', 'retry', [], 6, $nextBeforeImage, $pageSize),
    'bad page number rejected' => static fn () => $plan([0 => $currentPageImages[4]]),
    'bad page image rejected' => static fn () => $plan([4 => 'short']),
    'stale wal current source rejected' => static fn () => $plan($currentPageImages, $staleWalBytes),
    'missing wal current source rejected' => static fn () => $plan([5 => $currentPageImages[5]], $shortWalBytes),
    'bad next page rejected' => static fn () => SQLitePagerHotJournalWalRecoveryPlan::statementWalRecoveryCurrentSourceNext($journal, $databaseBytes, $journalBytes, $walBytes, $databasePath, $makeStack(), 'insert-transient-next93', 'retry', $currentPageImages, 0, $nextBeforeImage, $pageSize),
    'bad next image rejected' => static fn () => SQLitePagerHotJournalWalRecoveryPlan::statementWalRecoveryCurrentSourceNext($journal, $databaseBytes, $journalBytes, $walBytes, $databasePath, $makeStack(), 'insert-transient-next93', 'retry', $currentPageImages, 6, 'short', $pageSize),
];

foreach ($throws as $name => $callback) {
    $tests['pager hot journal savepoint statement current source next93 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
