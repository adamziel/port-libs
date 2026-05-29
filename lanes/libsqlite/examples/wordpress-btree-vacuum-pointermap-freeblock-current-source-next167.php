<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pages = array_fill(1, 110, str_repeat("\0", $pageSize));
$pages[1] = str_repeat("\0", $pageSize);
$pages[1] = substr_replace($pages[1], "SQLite format 3\0", 0, 16);
$pages[1] = substr_replace($pages[1], pack('n', $pageSize), 16, 2);
$pages[1][18] = "\x01";
$pages[1][19] = "\x01";
$pages[1][21] = "\x40";
$pages[1][22] = "\x20";
$pages[1][23] = "\x20";
$pages[1] = substr_replace($pages[1], pack('N', 110), 28, 4);
$pages[1] = substr_replace($pages[1], pack('N', 3), 52, 4);
$pages[1] = substr_replace($pages[1], pack('N', 1), 56, 4);
$pages[2] = str_repeat("\0", $pageSize);
$pages[105] = str_repeat("\0", $pageSize);

$putPointerMap = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages): void {
    if ($pageNumber === 2 || $pageNumber === 105) {
        return;
    }

    $pointerMapPage = $pageNumber >= 106 ? 105 : 2;
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$pages[3] = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next167_payload', str_repeat('x', 96)])),
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
], $pageSize);

foreach ([106 => [107, 'A'], 107 => [108, 'B'], 108 => [109, 'C'], 109 => [110, 'D'], 110 => [0, 'E']] as $pageNumber => [$nextPage, $byte]) {
    $pages[$pageNumber] = pack('N', $nextPage) . str_repeat($byte, $pageSize - 4);
}

foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
    108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
    109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
    110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
] as $pageNumber => [$type, $parentPageNumber]) {
    $putPointerMap($pageNumber, $type, $parentPageNumber);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);
$plan = SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext167(
    $database,
    3,
    [
        'page' => $deletedPage,
        'rowid' => 2,
        'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
    ],
    4,
    str_repeat('wordpress-next167-freeblock-audit-', 55),
    3,
    true,
);

$summary = [
    'wordpress_scenario' => 'wp_options transient delete keeps the leaf freeblock stable while vacuumed current-source overflow pages are reused',
    'action' => $plan->toArray()['action'],
    'stable_leaf_pages' => $plan->stableLeafPages(),
    'replacement_pointer_map_pages_after_vacuum' => $plan->replacementPointerMapPagesAfterVacuum(),
    'reused_truncated_current_source_pages' => $plan->currentSourceAudit()['reused_truncated_current_source_pages'],
    'changed_current_source_next_pages' => $plan->currentSourceAudit()['changed_current_source_next_pages'],
    'integrity_errors' => $plan->integrityErrors(),
];

if (($argv[1] ?? '') === '--self-test') {
    $expected = [
        'wordpress_scenario' => 'wp_options transient delete keeps the leaf freeblock stable while vacuumed current-source overflow pages are reused',
        'action' => 'btree-vacuum-pointermap-freeblock-current-source-next167',
        'stable_leaf_pages' => [3],
        'replacement_pointer_map_pages_after_vacuum' => [106, 107, 108, 109],
        'reused_truncated_current_source_pages' => [107, 108, 109],
        'changed_current_source_next_pages' => [109, 110],
        'integrity_errors' => [],
    ];
    if ($summary !== $expected) {
        fwrite(STDERR, "wordpress-btree-vacuum-pointermap-freeblock-current-source-next167 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-btree-vacuum-pointermap-freeblock-current-source-next167 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
