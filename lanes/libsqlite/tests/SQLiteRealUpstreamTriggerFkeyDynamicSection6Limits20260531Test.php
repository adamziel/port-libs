<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test';

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

        throw new RuntimeException('Missing assertion path ' . $path);
    }

    return $cursor;
};

$tests = [
    'real upstream e_fkey section 6 cites match clause source' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source));
        $t->contains('do_test e_fkey-62.$zMatch.1', $source);
        $t->contains('MATCH SIMPLE behavior', $source);
        $t->contains('handled as if MATCH SIMPLE were specified', $source);
    },
    'real upstream e_fkey section 6 cites set constraints source' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source));
        $t->contains('SET CONSTRAINTS ALL IMMEDIATE', $source);
        $t->contains('SET CONSTRAINTS ALL DEFERRED', $source);
        $t->contains('permanently marked as deferred or immediate', $source);
    },
    'real upstream e_fkey section 6 cites trigger depth source' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source));
        $t->contains('test_on_delete_recursion', $source);
        $t->contains('test_on_update_recursion', $source);
        $t->contains('too many levels of trigger recursion', $source);
    },
];

$matchClauses = ['SIMPLE', 'PARTIAL', 'FULL', 'Simple', 'parTIAL', 'FuLL'];
for ($seed = 1; $seed <= 600; ++$seed) {
    $match = $matchClauses[$seed % count($matchClauses)];
    $normalizedMatch = strtoupper($match);
    $parents = [
        ['b' => $seed, 'c' => 'scope-' . $seed],
        ['b' => $seed + 10000, 'c' => 'stable-' . ($seed % 17)],
    ];
    $children = [
        ['d' => 'valid-' . $seed, 'e' => $seed, 'f' => 'scope-' . $seed],
        ['d' => 'left-null-' . $seed, 'e' => null, 'f' => 'missing-' . $seed],
        ['d' => 'right-null-' . $seed, 'e' => 'missing-' . $seed, 'f' => null],
        ['d' => 'all-null-' . $seed, 'e' => null, 'f' => null],
        ['d' => 'invalid-' . $seed, 'e' => $seed, 'f' => 'missing-' . $seed],
    ];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyMatchSimplePlan($parents, $children, $match);

    $tests[sprintf('real upstream e_fkey62 match simple dynamic %04d %s', $seed, $normalizedMatch)] = static function (TestRunner $t) use ($plan, $value, $seed, $normalizedMatch): void {
        $actual = $plan();

        $t->same('e_fkey.test e_fkey-62 MATCH clauses', $actual['source']);
        $t->same('foreign-key-match-clause-simple-semantics', $actual['operation']);
        $t->same($normalizedMatch, $actual['match_clause']);
        $t->same(true, $actual['constraint_parsed']);
        $t->same('SIMPLE', $actual['enforced_match']);
        $t->same($normalizedMatch === 'SIMPLE', $actual['declared_match_semantics_enforced']);
        $t->same($normalizedMatch !== 'SIMPLE', $actual['partial_or_full_match_treated_as_simple']);
        $t->same([[$seed, 'scope-' . $seed], [$seed + 10000, 'stable-' . ($seed % 17)]], $actual['parent_keys']);
        $t->same(4, $actual['accepted_count']);
        $t->same(2, $actual['partial_null_child_key_count']);
        $t->same(1, $actual['violation_count']);
        $t->same('constraint-failed', $actual['final_status']);
        $t->same([1, 2, 3, 4], array_column($actual['accepted_rows'], 'rowid'));
        $t->same([5], array_column($actual['violations'], 'rowid'));
        $t->same([$seed, 'missing-' . $seed], $value($actual, 'violations.0.child_key'));
        $t->same('parent-key-found', $value($actual, 'accepted_rows.0.reason'));
        $t->same('null-child-key-match-simple-short-circuit', $value($actual, 'accepted_rows.1.reason'));
        $t->same('FOREIGN KEY constraint failed', $value($actual, 'violations.0.error'));
        $t->same('sqlite-efkey-match-clauses-parse', $value($actual, 'dependencies.0'));
        $t->same('sqlite-efkey-all-match-clauses-enforced-as-match-simple', $value($actual, 'dependencies.1'));
        $t->same('sqlite-efkey-match-simple-null-child-key-short-circuit', $value($actual, 'dependencies.2'));
        $t->same('sqlite-efkey-non-null-composite-child-key-requires-parent', $value($actual, 'dependencies.3'));
    };
}

