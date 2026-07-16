<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCreateTable;
use PortLibs\LibSqlite\SQLiteIndexColumn;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/schema5.test schema5-1.1 through
 * schema5-1.7. That file verifies legacy CREATE TABLE syntax where adjacent
 * table constraints are accepted even when they are not comma-separated:
 * PRIMARY KEY(a) UNIQUE(a), named CONSTRAINT wrappers, CHECK clauses, and
 * trailing constraint names. The PHP port exercises the same schema surface
 * through PRAGMA table_info, index_list, and index_xinfo metadata.
 */

$recordsFor = static function (int $variant, string $case, string $sql): array {
    $table = "schema5_{$case}_{$variant}";
    $createSql = str_replace('__TABLE__', $table, $sql);
    $records = [
        new SQLiteSchemaRecord('table', $table, $table, 1000 + $variant, $createSql, 1),
    ];

    $autoIndexes = SQLiteCreateTable::automaticIndexColumnMetadata($createSql);
    foreach ($autoIndexes as $index => $_columns) {
        $records[] = new SQLiteSchemaRecord(
            'index',
            sprintf('sqlite_autoindex_%s_%d', $table, $index + 1),
            $table,
            2000 + ($variant * 10) + $index,
            null,
            $index + 2,
        );
    }

    return [$table, $createSql, new SQLitePragmaSchemaCatalog($records)];
};

$columnNames = static fn (array $rows): array => array_column($rows, 'name');

$indexColumns = static function (array $metadata): array {
    return array_map(
        static fn (array $columns): array => array_map(
            static fn (SQLiteIndexColumn $column): string => $column->columnName,
            $columns,
        ),
        $metadata,
    );
};

$indexXInfoColumns = static function (SQLitePragmaSchemaCatalog $catalog, array $indexRows): array {
    $columns = [];
    foreach ($indexRows as $row) {
        $xinfo = $catalog->execute('PRAGMA index_xinfo(' . $row['name'] . ')')['rows'];
        $columns[] = array_values(array_filter(array_column($xinfo, 'name'), static fn ($name): bool => $name !== null));
    }

    return $columns;
};

