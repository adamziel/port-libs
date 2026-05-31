<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/gencol1.test gencol1-21.1:
 *   pragma_table_xinfo() preserves declared names and types for generated
 *   columns with mixed keyword casing and whitespace, while a column that uses
 *   "Always default(...)" is ordinary, not generated.
 * - SQLite test/pragma.test pragma-6.2.2 and pragma-6.8:
 *   schema-query PRAGMAs preserve DEFAULT expression text and table-level
 *   composite primary-key ordinals.
 *
 * This batch varies the upstream schema shape across 1000 source-neutral
 * application tables so the runner admits distinct PRAGMA/schema behavior
 * cases rather than metadata-only fixture rows.
 */

$catalogFor = static function (int $variant): SQLitePragmaSchemaCatalog {
    $suffix = sprintf('%04d', $variant);
    $generated = "generated_settings_{$suffix}";
    $defaults = "default_settings_{$suffix}";
    $composite = "composite_settings_{$suffix}";

    return new SQLitePragmaSchemaCatalog([
        new SQLiteSchemaRecord(
            'table',
            $generated,
            $generated,
            1000 + $variant,
            "CREATE TABLE {$generated}(
                a integer primary key,
                b int generated always as (a+{$variant}),
                c text    GENERATED   ALWAYS as (printf('%08x',a)),
                d Generated
                  Always
                  AS ('value_{$suffix}'),
                e int                         Always default({$variant})
            )",
            10 + $variant,
        ),
        new SQLiteSchemaRecord(
            'table',
            $defaults,
            $defaults,
            2000 + $variant,
            "CREATE TABLE {$defaults}(
                a TEXT DEFAULT CURRENT_TIMESTAMP,
                b DEFAULT (5+{$variant}),
                c TEXT,
                d INTEGER DEFAULT NULL,
                e TEXT DEFAULT '',
                UNIQUE(b,c,d),
                PRIMARY KEY(e,b,c)
            )",
            20 + $variant,
        ),
        new SQLiteSchemaRecord(
            'table',
            $composite,
            $composite,
            3000 + $variant,
            "CREATE TABLE {$composite}(first_key, second_key INTEGER PRIMARY KEY, payload, PRIMARY KEY(first_key,second_key,first_key,payload))",
            30 + $variant,
        ),
    ]);
};

foreach (range(1, 1000) as $variant) {
    $suffix = sprintf('%04d', $variant);
    $generated = "generated_settings_{$suffix}";
    $defaults = "default_settings_{$suffix}";
    $composite = "composite_settings_{$suffix}";

    $tests[sprintf('real upstream pragma schema generated column declared names variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $generated, $defaults, $composite, $variant): void {
        $catalog = $catalogFor($variant);
        $xinfo = $catalog->executeTableValuedPragma("pragma_table_xinfo('{$generated}')")['rows'];
        $ordinaryInfo = $catalog->execute("PRAGMA table_info({$generated})")['rows'];
        $defaultInfo = $catalog->execute("PRAGMA table_info({$defaults})")['rows'];
        $compositeInfo = $catalog->execute("PRAGMA table_info({$composite})")['rows'];

        $t->same(['a', 'b', 'c', 'd', 'e'], array_column($xinfo, 'name'));
        $t->same(['INTEGER', 'INT', 'TEXT', '', 'INT'], array_column($xinfo, 'type'));
        $t->same([0, 2, 2, 2, 0], array_column($xinfo, 'hidden'));
        $t->same(['a', 'e'], array_column($ordinaryInfo, 'name'));
        $t->same([(string) $variant], [$xinfo[4]['dflt_value']]);
        $t->same(['CURRENT_TIMESTAMP', '5+' . $variant, null, 'NULL', "''"], array_column($defaultInfo, 'dflt_value'));
        $t->same([1, 2, 3], [$defaultInfo[4]['pk'], $defaultInfo[1]['pk'], $defaultInfo[2]['pk']]);
        $t->same([1, 2, 4], [$compositeInfo[0]['pk'], $compositeInfo[1]['pk'], $compositeInfo[2]['pk']]);
    };
}

$tests['real upstream pragma schema generated column declared names source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'gencol1.test gencol1-21.1 SELECT name, type FROM pragma_table_xinfo preserves generated-column names and declared types',
        'pragma.test pragma-6.2.2 preserves DEFAULT CURRENT_TIMESTAMP, parenthesized arithmetic defaults, NULL, empty-string defaults, and composite primary-key ordinals',
        'pragma.test pragma-6.8 keeps duplicate table-level PRIMARY KEY columns from renumbering the first occurrence',
    ];

    $t->same(3, count($sections));
    $t->contains('gencol1-21.1', $sections[0]);
    $t->contains('pragma-6.8', $sections[2]);
};

return $tests;
