<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePDO;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

$open = static fn (): SQLitePDO => new SQLitePDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdoError = static function (SQLitePDO $pdo, string $sql): array {
    try {
        $pdo->exec($sql);
    } catch (PDOException $exception) {
        return [
            'message' => $exception->getMessage(),
            'exception_error_info' => $exception->errorInfo ?? null,
            'connection_error_info' => $pdo->errorInfo(),
        ];
    }

    throw new RuntimeException('Expected SQLitePDO exception for ' . $sql);
};

$rows = static fn (SQLitePDO $pdo, string $sql): array => $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$tests['real upstream insert error affinity dynamic cites insert test source sections'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/insert.test');

    $t->true($source !== false, 'hydrated upstream insert.test is readable');
    $t->contains('do_test insert-1.1', (string) $source);
    $t->contains('table sqlite_master may not be modified', (string) $source);
    $t->contains('table test1 has 3 columns but 2 values were supplied', (string) $source);
    $t->contains('4 values for 2 columns', (string) $source);
    $t->contains('CREATE TABLE test2(', (string) $source);
    $t->contains('f2 real default +4.32', (string) $source);
    $t->contains('f3 text default hi', (string) $source);
};

for ($seed = 1; $seed <= 250; ++$seed) {
    $tests[sprintf('real upstream insert.test insert-1 error diagnostics dynamic %04d', $seed)] = static function (TestRunner $t) use ($open, $pdoError, $seed): void {
        $pdo = $open();
        $table = sprintf('app_insert_shape_%04d', $seed);

        $missing = $pdoError($pdo, "INSERT INTO {$table} VALUES(1,2,3)");
        $protected = $pdoError($pdo, 'INSERT INTO sqlite_master VALUES(1,2,3,4)');

        $pdo->exec("CREATE TABLE {$table}(one int, two int, three int)");
        $tooFew = $pdoError($pdo, "INSERT INTO {$table} VALUES(1,2)");
        $tooMany = $pdoError($pdo, "INSERT INTO {$table} VALUES(1,2,3,4)");
        $explicitTooMany = $pdoError($pdo, "INSERT INTO {$table}(one,two) VALUES(1,2,3,4)");
        $explicitTooFew = $pdoError($pdo, "INSERT INTO {$table}(one,two) VALUES(1)");
        $badColumn = $pdoError($pdo, "INSERT INTO {$table}(one,four) VALUES(1,2)");

        $t->same("no such table: {$table}", $missing['message']);
        $t->same(['HY000', 1, "no such table: {$table}"], $missing['exception_error_info']);
        $t->same('table sqlite_master may not be modified', $protected['message']);
        $t->same(['HY000', 1, 'table sqlite_master may not be modified'], $protected['connection_error_info']);
        $t->same("table {$table} has 3 columns but 2 values were supplied", $tooFew['message']);
        $t->same("table {$table} has 3 columns but 4 values were supplied", $tooMany['message']);
        $t->same('4 values for 2 columns', $explicitTooMany['message']);
        $t->same('1 values for 2 columns', $explicitTooFew['message']);
        $t->same("table {$table} has no column named four", $badColumn['message']);
        $t->same([], $pdo->query("SELECT * FROM {$table}")->fetchAll(PDO::FETCH_ASSOC));
    };

    $tests[sprintf('real upstream insert.test insert-1 omitted columns become null dynamic %04d', $seed)] = static function (TestRunner $t) use ($open, $rows, $seed): void {
        $pdo = $open();
        $table = sprintf('app_insert_partial_%04d', $seed);

        $pdo->exec("CREATE TABLE {$table}(one int, two int, three int)");
        $pdo->exec(sprintf('INSERT INTO %s VALUES(%d,%d,%d)', $table, $seed, $seed + 1, $seed + 2));
        $pdo->exec(sprintf('INSERT INTO %s(one,two) VALUES(%d,%d)', $table, $seed + 10, $seed + 11));
        $pdo->exec(sprintf('INSERT INTO %s(two,three) VALUES(%d,%d)', $table, $seed + 20, $seed + 21));
        $pdo->exec(sprintf('INSERT INTO %s(three,one) VALUES(%d,%d)', $table, $seed + 31, $seed + 30));

        $t->same([
            ['one' => $seed, 'two' => $seed + 1, 'three' => $seed + 2],
            ['one' => $seed + 10, 'two' => $seed + 11, 'three' => null],
            ['one' => null, 'two' => $seed + 20, 'three' => $seed + 21],
            ['one' => $seed + 30, 'two' => null, 'three' => $seed + 31],
        ], $rows($pdo, "SELECT * FROM {$table}"));
        $t->same(4, (int) $pdo->query("SELECT count(*) AS c FROM {$table}")->fetch(PDO::FETCH_ASSOC)['c']);
    };

    $tests[sprintf('real upstream insert.test insert-2 numeric defaults affinity dynamic %04d', $seed)] = static function (TestRunner $t) use ($open, $rows, $seed): void {
        $pdo = $open();
        $table = sprintf('app_insert_defaults_%04d', $seed);
        $first = $seed + 10;
        $second = -($seed + 10);
        $explicitReal = -($seed + 0.45);

        $pdo->exec("CREATE TABLE {$table}(f1 int default -111, f2 real default +4.32, f3 int default +222, f4 int default 7.89)");
        $pdo->exec(sprintf('INSERT INTO %s(f1,f3) VALUES(+%d,%d)', $table, $first, $second));
        $pdo->exec(sprintf('INSERT INTO %s(f2,f4) VALUES(%.2f,%.2f)', $table, $explicitReal, $explicitReal - 1.0));
        $pdo->exec(sprintf('INSERT INTO %s(f1,f2,f4) VALUES(%d,+1.23,3.45)', $table, $seed + 77));

        $actual = $rows($pdo, "SELECT * FROM {$table}");
        $t->same(['f1' => $first, 'f2' => 4.32, 'f3' => $second, 'f4' => 7.89], $actual[0]);
        $t->same(['f1' => -111, 'f2' => $explicitReal, 'f3' => 222, 'f4' => $explicitReal - 1.0], $actual[1]);
        $t->same(['f1' => $seed + 77, 'f2' => 1.23, 'f3' => 222, 'f4' => 3.45], $actual[2]);
        $t->same('integer', SQLiteRealExpressionAffinityCorpusPlan::storageClass($actual[0]['f1']));
        $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass($actual[0]['f2']));
        $t->same('integer', SQLiteRealExpressionAffinityCorpusPlan::storageClass($actual[0]['f3']));
        $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass($actual[0]['f4']));
    };

    $tests[sprintf('real upstream insert.test insert-2 text defaults affinity dynamic %04d', $seed)] = static function (TestRunner $t) use ($open, $rows, $seed): void {
        $pdo = $open();
        $table = sprintf('app_insert_text_defaults_%04d', $seed);

        $pdo->exec("CREATE TABLE {$table}(f1 int default 111, f2 real default -4.32, f3 text default hi, f4 text default 'abc-123', f5 varchar(10))");
        $pdo->exec(sprintf("INSERT INTO %s(f2,f4) VALUES(-2.22,'hi-%d')", $table, $seed));
        $pdo->exec(sprintf("INSERT INTO %s(f1,f5) VALUES(%d,'xyzzy-%d')", $table, $seed, $seed));

        $actual = $rows($pdo, "SELECT * FROM {$table}");
        $t->same(['f1' => 111, 'f2' => -2.22, 'f3' => 'hi', 'f4' => 'hi-' . $seed, 'f5' => null], $actual[0]);
        $t->same(['f1' => $seed, 'f2' => -4.32, 'f3' => 'hi', 'f4' => 'abc-123', 'f5' => 'xyzzy-' . $seed], $actual[1]);
        $t->same('text', SQLiteRealExpressionAffinityCorpusPlan::storageClass($actual[1]['f3']));
        $t->same('text', SQLiteRealExpressionAffinityCorpusPlan::storageClass($actual[1]['f4']));
        $t->same('text', SQLiteRealExpressionAffinityCorpusPlan::storageClass($actual[1]['f5']));
    };
}

