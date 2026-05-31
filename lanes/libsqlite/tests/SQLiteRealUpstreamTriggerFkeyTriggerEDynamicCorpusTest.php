<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteForeignKeyDeferredCascadeTriggerCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteTriggerDynamicVariablePlan;

$createDefinitions = [
    ['name' => 'tr_when_qmark', 'sql' => 'AFTER INSERT ON settings WHEN new.key_name = ? BEGIN SELECT 1; END;'],
    ['name' => 'tr_select_qmark', 'sql' => 'BEFORE DELETE ON settings BEGIN SELECT ?; END;'],
    ['name' => 'tr_nested_select_qmark', 'sql' => 'BEFORE DELETE ON settings BEGIN SELECT * FROM (SELECT * FROM (SELECT ?)); END;'],
    ['name' => 'tr_group_qmark', 'sql' => 'BEFORE DELETE ON settings BEGIN SELECT * FROM audit GROUP BY ?; END;'],
    ['name' => 'tr_limit_qmark', 'sql' => 'BEFORE DELETE ON settings BEGIN SELECT * FROM audit LIMIT ?; END;'],
    ['name' => 'tr_order_qmark', 'sql' => 'BEFORE DELETE ON settings BEGIN SELECT * FROM audit ORDER BY ?; END;'],
    ['name' => 'tr_update_set_qmark', 'sql' => 'BEFORE UPDATE ON settings BEGIN UPDATE audit SET c = ?; END;'],
    ['name' => 'tr_update_where_qmark', 'sql' => 'BEFORE UPDATE ON settings BEGIN UPDATE audit SET c = 1 WHERE d = ?; END;'],
    ['name' => 'tr_pragma_qmark', 'sql' => 'AFTER INSERT ON settings BEGIN SELECT * FROM pragma_stats(?); END;'],
    ['name' => 'tr_window_dollar', 'sql' => 'BEFORE INSERT ON settings BEGIN INSERT INTO settings SELECT max(value_text) OVER(ORDER BY $1) FROM settings; END;'],
    ['name' => 'tr_named_dollar', 'sql' => 'AFTER INSERT ON settings BEGIN SELECT $setting_id; END;'],
    ['name' => 'tr_named_colon', 'sql' => 'AFTER INSERT ON settings BEGIN SELECT :setting_id; END;'],
    ['name' => 'tr_named_at', 'sql' => 'AFTER INSERT ON settings BEGIN SELECT @setting_id; END;'],
    ['name' => 'tr_clean', 'sql' => 'AFTER INSERT ON settings BEGIN SELECT 1; END;'],
    ['name' => 'tr_clean_temp', 'temp' => true, 'sql' => 'AFTER INSERT ON settings BEGIN SELECT new.setting_id; END;'],
];

$createPlan = static fn (): array => SQLiteTriggerDynamicVariablePlan::createDefinitions($createDefinitions);

$storedReplay = static fn (array $events = [['table' => 'settings', 'row' => ['setting_id' => 1, 'value_text' => 'alpha']]]): array => SQLiteTriggerDynamicVariablePlan::replayStoredSchema(
    [
        [
            'name' => 'stored_insert',
            'table' => 'settings',
            'sql' => 'CREATE TRIGGER stored_insert AFTER INSERT ON settings BEGIN INSERT INTO audit VALUES(?1, ?2); END',
        ],
        [
            'name' => 'stored_update',
            'table' => 'events',
            'sql' => 'CREATE TRIGGER stored_update AFTER INSERT ON events WHEN ?1 IS NULL BEGIN UPDATE audit SET c1=c2 WHERE c1 IS ?2; END',
        ],
    ],
    $events,
    [
        'settings' => [],
        'events' => [],
        'audit' => [
            ['c1' => 'x', 'c2' => 'y'],
            ['c1' => null, 'c2' => 'z'],
        ],
    ],
);

