<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaImportExecutor;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$recordNames = static fn (array $records): array => array_map(static fn (SQLiteSchemaRecord $record): string => $record->name, $records);
$recordByName = static function (array $records, string $name): SQLiteSchemaRecord {
    foreach ($records as $record) {
        if ($record->name === $name) {
            return $record;
        }
    }

    throw new RuntimeException("Missing schema record {$name}");
};

$tests = [];

$tests['upstream index create corpus index-1.1 creates table and index records'] = static function (TestRunner $t) use ($recordNames): void {
    $executor = new SQLiteSchemaImportExecutor();
    $executor->execute('CREATE TABLE test1(f1 int, f2 int, f3 int)');
    $index = $executor->execute('CREATE INDEX index1 ON test1(f1)');

    $t->same('ok', $index['status']);
    $t->same(true, $index['created']);
    $t->same(['test1', 'index1'], $recordNames($executor->schemaRecords('main')));
};

$tests['upstream index create corpus index-1.1b preserves sqlite schema index fields'] = static function (TestRunner $t) use ($recordByName): void {
    $executor = new SQLiteSchemaImportExecutor();
    $executor->execute('CREATE TABLE test1(f1 int, f2 int, f3 int)');
    $executor->execute('CREATE INDEX index1 ON test1(f1)');
    $record = $recordByName($executor->schemaRecords('main'), 'index1');

    $t->same('index1', $record->name);
    $t->same('CREATE INDEX index1 ON test1(f1)', $record->sql);
    $t->same('test1', $record->tableName);
    $t->same('index', $record->type);
};

$tests['upstream index create corpus index-2.1 rejects missing index table'] = static function (TestRunner $t): void {
    $executor = new SQLiteSchemaImportExecutor();

    $t->throws(InvalidArgumentException::class, static fn () => $executor->execute('CREATE INDEX index1 ON test1(f1)'));
};

$tests['upstream index create corpus index-2.1b rejects missing indexed column'] = static function (TestRunner $t): void {
    $executor = new SQLiteSchemaImportExecutor();
    $executor->execute('CREATE TABLE test1(f1 int, f2 int, f3 int)');

    $t->throws(InvalidArgumentException::class, static fn () => $executor->execute('CREATE INDEX index1 ON test1(f4)'));
};

$tests['upstream index create corpus index-2.2 rejects later missing indexed column'] = static function (TestRunner $t): void {
    $executor = new SQLiteSchemaImportExecutor();
    $executor->execute('CREATE TABLE test1(f1 int, f2 int, f3 int)');

    $t->throws(InvalidArgumentException::class, static fn () => $executor->execute('CREATE INDEX index1 ON test1(f1, f2, f4, f3)'));
    $t->same(['test1'], array_map(static fn (SQLiteSchemaRecord $record): string => $record->name, $executor->schemaRecords('main')));
};

$tests['upstream index create corpus index-3.1 creates many indexes on same table'] = static function (TestRunner $t) use ($recordNames): void {
    $executor = new SQLiteSchemaImportExecutor();
    $executor->execute('CREATE TABLE test1(f1 int, f2 int, f3 int, f4 int, f5 int)');
    $expected = ['test1'];
    for ($i = 1; $i < 100; $i++) {
        $name = sprintf('index%02d', $i);
        $executor->execute(sprintf('CREATE INDEX %s ON test1(f%d)', $name, ($i % 5) + 1));
        $expected[] = $name;
    }

    $t->same($expected, $recordNames($executor->schemaRecords('main')));
    $t->same(100, count($executor->schemaRecords('main')));
};

$tests['upstream index create corpus index-3.1 allocates roots for many indexes'] = static function (TestRunner $t): void {
    $executor = new SQLiteSchemaImportExecutor();
    $executor->execute('CREATE TABLE test1(f1 int, f2 int, f3 int, f4 int, f5 int)');
    for ($i = 1; $i < 100; $i++) {
        $executor->execute(sprintf('CREATE INDEX index%02d ON test1(f%d)', $i, ($i % 5) + 1));
    }

    $t->same(range(2, 101), array_map(static fn (SQLiteSchemaRecord $record): ?int => $record->rootPage, $executor->schemaRecords('main')));
};

$tests['upstream index create corpus index-4.1 hash-collision names coexist'] = static function (TestRunner $t) use ($recordNames): void {
    $executor = new SQLiteSchemaImportExecutor();
    $executor->execute('CREATE TABLE test1(cnt int, power int)');
    $executor->execute('CREATE INDEX index9 ON test1(cnt)');
    $executor->execute('CREATE INDEX indext ON test1(power)');

    $t->same(['test1', 'index9', 'indext'], $recordNames($executor->schemaRecords('main')));
};

$tests['upstream index create corpus index-6.1 rejects duplicate index name across tables'] = static function (TestRunner $t) use ($recordNames): void {
    $executor = new SQLiteSchemaImportExecutor();
    $executor->execute('CREATE TABLE test1(f1 int, f2 int)');
    $executor->execute('CREATE TABLE test2(g1 real, g2 real)');
    $executor->execute('CREATE INDEX index1 ON test1(f1)');

    $t->throws(InvalidArgumentException::class, static fn () => $executor->execute('CREATE INDEX index1 ON test2(g1)'));
    $t->same(['test1', 'test2', 'index1'], $recordNames($executor->schemaRecords('main')));
};

