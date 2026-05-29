<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreelistRebalanceTruncateCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pageCount = 412;
$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$pages[1] = str_repeat("\0", $pageSize);
$pages[1] = substr_replace($pages[1], "SQLite format 3\0", 0, 16);
$pages[1] = substr_replace($pages[1], pack('n', $pageSize), 16, 2);
$pages[1][18] = "\x01";
$pages[1][19] = "\x01";
$pages[1][20] = "\x00";
$pages[1][21] = "\x40";
$pages[1][22] = "\x20";
$pages[1][23] = "\x20";
$pages[1] = substr_replace($pages[1], pack('N', $pageCount), 28, 4);
$pages[1] = substr_replace($pages[1], pack('N', 4), 52, 4);
$pages[1] = substr_replace($pages[1], pack('N', 1), 56, 4);

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber) use ($pageSize): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$deletedValues = ['no', '_transient_rebalance_truncate_next90', str_repeat('deleted-next90:', 220), 701];
$deleted = SQLiteIndexCell::encodeWithOverflowPages(SQLiteRecord::encode($deletedValues), 406, $pageSize);
$pages[3] = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_alpha_next90', 10]), leftChildPage: 4),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['z', 'tail_divider_next90', 900]), leftChildPage: 5),
], 6);
$pages[4] = SQLiteIndexLeafPage::assemble([
    $deleted['cell'],
    SQLiteIndexCell::encode(SQLiteRecord::encode(['no', '_transient_keep_next90', 20])),
]);
$pages[5] = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_beta_next90', 40])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_gamma_next90', 50])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_omega_next90', 60])),
]);
$pages[6] = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['z', 'tail_next90', 900])),
]);

$overflowPages = array_combine(range(406, 405 + count($deleted['overflowPages'])), $deleted['overflowPages']);
foreach ($overflowPages as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}
foreach ([3 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 4 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 5 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 6 => [SQLitePointerMapEntry::BTREE_PAGE, 3]] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parent);
}
foreach (array_keys($overflowPages) as $index => $pageNumber) {
    $putPointerMapEntry(
        $pages,
        $pageNumber,
        $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
        $index === 0 ? 4 : $pageNumber - 1,
    );
}

$overflowReader = static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): string {
    $payload = '';
    $pageNumber = $firstOverflowPage;
    while ($pageNumber !== 0 && strlen($payload) < $byteCount) {
        $page = $overflowPages[$pageNumber] ?? null;
        if ($page === null) {
            throw new InvalidArgumentException('Fixture overflow page is missing');
        }
        $pageNumber = unpack('N', substr($page, 0, 4))[1];
        $payload .= substr($page, 4);
    }

    return substr($payload, 0, $byteCount);
};
$overflowNumbers = static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): array {
    $pageNumbers = [];
    $pageNumber = $firstOverflowPage;
    $remaining = $byteCount;
    while ($pageNumber !== 0 && $remaining > 0) {
        $page = $overflowPages[$pageNumber] ?? null;
        if ($page === null) {
            throw new InvalidArgumentException('Fixture overflow page is missing');
        }
        $pageNumbers[] = $pageNumber;
        $pageNumber = unpack('N', substr($page, 0, 4))[1];
        $remaining -= 508;
    }

    return $pageNumbers;
};

$plan = SQLiteBTreeFreelistRebalanceTruncateCurrentSourceNextPlan::indexDeleteRebalanceAndTruncate(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    3,
    4,
    5,
    0,
    $deletedValues,
    $overflowNumbers,
    4,
    true,
    $overflowReader,
);

echo json_encode([
    'scenario' => 'wordpress-btree-rebalance-truncate-current-source-next90',
    'deleted_option_name' => $deletedValues[1],
    'obsolete_overflow_pages' => $plan->obsoleteOverflowPageNumbers(),
    'surviving_freelist_pages' => $plan->survivingFreelistPageNumbers(),
    'truncated_page_numbers' => $plan->truncatedPageNumbers(),
    'next_database_page_count' => $plan->nextDatabase->pageCount(),
    'next_freelist_page_count' => $plan->nextDatabase->header->freelistPageCount,
    'materialized_byte_length' => strlen($plan->materializedBytes()),
], JSON_PRETTY_PRINT) . "\n";
