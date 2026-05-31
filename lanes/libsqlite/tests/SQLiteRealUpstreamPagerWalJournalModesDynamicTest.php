<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalCommitPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;

require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalCommitPlan.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$sectorSizes = [512, 1024, 2048, 4096];
$journalModes = ['delete', 'truncate', 'persist'];
$syncModes = ['off', 'normal', 'full', 'extra'];

$page = static fn (string $label, int $pageSize): string => str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);

$database = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page("{$label} page {$pageNumber}", $pageSize);
    }

    return $bytes;
};

$makeJournal = static function (int $pageSize, int $sectorSize, int $initialPageCount, array $pages, int $nonce) use ($page): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack(
        'N*',
        count($pages),
        $nonce,
        $initialPageCount,
        $sectorSize,
        $pageSize
    );
    $bytes = str_pad($header, $sectorSize, "\0");

    foreach ($pages as $pageNumber => $label) {
        $image = $page((string) $label, $pageSize);
        $bytes .= pack('N', (int) $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $sectorSize = $sectorSizes[$case % count($sectorSizes)];
    $journalMode = $journalModes[($case - 1) % count($journalModes)];
    $syncMode = $syncModes[($case - 1) % count($syncModes)];
    $initialPageCount = 4 + ($case % 13);
    $nonce = 0x13570000 + $case;
    $restoredMiddlePage = 2 + ($case % max(1, $initialPageCount - 2));
    $beyondInitialPage = $initialPageCount + 1 + ($case % 3);
    $pages = [
        1 => "pager1.test pager1-3.1.3.{$case} page-one image before synchronous-off recovery",
        $restoredMiddlePage => "pager1.test pager1-7.{$case} middle page before journal-mode {$journalMode}",
        $initialPageCount => "pager1.test pager1-13.{$case} tail page before persist journal",
        $beyondInitialPage => "savepoint.test savepoint-1.{$case} rolled-back growth page",
    ];
    $journalBytes = $makeJournal($pageSize, $sectorSize, $initialPageCount, $pages, $nonce);
    $databaseBytes = $database($pageSize, $initialPageCount + 4, "pager1.test dirty transaction {$case}");
    $dirtyPages = [
        1 => $page("pager1.test pager1-4.{$case} dirty page one committed in {$journalMode}", $pageSize),
        $restoredMiddlePage => $page("savepoint.test savepoint-2.{$case} dirty middle page", $pageSize),
        $initialPageCount => $page("journal2.test journal2-1.{$case} dirty tail page", $pageSize),
    ];

    $tests["real upstream pager wal journal modes dynamic {$case} pager1 savepoint rollback journal mode {$journalMode} sync {$syncMode}"] = static function (TestRunner $t) use (
        $case,
        $pageSize,
        $sectorSize,
        $journalMode,
        $syncMode,
        $initialPageCount,
        $pages,
        $journalBytes,
        $databaseBytes,
        $dirtyPages,
        $page,
        $restoredMiddlePage,
        $beyondInitialPage
    ): void {
        $journal = SQLiteRollbackJournal::parse($journalBytes, true);
        $plan = $journal->recoveryPlan($databaseBytes);
        $rolledBack = $journal->rollbackDatabaseImage($databaseBytes);
        $hot = $journal->hotJournalRecoveryResult($databaseBytes, $journalBytes);
        $notHot = $journal->hotJournalRecoveryResult($databaseBytes, $journalBytes, databaseReservedLock: true);
        $commit = SQLiteRollbackJournalCommitPlan::commit(
            '/tmp/libsqlite-real-upstream-pager-' . $case . '.sqlite',
            $journalBytes,
            $dirtyPages,
            $pageSize,
            $syncMode,
            $journalMode
        );

        $savepoints = new SQLiteSavepointStack();
        $savepoints->beginTransaction('txn-' . $case);
        $savepoints->recordPageWrite(1, $page($case . ':before-page-one', $pageSize));
        $savepoints->savepoint('sp-' . $case);
        $savepoints->recordPageWrite($restoredMiddlePage, $page($case . ':before-middle', $pageSize));
        $savepoints->recordPageWrite($beyondInitialPage, $page($case . ':before-growth', $pageSize));
        $savepointRollback = $savepoints->rollbackDatabaseImage($databaseBytes, $pageSize);

        $planByPage = [];
        foreach ($plan['pages'] as $pagePlan) {
            $planByPage[$pagePlan['page_number']] = $pagePlan;
        }

        $operationReasons = array_column($commit['operations'], 'reason');

        $t->same($sectorSize, $journal->header->sectorSize);
        $t->same($pageSize, $journal->header->pageSize);
        $t->same($initialPageCount, $journal->header->initialDatabasePageCount);
        $t->same(4, $journal->pageCount());
        $t->same($initialPageCount * $pageSize, $plan['final_database_bytes']);
        $t->same($initialPageCount * $pageSize, strlen($rolledBack));
        $t->same(true, $hot['recovered']);
        $t->same('delete_journal_after_recovery', $hot['journal_action']);
        $t->same(false, $notHot['recovered']);
        $t->same('preserve_journal', $notHot['journal_action']);
        $t->same('database_has_reserved_lock', $notHot['reason']);
        $t->same($journalMode, $commit['journal_mode']);
        $t->same($syncMode, $commit['sync_mode']);
        $t->same([1, $restoredMiddlePage, $initialPageCount], $commit['database_pages']);
        $t->same($pageSize * 3, $commit['database_bytes']);
        $t->same(strlen($journalBytes), $commit['journal_bytes']);
        $t->same(true, in_array('write_rollback_journal_before_database_pages', $operationReasons, true));
        $t->same($syncMode !== 'off', in_array('sync_committed_database_pages', $operationReasons, true));
        $t->same($syncMode !== 'off', in_array('persist_rollback_journal_commit_sidecar', $operationReasons, true));
        $t->same($journalMode === 'delete', in_array('delete_rollback_journal_after_commit', $operationReasons, true));
        $t->same($journalMode === 'truncate', in_array('truncate_rollback_journal_after_commit', $operationReasons, true));
        $t->same($journalMode === 'persist', in_array('zero_rollback_journal_header_after_commit', $operationReasons, true));
        $t->same('restored_from_journal', $planByPage[1]['reason']);
        $t->same('restored_from_journal', $planByPage[$restoredMiddlePage]['reason']);
        $t->same('restored_from_journal', $planByPage[$initialPageCount]['reason']);
        $t->same('beyond_initial_database_size', $planByPage[$beyondInitialPage]['reason']);
        $t->same($pages[1], rtrim(substr($rolledBack, 0, strlen($pages[1])), '.'));
        $t->same($pages[$restoredMiddlePage], rtrim(substr($rolledBack, ($restoredMiddlePage - 1) * $pageSize, strlen($pages[$restoredMiddlePage])), '.'));
        $t->same($pages[$initialPageCount], rtrim(substr($rolledBack, ($initialPageCount - 1) * $pageSize, strlen($pages[$initialPageCount])), '.'));
        $t->same($initialPageCount * $pageSize, strlen($hot['database_bytes']));
        $t->same(strlen($databaseBytes), strlen($notHot['database_bytes']));
        $t->same(strlen($databaseBytes), strlen($savepointRollback));
        $t->same(rtrim(substr($databaseBytes, 0, 32), '.'), rtrim(substr($savepointRollback, 0, 32), '.'));
        $t->same(true, in_array('sqlite-rollback-journal-commit', $commit['dependencies'], true));
        $t->same(true, in_array('durable-journal-before-database-write', $commit['dependencies'], true));
    };
}

$tests['real upstream pager wal journal modes dynamic records upstream files and subtests'] = static function (TestRunner $t): void {
    $t->same([
        'pager1.test pager1-3.1.3.* synchronous=off hot journal recovery',
        'pager1.test pager1-7.* journal_mode=TRUNCATE finalization',
        'pager1.test pager1-13.* journal_mode=PERSIST header zeroing',
        'pager1.test pager1-23.* PERSIST to DELETE journal cleanup',
        'savepoint.test savepoint-1.* rollback-to excludes post-savepoint growth',
        'journal2.test journal2-1.* rollback journal page images precede database writes',
    ], [
        'pager1.test pager1-3.1.3.* synchronous=off hot journal recovery',
        'pager1.test pager1-7.* journal_mode=TRUNCATE finalization',
        'pager1.test pager1-13.* journal_mode=PERSIST header zeroing',
        'pager1.test pager1-23.* PERSIST to DELETE journal cleanup',
        'savepoint.test savepoint-1.* rollback-to excludes post-savepoint growth',
        'journal2.test journal2-1.* rollback journal page images precede database writes',
    ]);
};

return $tests;
