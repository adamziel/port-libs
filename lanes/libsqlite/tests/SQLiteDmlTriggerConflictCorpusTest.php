<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDmlTriggerConflictPlan;

$target = [
    ['option_id' => 1, 'option_name' => 'siteurl'],
];
$side = [
    ['option_name' => 'siteurl', 'audit' => 'old-siteurl'],
];
$insert = [
    ['option_id' => 2, 'option_name' => 'home'],
    ['option_id' => 3, 'option_name' => 'siteurl'],
];
$beforeInsertAudit = [[
    'timing' => 'before',
    'event' => 'insert',
    'table' => 'side',
    'action' => 'insert',
    'conflict_action' => 'abort',
    'row' => ['option_name' => 'new.option_name', 'audit' => 'new.option_id'],
]];
$afterInsertAudit = [[
    'timing' => 'after',
    'event' => 'insert',
    'table' => 'side',
    'action' => 'insert',
    'conflict_action' => 'abort',
    'row' => ['option_name' => 'new.option_name', 'audit' => 'after'],
]];

$run = static function (array $triggers, string $conflictAction = 'abort') use ($target, $side, $insert): array {
    return SQLiteDmlTriggerConflictPlan::insertRows($target, $side, $insert, $triggers, ['option_name'], $conflictAction);
};

