<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteTriggerOrderPlan;

$rows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'bytes' => 24, 'value' => 'https://example.test'],
    ['setting_id' => 2, 'key_name' => 'public_url', 'load_policy' => 'yes', 'bytes' => 24, 'value' => 'https://example.test'],
    ['setting_id' => 3, 'key_name' => 'cache_feed', 'load_policy' => 'no', 'bytes' => 12, 'value' => 'feed'],
    ['setting_id' => 4, 'key_name' => 'cache_big', 'load_policy' => 'no', 'bytes' => 110, 'value' => str_repeat('x', 8)],
    ['setting_id' => 5, 'key_name' => 'module_settings', 'load_policy' => 'yes', 'bytes' => 48, 'value' => '{"enabled":true}'],
    ['setting_id' => 6, 'key_name' => 'cache_small', 'load_policy' => 'no', 'bytes' => 7, 'value' => 'tiny'],
];

$triggers = [
    [
        'name' => 'app_settings_before_value_update',
        'timing' => 'before',
        'event' => 'update',
        'table' => 'app_settings',
        'of' => ['value', 'bytes'],
        'values' => [
            'setting_id' => 'old.setting_id',
            'name_before' => 'old.key_name',
            'old_value' => 'old.value',
            'new_value' => 'new.value',
            'old_bytes' => 'old.bytes',
            'new_bytes' => 'new.bytes',
        ],
    ],
    [
        'name' => 'app_settings_before_any_update',
        'timing' => 'before',
        'event' => 'update',
        'table' => 'app_settings',
        'values' => [
            'setting_id' => 'old.setting_id',
            'summary' => 'concat:old.key_name:=>:new.key_name',
        ],
    ],
    [
        'name' => 'app_settings_after_update',
        'timing' => 'after',
        'event' => 'update',
        'table' => 'app_settings',
        'when' => ['left' => 'new.load_policy', 'operator' => '=', 'right' => 'no'],
        'values' => [
            'setting_id' => 'new.setting_id',
            'name_after' => 'new.key_name',
            'load_policy_after' => 'new.load_policy',
            'value_after' => 'new.value',
        ],
    ],
    [
        'name' => 'app_settings_before_delete',
        'timing' => 'before',
        'event' => 'delete',
        'table' => 'app_settings',
        'values' => [
            'setting_id' => 'old.setting_id',
            'deleted_name' => 'old.key_name',
            'deleted_bytes' => 'old.bytes',
        ],
    ],
    [
        'name' => 'app_settings_after_delete_large',
        'timing' => 'after',
        'event' => 'delete',
        'table' => 'app_settings',
        'when' => ['left' => 'old.bytes', 'operator' => '>=', 'right' => 10],
        'values' => [
            'setting_id' => 'old.setting_id',
            'deleted_name_after' => 'old.key_name',
            'phase' => 'removed',
        ],
    ],
];

$updatePlan = static fn (): array => SQLiteUpdateDeleteTriggerOrderPlan::updateRows(
    $rows,
    [
        'value' => static fn (array $row): string => $row['value'] . ':checked',
        'bytes' => static fn (array $row): int => (int) $row['bytes'] + 8,
        'load_policy' => 'no',
    ],
    static fn (array $row): bool => $row['load_policy'] === 'yes',
    $triggers,
    [['column' => 'bytes', 'direction' => 'desc'], ['column' => 'setting_id']],
    2,
);

$deletePlan = static fn (): array => SQLiteUpdateDeleteTriggerOrderPlan::deleteRows(
    $rows,
    static fn (array $row): bool => str_starts_with($row['key_name'], 'cache_'),
    $triggers,
    [['column' => 'bytes', 'direction' => 'desc'], ['column' => 'setting_id']],
);

