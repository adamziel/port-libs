<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamTriggerFkeyDynamicPlan;

$valueAt = static function (array $array, string $path): mixed {
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

$plan = SQLiteUpstreamTriggerFkeyDynamicPlan::triggerBViewUpdateAndNameResolution();

$tests = [
    'real upstream triggerB view update and name resolution cites source blocks' => static function (TestRunner $t) use ($plan): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerB.test');

        $t->true(is_string($source));
        $t->contains('CREATE TEMP TRIGGER tx INSTEAD OF UPDATE OF y ON vx', $source);
        $t->contains('SELECT wen.x; -- Unrecognized name', $source);
        $t->contains('CREATE TRIGGER r1t2 AFTER UPDATE ON t2', $source);
        $t->contains('SELECT dlo.x; -- Unrecognized name', $source);
        $t->same('triggerB.test', $plan['source']);
        $t->same([
            'triggerB-1.1',
            'triggerB-1.2',
            'triggerB-2.1',
            'triggerB-2.2',
            'triggerB-2.3',
            'triggerB-2.4',
        ], $plan['scenarios']);
    },
];

foreach ($plan['view_update_cases'] as $case) {
    $label = 'real upstream triggerB temp view update-of dynamic ' . $case['variant'];
    $expectations = [
        'source' => 'triggerB.test',
        'trigger' => 'tx',
        'view' => 'vx',
        'statement' => 'UPDATE vx SET y = yy',
        'update_of.0' => 'y',
        'trigger_fired_count' => 2,
        'unmentioned_view_column_preserved' => true,
        'instead_of_trigger_updates_base_table' => true,
        'final_view_rows.0.y' => $case['initial_view_rows'][0]['yy'],
        'final_view_rows.0.yy' => $case['initial_view_rows'][0]['yy'],
        'final_view_rows.1.y' => $case['initial_view_rows'][1]['yy'],
        'final_view_rows.1.yy' => $case['initial_view_rows'][1]['yy'],
        'updated_rows.0.old_y' => $case['initial_view_rows'][0]['y'],
        'updated_rows.0.new_y' => $case['initial_view_rows'][0]['yy'],
        'updated_rows.1.old_y' => $case['initial_view_rows'][1]['y'],
        'updated_rows.1.new_y' => $case['initial_view_rows'][1]['yy'],
    ];

    foreach ($expectations as $path => $expected) {
        $tests["{$label} {$path}"] = static function (TestRunner $t) use ($case, $path, $expected, $valueAt): void {
            $t->same($expected, $valueAt($case, (string) $path));
        };
    }
}

foreach ($plan['name_resolution_cases'] as $case) {
    $label = 'real upstream triggerB name resolution ' . $case['case'];
    foreach ([
        'source' => 'triggerB.test',
        'status' => 'runtime-error',
        'error' => $case['error'],
        'bad_column' => $case['bad_column'],
        'event' => $case['event'],
        'statement_rolled_back' => true,
        'trigger_created' => true,
    ] as $path => $expected) {
        $tests["{$label} {$path}"] = static function (TestRunner $t) use ($case, $path, $expected, $valueAt): void {
            $t->same($expected, $valueAt($case, (string) $path));
        };
    }
}

foreach ($plan['rowid_update_cases'] as $case) {
    $label = 'real upstream triggerB rowid update visible dynamic ' . $case['variant'];
    foreach ([
        'source' => 'triggerB.test',
        'case' => 'triggerB-2.3',
        'event' => 'update',
        'new_rowid' => $case['old_rowid'] + 10,
        'old_b' => $case['new_b'],
        'change_log.0' => $case['new_rowid'],
        'change_log.1' => $case['new_b'],
        'rowid_update_visible_to_after_trigger' => true,
    ] as $path => $expected) {
        $tests["{$label} {$path}"] = static function (TestRunner $t) use ($case, $path, $expected, $valueAt): void {
            $t->same($expected, $valueAt($case, (string) $path));
        };
    }
}

$tests['real upstream triggerB view name dependencies are non-overlapping'] = static function (TestRunner $t) use ($plan): void {
    $t->same('sqlite-upstream-triggerB-temp-view-update-of-instead-of-trigger', $plan['dependencies'][0]);
    $t->same('sqlite-upstream-triggerB-trigger-body-name-resolution-runtime-errors', $plan['dependencies'][1]);
    $t->same('sqlite-upstream-triggerB-rowid-update-visible-to-after-trigger', $plan['dependencies'][2]);
};

return $tests;
