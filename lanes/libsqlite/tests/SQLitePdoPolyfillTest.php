<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePDO;
use PortLibs\LibSqlite\SQLitePDOStatement;
use PortLibs\LibSqlite\SQLiteDatabase;

final class SQLitePdoFetchTarget
{
    public mixed $id = null;
    public mixed $name = null;
    public mixed $qty = null;
}

$sqlitePdoNativeAvailable = static fn (): bool => in_array('sqlite', PDO::getAvailableDrivers(), true);

return [
    'SQLitePDO extends native PDO classes' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $statement = $pdo->prepare('SELECT 1 AS one');

        $t->true($pdo instanceof PDO);
        $t->true($statement instanceof PDOStatement);
        $t->true($statement instanceof SQLitePDOStatement);
    },

    'SQLitePDO supports basic query exec and fetch modes' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $t->same(0, $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, score INTEGER)'));
        $t->same(2, $pdo->exec("INSERT INTO users (name, score) VALUES ('Ada', 7), ('Linus', 9)"));
        $t->same('2', $pdo->lastInsertId());

        $rows = $pdo->query('SELECT id, name FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $t->same([['id' => 1, 'name' => 'Ada'], ['id' => 2, 'name' => 'Linus']], $rows);

        $statement = $pdo->query('SELECT name, score FROM users WHERE id = 1');
        $t->same(['Ada', 7], $statement->fetch(PDO::FETCH_NUM));

        $statement = $pdo->query('SELECT name FROM users ORDER BY id');
        $t->same(['Ada', 'Linus'], $statement->fetchAll(PDO::FETCH_COLUMN));

        $statement = $pdo->query('SELECT id, name FROM users ORDER BY id', PDO::FETCH_COLUMN, 1);
        $t->same(['Ada', 'Linus'], $statement->fetchAll());
    },

    'SQLitePDO file DSN creates a SQLite file and persists rows across reopen' => static function (TestRunner $t): void {
        $scratchRoot = __DIR__ . '/.sqlite-pdo-tmp';
        if (!is_dir($scratchRoot) && !mkdir($scratchRoot)) {
            throw new RuntimeException("Unable to create temporary test root: {$scratchRoot}");
        }
        chmod($scratchRoot, 0700);
        $dir = $scratchRoot . '/sqlite-pdo-polyfill-' . bin2hex(random_bytes(6));
        if (!mkdir($dir) && !is_dir($dir)) {
            throw new RuntimeException("Unable to create temporary test directory: {$dir}");
        }
        chmod($dir, 0700);
        $path = $dir . '/test.sqlite';

        try {
            $sqlite = new SQLitePDO('sqlite:' . $path);
            $t->true(is_file($path));

            $t->same(0, $sqlite->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)'));
            $t->same(1, $sqlite->exec("INSERT INTO test (name) VALUES ('Janet')"));
            $t->same([['id' => 1, 'name' => 'Janet']], $sqlite->query('SELECT * FROM test')->fetchAll(PDO::FETCH_ASSOC));

            $database = SQLiteDatabase::fromFile($path);
            $rows = $database->tableRowsByName('test');
            $t->same(1, count($rows));
            $t->same(1, $rows[0]->rowId);
            $t->same([null, 'Janet'], $rows[0]->values());

            $reopened = new SQLitePDO('sqlite:' . $path);
            $t->same([['id' => 1, 'name' => 'Janet']], $reopened->query('SELECT * FROM test')->fetchAll(PDO::FETCH_ASSOC));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
            if (is_dir($dir)) {
                rmdir($dir);
            }
            if (is_dir($scratchRoot) && (scandir($scratchRoot) ?: ['.', '..']) === ['.', '..']) {
                rmdir($scratchRoot);
            }
        }
    },

    'SQLitePDO prepared statements bind positional named and referenced parameters' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT, qty INTEGER)');

        $insert = $pdo->prepare('INSERT INTO items (name, qty) VALUES (?, :qty)');
        $insert->bindValue(1, 'first');
        $qty = 4;
        $insert->bindParam(':qty', $qty, PDO::PARAM_INT);
        $t->true($insert->execute());
        $t->same(1, $insert->rowCount());

        $qty = 6;
        $insert->bindValue(1, 'second');
        $insert->execute();

        $pair = $pdo->prepare('INSERT INTO items (name, qty) VALUES (?, ?)');
        $pair->execute(['third', 10]);

        $select = $pdo->prepare('SELECT name FROM items WHERE qty = :qty');
        $t->true($select->execute(['qty' => 6]));
        $t->same('second', $select->fetchColumn());
        $t->same('third', $pdo->query('SELECT name FROM items WHERE qty = 10')->fetchColumn());

        $update = $pdo->prepare('UPDATE items SET qty = :qty WHERE name = :name');
        $update->execute(['qty' => 8, 'name' => 'first']);
        $t->same(1, $update->rowCount());

        $delete = $pdo->prepare('DELETE FROM items WHERE qty = ?');
        $delete->execute([8]);
        $t->same(1, $delete->rowCount());
        $t->same(2, (int) $pdo->query('SELECT count(*) AS c FROM items')->fetchColumn());
    },

    'SQLitePDO exec accepts optional positional and named parameters' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT, qty INTEGER)');

        $t->same(1, $pdo->exec('INSERT INTO items (name, qty) VALUES (?, ?)', ['first', 4]));
        $t->same('1', $pdo->lastInsertId());
        $t->same(1, $pdo->exec('INSERT INTO items (name, qty) VALUES (:name, :qty)', ['name' => 'second', 'qty' => 6]));
        $t->same('2', $pdo->lastInsertId());
        $t->same(1, $pdo->exec('UPDATE items SET qty = :qty WHERE name = :name', [':qty' => 8, ':name' => 'first']));
        $t->same(1, $pdo->exec('DELETE FROM items WHERE qty = ?', [6]));
        $t->same([['name' => 'first', 'qty' => 8]], $pdo->query('SELECT name, qty FROM items ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
    },

    'SQLitePDO exec parameter arrays reject batches and missing placeholders' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT, qty INTEGER)');
        $t->same(1, $pdo->exec('INSERT INTO items (name, qty) VALUES (?, ?)', ['first', 4]));

        try {
            $pdo->exec('INSERT INTO items (name, qty) VALUES (?, ?); INSERT INTO items (name, qty) VALUES (?, ?)', ['second', 6, 'third', 8]);
            throw new RuntimeException('Expected PDOException for parameterized multi-statement exec');
        } catch (PDOException $exception) {
            $t->contains('multi-statement SQL batches', $exception->getMessage());
            $t->same('HY000', $pdo->errorCode());
            $t->same('HY000', $pdo->errorInfo()[0]);
        }

        try {
            $pdo->exec('INSERT INTO items (name, qty) VALUES (?, ?)', ['second']);
            throw new RuntimeException('Expected PDOException for missing positional exec parameter');
        } catch (PDOException $exception) {
            $t->contains('missing positional parameter ?', $exception->getMessage());
            $t->same('HY000', $pdo->errorCode());
            $t->same('HY000', $pdo->errorInfo()[0]);
        }

        try {
            $pdo->exec('INSERT INTO items (name, qty) VALUES (:name, :qty)', ['name' => 'second']);
            throw new RuntimeException('Expected PDOException for missing named exec parameter');
        } catch (PDOException $exception) {
            $t->contains('missing named parameter :qty', $exception->getMessage());
            $t->same('HY000', $pdo->errorCode());
            $t->same('HY000', $pdo->errorInfo()[0]);
        }

        $t->same([['name' => 'first', 'qty' => 4]], $pdo->query('SELECT name, qty FROM items ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
    },

    'SQLitePDO transaction rollback restores in-memory tables' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE logs (id INTEGER PRIMARY KEY, body TEXT)');
        $pdo->beginTransaction();
        $pdo->exec("INSERT INTO logs (body) VALUES ('temporary')");
        $pdo->rollBack();
        $t->same(0, (int) $pdo->query('SELECT count(*) AS c FROM logs')->fetchColumn());

        $pdo->beginTransaction();
        $pdo->exec("INSERT INTO logs (body) VALUES ('kept')");
        $pdo->commit();
        $t->same('kept', $pdo->query('SELECT body FROM logs')->fetchColumn());
    },

    'SQLitePDO delegates INSERT syntax errors through PDO exceptions' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE logs (id INTEGER PRIMARY KEY, body TEXT)');
        $t->same(1, $pdo->exec("INSERT INTO logs (body) VALUES ('kept')"));

        foreach ([
            'INSERT logs (body) VALUES (\'missing into\')',
            'INSERT INTO logs (body) SELECT \'unsupported source\'',
            'INSERT INTO logs (body) VALUES',
            'INSERT INTO logs (body) VALUES (\'trailing comma\',)',
        ] as $sql) {
            try {
                $pdo->exec($sql);
            } catch (PDOException $exception) {
                $t->same('HY000', $pdo->errorCode());
                $t->true(!str_contains($exception->getMessage(), 'INSERT support requires INSERT INTO table [(columns)] VALUES (...)'));
                continue;
            }

            throw new RuntimeException('Expected PDOException for invalid INSERT SQL');
        }

        $t->same(['kept'], $pdo->query('SELECT body FROM logs ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    },

    'SQLitePDO rejects unknown INSERT and UPDATE columns without mutating rows' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');

        try {
            $pdo->exec("INSERT INTO test (namedd) VALUES ('Janet')");
            throw new RuntimeException('Expected PDOException for unknown INSERT column');
        } catch (PDOException $exception) {
            $t->contains('no column named namedd', $exception->getMessage());
            $t->same('HY000', $pdo->errorCode());
            $t->same('HY000', $pdo->errorInfo()[0]);
        }
        $t->same([], $pdo->query('SELECT * FROM test')->fetchAll(PDO::FETCH_ASSOC));

        $t->same(1, $pdo->exec("INSERT INTO test (name) VALUES ('Janet')"));
        try {
            $pdo->exec("UPDATE test SET namedd = 'Other' WHERE id = 1");
            throw new RuntimeException('Expected PDOException for unknown UPDATE column');
        } catch (PDOException $exception) {
            $t->contains('no column named namedd', $exception->getMessage());
            $t->same('HY000', $pdo->errorCode());
            $t->same('HY000', $pdo->errorInfo()[0]);
        }

        $t->same([['id' => 1, 'name' => 'Janet']], $pdo->query('SELECT * FROM test')->fetchAll(PDO::FETCH_ASSOC));
    },

    'SQLitePDO reports PDO exceptions for invalid DSNs unsupported APIs and transaction misuse' => static function (TestRunner $t): void {
        $t->throws(PDOException::class, static fn () => new SQLitePDO('mysql:dbname=test'));

        $pdo = new SQLitePDO('sqlite::memory:');
        $t->same(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        $t->true($pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION));
        $t->throws(PDOException::class, static fn () => $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT));
        $t->throws(PDOException::class, static fn () => $pdo->getAttribute(PDO::ATTR_AUTOCOMMIT));
        $t->same('sqlite', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        $t->throws(PDOException::class, static fn () => $pdo->exec('VACUUM'));
        $t->same('HY000', $pdo->errorCode());
        $t->same('HY000', $pdo->errorInfo()[0]);
        $t->contains('unsupported SQL statement', (string) $pdo->errorInfo()[2]);
        $t->throws(PDOException::class, static fn () => $pdo->commit());

        $pdo->beginTransaction();
        $t->true($pdo->inTransaction());
        $t->throws(PDOException::class, static fn () => $pdo->beginTransaction());
        $pdo->rollBack();
        $t->same(false, $pdo->inTransaction());
    },

    'SQLitePDO quotes scalar values and applies default fetch modes' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:', options: [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]);
        $t->same(PDO::FETCH_OBJ, $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
        $t->same("'Ada''s notes'", $pdo->quote("Ada's notes"));
        $t->same('42', $pdo->quote('42x', PDO::PARAM_INT));
        $t->same('NULL', $pdo->quote('ignored', PDO::PARAM_NULL));

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO users (name) VALUES ('Ada')");
        $row = $pdo->query('SELECT id, name FROM users')->fetch();
        $t->true($row instanceof stdClass);
        $t->same('Ada', $row->name);

        $t->throws(PDOException::class, static fn () => $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_LAZY));
    },

    'SQLitePDOStatement supports object bound and repeated column fetches' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT, qty INTEGER)');
        $insert = $pdo->prepare('INSERT INTO items (name, qty) VALUES (:name, :qty)');

        $name = 'first';
        $qty = '4';
        $insert->bindParam('name', $name);
        $insert->bindParam('qty', $qty, PDO::PARAM_INT);
        $insert->execute();

        $name = 'second';
        $qty = '6';
        $insert->execute();

        $statement = $pdo->query('SELECT name, qty FROM items ORDER BY id');
        $t->same('first', $statement->fetchColumn());
        $t->same('second', $statement->fetchColumn());
        $t->same(false, $statement->fetchColumn());

        $object = $pdo->query('SELECT name, qty FROM items WHERE id = 2')->fetch(PDO::FETCH_OBJ);
        $t->true($object instanceof stdClass);
        $t->same('second', $object->name);
        $t->same(6, $object->qty);

        $bound = $pdo->query('SELECT name, qty FROM items WHERE id = 1');
        $bound->bindColumn('name', $boundName);
        $bound->bindColumn(2, $boundQty, PDO::PARAM_INT);
        $t->true($bound->fetch(PDO::FETCH_BOUND));
        $t->same('first', $boundName);
        $t->same(4, $boundQty);
    },

    'SQLitePDOStatement reports columns classes into objects and statement errors' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT, qty INTEGER)');
        $pdo->exec("INSERT INTO items (name, qty) VALUES ('first', 4), ('second', 6)");

        $statement = $pdo->query('SELECT id, name, qty FROM items ORDER BY id');
        $t->same(3, $statement->columnCount());
        $row = $statement->fetch(PDO::FETCH_CLASS);
        $t->true($row instanceof stdClass);
        $t->same('first', $row->name);

        $classStatement = $pdo->query('SELECT id, name, qty FROM items WHERE id = 2');
        $t->true($classStatement->setFetchMode(PDO::FETCH_CLASS, SQLitePdoFetchTarget::class));
        $target = $classStatement->fetch();
        $t->true($target instanceof SQLitePdoFetchTarget);
        $t->same('second', $target->name);
        $t->same(6, $target->qty);

        $into = new SQLitePdoFetchTarget();
        $intoStatement = $pdo->query('SELECT id, name, qty FROM items WHERE id = 1');
        $t->true($intoStatement->setFetchMode(PDO::FETCH_INTO, $into));
        $t->same($into, $intoStatement->fetch());
        $t->same('first', $into->name);

        $bad = $pdo->prepare('SELECT * FROM missing_table');
        $t->throws(PDOException::class, static fn () => $bad->execute());
        $t->same('HY000', $bad->errorCode());
        $t->same('HY000', $bad->errorInfo()[0]);
    },

    'SQLitePDO supports common fetchAll shapes prepare cursor option and quoted batch exec' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $t->true($pdo->prepare('SELECT 1', [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]) instanceof PDOStatement);
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT, qty INTEGER)');
        $t->same(2, $pdo->exec("INSERT INTO items (name, qty) VALUES ('semi;colon', 4); INSERT INTO items (name, qty) VALUES ('plain', 6);"));

        $pairs = $pdo->query('SELECT id, name FROM items ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
        $t->same([1 => 'semi;colon', 2 => 'plain'], $pairs);

        $unique = $pdo->query('SELECT id, name, qty FROM items ORDER BY id')->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
        $t->same([1 => ['name' => 'semi;colon', 'qty' => 4], 2 => ['name' => 'plain', 'qty' => 6]], $unique);

        $classRows = $pdo->query('SELECT id, name, qty FROM items ORDER BY id')->fetchAll(PDO::FETCH_CLASS, SQLitePdoFetchTarget::class);
        $t->true($classRows[0] instanceof SQLitePdoFetchTarget);
        $t->same('semi;colon', $classRows[0]->name);

        $target = new SQLitePdoFetchTarget();
        $intoRows = $pdo->query('SELECT id, name, qty FROM items WHERE id = 2')->fetchAll(PDO::FETCH_INTO, $target);
        $t->same([$target], $intoRows);
        $t->same('plain', $target->name);
    },

    'SQLitePDO matches native PDO sqlite for common query prepare exec and transaction flows when available' => static function (TestRunner $t) use ($sqlitePdoNativeAvailable): void {
        if (!$sqlitePdoNativeAvailable()) {
            return;
        }

        $polyfill = new SQLitePDO('sqlite::memory:', options: [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $native = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

        foreach ([$polyfill, $native] as $pdo) {
            $t->same(0, $pdo->exec('CREATE TABLE records (id INTEGER PRIMARY KEY, label TEXT, amount INTEGER)'));
            $insert = $pdo->prepare('INSERT INTO records (label, amount) VALUES (:label, :amount)');
            $t->true($insert->execute(['label' => 'alpha', 'amount' => 3]));
            $t->true($insert->execute(['label' => 'beta', 'amount' => 5]));
        }

        $sql = 'SELECT id, label, amount FROM records WHERE amount >= :minimum ORDER BY id';
        $polyfillStatement = $polyfill->prepare($sql);
        $nativeStatement = $native->prepare($sql);
        $t->true($polyfillStatement->execute(['minimum' => 3]));
        $t->true($nativeStatement->execute(['minimum' => 3]));
        $t->same($nativeStatement->fetchAll(), $polyfillStatement->fetchAll());
        $polyfillPositional = $polyfill->prepare('SELECT label FROM records WHERE amount = ?');
        $nativePositional = $native->prepare('SELECT label FROM records WHERE amount = ?');
        $t->true($polyfillPositional->execute([5]));
        $t->true($nativePositional->execute([5]));
        $t->same($nativePositional->fetchColumn(), $polyfillPositional->fetchColumn());
        $t->same('alpha', $polyfill->query('SELECT id, label FROM records ORDER BY id', PDO::FETCH_COLUMN, 1)->fetch());
        $t->same(
            $native->query('SELECT id, label FROM records ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR),
            $polyfill->query('SELECT id, label FROM records ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR)
        );
        $t->same(
            $native->query('SELECT id, label, amount FROM records ORDER BY id')->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC),
            $polyfill->query('SELECT id, label, amount FROM records ORDER BY id')->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC)
        );

        $polyfill->beginTransaction();
        $native->beginTransaction();
        $polyfill->exec("INSERT INTO records (label, amount) VALUES ('discarded', 9)");
        $native->exec("INSERT INTO records (label, amount) VALUES ('discarded', 9)");
        $polyfill->rollBack();
        $native->rollBack();
        $t->same(
            $native->query('SELECT count(*) AS c FROM records')->fetchColumn(),
            $polyfill->query('SELECT count(*) AS c FROM records')->fetchColumn()
        );
    },
];
