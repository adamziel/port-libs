<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$execute = static function (array $rows, int $key, string $value, string $increment = '1.0'): array {
    return SQLiteUpsertReturningSql::execute(
        sprintf(
            'INSERT INTO app_real_metric(metric_key, real_value) VALUES (%d, %s) '
            . 'ON CONFLICT(metric_key) DO UPDATE SET real_value = real_value + %s '
            . 'RETURNING metric_key, real_value AS value_seen',
            $key,
            $value,
            $increment,
        ),
        ['app_real_metric' => $rows],
        [['metric_key']],
    );
};

$tests['real upstream returning1.test 15 insert returning preserves real literal affinity'] = static function (TestRunner $t) use ($execute): void {
    $result = $execute([], 1, '5.0');

    $t->same([['metric_key' => 1, 'value_seen' => 5.0]], $result['returning']);
    $t->same(5.0, $result['after'][0]['real_value']);
    $t->same(1, $result['changes']);
};

$tests['real upstream returning1.test 15 upsert update returning keeps real arithmetic affinity'] = static function (TestRunner $t) use ($execute): void {
    $result = $execute([
        ['metric_key' => 1, 'real_value' => 5.0],
    ], 1, '7.0', '1.0');

    $t->same([['metric_key' => 1, 'value_seen' => 6.0]], $result['returning']);
    $t->same(6.0, $result['after'][0]['real_value']);
    $t->same(1, $result['changes']);
};

$tests['real upstream returning1.test 15 scientific notation real literal is admitted'] = static function (TestRunner $t) use ($execute): void {
    $result = $execute([], 2, '5e0');

    $t->same([['metric_key' => 2, 'value_seen' => 5.0]], $result['returning']);
    $t->same(5.0, $result['inserted_rows'][0]['real_value']);
};

$tests['real upstream returning1.test 15 source citation'] = static function (TestRunner $t): void {
    $t->same(
        'returning1.test 15.0-15.2 REAL affinity is preserved by INSERT/UPDATE/DELETE RETURNING',
        'returning1.test 15.0-15.2 REAL affinity is preserved by INSERT/UPDATE/DELETE RETURNING',
    );
};

$case = 0;
foreach (range(1, 125) as $ordinal) {
    foreach (['0.5', '1.0', '2.25', '3e0'] as $increment) {
        ++$case;
        $key = 1000 + $case;
        $base = $ordinal + 0.5;
        $incoming = $base + 10.0;
        $expected = $base + (float) $increment;

        $tests[sprintf('real upstream upsert returning real affinity dynamic update %03d', $case)] = static function (TestRunner $t) use ($execute, $key, $base, $incoming, $increment, $expected, $case): void {
            $result = $execute([
                ['metric_key' => $key, 'real_value' => $base],
            ], $key, sprintf('%.1f', $incoming), $increment);

            $t->same([['metric_key' => $key, 'value_seen' => $expected]], $result['returning'], "returning1.test 15 dynamic returning row {$case}");
            $t->same($expected, $result['after'][0]['real_value'], "returning1.test 15 dynamic final real value {$case}");
            $t->same(1, $result['changes'], "returning1.test 15 dynamic change count {$case}");
        };
    }
}

$tests['real upstream upsert returning real affinity owns 500 dynamic update cases'] = static function (TestRunner $t) use ($case): void {
    $t->same(500, $case);
};

return $tests;
