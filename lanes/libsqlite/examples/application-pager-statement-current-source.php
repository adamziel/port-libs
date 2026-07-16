<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsFileWriter;

$pageSize = 512;
$mainPath = '/wp-content/database/.ht.sqlite';
$metaPath = '/wp-content/database/site-meta.sqlite';
$masterPath = '/wp-content/database/.ht.sqlite-mj84';
$root = sys_get_temp_dir() . '/port-libsqlite-wp-stmt-source84-' . bin2hex(random_bytes(4));
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");

mkdir(dirname($root . $mainPath), 0777, true);
file_put_contents(
    $root . $mainPath,
    $page('main current sqlite header')
    . $page('dirty wp_options plugin import row')
    . $page('dirty wp_options autoload index')
);
file_put_contents(
    $root . $metaPath,
    $page('meta current sqlite header')
    . $page('dirty wp_sitemeta plugin import row')
);
file_put_contents($root . $masterPath, $mainPath . "-journal\n" . $metaPath . "-journal\n");
file_put_contents($root . $mainPath . '-journal', 'main outer rollback journal');
file_put_contents($root . $mainPath . '-stmt-journal', 'main failed statement journal');
file_put_contents($root . $metaPath . '-journal', 'meta outer rollback journal');
file_put_contents($root . $metaPath . '-stmt-journal', 'meta failed statement journal');

$applied = (new SQLiteVfsFileWriter($root))->applyMasterJournalStatementPageRecoveryFromCurrentSource(
    $masterPath,
    [
        [
            'database_path' => $mainPath,
            'statement_pages' => [
                2 => $page('before failed wp_options plugin import row'),
                3 => $page('before failed wp_options autoload index'),
            ],
        ],
        [
            'database_path' => $metaPath,
            'statement_pages' => [
                2 => $page('before failed wp_sitemeta plugin import row'),
            ],
        ],
    ],
    $pageSize
);

$summary = [
    'scenario' => 'application pager statement journal current-source recovery',
    'applicationUse' => 'Recover failed copied wp_options/wp_sitemeta statement writes from the database bytes currently on disk, deleting only recovered statement journals while preserving outer rollback and master journals.',
    'status' => $applied['status'],
    'applied' => $applied['applied'],
    'currentSourceBytes' => $applied['current_source']['database_bytes'],
    'recoveredDatabases' => $applied['recovery']['recovered_database_count'],
    'deletedStatementJournals' => $applied['files_deleted'],
    'mainRecovered' => str_contains((string) file_get_contents($root . $mainPath), 'before failed wp_options plugin import row'),
    'metaRecovered' => str_contains((string) file_get_contents($root . $metaPath), 'before failed wp_sitemeta plugin import row'),
    'outerJournalsPreserved' => is_file($root . $mainPath . '-journal') && is_file($root . $metaPath . '-journal'),
    'statementJournalsDeleted' => !is_file($root . $mainPath . '-stmt-journal') && !is_file($root . $metaPath . '-stmt-journal'),
    'dependencies' => $applied['dependencies'],
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (
    $summary['status'] !== 'applied'
    || $summary['applied'] !== 7
    || !$summary['mainRecovered']
    || !$summary['metaRecovered']
    || !$summary['outerJournalsPreserved']
    || !$summary['statementJournalsDeleted']
) {
    fwrite(STDERR, "application pager statement journal current-source smoke failed\n");
    exit(1);
}