$tests['upstream index create corpus index-6.1c accepts duplicate with if not exists'] = static function (TestRunner $t): void {
    $executor = new SQLiteSchemaImportExecutor();
    $executor->execute('CREATE TABLE test1(f1 int, f2 int)');
    $executor->execute('CREATE INDEX index1 ON test1(f1)');
    $result = $executor->execute('CREATE INDEX IF NOT EXISTS index1 ON test1(f1)');

    $t->same('ok', $result['status']);
    $t->same(false, $result['created']);
    $t->same(2, count($executor->schemaRecords('main')));
};

$tests['upstream index create corpus index-6.2 rejects index name matching table name'] = static function (TestRunner $t) use ($recordNames): void {
    $executor = new SQLiteSchemaImportExecutor();
    $executor->execute('CREATE TABLE test1(f1 int, f2 int)');
    $executor->execute('CREATE TABLE test2(g1 real, g2 real)');

    $t->throws(InvalidArgumentException::class, static fn () => $executor->execute('CREATE INDEX test1 ON test2(g1)'));
    $t->same(['test1', 'test2'], $recordNames($executor->schemaRecords('main')));
};

$tests['upstream index create corpus index-7.1 primary key creates autoindex'] = static function (TestRunner $t) use ($recordNames, $recordByName): void {
    $executor = new SQLiteSchemaImportExecutor();
    $executor->execute('CREATE TABLE test1(f1 int, f2 int primary key)');

    $t->same(['test1', 'sqlite_autoindex_test1_1'], $recordNames($executor->schemaRecords('main')));
    $autoindex = $recordByName($executor->schemaRecords('main'), 'sqlite_autoindex_test1_1');
    $t->same('index', $autoindex->type);
    $t->same('test1', $autoindex->tableName);
    $t->same(null, $autoindex->sql);
};

$tests['upstream index create corpus index3-2.1 accepts string identifiers as column names'] = static function (TestRunner $t) use ($recordNames): void {
    $executor = new SQLiteSchemaImportExecutor();
    $executor->execute("CREATE TABLE t1(a, b, c, d, e, PRIMARY KEY('a'), UNIQUE('b' COLLATE nocase DESC))");
    $executor->execute("CREATE INDEX t1c ON t1('c')");
    $executor->execute("CREATE INDEX t1d ON t1('d' COLLATE binary ASC)");

    $t->same(['t1', 'sqlite_autoindex_t1_1', 'sqlite_autoindex_t1_2', 't1c', 't1d'], $recordNames($executor->schemaRecords('main')));
};

$tests['upstream index create corpus index3-2.4 accepts alternate quoted primary key identifiers'] = static function (TestRunner $t) use ($recordNames): void {
    $executor = new SQLiteSchemaImportExecutor();
    $executor->execute('CREATE TABLE t2a(a integer, b, PRIMARY KEY(a))');
    $executor->execute('CREATE TABLE t2b("a" integer, b, PRIMARY KEY("a"))');
    $executor->execute('CREATE TABLE t2c([a] integer, b, PRIMARY KEY([a]))');
    $executor->execute("CREATE TABLE t2d('a' integer, b, PRIMARY KEY('a'))");

    $t->same(['t2a', 'sqlite_autoindex_t2a_1', 't2b', 'sqlite_autoindex_t2b_1', 't2c', 'sqlite_autoindex_t2c_1', 't2d', 'sqlite_autoindex_t2d_1'], $recordNames($executor->schemaRecords('main')));
};

$quotedColumnCases = [
    'double quoted column' => ['CREATE TABLE app_settings("setting key" text, value text)', 'CREATE INDEX app_settings_key ON app_settings("setting key")'],
    'bracket quoted column' => ['CREATE TABLE app_settings([setting key] text, value text)', 'CREATE INDEX app_settings_key ON app_settings([setting key] COLLATE nocase)'],
    'backtick quoted column' => ['CREATE TABLE app_settings(`setting key` text, value text)', 'CREATE INDEX app_settings_key ON app_settings(`setting key` DESC)'],
    'single quoted column' => ["CREATE TABLE app_settings('setting key' text, value text)", "CREATE INDEX app_settings_key ON app_settings('setting key' COLLATE binary ASC)"],
];

foreach ($quotedColumnCases as $name => [$createTable, $createIndex]) {
    $tests['upstream index create corpus quoted simple column validation ' . $name] = static function (TestRunner $t) use ($createTable, $createIndex, $recordByName): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->execute($createTable);
        $executor->execute($createIndex);

        $index = $recordByName($executor->schemaRecords('main'), 'app_settings_key');
        $t->same('index', $index->type);
        $t->same('app_settings', $index->tableName);
    };
}

$expressionIndexCases = [
    'lower expression' => 'CREATE INDEX app_settings_expr ON app_settings(lower(key_name))',
    'parenthesized expression' => 'CREATE INDEX app_settings_expr ON app_settings((key_name || key_value))',
    'cast expression' => 'CREATE INDEX app_settings_expr ON app_settings(CAST(key_value AS INTEGER))',
];

foreach ($expressionIndexCases as $name => $createIndex) {
    $tests['upstream index create corpus expression index bypasses simple column validation ' . $name] = static function (TestRunner $t) use ($createIndex, $recordByName): void {
        $executor = new SQLiteSchemaImportExecutor();
        $executor->execute('CREATE TABLE app_settings(key_name text, key_value text)');
        $executor->execute($createIndex);

        $index = $recordByName($executor->schemaRecords('main'), 'app_settings_expr');
        $t->same($createIndex, $index->sql);
    };
}

return $tests;
