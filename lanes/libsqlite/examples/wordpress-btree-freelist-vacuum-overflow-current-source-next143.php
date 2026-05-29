<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeFreelistVacuumOverflowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$pageSize = 512;
$pageCount = 310;
$releasedPages = [306, 307, 308, 309, 310];

$makeFirstPage = static function () use ($pageSize, $pageCount): string {
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
$pages[1] = $makeFirstPage();
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

$plan = SQLiteBTreeFreelistVacuumOverflowCurrentSourceNextPlan::fromCurrentSourceDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [
        [
            'source' => 'copied-wp-options-autoload-current-overflow',
            'first_page' => 306,
            'overflow_payload_bytes' => 1524,
        ],
        [
            'source' => 'copied-wp-options-option-name-current-overflow',
            'first_page' => 309,
            'overflow_payload_bytes' => 1016,
        ],
    ],
    [
        [
            'source' => 'copied-wp-options-autoload-delete-tail-overflow',
            'obsolete_overflow_page_numbers' => [306, 307, 308],
            'rowids' => [14301],
        ],
        [
            'source' => 'copied-wp-options-option-name-delete-tail-overflow',
            'obsolete_overflow_page_numbers' => [309, 310],
            'record_values' => [['_transient_timeout_next143', 14301]],
        ],
    ],
    3,
    str_repeat('next143-wp-option-replacement-', 26),
    42,
    true,
);

$summary = [
    'scenario' => 'wordpress-btree-freelist-vacuum-overflow-current-source-next143',
    'wordpressUse' => 'After deleting copied wp_options rows with two current overflow chains, incremental vacuum truncates tail pages and replacement overflow allocation reuses only surviving freelist pages.',
    'current_source_pages' => array_column($plan->currentSourceRows(), 'page_number'),
    'vacuum_truncated_pages' => $plan->basePlan->vacuumPlan->truncatedFreedPointerMapPages(),
    'vacuum_surviving_pages' => $plan->basePlan->vacuumPlan->survivingFreedPointerMapPages(),
    'allocated_overflow_pages' => $plan->basePlan->allocatedOverflowPages(),
    'reused_current_source_pages' => $plan->reusedCurrentSourcePages(),
    'rejected_truncated_current_source_pages' => $plan->rejectedTruncatedCurrentSourcePages(),
    'replacement_chain_next_pages' => $plan->replacementChainNextPages(),
    'final_page_count' => $plan->basePlan->databaseAfterAllocation->pageCount(),
    'final_freelist_pages' => $plan->basePlan->databaseAfterAllocation->freelistPageNumbers(),
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['current_source_pages'] === [306, 307, 308, 309, 310]);
    assert($summary['vacuum_truncated_pages'] === [308, 309, 310]);
    assert($summary['allocated_overflow_pages'] === [307, 306]);
    assert($summary['reused_current_source_pages'] === [307, 306]);
    assert($summary['rejected_truncated_current_source_pages'] === [308, 309, 310]);
    assert($summary['replacement_chain_next_pages'] === [306, 0]);
    assert($summary['final_page_count'] === 307);
    echo "wordpress-btree-freelist-vacuum-overflow-current-source-next143 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
