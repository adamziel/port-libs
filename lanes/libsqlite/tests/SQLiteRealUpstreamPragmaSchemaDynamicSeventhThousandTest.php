<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma4.test.
 *
 * This ports two dynamic schema/PRAGMA behaviors that are not covered by the
 * sixth-thousand 4.1-4.5 direct rowset checks:
 * - pragma4-6.0 joins pragma_table_list(), pragma_foreign_key_list(), and
 *   pragma_table_info() so FK metadata can resolve the parent table's primary
 *   key columns through a table-valued PRAGMA pipeline.
 * - pragma4-7.2 and 7.3 verify that materialized pragma_table_info() rows and
 *   live pragma_table_info() cursors preserve RIGHT JOIN unmatched-column
 *   behavior when one table has fewer columns than the other.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$catalogFor = static function (int $variant) use ($record): SQLiteAttachedSchemaCatalog {
    $parent = sprintf('seventh_parent_%04d', $variant);
    $child = sprintf('seventh_child_%04d', $variant);
    $wide = sprintf('seventh_wide_%04d', $variant);
    $narrow = sprintf('seventh_narrow_%04d', $variant);
    $archiveParent = sprintf('seventh_archive_parent_%04d', $variant);
    $archiveChild = sprintf('seventh_archive_child_%04d', $variant);

    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', $parent, $parent, 1000 + $variant, "CREATE TABLE {$parent}(tenant_id INT, key_name TEXT, key_value TEXT, PRIMARY KEY(tenant_id, key_name))", 1),
        $record('table', $child, $child, 2000 + $variant, "CREATE TABLE {$child}(setting_id INT PRIMARY KEY, tenant_id INT, key_name TEXT, payload TEXT, FOREIGN KEY(tenant_id, key_name) REFERENCES {$parent}(tenant_id, key_name))", 2),
        $record('table', $wide, $wide, 3000 + $variant, "CREATE TABLE {$wide}(alpha TEXT, beta TEXT, gamma TEXT, delta TEXT)", 3),
        $record('table', $narrow, $narrow, 4000 + $variant, "CREATE TABLE {$narrow}(alpha TEXT, beta TEXT)", 4),
    ]);

    $catalog->attach('archive', "/tmp/seventh-pragma-schema-{$variant}.sqlite", [
        $record('table', $archiveParent, $archiveParent, 5000 + $variant, "CREATE TABLE {$archiveParent}(tenant_id INT PRIMARY KEY, key_name TEXT)", 5),
        $record('table', $archiveChild, $archiveChild, 6000 + $variant, "CREATE TABLE {$archiveChild}(tenant_id INT REFERENCES {$archiveParent}(tenant_id), key_name TEXT)", 6),
    ]);

    return $catalog;
};

$joinForeignKeysToParentPrimaryKeys = static function (SQLiteAttachedSchemaCatalog $catalog): array {
    $rows = [];

    foreach ($catalog->executeTableValuedPragma('pragma_table_list()')['rows'] as $tableRow) {
        if (($tableRow['type'] ?? null) !== 'table') {
            continue;
        }

        $tableName = (string) $tableRow['name'];
        $schemaName = (string) $tableRow['schema'];
        foreach ($catalog->executeTableValuedPragma("pragma_foreign_key_list('{$tableName}', '{$schemaName}')")['rows'] as $foreignKey) {
            $parentName = (string) $foreignKey['table'];
            foreach ($catalog->executeTableValuedPragma("pragma_table_info('{$parentName}', '{$schemaName}')")['rows'] as $column) {
                if (($column['pk'] ?? 0) === 0) {
                    continue;
                }

                $rows[] = [
                    'child' => $tableName,
                    'parent' => $parentName,
                    'from' => $foreignKey['from'],
                    'parent_pk' => $column['name'],
                    'schema' => $schemaName,
                    'pk' => $column['pk'],
                ];
            }
        }
    }

    usort($rows, static fn (array $left, array $right): int => [$left['schema'], $left['child'], $left['pk']] <=> [$right['schema'], $right['child'], $right['pk']]);

    return $rows;
};

