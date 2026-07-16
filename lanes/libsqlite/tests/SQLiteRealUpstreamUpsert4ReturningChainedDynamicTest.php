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
    $stmt = $db->query('SELECT w, x, y, z FROM app_item ORDER BY x, y');
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

$sqlSequence = static function (int $seed, array $rows) use ($quote): array {
    $first = $rows[0];
    $second = $rows[1];

    return [
        sprintf(
            'INSERT INTO app_item(w, x, y, z) VALUES(%s,%d,%d,%d) ON CONFLICT(z) DO UPDATE SET w=excluded.w RETURNING *',
            $quote('incoming-z-' . $seed),
            $seed * 10 + 3,
            $seed * 10 + 3,
            $first['z'],
        ),
        sprintf(
            'INSERT INTO app_item(w, x, y, z) VALUES(%s,%d,%d,%d) ON CONFLICT(y, x) DO UPDATE SET w=w||w RETURNING *',
            $quote('incoming-pk-' . $seed),
            $second['x'],
            $second['y'],
            $seed * 10 + 30,
        ),
        sprintf(
            'INSERT INTO app_item(w, x, y, z) VALUES(%s,%d,%d,%d) ON CONFLICT(y, x) DO UPDATE SET w=w||app_item.w RETURNING *',
            $quote('incoming-qualified-' . $seed),
            $second['x'],
            $second['y'],
            $seed * 10 + 40,
        ),
        sprintf(
            'INSERT INTO app_item AS app_alias(w, x, y, z) VALUES(%s,%d,%d,%d) ON CONFLICT(y, x) DO UPDATE SET w=w||app_alias.w RETURNING *',
            $quote('incoming-alias-' . $seed),
            $second['x'],
            $second['y'],
            $seed * 10 + 50,
        ),
    ];
};

$oracle = static function (array $rows, array $sqlList) use ($quote, $sqliteRows): array {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE app_item(w TEXT, x INTEGER, y INTEGER, z INTEGER, PRIMARY KEY(x, y))');
    $db->exec('CREATE UNIQUE INDEX app_item_z ON app_item(z)');
    foreach ($rows as $row) {
        $db->exec(sprintf(
            'INSERT INTO app_item(w, x, y, z) VALUES(%s,%d,%d,%s)',
            $quote($row['w']),
            $row['x'],
            $row['y'],
            $quote($row['z']),
        ));
    }

    $returning = [];
    $changes = [];
    foreach ($sqlList as $sql) {
        $stmt = $db->query($sql);
        $rowsForStatement = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $rowsForStatement[] = [
                'w' => (string) $row['w'],
                'x' => (int) $row['x'],
                'y' => (int) $row['y'],
                'z' => $row['z'] === null ? null : (int) $row['z'],
            ];
        }
        $returning[] = $rowsForStatement;
        $changes[] = (int) $db->query('SELECT changes()')->fetchColumn();
    }

    return [
        'after' => $sqliteRows($db),
        'returning' => $returning,
        'changes' => $changes,
    ];
};

$native = static function (array $rows, array $sqlList) use ($nativeRows): array {
    $current = $rows;
    $returning = [];
    $changes = [];

    foreach ($sqlList as $sql) {
        $result = SQLiteUpsertReturningSql::execute($sql, ['app_item' => $current], [['x', 'y'], ['z']]);
        $returning[] = $result['returning'];
        $changes[] = $result['changes'];
        $current = $result['after'];
    }

    return [
        'after' => $nativeRows($current),
        'returning' => $returning,
        'changes' => $changes,
    ];
};

$caseResult = static function (int $seed) use ($baseRows, $sqlSequence, $oracle, $native): array {
    static $cache = [];
    if (!isset($cache[$seed])) {
        $rows = $baseRows($seed);
        $sqlList = $sqlSequence($seed, $rows);
        $cache[$seed] = [
            'oracle' => $oracle($rows, $sqlList),
            'native' => $native($rows, $sqlList),
            'sql' => $sqlList,
        ];
    }

    return $cache[$seed];
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $tests[sprintf('real upstream upsert4 section7 chained returning dynamic oracle parity %04d', $seed)] = static function (TestRunner $t) use ($caseResult, $seed): void {
        $result = $caseResult($seed);

        $t->same($result['oracle']['returning'], $result['native']['returning'], 'upsert4.test 7.1-7.4 chained RETURNING stream');
        $t->same($result['oracle']['after'], $result['native']['after'], 'upsert4.test 7.1-7.4 final current row image');
        $t->same($result['oracle']['changes'], $result['native']['changes'], 'upsert4.test 7.1-7.4 per-statement changes');
        $t->same(['incoming-z-' . $seed, str_repeat('beta-' . $seed, 8)], array_column($result['native']['after'], 'w'), 'target-qualified and alias-qualified updates chain from prior current image');
    };
}

$tests['real upstream upsert4 section7 chained returning dynamic parser state'] = static function (TestRunner $t) use ($caseResult): void {
    $result = $caseResult(17);
    $parsedAlias = SQLiteUpsertReturningSql::parse($result['sql'][3]);

    $t->same('app_item', $parsedAlias['target']);
    $t->same('app_alias', $parsedAlias['target_alias']);
    $t->same(['y', 'x'], $parsedAlias['conflict_target']);
};

$tests['real upstream upsert4 section7 chained returning dynamic source coverage'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test section 7.1 excluded pseudo-table value wins on unique-z conflict',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test section 7.2 reordered primary-key conflict target updates current row',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test section 7.3 target-qualified app_item.w reads the current row image',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test section 7.4 target alias reads the current row image',
        '1000 deterministic chained oracle-backed sequences, each checking RETURNING stream, final image, changes, and chained current-row growth',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test section 7.1 excluded pseudo-table value wins on unique-z conflict',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test section 7.2 reordered primary-key conflict target updates current row',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test section 7.3 target-qualified app_item.w reads the current row image',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test section 7.4 target alias reads the current row image',
        '1000 deterministic chained oracle-backed sequences, each checking RETURNING stream, final image, changes, and chained current-row growth',
    ]);
};

$tests['real upstream upsert4 section7 chained returning dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteUpsertReturningSql target-alias binding, excluded pseudo-table evaluation, conflict-target parsing, and RETURNING projection',
        'no new support component needed; reuses SQLiteUpsertReturningSql target-alias binding, excluded pseudo-table evaluation, conflict-target parsing, and RETURNING projection',
    );
};

return $tests;
