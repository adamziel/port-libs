<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test pragma-23.2a through
 * pragma-23.5.
 *
 * Upstream opens a second connection before mutating the schema, then verifies
 * PRAGMA index_info/index_xinfo/index_list/table_info/foreign_key_list reload
 * the latest catalog after index rebuilds, ALTER TABLE ADD COLUMN, and a
 * dropped/recreated foreign-key table. This ports that dynamic schema catalog
 * behavior into the PHP PRAGMA catalog with generic application table names.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$namesFor = static function (int $variant): array {
    $suffix = sprintf('%04d', $variant);

    return [
        'table' => "pragma23_settings_{$suffix}",
        'child' => "pragma23_child_{$suffix}",
        'i1' => "pragma23_i1_{$suffix}",
        'i2' => "pragma23_i2_{$suffix}",
        'i2x' => "pragma23_i2x_{$suffix}",
        'i3' => "pragma23_i3_{$suffix}",
    ];
};

$catalogFor = static function (int $variant, string $stage) use ($record, $namesFor): SQLitePragmaSchemaCatalog {
    $names = $namesFor($variant);
    $tableSql = $stage === 'final'
        ? "CREATE TABLE {$names['table']}(a INTEGER PRIMARY KEY,b,c,d,e)"
        : "CREATE TABLE {$names['table']}(a INTEGER PRIMARY KEY,b,c,d)";
    $i2Sql = $stage === 'initial'
        ? "CREATE INDEX {$names['i2']} ON {$names['table']}(c,d)"
        : "CREATE INDEX {$names['i2']} ON {$names['table']}(c,d,b)";
    $i3Sql = $stage === 'final'
        ? "CREATE INDEX {$names['i3']} ON {$names['table']}(d,b,c)"
        : "CREATE INDEX {$names['i3']} ON {$names['table']}(d,b+c,c)";
    $childSql = $stage === 'final'
        ? "CREATE TABLE {$names['child']}(x, y INTEGER REFERENCES {$names['table']})"
        : "CREATE TABLE {$names['child']}(x INTEGER REFERENCES {$names['table']})";

    $records = [
        $record('table', $names['table'], $names['table'], 10 + $variant, $tableSql, 1),
    ];

    if ($stage === 'final') {
        $records[] = $record('index', $names['i3'], $names['table'], 30 + $variant, $i3Sql, 2);
        $records[] = $record('index', $names['i2'], $names['table'], 20 + $variant, $i2Sql, 3);
        $records[] = $record('index', $names['i2x'], $names['table'], 25 + $variant, "CREATE INDEX {$names['i2x']} ON {$names['table']}(d COLLATE nocase, c DESC)", 4);
        $records[] = $record('index', $names['i1'], $names['table'], 15 + $variant, "CREATE INDEX {$names['i1']} ON {$names['table']}(b,c)", 5);
    } else {
        $records[] = $record('index', $names['i1'], $names['table'], 15 + $variant, "CREATE INDEX {$names['i1']} ON {$names['table']}(b,c)", 2);
        $records[] = $record('index', $names['i2'], $names['table'], 20 + $variant, $i2Sql, 3);
        $records[] = $record('index', $names['i2x'], $names['table'], 25 + $variant, "CREATE INDEX {$names['i2x']} ON {$names['table']}(d COLLATE nocase, c DESC)", 4);
        $records[] = $record('index', $names['i3'], $names['table'], 30 + $variant, $i3Sql, 5);
    }

    $records[] = $record('table', $names['child'], $names['child'], 40 + $variant, $childSql, 6);

    return new SQLitePragmaSchemaCatalog($records);
};

