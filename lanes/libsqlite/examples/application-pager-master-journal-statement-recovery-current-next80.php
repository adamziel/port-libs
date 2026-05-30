<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerStatementRecoveryPlan.php';
require_once __DIR__ . '/../src/SQLiteVfsFileWriter.php';

use PortLibs\LibSqlite\SQLiteVfsFileWriter;

$root = sys_get_temp_dir() . '/port-libsqlite-wp-master-stmt-' . bin2hex(random_bytes(4));
$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$mainPath = '/srv/www/wp-content/database/.ht.sqlite';
$sitePath = '/srv/www/wp-content/database/site-meta.sqlite';
$masterPath = '/srv/www/wp-content/database/.ht.sqlite-mj80';
$masterBytes = $mainPath . "-journal\n" . $sitePath . "-journal\n";

$mainCurrent = $page('schema current during failed plugin statement')
    . $page('wp_options dirty plugin_settings row')
    . $page('wp_options dirty autoload index');
$siteCurrent = $page('sitemeta schema current during failed statement')
    . $page('wp_sitemeta dirty network option row');
$mainBefore = $page('wp_options before failed plugin statement');
$mainIndexBefore = $page('wp_options index before failed statement');
$siteBefore = $page('wp_sitemeta before failed network option');

mkdir(dirname($root . $masterPath), 0777, true);
file_put_contents($root . $masterPath, $masterBytes);
file_put_contents($root . $mainPath . '-journal', 'main outer rollback journal remains active');
file_put_contents($root . $mainPath . '-stmt-journal', 'main failed statement journal');
file_put_contents($root . $sitePath . '-journal', 'site outer rollback journal remains active');
file_put_contents($root . $sitePath . '-stmt-journal', 'site failed statement journal');

$databases = [
    [
        'database_path' => $mainPath,
        'database_bytes' => $mainCurrent,
        'statement_journal_path' => $mainPath . '-stmt-journal',
        'statement_pages' => [2 => $mainBefore, 3 => $mainIndexBefore],
    ],
    [
        'database_path' => $sitePath,
        'database_bytes' => $siteCurrent,
        'statement_journal_path' => $sitePath . '-stmt-journal',
        'statement_pages' => [2 => $siteBefore],
    ],
];

$applied = (new SQLiteVfsFileWriter($root))->applyMasterJournalStatementPageRecovery(
    $masterPath,
    $masterBytes,
    $databases,
    $pageSize
);

$mainBytes = file_get_contents($root . $mainPath);
$siteBytes = file_get_contents($root . $sitePath);
$summary = [
    'scenario' => 'application-pager-master-journal-statement-recovery-current-next80',
    'applicationUse' => 'Roll back failed plugin statement pages across copied wp_options and multisite metadata databases while preserving the master journal and outer rollback journals for the active attached transaction.',
    'status' => $applied['status'],
    'recoveredDatabases' => $applied['recovery']['recovered_database_count'],
    'deletedStatementJournals' => $applied['files_deleted'],
    'mainCurrentPage2' => $applied['recovery']['current_page_prefixes'][$mainPath][2],
    'mainNextPage2' => rtrim(substr($mainBytes, $pageSize, 48), "\0"),
    'siteNextPage2' => rtrim(substr($siteBytes, $pageSize, 48), "\0"),
    'masterJournalPreserved' => is_file($root . $masterPath),
    'outerJournalsPreserved' => is_file($root . $mainPath . '-journal') && is_file($root . $sitePath . '-journal'),
    'statementJournalsDeleted' => !is_file($root . $mainPath . '-stmt-journal') && !is_file($root . $sitePath . '-stmt-journal'),
    'dependencies' => $applied['dependencies'],
];

if (
    $summary['status'] !== 'applied'
    || $summary['recoveredDatabases'] !== 2
    || $summary['mainNextPage2'] !== 'wp_options before failed plugin statement'
    || !$summary['masterJournalPreserved']
    || !$summary['outerJournalsPreserved']
    || !$summary['statementJournalsDeleted']
) {
    fwrite(STDERR, "application-pager-master-journal-statement-recovery-current-next80 self-test failed\n");
    exit(1);
}

fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT) . "\n");
