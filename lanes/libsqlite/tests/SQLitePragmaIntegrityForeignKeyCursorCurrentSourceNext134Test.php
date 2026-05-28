<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyIntegrity;
use PortLibs\LibSqlite\SQLitePragmaIntegritySourceCursor;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$database = str_repeat("\0", 512);
$database = substr_replace($database, "SQLite format 3\0", 0, 16);
$database = substr_replace($database, pack('n', 512), 16, 2);
$database[18] = "\x01";
$database[19] = "\x01";
$database = substr_replace($database, pack('N', 1), 28, 4);
$database = substr_replace($database, pack('N', 1), 56, 4);

$record = static fn (string $type, string $name, string $table, int $root, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    'CREATE ' . strtoupper($type) . ' ' . $name,
    $rowid,
);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 1),
    $record('table', 'wp_option_names', 'wp_option_names', 3, 2),
]);
$catalog->attach('wp.archive', '/tmp/wp.archive.sqlite', [
    $record('table', 'wp_options', 'wp_options', 4, 1),
    $record('table', 'wp_option_names', 'wp_option_names', 5, 2),
]);
$catalog->attach('wp.import.2026', '/tmp/wp.import.2026.sqlite', [
    $record('table', 'wp_options', 'wp_options', 6, 1),
    $record('table', 'wp_option_names', 'wp_option_names', 7, 2),
]);

