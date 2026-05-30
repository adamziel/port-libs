<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerStatementRecoveryPlan.php';
require_once __DIR__ . '/../src/SQLiteVfsFileWriter.php';

use PortLibs\LibSqlite\SQLiteVfsFileWriter;

$root = sys_get_temp_dir() . '/port-libsqlite-wp-master-stmt-current-source-' . bin2hex(random_bytes(4));
$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$mainPath = '/srv/www/wp-content/database/.ht.sqlite';
$networkPath = '/srv/www/wp-content/database/site-meta.sqlite';
$stalePath = '/srv/www/wp-content/database/stale-plugin.sqlite';
$masterPath = '/srv/www/wp-content/database/.ht.sqlite-mj119';
$masterBytes = $mainPath . "-journal\n" . $networkPath . "-journal\n" . $stalePath . "-journal\n";

mkdir(dirname($root . $masterPath), 0777, true);
file_put_contents($root . $masterPath, $masterBytes);
file_put_contents($root . $mainPath, $page('current-source wp schema current') . $page('current-source wp_options dirty active_plugins'));
file_put_contents($root . $networkPath, $page('current-source network schema current') . $page('current-source site option dirty'));
file_put_contents($root . $stalePath, $page('current-source stale schema current') . $page('current-source stale dirty option'));
file_put_contents($root . $mainPath . '-journal', 'main outer rollback journal');
file_put_contents($root . $networkPath . '-journal', 'network outer rollback journal');
file_put_contents($root . $stalePath . '-journal', 'stale outer rollback journal');
file_put_contents($root . $mainPath . '-stmt-journal', 'main statement journal exists');
file_put_contents($root . $networkPath . '-stmt-journal', 'network statement journal exists');

$result = (new SQLiteVfsFileWriter($root))->applyMasterJournalStatementPageRecoveryFromCurrentSource($masterPath, [
    [
        'database_path' => $mainPath,
        'statement_journal_path' => $mainPath . '-stmt-journal',
        'statement_pages' => [2 => $page('current-source wp_options before failed active_plugins')],
    ],
    [
        'database_path' => $networkPath,
        'statement_journal_path' => $networkPath . '-stmt-journal',
        'statement_pages' => [2 => $page('current-source site option before failed network update')],
    ],
    [
        'database_path' => $stalePath,
        'statement_journal_path' => $stalePath . '-stmt-journal',
        'statement_pages' => [2 => $page('current-source stale before image ignored because journal missing')],
    ],
], $pageSize);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-statement-recovery-current-source',
    'wordpressUse' => 'Recover failed statement pages for copied WordPress option databases only when the current statement journal sidecar still exists; stale preimage input for a missing sidecar is skipped under the master-journal transaction.',
    'status' => $result['status'],
    'recovered' => $result['recovery']['recovered_database_count'],
    'skipped' => $result['recovery']['skipped_database_count'],
    'missingReason' => $result['recovery']['databases'][$stalePath]['reason'],
    'mainStatementJournalExists' => is_file($root . $mainPath . '-stmt-journal'),
    'networkStatementJournalExists' => is_file($root . $networkPath . '-stmt-journal'),
    'staleStatementJournalExists' => is_file($root . $stalePath . '-stmt-journal'),
    'staleDatabaseKeptDirty' => str_contains((string) file_get_contents($root . $stalePath), 'current-source stale dirty option'),
];

if (in_array('--self-test', $argv, true)) {
    $ok = $summary['status'] === 'applied'
        && $summary['recovered'] === 2
        && $summary['skipped'] === 1
        && $summary['missingReason'] === 'missing_statement_journal'
        && $summary['mainStatementJournalExists'] === false
        && $summary['networkStatementJournalExists'] === false
        && $summary['staleStatementJournalExists'] === false
        && $summary['staleDatabaseKeptDirty'] === true;

    if (!$ok) {
        fwrite(STDERR, json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL);
        exit(1);
    }

    echo "wordpress-pager-master-journal-statement-recovery-current-source self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
