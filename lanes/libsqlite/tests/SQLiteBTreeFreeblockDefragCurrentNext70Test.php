<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreeblockDefragPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$tablePage = static function (): string {
    $cellA = SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'autoload', 'yes']));
    $cellB = SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_timeout_feed', 'stale']));
    $cellC = SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, '_site_transient_update_plugins', 'fresh']));

    $page = SQLiteTableLeafPage::assemble([$cellA, $cellB, $cellC]);
    $deleted = SQLiteTableLeafPage::deleteCellByRowId($page, 2, secureDelete: true);
    $deleted[7] = chr(47);

    return $deleted;
};

$indexPage = static function (): string {
    $cellA = SQLiteIndexCell::encode(SQLiteRecord::encode(['autoload', 1]));
    $cellB = SQLiteIndexCell::encode(SQLiteRecord::encode(['_transient_timeout_feed', 2]));
    $cellC = SQLiteIndexCell::encode(SQLiteRecord::encode(['_site_transient_update_plugins', 3]));

    $page = SQLiteIndexLeafPage::assemble([$cellA, $cellB, $cellC]);
    $deleted = SQLiteIndexLeafPage::deleteCellByRecordValues($page, ['_transient_timeout_feed', 2], secureDelete: true);
    $deleted[7] = chr(47);

    return $deleted;
};

$tableFixture = static fn (): SQLiteBTreeFreeblockDefragPlan => SQLiteBTreeFreeblockDefragPlan::fromPage(5, $tablePage());
$indexFixture = static fn (): SQLiteBTreeFreeblockDefragPlan => SQLiteBTreeFreeblockDefragPlan::fromPage(6, $indexPage());
$tableAfterHeader = static fn (): SQLiteBTreePageHeader => SQLiteBTreePageHeader::parsePage($tableFixture()->pageImage, 512);
$indexAfterHeader = static fn (): SQLiteBTreePageHeader => SQLiteBTreePageHeader::parsePage($indexFixture()->pageImage, 512);

