<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan;
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

$plan = SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan::fromCurrentSourceOverflowChains(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [
        [
            'source' => 'copied-wp-options-autoload-table-tail-overflow-next145',
            'first_page' => 306,
            'overflow_payload_bytes' => 1524,
        ],
        [
            'source' => 'copied-wp-options-option-name-index-tail-overflow-next145',
            'first_page' => 309,
            'overflow_payload_bytes' => 1016,
        ],
    ],
    [
        [
            'source' => 'copied-wp-options-autoload-table-tail-overflow-next145',
            'obsolete_overflow_page_numbers' => [306, 307, 308],
            'rowids' => [14501],
        ],
        [
            'source' => 'copied-wp-options-option-name-index-tail-overflow-next145',
            'obsolete_overflow_page_numbers' => [309, 310],
            'record_values' => [['_transient_timeout_next145', 14501]],
        ],
    ],
    3,
    str_repeat('next145-wp-option-overflow-vacuum-pointermap-', 42),
    42,
    true,
);

$summary = [
    'scenario' => 'application-btree-overflow-vacuum-pointermap-current-source-next145',
    'applicationUse' => 'After copied wp_options transient cleanup frees tail overflow pages, incremental vacuum truncates the tail and the next oversized option rewrite reuses only surviving free pages before appending fresh overflow pages with pointer-map ownership.',
    'current_source_pages' => array_column($plan->currentSourceRows(), 'page_number'),
    'released_overflow_pages' => $plan->releasedOverflowPages(),
    'vacuum_truncated_pages' => $plan->vacuumPlan->truncatedFreedPointerMapPages(),
    'vacuum_surviving_pages' => $plan->vacuumPlan->survivingFreedPointerMapPages(),
    'allocated_overflow_pages' => $plan->allocatedOverflowPages(),
    'reused_surviving_overflow_pages' => $plan->reusedSurvivingOverflowPages(),
    'appended_overflow_pages' => $plan->appendedOverflowPages(),
    'truncated_overflow_pages_not_reused' => $plan->truncatedOverflowPagesNotReused(),
    'final_page_count' => $plan->databaseAfterAllocation->pageCount(),
    'final_freelist_pages' => $plan->databaseAfterAllocation->freelistPageNumbers(),
    'transition_rows' => $plan->transitionRows(),
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['current_source_pages'] === [306, 307, 308, 309, 310]);
    assert($summary['vacuum_truncated_pages'] === [308, 309, 310]);
    assert($summary['allocated_overflow_pages'] === [307, 306, 308, 309]);
    assert($summary['reused_surviving_overflow_pages'] === [307, 306]);
    assert($summary['appended_overflow_pages'] === [308, 309]);
    assert($summary['final_page_count'] === 309);
    echo "application-btree-overflow-vacuum-pointermap-current-source-next145 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
