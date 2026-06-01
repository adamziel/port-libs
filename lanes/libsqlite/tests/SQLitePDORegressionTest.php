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

$withScratchCwd = static function (callable $callback): mixed {
    $base = sys_get_temp_dir() . '/port-libs-sqlitepdo-snippet-' . bin2hex(random_bytes(6));
    if (!mkdir($base, 0700) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary SQLitePDO directory: {$base}");
    }
    $oldCwd = getcwd();
    if ($oldCwd === false || !chdir($base)) {
        throw new RuntimeException("Unable to enter temporary SQLitePDO directory: {$base}");
    }

    try {
        return $callback($base);
    } finally {
        chdir($oldCwd);
        foreach (glob($base . '/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($base)) {
            rmdir($base);
        }
    }
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

    'sqlite pdo default relative file snippet throws for misspelled insert column' => static function (TestRunner $t) use ($withScratchCwd): void {
        $withScratchCwd(static function (string $dir) use ($t): void {
            $sqlite = new SQLitePDO('sqlite:./test.sqlite');

            $t->same(0, $sqlite->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)'));
            $t->true(is_file($dir . '/test.sqlite'), 'Expected sqlite:./test.sqlite to create a file immediately');

            $thrown = false;
            try {
                $sqlite->exec("INSERT INTO test (namedd) VALUES ('Janet')");
            } catch (PDOException $exception) {
                $thrown = true;
                $t->same('HY000', $exception->getCode());
                $t->same('table test has no column named namedd', $exception->getMessage());
                $t->same(['HY000', 1, 'table test has no column named namedd'], $sqlite->errorInfo());
            }

            $t->true($thrown, 'Expected invalid INSERT target column to throw in default error mode');
            $t->same([], $sqlite->query('SELECT * FROM test')->fetchAll(PDO::FETCH_ASSOC));

            $reopened = new SQLitePDO('sqlite:./test.sqlite');
            $t->same([], $reopened->query('SELECT * FROM test')->fetchAll(PDO::FETCH_ASSOC));
        });
    },

    'sqlite pdo exec parameter snippet inserts and persists string values' => static function (TestRunner $t) use ($withScratchCwd): void {
        $withScratchCwd(static function () use ($t): void {
            $sqlite = new SQLitePDO('sqlite:./test.sqlite');

            $t->same(0, $sqlite->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)'));
            $t->same(1, $sqlite->exec('INSERT INTO test (name) VALUES (?)', ['John']));
            $t->same([['id' => 1, 'name' => 'John']], $sqlite->query('SELECT * FROM test')->fetchAll(PDO::FETCH_ASSOC));

            $reopened = new SQLitePDO('sqlite:./test.sqlite');
            $t->same([['id' => 1, 'name' => 'John']], $reopened->query('SELECT * FROM test')->fetchAll(PDO::FETCH_ASSOC));
        });
    },
];
