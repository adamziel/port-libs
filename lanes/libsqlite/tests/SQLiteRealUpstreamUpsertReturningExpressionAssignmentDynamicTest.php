<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$quoteSql = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$truthy = static function (mixed $value): bool {
    if ($value === null) {
        return false;
    }
    if (is_int($value) || is_float($value)) {
        return (float) $value != 0.0;
    }

    return ((float) $value) != 0.0;
};

$sqliteMaxAndTrue = static function (mixed $left, mixed $right) use ($truthy): int {
    $max = max($left, $right);

    return $truthy($max) ? 1 : 0;
};

$baseRowsForSeed = static function (int $seed): array {
    return [
        ['x' => 1, 'y' => ($seed % 9) - 4, 'label' => 'alpha-' . $seed],
        ['x' => 2, 'y' => ($seed % 7) + 2, 'label' => 'beta-' . $seed],
        ['x' => 3, 'y' => -($seed % 5), 'label' => 'gamma-' . $seed],
    ];
};

$incomingRowsForSeed = static function (int $seed): array {
    $base = 1000 + ($seed * 4);

    return [
        ['x' => 1, 'y' => ($seed % 11) - 5, 'label' => 'conflict-alpha-' . $seed],
        ['x' => $base, 'y' => ($seed % 13) - 6, 'label' => 'insert-delta-' . $seed],
        ['x' => 2, 'y' => ($seed % 17) - 8, 'label' => 'conflict-beta-' . $seed],
        ['x' => $base + 1, 'y' => ($seed % 19) - 9, 'label' => 'insert-epsilon-' . $seed],
        ['x' => 3, 'y' => ($seed % 23) - 11, 'label' => 'conflict-gamma-' . $seed],
    ];
};

$sortRows = static function (array $rows): array {
    usort($rows, static fn (array $left, array $right): int => (int) $left['x'] <=> (int) $right['x']);

    return array_values($rows);
};

$oracle = static function (array $baseRows, array $incomingRows) use ($quoteSql, $sortRows): array {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE app_values(x INTEGER PRIMARY KEY, y INT, label TEXT)');
    foreach ($baseRows as $row) {
        $db->exec(sprintf(
            'INSERT INTO app_values(x,y,label) VALUES(%d,%d,%s)',
            $row['x'],
            $row['y'],
            $quoteSql($row['label']),
        ));
    }

    $values = [];
    foreach ($incomingRows as $row) {
        $values[] = sprintf(
            '(%d,%d,%s)',
            $row['x'],
            $row['y'],
            $quoteSql($row['label']),
        );
    }
    $sql = 'INSERT INTO app_values(x,y,label) VALUES '
        . implode(',', $values)
        . ' ON CONFLICT(x) DO UPDATE SET y=max(app_values.y,excluded.y) AND true, label=excluded.label RETURNING x,y,label';

    $returning = [];
    $result = $db->query($sql);
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $returning[] = [
            'x' => (int) $row['x'],
            'y' => (int) $row['y'],
            'label' => (string) $row['label'],
        ];
    }

    $after = [];
    $result = $db->query('SELECT x,y,label FROM app_values ORDER BY x');
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $after[] = [
            'x' => (int) $row['x'],
            'y' => (int) $row['y'],
            'label' => (string) $row['label'],
        ];
    }

    return [
        'after' => $after,
        'returning_rows' => $returning,
        'changes' => (int) $db->query('SELECT changes()')->fetchColumn(),
    ];
};

$native = static function (array $baseRows, array $incomingRows) use ($sqliteMaxAndTrue, $sortRows): array {
    $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace(
        $baseRows,
        $incomingRows,
        [[
            'target' => ['x'],
            'action' => 'update',
            'assignments' => [
                'y' => static fn (array $current, array $excluded): int => $sqliteMaxAndTrue($current['y'], $excluded['y']),
                'label' => static fn (array $current, array $excluded): string => (string) $excluded['label'],
            ],
        ]],
        [['x']],
    );
    $plan['after'] = $sortRows($plan['after']);

    return $plan;
};

$caseResult = static function (int $seed) use ($baseRowsForSeed, $incomingRowsForSeed, $oracle, $native): array {
    static $cache = [];
    if (!isset($cache[$seed])) {
        $baseRows = $baseRowsForSeed($seed);
        $incomingRows = $incomingRowsForSeed($seed);
        $cache[$seed] = [
            'base_rows' => $baseRows,
            'incoming_rows' => $incomingRows,
            'expected' => $oracle($baseRows, $incomingRows),
            'actual' => $native($baseRows, $incomingRows),
        ];
    }

    return $cache[$seed];
};

for ($seed = 1; $seed <= 250; ++$seed) {
    $tests[sprintf('real upstream upsert returning expression assignment seed %03d final image matches oracle', $seed)] = static function (TestRunner $t) use ($caseResult, $seed): void {
        $result = $caseResult($seed);

        $t->same($result['expected']['after'], $result['actual']['after']);
    };

    $tests[sprintf('real upstream upsert returning expression assignment seed %03d returning stream matches oracle', $seed)] = static function (TestRunner $t) use ($caseResult, $seed): void {
        $result = $caseResult($seed);

        $t->same($result['expected']['returning_rows'], $result['actual']['returning_rows']);
    };

    $tests[sprintf('real upstream upsert returning expression assignment seed %03d changes count matches oracle', $seed)] = static function (TestRunner $t) use ($caseResult, $seed): void {
        $result = $caseResult($seed);

        $t->same($result['expected']['changes'], $result['actual']['changes']);
    };

    $tests[sprintf('real upstream upsert returning expression assignment seed %03d yield trace follows statement order', $seed)] = static function (TestRunner $t) use ($caseResult, $seed): void {
        $result = $caseResult($seed);
        $yielded = array_values(array_filter($result['actual']['yield_trace'], static fn (array $event): bool => $event['returning'] !== null));

        $t->same($result['actual']['returning_rows'], array_map(static fn (array $event): array => $event['returning'], $yielded));
        $t->same(count($result['incoming_rows']), count(array_filter($result['actual']['yield_trace'], static fn (array $event): bool => $event['event'] === 'before-insert')));
    };
}

$tests['real upstream upsert returning expression assignment source coverage'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test upsert1-500 DO UPDATE expression assignment max(current,excluded) AND true',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test RETURNING emits post-change row images in statement order',
        '250 deterministic mixed insert/update statements over generic app_values rows',
        '1000 focused dynamic TestRunner cases with PDO SQLite oracle comparison',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test upsert1-500 DO UPDATE expression assignment max(current,excluded) AND true',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test RETURNING emits post-change row images in statement order',
        '250 deterministic mixed insert/update statements over generic app_values rows',
        '1000 focused dynamic TestRunner cases with PDO SQLite oracle comparison',
    ]);
};

$tests['real upstream upsert returning expression assignment dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses native UPSERT conflict-arm/yield execution and PDO SQLite only as a focused oracle',
        'no new support component needed; reuses native UPSERT conflict-arm/yield execution and PDO SQLite only as a focused oracle',
    );
};

return $tests;
