<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteVfsSyncPlan;

$root = sys_get_temp_dir() . '/port-libsqlite-application-vfs-sync-apply';
$database = '/srv/www/wp-content/database/.ht.sqlite';
$journal = $database . '-journal';
$wal = $database . '-wal';
$directory = $root . dirname($database);

if (!is_dir($directory)) {
    mkdir($directory, 0777, true);
}
file_put_contents($root . $database, 'copied wp_options database bytes after commit');
file_put_contents($root . $journal, 'copied rollback journal bytes');
file_put_contents($root . $wal, 'copied wal frames after checkpoint');

$plans = SQLiteVfsSyncPlan::rollbackCommitSequence($database, 'full', true, false);
$plans[] = SQLiteVfsSyncPlan::forPath($wal, 'wal', 'normal', true);
$plans[] = SQLiteVfsSyncPlan::forPath('/srv/www/wp-content/database/archive.sqlite', 'database', 'full', false, false, true);

$applied = (new SQLiteVfsFileWriter($root))->applySyncPlans($plans, ['application-option-import']);

echo json_encode([
    'scenario' => 'application-vfs-sync-apply',
    'applicationUse' => 'Apply planned SQLite xSync barriers for copied wp_options rollback-journal, database, WAL, and directory handles through native PHP file handles without requiring ext/sqlite.',
    'status' => $applied['status'],
    'applied' => $applied['applied'],
    'skipped' => $applied['skipped'],
    'durableSyncs' => $applied['durable_syncs'],
    'directorySyncs' => $applied['directory_syncs'],
    'operations' => array_map(static fn (array $operation): array => [
        'op' => $operation['op'],
        'target' => $operation['target'],
        'mode' => $operation['mode'],
        'flags' => $operation['flags'],
        'flagNames' => $operation['flag_names'],
        'dataOnly' => $operation['data_only'],
        'directory' => $operation['directory'],
        'reason' => $operation['reason'],
    ], $applied['operations']),
    'dependencies' => $applied['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
