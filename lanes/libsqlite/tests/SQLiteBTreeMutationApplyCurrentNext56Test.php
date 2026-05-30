<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$tableFixture = static function (bool $secureDelete = false, int $fragmentedBytes = 2): array {
    $cell = SQLiteTableLeafCell::encode(9, SQLiteRecord::encode([null, '_transient_timeout_plugin', 'stale', 'no']));
    $cellOffset = 512 - strlen($cell);
    $freeblockOffset = $cellOffset - 22;
    $page = str_repeat("\xff", 512);
    $page[0] = "\x0d";
    $page = substr_replace($page, pack('n', $freeblockOffset), 1, 2);
    $page = substr_replace($page, pack('n', 1), 3, 2);
    $page = substr_replace($page, pack('n', $freeblockOffset), 5, 2);
    $page[7] = chr($fragmentedBytes);
    $page = substr_replace($page, pack('n', $cellOffset), 8, 2);
    $page = substr_replace($page, pack('n', 0) . pack('n', 20) . str_repeat('F', 16), $freeblockOffset, 20);
    $page = substr_replace($page, $cell, $cellOffset, strlen($cell));

    $before = SQLiteBTreePageHeader::parsePage($page, 512);
    $mutated = SQLiteTableLeafPage::deleteCellByRowId($page, 9, secureDelete: $secureDelete);
    $after = SQLiteBTreePageHeader::parsePage($mutated, 512);

    return [
        'cell' => $cell,
        'cell_offset' => $cellOffset,
        'freeblock_offset' => $freeblockOffset,
        'page' => $page,
        'mutated' => $mutated,
        'before' => $before,
        'after' => $after,
        'before_blocks' => $before->freeblocks($page),
        'after_blocks' => $after->freeblocks($mutated),
    ];
};

$indexFixture = static function (bool $secureDelete = false, int $fragmentedBytes = 2): array {
    $cell = SQLiteIndexCell::encode(SQLiteRecord::encode(['_transient_timeout_plugin', 9]));
    $cellOffset = 512 - strlen($cell);
    $freeblockOffset = $cellOffset - 22;
    $page = str_repeat("\xee", 512);
    $page[0] = "\x0a";
    $page = substr_replace($page, pack('n', $freeblockOffset), 1, 2);
    $page = substr_replace($page, pack('n', 1), 3, 2);
    $page = substr_replace($page, pack('n', $freeblockOffset), 5, 2);
    $page[7] = chr($fragmentedBytes);
    $page = substr_replace($page, pack('n', $cellOffset), 8, 2);
    $page = substr_replace($page, pack('n', 0) . pack('n', 20) . str_repeat('I', 16), $freeblockOffset, 20);
    $page = substr_replace($page, $cell, $cellOffset, strlen($cell));

    $before = SQLiteBTreePageHeader::parsePage($page, 512);
    $mutated = SQLiteIndexLeafPage::deleteCellByRecordValues($page, ['_transient_timeout_plugin', 9], secureDelete: $secureDelete);
    $after = SQLiteBTreePageHeader::parsePage($mutated, 512);

    return [
        'cell' => $cell,
        'cell_offset' => $cellOffset,
        'freeblock_offset' => $freeblockOffset,
        'page' => $page,
        'mutated' => $mutated,
        'before' => $before,
        'after' => $after,
        'before_blocks' => $before->freeblocks($page),
        'after_blocks' => $after->freeblocks($mutated),
    ];
};

$throwsMessage = static function (callable $callback): string {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return $exception->getMessage();
    }

    return 'no exception';
};

