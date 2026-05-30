<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePagerCacheSavepointFileHandlePlan;
use PortLibs\LibSqlite\SQLiteVfsFileHandle;

$pageSize = 64;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$root = sys_get_temp_dir() . '/port-libsqlite-application-pager-cache-savepoint-' . bin2hex(random_bytes(4));
mkdir($root, 0777, true);
$path = 'wp-content/database/.ht.sqlite';
$localPath = $root . '/' . $path;
mkdir(dirname($localPath), 0777, true);
file_put_contents($localPath, $page('wp-options-root') . $page('autoload-index-before') . $page('plugin-row-before'));

try {
    $plan = SQLitePagerCacheSavepointFileHandlePlan::currentNext(
        $root,
        $path,
        $pageSize,
        'plugin_settings',
        [
            2 => $page('autoload-index-dirty'),
            3 => $page('plugin-row-dirty'),
        ],
        [
            2 => $page('autoload-index-retry'),
            4 => $page('plugin-row-next'),
        ],
    );

    $bytes = (new SQLiteVfsFileHandle($root, $path))->readAt(0, $pageSize * 4)['zero_filled_data'];
    $summary = [
        'scenario' => 'application-pager-cache-savepoint-file-handle-current-next76',
        'status' => $plan['status'],
        'current_pages' => $plan['current']['written_page_numbers'],
        'rollback_pages' => $plan['rollback']['restored_page_numbers'],
        'next_pages' => $plan['next']['written_page_numbers'],
        'final_page_2' => rtrim(substr($bytes, $pageSize, $pageSize), '.'),
        'final_page_3' => rtrim(substr($bytes, $pageSize * 2, $pageSize), '.'),
        'final_page_4' => rtrim(substr($bytes, $pageSize * 3, $pageSize), '.'),
        'applicationUse' => 'Apply a copied wp_options plugin-settings import through native PHP VFS file handles where the current dirty cache pages are rolled back to a retained savepoint before the next retry captures fresh before-images and appends the follow-up page, without ext/sqlite.',
    ];

    if (
        $summary['final_page_2'] !== 'autoload-index-retry'
        || $summary['final_page_3'] !== 'plugin-row-before'
        || $summary['final_page_4'] !== 'plugin-row-next'
    ) {
        fwrite(STDERR, "application-pager-cache-savepoint-file-handle-current-next76 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT) . "\n");
} finally {
    foreach (array_reverse(glob($root . '/{*,*/*,*/*/*}', GLOB_BRACE) ?: []) as $file) {
        if (is_file($file)) {
            unlink($file);
        } elseif (is_dir($file)) {
            @rmdir($file);
        }
    }
    if (is_dir($root)) {
        @rmdir($root);
    }
}
