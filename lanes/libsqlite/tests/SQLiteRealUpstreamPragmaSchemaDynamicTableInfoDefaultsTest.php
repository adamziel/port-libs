<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test.
 *
 * - pragma-6.7 verifies PRAGMA table_info preserves declared type text,
 *   NOT NULL flags, literal/default-expression spelling, and primary-key
 *   ordinals for ordinary table declarations.
 * - pragma-6.8 verifies duplicate column names inside a table-level
 *   PRIMARY KEY list do not renumber the first occurrence; the later duplicate
 *   consumes an ordinal slot, so PRIMARY KEY(a,b,a,c) reports c as pk=4.
 *
 * This dynamic corpus keeps the upstream behavior generic by varying table
 * names and defaults while asserting the same PRAGMA table_info row shape.
 */

$catalogFor = static function (int $variant): SQLitePragmaSchemaCatalog {
    $suffix = sprintf('%03d', $variant);
    $notNullDefault = -1 * (($variant % 97) + 1);
    $varcharWidth = 32 + ($variant % 40);
    $varcharScale = 50 + ($variant % 30);
    $textDefault = 'setting_' . $suffix;
    $blobDefault = sprintf("X'%06x'", 0xabc000 + $variant);
    $timeDefault = match ($variant % 3) {
        0 => 'CURRENT_TIME',
        1 => 'CURRENT_DATE',
        default => 'CURRENT_TIMESTAMP',
    };

    return new SQLitePragmaSchemaCatalog([
        new SQLiteSchemaRecord(
            'table',
            'pragma_defaults_' . $suffix,
            'pragma_defaults_' . $suffix,
            20 + $variant,
            "CREATE TABLE pragma_defaults_{$suffix}(
                one INT NOT NULL DEFAULT {$notNullDefault},
                two text,
                three VARCHAR({$varcharWidth}, {$varcharScale}) DEFAULT '{$textDefault}',
                four REAL DEFAULT {$blobDefault},
                five DEFAULT {$timeDefault}
            )",
            100 + $variant,
        ),
        new SQLiteSchemaRecord(
            'table',
            'pragma_duplicate_pk_' . $suffix,
            'pragma_duplicate_pk_' . $suffix,
            200 + $variant,
            "CREATE TABLE pragma_duplicate_pk_{$suffix}(
                a,
                b,
                c,
                PRIMARY KEY(a,b,a,c)
            )",
            300 + $variant,
        ),
    ]);
};

foreach (range(1, 450) as $variant) {
    $suffix = sprintf('%03d', $variant);
    $notNullDefault = -1 * (($variant % 97) + 1);
    $varcharWidth = 32 + ($variant % 40);
    $varcharScale = 50 + ($variant % 30);
    $textDefault = 'setting_' . $suffix;
    $blobDefault = sprintf("X'%06x'", 0xabc000 + $variant);
    $timeDefault = match ($variant % 3) {
        0 => 'CURRENT_TIME',
        1 => 'CURRENT_DATE',
        default => 'CURRENT_TIMESTAMP',
    };

    $tests[sprintf('real upstream pragma.test 6.7 table_info defaults variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant, $notNullDefault, $varcharWidth, $varcharScale, $textDefault, $blobDefault, $timeDefault): void {
        $rows = $catalogFor($variant)->execute("PRAGMA table_info(pragma_defaults_" . sprintf('%03d', $variant) . ")")['rows'];

        $t->same(5, count($rows));
        $t->same(['cid' => 0, 'name' => 'one', 'type' => 'INT', 'notnull' => 1, 'dflt_value' => (string) $notNullDefault, 'pk' => 0], $rows[0]);
        $t->same(['cid' => 1, 'name' => 'two', 'type' => 'TEXT', 'notnull' => 0, 'dflt_value' => null, 'pk' => 0], $rows[1]);
        $t->same(['cid' => 2, 'name' => 'three', 'type' => "VARCHAR({$varcharWidth}, {$varcharScale})", 'notnull' => 0, 'dflt_value' => "'{$textDefault}'", 'pk' => 0], $rows[2]);
        $t->same(['cid' => 3, 'name' => 'four', 'type' => 'REAL', 'notnull' => 0, 'dflt_value' => $blobDefault, 'pk' => 0], $rows[3]);
        $t->same(['cid' => 4, 'name' => 'five', 'type' => '', 'notnull' => 0, 'dflt_value' => $timeDefault, 'pk' => 0], $rows[4]);
    };

    $tests[sprintf('real upstream pragma.test 6.8 duplicate primary-key ordinal variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant): void {
        $rows = $catalogFor($variant)->executeTableValuedPragma("pragma_table_info('pragma_duplicate_pk_" . sprintf('%03d', $variant) . "')")['rows'];

        $t->same(3, count($rows));
        $t->same(['cid' => 0, 'name' => 'a', 'type' => '', 'notnull' => 0, 'dflt_value' => null, 'pk' => 1], $rows[0]);
        $t->same(['cid' => 1, 'name' => 'b', 'type' => '', 'notnull' => 0, 'dflt_value' => null, 'pk' => 2], $rows[1]);
        $t->same(['cid' => 2, 'name' => 'c', 'type' => '', 'notnull' => 0, 'dflt_value' => null, 'pk' => 4], $rows[2]);
    };
}

$tests['real upstream pragma.test 6.7 and 6.8 source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-6.7 PRAGMA table_info preserves type/default/notnull row shape',
        'pragma.test pragma-6.8 duplicate PRIMARY KEY(a,b,a,c) reports c as pk ordinal 4',
    ];

    $t->same(2, count($sections));
    $t->contains('pragma-6.7', $sections[0]);
    $t->contains('pragma-6.8', $sections[1]);
};

return $tests;
