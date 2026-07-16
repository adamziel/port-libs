<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan;

$parents = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'public_url', 'key_value' => 'https://example.test/public_url', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'site_title', 'key_value' => 'Example', 'load_policy' => 'yes'],
];
$children = [
    ['meta_id' => 11, 'setting_id' => 1, 'meta_key' => '_imported'],
    ['meta_id' => 12, 'setting_id' => 2, 'meta_key' => '_imported'],
    ['meta_id' => 13, 'setting_id' => 3, 'meta_key' => '_imported'],
];
$returning = [
    ['expr' => 'old.setting_id', 'as' => 'deleted_id'],
    'key_name',
    'load_policy',
    static fn (array $old, string $event): string => $event . ':' . $old['key_name'],
];
$page = static fn (string $label): string => str_pad($label, 512, '.', STR_PAD_RIGHT);
$baseStatement = [
    'savepoint' => 'app_settings_delete',
    'where' => static fn (array $row): bool => in_array($row['key_name'], ['base_url', 'public_url'], true),
    'returning' => $returning,
    'before_triggers' => [
        ['name' => 'app_settings_bd_ignore_site_title', 'action' => 'ignore', 'when' => static fn (array $old): bool => $old['key_name'] === 'site_title'],
    ],
    'after_triggers' => [
        ['name' => 'app_settings_ad_audit', 'action' => 'log'],
    ],
    'page_images' => [2 => $page('settings-before'), 5 => $page('meta-before')],
    'dirty_pages' => [2 => $page('settings-dirty'), 5 => $page('meta-dirty'), 8 => $page('overflow-dirty')],
    'wal_start_frame' => 44,
    'wal_frames' => [
        ['frame_index' => 45, 'page_number' => 2],
        ['frame_index' => 46, 'page_number' => 5, 'commit_frame' => true],
        ['frame_index' => 47, 'page_number' => 8],
    ],
];
$cascadeFk = ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_delete' => 'cascade', 'deferred' => false];
$setNullFk = ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_delete' => 'set null', 'deferred' => false];
$restrictFk = ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_delete' => 'restrict', 'deferred' => false];
$deferredNoActionFk = ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_delete' => 'no action', 'deferred' => true];

$cascadePlan = static fn (): array => SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan::execute($parents, $children, $cascadeFk, $baseStatement);
$setNullPlan = static fn (): array => SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan::execute($parents, $children, $setNullFk, $baseStatement);
$restrictPlan = static fn (): array => SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan::execute($parents, $children, $restrictFk, $baseStatement);
$deferredBlockedPlan = static fn (): array => SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan::execute($parents, $children, $deferredNoActionFk, $baseStatement);
$deferredRollbackPlan = static fn (): array => SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan::execute($parents, $children, $deferredNoActionFk, $baseStatement + ['rollback_on_deferred_violation' => true]);
$triggerRollbackPlan = static fn (): array => SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan::execute(
    $parents,
    $children,
    $cascadeFk,
    array_replace($baseStatement, ['after_triggers' => [['name' => 'app_settings_ad_abort', 'action' => 'rollback']]]),
);
$ignorePlan = static fn (): array => SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan::execute(
    $parents,
    $children,
    $cascadeFk,
    array_replace($baseStatement, [
        'where' => static fn (array $row): bool => $row['key_name'] === 'site_title',
    ]),
);

