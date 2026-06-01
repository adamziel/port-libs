<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePDO;

$memoryTestDb = static function (): SQLitePDO {
    $sqlite = new SQLitePDO('sqlite::memory:');
    $sqlite->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');

    return $sqlite;
};

$tempSqlitePath = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'port-libs-sqlitepdo-');
    if ($path === false) {
        throw new RuntimeException('Unable to allocate temporary SQLitePDO file');
    }

    return $path;
};

return [
    'sqlite pdo rejects insert into unknown target column before mutation' => static function (TestRunner $t) use ($memoryTestDb): void {
        $sqlite = $memoryTestDb();
        $thrown = false;

        try {
            $sqlite->exec("INSERT INTO test (namedd) VALUES ('Janet')");
        } catch (PDOException $exception) {
            $thrown = true;
            $t->same('table test has no column named namedd', $exception->getMessage());
        }

        $t->true($thrown, 'Expected invalid INSERT target column to throw');
        $t->same([], $sqlite->query('SELECT * FROM test')->fetchAll(PDO::FETCH_ASSOC));
    },

    'sqlite pdo rejects prepared insert into unknown target column before mutation' => static function (TestRunner $t) use ($memoryTestDb): void {
        $sqlite = $memoryTestDb();
        $thrown = false;

        try {
            $statement = $sqlite->prepare('INSERT INTO test (namedd) VALUES (?)');
            if ($statement !== false) {
                $statement->execute(['Janet']);
            }
        } catch (PDOException $exception) {
            $thrown = true;
            $t->same('table test has no column named namedd', $exception->getMessage());
        }

        $t->true($thrown, 'Expected prepared invalid INSERT target column to throw');
        $t->same([], $sqlite->query('SELECT * FROM test')->fetchAll(PDO::FETCH_ASSOC));
    },

    'sqlite pdo persists file backed changes outside transactions' => static function (TestRunner $t) use ($tempSqlitePath): void {
        $path = $tempSqlitePath();
        try {
            $sqlite = new SQLitePDO('sqlite:' . $path);
            $sqlite->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
            $sqlite->exec("INSERT INTO test (name) VALUES ('Janet')");

            $reopened = new SQLitePDO('sqlite:' . $path);
            $t->same(
                [['id' => 1, 'name' => 'Janet']],
                $reopened->query('SELECT * FROM test')->fetchAll(PDO::FETCH_ASSOC),
            );
        } finally {
            @unlink($path);
        }
    },

    'sqlite pdo keeps file backed database unchanged after invalid insert' => static function (TestRunner $t) use ($tempSqlitePath): void {
        $path = $tempSqlitePath();
        try {
            $sqlite = new SQLitePDO('sqlite:' . $path);
            $sqlite->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
            $sqlite->exec("INSERT INTO test (name) VALUES ('Janet')");

            $thrown = false;
            try {
                $sqlite->exec("INSERT INTO test (namedd) VALUES ('Ignored')");
            } catch (PDOException) {
                $thrown = true;
            }

            $t->true($thrown, 'Expected invalid INSERT target column to throw');

            $reopened = new SQLitePDO('sqlite:' . $path);
            $t->same(
                [['id' => 1, 'name' => 'Janet']],
                $reopened->query('SELECT * FROM test')->fetchAll(PDO::FETCH_ASSOC),
            );
        } finally {
            @unlink($path);
        }
    },
];