$tests = [
    'dml trigger conflict corpus before insert adds side row' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $result = $run($beforeInsertAudit, 'ignore');
        $t->same('home', $result['side'][1]['option_name']);
    },
    'dml trigger conflict corpus before insert records inserted effect' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $result = $run($beforeInsertAudit, 'ignore');
        $t->same('inserted', $result['trigger_effects'][0]['result']);
    },
    'dml trigger conflict corpus statement ignore overrides trigger abort' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $result = $run($beforeInsertAudit, 'ignore');
        $t->same('ignored-conflict', $result['trigger_effects'][1]['result']);
    },
    'dml trigger conflict corpus ignore still inserts outer row' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $result = $run($beforeInsertAudit, 'ignore');
        $t->same([1, 2, 3], array_column($result['target'], 'option_id'));
    },
    'dml trigger conflict corpus ignore keeps existing side row' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $result = $run($beforeInsertAudit, 'ignore');
        $t->same('old-siteurl', $result['side'][0]['audit']);
    },
    'dml trigger conflict corpus ignore reports two outer changes' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $result = $run($beforeInsertAudit, 'ignore');
        $t->same(2, $result['changes']);
    },
    'dml trigger conflict corpus replace overrides trigger abort' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $result = $run($beforeInsertAudit, 'replace');
        $t->same('replaced-conflict', $result['trigger_effects'][1]['result']);
    },
    'dml trigger conflict corpus replace updates conflicting side row' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $result = $run($beforeInsertAudit, 'replace');
        $t->same(3, $result['side'][0]['audit']);
    },
    'dml trigger conflict corpus replace preserves side row count' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $result = $run($beforeInsertAudit, 'replace');
        $t->same(2, count($result['side']));
    },
    'dml trigger conflict corpus trigger replace applies without statement override' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $triggers = $beforeInsertAudit;
        $triggers[0]['conflict_action'] = 'replace';
        $result = $run($triggers);
        $t->same('replaced-conflict', $result['trigger_effects'][1]['result']);
    },
    'dml trigger conflict corpus trigger ignore applies without statement override' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $triggers = $beforeInsertAudit;
        $triggers[0]['conflict_action'] = 'ignore';
        $result = $run($triggers);
        $t->same('ignored-conflict', $result['trigger_effects'][1]['result']);
    },
    'dml trigger conflict corpus trigger fail skips conflicted outer row' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $triggers = $beforeInsertAudit;
        $triggers[0]['conflict_action'] = 'fail';
        $result = $run($triggers);
        $t->same([1, 2], array_column($result['target'], 'option_id'));
    },
    'dml trigger conflict corpus trigger fail records ignored outer row' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $triggers = $beforeInsertAudit;
        $triggers[0]['conflict_action'] = 'fail';
        $result = $run($triggers);
        $t->same(['siteurl'], array_column($result['ignored'], 'option_name'));
    },
    'dml trigger conflict corpus trigger fail keeps prior side effects' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $triggers = $beforeInsertAudit;
        $triggers[0]['conflict_action'] = 'fail';
        $result = $run($triggers);
        $t->same(['siteurl', 'home'], array_column($result['side'], 'option_name'));
    },
    'dml trigger conflict corpus trigger fail changes exclude skipped row' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $triggers = $beforeInsertAudit;
        $triggers[0]['conflict_action'] = 'fail';
        $result = $run($triggers);
        $t->same(1, $result['changes']);
    },
    'dml trigger conflict corpus abort raises on conflict' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $t->throws(InvalidArgumentException::class, static fn () => $run($beforeInsertAudit));
    },
    'dml trigger conflict corpus rollback raises like abort in bounded executor' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $t->throws(InvalidArgumentException::class, static fn () => $run($beforeInsertAudit, 'rollback'));
    },
    'dml trigger conflict corpus after trigger sees inserted row' => static function (TestRunner $t) use ($run, $afterInsertAudit): void {
        $result = $run($afterInsertAudit, 'ignore');
        $t->same([1, 2, 3], array_column($result['target'], 'option_id'));
    },
    'dml trigger conflict corpus after trigger ignores side conflict' => static function (TestRunner $t) use ($run, $afterInsertAudit): void {
        $result = $run($afterInsertAudit, 'ignore');
        $t->same('ignored-conflict', $result['trigger_effects'][1]['result']);
    },
    'dml trigger conflict corpus after trigger replace side row' => static function (TestRunner $t) use ($run, $afterInsertAudit): void {
        $result = $run($afterInsertAudit, 'replace');
        $t->same('after', $result['side'][0]['audit']);
    },
    'dml trigger conflict corpus after trigger fail does not undo target row' => static function (TestRunner $t) use ($run, $afterInsertAudit): void {
        $triggers = $afterInsertAudit;
        $triggers[0]['conflict_action'] = 'fail';
        $result = $run($triggers);
        $t->same([1, 2, 3], array_column($result['target'], 'option_id'));
    },
    'dml trigger conflict corpus after trigger fail marks conflict effect' => static function (TestRunner $t) use ($run, $afterInsertAudit): void {
        $triggers = $afterInsertAudit;
        $triggers[0]['conflict_action'] = 'fail';
        $result = $run($triggers);
        $t->same('failed-conflict', $result['trigger_effects'][1]['result']);
    },
    'dml trigger conflict corpus multiple triggers fire in order' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $extra = $beforeInsertAudit[0];
        $extra['row'] = ['option_name' => 'new.option_name', 'audit' => 'second'];
        $extra['conflict_action'] = 'ignore';
        $result = $run([$beforeInsertAudit[0], $extra], 'ignore');
        $t->same(['inserted', 'ignored-conflict', 'ignored-conflict', 'ignored-conflict'], array_column($result['trigger_effects'], 'result'));
    },
    'dml trigger conflict corpus new column substitution uses input value' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $result = $run($beforeInsertAudit, 'replace');
        $t->same(3, $result['side'][0]['audit']);
    },
    'dml trigger conflict corpus literal trigger row value is preserved' => static function (TestRunner $t) use ($run, $afterInsertAudit): void {
        $result = $run($afterInsertAudit, 'replace');
        $t->same('after', $result['side'][0]['audit']);
    },
    'dml trigger conflict corpus malformed statement conflict rejected' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $t->throws(InvalidArgumentException::class, static fn () => $run($beforeInsertAudit, 'sideways'));
    },
    'dml trigger conflict corpus malformed trigger conflict rejected' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $triggers = $beforeInsertAudit;
        $triggers[0]['conflict_action'] = 'sideways';
        $t->throws(InvalidArgumentException::class, static fn () => $run($triggers));
    },
    'dml trigger conflict corpus unsupported trigger target rejected' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $triggers = $beforeInsertAudit;
        $triggers[0]['table'] = 'other';
        $t->throws(InvalidArgumentException::class, static fn () => $run($triggers, 'ignore'));
    },
    'dml trigger conflict corpus unsupported trigger action rejected' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $triggers = $beforeInsertAudit;
        $triggers[0]['action'] = 'update';
        $t->throws(InvalidArgumentException::class, static fn () => $run($triggers, 'ignore'));
    },
    'dml trigger conflict corpus missing new column rejected' => static function (TestRunner $t) use ($run, $beforeInsertAudit): void {
        $triggers = $beforeInsertAudit;
        $triggers[0]['row']['audit'] = 'new.missing';
        $t->throws(InvalidArgumentException::class, static fn () => $run($triggers, 'ignore'));
    },
    'dml trigger conflict corpus empty unique columns rejected' => static function (TestRunner $t) use ($target, $side, $insert, $beforeInsertAudit): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerConflictPlan::insertRows($target, $side, $insert, $beforeInsertAudit, [], 'ignore'));
    },
    'dml trigger conflict corpus malformed unique column rejected' => static function (TestRunner $t) use ($target, $side, $insert, $beforeInsertAudit): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerConflictPlan::insertRows($target, $side, $insert, $beforeInsertAudit, ['1bad'], 'ignore'));
    },
];

return $tests;