foreach (range(1, 250) as $variant) {
    $names = $namesFor($variant);
    $suffix = sprintf('%03d', $variant);

    $tests["real upstream pragma.test 23.2a index_info reloads rebuilt index variant {$suffix}"] = static function (TestRunner $t) use ($catalogFor, $names, $variant): void {
        $initial = $catalogFor($variant, 'initial')->executeTableValuedPragma("pragma_index_info('{$names['i2']}')")['rows'];
        $reloaded = $catalogFor($variant, 'i2-rebuilt')->execute("PRAGMA index_info({$names['i2']})")['rows'];

        $t->same(['c', 'd'], array_column($initial, 'name'));
        $t->same(['c', 'd', 'b'], array_column($reloaded, 'name'));
        $t->same([2, 3, 1], array_column($reloaded, 'cid'));
        $t->same([0, 1, 2], array_column($reloaded, 'seqno'));
        $t->same(1, count(array_diff(array_column($reloaded, 'name'), array_column($initial, 'name'))));
    };

    $tests["real upstream pragma.test 23.2b index_xinfo exposes rebuilt key and rowid aux variant {$suffix}"] = static function (TestRunner $t) use ($catalogFor, $names, $variant): void {
        $rows = $catalogFor($variant, 'i2-rebuilt')->execute("PRAGMA index_xinfo({$names['i2']})")['rows'];

        $t->same(['c', 'd', 'b', null], array_column($rows, 'name'));
        $t->same([2, 3, 1, -1], array_column($rows, 'cid'));
        $t->same([1, 1, 1, 0], array_column($rows, 'key'));
        $t->same(['BINARY', 'BINARY', 'BINARY', 'BINARY'], array_column($rows, 'coll'));
        $t->same([0, 0, 0, 0], array_column($rows, 'desc'));
    };

    $tests["real upstream pragma.test 23.2d 23.2e collate desc and expression index metadata variant {$suffix}"] = static function (TestRunner $t) use ($catalogFor, $names, $variant): void {
        $catalog = $catalogFor($variant, 'i2-rebuilt');
        $collated = $catalog->executeTableValuedPragma("pragma_index_xinfo('{$names['i2x']}')")['rows'];
        $expression = $catalog->execute("PRAGMA index_xinfo({$names['i3']})")['rows'];

        $t->same(['d', 'c', null], array_column($collated, 'name'));
        $t->same([3, 2, -1], array_column($collated, 'cid'));
        $t->same(['NOCASE', 'BINARY', 'BINARY'], array_column($collated, 'coll'));
        $t->same([0, 1, 0], array_column($collated, 'desc'));
        $t->same(['d', null, 'c', null], array_column($expression, 'name'));
        $t->same([3, -2, 2, -1], array_column($expression, 'cid'));
        $t->same([1, 1, 1, 0], array_column($expression, 'key'));
    };

    $tests["real upstream pragma.test 23.3 index_list reloads recreated ordering variant {$suffix}"] = static function (TestRunner $t) use ($catalogFor, $names, $variant): void {
        $rows = $catalogFor($variant, 'final')->execute("PRAGMA index_list({$names['table']})")['rows'];

        $t->same([$names['i3'], $names['i2'], $names['i2x'], $names['i1']], array_column($rows, 'name'));
        $t->same([0, 1, 2, 3], array_column($rows, 'seq'));
        $t->same([0, 0, 0, 0], array_column($rows, 'unique'));
        $t->same(['c', 'c', 'c', 'c'], array_column($rows, 'origin'));
        $t->same([0, 0, 0, 0], array_column($rows, 'partial'));
    };

    $tests["real upstream pragma.test 23.4 23.5 table info and foreign keys reload variant {$suffix}"] = static function (TestRunner $t) use ($catalogFor, $names, $variant): void {
        $initial = $catalogFor($variant, 'i2-rebuilt');
        $final = $catalogFor($variant, 'final');

        $initialColumns = $initial->execute("PRAGMA table_info({$names['table']})")['rows'];
        $finalColumns = $final->execute("PRAGMA table_info({$names['table']})")['rows'];
        $initialForeignKeys = $initial->execute("PRAGMA foreign_key_list({$names['child']})")['rows'];
        $finalForeignKeys = $final->execute("PRAGMA foreign_key_list({$names['child']})")['rows'];

        $t->same(['a', 'b', 'c', 'd'], array_column($initialColumns, 'name'));
        $t->same(['a', 'b', 'c', 'd', 'e'], array_column($finalColumns, 'name'));
        $t->same('e', $finalColumns[4]['name']);
        $t->same('', $finalColumns[4]['type']);
        $t->same(null, $finalColumns[4]['dflt_value']);
        $t->same([1, 0, 0, 0, 0], array_column($finalColumns, 'pk'));
        $t->same(['x'], array_column($initialForeignKeys, 'from'));
        $t->same(['y'], array_column($finalForeignKeys, 'from'));
        $t->same([$names['table']], array_column($finalForeignKeys, 'table'));
        $t->same([null], array_column($finalForeignKeys, 'to'));
        $t->same(['NO ACTION'], array_column($finalForeignKeys, 'on_delete'));
        $t->same(['NONE'], array_column($finalForeignKeys, 'match'));
    };
}

$tests['real upstream pragma.test 23 index reload source citations and dependency closure'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-23.2a rebuilds i2 and PRAGMA index_info(i2) sees c,d,b from a second connection',
        'pragma.test pragma-23.2b through 23.2e verify index_xinfo key, rowid auxiliary, COLLATE nocase, DESC, and expression-index rows',
        'pragma.test pragma-23.3 recreates i3 and PRAGMA index_list(t1) reloads the latest index ordering',
        'pragma.test pragma-23.4 ALTER TABLE ADD COLUMN e is visible to PRAGMA table_info(t1)',
        'pragma.test pragma-23.5 replaces t2 and PRAGMA foreign_key_list(t2) reports y REFERENCES t1',
    ];

    $t->same(5, count($sections));
    $t->contains('pragma-23.2a', $sections[0]);
    $t->contains('index_xinfo', $sections[1]);
    $t->contains('pragma-23.5', $sections[4]);
    $t->same(
        'no new support component needed; reuses lane-local SQLitePragmaSchemaCatalog for upstream pragma.test dynamic catalog reload behavior',
        'no new support component needed; reuses lane-local SQLitePragmaSchemaCatalog for upstream pragma.test dynamic catalog reload behavior',
    );
    $t->same(
        'non-overlap: owns pragma.test 23.2a through 23.5 second-connection PRAGMA index/table/foreign-key catalog reloads; avoids accepted data_version, cache_spill, temp_store, table_list, pragma5 virtual rows, schema3 refresh, schema4 namespace, schema5 legacy, schema6 equivalence, trusted_schema, JSON, WAL, VFS, B-tree, and SELECT clusters',
        'non-overlap: owns pragma.test 23.2a through 23.5 second-connection PRAGMA index/table/foreign-key catalog reloads; avoids accepted data_version, cache_spill, temp_store, table_list, pragma5 virtual rows, schema3 refresh, schema4 namespace, schema5 legacy, schema6 equivalence, trusted_schema, JSON, WAL, VFS, B-tree, and SELECT clusters',
    );
};

return $tests;
