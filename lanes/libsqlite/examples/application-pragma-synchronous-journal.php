<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaJournalState;

$pragma = new SQLitePragmaJournalState([
    'main' => ['synchronous' => 'full', 'journal_mode' => 'delete'],
    'temp' => ['temporary' => true, 'synchronous' => 'normal'],
]);

$journal = $pragma->execute('PRAGMA journal_mode=WAL');
$sync = $pragma->execute('PRAGMA synchronous=NORMAL');
$tempJournal = $pragma->execute('PRAGMA temp.journal_mode=WAL');

$report = [
    'scenario' => 'copied wp_options import pragma preflight',
    'main_journal_mode' => $journal['effective'],
    'main_synchronous' => $sync['effective'],
    'temp_journal_mode' => $tempJournal['effective'],
    'temp_reason' => $tempJournal['reason'],
    'dependencies' => array_values(array_unique(array_merge($journal['dependencies'], $sync['dependencies']))),
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
