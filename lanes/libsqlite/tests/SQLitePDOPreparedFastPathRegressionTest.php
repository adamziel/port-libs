<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePDO;

return [
    'SQLitePDO cached UPDATE and DELETE validation follows transactional schema changes' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');

        $pdo->beginTransaction();
        $pdo->exec('CREATE TABLE volatile_rows (id INTEGER PRIMARY KEY, legacy_value TEXT)');
        $update = $pdo->prepare('UPDATE volatile_rows SET legacy_value = ? WHERE id = ?');
        $delete = $pdo->prepare('DELETE FROM volatile_rows WHERE legacy_value = ?');
        $pdo->rollBack();

        $pdo->exec('CREATE TABLE volatile_rows (id INTEGER PRIMARY KEY, fresh_value TEXT)');
        $pdo->exec("INSERT INTO volatile_rows (id, fresh_value) VALUES (1, 'kept')");
        $hasCurrentValidation = new ReflectionMethod(SQLitePDO::class, 'hasValidatedUpdateDeleteSql');
        $t->same(
            false,
            $hasCurrentValidation->invoke(
                $pdo,
                'UPDATE volatile_rows SET legacy_value = ? WHERE id = ?',
                'volatile_rows',
            ),
        );
        $t->same(
            false,
            $hasCurrentValidation->invoke(
                $pdo,
                'DELETE FROM volatile_rows WHERE legacy_value = ?',
                'volatile_rows',
            ),
        );
        foreach (
            [
                static fn (): bool => $update->execute(['changed', 1]),
                static fn (): bool => $delete->execute(['kept']),
            ] as $execute
        ) {
            try {
                $execute();
                throw new RuntimeException('Expected cached DML validation to follow the recreated schema');
            } catch (PDOException $exception) {
                $t->contains('no such column: legacy_value', $exception->getMessage());
            }
        }
        $t->same(
            [[1, 'kept']],
            $pdo->query('SELECT id, fresh_value FROM volatile_rows')->fetchAll(PDO::FETCH_NUM),
        );
    },

    'SQLitePDO cached UPDATE and DELETE validation remains valid for an identical recreated schema' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $createSql = 'CREATE TABLE stable_rows (id INTEGER PRIMARY KEY, value TEXT)';

        $pdo->beginTransaction();
        $pdo->exec($createSql);
        $update = $pdo->prepare('UPDATE stable_rows SET value = ? WHERE id = ?');
        $delete = $pdo->prepare('DELETE FROM stable_rows WHERE id = ?');
        $pdo->rollBack();

        $pdo->exec($createSql);
        $pdo->exec("INSERT INTO stable_rows (id, value) VALUES (1, 'one')");
        $pdo->exec("INSERT INTO stable_rows (id, value) VALUES (2, 'two')");
        $hasCurrentValidation = new ReflectionMethod(SQLitePDO::class, 'hasValidatedUpdateDeleteSql');
        $t->same(
            true,
            $hasCurrentValidation->invoke(
                $pdo,
                'UPDATE stable_rows SET value = ? WHERE id = ?',
                'stable_rows',
            ),
        );
        $t->same(
            true,
            $hasCurrentValidation->invoke(
                $pdo,
                'DELETE FROM stable_rows WHERE id = ?',
                'stable_rows',
            ),
        );

        $t->true($update->execute(['changed', 1]));
        $t->same(1, $update->rowCount());
        $t->true($delete->execute([2]));
        $t->same(1, $delete->rowCount());
        $t->same(
            [[1, 'changed']],
            $pdo->query('SELECT id, value FROM stable_rows')->fetchAll(PDO::FETCH_NUM),
        );
    },

    'SQLitePDO bounds its cached UPDATE and DELETE validations' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE cache_rows (id INTEGER PRIMARY KEY, value TEXT)');
        $oldestSql = 'UPDATE cache_rows SET value = ? WHERE id = ? /* cache-entry-0 */';
        $pdo->prepare($oldestSql);

        for ($index = 1; $index <= 256; $index++) {
            $sql = $index % 2 === 0
                ? "UPDATE cache_rows SET value = ? WHERE id = ? /* cache-entry-{$index} */"
                : "DELETE FROM cache_rows WHERE id = ? /* cache-entry-{$index} */";
            $pdo->prepare($sql);
        }

        $property = new ReflectionProperty(SQLitePDO::class, 'validatedUpdateDeleteSql');
        $cache = $property->getValue($pdo);
        $t->same(256, count($cache));
        $t->same(false, array_key_exists($oldestSql, $cache));

        $pdo->prepare($oldestSql);
        $cache = $property->getValue($pdo);
        $t->same(256, count($cache));
        $t->same(true, array_key_exists($oldestSql, $cache));
    },

    'SQLitePDO compiled parameter layouts ignore quoted and commented fake placeholders' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $statement = $pdo->prepare(
            "SELECT 1 AS \"double ? :double\", 2 AS `backtick ? :backtick`, "
            . "3 AS [bracket ? :bracket], '? :single' AS literal, ? AS actual "
            . "/* ? :block */ -- ? :line\r\n",
        );

        $t->true($statement->execute([42]));
        $t->same(
            [
                'double ? :double' => 1,
                'backtick ? :backtick' => 2,
                'bracket ? :bracket' => 3,
                'literal' => '? :single',
                'actual' => 42,
            ],
            $statement->fetch(PDO::FETCH_ASSOC),
        );

        try {
            $statement->execute([42, 43]);
            throw new RuntimeException('Expected the surplus parameter to be rejected');
        } catch (PDOException $exception) {
            $t->same(['HY000', 25, 'column index out of range'], $exception->errorInfo);
            $t->same(['HY000', 25, 'column index out of range'], $statement->errorInfo());
        }

        $named = $pdo->prepare(
            "SELECT :actual AS value, ':single' AS literal /* :block */ -- :line\r\n",
        );
        $t->true($named->execute(['actual' => 'kept']));
        $t->same('kept', $named->fetchColumn());

        try {
            $named->execute(['actual' => 'kept', 'block' => 'surplus']);
            throw new RuntimeException('Expected the fake named parameter to be rejected');
        } catch (PDOException $exception) {
            $t->same(['HY000', 25, 'column index out of range'], $exception->errorInfo);
        }
    },

    'SQLitePDO compiled parameter layouts preserve SQLite named-token slot grammar' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $assertRangeError = static function (PDOStatement $statement, array $parameters) use ($t): void {
            try {
                $statement->execute($parameters);
                throw new RuntimeException('Expected a surplus or invalid parameter key to be rejected');
            } catch (PDOException $exception) {
                $t->same(['HY000', 25, 'column index out of range'], $exception->errorInfo);
            }
        };

        $mixed = $pdo->prepare(
            'SELECT ?2 AS explicit_value, ? AS anonymous_value, '
            . ':n AS named_one, :n AS named_two, @a AS at_value, '
            . '$b::c(d) AS dollar_value',
        );
        $t->true($mixed->execute([11, 22, 33, 44, 55, 66]));
        $t->same(
            [
                'explicit_value' => 22,
                'anonymous_value' => 33,
                'named_one' => 44,
                'named_two' => 44,
                'at_value' => 55,
                'dollar_value' => 66,
            ],
            $mixed->fetch(PDO::FETCH_ASSOC),
        );
        $assertRangeError($mixed, [11, 22, 33, 44, 55, 66, 77]);

        $at = $pdo->prepare('SELECT @x');
        $assertRangeError($at, ['@x' => 42]);
        $t->true($at->execute([42]));
        $t->same(42, $at->fetchColumn());

        $dollar = $pdo->prepare('SELECT $a::b(c)');
        $assertRangeError($dollar, ['b' => 42]);
        $t->true($dollar->execute([42]));
        $t->same(42, $dollar->fetchColumn());

        $numeric = $pdo->prepare('SELECT :1');
        $assertRangeError($numeric, ['1' => 42]);
        $t->true($numeric->execute([':1' => 42]));
        $t->same(42, $numeric->fetchColumn());

        $dollarInName = $pdo->prepare('SELECT :a$b');
        $assertRangeError($dollarInName, ['a' => 42]);
        $t->true($dollarInName->execute(['a$b' => 42]));
        $t->same(42, $dollarInName->fetchColumn());

        $unicode = $pdo->prepare('SELECT :éx');
        $t->true($unicode->execute(['éx' => 42]));
        $t->same(42, $unicode->fetchColumn());

        foreach (
            [
                'SELECT $a(() AS value',
                "SELECT \$a(foo'bar) AS value",
                'SELECT $::x AS value',
            ] as $sql
        ) {
            $tcl = $pdo->prepare($sql);
            $t->true($tcl->execute([42]));
            $t->same(42, $tcl->fetchColumn());
        }

        foreach (
            [
                'SELECT $:: AS value',
                'SELECT $a(b(c)) AS value',
                "SELECT \$a(')') AS value",
            ] as $sql
        ) {
            try {
                $invalid = $pdo->prepare($sql);
                $invalid->execute([42]);
                throw new RuntimeException("Expected invalid Tcl-style parameter SQL to fail: {$sql}");
            } catch (PDOException $exception) {
                $t->same('HY000', $exception->errorInfo[0] ?? null);
            }
        }
    },

    'SQLitePDO rejects named parameter forms its data-change executor cannot bind safely' => static function (TestRunner $t): void {
        $pdo = new SQLitePDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE guarded_rows (id INTEGER PRIMARY KEY, value TEXT)');
        $pdo->exec("INSERT INTO guarded_rows (id, value) VALUES (1, 'one')");
        $pdo->exec("INSERT INTO guarded_rows (id, value) VALUES (42, 'forty-two')");

        foreach (
            [
                'UPDATE guarded_rows SET value = ? WHERE id = @x',
                'UPDATE guarded_rows SET value = ? WHERE id = $x',
                'UPDATE guarded_rows SET value = ? WHERE id = :1',
                'UPDATE guarded_rows SET value = ? WHERE id = :éx',
                'UPDATE guarded_rows SET value = ? WHERE id = :a$b',
                'DELETE FROM guarded_rows WHERE id = $a::b(c)',
            ] as $sql
        ) {
            try {
                $pdo->prepare($sql);
                throw new RuntimeException("Expected unsupported data-change parameter SQL to fail: {$sql}");
            } catch (PDOException $exception) {
                $t->contains(
                    'data-change statements support only ASCII :name named parameters',
                    $exception->getMessage(),
                );
            }
        }

        $t->same(
            [[1, 'one'], [42, 'forty-two']],
            $pdo->query('SELECT id, value FROM guarded_rows ORDER BY id')->fetchAll(PDO::FETCH_NUM),
        );
    },
];
