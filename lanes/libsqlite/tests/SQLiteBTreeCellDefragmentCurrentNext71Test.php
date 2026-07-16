<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeCellDefragmentPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$tablePage = static function (): string {
    $cells = [
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'alpha', '1', 'yes'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'beta', '22', 'no'])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'gamma', '333', 'yes'])),
    ];
    $offsets = [496, 475, 440];
    $page = str_repeat("\x7f", 512);
    $page[0] = "\x0d";
    $page = substr_replace($page, pack('n', 458), 1, 2);
    $page = substr_replace($page, pack('n', 3), 3, 2);
    $page = substr_replace($page, pack('n', 440), 5, 2);
    $page[7] = chr(2);
    foreach ($offsets as $index => $offset) {
        $page = substr_replace($page, pack('n', $offset), 8 + ($index * 2), 2);
        $page = substr_replace($page, $cells[$index], $offset, strlen($cells[$index]));
    }
    $page = substr_replace($page, pack('n', 470) . pack('n', 10) . str_repeat('A', 6), 458, 10);
    $page = substr_replace($page, pack('n', 490) . pack('n', 5) . 'Z', 470, 5);
    $page = substr_replace($page, pack('n', 0) . pack('n', 4), 490, 4);

    return $page;
};

$indexPage = static function (): string {
    $cells = [
        SQLiteIndexCell::encode(SQLiteRecord::encode(['autoload', 'alpha', 1])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['autoload', 'beta', 2])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['autoload', 'gamma', 3])),
    ];
    $offsets = [490, 455, 416];
    $page = str_repeat("\x6f", 512);
    $page[0] = "\x0a";
    $page = substr_replace($page, pack('n', 440), 1, 2);
    $page = substr_replace($page, pack('n', 3), 3, 2);
    $page = substr_replace($page, pack('n', 416), 5, 2);
    $page[7] = chr(3);
    foreach ($offsets as $index => $offset) {
        $page = substr_replace($page, pack('n', $offset), 8 + ($index * 2), 2);
        $page = substr_replace($page, $cells[$index], $offset, strlen($cells[$index]));
    }
    $page = substr_replace($page, pack('n', 447) . pack('n', 5) . 'I', 440, 5);
    $page = substr_replace($page, pack('n', 0) . pack('n', 8) . str_repeat('J', 4), 447, 8);

    return $page;
};