$schemasFactory = static function (int $archiveMissing = 4, int $importMissing = 3): array {
    $archiveRows = [['rowid' => 'archive-ok', 'option_name' => 'legacy_siteurl']];
    for ($i = 1; $i <= $archiveMissing; $i++) {
        $archiveRows[] = ['rowid' => 'archive-missing-' . $i, 'option_name' => 'archive_missing_' . $i];
    }

    $importRows = [['rowid' => 'import-ok', 'option_name' => 'queued_siteurl']];
    for ($i = 1; $i <= $importMissing; $i++) {
        $importRows[] = ['rowid' => 'import-missing-' . $i, 'option_name' => 'import_missing_' . $i];
    }

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => [
                    ['rowid' => 'main-ok', 'option_name' => 'siteurl'],
                    ['rowid' => 'main-missing', 'option_name' => 'missing_main'],
                ],
            ],
            'foreignKeys' => [
                ['id' => 1340, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
        'wp.archive' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 'archive-parent', 'name' => 'legacy_siteurl']],
                'wp_options' => $archiveRows,
            ],
            'foreignKeys' => [
                ['id' => 1341, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
        'wp.import.2026' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 'import-parent', 'name' => 'queued_siteurl']],
                'wp_options' => $importRows,
            ],
            'foreignKeys' => [
                ['id' => 1342, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$schemas = $schemasFactory();
$pragmaSql = 'PRAGMA "wp.archive".foreign_key_check(wp_options)';
$tableSql = 'SELECT * FROM "wp.archive".pragma_foreign_key_check(wp_options)';
$qualifiedTargetSql = "PRAGMA foreign_key_check('wp.import.2026'.wp_options)";
$tableQualifiedTargetSql = "pragma_foreign_key_check('wp.import.2026'.wp_options)";

$pragmaPage = static fn (int $offset = 0, int $limit = 8, ?array $cursor = null, string $sql = null, array $schemaRows = null): array => SQLitePragmaIntegritySourceCursor::pageForForeignKeyPragma(
    $database,
    $schemaRows ?? $schemas,
    $sql ?? $pragmaSql,
    $offset,
    $limit,
    'PRAGMA quick_check',
    $cursor,
    $catalog,
);
$tablePage = static fn (int $offset = 0, int $limit = 8, ?array $cursor = null, string $sql = null, array $schemaRows = null): array => SQLitePragmaIntegritySourceCursor::pageForForeignKeyTableValuedPragma(
    $database,
    $schemaRows ?? $schemas,
    $sql ?? $tableSql,
    $offset,
    $limit,
    'PRAGMA quick_check',
    $cursor,
    $catalog,
);

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$cases = [
    'statement quoted schema status' => [$pragmaPage, 'status', 'ok'],
    'statement quoted schema total' => [$pragmaPage, 'total', 4],
    'statement quoted schema current fk count' => [$pragmaPage, 'current.foreign_key', 4],
    'statement quoted schema normalized sql' => [$pragmaPage, 'current_source.foreign_key_sql', 'pragma "wp.archive".foreign_key_check(wp_options)'],
    'statement quoted schema first schema' => [$pragmaPage, 'rows.0.schema', 'wp.archive'],
    'statement quoted schema first table' => [$pragmaPage, 'rows.0.table', 'wp_options'],
    'statement quoted schema first rowid' => [$pragmaPage, 'rows.0.rowid', 'archive-missing-1'],
    'statement quoted schema first parent' => [$pragmaPage, 'rows.0.parent', 'wp_option_names'],
    'statement quoted schema first fkid' => [$pragmaPage, 'rows.0.fkid', 1341],
    'statement quoted schema first message' => [$pragmaPage, 'rows.0.message', 'foreign key mismatch in wp.archive.wp_options rowid archive-missing-1 references wp_option_names fkid 1341'],
    'statement quoted schema last rowid' => [$pragmaPage, 'rows.3.rowid', 'archive-missing-4'],
    'statement source id length' => [static fn (): array => ['length' => strlen($pragmaPage()['source_id'])], 'length', 64],
    'statement database hash length' => [static fn (): array => ['length' => strlen($pragmaPage()['current_source']['database'])], 'length', 64],
    'statement schema hash length' => [static fn (): array => ['length' => strlen($pragmaPage()['current_source']['schema_hash'])], 'length', 64],
    'statement catalog hash length' => [static fn (): array => ['length' => strlen((string) $pragmaPage()['current_source']['catalog_hash'])], 'length', 64],
    'table valued quoted schema status' => [$tablePage, 'status', 'ok'],
    'table valued quoted schema total' => [$tablePage, 'total', 4],
    'table valued quoted schema current fk count' => [$tablePage, 'current.foreign_key', 4],
    'table valued quoted schema normalized sql' => [$tablePage, 'current_source.foreign_key_sql', 'select * from "wp.archive".pragma_foreign_key_check(wp_options)'],
    'table valued quoted schema first schema' => [$tablePage, 'rows.0.schema', 'wp.archive'],
    'table valued quoted schema last rowid' => [$tablePage, 'rows.3.rowid', 'archive-missing-4'],
    'qualified target statement schema' => [static fn (): array => $pragmaPage(0, 8, null, $qualifiedTargetSql), 'rows.0.schema', 'wp.import.2026'],
    'qualified target statement total' => [static fn (): array => $pragmaPage(0, 8, null, $qualifiedTargetSql), 'total', 3],
    'qualified target statement fkid' => [static fn (): array => $pragmaPage(0, 8, null, $qualifiedTargetSql), 'rows.0.fkid', 1342],
    'qualified target statement normalized' => [static fn (): array => $pragmaPage(0, 8, null, $qualifiedTargetSql), 'current_source.foreign_key_sql', "pragma foreign_key_check('wp.import.2026'.wp_options)"],
    'qualified target table schema' => [static fn (): array => $tablePage(0, 8, null, $tableQualifiedTargetSql), 'rows.0.schema', 'wp.import.2026'],
    'qualified target table total' => [static fn (): array => $tablePage(0, 8, null, $tableQualifiedTargetSql), 'total', 3],
    'qualified target table last rowid' => [static fn (): array => $tablePage(0, 8, null, $tableQualifiedTargetSql), 'rows.2.rowid', 'import-missing-3'],
    'direct execute quoted pragma schema' => [static fn (): array => SQLitePragmaForeignKeyIntegrity::execute($pragmaSql, $schemas, $catalog), 'schema', 'wp.archive'],
    'direct execute quoted pragma target source' => [static fn (): array => SQLitePragmaForeignKeyIntegrity::execute($pragmaSql, $schemas, $catalog), 'target_source', 'pragma-schema'],
    'direct execute quoted pragma rows' => [static fn (): array => SQLitePragmaForeignKeyIntegrity::execute($pragmaSql, $schemas, $catalog), 'rows.count', 4],
    'direct execute table schema' => [static fn (): array => SQLitePragmaForeignKeyIntegrity::executeTableValued($tableSql, $schemas, $catalog), 'schema', 'wp.archive'],
    'direct execute table source' => [static fn (): array => SQLitePragmaForeignKeyIntegrity::executeTableValued($tableSql, $schemas, $catalog), 'target_source', 'pragma-schema'],
    'direct execute qualified target source' => [static fn (): array => SQLitePragmaForeignKeyIntegrity::execute($qualifiedTargetSql, $schemas, $catalog), 'target_source', 'qualified-target'],
    'direct execute qualified target table' => [static fn (): array => SQLitePragmaForeignKeyIntegrity::execute($qualifiedTargetSql, $schemas, $catalog), 'target', 'wp_options'],
    'direct execute qualified target rows' => [static fn (): array => SQLitePragmaForeignKeyIntegrity::execute($qualifiedTargetSql, $schemas, $catalog), 'rows.count', 3],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity foreignkey cursor current source next134 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity foreignkey cursor current source next134 paginates quoted statement schema'] = static function (TestRunner $t) use ($pragmaPage): void {
    $first = $pragmaPage(0, 2);
    $second = $pragmaPage(2, 2, ['source_id' => $first['source_id'], 'next_offset' => 2]);

    $t->same(2, $first['count']);
    $t->same(2, $first['next_offset']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 2], $first['next']);
    $t->same(2, $second['offset']);
    $t->same(2, $second['count']);
    $t->same(true, $second['complete']);
    $t->same(null, $second['next']);
    $t->same('archive-missing-3', $second['rows'][0]['rowid']);
};

$tests['pragma integrity foreignkey cursor current source next134 paginates quoted table valued schema'] = static function (TestRunner $t) use ($tablePage): void {
    $first = $tablePage(0, 3);
    $second = $tablePage(3, 3, ['source_id' => $first['source_id'], 'offset' => 3]);

    $t->same(3, $first['count']);
    $t->same(3, $first['next_offset']);
    $t->same(1, $second['count']);
    $t->same('archive-missing-4', $second['rows'][0]['rowid']);
    $t->same(null, $second['next_offset']);
};

$tests['pragma integrity foreignkey cursor current source next134 source changes by quoted schema sql form'] = static function (TestRunner $t) use ($pragmaPage, $tablePage): void {
    $statement = $pragmaPage();
    $table = $tablePage();

    $t->same(true, $statement['source_id'] !== $table['source_id']);
    $t->same($statement['current_source']['database'], $table['current_source']['database']);
    $t->same($statement['current_source']['schema_hash'], $table['current_source']['schema_hash']);
    $t->same($statement['current_source']['catalog_hash'], $table['current_source']['catalog_hash']);
};

$tests['pragma integrity foreignkey cursor current source next134 source changes by dotted schema rows'] = static function (TestRunner $t) use ($pragmaPage, $schemasFactory): void {
    $first = $pragmaPage(0, 8, null, null, $schemasFactory(4, 3));
    $second = $pragmaPage(0, 8, null, null, $schemasFactory(5, 3));

    $t->same(true, $first['source_id'] !== $second['source_id']);
    $t->same(4, $first['total']);
    $t->same(5, $second['total']);
    $t->same('archive-missing-5', $second['rows'][4]['rowid']);
};

$tests['pragma integrity foreignkey cursor current source next134 rejects stale dotted schema cursor'] = static function (TestRunner $t) use ($pragmaPage, $schemasFactory): void {
    $first = $pragmaPage(0, 2, null, null, $schemasFactory(4, 3));
    $t->throws(InvalidArgumentException::class, static fn () => $pragmaPage(2, 2, ['source_id' => $first['source_id'], 'next_offset' => 2], null, $schemasFactory(5, 3)));
};

$tests['pragma integrity foreignkey cursor current source next134 rejects stale sql form cursor'] = static function (TestRunner $t) use ($pragmaPage, $tableSql): void {
    $first = $pragmaPage(0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $pragmaPage(2, 2, ['source_id' => $first['source_id'], 'next_offset' => 2], 'PRAGMA "wp.archive".foreign_key_check(wp_options); ' . $tableSql));
};

$tests['pragma integrity foreignkey cursor current source next134 rejects schema target mismatch'] = static function (TestRunner $t) use ($schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIntegrity::execute("PRAGMA \"wp.archive\".foreign_key_check('wp.import.2026'.wp_options)", $schemas, $catalog));
};

$tests['pragma integrity foreignkey cursor current source next134 rejects malformed quoted schema segment'] = static function (TestRunner $t) use ($schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIntegrity::execute('PRAGMA "wp..archive".foreign_key_check(wp_options)', $schemas, $catalog));
};

$tests['pragma integrity foreignkey cursor current source next134 rejects malformed target table'] = static function (TestRunner $t) use ($schemas, $catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaForeignKeyIntegrity::execute('PRAGMA foreign_key_check("\'wp.archive\'.\'bad.table\'")', $schemas, $catalog));
};

foreach (range(1, 8) as $index) {
    $tests['pragma integrity foreignkey cursor current source next134 repeated dotted archive row count ' . $index] = static function (TestRunner $t) use ($pragmaPage, $schemasFactory, $index): void {
        $result = $pragmaPage(0, 12, null, null, $schemasFactory($index, 3));
        $t->same($index, $result['current']['foreign_key']);
        $t->same($index, $result['total']);
        $t->same('wp.archive', $result['rows'][0]['schema']);
    };
}

return $tests;
