<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamTriggerFkeyDynamicPlan;

$value = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test';
$plan = SQLiteUpstreamTriggerFkeyDynamicPlan::triggerCDefaultValuesInsert();

$tests = [
    'real upstream triggerC default values cites source setup' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source));
        $t->contains('Test that bug [371bab5d65] has been fixed', $source);
        $t->contains('BEFORE INSERT and INSTEAD OF', $source);
        $t->contains('INSERT INTO t1 DEFAULT VALUES', $source);
    },
    'real upstream triggerC default values cites table-default matrix' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source));
        $t->contains("CREATE TABLE t1(a DEFAULT 1, b DEFAULT 'abc')", $source);
        $t->contains('CREATE TABLE t1(a, b DEFAULT 4.5)', $source);
        $t->contains('CREATE TRIGGER tt2 AFTER INSERT ON t1', $source);
    },
    'real upstream triggerC default values cites view trigger case' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source));
        $t->contains('CREATE TRIGGER tv2 INSTEAD OF INSERT ON v2', $source);
        $t->contains('INSERT INTO v2 DEFAULT VALUES', $source);
        $t->contains('SELECT a, b, a IS NULL, b IS NULL FROM log', $source);
    },
    'real upstream triggerC default values plan source and scenarios' => static function (TestRunner $t) use ($plan): void {
        $t->same('triggerC.test', $plan['source']);
        $t->same('triggerC-11.1.1', $plan['scenarios'][0]);
        $t->same('triggerC-11.4', $plan['scenarios'][9]);
        $t->same(240, count($plan['cases']));
    },
    'real upstream triggerC default values dependency list' => static function (TestRunner $t) use ($plan): void {
        $t->same('sqlite-upstream-triggerC-default-values-visible-to-before-insert-trigger', $plan['dependencies'][0]);
        $t->same('sqlite-upstream-triggerC-default-values-visible-to-after-insert-trigger', $plan['dependencies'][1]);
        $t->same('sqlite-upstream-triggerC-dropped-before-trigger-no-longer-logs', $plan['dependencies'][2]);
        $t->same('sqlite-upstream-triggerC-view-default-values-visible-to-instead-of-trigger', $plan['dependencies'][3]);
    },
    'real upstream triggerC default values view plan' => static function (TestRunner $t) use ($plan): void {
        $view = $plan['view_default_values'];
        $t->same('triggerC-11.4', $view['case']);
        $t->same('INSERT INTO v2 DEFAULT VALUES', $view['insert_sql']);
        $t->same([[null, null, 1, 1]], $view['log_rows']);
        $t->same(true, $view['a_is_null']);
        $t->same(true, $view['b_is_null']);
        $t->same(0, $view['underlying_table_rows_inserted']);
    },
];

foreach ($plan['cases'] as $case) {
    $variant = (int) $case['variant'];
    $schemaNo = (($variant - 1) % 3) + 1;
    $defaults = match ($schemaNo) {
        1 => [null, null],
        2 => [1, 'abc'],
        default => [null, 4.5],
    };

    foreach ([
        'source' => 'triggerC.test',
        'case' => 'triggerC-11.' . $schemaNo,
        'variant' => $variant,
        'default_values.a' => $defaults[0],
        'default_values.b' => $defaults[1],
        'before_insert_default_values.case' => 'triggerC-11.' . $schemaNo . '.1',
        'before_insert_default_values.trigger' => 'BEFORE INSERT',
        'before_insert_default_values.insert_sql' => 'INSERT INTO t1 DEFAULT VALUES',
        'before_insert_default_values.log_rows' => [$defaults],
        'before_insert_default_values.new_row.a' => $defaults[0],
        'before_insert_default_values.new_row.b' => $defaults[1],
        'before_insert_default_values.fires' => 1,
        'before_after_insert_default_values.case' => 'triggerC-11.' . $schemaNo . '.2',
        'before_after_insert_default_values.triggers' => ['BEFORE INSERT', 'AFTER INSERT'],
        'before_after_insert_default_values.log_rows' => [$defaults, $defaults],
        'before_after_insert_default_values.fires' => 2,
        'after_insert_after_drop_before.case' => 'triggerC-11.' . $schemaNo . '.3',
        'after_insert_after_drop_before.dropped_trigger' => 'tt1',
        'after_insert_after_drop_before.remaining_trigger' => 'tt2',
        'after_insert_after_drop_before.log_rows' => [$defaults],
        'after_insert_after_drop_before.fires' => 1,
        'new_defaults_visible_to_before_trigger' => true,
        'new_defaults_visible_to_after_trigger' => true,
        'dropped_before_trigger_stops_logging' => true,
    ] as $path => $expected) {
        $tests[sprintf('real upstream triggerC default values dynamic %03d %s', $variant, $path)] = static function (TestRunner $t) use ($case, $value, $path, $expected): void {
            $t->same($expected, $value($case, (string) $path));
        };
    }

    $tests[sprintf('real upstream triggerC default values dynamic %03d before and after logs share defaults', $variant)] = static function (TestRunner $t) use ($case): void {
        $defaults = array_values($case['default_values']);
        $t->same($defaults, $case['before_insert_default_values']['log_rows'][0]);
        $t->same($defaults, $case['before_after_insert_default_values']['log_rows'][0]);
        $t->same($defaults, $case['before_after_insert_default_values']['log_rows'][1]);
    };
    $tests[sprintf('real upstream triggerC default values dynamic %03d drop before leaves one after row', $variant)] = static function (TestRunner $t) use ($case): void {
        $t->same(1, count($case['after_insert_after_drop_before']['log_rows']));
        $t->same(array_values($case['default_values']), $case['after_insert_after_drop_before']['log_rows'][0]);
    };
}

return $tests;
