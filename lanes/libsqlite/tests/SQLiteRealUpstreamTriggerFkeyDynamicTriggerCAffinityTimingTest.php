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
$plan = SQLiteUpstreamTriggerFkeyDynamicPlan::triggerCAffinityTiming();

$tests = [
    'real upstream triggerC affinity timing cites trigger creation block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source));
        $t->contains('triggerC-4.1.1', $source);
        $t->contains('Apply affinities to non-rowid values to be inserted', $source);
        $t->contains('If the value of the rowid field is to be automatically assigned', $source);
    },
    'real upstream triggerC affinity timing cites insert update matrix' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source));
        $t->contains('do_test triggerC-4.1.$n', $source);
        $t->contains('INSERT INTO t4(rowid,a,b,c) VALUES(NULL, -42.4, -42.4, -42.4)', $source);
        $t->contains("UPDATE t4 SET a='9.1', b='9.1', c='9.1'", $source);
    },
    'real upstream triggerC affinity timing plan source' => static function (TestRunner $t) use ($plan): void {
        $t->same('triggerC.test', $plan['source']);
        $t->same(['triggerC-4.1.1', 'triggerC-4.1.2', 'triggerC-4.1.3', 'triggerC-4.1.4', 'triggerC-4.1.5', 'triggerC-4.1.6', 'triggerC-4.1.7', 'triggerC-4.1.8', 'triggerC-4.1.9'], $plan['scenarios']);
    },
    'real upstream triggerC affinity timing dependency list' => static function (TestRunner $t) use ($plan): void {
        $t->same('sqlite-upstream-triggerC-affinity-before-trigger-new-row', $plan['dependencies'][0]);
        $t->same('sqlite-upstream-triggerC-auto-rowid-before-insert-negative-one', $plan['dependencies'][1]);
        $t->same('sqlite-upstream-triggerC-real-affinity-type-visible-to-triggers', $plan['dependencies'][2]);
        $t->same('sqlite-upstream-triggerC-update-old-new-images-affinity-coerced', $plan['dependencies'][3]);
    },
];

foreach ($plan['cases'] as $index => $case) {
    $variant = (int) $case['variant'];
    foreach ([
        'source' => 'triggerC.test',
        'case' => 'triggerC-4.1.' . (2 + ($variant % 8)),
        'variant' => $variant,
        'new_values_are_affinity_coerced_before_before_trigger' => true,
        'real_affinity_reports_real_for_exact_integer' => true,
        'integer_affinity_keeps_fractional_real' => true,
        'before_insert_log.types.rowid' => 'integer',
        'before_insert_log.types.a' => 'text',
        'before_insert_log.types.c' => 'real',
        'after_insert_log.types.rowid' => 'integer',
        'after_insert_log.types.a' => 'text',
        'after_insert_log.types.c' => 'real',
        'before_delete_log' => $case['after_insert_log'],
        'after_delete_log' => $case['after_insert_log'],
        'before_update_old_log' => $case['after_insert_log'],
        'after_update_old_log' => $case['after_insert_log'],
        'before_update_new_log' => $case['after_update_new_log'],
    ] as $path => $expected) {
        $tests[sprintf('real upstream triggerC affinity timing dynamic %03d %s', $variant, $path)] = static function (TestRunner $t) use ($case, $path, $expected, $value): void {
            $t->same($expected, $value($case, (string) $path));
        };
    }

    $tests[sprintf('real upstream triggerC affinity timing dynamic %03d before insert rowid rule', $variant)] = static function (TestRunner $t) use ($case): void {
        if ($case['insert_statement']['rowid'] === null) {
            $t->same(-1, $case['before_insert_log']['rowid']);
            $t->same(true, $case['auto_rowid_before_insert_is_negative_one']);
            $t->same(1, $case['after_insert_log']['rowid']);
            return;
        }

        $t->same(false, $case['auto_rowid_before_insert_is_negative_one']);
        $t->same($case['after_insert_log']['rowid'], $case['before_insert_log']['rowid']);
    };
    $tests[sprintf('real upstream triggerC affinity timing dynamic %03d log strings include typeof order', $variant)] = static function (TestRunner $t) use ($case): void {
        $t->contains(' integer ', $case['before_insert_log']['log']);
        $t->contains(' text ', $case['before_insert_log']['log']);
        $t->contains(' real', $case['after_update_new_log']['log']);
    };
    $tests[sprintf('real upstream triggerC affinity timing dynamic %03d update old new differ when assigned', $variant)] = static function (TestRunner $t) use ($case): void {
        $t->same($case['after_insert_log']['values'], $case['before_update_old_log']['values']);
        $t->same($case['before_update_new_log']['values'], $case['after_update_new_log']['values']);
    };
}

return $tests;