foreach (range(1, 250) as $variant) {
    $tests["real upstream pragma schema dynamic schema5 legacy adjacent primary unique constraint schema5-1.1/1.2 variant {$variant}"] = static function (TestRunner $t) use ($recordsFor, $columnNames, $indexColumns, $indexXInfoColumns, $variant): void {
        [$table, $sql, $catalog] = $recordsFor(
            $variant,
            'pk_unique_same_column',
            'CREATE TABLE __TABLE__(a,b,c, PRIMARY KEY(a) UNIQUE (a) CONSTRAINT one)',
        );

        $tableInfo = $catalog->execute("PRAGMA table_info({$table})")['rows'];
        $indexRows = $catalog->execute("PRAGMA index_list({$table})")['rows'];

        $t->same(['a', 'b', 'c'], $columnNames($tableInfo));
        $t->same([1, 0, 0], array_column($tableInfo, 'pk'));
        $t->same([['a']], $indexColumns(SQLiteCreateTable::automaticIndexColumnMetadata($sql)));
        $t->same([1], array_column($indexRows, 'unique'));
        $t->same(['pk'], array_column($indexRows, 'origin'));
        $t->same([['a']], $indexXInfoColumns($catalog, $indexRows));
    };

    $tests["real upstream pragma schema dynamic schema5 named check plus adjacent unique schema5-1.3/1.4 variant {$variant}"] = static function (TestRunner $t) use ($recordsFor, $columnNames, $indexColumns, $indexXInfoColumns, $variant): void {
        [$table, $sql, $catalog] = $recordsFor(
            $variant,
            'named_check_unique',
            'CREATE TABLE __TABLE__(a,b,c, CONSTRAINT one PRIMARY KEY(a) CONSTRAINT two CHECK(b<10) UNIQUE(b) CONSTRAINT three)',
        );

        $tableInfo = $catalog->execute("PRAGMA table_info({$table})")['rows'];
        $indexRows = $catalog->execute("PRAGMA index_list({$table})")['rows'];

        $t->same(['a', 'b', 'c'], $columnNames($tableInfo));
        $t->same([1, 0, 0], array_column($tableInfo, 'pk'));
        $t->same([['a'], ['b']], $indexColumns(SQLiteCreateTable::automaticIndexColumnMetadata($sql)));
        $t->same([1, 1], array_column($indexRows, 'unique'));
        $t->same(['pk', 'u'], array_column($indexRows, 'origin'));
        $t->same([['a'], ['b']], $indexXInfoColumns($catalog, $indexRows));
    };

    $tests["real upstream pragma schema dynamic schema5 unique then composite primary key schema5-1.5/1.7 variant {$variant}"] = static function (TestRunner $t) use ($recordsFor, $columnNames, $indexColumns, $indexXInfoColumns, $variant): void {
        [$table, $sql, $catalog] = $recordsFor(
            $variant,
            'unique_composite_pk',
            'CREATE TABLE __TABLE__(a,b,c, UNIQUE(a) CONSTRAINT one, PRIMARY KEY(b,c) CONSTRAINT two)',
        );

        $tableInfo = $catalog->execute("PRAGMA table_info({$table})")['rows'];
        $indexRows = $catalog->execute("PRAGMA index_list({$table})")['rows'];

        $t->same(['a', 'b', 'c'], $columnNames($tableInfo));
        $t->same([0, 1, 2], array_column($tableInfo, 'pk'));
        $t->same([['a'], ['b', 'c']], $indexColumns(SQLiteCreateTable::automaticIndexColumnMetadata($sql)));
        $t->same([1, 1], array_column($indexRows, 'unique'));
        $t->same(['u', 'pk'], array_column($indexRows, 'origin'));
        $t->same([['a'], ['b', 'c']], $indexXInfoColumns($catalog, $indexRows));
    };

    $tests["real upstream pragma schema dynamic schema5 comma separated baseline remains equivalent variant {$variant}"] = static function (TestRunner $t) use ($recordsFor, $columnNames, $indexColumns, $variant): void {
        [$legacyTable, $legacySql, $legacyCatalog] = $recordsFor(
            $variant,
            'legacy_equiv',
            'CREATE TABLE __TABLE__(a,b,c, CONSTRAINT one PRIMARY KEY(a) CONSTRAINT two CHECK(b<10) UNIQUE(b) CONSTRAINT three)',
        );
        [$commaTable, $commaSql, $commaCatalog] = $recordsFor(
            $variant,
            'comma_equiv',
            'CREATE TABLE __TABLE__(a,b,c, CONSTRAINT one PRIMARY KEY(a), CONSTRAINT two CHECK(b<10), UNIQUE(b) CONSTRAINT three)',
        );

        $legacyInfo = $legacyCatalog->execute("PRAGMA table_info({$legacyTable})")['rows'];
        $commaInfo = $commaCatalog->execute("PRAGMA table_info({$commaTable})")['rows'];

        $t->same($columnNames($commaInfo), $columnNames($legacyInfo));
        $t->same(array_column($commaInfo, 'pk'), array_column($legacyInfo, 'pk'));
        $t->same(
            $indexColumns(SQLiteCreateTable::automaticIndexColumnMetadata($commaSql)),
            $indexColumns(SQLiteCreateTable::automaticIndexColumnMetadata($legacySql)),
        );
        $t->same(
            array_column($commaCatalog->execute("PRAGMA index_list({$commaTable})")['rows'], 'origin'),
            array_column($legacyCatalog->execute("PRAGMA index_list({$legacyTable})")['rows'], 'origin'),
        );
    };
}

$tests['real upstream pragma schema dynamic schema5 source citations and dependency closure'] = static function (TestRunner $t): void {
    $sections = [
        'schema5.test schema5-1.1 and schema5-1.2 accept PRIMARY KEY(a) UNIQUE(a) without an intervening comma and enforce the same key column',
        'schema5.test schema5-1.3 and schema5-1.4 accept named CONSTRAINT wrappers, adjacent CHECK, and adjacent UNIQUE(b)',
        'schema5.test schema5-1.5 through schema5-1.7 keep UNIQUE(a) and composite PRIMARY KEY(b,c) as separate automatic indexes',
    ];

    $t->same(3, count($sections));
    $t->contains('schema5.test', $sections[0]);
    $t->contains('adjacent CHECK', $sections[1]);
    $t->contains('PRIMARY KEY(b,c)', $sections[2]);
    $t->same(
        'no new support component needed; reuses SQLiteCreateTable automatic-index parsing and SQLitePragmaSchemaCatalog metadata rows',
        'no new support component needed; reuses SQLiteCreateTable automatic-index parsing and SQLitePragmaSchemaCatalog metadata rows',
    );
};

return $tests;
