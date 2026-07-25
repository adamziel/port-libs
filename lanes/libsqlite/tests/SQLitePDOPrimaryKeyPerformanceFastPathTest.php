<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePDO;

return [
    'SQLitePDO prepared integer primary key lookup stays correct across mutations and rollback' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE records (id INTEGER PRIMARY KEY, name TEXT, score INTEGER)');
        $insert = $pdo->prepare('INSERT INTO records (id, name, score) VALUES (?, ?, ?)');
        foreach ([
            [1, 'one', 10],
            [2, 'two', 20],
            [3, 'three', 30],
            [4, 'four', 40],
        ] as $row) {
            $t->true($insert->execute($row));
        }

        $lookup = $pdo->prepare('SELECT name, score FROM records WHERE id = ?');
        foreach ([
            1 => [['name' => 'one', 'score' => 10]],
            3 => [['name' => 'three', 'score' => 30]],
            4 => [['name' => 'four', 'score' => 40]],
            99 => [],
        ] as $id => $expected) {
            $t->true($lookup->execute([$id]));
            $t->same($expected, $lookup->fetchAll(PDO::FETCH_ASSOC));
        }

        $namedLookup = $pdo->prepare('SELECT name FROM records WHERE id = :id');
        $id = 2;
        $t->true($namedLookup->bindParam(':id', $id, PDO::PARAM_INT));
        $t->true($namedLookup->execute());
        $t->same('two', $namedLookup->fetchColumn());
        $id = 4;
        $t->true($namedLookup->execute());
        $t->same('four', $namedLookup->fetchColumn());

        $t->same(1, $pdo->exec("UPDATE records SET name = 'FOUR' WHERE id = 4"));
        $t->true($lookup->execute([4]));
        $t->same([['name' => 'FOUR', 'score' => 40]], $lookup->fetchAll(PDO::FETCH_ASSOC));

        $move = $pdo->prepare('UPDATE records SET id = ? WHERE id = ?');
        $t->true($move->execute([40, 4]));
        $t->same(1, $move->rowCount());
        $t->true($lookup->execute([4]));
        $t->same([], $lookup->fetchAll(PDO::FETCH_ASSOC));
        $t->true($lookup->execute([40]));
        $t->same([['name' => 'FOUR', 'score' => 40]], $lookup->fetchAll(PDO::FETCH_ASSOC));

        $delete = $pdo->prepare('DELETE FROM records WHERE id = ?');
        $t->true($delete->execute([1]));
        $t->same(1, $delete->rowCount());
        $t->true($lookup->execute([3]));
        $t->same([['name' => 'three', 'score' => 30]], $lookup->fetchAll(PDO::FETCH_ASSOC));

        $pdo->beginTransaction();
        $t->same(1, $pdo->exec("UPDATE records SET name = 'temporary' WHERE id = 3"));
        $t->true($delete->execute([40]));
        $t->true($insert->execute([50, 'fifty', 500]));
        $t->true($lookup->execute([50]));
        $t->same([['name' => 'fifty', 'score' => 500]], $lookup->fetchAll(PDO::FETCH_ASSOC));
        $pdo->rollBack();

        $t->true($lookup->execute([3]));
        $t->same([['name' => 'three', 'score' => 30]], $lookup->fetchAll(PDO::FETCH_ASSOC));
        $t->true($lookup->execute([40]));
        $t->same([['name' => 'FOUR', 'score' => 40]], $lookup->fetchAll(PDO::FETCH_ASSOC));
        $t->true($lookup->execute([50]));
        $t->same([], $lookup->fetchAll(PDO::FETCH_ASSOC));
    },

    'SQLitePDO primary key lookup index is rebuilt from a file image' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'port-libs-sqlite-pk-index-');
        if ($path === false) {
            throw new RuntimeException('Unable to allocate SQLitePDO primary-key test file');
        }

        try {
            $writer = new SQLitePDO('sqlite:' . $path);
            $writer->exec('CREATE TABLE records (id INTEGER PRIMARY KEY, name TEXT, score INTEGER)');
            $insert = $writer->prepare('INSERT INTO records (id, name, score) VALUES (?, ?, ?)');
            $insert->execute([1, 'first', 10]);
            $insert->execute([20, 'twentieth', 200]);
            unset($insert, $writer);

            $reader = new SQLitePDO('sqlite:' . $path);
            $lookup = $reader->prepare('SELECT name, score FROM records WHERE id = ?');
            $t->true($lookup->execute([20]));
            $t->same([['name' => 'twentieth', 'score' => 200]], $lookup->fetchAll(PDO::FETCH_ASSOC));
            $t->same(1, $reader->exec("UPDATE records SET name = 'changed' WHERE id = 20"));
            $t->true($lookup->execute([20]));
            $t->same([['name' => 'changed', 'score' => 200]], $lookup->fetchAll(PDO::FETCH_ASSOC));
            $t->same(1, $reader->exec('DELETE FROM records WHERE id = 1'));
            $t->true($lookup->execute([20]));
            $t->same([['name' => 'changed', 'score' => 200]], $lookup->fetchAll(PDO::FETCH_ASSOC));
        } finally {
            @unlink($path);
        }
    },

    'SQLitePDO primary key fast path matches the general predicate fallback' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE records (id INTEGER PRIMARY KEY, name TEXT, score INTEGER)');
        $insert = $pdo->prepare('INSERT INTO records (id, name, score) VALUES (?, ?, ?)');
        $insert->execute([1, 'one', 10]);
        $insert->execute([2, 'two-a', 20]);
        // Keep the polyfill's current duplicate-key behavior identical on both paths.
        $insert->execute([2, 'two-b', 21]);

        $fast = $pdo->prepare('SELECT name, score FROM records WHERE id = ?');
        $general = $pdo->prepare('SELECT name, score FROM records WHERE id = ? AND 1 = 1');
        foreach ([1, 2, 99, '2', 2.0, true, null] as $probe) {
            $t->true($fast->execute([$probe]));
            $t->true($general->execute([$probe]));
            $t->same(
                $general->fetchAll(PDO::FETCH_ASSOC),
                $fast->fetchAll(PDO::FETCH_ASSOC),
            );
        }

        $numbered = $pdo->prepare('SELECT name FROM records WHERE id = ?1');
        $t->true($numbered->execute([2]));
        $t->same(['two-a', 'two-b'], $numbered->fetchAll(PDO::FETCH_COLUMN));

        $secondNumbered = $pdo->prepare('SELECT name FROM records WHERE id = ?2');
        $t->true($secondNumbered->execute([2]));
        $t->same([], $secondNumbered->fetchAll(PDO::FETCH_COLUMN));
        $t->true($secondNumbered->execute([null, 2]));
        $t->same(['two-a', 'two-b'], $secondNumbered->fetchAll(PDO::FETCH_COLUMN));
    },

    'SQLitePDO indexed update preserves missing null and numbered parameter positions' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE records (id INTEGER PRIMARY KEY, score INTEGER)');
        $pdo->exec('INSERT INTO records (id, score) VALUES (123, 10)');

        $update = $pdo->prepare('UPDATE records SET score = ? WHERE id = :id');
        $t->true($update->execute([123, 'id' => null]));
        $t->same(0, $update->rowCount());
        $t->same(10, $pdo->query('SELECT score FROM records WHERE id = 123')->fetchColumn());

        $t->true($update->execute([123]));
        $t->same(0, $update->rowCount());
        $t->same(10, $pdo->query('SELECT score FROM records WHERE id = 123')->fetchColumn());

        $t->true($update->execute([20, 'id' => 123]));
        $t->same(1, $update->rowCount());
        $t->same(20, $pdo->query('SELECT score FROM records WHERE id = 123')->fetchColumn());

        $anonymous = $pdo->prepare('UPDATE records SET score = ? WHERE id = ?');
        $t->true($anonymous->execute([123]));
        $t->same(0, $anonymous->rowCount());
        $t->same(20, $pdo->query('SELECT score FROM records WHERE id = 123')->fetchColumn());
        $t->true($anonymous->execute([30, 123]));
        $t->same(1, $anonymous->rowCount());
        $t->same(30, $pdo->query('SELECT score FROM records WHERE id = 123')->fetchColumn());

        $numbered = $pdo->prepare('UPDATE records SET score = ?1 WHERE id = ?2');
        $t->true($numbered->execute([123]));
        $t->same(0, $numbered->rowCount());
        $t->same(30, $pdo->query('SELECT score FROM records WHERE id = 123')->fetchColumn());
        $t->true($numbered->execute([40, 123]));
        $t->same(1, $numbered->rowCount());
        $t->same(40, $pdo->query('SELECT score FROM records WHERE id = 123')->fetchColumn());

        $named = $pdo->prepare('UPDATE records SET score = :score WHERE id = :id');
        $t->true($named->execute([50, 123]));
        $t->same(1, $named->rowCount());
        $t->same(50, $pdo->query('SELECT score FROM records WHERE id = 123')->fetchColumn());

        $namedAnonymous = $pdo->prepare('UPDATE records SET score = :score WHERE id = ?');
        $t->true($namedAnonymous->execute([60, 123]));
        $t->same(1, $namedAnonymous->rowCount());
        $t->same(60, $pdo->query('SELECT score FROM records WHERE id = 123')->fetchColumn());

        $repeatedNamed = $pdo->prepare('UPDATE records SET score = :id WHERE id = :id');
        $t->true($repeatedNamed->execute([123]));
        $t->same(1, $repeatedNamed->rowCount());
        $t->same(123, $pdo->query('SELECT score FROM records WHERE id = 123')->fetchColumn());

        $commentedUpdate = $pdo->prepare(
            "UPDATE records SET score = ? WHERE 1 = 1 -- :fake\n AND id = :id",
        );
        $t->true($commentedUpdate->execute([50, 123]));
        $t->same(1, $commentedUpdate->rowCount());
        $t->same(50, $pdo->query('SELECT score FROM records WHERE id = 123')->fetchColumn());

        $commentedDelete = $pdo->prepare(
            'DELETE FROM records WHERE 1 = 1 /* :fake */ AND id = :id',
        );
        $t->true($commentedDelete->execute([123]));
        $t->same(1, $commentedDelete->rowCount());
        $t->same(0, $pdo->query('SELECT count(*) FROM records WHERE id = 123')->fetchColumn());

        $pdo->exec('INSERT INTO records (id, score) VALUES (124, 60)');
        $quotedDelete = $pdo->prepare(
            'DELETE FROM records WHERE "prefix:fake" = "prefix:fake" AND id = :id',
        );
        $t->true($quotedDelete->execute([124]));
        $t->same(1, $quotedDelete->rowCount());
        $t->same(0, $pdo->query('SELECT count(*) FROM records WHERE id = 124')->fetchColumn());
    },

    'SQLitePDO cached insert parsing and metadata respect transactional schema boundaries' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');

        $pdo->beginTransaction();
        $pdo->exec("CREATE TABLE volatile_rows (id INTEGER PRIMARY KEY, value INTEGER DEFAULT 7)");
        $oldInsert = $pdo->prepare('INSERT INTO volatile_rows (id) VALUES (?)');
        $t->true($oldInsert->execute([1]));
        $t->same(7, $pdo->query('SELECT value FROM volatile_rows WHERE id = 1')->fetchColumn());
        $pdo->rollBack();

        $pdo->exec("CREATE TABLE volatile_rows (id INTEGER PRIMARY KEY, value TEXT DEFAULT 'fresh')");
        $t->true($oldInsert->execute([2]));
        $t->same('fresh', $pdo->query('SELECT value FROM volatile_rows WHERE id = 2')->fetchColumn());

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $pdo->prepare('INSERT INTO volatile_rows (missing_column) VALUES (?)');
                throw new RuntimeException('Expected invalid cached INSERT to fail during prepare');
            } catch (PDOException $exception) {
                $t->same('table volatile_rows has no column named missing_column', $exception->getMessage());
            }
        }
        $t->same(
            [[2, 'fresh']],
            $pdo->query('SELECT id, value FROM volatile_rows')->fetchAll(PDO::FETCH_NUM),
        );
    },

    'SQLitePDO cached rowid maximum follows delete update and rollback boundaries' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE auto_rows (id INTEGER PRIMARY KEY, payload TEXT)');
        $insert = $pdo->prepare('INSERT INTO auto_rows (payload) VALUES (?)');
        for ($id = 1; $id <= 20; $id++) {
            $t->true($insert->execute(['payload-' . $id]));
        }
        $t->same('20', $pdo->lastInsertId());

        $t->same(1, $pdo->exec('DELETE FROM auto_rows WHERE id = 20'));
        $t->true($insert->execute(['reused']));
        $t->same('20', $pdo->lastInsertId());

        $t->same(1, $pdo->exec('UPDATE auto_rows SET id = 50 WHERE id = 20'));
        $t->true($insert->execute(['after-move']));
        $t->same('51', $pdo->lastInsertId());

        $pdo->beginTransaction();
        $t->true($insert->execute(['temporary']));
        $t->same('52', $pdo->lastInsertId());
        $pdo->rollBack();
        $t->true($insert->execute(['after-rollback']));
        $t->same('52', $pdo->lastInsertId());

        $lookup = $pdo->prepare('SELECT payload FROM auto_rows WHERE id = ?');
        $t->true($lookup->execute([50]));
        $t->same('reused', $lookup->fetchColumn());
        $t->true($lookup->execute([52]));
        $t->same('after-rollback', $lookup->fetchColumn());
    },
];
