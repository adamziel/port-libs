<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalStatementRecoveryPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$pageSize = 512;
$mainPath = '/wp-content/database/.ht.sqlite';
$metaPath = '/wp-content/database/wp_meta.sqlite';
$superPath = '/wp-content/database/wp-import-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$journal = static function (array $pages, int $initialPageCount, int $nonce) use ($pageSize): string {
    $bytes = str_pad(SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, 512, $pageSize), 512, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import');
$stack->recordPageImageWrite(1, $page('outer clean header'));
$stack->recordWalFrameWrite(1, 1);
$stack->savepoint('plugin-options');
$stack->beginStatementJournal('insert-transient');
$stack->recordStatementPageImageWrite('insert-transient', 4, $page('clean transient before failed statement'));
$stack->recordStatementWalFrameWrite('insert-transient', 2, 4, true);

$mainDirty = $page('dirty header') . $page('dirty siteurl') . $page('dirty plugins') . $page('dirty transient statement');
$metaDirty = $page('dirty meta header') . $page('dirty meta index');
$plan = SQLitePagerMasterJournalStatementRecoveryPlan::currentNext(
    $superPath,
    $mainPath . "-journal\n" . $metaPath . "-journal\n",
    [
        [
            'database_path' => $mainPath,
            'database_bytes' => $mainDirty,
            'journal_bytes' => $journal([
                1 => $page('clean header'),
                2 => $page('clean siteurl'),
                3 => $page('clean plugins'),
                4 => $page('clean transient journal'),
            ], 4, 0x75010011),
        ],
        [
            'database_path' => $metaPath,
            'database_bytes' => $metaDirty,
            'journal_bytes' => $journal([
                1 => $page('clean meta header'),
                2 => $page('clean meta index'),
            ], 2, 0x75010012),
        ],
    ],
    $pageSize,
    $stack,
    'insert-transient',
    'retry-transient',
    5,
    $page('clean retry before next statement'),
    true,
    $mainPath
);

$summary = [
    'scenario' => 'application-pager-master-journal-statement-recovery-current-next75',
    'status' => $plan['status'],
    'recoveredDatabases' => $plan['super_recovery']['recovered_count'],
    'statementRollbackPages' => $plan['statement_recovery']['rollback_restored_page_numbers'],
    'nextStatement' => $plan['statement_recovery']['next_statement'],
    'operationReasons' => array_column($plan['operations'], 'reason'),
    'applicationUse' => 'Recover copied main and attached Application SQLite databases from a master journal, then roll back the failed wp_options statement subjournal and prepare the next retry statement without ext/sqlite.',
];

if ($summary['status'] !== 'recovered' || $summary['statementRollbackPages'] !== [4]) {
    fwrite(STDERR, "application-pager-master-journal-statement-recovery-current-next75 self-test failed\n");
    exit(1);
}

fwrite(STDOUT, "application-pager-master-journal-statement-recovery-current-next75 self-test passed\n");
fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT) . "\n");
