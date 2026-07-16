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

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test';
$plan = SQLiteUpstreamTriggerFkeyDynamicPlan::trigger1RaiseDatatypeCorpus();

$tests = [
    'real upstream trigger1 raise datatype cites hydrated source sections' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source));
        $t->contains('do_test trigger1-11.1', $source);
        $t->contains("catchsql {SELECT raise(abort,'message');}", $source);
        $t->contains('RAISE() may only be used within a trigger-program', $source);
        $t->contains('do_test trigger1-15.1', $source);
        $t->contains('CREATE TRIGGER tA_trigger BEFORE UPDATE ON "tA" BEGIN SELECT 1; END;', $source);
        $t->contains("catchsql { UPDATE tA SET a = 'abc' }", $source);
        $t->contains('do_test trigger1-15.2', $source);
        $t->contains("catchsql { INSERT INTO tA VALUES('abc', 2, 3) }", $source);
    },
    'real upstream trigger1 raise datatype corpus metadata' => static function (TestRunner $t) use ($plan): void {
        $t->same('trigger1.test', $plan['source']);
        $t->same(['trigger1-11.1', 'trigger1-15.1', 'trigger1-15.2'], $plan['scenarios']);
        $t->same(250, count($plan['cases']));
        $t->same('sqlite-upstream-trigger1-raise-only-inside-trigger-program', $plan['dependencies'][0]);
        $t->same('sqlite-upstream-trigger1-datatype-mismatch-preserves-row-images', $plan['dependencies'][3]);
    },
];

foreach ($plan['cases'] as $case) {
    $variant = (int) $case['variant'];

    $tests['real upstream trigger1 raise outside trigger context dynamic ' . $variant] = static function (TestRunner $t) use ($case, $valueAt): void {
        $t->same('trigger1.test', $valueAt($case, 'source'));
        $t->same(['trigger1-11.1', 'trigger1-15.1', 'trigger1-15.2'], $valueAt($case, 'scenarios'));
        $t->same("SELECT raise(abort,'message')", $valueAt($case, 'raise_outside_trigger.statement'));
        $t->same(false, $valueAt($case, 'raise_outside_trigger.ok'));
        $t->same('RAISE() may only be used within a trigger-program', $valueAt($case, 'raise_outside_trigger.error'));
        $t->same(true, $valueAt($case, 'raise_outside_trigger.trigger_program_context_required'));
        $t->same(0, $valueAt($case, 'raise_outside_trigger.side_effect_count'));
    };

    $tests['real upstream trigger1 before update ipk datatype mismatch dynamic ' . $variant] = static function (TestRunner $t) use ($case, $variant, $valueAt): void {
        $expectedRow = ['a' => $variant + 1000, 'b' => $variant + 2, 'c' => $variant + 3];

        $t->same('tA', $valueAt($case, 'before_update_integer_primary_key.table'));
        $t->same('tA_trigger', $valueAt($case, 'before_update_integer_primary_key.trigger'));
        $t->same('BEFORE UPDATE', $valueAt($case, 'before_update_integer_primary_key.trigger_timing'));
        $t->same('SELECT 1', $valueAt($case, 'before_update_integer_primary_key.trigger_body'));
        $t->same(['a' => 'abc-' . $variant], $valueAt($case, 'before_update_integer_primary_key.assignment'));
        $t->same(false, $valueAt($case, 'before_update_integer_primary_key.ok'));
        $t->same('datatype-mismatch', $valueAt($case, 'before_update_integer_primary_key.status'));
        $t->same('datatype mismatch', $valueAt($case, 'before_update_integer_primary_key.error'));
        $t->same($expectedRow, $valueAt($case, 'before_update_integer_primary_key.row_after_statement'));
    };

    $tests['real upstream trigger1 insert ipk datatype mismatch dynamic ' . $variant] = static function (TestRunner $t) use ($case, $variant, $valueAt): void {
        $initialRow = ['a' => $variant + 1000, 'b' => $variant + 2, 'c' => $variant + 3];
        $accepted = ['a' => $variant + 1001, 'b' => $variant + 4, 'c' => $variant + 5];
        $rejected = ['a' => 'abc-' . $variant, 'b' => $variant + 6, 'c' => $variant + 7];

        $t->same($accepted, $valueAt($case, 'insert_integer_primary_key.accepted_integer_row'));
        $t->same($rejected, $valueAt($case, 'insert_integer_primary_key.rejected_text_row'));
        $t->same(false, $valueAt($case, 'insert_integer_primary_key.ok'));
        $t->same('datatype-mismatch', $valueAt($case, 'insert_integer_primary_key.status'));
        $t->same('datatype mismatch', $valueAt($case, 'insert_integer_primary_key.error'));
        $t->same(true, $valueAt($case, 'insert_integer_primary_key.integer_insert_visible'));
        $t->same(false, $valueAt($case, 'insert_integer_primary_key.text_insert_visible'));
        $t->same([$initialRow, $accepted], $valueAt($case, 'insert_integer_primary_key.rows_after_statement'));
    };

    $tests['real upstream trigger1 raise datatype row preservation dynamic ' . $variant] = static function (TestRunner $t) use ($case, $variant, $valueAt): void {
        $t->same($variant, $case['variant']);
        $t->same('trigger1-11.1/15.1/15.2-' . $variant, $case['case']);
        $t->same('a', $valueAt($case, 'before_update_integer_primary_key.integer_primary_key_column'));
        $t->same(true, $valueAt($case, 'before_update_integer_primary_key.trigger_installed'));
        $t->same(true, $valueAt($case, 'before_update_integer_primary_key.statement_rolled_back'));
        $t->same(0, $valueAt($case, 'before_update_integer_primary_key.trigger_side_effect_count'));
        $t->same(true, $valueAt($case, 'before_update_integer_primary_key.text_key_rejected_before_storage'));
        $t->same(0, $valueAt($case, 'insert_integer_primary_key.trigger_side_effect_count'));
        $t->same(true, $valueAt($case, 'insert_integer_primary_key.text_key_rejected_before_storage'));
    };
}

return $tests;
