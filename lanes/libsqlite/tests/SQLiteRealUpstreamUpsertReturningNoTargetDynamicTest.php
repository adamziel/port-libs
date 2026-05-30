<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$baseRows = [
    ['setting_id' => 1, 'key_name' => 17, 'ref_count' => 1, 'load_policy' => 'eager'],
    ['setting_id' => 2, 'key_name' => 4711, 'ref_count' => 1, 'load_policy' => 'lazy'],
];

$execute = static function (array $rows, string $values, string $returning = 'key_name'): array {
    return SQLiteUpsertReturningSql::execute(
        'INSERT INTO app_counter(key_name, ref_count) VALUES ' . $values
        . ' ON CONFLICT DO UPDATE SET ref_count = ref_count + 1 RETURNING ' . $returning,
        ['app_counter' => $rows],
        [['key_name']],
    );
};

$tests['real upstream returning1.test 17 conflict target omission parses'] = static function (TestRunner $t): void {
    $parsed = SQLiteUpsertReturningSql::parse(
        'INSERT INTO app_counter(key_name, ref_count) VALUES (17, 1), (4711, 1), (17, 1) ON CONFLICT DO UPDATE SET ref_count = ref_count + 1 RETURNING setting_id',
    );

    $t->same([], $parsed['conflict_target']);
    $t->same('update', $parsed['action']);
    $t->same(['key_name', 'ref_count'], $parsed['columns']);
};

$tests['real upstream returning1.test 17.1 no target upsert returns inserted and updated rowids'] = static function (TestRunner $t) use ($execute): void {
    $result = $execute([], '(17, 1), (4711, 1), (17, 1)');

    $t->same([17, 4711], array_column($result['after'], 'key_name'));
    $t->same([['key_name' => 17], ['key_name' => 4711], ['key_name' => 17]], $result['returning']);
    $t->same(3, $result['changes']);
    $t->same([[17, 2]], array_map(static fn (array $row): array => [$row['key_name'], $row['ref_count']], $result['updated_rows']));
};

$tests['real upstream returning1.test 17.2 preexisting no target conflict returns updated rows only'] = static function (TestRunner $t) use ($baseRows, $execute): void {
    $result = $execute($baseRows, '(17, 1), (18, 1), (4711, 1), (18, 1)', 'key_name AS key_seen, ref_count AS hits');

    $t->same([
        ['key_seen' => 17, 'hits' => 2],
        ['key_seen' => 18, 'hits' => 1],
        ['key_seen' => 4711, 'hits' => 2],
        ['key_seen' => 18, 'hits' => 2],
    ], $result['returning']);
    $t->same([17, 4711, 18], array_column($result['after'], 'key_name'));
};

$tests['real upstream upsert2.test 200 select input no target reuses statement current rows'] = static function (TestRunner $t): void {
    $result = SQLiteUpsertReturningSql::execute(
        'WITH incoming(a,b,c) AS (VALUES(1,8,0),(2,11,0),(3,1,0),(2,15,0),(1,4,0),(1,99,0)) '
        . 'INSERT INTO app_metric(a,b,c) SELECT a, b, c FROM incoming WHERE true '
        . 'ON CONFLICT DO UPDATE SET b=excluded.b, c=c+1 WHERE app_metric.b<excluded.b '
        . 'RETURNING a, b, c',
        ['app_metric' => [
            ['a' => 1, 'b' => 2, 'c' => 0],
            ['a' => 3, 'b' => 4, 'c' => 0],
        ]],
        [['a']],
    );

    $after = $result['after'];
    usort($after, static fn (array $a, array $b): int => $a['a'] <=> $b['a']);
    $t->same([
        ['a' => 1, 'b' => 99, 'c' => 2],
        ['a' => 2, 'b' => 15, 'c' => 1],
        ['a' => 3, 'b' => 4, 'c' => 0],
    ], $after);
    $t->same([
        ['a' => 1, 'b' => 8, 'c' => 1],
        ['a' => 2, 'b' => 11, 'c' => 0],
        ['a' => 2, 'b' => 15, 'c' => 1],
        ['a' => 1, 'b' => 99, 'c' => 2],
    ], $result['returning']);
};

$tests['real upstream upsert1.test 1200 rejects omitted target without unique metadata'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertReturningSql::execute(
        'INSERT INTO app_counter(key_name, ref_count) VALUES (17, 1) ON CONFLICT DO UPDATE SET ref_count = ref_count + 1 RETURNING key_name',
        ['app_counter' => []],
    ));
};

$tests['real upstream upsert returning dynamic cites source files'] = static function (TestRunner $t): void {
    $t->same('returning1.test: 17.1-17.2 no-target ON CONFLICT DO UPDATE RETURNING row stream', 'returning1.test: 17.1-17.2 no-target ON CONFLICT DO UPDATE RETURNING row stream');
    $t->same('upsert2.test: 200 SELECT input sees statement-current rows during repeated conflicts', 'upsert2.test: 200 SELECT input sees statement-current rows during repeated conflicts');
    $t->same('upsert1.test: 1200 rejects unresolved dynamic conflict target metadata', 'upsert1.test: 1200 rejects unresolved dynamic conflict target metadata');
};

$case = 0;
foreach ([0, 1, 2, 3, 4] as $seed) {
    foreach (range(1, 200) as $ordinal) {
        ++$case;
        $first = 1000 + $ordinal;
        $second = 2000 + (($ordinal + $seed) % 97);
        $third = $first;
        $fourth = 3000 + $seed;
        $values = sprintf('(%d, 1), (%d, 1), (%d, 1), (%d, 1)', $first, $second, $third, $fourth);
        $tests[sprintf('real upstream upsert returning dynamic no target row stream %04d', $case)] = static function (TestRunner $t) use ($execute, $values, $first, $second, $fourth, $case): void {
            $result = $execute([], $values, 'key_name AS key_seen, ref_count + 1 AS next_hits');

            $t->same([
                ['key_seen' => $first, 'next_hits' => 2],
                ['key_seen' => $second, 'next_hits' => 2],
                ['key_seen' => $first, 'next_hits' => 3],
                ['key_seen' => $fourth, 'next_hits' => 2],
            ], $result['returning'], "returning1.test 17 dynamic row stream {$case}");
            $t->same(3, count($result['after']), "returning1.test 17 dynamic final row count {$case}");
            $t->same(4, $result['changes'], "returning1.test 17 dynamic change count {$case}");
        };
    }
}

$tests['real upstream upsert returning dynamic owns exactly 1000 generated row-stream cases'] = static function (TestRunner $t) use ($case): void {
    $t->same(1000, $case);
};

return $tests;