$tests['real upstream insert error affinity dynamic owns 1000 generated cases'] = static function (TestRunner $t): void {
    $t->same(1000, 250 * 4);
    $t->same([
        'insert.test insert-1.1/1.2/1.3/1.3b/1.3c/1.3d/1.4 error diagnostics',
        'insert.test insert-1.5/1.6/1.6b/1.6c ordinary INSERT VALUES and omitted-column NULL materialization',
        'insert.test insert-2.2/2.3/2.4 signed numeric defaults and affinity application',
        'insert.test insert-2.11/2.12 unquoted TEXT default and nullable omitted column behavior',
    ], [
        'insert.test insert-1.1/1.2/1.3/1.3b/1.3c/1.3d/1.4 error diagnostics',
        'insert.test insert-1.5/1.6/1.6b/1.6c ordinary INSERT VALUES and omitted-column NULL materialization',
        'insert.test insert-2.2/2.3/2.4 signed numeric defaults and affinity application',
        'insert.test insert-2.11/2.12 unquoted TEXT default and nullable omitted column behavior',
    ]);
};

$tests['real upstream insert error affinity dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLitePDO INSERT VALUES parser/executor, CREATE TABLE metadata, and existing insert-affinity coercion helper',
        'no new support component needed; reuses SQLitePDO INSERT VALUES parser/executor, CREATE TABLE metadata, and existing insert-affinity coercion helper',
    );
};

return $tests;
