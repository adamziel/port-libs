<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;
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
$upstreamPlan = SQLiteUpstreamTriggerFkeyDynamicPlan::triggerCAffinityTiming();

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
    'real upstream triggerC affinity timing plan source' => static function (TestRunner $t) use ($upstreamPlan): void {
        $t->same('triggerC.test', $upstreamPlan['source']);
        $t->same(['triggerC-4.1.1', 'triggerC-4.1.2', 'triggerC-4.1.3', 'triggerC-4.1.4', 'triggerC-4.1.5', 'triggerC-4.1.6', 'triggerC-4.1.7', 'triggerC-4.1.8', 'triggerC-4.1.9'], $upstreamPlan['scenarios']);
    },
    'real upstream triggerC affinity timing dependency list' => static function (TestRunner $t) use ($upstreamPlan): void {
        $t->same('sqlite-upstream-triggerC-affinity-before-trigger-new-row', $upstreamPlan['dependencies'][0]);
        $t->same('sqlite-upstream-triggerC-auto-rowid-before-insert-negative-one', $upstreamPlan['dependencies'][1]);
        $t->same('sqlite-upstream-triggerC-real-affinity-type-visible-to-triggers', $upstreamPlan['dependencies'][2]);
        $t->same('sqlite-upstream-triggerC-update-old-new-images-affinity-coerced', $upstreamPlan['dependencies'][3]);
    },
    'real upstream triggerC affinity timing cites trigger image order block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test');
        $t->true(is_string($source) && str_contains($source, 'triggerC-4.1.*: Check that affinity transformations are made before'));
        $t->true(is_string($source) && str_contains($source, 'If the value of the rowid field is to be automatically assigned'));
    },
    'real upstream triggerC affinity timing cites real-affinity typeof guard' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test');
        $t->true(is_string($source) && str_contains($source, "typeof() such values is always 'real'"));
        $t->true(is_string($source) && str_contains($source, 'INSERT INTO t4(rowid,a,b,c) VALUES(NULL, -42.4, -42.4, -42.4)'));
    },
];

foreach ($upstreamPlan['cases'] as $case) {
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
        $tests[sprintf('real upstream triggerC affinity timing existing dynamic %03d %s', $variant, $path)] = static function (TestRunner $t) use ($case, $path, $expected, $value): void {
            $t->same($expected, $value($case, (string) $path));
        };
    }

    $tests[sprintf('real upstream triggerC affinity timing existing dynamic %03d before insert rowid rule', $variant)] = static function (TestRunner $t) use ($case): void {
        if ($case['insert_statement']['rowid'] === null) {
            $t->same(-1, $case['before_insert_log']['rowid']);
            $t->same(true, $case['auto_rowid_before_insert_is_negative_one']);
            $t->same(1, $case['after_insert_log']['rowid']);
            return;
        }

        $t->same(false, $case['auto_rowid_before_insert_is_negative_one']);
        $t->same($case['after_insert_log']['rowid'], $case['before_insert_log']['rowid']);
    };
    $tests[sprintf('real upstream triggerC affinity timing existing dynamic %03d log strings include typeof order', $variant)] = static function (TestRunner $t) use ($case): void {
        $t->contains(' integer ', $case['before_insert_log']['log']);
        $t->contains(' text ', $case['before_insert_log']['log']);
        $t->contains(' real', $case['after_update_new_log']['log']);
    };
    $tests[sprintf('real upstream triggerC affinity timing existing dynamic %03d update old new differ when assigned', $variant)] = static function (TestRunner $t) use ($case): void {
        $t->same($case['after_insert_log']['values'], $case['before_update_old_log']['values']);
        $t->same($case['before_update_new_log']['values'], $case['after_update_new_log']['values']);
    };
}

$insertRows = [
    ['a' => '1', 'b' => '1', 'c' => '1'],
    ['rowid' => 45, 'a' => 45, 'b' => 45, 'c' => 45],
    ['rowid' => -42.0, 'a' => -42.0, 'b' => -42.0, 'c' => -42.0],
    ['rowid' => null, 'a' => -42.4, 'b' => -42.4, 'c' => -42.4],
    ['a' => 7, 'b' => 7, 'c' => 7],
    ['rowid' => 12, 'a' => '9.1', 'b' => '9.1', 'c' => '9.1'],
];
$updateSets = [
    [],
    [['a' => 8, 'b' => 8, 'c' => 8]],
    [['rowid' => 2]],
    [['a' => '9', 'b' => '9', 'c' => '9']],
    [['a' => '9.1', 'b' => '9.1', 'c' => '9.1']],
    [['a' => 8, 'b' => 8, 'c' => 8], ['rowid' => 2], ['a' => '9.1', 'b' => '9.1', 'c' => '9.1']],
];

$caseNo = 0;
for ($i = 1; $i <= 120; ++$i) {
    $insert = $insertRows[$i % count($insertRows)];
    $updates = $updateSets[$i % count($updateSets)];
    $delete = $i % 3 !== 0;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCAffinityTimingPlan($insert, $updates, $delete);
    $explicitRowid = array_key_exists('rowid', $insert) && $insert['rowid'] !== null;
    $expectedLogCount = 2 + (count($updates) * 4) + ($delete ? 2 : 0);
    $expectedFinalCount = $delete ? 0 : 1;
    $case = 'triggerC-4.1 affinity before trigger dynamic ' . (++$caseNo);

    foreach ([
        'source' => 'triggerC.test triggerC-4.1.1..4.1.9',
        'operation' => 'trigger-affinity-timing-before-after-images',
        'status' => 'commit-ok',
        'log_count' => $expectedLogCount,
        'update_count' => count($updates),
        'deleted_count' => $delete ? 1 : 0,
        'real_affinity_type_preserved_in_triggers' => true,
        'integer_affinity_type_preserved_in_triggers' => true,
        'text_affinity_type_preserved_in_triggers' => true,
        'dependencies.0' => 'sqlite-triggerC-affinity-applied-before-before-trigger',
        'dependencies.1' => 'sqlite-triggerC-auto-rowid-before-insert-is-negative-one',
        'dependencies.2' => 'sqlite-triggerC-real-affinity-reports-real-in-trigger-images',
        'dependencies.3' => 'sqlite-triggerC-update-old-new-images-use-affinity-coerced-values',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests[$case . ' auto rowid before insert image'] = static function (TestRunner $t) use ($plan, $explicitRowid): void {
        $actual = $plan();
        $t->same(!$explicitRowid, $actual['auto_rowid_before_insert_is_negative_one']);
        $t->same($explicitRowid ? $actual['stored_rowid'] : -1, $actual['before_insert_rowid']);
    };

    $tests[$case . ' final row count tracks delete trigger boundary'] = static function (TestRunner $t) use ($plan, $expectedFinalCount): void {
        $t->same($expectedFinalCount, count($plan()['final_rows']));
    };

    $tests[$case . ' first log has text integer real affinity types'] = static function (TestRunner $t) use ($plan): void {
        $first = $plan()['log'][0];
        $t->same('integer', $first['rowid_type']);
        $t->same('text', $first['a_type']);
        $t->true(in_array($first['b_type'], ['integer', 'real'], true));
        $t->same('real', $first['c_type']);
    };

    $tests[$case . ' update images preserve old then new ordering'] = static function (TestRunner $t) use ($plan, $updates): void {
        $events = array_column($plan()['log'], 'event');
        if ($updates === []) {
            $t->same(false, in_array('before-update-old', $events, true));
            return;
        }
        $t->same(['before-update-old', 'before-update-new', 'after-update-old', 'after-update-new'], array_slice($events, 2, 4));
    };
}

return $tests;
