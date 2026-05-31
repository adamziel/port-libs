<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamTriggerFkeyDynamicPlan;

$tests = [];

$valueAt = static function (array $row, string $path): mixed {
    $value = $row;
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }

        throw new RuntimeException("Missing trigger6 assertion path {$path}");
    }

    return $value;
};

$plan = SQLiteUpstreamTriggerFkeyDynamicPlan::trigger6EvaluateOnce();

$tests['real upstream trigger6 evaluate once cites upstream source and scenarios'] = static function (TestRunner $t) use ($plan): void {
    $t->same('trigger6.test', $plan['source']);
    $t->same(['trigger6-1.1', 'trigger6-1.2', 'trigger6-1.3', 'trigger6-1.4', 'trigger6-1.5', 'trigger6-1.6'], $plan['scenarios']);
    $t->same('sqlite-upstream-trigger6-insert-expression-evaluated-once', $plan['dependencies'][0]);
    $t->same('sqlite-upstream-trigger6-update-expression-evaluated-once', $plan['dependencies'][1]);
    $t->same('sqlite-upstream-trigger6-new-row-uses-statement-expression-value', $plan['dependencies'][2]);
};

foreach ($plan['cases'] as $case) {
    $label = 'real upstream trigger6 evaluate-once variant ' . $case['variant'];
    $expectations = [
        'source' => 'trigger6.test',
        'insert_simple.statement_row.x' => 1,
        'insert_simple.statement_row.y' => $case['variant'],
        'insert_simple.trigger_log.trigger' => 1,
        'insert_simple.trigger_log.new_x' => 1,
        'insert_simple.trigger_log.new_y' => $case['variant'],
        'insert_simple.counter_after' => $case['variant'],
        'insert_simple.evaluations' => 1,
        'insert_simple.new_matches_statement' => true,
        'insert_expression.statement_row.x' => 2,
        'insert_expression.statement_row.y' => $case['variant'] + 5,
        'insert_expression.trigger_log.trigger' => 1,
        'insert_expression.trigger_log.new_x' => 2,
        'insert_expression.trigger_log.new_y' => $case['variant'] + 5,
        'insert_expression.counter_after' => $case['variant'] + 1,
        'insert_expression.evaluations' => 1,
        'insert_expression.new_matches_statement' => true,
        'update_expression.statement_row.x' => 2,
        'update_expression.statement_row.y' => $case['variant'] + 2,
        'update_expression.trigger_log.trigger' => 2,
        'update_expression.trigger_log.new_x' => 2,
        'update_expression.trigger_log.new_y' => $case['variant'] + 2,
        'update_expression.counter_after' => $case['variant'] + 2,
        'update_expression.evaluations' => 1,
        'update_expression.new_matches_statement' => true,
    ];

    foreach ($expectations as $path => $expected) {
        $tests["{$label} {$path}"] = static function (TestRunner $t) use ($case, $path, $expected, $valueAt): void {
            $t->same($expected, $valueAt($case, (string) $path));
        };
    }

    $tests["{$label} trigger logs reuse statement values without advancing counter"] = static function (TestRunner $t) use ($case): void {
        $t->same($case['insert_simple']['statement_row']['y'], $case['insert_simple']['trigger_log']['new_y']);
        $t->same($case['insert_expression']['statement_row']['y'], $case['insert_expression']['trigger_log']['new_y']);
        $t->same($case['update_expression']['statement_row']['y'], $case['update_expression']['trigger_log']['new_y']);
    };
}

return $tests;