$cases = [
    'table before page type' => static fn (): mixed => $tableFixture()['before']->pageType,
    'table before cell count' => static fn (): mixed => $tableFixture()['before']->cellCount,
    'table before first freeblock offset' => static fn (): mixed => $tableFixture()['before']->firstFreeblockOffset,
    'table before fragmented bytes' => static fn (): mixed => $tableFixture()['before']->fragmentedFreeBytes,
    'table before has one freeblock' => static fn (): mixed => count($tableFixture()['before_blocks']),
    'table before freeblock size' => static fn (): mixed => $tableFixture()['before_blocks'][0]->size,
    'table before current next fragment bytes' => static fn (): mixed => $tableFixture()['before']->freeblockFragmentReport($tableFixture()['page'])['current_next_fragment_bytes'],
    'table deleted cell offset follows two byte fragment' => static fn (): mixed => $tableFixture()['cell_offset'] - $tableFixture()['before_blocks'][0]->endOffset(),
    'table after cell count' => static fn (): mixed => $tableFixture()['after']->cellCount,
    'table after fragmented bytes consumed' => static fn (): mixed => $tableFixture()['after']->fragmentedFreeBytes,
    'table after has one coalesced freeblock' => static fn (): mixed => count($tableFixture()['after_blocks']),
    'table after freeblock offset preserved' => static fn (): mixed => $tableFixture()['after_blocks'][0]->offset,
    'table after freeblock size absorbs fragment and cell' => static fn (): mixed => $tableFixture()['after_blocks'][0]->size,
    'table after freeblock end reaches page end' => static fn (): mixed => $tableFixture()['after_blocks'][0]->endOffset(),
    'table after freeblock next remains null' => static fn (): mixed => $tableFixture()['after_blocks'][0]->nextOffset,
    'table after fragment report has no current next gap' => static fn (): mixed => $tableFixture()['after']->freeblockFragmentReport($tableFixture()['mutated'])['current_next_fragment_bytes'],
    'table after integrity remains ok' => static fn (): mixed => $tableFixture()['after']->freeblockIntegrityReport($tableFixture()['mutated'])['status'],
    'table after free space increases by deleted cell and fragment' => static fn (): mixed => $tableFixture()['after']->freeSpaceBytes($tableFixture()['mutated']) - $tableFixture()['before']->freeSpaceBytes($tableFixture()['page']),
    'table secure delete clears merged freeblock payload' => static fn (): mixed => trim(substr($tableFixture(true)['mutated'], $tableFixture(true)['after_blocks'][0]->offset + 4, $tableFixture(true)['after_blocks'][0]->size - 4), "\0") === '',
    'table throws when fragment counter is too small' => static fn (): mixed => $throwsMessage(static fn () => $tableFixture(fragmentedBytes: 1)),
    'index before page type' => static fn (): mixed => $indexFixture()['before']->pageType,
    'index before cell count' => static fn (): mixed => $indexFixture()['before']->cellCount,
    'index before first freeblock offset' => static fn (): mixed => $indexFixture()['before']->firstFreeblockOffset,
    'index before fragmented bytes' => static fn (): mixed => $indexFixture()['before']->fragmentedFreeBytes,
    'index before has one freeblock' => static fn (): mixed => count($indexFixture()['before_blocks']),
    'index before freeblock size' => static fn (): mixed => $indexFixture()['before_blocks'][0]->size,
    'index before current next fragment bytes' => static fn (): mixed => $indexFixture()['before']->freeblockFragmentReport($indexFixture()['page'])['current_next_fragment_bytes'],
    'index deleted cell offset follows two byte fragment' => static fn (): mixed => $indexFixture()['cell_offset'] - $indexFixture()['before_blocks'][0]->endOffset(),
    'index after cell count' => static fn (): mixed => $indexFixture()['after']->cellCount,
    'index after fragmented bytes consumed' => static fn (): mixed => $indexFixture()['after']->fragmentedFreeBytes,
    'index after has one coalesced freeblock' => static fn (): mixed => count($indexFixture()['after_blocks']),
    'index after freeblock offset preserved' => static fn (): mixed => $indexFixture()['after_blocks'][0]->offset,
    'index after freeblock size absorbs fragment and cell' => static fn (): mixed => $indexFixture()['after_blocks'][0]->size,
    'index after freeblock end reaches page end' => static fn (): mixed => $indexFixture()['after_blocks'][0]->endOffset(),
    'index after freeblock next remains null' => static fn (): mixed => $indexFixture()['after_blocks'][0]->nextOffset,
    'index after fragment report has no current next gap' => static fn (): mixed => $indexFixture()['after']->freeblockFragmentReport($indexFixture()['mutated'])['current_next_fragment_bytes'],
    'index after integrity remains ok' => static fn (): mixed => $indexFixture()['after']->freeblockIntegrityReport($indexFixture()['mutated'])['status'],
    'index after free space increases by deleted cell and fragment' => static fn (): mixed => $indexFixture()['after']->freeSpaceBytes($indexFixture()['mutated']) - $indexFixture()['before']->freeSpaceBytes($indexFixture()['page']),
    'index secure delete clears merged freeblock payload' => static fn (): mixed => trim(substr($indexFixture(true)['mutated'], $indexFixture(true)['after_blocks'][0]->offset + 4, $indexFixture(true)['after_blocks'][0]->size - 4), "\0") === '',
    'index throws when fragment counter is too small' => static fn (): mixed => $throwsMessage(static fn () => $indexFixture(fragmentedBytes: 1)),
];

