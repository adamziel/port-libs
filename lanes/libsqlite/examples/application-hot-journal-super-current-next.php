<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalSuperCurrentNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$sectorSize = 512;
$mainPath = '/srv/www/wp-content/database/main.sqlite';
$metaPath = '/srv/www/wp-content/database/site-meta.sqlite';
$superPath = '/srv/www/wp-content/database/main.sqlite-mj70';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$journalBytes = static function (array $pages, int $initialPageCount) use ($sectorSize, $pageSize): string {
    $nonce = 0x70000000 + $initialPageCount;
    $bytes = str_pad(SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize), $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$databases = [
    [
        'database_path' => $mainPath,
        'database_bytes' => $page('dirty main schema during copied import') . $page('dirty wp_options during copied import') . $page('dirty transient overflow'),
        'journal_bytes' => $journalBytes([
            1 => $page('clean main schema before copied import'),
            2 => $page('clean wp_options before copied import'),
        ], 2),
    ],
    [
        'database_path' => $metaPath,
        'database_bytes' => $page('dirty site-meta schema during copied import') . $page('dirty wp_sitemeta during copied import'),
        'journal_bytes' => $journalBytes([
            1 => $page('clean site-meta schema before copied import'),
            2 => $page('clean wp_sitemeta before copied import'),
        ], 2),
    ],
];
$superBytes = $mainPath . "-journal\n" . $metaPath . "-journal\n";
$root = sys_get_temp_dir() . '/port-libsqlite-application-hot-super-' . bin2hex(random_bytes(4));
$plan = SQLitePagerHotJournalSuperCurrentNextPlan::currentNext($superPath, $superBytes, $databases, $pageSize);
$applied = (new SQLiteVfsFileWriter($root))->applyHotJournalSuperRecovery($superPath, $superBytes, $databases, $pageSize);

echo json_encode([
    'scenario' => 'application-hot-journal-super-current-next',
    'applicationUse' => 'Recover copied Application attached-database images after an interrupted rollback-journal transaction only when the super-journal is present, restoring clean wp_options/wp_sitemeta pages and deleting the attached hot journals as one recovery boundary without ext/sqlite.',
    'root' => $root,
    'status' => $plan['status'],
    'recoveredCount' => $plan['recovered_count'],
    'blockedCount' => $plan['blocked_count'],
    'currentMainPagePrefix' => $plan['current_page_summaries'][$mainPath][0]['prefix'],
    'nextMainPagePrefix' => $plan['next_page_summaries'][$mainPath][0]['prefix'],
    'nextMainBytes' => strlen($plan['next_databases'][$mainPath]),
    'operationReasons' => array_column($plan['operations'], 'reason'),
    'applied' => [
        'status' => $applied['status'],
        'operations' => $applied['applied'],
        'bytesWritten' => $applied['bytes_written'],
        'bytesTruncated' => $applied['bytes_truncated'],
        'filesDeleted' => $applied['files_deleted'],
        'durableSyncs' => $applied['durable_syncs'],
        'directorySyncs' => $applied['directory_syncs'],
        'dependencies' => $applied['dependencies'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
