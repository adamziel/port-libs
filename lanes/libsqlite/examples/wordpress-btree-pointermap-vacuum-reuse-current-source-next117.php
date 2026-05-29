<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeFreelistPointerMapVacuumReuseCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage = static function (): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', 310), 28, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    $stride = intdiv(512, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pageNumber === 1 || $pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", 512),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$pages = array_fill(1, 310, str_repeat("\0", 512));
$pages[1] = $makeFirstPage();

foreach ([203 => 204, 204 => 0, 306 => 307, 307 => 308, 308 => 0, 309 => 310, 310 => 0] as $pageNumber => $nextPage) {
    $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(64 + ($pageNumber % 26)), 508);
}

foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 42 => [SQLitePointerMapEntry::BTREE_PAGE, 4], 88 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parent);
}
foreach ([203, 204] as $index => $pageNumber) {
    $putPointerMapEntry($pages, $pageNumber, $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE, $index === 0 ? 42 : 203);
}
foreach ([306, 307, 308] as $index => $pageNumber) {
    $putPointerMapEntry($pages, $pageNumber, $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE, $index === 0 ? 88 : $pageNumber - 1);
}
foreach ([309, 310] as $index => $pageNumber) {
    $putPointerMapEntry($pages, $pageNumber, $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE, $index === 0 ? 4 : 309);
}

$plan = SQLiteBTreeFreelistPointerMapVacuumReuseCurrentSourceNextPlan::fromOverflowDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [
        ['source' => 'wp_options-autoload-middle-overflow-next117', 'obsolete_overflow_page_numbers' => [203, 204], 'rowids' => [11701]],
        ['source' => 'wp_options-option-value-tail-overflow-next117', 'obsolete_overflow_page_numbers' => [306, 307, 308], 'rowids' => [11702]],
        ['source' => 'wp_options-index-tail-overflow-next117', 'obsolete_overflow_page_numbers' => [309, 310], 'record_values' => [['_transient_next117', 11702]]],
    ],
    3,
    4,
    42,
    [
        204 => SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(11711, SQLiteRecord::encode([null, '_site_transient_pm_reused_next117', 'payload', 'no'])),
        ]),
        307 => SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(11710, SQLiteRecord::encode([null, '_transient_pm_reused_next117', 'payload', 'no'])),
        ]),
        306 => SQLiteIndexLeafPage::assemble([
            SQLiteRecord::encode(['_transient_pm_reused_next117', 11710]),
        ]),
        203 => SQLiteIndexLeafPage::assemble([
            SQLiteRecord::encode(['_site_transient_pm_reused_next117', 11711]),
        ]),
    ],
    true,
);

echo json_encode([
    'scenario' => 'wordpress-btree-pointermap-vacuum-reuse-current-source-next117',
    'wordpressUse' => 'After wp_options cleanup frees overflow chains on both middle and tail pages, incremental vacuum truncates the tail and reuses surviving pages while rewriting both affected auto-vacuum pointer-map pages.',
    'allocatedPages' => $plan->allocatedPageNumbers(),
    'survivingFreedPages' => $plan->reusePlan->vacuumPlan->survivingFreedPointerMapPages(),
    'truncatedFreedPages' => $plan->reusePlan->vacuumPlan->truncatedFreedPointerMapPages(),
    'pointerMapPageRewrites' => $plan->touchedPointerMapPageRows(),
    'reuseRows' => $plan->pointerMapVacuumReuseRows(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
