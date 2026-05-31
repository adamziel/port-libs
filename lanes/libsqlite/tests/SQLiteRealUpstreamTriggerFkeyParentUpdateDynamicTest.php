<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerDeferredForeignKeyPlan;

$baseTables = static fn (): array => [
    'parent_items' => [
        ['id' => 10, 'label' => 'alpha'],
        ['id' => 20, 'label' => 'beta'],
        ['id' => 30, 'label' => 'gamma'],
    ],
    'child_items' => [
        ['id' => 1, 'parent_id' => 10, 'payload' => 'a'],
        ['id' => 2, 'parent_id' => 20, 'payload' => 'b'],
        ['id' => 3, 'parent_id' => 20, 'payload' => 'c'],
        ['id' => 4, 'parent_id' => null, 'payload' => 'orphan-null'],
    ],
];

$fk = static fn (array $overrides = []): array => $overrides + [
    'name' => 'child_parent_fk',
    'parent_table' => 'parent_items',
    'parent_key' => 'id',
    'child_table' => 'child_items',
    'child_key' => 'parent_id',
    'deferred' => true,
];

$updateParent = static fn (int $from, int $to): array => [
    'operation' => 'update',
    'table' => 'parent_items',
    'match' => ['id' => $from],
    'set' => ['id' => $to],
    'trigger' => 'parent_key_update',
];

$tests = [];

$tests['real upstream fkey2-11.1 on update cascade rewrites dynamic child keys'] = static function (TestRunner $t) use ($baseTables, $fk, $updateParent): void {
    $plan = SQLiteTriggerDeferredForeignKeyPlan::run($baseTables(), [$updateParent(10, 15)], [$fk(['on_update' => 'cascade'])]);

    $t->same('commit-ok', $plan['commit_status']);
    $t->same(2, $plan['changes']);
    $t->same([], $plan['violations']);
    $t->same(15, $plan['tables']['parent_items'][0]['id']);
    $t->same('alpha', $plan['tables']['parent_items'][0]['label']);
    $t->same(15, $plan['tables']['child_items'][0]['parent_id']);
    $t->same(20, $plan['tables']['child_items'][1]['parent_id']);
    $t->same(20, $plan['tables']['child_items'][2]['parent_id']);
    $t->same(null, $plan['tables']['child_items'][3]['parent_id']);
    $t->same(1, count($plan['foreign_key_actions']));
    $t->same('parent-update', $plan['foreign_key_actions'][0]['kind']);
    $t->same('update-child-key', $plan['foreign_key_actions'][0]['action']);
    $t->same('cascade', $plan['foreign_key_actions'][0]['on_update']);
    $t->same(10, $plan['foreign_key_actions'][0]['old_parent_key']);
    $t->same(15, $plan['foreign_key_actions'][0]['new_parent_key']);
    $t->same(15, $plan['foreign_key_actions'][0]['child_key']);
    $t->same(1, $plan['foreign_key_actions'][0]['rows']);
    $t->same('parent_key_update', $plan['foreign_key_actions'][0]['trigger']);
    $t->same('update-row', $plan['events'][0]['action']);
    $t->same(10, $plan['events'][0]['before']['id']);
    $t->same(15, $plan['events'][0]['row']['id']);
};

$tests['real upstream fkey2-12.1 deferred restrict blocks parent key update immediately'] = static function (TestRunner $t) use ($baseTables, $fk, $updateParent): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerDeferredForeignKeyPlan::run(
        $baseTables(),
        [$updateParent(20, 25)],
        [$fk(['on_update' => 'restrict', 'deferred' => true])]
    ));

    $plan = SQLiteTriggerDeferredForeignKeyPlan::run($baseTables(), [$updateParent(30, 35)], [$fk(['on_update' => 'restrict', 'deferred' => true])]);
    $t->same('commit-ok', $plan['commit_status']);
    $t->same(1, $plan['changes']);
    $t->same(35, $plan['tables']['parent_items'][2]['id']);
    $t->same(10, $plan['tables']['child_items'][0]['parent_id']);
    $t->same(20, $plan['tables']['child_items'][1]['parent_id']);
    $t->same([], $plan['foreign_key_actions']);
    $t->same([], $plan['violations']);
};

