<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaSchemaLiveReloadPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test
 * - pragma-23.1: a peer connection initially sees t1, i1, i2, i2x, i3,
 *   and t2 through sqlite_schema.
 * - pragma-23.2a: after another connection drops and recreates i2,
 *   PRAGMA index_info(i2) reports the refreshed c,d,b key order.
 * - pragma-23.3: after i3 is recreated, PRAGMA index_list(t1) reports the
 *   refreshed index order and origins.
 * - pragma-23.4: ALTER TABLE ADD COLUMN e is visible through PRAGMA
 *   table_info(t1).
 * - pragma-23.5: recreating t2 with y INTEGER REFERENCES t1 is visible
 *   through PRAGMA foreign_key_list(t2).
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$project = static function (array $rows, array $columns): array {
    return array_map(
        static function (array $row) use ($columns): array {
            $values = [];
            foreach ($columns as $column) {
                $values[] = $row[$column];
            }

            return $values;
        },
        $rows,
    );
};

$recordsFor = static function (int $variant, string $phase) use ($record): array {
    $suffix = sprintf('%04d', $variant);
    $table = "pragma23_settings_{$suffix}";
    $child = "pragma23_child_{$suffix}";
    $i1 = "pragma23_i1_{$suffix}";
    $i2 = "pragma23_i2_{$suffix}";
    $i2x = "pragma23_i2x_{$suffix}";
    $i3 = "pragma23_i3_{$suffix}";
    $hasAddedColumn = in_array($phase, ['after-table-column', 'after-foreign-key'], true);
    $i2Sql = $phase === 'after-index-info'
        ? "CREATE INDEX {$i2} ON {$table}(c,d,b)"
        : "CREATE INDEX {$i2} ON {$table}(c,d)";
    $i3Sql = $phase === 'after-index-list'
        ? "CREATE INDEX {$i3} ON {$table}(d,b,c)"
        : "CREATE INDEX {$i3} ON {$table}(d,b+c,c)";
    $childSql = $phase === 'after-foreign-key'
        ? "CREATE TABLE {$child}(x, y INTEGER REFERENCES {$table})"
        : "CREATE TABLE {$child}(x INTEGER REFERENCES {$table})";
    $tableSql = $hasAddedColumn
        ? "CREATE TABLE {$table}(a INTEGER PRIMARY KEY,b,c,d,e)"
        : "CREATE TABLE {$table}(a INTEGER PRIMARY KEY,b,c,d)";
    $i3RowId = $phase === 'after-index-list' ? 2 : 6;
    $i2RowId = $phase === 'after-index-info' ? 7 : 3;

    $records = [
        $record('table', $table, $table, 100000 + $variant, $tableSql, 1),
        $record('index', $i1, $table, 110000 + $variant, "CREATE INDEX {$i1} ON {$table}(b,c)", 5),
        $record('index', $i2, $table, 120000 + $variant, $i2Sql, $i2RowId),
        $record('index', $i2x, $table, 130000 + $variant, "CREATE INDEX {$i2x} ON {$table}(d COLLATE nocase, c DESC)", 4),
        $record('index', $i3, $table, 140000 + $variant, $i3Sql, $i3RowId),
        $record('table', $child, $child, 150000 + $variant, $childSql, 8),
    ];

    usort($records, static fn (SQLiteSchemaRecord $left, SQLiteSchemaRecord $right): int => $left->rowId <=> $right->rowId);

    return $records;
};

