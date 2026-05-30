<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLitePragmaSnapshot;
use PortLibs\LibSqlite\SQLiteVacuumPageSizeAutoVacuumPlan;

$makeDatabase = static function (int $pageSize = 1024, int $pageCount = 3, string $autoVacuum = 'none'): SQLiteDatabase {
    $first = str_repeat("\0", $pageSize);
    $first = substr_replace($first, "SQLite format 3\0", 0, 16);
    $first = substr_replace($first, pack('n', $pageSize === 65536 ? 1 : $pageSize), 16, 2);
    $first[18] = "\x01";
    $first[19] = "\x01";
    $first[20] = "\x00";
    $first[21] = "\x40";
    $first[22] = "\x20";
    $first[23] = "\x20";
    $first = substr_replace($first, pack('N', 11), 24, 4);
    $first = substr_replace($first, pack('N', $pageCount), 28, 4);
    $first = substr_replace($first, pack('N', 1), 40, 4);
    $first = substr_replace($first, pack('N', 1), 56, 4);
    if ($autoVacuum !== 'none') {
        $first = substr_replace($first, pack('N', min($pageCount, 3)), 52, 4);
        $first = substr_replace($first, pack('N', $autoVacuum === 'incremental' ? 1 : 0), 64, 4);
    }

    $pages = [$first];
    for ($page = 2; $page <= $pageCount; $page++) {
        $pages[] = str_pad("wp_options-page-{$page};", $pageSize, chr(64 + $page));
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$tests = [];

$pageSizeCases = [
    'keeps source page size when unset' => [1024, null, 1024],
    'rewrites to minimum sqlite page size' => [1024, 512, 512],
    'rewrites to 1k page size' => [512, 1024, 1024],
    'rewrites to 2k page size' => [1024, 2048, 2048],
    'rewrites to 4k page size' => [1024, 4096, 4096],
    'rewrites to 8k page size' => [1024, 8192, 8192],
    'rewrites to 16k page size' => [1024, 16384, 16384],
    'rewrites to 32k page size' => [1024, 32768, 32768],
    'rewrites to 64k page size with header sentinel' => [1024, 65536, 65536],
    'accepts numeric page size text' => [1024, '4096', 4096],
];

foreach ($pageSizeCases as $name => [$sourcePageSize, $targetPageSize, $expectedPageSize]) {
    $tests['vacuum page-size autovacuum corpus page size ' . $name] = static function (TestRunner $t) use ($makeDatabase, $sourcePageSize, $targetPageSize, $expectedPageSize): void {
        $plan = SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase($sourcePageSize, 3), $targetPageSize);
        $header = SQLiteHeader::parse($plan['bytes']);

        $t->same($expectedPageSize, $plan['target_page_size']);
        $t->same($expectedPageSize, $header->pageSize);
    };
}

$pageCountCases = [
    '512 from 1k three pages grows page count' => [1024, 3, 512, 6],
    '2k from 1k three pages rounds up' => [1024, 3, 2048, 2],
    '4k from 1k three pages stays one page image' => [1024, 3, 4096, 1],
    '8k from 512 five pages stays one page image' => [512, 5, 8192, 1],
    'same 2k page size preserves page count' => [2048, 4, 2048, 4],
    '64k image compacts small application copy' => [1024, 7, 65536, 1],
];

foreach ($pageCountCases as $name => [$sourcePageSize, $sourcePageCount, $targetPageSize, $expectedPageCount]) {
    $tests['vacuum page-size autovacuum corpus page count ' . $name] = static function (TestRunner $t) use ($makeDatabase, $sourcePageSize, $sourcePageCount, $targetPageSize, $expectedPageCount): void {
        $plan = SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase($sourcePageSize, $sourcePageCount), $targetPageSize);
        $header = SQLiteHeader::parse($plan['bytes']);

        $t->same($expectedPageCount, $plan['target_page_count']);
        $t->same($expectedPageCount, $header->databaseSizePages);
        $t->same(strlen($plan['bytes']), $expectedPageCount * $targetPageSize);
    };
}

$autoVacuumCases = [
    'preserves none when unset' => ['none', null, 'none', 0, 0],
    'preserves full when unset' => ['full', null, 'full', 0, 3],
    'preserves incremental when unset' => ['incremental', null, 'incremental', 1, 3],
    'enables full by text' => ['none', 'full', 'full', 0, 2],
    'enables full by integer' => ['none', 1, 'full', 0, 2],
    'enables full by on alias' => ['none', 'on', 'full', 0, 2],
    'enables incremental by text' => ['none', 'incremental', 'incremental', 1, 2],
    'enables incremental by integer' => ['full', 2, 'incremental', 1, 3],
    'disables auto vacuum by none' => ['incremental', 'none', 'none', 0, 0],
    'disables auto vacuum by zero' => ['full', 0, 'none', 0, 0],
    'disables auto vacuum by off alias' => ['incremental', 'off', 'none', 0, 0],
];

