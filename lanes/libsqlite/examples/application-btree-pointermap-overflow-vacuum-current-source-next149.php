<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$pageSize = 512;
$pageCount = 310;
$releasedPages = [306, 307, 308, 309, 310];

$makeFirstPage = static function (int $pageSize, int $pageCount): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

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

$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$pages[1] = $makeFirstPage($pageSize, $pageCount);
$putPointerMapEntry($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry($pages, 42, SQLitePointerMapEntry::BTREE_PAGE, 4);

foreach ($releasedPages as $index => $pageNumber) {
    $first = $index === 0 || $index === 3;
    $parent = $first ? ($index === 0 ? 42 : 4) : $releasedPages[$index - 1];
    $putPointerMapEntry(
        $pages,
        $pageNumber,
        $first ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
        $parent,
    );
    $next = in_array($pageNumber, [306, 307, 309], true) ? $pageNumber + 1 : 0;
    $pages[$pageNumber] = pack('N', $next) . str_repeat(chr(65 + $index), $pageSize - 4);
}

$plan = SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan::fromCurrentSourceOverflowChains(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [
        [
            'source' => 'copied-wp-options-autoload-table-tail-overflow-next149',
            'first_page' => 306,
            'overflow_payload_bytes' => 1524,
        ],
        [
            'source' => 'copied-wp-options-option-name-index-tail-overflow-next149',
            'first_page' => 309,
            'overflow_payload_bytes' => 1016,
        ],
    ],
    [
        [
            'source' => 'copied-wp-options-autoload-table-tail-overflow-next149',
            'obsolete_overflow_page_numbers' => [306, 307, 308],
            'rowids' => [14901],
        ],
        [
            'source' => 'copied-wp-options-option-name-index-tail-overflow-next149',
            'obsolete_overflow_page_numbers' => [309, 310],
            'record_values' => [['_transient_timeout_next149', 14901]],
        ],
    ],
    3,
    str_repeat('next149-wp-option-overflow-vacuum-pointermap-', 24),
    42,
    true,
);

$summary = [
    'scenario' => 'application-btree-pointermap-overflow-vacuum-current-source-next149',
    'applicationUse' => 'Copied wp_options cleanup can truncate tail overflow pages during incremental vacuum; a later option rewrite must reuse only surviving current-source pages and reject truncated page numbers for pointer-map ownership.',
    'released_overflow_pages' => $plan->basePlan->releasedOverflowPages(),
    'vacuum_truncated_pages' => $plan->basePlan->truncatedPageNumbers(),
    'allocated_overflow_pages' => $plan->basePlan->allocatedOverflowPages(),
    'current_source_pages_reused_after_vacuum' => $plan->currentSourcePagesReusedAfterVacuum(),
    'truncated_current_source_pages_rejected_for_reuse' => $plan->truncatedCurrentSourcePagesRejectedForReuse(),
    'final_page_count' => $plan->basePlan->databaseAfterAllocation->pageCount(),
    'vacuum_rows' => $plan->vacuumRows(),
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['allocated_overflow_pages'] === [307, 306, 308]);
    assert($summary['current_source_pages_reused_after_vacuum'] === [306, 307, 308]);
    assert($summary['truncated_current_source_pages_rejected_for_reuse'] === [309, 310]);
    assert($summary['final_page_count'] === 308);
    echo "application-btree-pointermap-overflow-vacuum-current-source-next149 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
