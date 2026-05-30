<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePDO;
use PortLibs\LibSqlite\SQLitePDOStatement;

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
];
