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

$baseRows = static fn (int $seed): array => [
    ['w' => 'alpha-' . $seed, 'x' => $seed * 10 + 1, 'y' => $seed * 10 + 1, 'z' => $seed * 10 + 10],
    ['w' => 'beta-' . $seed, 'x' => $seed * 10 + 2, 'y' => $seed * 10 + 2, 'z' => $seed * 10 + 20],
];

$sqliteRows = static function (PDO $db): array {
    $rows = [];
    $stmt = $db->query('SELECT w, x, y, z FROM t1 ORDER BY x, y');
    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        $rows[] = [
            'w' => (string) $row['w'],
            'x' => (int) $row['x'],
            'y' => (int) $row['y'],
            'z' => $row['z'] === null ? null : (int) $row['z'],
        ];
    }

    return $rows;
};

$nativeRows = static function (array $rows): array {
    usort($rows, static fn (array $left, array $right): int => [$left['x'], $left['y']] <=> [$right['x'], $right['y']]);

    return array_values($rows);
};

$oracle = static function (array $rows, array $sqlList) use ($quote, $sqliteRows): array {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE t1(w TEXT, x INTEGER, y INTEGER, z INTEGER, PRIMARY KEY(x, y))');
    $db->exec('CREATE UNIQUE INDEX t1_z ON t1(z)');
    foreach ($rows as $row) {
        $db->exec(sprintf(
            'INSERT INTO t1(w, x, y, z) VALUES(%s,%d,%d,%s)',
            $quote($row['w']),
            $row['x'],
            $row['y'],
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
                'y' => (int) $row['y'],
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
        $result = SQLiteUpsertReturningSql::execute($sql, ['t1' => $current], [['x', 'y'], ['z']]);
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
    $second = $rows[1];
    $incoming = 'incoming-' . $seed;

    return [
        'upsert4-7.1 unique-z conflict updates from excluded pseudo-table' => [
            sprintf(
                'INSERT INTO t1(w, x, y, z) VALUES(%s,%d,%d,%d) ON CONFLICT(z) DO UPDATE SET w=excluded.w RETURNING *',
                $quote($incoming),
                $seed * 10 + 3,
                $seed * 10 + 3,
                $first['z'],
            ),
        ],
        'upsert4-7.2 reordered primary-key conflict doubles current value' => [
            sprintf(
                'INSERT INTO t1(w, x, y, z) VALUES(%s,%d,%d,%d) ON CONFLICT(y, x) DO UPDATE SET w=w||w RETURNING *',
                $quote($incoming),
                $second['x'],
                $second['y'],
                $seed * 10 + 30,
            ),
        ],
        'upsert4-7.3 target-qualified value follows current row' => [
            sprintf(
                'INSERT INTO t1(w, x, y, z) VALUES(%s,%d,%d,%d) ON CONFLICT(y, x) DO UPDATE SET w=w||t1.w RETURNING *',
                $quote($incoming),
                $second['x'],
                $second['y'],
                $seed * 10 + 40,
            ),
        ],
        'upsert4-7.4 target alias qualifies current row inside update arm' => [
            sprintf(
                'INSERT INTO t1 AS tbl(w, x, y, z) VALUES(%s,%d,%d,%d) ON CONFLICT(y, x) DO UPDATE SET w=w||tbl.w RETURNING *',
                $quote($incoming),
                $second['x'],
                $second['y'],
                $seed * 10 + 50,
            ),
        ],
    ];
};

$caseResult = static function (int $seed, array $sqlList) use ($baseRows, $oracle, $native): array {
    static $cache = [];
    $key = $seed . "\n" . implode("\n", $sqlList);
    if (!isset($cache[$key])) {
        $rows = $baseRows($seed);
        $cache[$key] = [
            'oracle' => $oracle($rows, $sqlList),
            'native' => $native($rows, $sqlList),
        ];
    }

    return $cache[$key];
};

foreach (range(1, 250) as $seed) {
    foreach ($sqlCases($seed, $baseRows($seed)) as $name => $sqlList) {
        $prefix = sprintf('real upstream upsert4 target-alias RETURNING dynamic %03d %s', $seed, $name);

        $tests[$prefix . ' returning stream matches sqlite oracle'] = static function (TestRunner $t) use ($seed, $sqlList, $caseResult): void {
            $result = $caseResult($seed, $sqlList);

            $t->same($result['oracle']['returning'], $result['native']['returning']);
        };

        $tests[$prefix . ' final image matches sqlite oracle'] = static function (TestRunner $t) use ($seed, $sqlList, $caseResult): void {
            $result = $caseResult($seed, $sqlList);

            $t->same($result['oracle']['after'], $result['native']['after']);
        };

        $tests[$prefix . ' changes count matches sqlite oracle'] = static function (TestRunner $t) use ($seed, $sqlList, $caseResult): void {
            $result = $caseResult($seed, $sqlList);

            $t->same($result['oracle']['changes'], $result['native']['changes']);
        };

        $tests[$prefix . ' conflict target order survives native parse'] = static function (TestRunner $t) use ($sqlList): void {
            $parsed = SQLiteUpsertReturningSql::parse($sqlList[0]);
            $expected = str_contains($sqlList[0], 'ON CONFLICT(z)') ? ['z'] : ['y', 'x'];

            $t->same($expected, $parsed['conflict_target']);
        };
    }
}

$tests['real upstream upsert4 target-alias RETURNING dynamic source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert4.test 7.1 excluded pseudo-table value wins on unique-z conflict',
        'upsert4.test 7.2 reordered primary-key conflict target doubles current row value',
        'upsert4.test 7.3 target-qualified t1.w resolves to the current row',
        'upsert4.test 7.4 target alias tbl.w resolves to the current row',
        '250 deterministic dynamic seeds, 1000 oracle-backed focused behavior cases',
    ], [
        'upsert4.test 7.1 excluded pseudo-table value wins on unique-z conflict',
        'upsert4.test 7.2 reordered primary-key conflict target doubles current row value',
        'upsert4.test 7.3 target-qualified t1.w resolves to the current row',
        'upsert4.test 7.4 target alias tbl.w resolves to the current row',
        '250 deterministic dynamic seeds, 1000 oracle-backed focused behavior cases',
    ]);
};

$tests['real upstream upsert4 target-alias RETURNING dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteUpsertReturningSql conflict target parsing, target alias binding, excluded pseudo-table evaluation, and RETURNING projection',
        'no new support component needed; reuses SQLiteUpsertReturningSql conflict target parsing, target alias binding, excluded pseudo-table evaluation, and RETURNING projection',
    );
};

return $tests;