$throwsMessage = static function (callable $callback): string {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases = [
    'table plan class' => static fn (): mixed => get_class($tableFixture()),
    'table action' => static fn (): mixed => $tableFixture()->toArray()['action'],
    'table page number' => static fn (): mixed => $tableFixture()->pageNumber,
    'table page type' => static fn (): mixed => $tableFixture()->pageType,
    'table fragmented bytes before' => static fn (): mixed => $tableFixture()->fragmentedBytesBefore,
    'table fragmented bytes after' => static fn (): mixed => $tableFixture()->fragmentedBytesAfter,
    'table first freeblock before nonzero' => static fn (): mixed => $tableFixture()->firstFreeblockOffsetBefore > 0,
    'table first freeblock after zero' => static fn (): mixed => $tableFixture()->firstFreeblockOffsetAfter,
    'table freeblock count before' => static fn (): mixed => count($tableFixture()->freeblocksBefore),
    'table freeblock count after' => static fn (): mixed => count($tableFixture()->freeblocksAfter),
    'table cell count after' => static fn (): mixed => $tableAfterHeader()->cellCount,
    'table pointer count after' => static fn (): mixed => count($tableFixture()->cellPointersAfter),
    'table compacted content start moves upward' => static fn (): mixed => $tableFixture()->cellContentStartAfter > $tableFixture()->cellContentStartBefore,
    'table last cell remains at page end' => static fn (): mixed => max($tableFixture()->cellPointersAfter) === max($tableFixture()->cellPointersBefore),
    'table content start equals first pointer' => static fn (): mixed => $tableFixture()->cellContentStartAfter,
    'table free space drops by recovered fragments' => static fn (): mixed => $tableFixture()->freeSpaceBytesBefore - $tableFixture()->freeSpaceBytesAfter,
    'table updated page numbers' => static fn (): mixed => $tableFixture()->toArray()['updated_page_numbers'],
    'table page image parse status' => static fn (): mixed => $tableAfterHeader()->freeblockIntegrityReport($tableFixture()->pageImage)['status'],
    'table page image has no current next fragments' => static fn (): mixed => $tableAfterHeader()->freeblockFragmentReport($tableFixture()->pageImage)['current_next_fragment_bytes'],
    'table cleared free space has no nonzero freeblock payload' => static fn (): mixed => strpos(substr($tableFixture()->pageImage, 8, $tableFixture()->cellContentStartAfter - 8), "\xff") === false,
    'table keeps first rowid' => static fn (): mixed => SQLiteTableLeafCell::parsePageCells($tableFixture()->pageImage, $tableAfterHeader())[0]->rowId,
    'table keeps second remaining rowid' => static fn (): mixed => SQLiteTableLeafCell::parsePageCells($tableFixture()->pageImage, $tableAfterHeader())[1]->rowId,
    'table pageImages carries compacted page' => static fn (): mixed => $tableFixture()->pageImages()[5] === $tableFixture()->pageImage,
    'table toArray before freeblocks mirror property' => static fn (): mixed => $tableFixture()->toArray()['freeblocks_before'] === $tableFixture()->freeblocksBefore,
    'table toArray after pointers mirror property' => static fn (): mixed => $tableFixture()->toArray()['cell_pointers_after'] === $tableFixture()->cellPointersAfter,
    'index plan class' => static fn (): mixed => get_class($indexFixture()),
    'index action' => static fn (): mixed => $indexFixture()->toArray()['action'],
    'index page number' => static fn (): mixed => $indexFixture()->pageNumber,
    'index page type' => static fn (): mixed => $indexFixture()->pageType,
    'index fragmented bytes before' => static fn (): mixed => $indexFixture()->fragmentedBytesBefore,
    'index fragmented bytes after' => static fn (): mixed => $indexFixture()->fragmentedBytesAfter,
    'index first freeblock before nonzero' => static fn (): mixed => $indexFixture()->firstFreeblockOffsetBefore > 0,
    'index first freeblock after zero' => static fn (): mixed => $indexFixture()->firstFreeblockOffsetAfter,
    'index freeblock count before' => static fn (): mixed => count($indexFixture()->freeblocksBefore),
    'index freeblock count after' => static fn (): mixed => count($indexFixture()->freeblocksAfter),
    'index cell count after' => static fn (): mixed => $indexAfterHeader()->cellCount,
    'index pointer count after' => static fn (): mixed => count($indexFixture()->cellPointersAfter),
    'index compacted content start moves upward' => static fn (): mixed => $indexFixture()->cellContentStartAfter > $indexFixture()->cellContentStartBefore,
    'index last cell remains at page end' => static fn (): mixed => max($indexFixture()->cellPointersAfter) === max($indexFixture()->cellPointersBefore),
    'index content start equals first pointer' => static fn (): mixed => $indexFixture()->cellContentStartAfter,
    'index free space drops by recovered fragments' => static fn (): mixed => $indexFixture()->freeSpaceBytesBefore - $indexFixture()->freeSpaceBytesAfter,
    'index updated page numbers' => static fn (): mixed => $indexFixture()->toArray()['updated_page_numbers'],
    'index page image parse status' => static fn (): mixed => $indexAfterHeader()->freeblockIntegrityReport($indexFixture()->pageImage)['status'],
    'index page image has no current next fragments' => static fn (): mixed => $indexAfterHeader()->freeblockFragmentReport($indexFixture()->pageImage)['current_next_fragment_bytes'],
    'index cleared free space has no nonzero freeblock payload' => static fn (): mixed => strpos(substr($indexFixture()->pageImage, 8, $indexFixture()->cellContentStartAfter - 8), "\xee") === false,
    'index keeps first record name' => static fn (): mixed => SQLiteIndexCell::parsePageCells($indexFixture()->pageImage, $indexAfterHeader())[0]->record()->values[0],
    'index keeps second remaining record name' => static fn (): mixed => SQLiteIndexCell::parsePageCells($indexFixture()->pageImage, $indexAfterHeader())[1]->record()->values[0],
    'index pageImages carries compacted page' => static fn (): mixed => $indexFixture()->pageImages()[6] === $indexFixture()->pageImage,
    'index toArray before freeblocks mirror property' => static fn (): mixed => $indexFixture()->toArray()['freeblocks_before'] === $indexFixture()->freeblocksBefore,
    'index toArray after pointers mirror property' => static fn (): mixed => $indexFixture()->toArray()['cell_pointers_after'] === $indexFixture()->cellPointersAfter,
    'clear false keeps compacted freeblock chain cleared' => static fn (): mixed => SQLiteBTreeFreeblockDefragPlan::fromPage(5, $tablePage(), clearFreeSpace: false)->firstFreeblockOffsetAfter,
    'throws on page zero' => static fn (): mixed => $throwsMessage(static fn () => SQLiteBTreeFreeblockDefragPlan::fromPage(0, $tablePage())),
    'throws on short page' => static fn (): mixed => $throwsMessage(static fn () => SQLiteBTreeFreeblockDefragPlan::fromPage(5, substr($tablePage(), 1))),
    'throws on interior page' => static function () use ($tablePage, $throwsMessage): mixed {
        $page = $tablePage();
        $page[0] = "\x05";
        $page = substr_replace($page, pack('N', 0), 8, 4);

        return $throwsMessage(static fn () => SQLiteBTreeFreeblockDefragPlan::fromPage(5, $page));
    },
];

$expected = [
    'table plan class' => SQLiteBTreeFreeblockDefragPlan::class,
    'table action' => 'btree-freeblock-defrag-current-next',
    'table page number' => 5,
    'table page type' => 'table-leaf',
    'table fragmented bytes before' => 47,
    'table fragmented bytes after' => 0,
    'table first freeblock before nonzero' => true,
    'table first freeblock after zero' => 0,
    'table freeblock count before' => 1,
    'table freeblock count after' => 0,
    'table cell count after' => 2,
    'table pointer count after' => 2,
    'table compacted content start moves upward' => true,
    'table last cell remains at page end' => true,
    'table content start equals first pointer' => min($tableFixture()->cellPointersAfter),
    'table free space drops by recovered fragments' => 47,
    'table updated page numbers' => [5],
    'table page image parse status' => 'ok',
    'table page image has no current next fragments' => 0,
    'table cleared free space has no nonzero freeblock payload' => true,
    'table keeps first rowid' => 1,
    'table keeps second remaining rowid' => 3,
    'table pageImages carries compacted page' => true,
    'table toArray before freeblocks mirror property' => true,
    'table toArray after pointers mirror property' => true,
    'index plan class' => SQLiteBTreeFreeblockDefragPlan::class,
    'index action' => 'btree-freeblock-defrag-current-next',
    'index page number' => 6,
    'index page type' => 'index-leaf',
    'index fragmented bytes before' => 47,
    'index fragmented bytes after' => 0,
    'index first freeblock before nonzero' => true,
    'index first freeblock after zero' => 0,
    'index freeblock count before' => 1,
    'index freeblock count after' => 0,
    'index cell count after' => 2,
    'index pointer count after' => 2,
    'index compacted content start moves upward' => true,
    'index last cell remains at page end' => true,
    'index content start equals first pointer' => min($indexFixture()->cellPointersAfter),
    'index free space drops by recovered fragments' => 47,
    'index updated page numbers' => [6],
    'index page image parse status' => 'ok',
    'index page image has no current next fragments' => 0,
    'index cleared free space has no nonzero freeblock payload' => true,
    'index keeps first record name' => 'autoload',
    'index keeps second remaining record name' => '_site_transient_update_plugins',
    'index pageImages carries compacted page' => true,
    'index toArray before freeblocks mirror property' => true,
    'index toArray after pointers mirror property' => true,
    'clear false keeps compacted freeblock chain cleared' => 0,
    'throws on page zero' => 'SQLite b-tree defrag page number must be positive',
    'throws on short page' => 'SQLite b-tree defrag requires a complete page image',
    'throws on interior page' => 'SQLite b-tree defrag currently supports leaf pages only',
];

$tests = [];
foreach ($cases as $name => $read) {
    $tests['btree freeblock defrag current next70 ' . $name] = static function (TestRunner $t) use ($read, $expected, $name): void {
        $t->same($expected[$name], $read());
    };
}

return $tests;
