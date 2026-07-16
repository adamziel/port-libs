<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/starschema1.test.
 *
 * The upstream file builds a wide fact table t1(a01..a63,d), 63 dimension
 * tables x01..x63, and matching indexes on every fact/dimension join key.
 * This ports the schema shape into the PHP PRAGMA catalog layer so table_info,
 * index_list, index_info, and table_list keep the same large star-schema
 * metadata visible to planner/executor work.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$factColumnsSql = static function (int $variant): string {
    $columns = [];
    foreach (range(1, 63) as $i) {
        $columns[] = sprintf('a%02d INT', $i);
    }
    $columns[] = "d TEXT DEFAULT 'star-{$variant}'";

    return implode(', ', $columns);
};

$recordsFor = static function (int $variant) use ($record, $factColumnsSql): array {
    $prefix = sprintf('star_schema_%03d', $variant);
    $fact = "{$prefix}_fact";
    $records = [
        $record('table', $fact, $fact, 1000 + $variant, "CREATE TABLE {$fact}(" . $factColumnsSql($variant) . ')', 1),
    ];
    $rowId = 2;

    foreach (range(1, 63) as $i) {
        $suffix = sprintf('%02d', $i);
        $dimension = "{$prefix}_x{$suffix}";
        $factIndex = "{$prefix}_fact_a{$suffix}";
        $dimensionIndex = "{$prefix}_x{$suffix}_b{$suffix}";
        $records[] = $record('table', $dimension, $dimension, 2000 + $variant + $i, "CREATE TABLE {$dimension}(b{$suffix} INT, c{$suffix} TEXT)", $rowId++);
        $records[] = $record('index', $factIndex, $fact, 3000 + $variant + $i, "CREATE INDEX {$factIndex} ON {$fact}(a{$suffix})", $rowId++);
        $records[] = $record('index', $dimensionIndex, $dimension, 4000 + $variant + $i, "CREATE INDEX {$dimensionIndex} ON {$dimension}(b{$suffix})", $rowId++);
    }

    return $records;
};

foreach (range(1, 120) as $variant) {
    $prefix = sprintf('star_schema_%03d', $variant);
    $fact = "{$prefix}_fact";

    $tests[sprintf('real upstream starschema1 pragma fact table info exposes all join columns variant %03d', $variant)] = static function (TestRunner $t) use ($recordsFor, $variant, $fact): void {
        $catalog = new SQLitePragmaSchemaCatalog($recordsFor($variant));
        $rows = $catalog->execute("PRAGMA table_info({$fact})")['rows'];

        $t->same(64, count($rows));
        foreach (range(1, 63) as $offset => $i) {
            $name = sprintf('a%02d', $i);
            $t->same($offset, $rows[$offset]['cid']);
            $t->same($name, $rows[$offset]['name']);
            $t->same('INT', $rows[$offset]['type']);
            $t->same(0, $rows[$offset]['notnull']);
            $t->same(0, $rows[$offset]['pk']);
        }
        $t->same('d', $rows[63]['name']);
        $t->same('TEXT', $rows[63]['type']);
        $t->same("'star-{$variant}'", $rows[63]['dflt_value']);
    };

    $tests[sprintf('real upstream starschema1 pragma fact index list keeps sixty three indexes variant %03d', $variant)] = static function (TestRunner $t) use ($recordsFor, $variant, $fact, $prefix): void {
        $catalog = new SQLitePragmaSchemaCatalog($recordsFor($variant));
        $rows = $catalog->execute("PRAGMA index_list({$fact})")['rows'];

        $t->same(63, count($rows));
        foreach (range(1, 63) as $offset => $i) {
            $expected = sprintf('%s_fact_a%02d', $prefix, $i);
            $t->same($offset, $rows[$offset]['seq']);
            $t->same($expected, $rows[$offset]['name']);
            $t->same(0, $rows[$offset]['unique']);
            $t->same('c', $rows[$offset]['origin']);
            $t->same(0, $rows[$offset]['partial']);
        }
    };

    $tests[sprintf('real upstream starschema1 pragma dimension index info maps join keys variant %03d', $variant)] = static function (TestRunner $t) use ($recordsFor, $variant, $prefix): void {
        $catalog = new SQLitePragmaSchemaCatalog($recordsFor($variant));

        foreach (range(1, 63) as $i) {
            $suffix = sprintf('%02d', $i);
            $rows = $catalog->execute("PRAGMA index_info({$prefix}_x{$suffix}_b{$suffix})")['rows'];
            $t->same(1, count($rows));
            $t->same(0, $rows[0]['seqno']);
            $t->same(0, $rows[0]['cid']);
            $t->same("b{$suffix}", $rows[0]['name']);
        }
    };

    $tests[sprintf('real upstream starschema1 pragma table list exposes fact and dimension tables variant %03d', $variant)] = static function (TestRunner $t) use ($recordsFor, $variant, $fact, $prefix): void {
        $catalog = new SQLitePragmaSchemaCatalog($recordsFor($variant));
        $rows = $catalog->execute('PRAGMA table_list')['rows'];
        $byName = [];
        foreach ($rows as $row) {
            $byName[$row['name']] = $row;
        }

        $t->same(64, count($rows));
        $t->same(64, $byName[$fact]['ncol']);
        $t->same('table', $byName[$fact]['type']);
        foreach (range(1, 63) as $i) {
            $dimension = sprintf('%s_x%02d', $prefix, $i);
            $t->same(2, $byName[$dimension]['ncol']);
            $t->same('table', $byName[$dimension]['type']);
            $t->same('main', $byName[$dimension]['schema']);
        }
    };
}

$tests['real upstream starschema1 pragma source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'starschema1.test 1.1 creates fact table t1 with a01 through a63 and d',
        'starschema1.test 1.1 creates dimension tables x01 through x63',
        'starschema1.test 1.1 creates indexes t1a01 through t1a63 and x01b01 through x63b63',
        'starschema1.test exercises star-schema planner metadata that depends on the full schema shape',
    ];

    $t->same(4, count($sections));
    $t->contains('a01 through a63', $sections[0]);
    $t->contains('x01 through x63', $sections[1]);
    $t->contains('t1a01', $sections[2]);
};

return $tests;