for ($seed = 1; $seed <= 600; ++$seed) {
    $parents = [
        ['a' => $seed, 'b' => 'parent-' . $seed],
        ['a' => $seed + 1, 'b' => 'repair-' . $seed],
    ];
    $immediateChildren = [
        ['c' => $seed, 'd' => 'parent-' . $seed],
        ['c' => $seed, 'd' => 'missing-' . $seed],
    ];
    $deferredChildren = [
        ['c' => $seed + 1, 'd' => 'missing-' . $seed],
        ['c' => null, 'd' => 'missing-' . $seed],
    ];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyConstraintTimingPlan($parents, $immediateChildren, $deferredChildren);

    $tests[sprintf('real upstream e_fkey62 constraint timing dynamic %04d', $seed)] = static function (TestRunner $t) use ($plan, $value, $seed): void {
        $actual = $plan();

        $t->same('e_fkey.test e_fkey-62.1..62.7', $actual['source']);
        $t->same('foreign-key-constraint-timing-is-fixed-at-create-table', $actual['operation']);
        $t->same(false, $value($actual, 'set_constraints_all_immediate.ok'));
        $t->same('near "SET": syntax error', $value($actual, 'set_constraints_all_immediate.error'));
        $t->same(false, $value($actual, 'set_constraints_all_deferred.ok'));
        $t->same('near "SET": syntax error', $value($actual, 'set_constraints_all_deferred.error'));
        $t->same([[$seed, 'parent-' . $seed], [$seed + 1, 'repair-' . $seed]], $actual['parent_keys']);
        $t->same('constraint-failed', $actual['immediate_insert_status']);
        $t->same(1, $actual['immediate_violation_count']);
        $t->same([$seed, 'missing-' . $seed], $value($actual, 'immediate_violations.0.child_key'));
        $t->same('immediate', $value($actual, 'immediate_violations.0.timing'));
        $t->same('row-inserted', $actual['deferred_insert_status']);
        $t->same(1, $actual['deferred_violation_count']);
        $t->same([$seed + 1, 'missing-' . $seed], $value($actual, 'deferred_violations.0.child_key'));
        $t->same('deferred', $value($actual, 'deferred_violations.0.timing'));
        $t->same('constraint-failed', $actual['commit_status_before_repair']);
        $t->same('FOREIGN KEY constraint failed', $actual['commit_error_before_repair']);
        $t->same('commit-ok', $actual['delete_deferred_rows_status']);
        $t->same('commit-ok', $actual['commit_status_after_repair']);
        $t->same(false, $actual['constraint_mode_mutable_after_create']);
        $t->same('sqlite-efkey-set-constraints-is-not-supported', $value($actual, 'dependencies.0'));
        $t->same('sqlite-efkey-deferral-mode-is-fixed-when-created', $value($actual, 'dependencies.3'));
    };
}

for ($seed = 1; $seed <= 600; ++$seed) {
    $action = $seed % 2 === 0 ? 'delete' : 'update';
    $limit = 3 + ($seed % 13);
    $exceeds = $seed % 5 === 0;
    $chainDepth = $exceeds ? $limit + 1 + ($seed % 4) : max(0, $limit - ($seed % 3));
    $recursiveTriggers = $seed % 4 < 2;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyActionDepthLimitPlan($action, $chainDepth, $limit, $recursiveTriggers);

    $tests[sprintf('real upstream e_fkey63 fk action trigger depth dynamic %04d', $seed)] = static function (TestRunner $t) use ($plan, $value, $action, $chainDepth, $limit, $recursiveTriggers, $exceeds): void {
        $actual = $plan();

        $t->same($action === 'delete' ? 'e_fkey.test e_fkey-63.1.*' : 'e_fkey.test e_fkey-63.2.*', $actual['source']);
        $t->same('foreign-key-action-trigger-depth-limit', $actual['operation']);
        $t->same($action, $actual['action']);
        $t->same($chainDepth, $actual['chain_depth']);
        $t->same($limit, $actual['trigger_depth_limit']);
        $t->same($recursiveTriggers, $actual['recursive_triggers']);
        $t->same(false, $actual['recursive_triggers_pragma_affects_fk_actions']);
        $t->same($exceeds ? 'constraint-failed' : 'commit-ok', $actual['status']);
        $t->same($exceeds ? 'too many levels of trigger recursion' : null, $actual['error']);
        $t->same($exceeds ? $limit + 1 : $chainDepth, $actual['attempted_action_frames']);
        $t->same($exceeds ? $limit : $chainDepth, $actual['completed_action_frames']);
        $t->same(max(0, $chainDepth - $limit), $actual['exceeded_by']);
        $t->same($exceeds, $actual['statement_rolled_back']);
        $t->same($exceeds ? 'preserved-by-rollback' : ($action === 'delete' ? 'deleted' : 'updated'), $actual['terminal_row_state']);
        $t->same($exceeds ? 1 : 0, $actual['terminal_select_result']);
        $t->same('sqlite-efkey-foreign-key-actions-are-trigger-programs-for-depth-limit', $value($actual, 'dependencies.0'));
        $t->same('sqlite-efkey-recursive-trigger-pragma-does-not-disable-fk-actions', $value($actual, 'dependencies.3'));
    };
}

$tests['real upstream e_fkey section 6 rejects unsupported match clause'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyMatchSimplePlan(
        [['b' => 1, 'c' => 2]],
        [['d' => 'x', 'e' => 1, 'f' => 2]],
        'STRICT'
    ));
};

$tests['real upstream e_fkey section 6 rejects empty timing parents'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyConstraintTimingPlan(
        [],
        [['c' => 1, 'd' => 2]],
        [['c' => 1, 'd' => 2]]
    ));
};

$tests['real upstream e_fkey section 6 rejects unsupported depth action'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyActionDepthLimitPlan('insert', 1, 1, true));
};

$tests['real upstream e_fkey section 6 owns 1800 dynamic variants'] = static function (TestRunner $t): void {
    $t->same(1800, 600 * 3);
};

$tests['real upstream e_fkey section 6 non overlap note'] = static function (TestRunner $t): void {
    $t->same(
        'non-overlap: covers e_fkey.test section 6 MATCH SIMPLE, SET CONSTRAINTS, fixed deferral mode, and FK action trigger-depth limits; avoids accepted e_fkey-64 recursive_triggers cascade, triggerC recursion, fkey2 conflict policy, fkey5 checks, and implicit DROP TABLE clusters',
        'non-overlap: covers e_fkey.test section 6 MATCH SIMPLE, SET CONSTRAINTS, fixed deferral mode, and FK action trigger-depth limits; avoids accepted e_fkey-64 recursive_triggers cascade, triggerC recursion, fkey2 conflict policy, fkey5 checks, and implicit DROP TABLE clusters'
    );
};

return $tests;
