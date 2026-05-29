<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityPointerMapCurrentSourceNext;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pageCount = 40;
$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$database = static function (bool $repaired) use ($pageSize, $pageCount, $putPointerMapEntry): string {
    $header = str_repeat("\0", $pageSize);
    $header = substr_replace($header, "SQLite format 3\0", 0, 16);
    $header = substr_replace($header, pack('n', $pageSize), 16, 2);
    $header[18] = "\x01";
    $header[19] = "\x01";
    $header = substr_replace($header, pack('N', $pageCount), 28, 4);
    $header = substr_replace($header, pack('N', 3), 52, 4);
    $header = substr_replace($header, pack('N', 1), 56, 4);

    $pointerMap = str_repeat("\0", $pageSize);
    $pointerMap = $putPointerMapEntry($pointerMap, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    for ($pageNumber = 4; $pageNumber <= $pageCount; $pageNumber++) {
        $parent = !$repaired && $pageNumber <= 6 ? 0 : 3;
        $pointerMap = $putPointerMapEntry($pointerMap, $pageNumber, SQLitePointerMapEntry::BTREE_PAGE, $parent);
    }

    $pages = [$header, $pointerMap];
    for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[] = str_repeat("\0", $pageSize);
    }

    return implode('', $pages);
};

$comparison = SQLitePragmaIntegrityPointerMapCurrentSourceNext::compare(
    $database(false),
    $database(true),
    'wp-options-current-autovacuum-copy',
    'wp-options-next-pointermap-rewrite',
);

echo json_encode([
    'status' => $comparison['status'],
    'ready' => $comparison['next']['ready'],
    'resolved_count' => $comparison['resolved_count'],
    'persisting_count' => $comparison['persisting_count'],
    'introduced_count' => $comparison['introduced_count'],
    'block_commit' => $comparison['must_block_commit'],
], JSON_PRETTY_PRINT) . PHP_EOL;
