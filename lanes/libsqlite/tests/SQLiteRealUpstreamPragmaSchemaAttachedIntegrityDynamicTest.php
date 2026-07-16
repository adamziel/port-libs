<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaAttachedIntegrityCheck;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test 22.2 through 22.4.
 *
 * Upstream creates one corrupt database and one clean database, attaches them
 * in both roles, and verifies that PRAGMA integrity_check reports errors for
 * the selected schema only. This ports that schema-targeting behavior into the
 * PHP attached-integrity path with dynamic generic database images.
 */

$pageSize = 1024;

$headerPage = static function (int $pageCount) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 2), 44, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$schemaCell = static function (int $rowId, array $values) use ($pageSize): string {
    return SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values), $pageSize);
};

$cleanDatabase = static function (int $variant) use ($headerPage, $schemaCell, $pageSize): string {
    $table = "pragma22_clean_settings_{$variant}";
    $rows = [
        $schemaCell(1, [
            'table',
            $table,
            $table,
            2,
            "CREATE TABLE {$table}(setting_id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT)",
        ]),
    ];

    return SQLiteTableLeafPage::assemble($rows, $pageSize, 100, $headerPage(2))
        . SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([1, "k{$variant}", "v{$variant}"]), $pageSize),
        ], $pageSize);
};

$corruptDatabase = static function (int $variant) use ($cleanDatabase): string {
    $bytes = $cleanDatabase($variant);
    $bytes[18] = "\x09";
    $bytes[19] = "\x08";

    return substr_replace($bytes, pack('N', 99 + $variant), 52, 4);
};

foreach (range(1, 250) as $variant) {
    $tests[sprintf('real upstream pragma.test 22 attached integrity unqualified reports corrupt aux variant %03d', $variant)] = static function (TestRunner $t) use ($cleanDatabase, $corruptDatabase, $variant): void {
        $result = SQLitePragmaAttachedIntegrityCheck::execute('PRAGMA integrity_check', [
            'main' => $cleanDatabase($variant),
            'aux' => $corruptDatabase($variant),
        ]);

        $t->same(['main', 'aux'], $result['checked_schemas']);
        $t->same(4, count($result['errors']));
        $t->contains('*** in database aux ***', $result['errors'][0]);
        $t->contains('invalid schema write version 9', $result['errors'][0]);
        $t->contains('invalid schema read version 8', $result['errors'][1]);
        $t->contains('largest root btree page ' . (99 + $variant) . ' is beyond the database image', implode("\n", $result['errors']));
        $t->same($result['errors'][0], $result['rows'][0]['integrity_check']);
    };

    $tests[sprintf('real upstream pragma.test 22 attached integrity main schema stays ok variant %03d', $variant)] = static function (TestRunner $t) use ($cleanDatabase, $corruptDatabase, $variant): void {
        $result = SQLitePragmaAttachedIntegrityCheck::execute('PRAGMA main.integrity_check', [
            'main' => $cleanDatabase($variant),
            'aux' => $corruptDatabase($variant),
        ]);

        $t->same('main', $result['schema']);
        $t->same(['main'], $result['checked_schemas']);
        $t->same([], $result['errors']);
        $t->same([['integrity_check' => 'ok']], $result['rows']);
    };

    $tests[sprintf('real upstream pragma.test 22 attached integrity aux schema reports aux only variant %03d', $variant)] = static function (TestRunner $t) use ($cleanDatabase, $corruptDatabase, $variant): void {
        $result = SQLitePragmaAttachedIntegrityCheck::execute('PRAGMA aux.integrity_check(2)', [
            'main' => $cleanDatabase($variant),
            'aux' => $corruptDatabase($variant),
        ]);

        $t->same('aux', $result['schema']);
        $t->same(2, $result['limit']);
        $t->same(['aux'], $result['checked_schemas']);
        $t->same(2, count($result['errors']));
        $t->contains('*** in database aux ***', $result['errors'][0]);
        $t->contains('invalid schema write version 9', $result['errors'][0]);
        $t->contains('invalid schema read version 8', $result['errors'][1]);
    };

    $tests[sprintf('real upstream pragma.test 22 attached integrity corrupt main clean aux variant %03d', $variant)] = static function (TestRunner $t) use ($cleanDatabase, $corruptDatabase, $variant): void {
        $result = SQLitePragmaAttachedIntegrityCheck::execute('PRAGMA "aux".quick_check = 1', [
            'main' => $corruptDatabase($variant),
            'aux' => $cleanDatabase($variant),
        ]);

        $t->same('aux', $result['schema']);
        $t->same('quick_check', $result['pragma']);
        $t->same(1, $result['limit']);
        $t->same(['aux'], $result['checked_schemas']);
        $t->same([], $result['errors']);
        $t->same([['quick_check' => 'ok']], $result['rows']);
    };
}

$tests['real upstream pragma.test 22 attached integrity source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test 22.2 unqualified PRAGMA integrity_check reports corruption in main',
        'pragma.test 22.3.1 unqualified PRAGMA integrity_check reports corruption in attached aux',
        'pragma.test 22.3.2 PRAGMA main.integrity_check stays ok when aux is corrupt',
        'pragma.test 22.3.3 PRAGMA aux.integrity_check reports only aux corruption',
        'pragma.test 22.4.1 through 22.4.3 repeat the matrix with corrupt main and clean aux',
    ];

    $t->same(5, count($sections));
    $t->contains('22.2', $sections[0]);
    $t->contains('22.3.3', $sections[3]);
    $t->contains('22.4.3', $sections[4]);
};

return $tests;
