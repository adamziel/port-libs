<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma4.test 6.0 joins pragma_table_list(),
 *   pragma_foreign_key_list(t.name,t.schema), and
 *   pragma_table_info(f."table",t.schema) to recover the referenced primary
 *   key column for schema-local foreign keys.
 * - SQLite test/pragma4.test 7.1 through 7.3 materializes
 *   pragma_table_info() rowsets and RIGHT JOINs them, preserving only matching
 *   column names from the right-side pragma row source.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$makeCatalog = static function (int $variant) use ($record): SQLiteAttachedSchemaCatalog {
    $mainParent = "main_parent_{$variant}";
    $mainChild = "main_child_{$variant}";
    $mainPeer = "main_peer_{$variant}";
    $tempChild = "temp_child_{$variant}";
    $auxParent = "aux_parent_{$variant}";
    $auxChild = "aux_child_{$variant}";
    $auxPeer = "aux_peer_{$variant}";
    $auxSchema = "tenant{$variant}";

    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', $mainParent, $mainParent, 10 + $variant, "CREATE TABLE {$mainParent}(a INT PRIMARY KEY, b TEXT, c TEXT)", 100 + $variant),
            $record('table', $mainChild, $mainChild, 20 + $variant, "CREATE TABLE {$mainChild}(d INT PRIMARY KEY, e INT REFERENCES {$mainParent}(a), f TEXT)", 200 + $variant),
            $record('table', $mainPeer, $mainPeer, 30 + $variant, "CREATE TABLE {$mainPeer}(a TEXT, b TEXT)", 300 + $variant),
        ],
        [
            $record('table', $tempChild, $tempChild, 40 + $variant, "CREATE TABLE {$tempChild}(d INT PRIMARY KEY, e TEXT, f TEXT)", 400 + $variant),
        ],
    );

    $catalog->attach($auxSchema, "tenant-{$variant}.sqlite", [
        $record('table', $auxParent, $auxParent, 50 + $variant, "CREATE TABLE {$auxParent}(a INT PRIMARY KEY, b TEXT, c TEXT)", 500 + $variant),
        $record('table', $auxChild, $auxChild, 60 + $variant, "CREATE TABLE {$auxChild}(d INT PRIMARY KEY, e INT REFERENCES {$auxParent}(a), f TEXT)", 600 + $variant),
        $record('table', $auxPeer, $auxPeer, 70 + $variant, "CREATE TABLE {$auxPeer}(a TEXT, b TEXT, c TEXT)", 700 + $variant),
    ]);

    return $catalog;
};

/**
 * @return list<array{schema:string, child:string, parent:string, from:string, pk_column:string, pk:int}>
 */
$foreignKeyPrimaryKeyJoin = static function (SQLiteAttachedSchemaCatalog $catalog): array {
    $joined = [];
    foreach ($catalog->executeTableValuedPragma('pragma_table_list()')['rows'] as $tableRow) {
        if ($tableRow['type'] !== 'table') {
            continue;
        }

        $schema = (string) $tableRow['schema'];
        $tableName = (string) $tableRow['name'];
        $foreignKeys = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$tableName}', '{$schema}')")['rows'];
        foreach ($foreignKeys as $foreignKey) {
            $parent = (string) $foreignKey['table'];
            $parentInfo = $catalog->executeTableValuedPragma("pragma_table_info('{$parent}', '{$schema}')")['rows'];
            foreach ($parentInfo as $column) {
                if ((int) $column['pk'] === 0) {
                    continue;
                }

                $joined[] = [
                    'schema' => $schema,
                    'child' => $tableName,
                    'parent' => $parent,
                    'from' => (string) $foreignKey['from'],
                    'pk_column' => (string) $column['name'],
                    'pk' => (int) $column['pk'],
                ];
            }
        }
    }

    return $joined;
};

/**
 * @return list<array{left:string|null, right:string}>
 */
$rightJoinTableInfoNames = static function (SQLiteAttachedSchemaCatalog $catalog, string $leftTable, string $rightTable, string $schema): array {
    $leftRows = $catalog->executeTableValuedPragma("pragma_table_info('{$leftTable}', '{$schema}')")['rows'];
    $rightRows = $catalog->executeTableValuedPragma("pragma_table_info('{$rightTable}', '{$schema}')")['rows'];
    $leftNames = array_column($leftRows, 'name');

    $joined = [];
    foreach ($rightRows as $rightRow) {
        $rightName = (string) $rightRow['name'];
        $joined[] = [
            'left' => in_array($rightName, $leftNames, true) ? $rightName : null,
            'right' => $rightName,
        ];
    }

    return $joined;
};

foreach (range(1, 1000) as $variant) {
    $tests["real upstream pragma4 dynamic schema pragma join variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $foreignKeyPrimaryKeyJoin, $rightJoinTableInfoNames, $variant): void {
        $catalog = $makeCatalog($variant);
        $auxSchema = "tenant{$variant}";
        $mainParent = "main_parent_{$variant}";
        $mainChild = "main_child_{$variant}";
        $mainPeer = "main_peer_{$variant}";
        $auxParent = "aux_parent_{$variant}";
        $auxChild = "aux_child_{$variant}";
        $auxPeer = "aux_peer_{$variant}";

        $joined = $foreignKeyPrimaryKeyJoin($catalog);
        $mainRightJoin = $rightJoinTableInfoNames($catalog, $mainPeer, $mainParent, 'main');
        $auxRightJoin = $rightJoinTableInfoNames($catalog, $auxPeer, $auxParent, $auxSchema);
        $tableList = $catalog->executeTableValuedPragma('pragma_table_list()')['rows'];

        $t->same(2, count($joined));
        $t->same([
            'schema' => 'main',
            'child' => $mainChild,
            'parent' => $mainParent,
            'from' => 'e',
            'pk_column' => 'a',
            'pk' => 1,
        ], $joined[0]);
        $t->same([
            'schema' => $auxSchema,
            'child' => $auxChild,
            'parent' => $auxParent,
            'from' => 'e',
            'pk_column' => 'a',
            'pk' => 1,
        ], $joined[1]);
        $t->same(['temp', 'main', $auxSchema], array_values(array_unique(array_column($tableList, 'schema'))));
        $t->same(['a', $mainParent], [$mainRightJoin[0]['left'], $mainParent]);
        $t->same([['left' => 'a', 'right' => 'a'], ['left' => 'b', 'right' => 'b'], ['left' => null, 'right' => 'c']], $mainRightJoin);
        $t->same([['left' => 'a', 'right' => 'a'], ['left' => 'b', 'right' => 'b'], ['left' => 'c', 'right' => 'c']], $auxRightJoin);
        $t->same([], $catalog->executeTableValuedPragma("pragma_foreign_key_list('temp_child_{$variant}', 'temp')")['rows']);
        $t->same('main', $catalog->executeTableValuedPragma("pragma_table_info('{$mainParent}', 'main')")['schema']);
        $t->same($auxSchema, $catalog->executeTableValuedPragma("pragma_table_info('{$auxParent}', '{$auxSchema}')")['schema']);
    };
}

$tests['real upstream pragma4 dynamic schema pragma join source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma4.test 6.0 joins pragma_table_list(), pragma_foreign_key_list(t.name,t.schema), and pragma_table_info(f."table",t.schema)',
        'pragma4.test 7.1 through 7.3 materializes pragma_table_info() rowsets and RIGHT JOINs matching column names',
    ];

    $t->same(2, count($sections));
    $t->contains('pragma4.test 6.0', $sections[0]);
    $t->contains('RIGHT JOIN', $sections[1]);
};

return $tests;