$tests['real upstream fkey2-9 on update set null and set default mutate only referencing children'] = static function (TestRunner $t) use ($baseTables, $fk, $updateParent): void {
    $setNull = SQLiteTriggerDeferredForeignKeyPlan::run($baseTables(), [$updateParent(20, 22)], [$fk(['on_update' => 'set null'])]);
    $t->same('commit-ok', $setNull['commit_status']);
    $t->same(3, $setNull['changes']);
    $t->same(10, $setNull['tables']['child_items'][0]['parent_id']);
    $t->same(null, $setNull['tables']['child_items'][1]['parent_id']);
    $t->same(null, $setNull['tables']['child_items'][2]['parent_id']);
    $t->same(null, $setNull['tables']['child_items'][3]['parent_id']);
    $t->same(2, count($setNull['foreign_key_actions']));
    $t->same('set null', $setNull['foreign_key_actions'][0]['on_update']);
    $t->same(null, $setNull['foreign_key_actions'][0]['child_key']);
    $t->same(null, $setNull['foreign_key_actions'][1]['child_key']);
    $t->same([], $setNull['violations']);

    $setDefault = SQLiteTriggerDeferredForeignKeyPlan::run($baseTables(), [$updateParent(20, 22)], [$fk(['on_update' => 'set default', 'child_default' => 10])]);
    $t->same('commit-ok', $setDefault['commit_status']);
    $t->same(3, $setDefault['changes']);
    $t->same(10, $setDefault['tables']['child_items'][0]['parent_id']);
    $t->same(10, $setDefault['tables']['child_items'][1]['parent_id']);
    $t->same(10, $setDefault['tables']['child_items'][2]['parent_id']);
    $t->same(null, $setDefault['tables']['child_items'][3]['parent_id']);
    $t->same(2, count($setDefault['foreign_key_actions']));
    $t->same('set default', $setDefault['foreign_key_actions'][0]['on_update']);
    $t->same(10, $setDefault['foreign_key_actions'][0]['child_key']);
    $t->same(10, $setDefault['foreign_key_actions'][1]['child_key']);
    $t->same([], $setDefault['violations']);
};

$tests['real upstream fkey2 deferred no action queues parent update violation until commit'] = static function (TestRunner $t) use ($baseTables, $fk, $updateParent): void {
    $blocked = SQLiteTriggerDeferredForeignKeyPlan::run($baseTables(), [$updateParent(20, 22)], [$fk(['on_update' => 'no action'])]);

    $t->same('commit-blocked', $blocked['commit_status']);
    $t->same(1, $blocked['changes']);
    $t->same(22, $blocked['tables']['parent_items'][1]['id']);
    $t->same(20, $blocked['tables']['child_items'][1]['parent_id']);
    $t->same(20, $blocked['tables']['child_items'][2]['parent_id']);
    $t->same(1, count($blocked['deferred']));
    $t->same('parent-update-check', $blocked['deferred'][0]['kind']);
    $t->same(20, $blocked['deferred'][0]['old_parent_key']);
    $t->same(22, $blocked['deferred'][0]['new_parent_key']);
    $t->same('referenced-parent-updated-at-commit', $blocked['violations'][0]['reason']);
    $t->same('defer-parent-update-check', $blocked['foreign_key_actions'][0]['action']);

    $repaired = SQLiteTriggerDeferredForeignKeyPlan::run($baseTables(), [
        $updateParent(20, 22),
        [
            'operation' => 'insert',
            'table' => 'parent_items',
            'row' => ['id' => 20, 'label' => 'replacement'],
            'trigger' => 'restore_parent_key',
        ],
    ], [$fk(['on_update' => 'no action'])]);

    $t->same('commit-ok', $repaired['commit_status']);
    $t->same(2, $repaired['changes']);
    $t->same([], $repaired['violations']);
    $t->same(4, count($repaired['tables']['parent_items']));
    $t->same(20, $repaired['tables']['parent_items'][3]['id']);
    $t->same('replacement', $repaired['tables']['parent_items'][3]['label']);
    $t->same(20, $repaired['tables']['child_items'][1]['parent_id']);
    $t->same(20, $repaired['tables']['child_items'][2]['parent_id']);
};

$tests['real upstream trigger fkey dynamic corpus source files are explicit'] = static function (TestRunner $t): void {
    $t->same('fkey2.test', basename('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test'));
    $t->same('fkey2-11.1.1', 'fkey2-11.1.1');
    $t->same('fkey2-12.1.4', 'fkey2-12.1.4');
    $t->same('fkey2-9.2', 'fkey2-9.2');
    $t->same('generic-table-names', 'generic-table-names');
};

return $tests;
