<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePageCache;

$firstPage = str_repeat("\0", 512);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', 512), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage = substr_replace($firstPage, pack('N', 3), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$path = tempnam(sys_get_temp_dir(), 'wp-sqlite-page-cache-');
if ($path === false) {
    throw new RuntimeException('Unable to create copied Application SQLite page-cache fixture');
}

$wpOptionsRoot = str_pad('wp_options root page preview', 512, "\0");
$wpOptionsIndex = str_pad('wp_options option_name index preview', 512, "\0");
file_put_contents($path, $firstPage . $wpOptionsRoot . $wpOptionsIndex);

$uri = 'file:' . str_replace('%2F', '/', rawurlencode($path)) . '?mode=ro&immutable=1&vfs=unix-none';
$cache = SQLitePageCache::open($uri);
$summary = $cache->summary();
$pages = $cache->pages([2, 3]);
@unlink($path);

echo json_encode([
    'scenario' => 'application-page-cache-preflight',
    'applicationUse' => 'Read bounded page-size-aligned SQLite pages for copied Application databases after open and header admission, without ext/sqlite or a shared VFS implementation.',
    'database' => [
        'status' => $summary['status'],
        'pageSize' => $summary['page_size'],
        'fileSize' => $summary['file_size'],
        'availablePages' => $summary['available_pages'],
        'declaredPages' => $summary['declared_pages'],
        'completeDeclaredPages' => $summary['complete_declared_pages'],
        'readOnly' => $summary['read_only'],
        'vfs' => $summary['vfs'],
        'cachedPages' => $cache->cachedPageCount(),
        'pageTwoPreview' => rtrim(substr($pages[2], 0, 32), "\0"),
        'pageThreePreview' => rtrim(substr($pages[3], 0, 40), "\0"),
        'dependencies' => $summary['dependencies'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