$parents = [
    ['setting_id' => 1, 'key_name' => 'alpha'],
    ['setting_id' => 2, 'key_name' => 'beta'],
    ['setting_id' => 3, 'key_name' => 'gamma'],
];
$children = [
    ['entry_id' => 10, 'setting_id' => 1, 'value_text' => 'a-one'],
    ['entry_id' => 11, 'setting_id' => 1, 'value_text' => 'a-two'],
    ['entry_id' => 12, 'setting_id' => 2, 'value_text' => 'b-one'],
    ['entry_id' => 13, 'setting_id' => null, 'value_text' => 'loose'],
    ['entry_id' => 14, 'setting_id' => 3, 'value_text' => 'g-one'],
];
$details = [
    ['detail_id' => 100, 'entry_id' => 10, 'label' => 'd10a'],
    ['detail_id' => 101, 'entry_id' => 10, 'label' => 'd10b'],
    ['detail_id' => 102, 'entry_id' => 11, 'label' => 'd11'],
    ['detail_id' => 103, 'entry_id' => 12, 'label' => 'd12'],
    ['detail_id' => 104, 'entry_id' => null, 'label' => 'loose'],
    ['detail_id' => 105, 'entry_id' => 14, 'label' => 'd14'],
];
$fk = ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_delete' => 'CASCADE', 'deferred' => true];
$detailFk = ['parent_key' => 'entry_id', 'child_key' => 'entry_id', 'on_delete' => 'CASCADE'];
$beforeAudit = [[
    'timing' => 'before',
    'event' => 'delete',
    'action' => 'insert-audit',
    'audit' => ['phase' => 'before', 'entry_id' => 'old.entry_id', 'remaining_detail' => 'grandchild_count'],
]];
$afterAudit = [[
    'timing' => 'after',
    'event' => 'delete',
    'action' => 'insert-audit',
    'audit' => ['phase' => 'after', 'entry_id' => 'old.entry_id', 'remaining_detail' => 'grandchild_count'],
]];
$moveDetailsBeforeCascade = [[
    'timing' => 'before',
    'event' => 'delete',
    'action' => 'update-grandchild-key',
    'grandchild_key' => 'old.entry_id',
    'set_grandchild_key' => 12,
]];

$cascade = static fn (
    array $deleteKeys = [['setting_id' => 1]],
    array $triggers = [],
    ?array $grandchildFk = null,
    array $ops = [],
    ?array $foreignKey = null,
): array => SQLiteForeignKeyDeferredCascadeTriggerCurrentSourcePlan::deleteParents(
    $parents,
    $children,
    $details,
    $deleteKeys,
    $foreignKey ?? $fk,
    $triggers,
    $grandchildFk,
    $ops,
);

$updateCascade = static fn (
    array $updates = [['setting_id' => 1, 'new_setting_id' => 101]],
    array $triggers = [],
    ?array $grandchildFk = null,
    array $ops = [],
    ?array $foreignKey = null,
): array => SQLiteForeignKeyDeferredCascadeTriggerCurrentSourcePlan::updateParents(
    $parents,
    $children,
    $details,
    $updates,
    $foreignKey ?? ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'CASCADE', 'deferred' => true],
    $triggers,
    $grandchildFk,
    $ops,
);

