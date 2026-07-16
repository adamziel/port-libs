<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDmlTriggerRecursionPlan;

$baseTrigger = [[
    'timing' => 'after',
    'event' => 'insert',
    'table' => 'target',
    'action' => 'insert',
    'when' => ['column' => 'level', 'operator' => '<', 'value' => 4],
    'insert_row' => [
        'option_id' => 'new_increment.option_id',
        'option_name' => 'concat:new.option_name::child',
        'level' => 'new_increment.level',
        'autoload' => 'new.autoload',
    ],
]];

$beforeTrigger = $baseTrigger;
$beforeTrigger[0]['timing'] = 'before';

$run = static function (array $triggers = null, array $input = null, string $conflictAction = 'abort', array $options = []): array {
    return SQLiteDmlTriggerRecursionPlan::insertRows(
        [],
        $input ?? [['option_id' => 1, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes']],
        $triggers ?? [],
        ['option_name'],
        $conflictAction,
        $options
    );
};

$tests = [
    'dml trigger recursion corpus after trigger inserts recursive rows' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $t->same(['plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'plugin_seed:child:child:child'], array_column($run($baseTrigger)['rows'], 'option_name'));
    },
    'dml trigger recursion corpus after trigger increments levels' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $t->same([1, 2, 3, 4], array_column($run($baseTrigger)['rows'], 'level'));
    },
    'dml trigger recursion corpus after trigger changes include recursive rows' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $t->same(4, $run($baseTrigger)['changes']);
    },
    'dml trigger recursion corpus after trigger preserves copied autoload value' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $t->same(['yes', 'yes', 'yes', 'yes'], array_column($run($baseTrigger)['rows'], 'autoload'));
    },
    'dml trigger recursion corpus after trigger records trigger fire depths' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $effects = array_values(array_filter($run($baseTrigger)['effects'], static fn (array $effect): bool => $effect['action'] === 'trigger' && $effect['result'] === 'fired'));
        $t->same([0, 1, 2], array_column($effects, 'depth'));
    },
    'dml trigger recursion corpus after trigger records inserted depths' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $effects = array_values(array_filter($run($baseTrigger)['effects'], static fn (array $effect): bool => $effect['action'] === 'insert' && $effect['result'] === 'inserted'));
        $t->same([0, 1, 2, 3], array_column($effects, 'depth'));
    },
    'dml trigger recursion corpus when guard records final skip' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $t->same('when-skipped', $run($baseTrigger)['effects'][7]['result']);
    },
    'dml trigger recursion corpus reports recursive trigger option enabled' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $t->same(true, $run($baseTrigger)['recursive_triggers']);
    },
    'dml trigger recursion corpus reports max depth default' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $t->same(1000, $run($baseTrigger)['max_depth']);
    },
    'dml trigger recursion corpus inserted rows list matches final rows' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $result = $run($baseTrigger);
        $t->same(array_column($result['rows'], 'option_name'), array_column($result['inserted'], 'option_name'));
    },
    'dml trigger recursion corpus has no ignored rows on guarded recursion' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $t->same([], $run($baseTrigger)['ignored']);
    },
    'dml trigger recursion corpus before trigger inserts descendants before seed row' => static function (TestRunner $t) use ($run, $beforeTrigger): void {
        $t->same(['plugin_seed:child:child:child', 'plugin_seed:child:child', 'plugin_seed:child', 'plugin_seed'], array_column($run($beforeTrigger)['rows'], 'option_name'));
    },
    'dml trigger recursion corpus before trigger still increments levels' => static function (TestRunner $t) use ($run, $beforeTrigger): void {
        $t->same([4, 3, 2, 1], array_column($run($beforeTrigger)['rows'], 'level'));
    },
    'dml trigger recursion corpus before trigger counts descendants and seed' => static function (TestRunner $t) use ($run, $beforeTrigger): void {
        $t->same(4, $run($beforeTrigger)['changes']);
    },
    'dml trigger recursion corpus before trigger records before timing' => static function (TestRunner $t) use ($run, $beforeTrigger): void {
        $effects = array_values(array_filter($run($beforeTrigger)['effects'], static fn (array $effect): bool => $effect['action'] === 'trigger'));
        $t->same(['before', 'before', 'before', 'before'], array_column($effects, 'timing'));
    },
    'dml trigger recursion corpus recursive triggers off inserts one child only' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $t->same(['plugin_seed', 'plugin_seed:child'], array_column($run($baseTrigger, null, 'abort', ['recursive_triggers' => false])['rows'], 'option_name'));
    },
    'dml trigger recursion corpus recursive triggers off still fires top-level trigger' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $effects = array_values(array_filter($run($baseTrigger, null, 'abort', ['recursive_triggers' => false])['effects'], static fn (array $effect): bool => $effect['action'] === 'trigger'));
        $t->same(['fired', 'fired', 'recursive-trigger-suppressed'], array_column($effects, 'result'));
    },
    'dml trigger recursion corpus recursive triggers off changes exclude suppressed grandchild' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $t->same(2, $run($baseTrigger, null, 'abort', ['recursive_triggers' => false])['changes']);
    },
    'dml trigger recursion corpus recursive triggers off reports disabled option' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $t->same(false, $run($baseTrigger, null, 'abort', ['recursive_triggers' => false])['recursive_triggers']);
    },
    'dml trigger recursion corpus bounded max depth rejects runaway recursion' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $t->throws(RuntimeException::class, static fn () => $run($baseTrigger, null, 'abort', ['max_depth' => 2]));
    },
    'dml trigger recursion corpus max depth equal final depth passes' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $t->same(4, $run($baseTrigger, null, 'abort', ['max_depth' => 4])['changes']);
    },
    'dml trigger recursion corpus zero max depth allows top-level insert only when trigger suppressed by when' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 1;
        $t->same(['plugin_seed'], array_column($run($trigger, null, 'abort', ['max_depth' => 0])['rows'], 'option_name'));
    },
    'dml trigger recursion corpus negative max depth rejected' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $t->throws(InvalidArgumentException::class, static fn () => $run($baseTrigger, null, 'abort', ['max_depth' => -1]));
    },
    'dml trigger recursion corpus ignore skips recursive duplicate child' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $t->same(['plugin_seed'], array_column($run($trigger, null, 'ignore')['rows'], 'option_name'));
    },
    'dml trigger recursion corpus ignore records duplicate child ignored' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $t->same(['plugin_seed'], array_column($run($trigger, null, 'ignore')['ignored'], 'option_name'));
    },
    'dml trigger recursion corpus ignore leaves change count at seed row' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $t->same(1, $run($trigger, null, 'ignore')['changes']);
    },
    'dml trigger recursion corpus replace applies recursive duplicate child' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['insert_row']['level'] = 2;
        $t->same([2], array_column($run($trigger, null, 'replace')['rows'], 'level'));
    },
    'dml trigger recursion corpus replace counts seed and replacement' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['insert_row']['level'] = 2;
        $t->same(2, $run($trigger, null, 'replace')['changes']);
    },
    'dml trigger recursion corpus abort rejects recursive duplicate child' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
    'dml trigger recursion corpus rollback rejects recursive duplicate like abort' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger, null, 'rollback'));
    },
    'dml trigger recursion corpus trigger conflict action overrides statement abort' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['conflict_action'] = 'ignore';
        $t->same(1, $run($trigger)['changes']);
    },
    'dml trigger recursion corpus statement ignore overrides recursive trigger fail conflict' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['conflict_action'] = 'fail';
        $t->same(['plugin_seed'], array_column($run($trigger, null, 'ignore')['rows'], 'option_name'));
    },
    'dml trigger recursion corpus statement ignore records ignored child under trigger fail' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['conflict_action'] = 'fail';
        $t->same(['plugin_seed'], array_column($run($trigger, null, 'ignore')['ignored'], 'option_name'));
    },
    'dml trigger recursion corpus statement ignore changes exclude failed child override' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['conflict_action'] = 'fail';
        $t->same(1, $run($trigger, null, 'ignore')['changes']);
    },
    'dml trigger recursion corpus statement ignore effect records effective ignore' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['conflict_action'] = 'fail';
        $effects = $run($trigger, null, 'ignore')['effects'];
        $t->same(['ignore', 'ignore', 'ignore'], array_column($effects, 'effective_conflict_action'));
    },
    'dml trigger recursion corpus statement ignore suppresses trigger rollback conflict' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['conflict_action'] = 'rollback';
        $t->same(1, $run($trigger, null, 'ignore')['changes']);
    },
    'dml trigger recursion corpus statement ignore suppresses trigger abort conflict' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['conflict_action'] = 'abort';
        $t->same(['ignored-conflict'], array_values(array_filter(array_column($run($trigger, null, 'ignore')['effects'], 'result'), static fn (string $result): bool => $result === 'ignored-conflict')));
    },
    'dml trigger recursion corpus statement replace overrides recursive trigger fail conflict' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['insert_row']['level'] = 7;
        $trigger[0]['conflict_action'] = 'fail';
        $t->same([7], array_column($run($trigger, null, 'replace')['rows'], 'level'));
    },
    'dml trigger recursion corpus statement replace counts replacement despite trigger fail' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['insert_row']['level'] = 7;
        $trigger[0]['conflict_action'] = 'fail';
        $t->same(2, $run($trigger, null, 'replace')['changes']);
    },
    'dml trigger recursion corpus statement replace records replaced child under trigger fail' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['insert_row']['level'] = 7;
        $trigger[0]['conflict_action'] = 'fail';
        $t->same(['replaced-conflict'], array_values(array_filter(array_column($run($trigger, null, 'replace')['effects'], 'result'), static fn (string $result): bool => $result === 'replaced-conflict')));
    },
    'dml trigger recursion corpus statement replace effect records effective replace' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['insert_row']['level'] = 7;
        $trigger[0]['conflict_action'] = 'fail';
        $effects = $run($trigger, null, 'replace')['effects'];
        $t->same(['replace'], array_values(array_unique(array_column($effects, 'effective_conflict_action'))));
    },
    'dml trigger recursion corpus statement replace suppresses trigger rollback conflict' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['insert_row']['level'] = 9;
        $trigger[0]['conflict_action'] = 'rollback';
        $t->same([9], array_column($run($trigger, null, 'replace')['rows'], 'level'));
    },
    'dml trigger recursion corpus statement fail overrides trigger ignore on recursive duplicate' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['conflict_action'] = 'ignore';
        $result = $run($trigger, null, 'fail');
        $t->same(['failed-conflict'], array_values(array_filter(array_column($result['effects'], 'result'), static fn (string $result): bool => $result === 'failed-conflict')));
    },
    'dml trigger recursion corpus statement fail skips outer before row despite trigger ignore' => static function (TestRunner $t) use ($run, $beforeTrigger): void {
        $trigger = $beforeTrigger;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['conflict_action'] = 'ignore';
        $result = SQLiteDmlTriggerRecursionPlan::insertRows(
            [['option_id' => 99, 'option_name' => 'plugin_seed', 'level' => 0, 'autoload' => 'old']],
            [['option_id' => 1, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes']],
            $trigger,
            ['option_name'],
            'fail'
        );
        $t->same([0], array_column($result['rows'], 'level'));
    },
    'dml trigger recursion corpus statement fail records effective fail despite trigger ignore' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['conflict_action'] = 'ignore';
        $effects = $run($trigger, null, 'fail')['effects'];
        $t->same(['fail'], array_values(array_unique(array_column($effects, 'effective_conflict_action'))));
    },
    'dml trigger recursion corpus statement rollback overrides trigger ignore on recursive duplicate' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['conflict_action'] = 'ignore';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger, null, 'rollback'));
    },
    'dml trigger recursion corpus default abort still lets trigger ignore handle duplicate' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['conflict_action'] = 'ignore';
        $t->same(['ignored-conflict'], array_values(array_filter(array_column($run($trigger)['effects'], 'result'), static fn (string $result): bool => $result === 'ignored-conflict')));
    },
    'dml trigger recursion corpus default abort still lets trigger replace handle duplicate' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['insert_row']['level'] = 5;
        $trigger[0]['conflict_action'] = 'replace';
        $t->same([5], array_column($run($trigger)['rows'], 'level'));
    },
    'dml trigger recursion corpus default abort still lets trigger fail skip before duplicate' => static function (TestRunner $t) use ($beforeTrigger): void {
        $trigger = $beforeTrigger;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['conflict_action'] = 'fail';
        $result = SQLiteDmlTriggerRecursionPlan::insertRows(
            [['option_id' => 99, 'option_name' => 'plugin_seed', 'level' => 0, 'autoload' => 'old']],
            [['option_id' => 1, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes']],
            $trigger,
            ['option_name'],
            'abort'
        );
        $t->same([0], array_column($result['rows'], 'level'));
    },
    'dml trigger recursion corpus outer ignore applies through multiple recursive duplicate rows' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 4;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['conflict_action'] = 'fail';
        $t->same(1, $run($trigger, null, 'ignore')['changes']);
    },
    'dml trigger recursion corpus outer ignore with multiple inputs skips duplicate trigger side effects only' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['conflict_action'] = 'rollback';
        $input = [
            ['option_id' => 1, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes'],
            ['option_id' => 2, 'option_name' => 'plugin_next', 'level' => 2, 'autoload' => 'no'],
        ];
        $t->same(['plugin_seed', 'plugin_next'], array_column($run($trigger, $input, 'ignore')['rows'], 'option_name'));
    },
    'dml trigger recursion corpus outer replace with multiple inputs replaces only current duplicate key' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['insert_row']['level'] = 8;
        $trigger[0]['conflict_action'] = 'fail';
        $input = [
            ['option_id' => 1, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes'],
            ['option_id' => 2, 'option_name' => 'plugin_next', 'level' => 2, 'autoload' => 'no'],
        ];
        $t->same([8, 2], array_column($run($trigger, $input, 'replace')['rows'], 'level'));
    },
    'dml trigger recursion corpus outer replace preserves ignored list empty under trigger fail' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['insert_row']['level'] = 8;
        $trigger[0]['conflict_action'] = 'fail';
        $t->same([], $run($trigger, null, 'replace')['ignored']);
    },
    'dml trigger recursion corpus outer ignore preserves inserted list at seed row only' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['conflict_action'] = 'fail';
        $t->same(['plugin_seed'], array_column($run($trigger, null, 'ignore')['inserted'], 'option_name'));
    },
    'dml trigger recursion corpus outer replace preserves inserted list with replacement child' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['insert_row']['level'] = 8;
        $trigger[0]['conflict_action'] = 'fail';
        $t->same([1, 8], array_column($run($trigger, null, 'replace')['inserted'], 'level'));
    },
    'dml trigger recursion corpus trigger conflict fail skips outer before row' => static function (TestRunner $t) use ($run, $beforeTrigger): void {
        $trigger = $beforeTrigger;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['conflict_action'] = 'fail';
        $result = SQLiteDmlTriggerRecursionPlan::insertRows(
            [['option_id' => 99, 'option_name' => 'plugin_seed', 'level' => 0, 'autoload' => 'old']],
            [['option_id' => 1, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes']],
            $trigger,
            ['option_name'],
            'abort'
        );
        $t->same(['plugin_seed'], array_column($result['rows'], 'option_name'));
    },
    'dml trigger recursion corpus trigger conflict fail records ignored outer before row' => static function (TestRunner $t) use ($run, $beforeTrigger): void {
        $trigger = $beforeTrigger;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['conflict_action'] = 'fail';
        $result = SQLiteDmlTriggerRecursionPlan::insertRows(
            [['option_id' => 99, 'option_name' => 'plugin_seed', 'level' => 0, 'autoload' => 'old']],
            [['option_id' => 1, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes']],
            $trigger,
            ['option_name'],
            'abort'
        );
        $t->same(['plugin_seed'], array_column($result['ignored'], 'option_name'));
    },
    'dml trigger recursion corpus multiple input rows recurse independently' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $input = [
            ['option_id' => 1, 'option_name' => 'plugin_a', 'level' => 1, 'autoload' => 'yes'],
            ['option_id' => 10, 'option_name' => 'plugin_b', 'level' => 2, 'autoload' => 'no'],
        ];
        $t->same(7, $run($baseTrigger, $input)['changes']);
    },
    'dml trigger recursion corpus multiple input rows preserve independent autoload values' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $input = [
            ['option_id' => 1, 'option_name' => 'plugin_a', 'level' => 1, 'autoload' => 'yes'],
            ['option_id' => 10, 'option_name' => 'plugin_b', 'level' => 2, 'autoload' => 'no'],
        ];
        $t->same(['yes', 'yes', 'yes', 'yes', 'no', 'no', 'no'], array_column($run($baseTrigger, $input)['rows'], 'autoload'));
    },
    'dml trigger recursion corpus equality when guard can stop at seed value' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when'] = ['column' => 'level', 'operator' => '=', 'value' => 1];
        $t->same([1, 2], array_column($run($trigger)['rows'], 'level'));
    },
    'dml trigger recursion corpus in-list when guard can choose one level' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when'] = ['column' => 'level', 'operator' => 'in', 'value' => [1, 3]];
        $t->same([1, 2], array_column($run($trigger)['rows'], 'level'));
    },
    'dml trigger recursion corpus not-equal when guard recurses until threshold row skipped' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when'] = ['column' => 'level', 'operator' => '!=', 'value' => 3];
        $t->same([1, 2, 3], array_column($run($trigger)['rows'], 'level'));
    },
    'dml trigger recursion corpus less-equal when guard includes boundary row' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when'] = ['column' => 'level', 'operator' => '<=', 'value' => 2];
        $t->same([1, 2, 3], array_column($run($trigger)['rows'], 'level'));
    },
    'dml trigger recursion corpus malformed statement conflict rejected' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $t->throws(InvalidArgumentException::class, static fn () => $run($baseTrigger, null, 'sideways'));
    },
    'dml trigger recursion corpus malformed trigger timing rejected' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['timing'] = 'during';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
    'dml trigger recursion corpus malformed trigger event rejected' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['event'] = 'update';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
    'dml trigger recursion corpus malformed trigger table rejected' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['table'] = 'side';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
    'dml trigger recursion corpus malformed trigger action rejected' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['action'] = 'delete';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
    'dml trigger recursion corpus missing insert row rejected' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        unset($trigger[0]['insert_row']);
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
    'dml trigger recursion corpus missing NEW column rejected' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['insert_row']['autoload'] = 'new.missing';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
    'dml trigger recursion corpus missing NEW integer column rejected' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['insert_row']['option_id'] = 'new_increment.missing';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
    'dml trigger recursion corpus noninteger NEW increment rejected' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $t->throws(InvalidArgumentException::class, static fn () => $run($baseTrigger, [['option_id' => 'one', 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes']]));
    },
    'dml trigger recursion corpus malformed when clause rejected' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when'] = 'level < 4';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
    'dml trigger recursion corpus missing when column rejected' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['column'] = 'missing';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
    'dml trigger recursion corpus unsupported when operator rejected' => static function (TestRunner $t) use ($run, $baseTrigger): void {
        $trigger = $baseTrigger;
        $trigger[0]['when']['operator'] = 'between';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
    'dml trigger recursion corpus empty unique columns rejected' => static function (TestRunner $t) use ($baseTrigger): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerRecursionPlan::insertRows([], [['option_id' => 1, 'option_name' => 'plugin_seed', 'level' => 1]], $baseTrigger, []));
    },
    'dml trigger recursion corpus malformed unique column rejected' => static function (TestRunner $t) use ($baseTrigger): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerRecursionPlan::insertRows([], [['option_id' => 1, 'option_name' => 'plugin_seed', 'level' => 1]], $baseTrigger, ['1bad']));
    },
];

return $tests;