foreach ($autoVacuumCases as $name => [$sourceMode, $targetMode, $expectedMode, $expectedIncremental, $expectedLargestRoot]) {
    $tests['vacuum page-size autovacuum corpus mode ' . $name] = static function (TestRunner $t) use ($makeDatabase, $sourceMode, $targetMode, $expectedMode, $expectedIncremental, $expectedLargestRoot): void {
        $plan = SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(1024, 4, $sourceMode), null, $targetMode);
        $header = SQLiteHeader::parse($plan['bytes']);
        $snapshot = SQLitePragmaSnapshot::fromDatabase(SQLiteDatabase::fromBytes($plan['bytes']));

        $t->same($expectedMode, $plan['target_auto_vacuum']);
        $t->same($expectedIncremental, $plan['incremental_vacuum']);
        $t->same($expectedLargestRoot, $header->largestRootBtreePage);
        $t->same($expectedMode, $snapshot->value('auto_vacuum'));
    };
}

$operationCases = [
    'reports header rewrite first' => static fn () => SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(), 4096, 'full')['operations'][0]['op'],
    'reports image rewrite second' => static fn () => SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(), 4096, 'full')['operations'][1]['op'],
    'reports page-size vacuum dependency' => static fn () => SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(), 4096)['operations'][2]['op'],
    'omits page-size change operation when unchanged' => static fn () => count(SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(), 1024)['operations']),
    'tracks source page size' => static fn () => SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(2048), 4096)['source_page_size'],
    'tracks source page count' => static fn () => SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(1024, 5), 4096)['source_page_count'],
    'tracks source auto vacuum' => static fn () => SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(1024, 4, 'incremental'), 4096)['source_auto_vacuum'],
    'includes sqlite vacuum dependency' => static fn () => in_array('sqlite-vacuum', SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase())['dependencies'], true),
    'includes page-size dependency' => static fn () => in_array('sqlite-page-size', SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase())['dependencies'], true),
    'includes auto-vacuum dependency' => static fn () => in_array('sqlite-auto-vacuum-header', SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase())['dependencies'], true),
];

$operationExpected = ['rewrite_header', 'rewrite_database_image', 'page_size_change_requires_vacuum', 2, 2048, 5, 'incremental', true, true, true];
foreach (array_values($operationCases) as $index => $callback) {
    $name = array_keys($operationCases)[$index];
    $tests['vacuum page-size autovacuum corpus operations ' . $name] = static function (TestRunner $t) use ($callback, $operationExpected, $index): void {
        $t->same($operationExpected[$index], $callback());
    };
}

$roundTripCases = [
    'rewritten image keeps sqlite magic' => static fn () => substr(SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(), 4096, 'full')['bytes'], 0, 16),
    'rewritten image remains parseable' => static fn () => SQLiteDatabase::fromBytes(SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(), 4096, 'full')['bytes'])->pageCount(),
    'snapshot sees rewritten page size' => static fn () => SQLitePragmaSnapshot::fromDatabase(SQLiteDatabase::fromBytes(SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(), 4096, 'full')['bytes']))->value('page_size'),
    'snapshot sees rewritten page count' => static fn () => SQLitePragmaSnapshot::fromDatabase(SQLiteDatabase::fromBytes(SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(), 2048, 'full')['bytes']))->value('page_count'),
    'snapshot sees incremental flag' => static fn () => SQLitePragmaSnapshot::fromDatabase(SQLiteDatabase::fromBytes(SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(), 2048, 'incremental')['bytes']))->value('incremental_vacuum'),
    'page one preserves payload after header' => static fn () => substr(SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(), 4096, 'full')['bytes'], 100, 16),
    'target bytes are page aligned' => static fn () => strlen(SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(1024, 5), 2048)['bytes']) % 2048,
    'status is ready' => static fn () => SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(), 4096, 'full')['status'],
];

$roundTripExpected = ["SQLite format 3\0", 1, 4096, 2, 1, str_repeat("\0", 16), 0, 'ready'];
foreach (array_values($roundTripCases) as $index => $callback) {
    $name = array_keys($roundTripCases)[$index];
    $tests['vacuum page-size autovacuum corpus round trip ' . $name] = static function (TestRunner $t) use ($callback, $roundTripExpected, $index): void {
        $t->same($roundTripExpected[$index], $callback());
    };
}

$errorCases = [
    'rejects empty page size text' => static fn () => SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(), ''),
    'rejects below minimum page size' => static fn () => SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(), 256),
    'rejects non power two page size' => static fn () => SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(), 3000),
    'rejects above maximum page size' => static fn () => SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(), 131072),
    'rejects unknown auto vacuum text' => static fn () => SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(), null, 'enabled'),
    'rejects unknown auto vacuum integer' => static fn () => SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(), null, 9),
    'rejects empty auto vacuum text' => static fn () => SQLiteVacuumPageSizeAutoVacuumPlan::plan($makeDatabase(), null, ''),
];

foreach ($errorCases as $name => $callback) {
    $tests['vacuum page-size autovacuum corpus errors ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