$tests = [
    'upstream triggerE 1.1 rejects qmark in WHEN clause' => static fn (TestRunner $t) => $t->same('tr_when_qmark', $createPlan()['rejected'][0]['name']),
    'upstream triggerE 1.1 reports trigger variable error' => static fn (TestRunner $t) => $t->same('trigger cannot use variables', $createPlan()['rejected'][0]['reason']),
    'upstream triggerE 1.2 rejects select qmark in trigger body' => static fn (TestRunner $t) => $t->same('tr_select_qmark', $createPlan()['rejected'][1]['name']),
    'upstream triggerE 1.3 rejects nested select variable' => static fn (TestRunner $t) => $t->same('tr_nested_select_qmark', $createPlan()['rejected'][2]['name']),
    'upstream triggerE 1.5 rejects group by variable' => static fn (TestRunner $t) => $t->same('tr_group_qmark', $createPlan()['rejected'][3]['name']),
    'upstream triggerE 1.6 rejects limit variable' => static fn (TestRunner $t) => $t->same('tr_limit_qmark', $createPlan()['rejected'][4]['name']),
    'upstream triggerE 1.7 rejects order by variable' => static fn (TestRunner $t) => $t->same('tr_order_qmark', $createPlan()['rejected'][5]['name']),
    'upstream triggerE 1.8 rejects update set variable' => static fn (TestRunner $t) => $t->same('tr_update_set_qmark', $createPlan()['rejected'][6]['name']),
    'upstream triggerE 1.9 rejects update where variable' => static fn (TestRunner $t) => $t->same('tr_update_where_qmark', $createPlan()['rejected'][7]['name']),
    'upstream triggerE 1.10 rejects pragma variable' => static fn (TestRunner $t) => $t->same('tr_pragma_qmark', $createPlan()['rejected'][8]['name']),
    'upstream triggerE 1.11 rejects dollar window order variable' => static fn (TestRunner $t) => $t->same('tr_window_dollar', $createPlan()['rejected'][9]['name']),
    'upstream triggerE rejects named dollar variable' => static fn (TestRunner $t) => $t->same('tr_named_dollar', $createPlan()['rejected'][10]['name']),
    'upstream triggerE rejects named colon variable' => static fn (TestRunner $t) => $t->same('tr_named_colon', $createPlan()['rejected'][11]['name']),
    'upstream triggerE rejects named at variable' => static fn (TestRunner $t) => $t->same('tr_named_at', $createPlan()['rejected'][12]['name']),
    'upstream triggerE create accepts clean regular trigger' => static fn (TestRunner $t) => $t->same('tr_clean', $createPlan()['accepted'][0]['name']),
    'upstream triggerE create accepts clean temp trigger' => static fn (TestRunner $t) => $t->same(true, $createPlan()['accepted'][1]['temp']),
    'upstream triggerE create reports thirteen rejected variable definitions' => static fn (TestRunner $t) => $t->same(13, count($createPlan()['rejected'])),
    'upstream triggerE create reports two accepted definitions' => static fn (TestRunner $t) => $t->same(2, count($createPlan()['accepted'])),
    'upstream triggerE stored schema insert converts first variable to null' => static fn (TestRunner $t) => $t->same(null, $storedReplay()['tables']['audit'][2]['c1']),
    'upstream triggerE stored schema insert converts second variable to null' => static fn (TestRunner $t) => $t->same(null, $storedReplay()['tables']['audit'][2]['c2']),
    'upstream triggerE stored schema marks insert variables converted' => static fn (TestRunner $t) => $t->same(true, $storedReplay()['trigger_effects'][0]['variables_as_null']),
    'upstream triggerE stored schema update when null fires' => static fn (TestRunner $t) => $t->same('update-null-match', $storedReplay([['table' => 'events', 'row' => ['event_id' => 1]]])['trigger_effects'][0]['action']),
    'upstream triggerE stored schema update rewrites null-matched row' => static fn (TestRunner $t) => $t->same('z', $storedReplay([['table' => 'events', 'row' => ['event_id' => 1]]])['tables']['audit'][1]['c1']),
    'upstream triggerE stored schema leaves non-null row untouched' => static fn (TestRunner $t) => $t->same('x', $storedReplay([['table' => 'events', 'row' => ['event_id' => 1]]])['tables']['audit'][0]['c1']),
    'upstream triggerE stored schema ignores unrelated event table' => static fn (TestRunner $t) => $t->same([], $storedReplay([['table' => 'other_events', 'row' => ['event_id' => 1]]])['trigger_effects']),
    'upstream triggerE stored schema records dependency' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-triggerE-stored-schema-variables-as-null', $storedReplay()['dependencies'], true)),
    'upstream fkey8 dynamic deferred delete removes parent at statement time' => static fn (TestRunner $t) => $t->same([2, 3], array_column($cascade()['after_statement']['parent'], 'setting_id')),
    'upstream fkey8 dynamic deferred delete keeps child before commit' => static fn (TestRunner $t) => $t->same([10, 11, 12, 13, 14], array_column($cascade()['before_commit']['child'], 'entry_id')),
    'upstream fkey8 dynamic deferred delete cascades children at commit' => static fn (TestRunner $t) => $t->same([12, 13, 14], array_column($cascade()['after_commit']['child'], 'entry_id')),
    'upstream fkey8 dynamic deferred delete cascades grandchildren' => static fn (TestRunner $t) => $t->same([103, 104, 105], array_column($cascade([['setting_id' => 1]], [], $detailFk)['after_commit']['grandchild'], 'detail_id')),
    'upstream fkey8 dynamic cascade action order includes grandchildren' => static fn (TestRunner $t) => $t->same(['deferred-cascade-delete-child', 'deferred-cascade-delete-grandchild', 'deferred-cascade-delete-grandchild', 'deferred-cascade-delete-child', 'deferred-cascade-delete-grandchild'], array_column($cascade([['setting_id' => 1]], [], $detailFk)['cascade_actions'], 'action')),
    'upstream fkey8 dynamic before trigger sees all current grandchildren' => static fn (TestRunner $t) => $t->same(6, $cascade([['setting_id' => 1]], $beforeAudit, $detailFk)['audit'][0]['remaining_detail']),
    'upstream fkey8 dynamic after trigger sees post-cascade counts' => static fn (TestRunner $t) => $t->same([4, 3], array_column($cascade([['setting_id' => 1]], $afterAudit, $detailFk)['audit'], 'remaining_detail')),
    'upstream fkey8 dynamic before trigger can move grandchildren out of cascade' => static fn (TestRunner $t) => $t->same([100, 101, 102, 103, 104, 105], array_column($cascade([['setting_id' => 1]], $moveDetailsBeforeCascade, $detailFk)['after_commit']['grandchild'], 'detail_id')),
    'upstream fkey8 dynamic before move suppresses grandchild cascade actions' => static fn (TestRunner $t) => $t->same(['deferred-cascade-delete-child', 'deferred-cascade-delete-child'], array_column($cascade([['setting_id' => 1]], $moveDetailsBeforeCascade, $detailFk)['cascade_actions'], 'action')),
    'upstream fkey8 dynamic inserted child before commit is cascaded' => static fn (TestRunner $t) => $t->same([12, 13, 14], array_column($cascade([['setting_id' => 1]], [], null, [['operation' => 'insert', 'table' => 'child', 'row' => ['entry_id' => 20, 'setting_id' => 1, 'value_text' => 'late']]])['after_commit']['child'], 'entry_id')),
    'upstream fkey8 dynamic relinked child into deleted parent is cascaded' => static fn (TestRunner $t) => $t->same([13, 14], array_column($cascade([['setting_id' => 1]], [], null, [['operation' => 'update', 'table' => 'child', 'match' => ['entry_id' => 12], 'set' => ['setting_id' => 1]]])['after_commit']['child'], 'entry_id')),
    'upstream fkey8 dynamic detached child escapes cascade' => static fn (TestRunner $t) => $t->same([10, 12, 13, 14], array_column($cascade([['setting_id' => 1]], [], null, [['operation' => 'update', 'table' => 'child', 'match' => ['entry_id' => 10], 'set' => ['setting_id' => 2]]])['after_commit']['child'], 'entry_id')),
    'upstream fkey8 dynamic deleted child reduces cascade work' => static fn (TestRunner $t) => $t->same([12, 13, 14], array_column($cascade([['setting_id' => 1]], [], null, [['operation' => 'delete', 'table' => 'child', 'match' => ['entry_id' => 11]]])['after_commit']['child'], 'entry_id')),
    'upstream fkey8 dynamic inserted grandchild is visible to before trigger' => static fn (TestRunner $t) => $t->same(7, $cascade([['setting_id' => 1]], $beforeAudit, $detailFk, [['operation' => 'insert', 'table' => 'grandchild', 'row' => ['detail_id' => 106, 'entry_id' => 10, 'label' => 'late']]])['audit'][0]['remaining_detail']),
    'upstream fkey8 dynamic updated grandchild can escape cascade' => static fn (TestRunner $t) => $t->same([102, 103, 104, 105], array_column($cascade([['setting_id' => 1]], [], $detailFk, [['operation' => 'update', 'table' => 'grandchild', 'match' => ['detail_id' => 102], 'set' => ['entry_id' => 12]]])['after_commit']['grandchild'], 'detail_id')),
    'upstream fkey8 dynamic no action reports deferred violation' => static function (TestRunner $t) use ($fk, $cascade): void {
        $noAction = $fk;
        $noAction['on_delete'] = 'NO ACTION';
        $t->same('referenced-parent-deleted-at-deferred-commit', $cascade([['setting_id' => 1]], [], null, [], $noAction)['violations'][0]['reason']);
    },
    'upstream fkey8 dynamic restrict blocks before deferred commit' => static function (TestRunner $t) use ($fk, $cascade): void {
        $restrict = $fk;
        $restrict['on_delete'] = 'RESTRICT';
        $t->throws(InvalidArgumentException::class, static fn () => $cascade([['setting_id' => 1]], [], null, [], $restrict));
    },
    'upstream fkey8 dynamic multiple parent deletes keep loose rows' => static fn (TestRunner $t) => $t->same([12, 13], array_column($cascade([['setting_id' => 1], ['setting_id' => 3]])['after_commit']['child'], 'entry_id')),
    'upstream fkey8 dynamic dependencies name current source' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-current-source-before-deferred-commit', $cascade()['dependencies'], true)),
    'upstream fkey8 dynamic update cascade changes parent after statement' => static fn (TestRunner $t) => $t->same([101, 2, 3], array_column($updateCascade()['after_statement']['parent'], 'setting_id')),
    'upstream fkey8 dynamic update cascade keeps child until commit' => static fn (TestRunner $t) => $t->same([1, 1, 2, null, 3], array_column($updateCascade()['before_commit']['child'], 'setting_id')),
    'upstream fkey8 dynamic update cascade rewrites children at commit' => static fn (TestRunner $t) => $t->same([101, 101, 2, null, 3], array_column($updateCascade()['after_commit']['child'], 'setting_id')),
    'upstream fkey8 dynamic update cascade records child actions' => static fn (TestRunner $t) => $t->same(['deferred-cascade-update-child', 'deferred-cascade-update-child'], array_column($updateCascade()['cascade_actions'], 'action')),
    'upstream fkey8 dynamic update cascade relinked child joins update set' => static fn (TestRunner $t) => $t->same([101, 101, 101, null, 3], array_column($updateCascade([['setting_id' => 1, 'new_setting_id' => 101]], [], null, [['operation' => 'update', 'table' => 'child', 'match' => ['entry_id' => 12], 'set' => ['setting_id' => 1]]])['after_commit']['child'], 'setting_id')),
    'upstream fkey8 dynamic update cascade restrict blocks referenced parent update' => static function (TestRunner $t) use ($updateCascade): void {
        $restrict = ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'RESTRICT', 'deferred' => true];
        $t->throws(InvalidArgumentException::class, static fn () => $updateCascade([['setting_id' => 1, 'new_setting_id' => 101]], [], null, [], $restrict));
    },
];

return $tests;
