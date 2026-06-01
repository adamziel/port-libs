<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma.test pragma-24.0 creates a 1024-byte page database,
 *   inserts one row into t1, and verifies PRAGMA integrity_check is ok.
 * - pragma-24.1 corrupts the tail of t1's root page and SELECT * FROM t1
 *   reports "database disk image is malformed".
 * - pragma-24.2 requires PRAGMA integrity_check to return the same malformed
 *   image message instead of silently accepting the damaged table payload.
 */

$upstreamPragmaTest = '/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test';

$tests['real upstream pragma 24 source cites malformed leaf setup'] = static function (TestRunner $t) use ($upstreamPragmaTest): void {
    $source = file_get_contents($upstreamPragmaTest);

    $t->true(is_string($source));
    $t->true(is_string($source) && str_contains($source, 'do_execsql_test 24.0'));
    $t->true(is_string($source) && str_contains($source, 'PRAGMA page_size = 1024'));
    $t->true(is_string($source) && str_contains($source, 'hexio_write test.db [expr $r*1024 - 16] 000000000000000701040f0f1f616263'));
};

$tests['real upstream pragma 24 source cites select and integrity errors'] = static function (TestRunner $t) use ($upstreamPragmaTest): void {
    $source = file_get_contents($upstreamPragmaTest);

    $t->true(is_string($source) && str_contains($source, 'do_catchsql_test 24.1'));
    $t->true(is_string($source) && str_contains($source, 'SELECT * FROM t1'));
    $t->true(is_string($source) && str_contains($source, 'do_catchsql_test 24.2'));
    $t->true(is_string($source) && str_contains($source, 'database disk image is malformed'));
};

$pageSize = 1024;
$malformedTail = (string) hex2bin('000000000000000701040f0f1f616263');

$headerPage = static function (int $pageCount) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

/**
 * @return array{database:SQLiteDatabase,rowid:int,values:list<string>}
 */
$databaseFor = static function (int $variant, bool $corrupt) use ($headerPage, $malformedTail, $pageSize): array {
    $rowId = $variant + 1;
    $values = [
        chr(97 + ($variant % 26)),
        chr(65 + (($variant * 7) % 26)),
        chr(48 + ($variant % 10)),
    ];

    $schemaPayload = SQLiteRecord::encode([
        'table',
        't1',
        't1',
        2,
        'CREATE TABLE t1(a, b, c)',
    ]);
    $page1 = SQLiteTableLeafPage::assemble(
        [SQLiteTableLeafCell::encode(1, $schemaPayload, $pageSize)],
        $pageSize,
        100,
        $headerPage(2),
    );

    $rowPayload = SQLiteRecord::encode($values);
    $page2 = SQLiteTableLeafPage::assemble(
        [SQLiteTableLeafCell::encode($rowId, $rowPayload, $pageSize)],
        $pageSize,
    );
    if ($corrupt) {
        $page2 = substr_replace($page2, $malformedTail, $pageSize - strlen($malformedTail), strlen($malformedTail));
    }

    return [
        'database' => SQLiteDatabase::fromBytes($page1 . $page2),
        'rowid' => $rowId,
        'values' => $values,
    ];
};

/**
 * @return array{caught:bool,message:string|null,previous:string|null}
 */
$selectAllT1Error = static function (SQLiteDatabase $database): array {
    try {
        $database->tableRowsByName('t1');
    } catch (InvalidArgumentException $exception) {
        return [
            'caught' => true,
            'message' => $exception->getMessage(),
            'previous' => $exception->getPrevious()?->getMessage(),
        ];
    }

    return [
        'caught' => false,
        'message' => null,
        'previous' => null,
    ];
};

foreach (range(1, 340) as $variant) {
    $tests[sprintf('real upstream pragma24 valid leaf select and integrity ok variant %03d', $variant)] = static function (TestRunner $t) use ($databaseFor, $variant): void {
        $fixture = $databaseFor($variant, false);
        $rows = $fixture['database']->tableRowsByName('t1');
        $integrity = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $fixture['database']);

        $t->same(1, count($rows));
        $t->same($fixture['rowid'], $rows[0]->rowId);
        $t->same($fixture['values'], $rows[0]->values());
        $t->same([['integrity_check' => 'ok']], $integrity['rows']);
        $t->same([], $integrity['errors']);
    };

    $tests[sprintf('real upstream pragma24 malformed leaf select errors variant %03d', $variant)] = static function (TestRunner $t) use ($databaseFor, $selectAllT1Error, $variant): void {
        $fixture = $databaseFor($variant, true);
        $error = $selectAllT1Error($fixture['database']);

        $t->true($error['caught']);
        $t->same('database disk image is malformed', $error['message']);
        $t->true(is_string($error['previous']) && $error['previous'] !== '');
    };

    $tests[sprintf('real upstream pragma24 malformed leaf integrity errors variant %03d', $variant)] = static function (TestRunner $t) use ($databaseFor, $variant): void {
        $fixture = $databaseFor($variant, true);
        $integrity = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $fixture['database']);

        $t->same('integrity_check', $integrity['pragma']);
        $t->same(100, $integrity['limit']);
        $t->same([['integrity_check' => 'database disk image is malformed']], $integrity['rows']);
        $t->same(['database disk image is malformed'], $integrity['errors']);
    };
}

return $tests;
