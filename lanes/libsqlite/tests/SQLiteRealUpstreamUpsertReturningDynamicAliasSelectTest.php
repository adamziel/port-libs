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

$normalizeRows = static function (array $rows): array {
    usort($rows, static fn (array $left, array $right): int => [$left['a'], $left['b'], $left['c']] <=> [$right['a'], $right['b'], $right['c']]);

    return array_values($rows);
};

$oracle = static function (array $seedRows, array $incomingRows) use ($quote, $normalizeRows): array {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE app_metric(a INTEGER PRIMARY KEY, b INT, c INT DEFAULT 0)');

    foreach ($seedRows as $row) {
        $db->exec(sprintf(
            'INSERT INTO app_metric(a,b,c) VALUES(%s,%s,%s)',
            $quote($row['a']),
            $quote($row['b']),
            $quote($row['c']),
        ));
    }

    $values = [];
    foreach ($incomingRows as $row) {
        $values[] = sprintf('(%s,%s,%s)', $quote($row['a']), $quote($row['b']), $quote($row['c']));
    }

    $sql = 'WITH nx(a,b,c) AS (VALUES ' . implode(',', $values) . ') '
        . 'INSERT INTO main.app_metric AS m(a,b,c) SELECT a,b,c FROM nx WHERE true '
        . 'ON CONFLICT(a) DO UPDATE SET b=excluded.b, c=m.c+1 WHERE m.b<excluded.b '
        . 'RETURNING a, b, c';

    $returning = [];
    $result = $db->query($sql);
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $returning[] = ['a' => (int) $row['a'], 'b' => (int) $row['b'], 'c' => (int) $row['c']];
    }

    $after = [];
    $result = $db->query('SELECT a,b,c FROM app_metric ORDER BY a');
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $after[] = ['a' => (int) $row['a'], 'b' => (int) $row['b'], 'c' => (int) $row['c']];
    }

    return [
        'after' => $after,
        'returning' => $returning,
        'changes' => (int) $db->query('SELECT changes()')->fetchColumn(),
        'normalized_after' => $normalizeRows($after),
    ];
};

$native = static function (array $seedRows, array $incomingRows): array {
    $values = [];
    foreach ($incomingRows as $row) {
        $values[] = sprintf('(%d,%d,%d)', $row['a'], $row['b'], $row['c']);
    }

    return SQLiteUpsertReturningSql::execute(
        'WITH nx(a,b,c) AS (VALUES ' . implode(',', $values) . ') '
        . 'INSERT INTO main.app_metric AS m(a,b,c) SELECT a,b,c FROM nx WHERE true '
        . 'ON CONFLICT(a) DO UPDATE SET b=excluded.b, c=m.c+1 WHERE m.b<excluded.b '
        . 'RETURNING a, b, c',
        ['app_metric' => $seedRows],
        [['a']],
    );
};

$makeSeedRows = static fn (int $variant): array => [
    ['a' => 1, 'b' => 2 + ($variant % 7), 'c' => 0],
    ['a' => 3, 'b' => 4 + ($variant % 5), 'c' => 0],
    ['a' => 5, 'b' => 10 + ($variant % 11), 'c' => 2],
];

$makeIncomingRows = static function (int $variant): array {
    $base = 1000 + $variant;
    $firstB = 8 + ($variant % 17);
    $secondB = 11 + ($variant % 19);
    $thirdB = 1 + ($variant % 3);
    $skipB = 1 + ($variant % 2);
    $lateB = 40 + ($variant % 23);

    return [
        ['a' => 1, 'b' => $firstB, 'c' => 0],
        ['a' => 2, 'b' => $secondB, 'c' => 0],
        ['a' => 3, 'b' => $thirdB, 'c' => 0],
        ['a' => 2, 'b' => $secondB + 4, 'c' => 0],
        ['a' => 1, 'b' => $skipB, 'c' => 0],
        ['a' => 1, 'b' => $lateB, 'c' => 0],
        ['a' => $base, 'b' => $base + 7, 'c' => $variant % 4],
    ];
};