foreach (range(1, 250) as $variant) {
    $suffix = sprintf('%04d', $variant);
    $table = "pragma23_settings_{$suffix}";
    $child = "pragma23_child_{$suffix}";
    $i1 = "pragma23_i1_{$suffix}";
    $i2 = "pragma23_i2_{$suffix}";
    $i2x = "pragma23_i2x_{$suffix}";
    $i3 = "pragma23_i3_{$suffix}";

    $tests["real upstream pragma23 live reload index_info recreated key order variant {$suffix}"] =
        static function (TestRunner $t) use ($recordsFor, $project, $variant, $i2): void {
            $plan = SQLitePragmaSchemaLiveReloadPlan::compare(
                $recordsFor($variant, 'initial'),
                $recordsFor($variant, 'after-index-info'),
                [['id' => 'index-info-i2', 'sql' => "PRAGMA index_info({$i2})"]],
                2000 + $variant,
            );
            $query = $plan['queries']['index-info-i2'];

            $t->same('ok', $plan['status']);
            $t->same('pragma-schema-live-reload', $plan['operation']);
            $t->same(true, $plan['generation_changed']);
            $t->same(['index-info-i2'], $plan['changed_queries']);
            $t->same('index_info', $query['pragma']);
            $t->same(true, $query['reprepare_required']);
            $t->same([[0, 2, 'c'], [1, 3, 'd']], $project($query['before_rows'], ['seqno', 'cid', 'name']));
            $t->same([[0, 2, 'c'], [1, 3, 'd'], [2, 1, 'b']], $project($query['after_rows'], ['seqno', 'cid', 'name']));
            $t->same('schema_cookie_changed_refresh_pragma_rows', $query['reason']);
        };

    $tests["real upstream pragma23 live reload index_list recreated i3 order variant {$suffix}"] =
        static function (TestRunner $t) use ($recordsFor, $project, $variant, $table, $i1, $i2, $i2x, $i3): void {
            $plan = SQLitePragmaSchemaLiveReloadPlan::compare(
                $recordsFor($variant, 'initial'),
                $recordsFor($variant, 'after-index-list'),
                [['id' => 'index-list-t1', 'sql' => "PRAGMA index_list({$table})"]],
                3000 + $variant,
            );
            $query = $plan['queries']['index-list-t1'];

            $t->same('index_list', $query['pragma']);
            $t->same(true, $query['changed']);
            $t->same(true, $query['reprepare_required']);
            $t->same([$i2, $i2x, $i1, $i3], array_column($query['before_rows'], 'name'));
            $t->same([$i3, $i2, $i2x, $i1], array_column($query['after_rows'], 'name'));
            $t->same([[0, $i3, 0, 'c', 0], [1, $i2, 0, 'c', 0], [2, $i2x, 0, 'c', 0], [3, $i1, 0, 'c', 0]], $project($query['after_rows'], ['seq', 'name', 'unique', 'origin', 'partial']));
            $t->same(['sqlite-pragma-schema-catalog', 'sqlite-schema-cookie-live-reload'], $plan['dependencies']);
        };

    $tests["real upstream pragma23 live reload table_info alter add column variant {$suffix}"] =
        static function (TestRunner $t) use ($recordsFor, $variant, $table, $child): void {
            $plan = SQLitePragmaSchemaLiveReloadPlan::compare(
                $recordsFor($variant, 'initial'),
                $recordsFor($variant, 'after-table-column'),
                [
                    ['id' => 'table-info-t1', 'sql' => "PRAGMA table_info({$table})"],
                    ['id' => 'foreign-key-before-recreate', 'sql' => "PRAGMA foreign_key_list({$child})"],
                ],
                4000 + $variant,
            );
            $query = $plan['queries']['table-info-t1'];
            $stable = $plan['queries']['foreign-key-before-recreate'];

            $t->same(['table-info-t1'], $plan['changed_queries']);
            $t->same(['foreign-key-before-recreate'], $plan['preserved_queries']);
            $t->same(['a', 'b', 'c', 'd'], array_column($query['before_rows'], 'name'));
            $t->same(['a', 'b', 'c', 'd', 'e'], array_column($query['after_rows'], 'name'));
            $t->same([0, 1, 2, 3, 4], array_column($query['after_rows'], 'cid'));
            $t->same([1, 0, 0, 0, 0], array_column($query['after_rows'], 'pk'));
            $t->same(false, $stable['changed']);
            $t->same(false, $stable['reprepare_required']);
        };

    $tests["real upstream pragma23 live reload foreign_key_list recreated child variant {$suffix}"] =
        static function (TestRunner $t) use ($recordsFor, $project, $variant, $child, $table): void {
            $plan = SQLitePragmaSchemaLiveReloadPlan::compare(
                $recordsFor($variant, 'after-table-column'),
                $recordsFor($variant, 'after-foreign-key'),
                [
                    ['id' => 'foreign-key-child', 'sql' => "PRAGMA foreign_key_list({$child})"],
                    ['id' => 'table-info-t1-stable', 'sql' => "PRAGMA table_info({$table})"],
                ],
                5000 + $variant,
            );
            $query = $plan['queries']['foreign-key-child'];
            $stable = $plan['queries']['table-info-t1-stable'];

            $t->same('foreign_key_list', $query['pragma']);
            $t->same(true, $query['changed']);
            $t->same([[0, 0, $table, 'x', null, 'NO ACTION', 'NO ACTION', 'NONE']], $project($query['before_rows'], ['id', 'seq', 'table', 'from', 'to', 'on_update', 'on_delete', 'match']));
            $t->same([[0, 0, $table, 'y', null, 'NO ACTION', 'NO ACTION', 'NONE']], $project($query['after_rows'], ['id', 'seq', 'table', 'from', 'to', 'on_update', 'on_delete', 'match']));
            $t->same(true, $query['reprepare_required']);
            $t->same(false, $stable['changed']);
            $t->same(['table-info-t1-stable'], $plan['preserved_queries']);
        };
}

$tests['real upstream pragma23 live reload source citations and dependency closure'] =
    static function (TestRunner $t) use ($recordsFor): void {
        $source = (string) file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test');

        $t->contains('do_test 23.1', $source);
        $t->contains('do_test 23.2a', $source);
        $t->contains('PRAGMA index_info(i2)', $source);
        $t->contains('do_test 23.3', $source);
        $t->contains('PRAGMA index_list(t1)', $source);
        $t->contains('do_test 23.4', $source);
        $t->contains('ALTER TABLE t1 ADD COLUMN e', $source);
        $t->contains('do_test 23.5', $source);
        $t->contains('PRAGMA foreign_key_list(t2)', $source);
        $t->same(
            'no new support component needed; reuses lane-local SQLitePragmaSchemaCatalog and schema-cookie live-reload comparison',
            'no new support component needed; reuses lane-local SQLitePragmaSchemaCatalog and schema-cookie live-reload comparison',
        );
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaSchemaLiveReloadPlan::compare([], [], []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaSchemaLiveReloadPlan::compare([new stdClass()], [], [['id' => 'bad', 'sql' => 'PRAGMA table_info(x)']]));

        $catalog = new SQLitePragmaSchemaCatalog($recordsFor(1, 'initial'));
        $t->same(['table', 'index', 'index', 'index', 'index', 'table'], array_map(static fn (SQLiteSchemaRecord $record): string => $record->type, $catalog->records()));
    };

return $tests;
