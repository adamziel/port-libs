<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteSavepointStack.php';
require_once dirname(__DIR__) . '/src/SQLiteWalHeader.php';
require_once dirname(__DIR__) . '/src/SQLiteWalFrame.php';
require_once dirname(__DIR__) . '/src/SQLiteWal.php';
require_once dirname(__DIR__) . '/src/SQLiteWalFileWritePlan.php';
require_once dirname(__DIR__) . '/src/SQLiteWalRecoveryPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteWalAppendPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteWalSavepointCheckpointPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteRollbackJournalCommitPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteRollbackJournalHeader.php';
require_once dirname(__DIR__) . '/src/SQLiteRollbackJournalPage.php';
require_once dirname(__DIR__) . '/src/SQLiteRollbackJournal.php';
require_once dirname(__DIR__) . '/src/SQLitePagerHotJournalWalRecoveryPlan.php';
require_once dirname(__DIR__) . '/src/SQLitePagerHotJournalSuperCurrentNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLitePagerMasterJournalHotRollbackCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLitePagerMasterJournalStatementRecoveryPlan.php';
require_once dirname(__DIR__) . '/src/SQLitePagerStatementRecoveryPlan.php';
require_once dirname(__DIR__) . '/src/SQLitePagerCheckpointTransactionPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteLockCoordinator.php';
require_once dirname(__DIR__) . '/src/SQLiteBusyHandler.php';
require_once dirname(__DIR__) . '/src/SQLiteVfsFileWriter.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$makeWalBytes = static function () use ($pageSize, $page): string {
    $salt1 = 0x94949494;
    $salt2 = 0x14141414;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 94, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $append = static function (int $pageNumber, int $commit, string $image) use (&$bytes, &$seed, $salt1, $salt2): void {
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    };
    $append(2, 0, $page('wal retained autoload draft next94'));
    $append(2, 3, $page('wal retained autoload commit next94'));
    $append(3, 0, $page('wal discard option draft next94'));
    $append(3, 3, $page('wal discard option commit next94'));

    return $bytes;
};

$root = sys_get_temp_dir() . '/libsqlite-wordpress-savepoint-filehandle-next94-' . bin2hex(random_bytes(4));
$dir = $root . '/wp-content/database';
mkdir($dir, 0777, true);
$databasePath = 'wp-content/database/wp.sqlite';
$journals = [
    'outer-import' => 'wp-content/database/outer.stmt',
    'update-plugin-option' => 'wp-content/database/plugin.stmt',
    'insert-plugin-child' => 'wp-content/database/child.stmt',
];

file_put_contents($dir . '/wp.sqlite', $page('schema dirty next94') . $page('plugin dirty autoload next94') . $page('plugin child dirty next94'));
file_put_contents($dir . '/wp.sqlite-wal', $makeWalBytes());
file_put_contents($dir . '/outer.stmt', 'outer statement journal remains next94');
file_put_contents($dir . '/plugin.stmt', 'plugin statement journal is stale next94');
file_put_contents($dir . '/child.stmt', 'child statement journal is stale next94');

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import');
$stack->recordPageImageWrite(1, $page('before schema page next94'));
$stack->recordWalFrameWrite(1, 2);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-settings');
$stack->beginStatementJournal('update-plugin-option');
$stack->recordStatementPageImageWrite('update-plugin-option', 2, $page('before plugin autoload next94'));
$stack->recordPageImageWrite(2, $page('before plugin autoload next94'));
$stack->recordStatementWalFrameWrite('update-plugin-option', 3, 2);
$stack->savepoint('plugin-child');
$stack->beginStatementJournal('insert-plugin-child');
$stack->recordStatementPageImageWrite('insert-plugin-child', 3, $page('before plugin child next94'));
$stack->recordPageImageWrite(3, $page('before plugin child next94'));
$stack->recordStatementWalFrameWrite('insert-plugin-child', 4, 3, true);

try {
    $writer = new SQLiteVfsFileWriter($root);
    $result = $writer->applySavepointRollbackFromCurrentSourceNext94(
        $stack,
        'plugin-settings',
        $databasePath,
        $pageSize,
        $journals
    );

    $summary = [
        'status' => 'pager_savepoint_journal_filehandle_current_source_next94',
        'applied' => $result['applied'],
        'filesDeleted' => $result['files_deleted'],
        'databaseBytesAfter' => $result['current_source']['database_bytes_after'],
        'walBytesAfter' => $result['current_source']['wal_bytes_after'],
        'restoredPages' => $result['database_image']['restored_page_numbers'],
        'discardedStatementJournals' => $result['statement_journals']['discarded'],
        'outerStatementJournalPreserved' => is_file($dir . '/outer.stmt'),
        'pluginStatementJournalDeleted' => !is_file($dir . '/plugin.stmt'),
        'childStatementJournalDeleted' => !is_file($dir . '/child.stmt'),
        'wordpressUse' => 'Rollback a failed copied wp_options plugin import savepoint from current VFS file handles, restore database pages, truncate the WAL prefix, and delete only stale statement journals while preserving the outer import journal.',
    ];

    if (($argv[1] ?? '') === '--self-test') {
        assert($summary['filesDeleted'] === 2);
        assert($summary['restoredPages'] === [2, 3]);
        assert($summary['outerStatementJournalPreserved'] === true);
        assert($summary['pluginStatementJournalDeleted'] === true);
        assert($summary['childStatementJournalDeleted'] === true);
    }

    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    foreach (['wp.sqlite-wal', 'wp.sqlite', 'outer.stmt', 'plugin.stmt', 'child.stmt'] as $file) {
        $path = $dir . '/' . $file;
        if (is_file($path)) {
            unlink($path);
        }
    }
    if (is_dir($dir)) {
        rmdir($dir);
    }
    if (is_dir($root . '/wp-content')) {
        rmdir($root . '/wp-content');
    }
    if (is_dir($root)) {
        rmdir($root);
    }
}