$tests = [
    'trigger delete returning fk savepoint next120 cascade status' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same('commit-ok', $cascadePlan()['status']);
    },
    'trigger delete returning fk savepoint next120 savepoint name' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same('app_settings_delete', $cascadePlan()['savepoint']);
    },
    'trigger delete returning fk savepoint next120 cascade current rowids' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same([3], $cascadePlan()['current_rowids']);
    },
    'trigger delete returning fk savepoint next120 cascade next rowids' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same([3], $cascadePlan()['next_rowids']);
    },
    'trigger delete returning fk savepoint next120 cascade deleted rowids' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same([1, 2], $cascadePlan()['deleted_rowids']);
    },
    'trigger delete returning fk savepoint next120 cascade child count keeps undeleted child' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same(1, count($cascadePlan()['current_child']));
    },
    'trigger delete returning fk savepoint next120 cascade child keys' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same([3], array_column($cascadePlan()['current_child'], 'setting_id'));
    },
    'trigger delete returning fk savepoint next120 cascade fk actions count' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same(2, count($cascadePlan()['foreign_key_actions']));
    },
    'trigger delete returning fk savepoint next120 cascade action names' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same(['cascade', 'cascade'], array_column($cascadePlan()['foreign_key_actions'], 'action'));
    },
    'trigger delete returning fk savepoint next120 cascade no violations' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same([], $cascadePlan()['foreign_key_violations']);
    },
    'trigger delete returning fk savepoint next120 returning count' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same(2, count($cascadePlan()['current_returning_rows']));
    },
    'trigger delete returning fk savepoint next120 first returning id' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same(1, $cascadePlan()['current_returning_rows'][0]['deleted_id']);
    },
    'trigger delete returning fk savepoint next120 second returning id' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same(2, $cascadePlan()['current_returning_rows'][1]['deleted_id']);
    },
    'trigger delete returning fk savepoint next120 returning setting names' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same(['base_url', 'public_url'], array_column($cascadePlan()['current_returning_rows'], 'key_name'));
    },
    'trigger delete returning fk savepoint next120 returning callable event' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same(['delete:base_url', 'delete:public_url'], array_column($cascadePlan()['current_returning_rows'], 'expr3'));
    },
    'trigger delete returning fk savepoint next120 next returning visible on commit' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same($cascadePlan()['current_returning_rows'], $cascadePlan()['next_returning_rows']);
    },
    'trigger delete returning fk savepoint next120 trigger effects count' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same(2, count($cascadePlan()['trigger_effects']));
    },
    'trigger delete returning fk savepoint next120 trigger effect action' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same(['log', 'log'], array_column($cascadePlan()['trigger_effects'], 'action'));
    },
    'trigger delete returning fk savepoint next120 trigger effect phase' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same(['after-delete', 'after-delete'], array_column($cascadePlan()['trigger_effects'], 'phase'));
    },
    'trigger delete returning fk savepoint next120 changes count' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same(6, $cascadePlan()['current_changes']);
    },
    'trigger delete returning fk savepoint next120 boundary release' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same('release-savepoint', $cascadePlan()['current_next_boundary']);
    },
    'trigger delete returning fk savepoint next120 dependency marker' => static function (TestRunner $t) use ($cascadePlan): void {
        $t->same(true, in_array('sqlite-foreign-key-on-delete-actions', $cascadePlan()['dependencies'], true));
    },
    'trigger delete returning fk savepoint next120 set null status' => static function (TestRunner $t) use ($setNullPlan): void {
        $t->same('commit-ok', $setNullPlan()['status']);
    },
    'trigger delete returning fk savepoint next120 set null child keys' => static function (TestRunner $t) use ($setNullPlan): void {
        $t->same([null, null, 3], array_column($setNullPlan()['current_child'], 'setting_id'));
    },
    'trigger delete returning fk savepoint next120 set null actions' => static function (TestRunner $t) use ($setNullPlan): void {
        $t->same(['set null', 'set null'], array_column($setNullPlan()['foreign_key_actions'], 'action'));
    },
    'trigger delete returning fk savepoint next120 set null no rollback pages' => static function (TestRunner $t) use ($setNullPlan): void {
        $t->same([], $setNullPlan()['rollback_page_numbers']);
    },
    'trigger delete returning fk savepoint next120 restrict rolls back' => static function (TestRunner $t) use ($restrictPlan): void {
        $t->same('rolled-back', $restrictPlan()['status']);
    },
    'trigger delete returning fk savepoint next120 restrict reason' => static function (TestRunner $t) use ($restrictPlan): void {
        $t->same('restrict-foreign-key', $restrictPlan()['rollback_reason']);
    },
    'trigger delete returning fk savepoint next120 restrict next rows restored' => static function (TestRunner $t) use ($restrictPlan): void {
        $t->same([1, 2, 3], $restrictPlan()['next_rowids']);
    },
    'trigger delete returning fk savepoint next120 restrict next children restored' => static function (TestRunner $t) use ($restrictPlan): void {
        $t->same([1, 2, 3], array_column($restrictPlan()['next_child'], 'setting_id'));
    },
    'trigger delete returning fk savepoint next120 restrict returning suppressed' => static function (TestRunner $t) use ($restrictPlan): void {
        $t->same([], $restrictPlan()['next_returning_rows']);
    },
    'trigger delete returning fk savepoint next120 restrict yield suppressed flag' => static function (TestRunner $t) use ($restrictPlan): void {
        $t->same(true, $restrictPlan()['yield_suppressed_by_rollback']);
    },
    'trigger delete returning fk savepoint next120 restrict rollback pages' => static function (TestRunner $t) use ($restrictPlan): void {
        $t->same([2, 5, 8], $restrictPlan()['rollback_page_numbers']);
    },
    'trigger delete returning fk savepoint next120 restrict restored page keys' => static function (TestRunner $t) use ($restrictPlan): void {
        $t->same([2, 5], array_keys($restrictPlan()['restored_page_images']));
    },
    'trigger delete returning fk savepoint next120 restrict dirty pages' => static function (TestRunner $t) use ($restrictPlan): void {
        $t->same([2, 5, 8], $restrictPlan()['dirty_page_numbers']);
    },
    'trigger delete returning fk savepoint next120 restrict wal frame' => static function (TestRunner $t) use ($restrictPlan): void {
        $t->same(44, $restrictPlan()['rollback_to_wal_frame']);
    },
    'trigger delete returning fk savepoint next120 restrict discarded frames' => static function (TestRunner $t) use ($restrictPlan): void {
        $t->same([45, 46, 47], array_column($restrictPlan()['discarded_wal_frames'], 'frame_index'));
    },
    'trigger delete returning fk savepoint next120 restrict commit frame preserved' => static function (TestRunner $t) use ($restrictPlan): void {
        $t->same(true, $restrictPlan()['discarded_wal_frames'][1]['commit_frame']);
    },
    'trigger delete returning fk savepoint next120 deferred blocked status' => static function (TestRunner $t) use ($deferredBlockedPlan): void {
        $t->same('deferred-commit-blocked', $deferredBlockedPlan()['status']);
    },
    'trigger delete returning fk savepoint next120 deferred blocked boundary' => static function (TestRunner $t) use ($deferredBlockedPlan): void {
        $t->same('deferred-commit-blocked', $deferredBlockedPlan()['current_next_boundary']);
    },
    'trigger delete returning fk savepoint next120 deferred blocked violations' => static function (TestRunner $t) use ($deferredBlockedPlan): void {
        $t->same([1, 2], array_column($deferredBlockedPlan()['foreign_key_violations'], 'child_key'));
    },
    'trigger delete returning fk savepoint next120 deferred blocked next keeps attempted rows' => static function (TestRunner $t) use ($deferredBlockedPlan): void {
        $t->same([3], $deferredBlockedPlan()['next_rowids']);
    },
    'trigger delete returning fk savepoint next120 deferred rollback status' => static function (TestRunner $t) use ($deferredRollbackPlan): void {
        $t->same('rolled-back', $deferredRollbackPlan()['status']);
    },
    'trigger delete returning fk savepoint next120 deferred rollback reason' => static function (TestRunner $t) use ($deferredRollbackPlan): void {
        $t->same('deferred-foreign-key-violation', $deferredRollbackPlan()['rollback_reason']);
    },
    'trigger delete returning fk savepoint next120 deferred rollback next restored' => static function (TestRunner $t) use ($deferredRollbackPlan): void {
        $t->same([1, 2, 3], $deferredRollbackPlan()['next_rowids']);
    },
    'trigger delete returning fk savepoint next120 deferred rollback suppresses returning' => static function (TestRunner $t) use ($deferredRollbackPlan): void {
        $t->same([], $deferredRollbackPlan()['next_returning_rows']);
    },
    'trigger delete returning fk savepoint next120 after rollback status' => static function (TestRunner $t) use ($triggerRollbackPlan): void {
        $t->same('rolled-back', $triggerRollbackPlan()['status']);
    },
    'trigger delete returning fk savepoint next120 after rollback reason' => static function (TestRunner $t) use ($triggerRollbackPlan): void {
        $t->same('trigger-rollback:app_settings_ad_abort', $triggerRollbackPlan()['rollback_reason']);
    },
    'trigger delete returning fk savepoint next120 after rollback effect' => static function (TestRunner $t) use ($triggerRollbackPlan): void {
        $t->same('raise-rollback', $triggerRollbackPlan()['trigger_effects'][0]['action']);
    },
    'trigger delete returning fk savepoint next120 before ignore status' => static function (TestRunner $t) use ($ignorePlan): void {
        $t->same('commit-ok', $ignorePlan()['status']);
    },
    'trigger delete returning fk savepoint next120 before ignore rowid' => static function (TestRunner $t) use ($ignorePlan): void {
        $t->same([3], $ignorePlan()['ignored_rowids']);
    },
    'trigger delete returning fk savepoint next120 before ignore no returning' => static function (TestRunner $t) use ($ignorePlan): void {
        $t->same([], $ignorePlan()['current_returning_rows']);
    },
    'trigger delete returning fk savepoint next120 before ignore rows unchanged' => static function (TestRunner $t) use ($ignorePlan): void {
        $t->same([1, 2, 3], $ignorePlan()['next_rowids']);
    },
    'trigger delete returning fk savepoint next120 bad savepoint throws' => static function (TestRunner $t) use ($parents, $children, $cascadeFk, $baseStatement): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan::execute($parents, $children, $cascadeFk, array_replace($baseStatement, ['savepoint' => 'bad-name'])));
    },
    'trigger delete returning fk savepoint next120 missing where throws' => static function (TestRunner $t) use ($parents, $children, $cascadeFk, $baseStatement): void {
        $statement = $baseStatement;
        unset($statement['where']);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan::execute($parents, $children, $cascadeFk, $statement));
    },
    'trigger delete returning fk savepoint next120 bad fk action throws' => static function (TestRunner $t) use ($parents, $children, $baseStatement): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan::execute($parents, $children, ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_delete' => 'explode'], $baseStatement));
    },
    'trigger delete returning fk savepoint next120 bad trigger action throws' => static function (TestRunner $t) use ($parents, $children, $cascadeFk, $baseStatement): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan::execute($parents, $children, $cascadeFk, array_replace($baseStatement, ['after_triggers' => [['name' => 'app_settings_ad_bad', 'action' => 'explode']]])));
    },
    'trigger delete returning fk savepoint next120 new returning throws' => static function (TestRunner $t) use ($parents, $children, $cascadeFk, $baseStatement): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan::execute($parents, $children, $cascadeFk, array_replace($baseStatement, ['returning' => ['new.setting_id']])));
    },
    'trigger delete returning fk savepoint next120 bad page throws' => static function (TestRunner $t) use ($parents, $children, $cascadeFk, $baseStatement): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan::execute($parents, $children, $cascadeFk, array_replace($baseStatement, ['page_images' => [0 => 'short']])));
    },
    'trigger delete returning fk savepoint next120 bad wal frame throws' => static function (TestRunner $t) use ($parents, $children, $cascadeFk, $baseStatement): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan::execute($parents, $children, $cascadeFk, array_replace($baseStatement, ['wal_frames' => [['frame_index' => 0, 'page_number' => 1]]])));
    },
];

return $tests;
