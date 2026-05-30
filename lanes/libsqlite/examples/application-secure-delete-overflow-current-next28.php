<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteOverflowFreelistReleasePlan;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pageCount = 260;
$firstTrunk = 8;
$existingLeaves = range(130, 249);
$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', $pageCount), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', $firstTrunk), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 1 + count($existingLeaves)), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);
$pages[1] = $firstPage;
$pages[$firstTrunk] = SQLiteFreelistTrunkPage::assemble(null, $existingLeaves, $pageSize);

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber) use ($pageSize): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $offset = 5 * ($pageNumber - $pointerMapPage - 1);
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        $offset,
        5,
    );
};

foreach ($existingLeaves as $pageNumber) {
    $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
}

$chains = [
    [
        'source' => 'wp_options-table-current-overflow',
        'first_page' => 20,
        'pages' => [20, 22, 106],
        'payload' => str_repeat('table-current-next28:', 60),
        'rowids' => [41],
    ],
    [
        'source' => 'wp_options-index-current-overflow',
        'first_page' => 107,
        'pages' => [107, 21],
        'payload' => str_repeat('index-current-next28:', 35),
        'record_values' => [['_transient_current_next28', 41]],
    ],
];

foreach ($chains as $chain) {
    foreach (SQLiteOverflowPage::encodeChainAtPages($chain['payload'], $chain['pages'], $pageSize) as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }
    foreach ($chain['pages'] as $index => $pageNumber) {
        $type = $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE;
        $parent = $index === 0 ? 4 : $chain['pages'][$index - 1];
        $putPointerMapEntry($pages, $pageNumber, $type, $parent);
    }
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$release = SQLiteOverflowFreelistReleasePlan::fromOverflowChains(
    $database,
    array_map(
        static fn (array $chain): array => array_filter([
            'source' => $chain['source'],
            'first_page' => $chain['first_page'],
            'overflow_payload_bytes' => strlen($chain['payload']),
            'rowids' => $chain['rowids'] ?? null,
            'record_values' => $chain['record_values'] ?? null,
        ], static fn (mixed $value): bool => $value !== null),
        $chains,
    ),
    true,
);

$postPages = $pages;
foreach ($release->freePlan->pageImages() as $pageNumber => $page) {
    $postPages[$pageNumber] = $page;
}
$postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));

echo json_encode([
    'applicationUse' => 'Audit copied wp_options overflow current/next links before secure-delete release, then prove released overflow pages are freelist-owned and scrubbed without ext/sqlite.',
    'tableCurrentNextLinks' => SQLiteOverflowPage::chainLinksFromDatabase($database, 20, strlen($chains[0]['payload'])),
    'indexCurrentNextLinks' => SQLiteOverflowPage::chainLinksFromDatabase($database, 107, strlen($chains[1]['payload'])),
    'releasedOverflowPages' => $release->releasedOverflowPages,
    'secureDeleteClearedPages' => $release->freePlan->clearedPageNumbers,
    'freelistAfter' => $postDatabase->freelistPageNumbers(),
    'pointerMapTypes' => array_map(
        static fn (int $pageNumber): string => $postDatabase->pointerMapEntryForPage($pageNumber)->typeName(),
        $release->releasedOverflowPages,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
