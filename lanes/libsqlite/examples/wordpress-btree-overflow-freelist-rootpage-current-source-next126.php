<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowFreelistRootpageCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$firstPage = static function (): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', 8), 28, 4);
    $page = substr_replace($page, pack('N', 8), 32, 4);
    $page = substr_replace($page, pack('N', 1), 36, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMap = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    $pointerMapPage = (intdiv($pageNumber - 2, 103) * 103) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", 512),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$overflowPage = static fn (?int $nextPage, string $payload): string => str_pad(pack('N', $nextPage ?? 0) . $payload, 512, "\0");

$pages = array_fill(1, 8, str_repeat("\0", 512));
$pages[1] = $firstPage();
$pages[3] = SQLiteTableLeafPage::assemble([]);
$pages[6] = $overflowPage(7, str_repeat('w', 508));
$pages[7] = $overflowPage(null, str_repeat('p', 192));
$pages[8] = SQLiteFreelistTrunkPage::assemble(null, [], 512);

$putPointerMap($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMap($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$putPointerMap($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
$putPointerMap($pages, 8, SQLitePointerMapEntry::FREE_PAGE, 0);

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = SQLiteBTreeOverflowFreelistRootpageCurrentSourceNextPlan::fromOverflowChains(
    $database,
    [[
        'source' => 'deleted _transient import payload overflow',
        'first_page' => 6,
        'overflow_payload_bytes' => 700,
        'rowids' => [12601],
    ]],
    'table',
    'wp_import_stage_next126',
    'wp_import_stage_next126',
    'CREATE TABLE wp_import_stage_next126 (ID INTEGER PRIMARY KEY, post_id INTEGER, meta_id INTEGER, status TEXT)',
    [
        6 => SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 1, 1, 'draft'])),
        ]),
    ],
    true,
);

echo json_encode([
    'action' => $plan->toArray()['action'],
    'releasedOverflowPages' => $plan->reusePlan->releasedOverflowPages(),
    'rootPageNumbers' => $plan->rootPageNumbers(),
    'freelistAfterReuse' => $plan->reusePlan->databaseAfterReuse->freelistPageNumbers(),
    'rootRows' => $plan->rootpageRows,
], JSON_PRETTY_PRINT) . "\n";