$tests = [
    'trigger order update delete corpus update visits rows in ordered limit' => static fn (TestRunner $t) => $t->same([5, 1], $updatePlan()['visited']),
    'trigger order update delete corpus update changes count selected rows' => static fn (TestRunner $t) => $t->same(2, $updatePlan()['changes']),
    'trigger order update delete corpus update preserves row count' => static fn (TestRunner $t) => $t->same(6, count($updatePlan()['rows'])),
    'trigger order update delete corpus update first selected row is module settings' => static fn (TestRunner $t) => $t->same('module_settings', $updatePlan()['updated'][0]['key_name']),
    'trigger order update delete corpus update second selected row is base_url' => static fn (TestRunner $t) => $t->same('base_url', $updatePlan()['updated'][1]['key_name']),
    'trigger order update delete corpus update applies callable value assignment' => static fn (TestRunner $t) => $t->same('{"enabled":true}:checked', $updatePlan()['updated'][0]['value']),
    'trigger order update delete corpus update applies callable bytes assignment' => static fn (TestRunner $t) => $t->same(56, $updatePlan()['updated'][0]['bytes']),
    'trigger order update delete corpus update applies literal load_policy assignment' => static fn (TestRunner $t) => $t->same('no', $updatePlan()['updated'][0]['load_policy']),
    'trigger order update delete corpus update before trigger sees old value' => static fn (TestRunner $t) => $t->same('{"enabled":true}', $updatePlan()['audit'][0]['old_value']),
    'trigger order update delete corpus update before trigger sees new value' => static fn (TestRunner $t) => $t->same('{"enabled":true}:checked', $updatePlan()['audit'][0]['new_value']),
    'trigger order update delete corpus update before trigger sees old bytes' => static fn (TestRunner $t) => $t->same(48, $updatePlan()['audit'][0]['old_bytes']),
    'trigger order update delete corpus update before trigger sees new bytes' => static fn (TestRunner $t) => $t->same(56, $updatePlan()['audit'][0]['new_bytes']),
    'trigger order update delete corpus update fires same timing triggers in declaration order' => static fn (TestRunner $t) => $t->same(['app_settings_before_value_update', 'app_settings_before_any_update', 'app_settings_after_update'], array_column(array_slice($updatePlan()['audit'], 0, 3), 'trigger')),
    'trigger order update delete corpus update second row repeats trigger declaration order' => static fn (TestRunner $t) => $t->same(['app_settings_before_value_update', 'app_settings_before_any_update', 'app_settings_after_update'], array_column(array_slice($updatePlan()['audit'], 3, 3), 'trigger')),
    'trigger order update delete corpus update audit contains six rows' => static fn (TestRunner $t) => $t->same(6, count($updatePlan()['audit'])),
    'trigger order update delete corpus update of column trigger fires for bytes change' => static fn (TestRunner $t) => $t->same('app_settings_before_value_update', $updatePlan()['audit'][0]['trigger']),
    'trigger order update delete corpus update unrestricted before trigger fires after update-of trigger' => static fn (TestRunner $t) => $t->same('app_settings_before_any_update', $updatePlan()['audit'][1]['trigger']),
    'trigger order update delete corpus update after trigger sees new load_policy' => static fn (TestRunner $t) => $t->same('no', $updatePlan()['audit'][2]['load_policy_after']),
    'trigger order update delete corpus update after trigger sees new value' => static fn (TestRunner $t) => $t->same('{"enabled":true}:checked', $updatePlan()['audit'][2]['value_after']),
    'trigger order update delete corpus update concat value uses old and new names' => static fn (TestRunner $t) => $t->same('module_settings=>module_settings', $updatePlan()['audit'][1]['summary']),
    'trigger order update delete corpus update result row materializes first mutation' => static fn (TestRunner $t) => $t->same('{"enabled":true}:checked', $updatePlan()['rows'][4]['value']),
    'trigger order update delete corpus update result row materializes second mutation' => static fn (TestRunner $t) => $t->same('https://example.test:checked', $updatePlan()['rows'][0]['value']),
    'trigger order update delete corpus update leaves unvisited load_policy row unchanged' => static fn (TestRunner $t) => $t->same('https://example.test', $updatePlan()['rows'][1]['value']),
    'trigger order update delete corpus update no deleted rows' => static fn (TestRunner $t) => $t->same([], $updatePlan()['deleted']),
    'trigger order update delete corpus update limit zero has no triggers' => static fn (TestRunner $t) => $t->same([], SQLiteUpdateDeleteTriggerOrderPlan::updateRows($rows, ['load_policy' => 'no'], static fn (): bool => true, $triggers, [], 0)['audit']),
    'trigger order update delete corpus update of unrelated column skips update-of trigger' => static function (TestRunner $t) use ($rows, $triggers): void {
        $result = SQLiteUpdateDeleteTriggerOrderPlan::updateRows($rows, ['load_policy' => 'no'], static fn (array $row): bool => $row['setting_id'] === 2, $triggers);
        $t->same(['app_settings_before_any_update', 'app_settings_after_update'], array_column($result['audit'], 'trigger'));
    },
    'trigger order update delete corpus update when false skips after trigger' => static function (TestRunner $t) use ($rows, $triggers): void {
        $result = SQLiteUpdateDeleteTriggerOrderPlan::updateRows($rows, ['value' => 'same', 'bytes' => 1], static fn (array $row): bool => $row['setting_id'] === 2, $triggers);
        $t->same(['app_settings_before_value_update', 'app_settings_before_any_update'], array_column($result['audit'], 'trigger'));
    },
    'trigger order update delete corpus update no match has zero changes' => static fn (TestRunner $t) => $t->same(0, SQLiteUpdateDeleteTriggerOrderPlan::updateRows($rows, ['load_policy' => 'no'], static fn (): bool => false, $triggers)['changes']),
    'trigger order update delete corpus update no match keeps rows unchanged' => static fn (TestRunner $t) => $t->same($rows, SQLiteUpdateDeleteTriggerOrderPlan::updateRows($rows, ['load_policy' => 'no'], static fn (): bool => false, $triggers)['rows']),
    'trigger order update delete corpus update negative limit rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpdateDeleteTriggerOrderPlan::updateRows($rows, ['load_policy' => 'no'], static fn (): bool => true, $triggers, [], -1)),
    'trigger order update delete corpus delete visits cache rows by order' => static fn (TestRunner $t) => $t->same([4, 3, 6], $deletePlan()['visited']),
    'trigger order update delete corpus delete changes count selected rows' => static fn (TestRunner $t) => $t->same(3, $deletePlan()['changes']),
    'trigger order update delete corpus delete leaves non cache rows' => static fn (TestRunner $t) => $t->same(['base_url', 'public_url', 'module_settings'], array_column($deletePlan()['rows'], 'key_name')),
    'trigger order update delete corpus delete first removed row is large cache' => static fn (TestRunner $t) => $t->same('cache_big', $deletePlan()['deleted'][0]['key_name']),
    'trigger order update delete corpus delete before trigger sees old name' => static fn (TestRunner $t) => $t->same('cache_big', $deletePlan()['audit'][0]['deleted_name']),
    'trigger order update delete corpus delete before trigger sees old bytes' => static fn (TestRunner $t) => $t->same(110, $deletePlan()['audit'][0]['deleted_bytes']),
    'trigger order update delete corpus delete after trigger fires after before trigger' => static fn (TestRunner $t) => $t->same(['app_settings_before_delete', 'app_settings_after_delete_large'], array_column(array_slice($deletePlan()['audit'], 0, 2), 'trigger')),
    'trigger order update delete corpus delete after trigger skips small cache by when' => static fn (TestRunner $t) => $t->same(['app_settings_before_delete', 'app_settings_after_delete_large', 'app_settings_before_delete', 'app_settings_after_delete_large', 'app_settings_before_delete'], array_column($deletePlan()['audit'], 'trigger')),
    'trigger order update delete corpus delete audit row count includes skipped after trigger' => static fn (TestRunner $t) => $t->same(5, count($deletePlan()['audit'])),
    'trigger order update delete corpus delete after row records phase' => static fn (TestRunner $t) => $t->same('removed', $deletePlan()['audit'][1]['phase']),
    'trigger order update delete corpus delete returns deleted old rows' => static fn (TestRunner $t) => $t->same([110, 12, 7], array_column($deletePlan()['deleted'], 'bytes')),
    'trigger order update delete corpus delete limit selects first ordered row only' => static fn (TestRunner $t) => $t->same([4], SQLiteUpdateDeleteTriggerOrderPlan::deleteRows($rows, static fn (array $row): bool => str_starts_with($row['key_name'], 'cache_'), $triggers, [['column' => 'bytes', 'direction' => 'desc']], 1)['visited']),
    'trigger order update delete corpus delete limit zero has no changes' => static fn (TestRunner $t) => $t->same(0, SQLiteUpdateDeleteTriggerOrderPlan::deleteRows($rows, static fn (): bool => true, $triggers, [], 0)['changes']),
    'trigger order update delete corpus delete no match keeps rows unchanged' => static fn (TestRunner $t) => $t->same($rows, SQLiteUpdateDeleteTriggerOrderPlan::deleteRows($rows, static fn (): bool => false, $triggers)['rows']),
    'trigger order update delete corpus delete no match has no audit' => static fn (TestRunner $t) => $t->same([], SQLiteUpdateDeleteTriggerOrderPlan::deleteRows($rows, static fn (): bool => false, $triggers)['audit']),
    'trigger order update delete corpus delete new reference rejected' => static function (TestRunner $t) use ($rows, $triggers): void {
        $bad = $triggers;
        $bad[3]['values']['bad'] = 'new.key_name';
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpdateDeleteTriggerOrderPlan::deleteRows($rows, static fn (array $row): bool => $row['setting_id'] === 3, $bad));
    },
    'trigger order update delete corpus malformed trigger target rejected' => static function (TestRunner $t) use ($rows, $triggers): void {
        $bad = $triggers;
        $bad[0]['table'] = 'other';
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpdateDeleteTriggerOrderPlan::updateRows($rows, ['load_policy' => 'no'], static fn (): bool => true, $bad));
    },
    'trigger order update delete corpus malformed trigger timing rejected' => static function (TestRunner $t) use ($rows, $triggers): void {
        $bad = $triggers;
        $bad[0]['timing'] = 'during';
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpdateDeleteTriggerOrderPlan::updateRows($rows, ['load_policy' => 'no'], static fn (): bool => true, $bad));
    },
    'trigger order update delete corpus malformed trigger event rejected' => static function (TestRunner $t) use ($rows, $triggers): void {
        $bad = $triggers;
        $bad[0]['event'] = 'insert';
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpdateDeleteTriggerOrderPlan::updateRows($rows, ['load_policy' => 'no'], static fn (): bool => true, $bad));
    },
    'trigger order update delete corpus malformed assignment column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpdateDeleteTriggerOrderPlan::updateRows($rows, ['1bad' => 'no'], static fn (): bool => true, $triggers)),
    'trigger order update delete corpus malformed update of column rejected' => static function (TestRunner $t) use ($rows, $triggers): void {
        $bad = $triggers;
        $bad[0]['of'] = ['1bad'];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpdateDeleteTriggerOrderPlan::updateRows($rows, ['load_policy' => 'no'], static fn (): bool => true, $bad));
    },
    'trigger order update delete corpus malformed order direction rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpdateDeleteTriggerOrderPlan::deleteRows($rows, static fn (): bool => true, $triggers, [['column' => 'bytes', 'direction' => 'sideways']])),
];

return $tests;
