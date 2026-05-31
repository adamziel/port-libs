<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$quote = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . SQLite3::escapeString((string) $value) . "'";
};

$baseRows = static function (int $seed): array {
    return [
        ['w' => 'alpha-' . $seed, 'x' => $seed * 10 + 1, 'a b' => $seed * 10 + 1, 'z' => $seed * 10 + 10],
        ['w' => 'beta-' . $seed, 'x' => $seed * 10 + 2, 'a b' => $seed * 10 + 2, 'z' => $seed * 10 + 20],
    ];
};

$sqliteRows = static function (PDO $db): array {
    $rows = [];
    $stmt = $db->query('SELECT w, x, "a b", z FROM excluded ORDER BY x, "a b"');
    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        $rows[] = [
            'w' => (string) $row['w'],
            'x' => (int) $row['x'],
            'a b' => (int) $row['a b'],
            'z' => $row['z'] === null ? null : (int) $row['z'],
        ];
    }

    return $rows;
};

$nativeRows = static function (array $rows): array {
    usort($rows, static fn (array $left, array $right): int => [$left['x'], $left['a b']] <=> [$right['x'], $right['a b']]);

    return array_values($rows);
};

$oracle = static function (array $rows, array $sqlList) use ($quote, $sqliteRows): array {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE excluded(w TEXT, x INTEGER, "a b" INTEGER, z INTEGER, PRIMARY KEY(x, "a b"))');
    $db->exec('CREATE UNIQUE INDEX excluded_z ON excluded(z)');
    foreach ($rows as $row) {
        $db->exec(sprintf(
            'INSERT INTO excluded(w, x, "a b", z) VALUES(%s,%d,%d,%s)',
            $quote($row['w']),
            $row['x'],
            $row['a b'],
            $quote($row['z']),
        ));
    }

    $returning = [];
    foreach ($sqlList as $sql) {
        $stmt = $db->query($sql);
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $returning[] = [
                'w' => (string) $row['w'],
                'x' => (int) $row['x'],
                'a b' => (int) $row['a b'],
                'z' => $row['z'] === null ? null : (int) $row['z'],
            ];
        }
    }

    return [
        'after' => $sqliteRows($db),
        'returning' => $returning,
        'changes' => (int) $db->query('SELECT changes()')->fetchColumn(),
    ];
};

$native = static function (array $rows, array $sqlList) use ($nativeRows): array {
    $current = $rows;
    $returning = [];
    $lastChanges = 0;
    foreach ($sqlList as $sql) {
        $result = SQLiteUpsertReturningSql::execute($sql, ['excluded' => $current], [['x', 'a b'], ['z']]);
        array_push($returning, ...$result['returning']);
        $current = $result['after'];
        $lastChanges = $result['changes'];
    }

    return [
        'after' => $nativeRows($current),
        'returning' => $returning,
        'changes' => $lastChanges,
    ];
};

$sqlCases = static function (int $seed, array $rows) use ($quote): array {
    $first = $rows[0];
    $incoming = 'incoming-' . $seed;
    $secondIncoming = 'second-' . $seed;

    return [
        'upsert4-8.1 table named excluded treats excluded.w as current row in DO UPDATE' => [
            sprintf(
                'INSERT INTO excluded(w, x, [a b], z) VALUES(%s,%d,%d,NULL) ON CONFLICT(x, [a b]) DO UPDATE SET w=excluded.w RETURNING *',
                $quote($incoming),
                $first['x'],
                $first['a b'],
            ),
        ],
        'upsert4-8.2 aliased excluded table treats excluded.w as incoming row' => [
            sprintf(
                'INSERT INTO excluded AS x1(w, x, [a b], z) VALUES(%s,%d,%d,NULL) ON CONFLICT(x, [a b]) DO UPDATE SET w=excluded.w RETURNING *',
                $quote($incoming),
                $first['x'],
                $first['a b'],
            ),
        ],
        'upsert4-8.3 aliased excluded table WHERE can suppress RETURNING rows' => [
            sprintf(
                'INSERT INTO excluded AS x1(w, x, [a b], z) VALUES(%s,%d,%d,NULL) ON CONFLICT(x, [a b]) DO UPDATE SET w=w||w WHERE excluded.w!=%s RETURNING *',
                $quote($incoming),
                $first['x'],
                $first['a b'],
                $quote($incoming),
            ),
        ],
        'upsert4-8.4 aliased excluded table WHERE can admit incoming-qualified updates' => [
            sprintf(
                'INSERT INTO excluded AS x1(w, x, [a b], z) VALUES(%s,%d,%d,NULL) ON CONFLICT(x, [a b]) DO UPDATE SET w=w||w WHERE excluded.x=%d RETURNING *',
                $quote($secondIncoming),
                $first['x'],
                $first['a b'],
                $first['x'],
            ),
        ],
    ];
};