$expected = [
    'table before page type' => 'table-leaf',
    'table before cell count' => 1,
    'table before first freeblock offset' => $tableFixture()['freeblock_offset'],
    'table before fragmented bytes' => 2,
    'table before has one freeblock' => 1,
    'table before freeblock size' => 20,
    'table before current next fragment bytes' => 0,
    'table deleted cell offset follows two byte fragment' => 2,
    'table after cell count' => 0,
    'table after fragmented bytes consumed' => 0,
    'table after has one coalesced freeblock' => 1,
    'table after freeblock offset preserved' => $tableFixture()['freeblock_offset'],
    'table after freeblock size absorbs fragment and cell' => 22 + strlen($tableFixture()['cell']),
    'table after freeblock end reaches page end' => 512,
    'table after freeblock next remains null' => null,
    'table after fragment report has no current next gap' => 0,
    'table after integrity remains ok' => 'ok',
    'table after free space increases by deleted cell and fragment' => strlen($tableFixture()['cell']) + 2,
    'table secure delete clears merged freeblock payload' => true,
    'table throws when fragment counter is too small' => 'SQLite table leaf deletion current/next fragments exceed fragmented free byte count',
    'index before page type' => 'index-leaf',
    'index before cell count' => 1,
    'index before first freeblock offset' => $indexFixture()['freeblock_offset'],
    'index before fragmented bytes' => 2,
    'index before has one freeblock' => 1,
    'index before freeblock size' => 20,
    'index before current next fragment bytes' => 0,
    'index deleted cell offset follows two byte fragment' => 2,
    'index after cell count' => 0,
    'index after fragmented bytes consumed' => 0,
    'index after has one coalesced freeblock' => 1,
    'index after freeblock offset preserved' => $indexFixture()['freeblock_offset'],
    'index after freeblock size absorbs fragment and cell' => 22 + strlen($indexFixture()['cell']),
    'index after freeblock end reaches page end' => 512,
    'index after freeblock next remains null' => null,
    'index after fragment report has no current next gap' => 0,
    'index after integrity remains ok' => 'ok',
    'index after free space increases by deleted cell and fragment' => strlen($indexFixture()['cell']) + 2,
    'index secure delete clears merged freeblock payload' => true,
    'index throws when fragment counter is too small' => 'SQLite index leaf deletion current/next fragments exceed fragmented free byte count',
];

$tests = [];
foreach ($cases as $name => $read) {
    $tests['btree mutation apply current next56 ' . $name] = static function (TestRunner $t) use ($read, $expected, $name): void {
        $t->same($expected[$name], $read());
    };
}

return $tests;