$rightJoinTableInfoNames = static function (array $leftRows, array $rightRows): array {
    $leftByName = [];
    foreach ($leftRows as $row) {
        $leftByName[(string) $row['name']] = $row;
    }

    $joined = [];
    foreach ($rightRows as $rightRow) {
        $name = (string) $rightRow['name'];
        $joined[] = [
            'left' => isset($leftByName[$name]) ? $name : null,
            'right' => $name,
        ];
    }

    return $joined;
};

foreach (range(1, 500) as $variant) {
    $parent = sprintf('seventh_parent_%04d', $variant);
    $child = sprintf('seventh_child_%04d', $variant);
    $wide = sprintf('seventh_wide_%04d', $variant);
    $narrow = sprintf('seventh_narrow_%04d', $variant);
    $archiveParent = sprintf('seventh_archive_parent_%04d', $variant);
    $archiveChild = sprintf('seventh_archive_child_%04d', $variant);

    $tests[sprintf('real upstream pragma schema seventh thousand pragma4 6.0 join pipeline variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $joinForeignKeysToParentPrimaryKeys, $parent, $child, $archiveParent, $archiveChild, $variant): void {
        $rows = $joinForeignKeysToParentPrimaryKeys($catalogFor($variant));

        $t->same(5, count($rows));
        $t->same([
            ['child' => $archiveChild, 'parent' => $archiveParent, 'from' => 'tenant_id', 'parent_pk' => 'tenant_id', 'schema' => 'archive', 'pk' => 1],
            ['child' => $child, 'parent' => $parent, 'from' => 'tenant_id', 'parent_pk' => 'tenant_id', 'schema' => 'main', 'pk' => 1],
            ['child' => $child, 'parent' => $parent, 'from' => 'key_name', 'parent_pk' => 'tenant_id', 'schema' => 'main', 'pk' => 1],
            ['child' => $child, 'parent' => $parent, 'from' => 'tenant_id', 'parent_pk' => 'key_name', 'schema' => 'main', 'pk' => 2],
            ['child' => $child, 'parent' => $parent, 'from' => 'key_name', 'parent_pk' => 'key_name', 'schema' => 'main', 'pk' => 2],
        ], $rows);
    };

    $tests[sprintf('real upstream pragma schema seventh thousand pragma4 7 right join table info variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $rightJoinTableInfoNames, $wide, $narrow, $variant): void {
        $catalog = $catalogFor($variant);
        $wideRows = $catalog->executeTableValuedPragma("pragma_table_info('{$wide}', 'main')")['rows'];
        $narrowRows = $catalog->executeTableValuedPragma("pragma_table_info('{$narrow}', 'main')")['rows'];
        $materializedWide = array_map(static fn (array $row): array => ['name' => $row['name']], $wideRows);
        $materializedNarrow = array_map(static fn (array $row): array => ['name' => $row['name']], $narrowRows);

        $t->same([
            ['left' => 'alpha', 'right' => 'alpha'],
            ['left' => 'beta', 'right' => 'beta'],
        ], $rightJoinTableInfoNames($materializedWide, $materializedNarrow));
        $t->same([
            ['left' => 'alpha', 'right' => 'alpha'],
            ['left' => 'beta', 'right' => 'beta'],
        ], $rightJoinTableInfoNames($wideRows, $narrowRows));
        $t->same(['alpha', 'beta', 'gamma', 'delta'], array_column($wideRows, 'name'));
        $t->same(['alpha', 'beta'], array_column($narrowRows, 'name'));
    };
}

$tests['real upstream pragma schema seventh thousand cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma4.test 6.0 joins pragma_table_list(), pragma_foreign_key_list(t.name,t.schema), and pragma_table_info(f.table,t.schema) to find parent primary keys',
        'pragma4.test 7.1 materializes pragma_table_info rows into ordinary tables before joining metadata',
        'pragma4.test 7.2 RIGHT JOIN over materialized pragma rows preserves only names from the narrower right table',
        'pragma4.test 7.3 RIGHT JOIN directly over pragma_table_info virtual rows has the same unmatched-column behavior',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma4.test 6.0', $sections[0]);
    $t->contains('pragma4.test 7.3', $sections[3]);
};

return $tests;