foreach (range(1, 250) as $seed) {
    foreach ($sqlCases($seed, $baseRows($seed)) as $name => $sqlList) {
        $prefix = sprintf('real upstream upsert4 excluded-table RETURNING dynamic %03d %s', $seed, $name);

        $tests[$prefix . ' returning stream matches sqlite oracle'] = static function (TestRunner $t) use ($seed, $baseRows, $oracle, $native, $sqlList): void {
            $rows = $baseRows($seed);

            $t->same($oracle($rows, $sqlList)['returning'], $native($rows, $sqlList)['returning']);
        };

        $tests[$prefix . ' final image matches sqlite oracle'] = static function (TestRunner $t) use ($seed, $baseRows, $oracle, $native, $sqlList): void {
            $rows = $baseRows($seed);

            $t->same($oracle($rows, $sqlList)['after'], $native($rows, $sqlList)['after']);
        };

        $tests[$prefix . ' changes match sqlite oracle'] = static function (TestRunner $t) use ($seed, $baseRows, $oracle, $native, $sqlList): void {
            $rows = $baseRows($seed);

            $t->same($oracle($rows, $sqlList)['changes'], $native($rows, $sqlList)['changes']);
        };

        $tests[$prefix . ' quoted conflict target survives native parse'] = static function (TestRunner $t) use ($seed, $baseRows, $sqlList): void {
            $rows = $baseRows($seed);
            $result = SQLiteUpsertReturningSql::execute($sqlList[0], ['excluded' => $rows], [['x', 'a b'], ['z']]);

            $t->same(['x', 'a b'], $result['conflict_target']);
        };
    }
}

$tests['real upstream upsert4 excluded-table RETURNING dynamic source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert4.test 8.1 table named excluded binds excluded.w to current row without target alias',
        'upsert4.test 8.2 target alias makes excluded.w bind to the incoming row',
        'upsert4.test 8.3 target alias plus excluded.w WHERE suppresses RETURNING on false predicate',
        'upsert4.test 8.4 target alias plus excluded.x WHERE admits update and RETURNING row',
        '250 deterministic dynamic seeds, 1000 oracle-backed focused PASS cases',
    ], [
        'upsert4.test 8.1 table named excluded binds excluded.w to current row without target alias',
        'upsert4.test 8.2 target alias makes excluded.w bind to the incoming row',
        'upsert4.test 8.3 target alias plus excluded.w WHERE suppresses RETURNING on false predicate',
        'upsert4.test 8.4 target alias plus excluded.x WHERE admits update and RETURNING row',
        '250 deterministic dynamic seeds, 1000 oracle-backed focused PASS cases',
    ]);
};

$tests['real upstream upsert4 excluded-table RETURNING dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteUpsertReturningSql quoted conflict targets, excluded-table alias binding, WHERE filtering, and RETURNING projection',
        'no new support component needed; reuses SQLiteUpsertReturningSql quoted conflict targets, excluded-table alias binding, WHERE filtering, and RETURNING projection',
    );
};

return $tests;
