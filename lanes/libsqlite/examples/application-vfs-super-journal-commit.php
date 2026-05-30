<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSuperJournalCommitPlan;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$root = sys_get_temp_dir() . '/port-libsqlite-application-super-journal-' . bin2hex(random_bytes(4));
$mainPath = '/srv/www/wp-content/database/main.sqlite';
$metaPath = '/srv/www/wp-content/database/site-meta.sqlite';
$superJournalPath = '/srv/www/wp-content/database/main.sqlite-mjWp';
$mainJournal = str_pad('main rollback journal with copied wp_options preimages', $pageSize, "\0");
$metaJournal = str_pad('site meta rollback journal with copied wp_sitemeta preimages', $pageSize, "\0");
$mainSchema = str_pad('main schema after network option import', $pageSize, "\0");
$mainOptions = str_pad('wp_options plugin setting after attached commit', $pageSize, "\0");
$metaSchema = str_pad('site meta schema after network option import', $pageSize, "\0");
$siteMeta = str_pad('wp_sitemeta network transient after attached commit', $pageSize, "\0");

$commits = [
    [
        'database_path' => $mainPath,
        'journal_bytes' => $mainJournal,
        'database_pages' => [1 => $mainSchema, 2 => $mainOptions],
    ],
    [
        'database_path' => $metaPath,
        'journal_bytes' => $metaJournal,
        'database_pages' => [1 => $metaSchema, 3 => $siteMeta],
    ],
];

$plan = SQLiteSuperJournalCommitPlan::commit($superJournalPath, $commits, $pageSize, 'full', 'delete');
$applied = (new SQLiteVfsFileWriter($root))->applySuperJournalCommit($superJournalPath, $commits, $pageSize, 'full', 'delete');

echo json_encode([
    'scenario' => 'application-vfs-super-journal-commit',
    'applicationUse' => 'Apply SQLite super-journal commit ordering for copied Application multisite-style attached databases: the master journal lists each rollback journal, all attached journals and database pages are synced, then the super-journal deletion atomically commits the group without ext/sqlite.',
    'root' => $root,
    'superJournalPath' => $superJournalPath,
    'localSuperJournalExistsAfterCommit' => is_file($root . $superJournalPath),
    'localMainJournalExistsAfterCommit' => is_file($root . $mainPath . '-journal'),
    'localMetaJournalExistsAfterCommit' => is_file($root . $metaPath . '-journal'),
    'localMainDatabaseBytes' => filesize($root . $mainPath),
    'localMetaDatabaseBytes' => filesize($root . $metaPath),
    'plan' => [
        'databaseCount' => $plan['database_count'],
        'journalPaths' => $plan['journal_paths'],
        'databasePages' => $plan['database_pages'],
        'operationReasons' => array_column($plan['operations'], 'reason'),
        'dependencies' => $plan['dependencies'],
    ],
    'applied' => [
        'status' => $applied['status'],
        'operations' => $applied['applied'],
        'bytesWritten' => $applied['bytes_written'],
        'durableSyncs' => $applied['durable_syncs'],
        'directorySyncs' => $applied['directory_syncs'],
        'filesDeleted' => $applied['files_deleted'],
        'dependencies' => $applied['dependencies'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
