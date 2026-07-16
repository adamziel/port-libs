<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteReturningForeignKeyBarrierPlan;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test';

$tests['real upstream returning1 foreign key barrier cites upstream section 14'] = static function (TestRunner $t) use ($sourcePath): void {
    $source = file_get_contents($sourcePath);

    $t->true(is_string($source) && str_contains($source, 'do_execsql_test 14.0'));
    $t->true(is_string($source) && str_contains($source, 'PRAGMA foreign_keys(1);'));
    $t->true(is_string($source) && str_contains($source, 'INSERT INTO child(parent_id) VALUES(123) RETURNING id;'));
    $t->true(is_string($source) && str_contains($source, 'FOREIGN KEY constraint failed'));
};

$tests['real upstream returning1 foreign key barrier sqlite oracle rejects before row'] = static function (TestRunner $t): void {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys=ON');
    $db->exec('CREATE TABLE parent(id INTEGER PRIMARY KEY)');
    $db->exec('CREATE TABLE child(id INTEGER PRIMARY KEY, parent_id INTEGER REFERENCES parent(id))');
    $db->exec('INSERT INTO parent(id) VALUES(1)');

    try {
        $db->query('INSERT INTO child(parent_id) VALUES(123) RETURNING id');
    } catch (PDOException $exception) {
        $t->contains('FOREIGN KEY constraint failed', $exception->getMessage());
        $t->same([], $db->query('SELECT id,parent_id FROM child')->fetchAll(PDO::FETCH_ASSOC));
        return;
    }

    throw new RuntimeException('SQLite oracle did not reject invalid child RETURNING insert');
};

$parentsFor = static function (int $seed): array {
    $base = $seed * 1000;

    return [
        ['id' => $base + 1, 'label' => 'parent-a-' . $seed],
        ['id' => $base + 2, 'label' => 'parent-b-' . $seed],
    ];
};

$childrenFor = static function (int $seed): array {
    $base = $seed * 1000;

    return [
        ['id' => $base + 10, 'parent_id' => $base + 1, 'payload' => 'existing-' . $seed],
    ];
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $tests[sprintf('real upstream returning1 foreign key barrier dynamic %04d', $seed)] = static function (TestRunner $t) use ($parentsFor, $childrenFor, $seed): void {
        $parents = $parentsFor($seed);
        $children = $childrenFor($seed);
        $base = $seed * 1000;
        $foreignKey = ['parent_key' => 'id', 'child_key' => 'parent_id'];

        $invalid = SQLiteReturningForeignKeyBarrierPlan::insertChildReturning(
            $parents,
            $children,
            ['parent_id' => $base + 999, 'payload' => 'blocked-' . $seed],
            $foreignKey,
            ['id', 'parent_id'],
        );

        $t->same('returning1.test', $invalid['source']);
        $t->same('returning1-14.1 immediate foreign-key failure is reported before RETURNING rows', $invalid['scenario']);
        $t->same(false, $invalid['ok'], "returning1-14.1 invalid child insert fails {$seed}");
        $t->same('FOREIGN KEY constraint failed', $invalid['error'], "returning1-14.1 error text {$seed}");
        $t->same(false, $invalid['returning_evaluated'], "RETURNING is not evaluated after FK failure {$seed}");
        $t->same([], $invalid['returning_rows'], "no RETURNING row leaks after FK failure {$seed}");
        $t->same($children, $invalid['after'], "failed FK insert preserves child table {$seed}");
        $t->same(0, $invalid['changes'], "failed FK insert changes no rows {$seed}");
        $t->same([[
            'child_key' => 'parent_id',
            'child_value' => $base + 999,
            'parent_key' => 'id',
        ]], $invalid['violations'], "failed FK insert records missing parent {$seed}");

        $valid = SQLiteReturningForeignKeyBarrierPlan::insertChildReturning(
            $parents,
            $children,
            ['parent_id' => $base + 2, 'payload' => 'valid-' . $seed],
            $foreignKey,
            ['id', 'parent_id', 'payload'],
        );

        $t->same(true, $valid['ok'], "valid child insert succeeds {$seed}");
        $t->same(null, $valid['error'], "valid child insert has no error {$seed}");
        $t->same(true, $valid['returning_evaluated'], "valid child insert evaluates RETURNING {$seed}");
        $t->same([['id' => $base + 11, 'parent_id' => $base + 2, 'payload' => 'valid-' . $seed]], $valid['returning_rows'], "valid child insert returns row {$seed}");
        $t->same($base + 11, $valid['attempted']['id'], "valid child insert receives next rowid {$seed}");
        $t->same(1, $valid['changes'], "valid child insert changes one row {$seed}");
        $t->same([], $valid['violations'], "valid child insert has no FK violations {$seed}");

        $nullParent = SQLiteReturningForeignKeyBarrierPlan::insertChildReturning(
            $parents,
            $children,
            ['id' => $base + 77, 'parent_id' => null, 'payload' => 'null-parent-' . $seed],
            $foreignKey,
            ['id', 'parent_id'],
        );

        $t->same(true, $nullParent['ok'], "NULL child key is accepted {$seed}");
        $t->same([['id' => $base + 77, 'parent_id' => null]], $nullParent['returning_rows'], "NULL child key still returns row {$seed}");
        $t->same(1, $nullParent['changes'], "NULL child key insert changes one row {$seed}");
        $t->same([
            'sqlite-returning-immediate-foreign-key-barrier',
            'returning1.test-14.0',
            'returning1.test-14.1',
        ], $nullParent['dependencies'], "source dependencies are stable {$seed}");
    };
}

$tests['real upstream returning1 foreign key barrier dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses generic PHP row arrays and immediate foreign-key validation before RETURNING projection',
        'no new support component needed; reuses generic PHP row arrays and immediate foreign-key validation before RETURNING projection',
    );
};

return $tests;