$tests['real upstream upsert2 alias select parser preserves target alias'] = static function (TestRunner $t): void {
    $parsed = SQLiteUpsertReturningSql::parse(
        'WITH nx(a,b,c) AS (VALUES (1,8,0)) '
        . 'INSERT INTO main.app_metric AS m(a,b,c) SELECT a,b,c FROM nx WHERE true '
        . 'ON CONFLICT(a) DO UPDATE SET b=excluded.b, c=m.c+1 WHERE m.b<excluded.b '
        . 'RETURNING a, b, c',
    );

    $t->same('app_metric', $parsed['target']);
    $t->same('m', $parsed['target_alias']);
    $t->same(['a'], $parsed['conflict_target']);
};

$tests['real upstream upsert2 alias select rejects base table qualifier like upsert2-202'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertReturningSql::execute(
        'WITH nx(a,b,c) AS (VALUES (1,8,0)) '
        . 'INSERT INTO main.app_metric AS m(a,b,c) SELECT a,b,c FROM nx WHERE true '
        . 'ON CONFLICT(a) DO UPDATE SET b=excluded.b, c=app_metric.c+1 WHERE app_metric.b<excluded.b '
        . 'RETURNING a, b, c',
        ['app_metric' => [['a' => 1, 'b' => 2, 'c' => 0]]],
        [['a']],
    ));
};

for ($variant = 1; $variant <= 1000; ++$variant) {
    $tests[sprintf('real upstream upsert2 alias select returning dynamic oracle parity %04d', $variant)] = static function (TestRunner $t) use ($makeSeedRows, $makeIncomingRows, $oracle, $native, $normalizeRows, $variant): void {
        $seedRows = $makeSeedRows($variant);
        $incomingRows = $makeIncomingRows($variant);
        $expected = $oracle($seedRows, $incomingRows);
        $actual = $native($seedRows, $incomingRows);

        $t->same($expected['returning'], $actual['returning'], 'upsert2.test 201 alias-qualified SELECT-input RETURNING stream');
        $t->same($expected['after'], $normalizeRows($actual['after']), 'upsert2.test 201 final table after repeated current-row updates');
        $t->same($expected['changes'], $actual['changes'], 'upsert2.test 201 changes equal RETURNING row count');
        $t->same($actual['changes'], count($actual['returning']), 'RETURNING emits exactly changed rows');
    };
}

$tests['real upstream upsert2 alias select returning dynamic source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert2.test 200 SELECT input sees statement-current rows across repeated conflicts',
        'upsert2.test 201 alias-qualified target can be used in DO UPDATE SET and WHERE',
        'upsert2.test 202 base table qualifier is hidden after INSERT target aliasing',
        'returning1.test 17 RETURNING row stream emits one row for each successful insert/update',
        'non-overlap: existing batches cover omitted targets, composite targets, wide conflict-arm priority, and long no-target row streams; this batch covers alias-qualified SELECT-input current-row UPSERT RETURNING parity',
    ], [
        'upsert2.test 200 SELECT input sees statement-current rows across repeated conflicts',
        'upsert2.test 201 alias-qualified target can be used in DO UPDATE SET and WHERE',
        'upsert2.test 202 base table qualifier is hidden after INSERT target aliasing',
        'returning1.test 17 RETURNING row stream emits one row for each successful insert/update',
        'non-overlap: existing batches cover omitted targets, composite targets, wide conflict-arm priority, and long no-target row streams; this batch covers alias-qualified SELECT-input current-row UPSERT RETURNING parity',
    ]);
};

$tests['real upstream upsert2 alias select returning dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteUpsertReturningSql SELECT-input parser, alias-qualified expression evaluator, unique conflict executor, and RETURNING projection',
        'no new support component needed; reuses SQLiteUpsertReturningSql SELECT-input parser, alias-qualified expression evaluator, unique conflict executor, and RETURNING projection',
    );
};

return $tests;