$tablePlan = static fn (): SQLiteBTreeCellDefragmentPlan => SQLiteBTreeCellDefragmentPlan::fromPage(11, $tablePage());
$indexPlan = static fn (): SQLiteBTreeCellDefragmentPlan => SQLiteBTreeCellDefragmentPlan::fromPage(12, $indexPage());
$header = static fn (SQLiteBTreeCellDefragmentPlan $plan): SQLiteBTreePageHeader => SQLiteBTreePageHeader::parsePage($plan->pageImage, 512);
$throws = static function (callable $callback): string {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases = [
    'table action' => static fn (): mixed => $tablePlan()->toArray()['action'],
    'table page number' => static fn (): mixed => $tablePlan()->pageNumber,
    'table page type' => static fn (): mixed => $tablePlan()->pageType,
    'table cell count' => static fn (): mixed => $tablePlan()->cellCount,
    'table fragmented before' => static fn (): mixed => $tablePlan()->fragmentedBytesBefore,
    'table fragmented after' => static fn (): mixed => $tablePlan()->fragmentedBytesAfter,
    'table first freeblock before' => static fn (): mixed => $tablePlan()->firstFreeblockBefore,
    'table first freeblock after' => static fn (): mixed => $tablePlan()->firstFreeblockAfter,
    'table content start before' => static fn (): mixed => $tablePlan()->cellContentStartBefore,
    'table content start after' => static fn (): mixed => $tablePlan()->cellContentStartAfter,
    'table free space preserved' => static fn (): mixed => $tablePlan()->freeSpaceBefore === $tablePlan()->freeSpaceAfter,
    'table moved two cells' => static fn (): mixed => $tablePlan()->movedCellCount,
    'table total cell bytes' => static fn (): mixed => $tablePlan()->totalCellBytes,
    'table before first key' => static fn (): mixed => $tablePlan()->cellsBefore[0]['key'],
    'table before second offset' => static fn (): mixed => $tablePlan()->cellsBefore[1]['offset'],
    'table before third offset' => static fn (): mixed => $tablePlan()->cellsBefore[2]['offset'],
    'table after first offset' => static fn (): mixed => $tablePlan()->cellsAfter[0]['offset'],
    'table after second offset' => static fn (): mixed => $tablePlan()->cellsAfter[1]['offset'],
    'table after third offset' => static fn (): mixed => $tablePlan()->cellsAfter[2]['offset'],
    'table after key order' => static fn (): mixed => array_column($tablePlan()->cellsAfter, 'key'),
    'table current next fragment status' => static fn (): mixed => $tablePlan()->currentNextFragmentReport['status'],
    'table current next fragment bytes' => static fn (): mixed => $tablePlan()->currentNextFragmentReport['current_next_fragment_bytes'],
    'table current next fragment count' => static fn (): mixed => count($tablePlan()->currentNextFragmentReport['current_next_fragments']),
    'table current next first gap' => static fn (): mixed => $tablePlan()->currentNextFragmentReport['current_next_fragments'][0]['fragment_bytes'],
    'table updated page number' => static fn (): mixed => $tablePlan()->toArray()['updated_page_numbers'],
    'table page image map matches' => static fn (): mixed => $tablePlan()->pageImages()[11] === $tablePlan()->pageImage,
    'table after freeblock count' => static fn (): mixed => count($header($tablePlan())->freeblocks($tablePlan()->pageImage)),
    'table after integrity ok' => static fn (): mixed => $header($tablePlan())->freeblockIntegrityReport($tablePlan()->pageImage)['status'],
    'table after pointer array' => static fn (): mixed => $header($tablePlan())->cellPointers($tablePlan()->pageImage),
    'table after rows parse' => static fn (): mixed => array_map(static fn ($cell): int => $cell->rowId, SQLiteTableLeafCell::parsePageCells($tablePlan()->pageImage, $header($tablePlan()))),
    'table clear free space zeroes free area before compacted cells' => static fn (): mixed => trim(substr($tablePlan()->pageImage, 458, 5), "\0") === '',
    'index action' => static fn (): mixed => $indexPlan()->toArray()['action'],
    'index page number' => static fn (): mixed => $indexPlan()->pageNumber,
    'index page type' => static fn (): mixed => $indexPlan()->pageType,
    'index cell count' => static fn (): mixed => $indexPlan()->cellCount,
    'index fragmented before' => static fn (): mixed => $indexPlan()->fragmentedBytesBefore,
    'index fragmented after' => static fn (): mixed => $indexPlan()->fragmentedBytesAfter,
    'index first freeblock before' => static fn (): mixed => $indexPlan()->firstFreeblockBefore,
    'index first freeblock after' => static fn (): mixed => $indexPlan()->firstFreeblockAfter,
    'index content start before' => static fn (): mixed => $indexPlan()->cellContentStartBefore,
    'index content start after' => static fn (): mixed => $indexPlan()->cellContentStartAfter,
    'index free space preserved' => static fn (): mixed => $indexPlan()->freeSpaceBefore === $indexPlan()->freeSpaceAfter,
    'index moved cells' => static fn (): mixed => $indexPlan()->movedCellCount,
    'index before first key' => static fn (): mixed => $indexPlan()->cellsBefore[0]['key'],
    'index before second offset' => static fn (): mixed => $indexPlan()->cellsBefore[1]['offset'],
    'index after first offset' => static fn (): mixed => $indexPlan()->cellsAfter[0]['offset'],
    'index after second offset' => static fn (): mixed => $indexPlan()->cellsAfter[1]['offset'],
    'index after third offset' => static fn (): mixed => $indexPlan()->cellsAfter[2]['offset'],
    'index after key order' => static fn (): mixed => array_column($indexPlan()->cellsAfter, 'key'),
    'index current next fragment bytes' => static fn (): mixed => $indexPlan()->currentNextFragmentReport['current_next_fragment_bytes'],
    'index current next fragment count' => static fn (): mixed => count($indexPlan()->currentNextFragmentReport['current_next_fragments']),
    'index current next gap starts' => static fn (): mixed => $indexPlan()->currentNextFragmentReport['current_next_fragments'][0]['current_end_offset'],
    'index after freeblock count' => static fn (): mixed => count($header($indexPlan())->freeblocks($indexPlan()->pageImage)),
    'index after integrity ok' => static fn (): mixed => $header($indexPlan())->freeblockIntegrityReport($indexPlan()->pageImage)['status'],
    'index after records parse' => static fn (): mixed => array_map(static fn ($cell): array => $cell->record()->values, SQLiteIndexCell::parsePageCells($indexPlan()->pageImage, $header($indexPlan()))),
    'index page image map matches' => static fn (): mixed => $indexPlan()->pageImages()[12] === $indexPlan()->pageImage,
    'keeps uncleared bytes when requested' => static fn (): mixed => trim(substr(SQLiteBTreeCellDefragmentPlan::fromPage(11, $tablePage(), clearFreeSpace: false)->pageImage, 458, 10), "\0") !== '',
    'rejects page zero' => static fn (): mixed => $throws(static fn () => SQLiteBTreeCellDefragmentPlan::fromPage(0, $tablePage())),
    'rejects short page' => static fn (): mixed => $throws(static fn () => SQLiteBTreeCellDefragmentPlan::fromPage(11, substr($tablePage(), 1))),
    'rejects interior page' => static function () use ($tablePage, $throws): string {
        $page = $tablePage();
        $page[0] = "\x05";
        return $throws(static fn () => SQLiteBTreeCellDefragmentPlan::fromPage(11, $page));
    },
    'table direct defragment pointer parity' => static fn (): mixed => SQLiteBTreePageHeader::parsePage(SQLiteTableLeafPage::defragment($tablePage()), 512)->cellPointers(SQLiteTableLeafPage::defragment($tablePage())),
    'index direct defragment pointer parity' => static fn (): mixed => SQLiteBTreePageHeader::parsePage(SQLiteIndexLeafPage::defragment($indexPage()), 512)->cellPointers(SQLiteIndexLeafPage::defragment($indexPage())),
];

$expected = [
    'table action' => 'btree-cell-defragment-current-next',
    'table page number' => 11,
    'table page type' => 'table-leaf',
    'table cell count' => 3,
    'table fragmented before' => 2,
    'table fragmented after' => 0,
    'table first freeblock before' => 458,
    'table first freeblock after' => 0,
    'table content start before' => 440,
    'table content start after' => 463,
    'table free space preserved' => false,
    'table moved two cells' => 2,
    'table total cell bytes' => 49,
    'table before first key' => 1,
    'table before second offset' => 475,
    'table before third offset' => 440,
    'table after first offset' => 496,
    'table after second offset' => 481,
    'table after third offset' => 463,
    'table after key order' => [1, 2, 3],
    'table current next fragment status' => 'ok',
    'table current next fragment bytes' => 2,
    'table current next fragment count' => 1,
    'table current next first gap' => 2,
    'table updated page number' => [11],
    'table page image map matches' => true,
    'table after freeblock count' => 0,
    'table after integrity ok' => 'ok',
    'table after pointer array' => [496, 481, 463],
    'table after rows parse' => [1, 2, 3],
    'table clear free space zeroes free area before compacted cells' => true,
    'index action' => 'btree-cell-defragment-current-next',
    'index page number' => 12,
    'index page type' => 'index-leaf',
    'index cell count' => 3,
    'index fragmented before' => 3,
    'index fragmented after' => 0,
    'index first freeblock before' => 440,
    'index first freeblock after' => 0,
    'index content start before' => 416,
    'index content start after' => 457,
    'index free space preserved' => false,
    'index moved cells' => 3,
    'index before first key' => ['autoload', 'alpha', 1],
    'index before second offset' => 455,
    'index after first offset' => 494,
    'index after second offset' => 476,
    'index after third offset' => 457,
    'index after key order' => [
        ['autoload', 'alpha', 1],
        ['autoload', 'beta', 2],
        ['autoload', 'gamma', 3],
    ],
    'index current next fragment bytes' => 2,
    'index current next fragment count' => 1,
    'index current next gap starts' => 445,
    'index after freeblock count' => 0,
    'index after integrity ok' => 'ok',
    'index after records parse' => [
        ['autoload', 'alpha', 1],
        ['autoload', 'beta', 2],
        ['autoload', 'gamma', 3],
    ],
    'index page image map matches' => true,
    'keeps uncleared bytes when requested' => true,
    'rejects page zero' => 'SQLite b-tree cell defragment page number must be positive',
    'rejects short page' => 'SQLite b-tree cell defragment requires a complete page image',
    'rejects interior page' => 'SQLite b-tree cell defragmentation requires a leaf page',
    'table direct defragment pointer parity' => [496, 481, 463],
    'index direct defragment pointer parity' => [494, 476, 457],
];

$tests = [];
foreach ($cases as $name => $read) {
    $tests['btree cell defragment current next71 ' . $name] = static function (TestRunner $t) use ($read, $expected, $name): void {
        $t->same($expected[$name], $read());
    };
}

return $tests;
