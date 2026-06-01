<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerForeignKeyReturningPlan;

$tests = [];

$parents = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 2],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'revision' => 1],
    ['option_id' => 3, 'option_name' => 'plugin_cache', 'option_value' => 'stale', 'autoload' => 'no', 'revision' => 5],
    ['option_id' => 4, 'option_name' => 'blogname', 'option_value' => 'Old Blog', 'autoload' => 'no', 'revision' => 3],
];

$children = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 11, 'option_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 12, 'option_id' => 3, 'meta_key' => 'source', 'meta_value' => 'plugin'],
    ['meta_id' => 13, 'option_id' => 4, 'meta_key' => 'source', 'meta_value' => 'core'],
];

$fkCascade = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_update' => 'cascade', 'on_delete' => 'cascade', 'deferred' => false];
$fkDeferredNoAction = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_update' => 'no action', 'on_delete' => 'no action', 'deferred' => true];
$fkSetNull = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_update' => 'set null', 'on_delete' => 'set null', 'deferred' => false];

$assignments = [
    'option_id' => static fn (array $old): int => (int) $old['option_id'] + 100,
    'option_value' => static fn (array $old): string => $old['option_name'] . ':migrated',
    'revision' => static fn (array $old): int => (int) $old['revision'] + 1,
];

$triggers = [
    [
        'name' => 'wp_options_bu_touch',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'set-new',
        'when' => ['new.autoload', '=', 'yes'],
        'set' => ['option_value' => 'before:' . 'new.option_name'],
        'values' => ['old_key' => 'old.option_id', 'new_key' => 'new.option_id', 'name' => 'new.option_name'],
    ],
    [
        'name' => 'wp_options_au_audit',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.option_id', 'option_id' => 'new.parent_key', 'meta_key' => 'returning_seen', 'meta_value' => 'new.option_value'],
        'values' => ['key' => 'new.option_id', 'value' => 'new.option_value'],
    ],
    [
        'name' => 'wp_options_ad_audit',
        'timing' => 'after',
        'event' => 'delete',
        'action' => 'insert-child',
        'row' => ['meta_id' => 900, 'option_id' => null, 'meta_key' => 'deleted', 'meta_value' => 'old.option_name'],
        'values' => ['old_key' => 'old.option_id', 'name' => 'old.option_name'],
    ],
];

$returning = [
    'option_id',
    'option_name',
    'option_value',
    ['expr' => 'old.option_id', 'as' => 'old_option_id'],
    ['expr' => 'new.revision', 'as' => 'current_revision'],
    static fn (array $row, array $old, string $event): string => $event . ':' . $old['option_name'] . '=>' . $row['option_id'],
];

$updateCascade = static fn (): array => SQLiteTriggerForeignKeyReturningPlan::updateParents(
    $parents,
    $children,
    $assignments,
    static fn (array $row): bool => $row['autoload'] === 'yes',
    $fkCascade,
    $triggers,
    $returning,
    'option_id',
);

$deleteCascade = static fn (): array => SQLiteTriggerForeignKeyReturningPlan::deleteParents(
    $parents,
    $children,
    static fn (array $row): bool => $row['autoload'] === 'no',
    $fkCascade,
    $triggers,
    ['option_id', 'option_name', ['expr' => 'old.option_value', 'as' => 'old_value']],
    'option_id',
);

$deferredNoAction = static fn (): array => SQLiteTriggerForeignKeyReturningPlan::updateParents(
    $parents,
    $children,
    ['option_id' => static fn (array $old): int => (int) $old['option_id'] + 500],
    static fn (array $row): bool => $row['option_id'] === 3,
    $fkDeferredNoAction,
    [],
    ['option_id', ['expr' => 'old.option_id', 'as' => 'old_option_id']],
    'option_id',
);

$setNullDelete = static fn (): array => SQLiteTriggerForeignKeyReturningPlan::deleteParents(
    $parents,
    $children,
    static fn (array $row): bool => $row['option_id'] === 1,
    $fkSetNull,
    [],
    ['*'],
    'option_id',
);

$cases = [
    'update cascade changes two autoloaded parents' => [static fn (): mixed => $updateCascade()['changes'], 2],
    'update cascade parent keys are rewritten in place' => [static fn (): mixed => array_column($updateCascade()['parent'], 'option_id'), [101, 102, 3, 4]],
    'update cascade child keys follow parent keys' => [static fn (): mixed => array_column($updateCascade()['child'], 'option_id'), [101, 102, 3, 4, 101, 102]],
    'update cascade inserts after trigger audit children' => [static fn (): mixed => array_column($updateCascade()['child'], 'meta_key'), ['source', 'source', 'source', 'source', 'returning_seen', 'returning_seen']],
    'update cascade records two foreign key actions' => [static fn (): mixed => count($updateCascade()['foreign_key_actions']), 2],
    'update cascade first action is cascade' => [static fn (): mixed => $updateCascade()['foreign_key_actions'][0]['action'], 'cascade'],
    'update cascade first action from old key' => [static fn (): mixed => $updateCascade()['foreign_key_actions'][0]['from'], 1],
    'update cascade first action to new key' => [static fn (): mixed => $updateCascade()['foreign_key_actions'][0]['to'], 101],
    'update cascade second action to new key' => [static fn (): mixed => $updateCascade()['foreign_key_actions'][1]['to'], 102],
    'update cascade trigger order alternates per row' => [static fn (): mixed => array_column($updateCascade()['trigger_effects'], 'trigger'), ['wp_options_bu_touch', 'wp_options_au_audit', 'wp_options_bu_touch', 'wp_options_au_audit']],
    'update cascade before trigger sees old key' => [static fn (): mixed => $updateCascade()['trigger_effects'][0]['row']['old_key'], 1],
    'update cascade before trigger sees new key' => [static fn (): mixed => $updateCascade()['trigger_effects'][0]['row']['new_key'], 101],
    'update cascade after trigger sees current key' => [static fn (): mixed => $updateCascade()['trigger_effects'][1]['row']['key'], 101],
    'update cascade yielded rows preserve statement order' => [static fn (): mixed => array_column($updateCascade()['yielded'], 'old_key'), [1, 2]],
    'update cascade yielded new keys are current parent keys' => [static fn (): mixed => array_column($updateCascade()['yielded'], 'new_key'), [101, 102]],
    'update cascade returning first option id' => [static fn (): mixed => $updateCascade()['yielded'][0]['returning']['option_id'], 101],
    'update cascade returning first old option id' => [static fn (): mixed => $updateCascade()['yielded'][0]['returning']['old_option_id'], 1],
    'update cascade returning second old option id' => [static fn (): mixed => $updateCascade()['yielded'][1]['returning']['old_option_id'], 2],
    'update cascade returning first current revision' => [static fn (): mixed => $updateCascade()['yielded'][0]['returning']['current_revision'], 3],
    'update cascade returning value is before trigger image' => [static fn (): mixed => $updateCascade()['yielded'][0]['returning']['option_value'], 'before:new.option_name'],
    'update cascade final parent keeps before trigger value' => [static fn (): mixed => $updateCascade()['parent'][0]['option_value'], 'before:new.option_name'],
    'update cascade callable returning labels update' => [static fn (): mixed => $updateCascade()['yielded'][0]['returning']['expr5'], 'update:siteurl=>101'],
    'update cascade has no statement violations' => [static fn (): mixed => array_column($updateCascade()['yielded'], 'violations_before_after_triggers'), [0, 0]],
    'update cascade has no final violations' => [static fn (): mixed => array_column($updateCascade()['yielded'], 'violations_after_triggers'), [0, 0]],
    'update cascade violation list empty' => [static fn (): mixed => $updateCascade()['foreign_key_violations'], []],
    'update cascade untouched plugin row remains' => [static fn (): mixed => $updateCascade()['parent'][2]['option_id'], 3],
    'update cascade untouched blog row remains' => [static fn (): mixed => $updateCascade()['parent'][3]['option_id'], 4],
    'update cascade after audit child value uses returning image' => [static fn (): mixed => $updateCascade()['child'][4]['meta_value'], 'before:new.option_name'],
    'update cascade second audit child value uses returning image' => [static fn (): mixed => $updateCascade()['child'][5]['meta_value'], 'before:new.option_name'],

    'delete cascade changes two nonautoloaded parents' => [static fn (): mixed => $deleteCascade()['changes'], 2],
    'delete cascade remaining parent names' => [static fn (): mixed => array_column($deleteCascade()['parent'], 'option_name'), ['siteurl', 'home']],
    'delete cascade removes matching child rows then audits deletes' => [static fn (): mixed => array_column($deleteCascade()['child'], 'meta_key'), ['source', 'source', 'deleted', 'deleted']],
    'delete cascade action names' => [static fn (): mixed => array_column($deleteCascade()['foreign_key_actions'], 'action'), ['cascade-delete', 'cascade-delete']],
    'delete cascade action source keys' => [static fn (): mixed => array_column($deleteCascade()['foreign_key_actions'], 'from'), [3, 4]],
    'delete cascade yielded old keys' => [static fn (): mixed => array_column($deleteCascade()['yielded'], 'old_key'), [3, 4]],
    'delete cascade yielded new keys equal deleted keys' => [static fn (): mixed => array_column($deleteCascade()['yielded'], 'new_key'), [3, 4]],
    'delete cascade returning old value' => [static fn (): mixed => $deleteCascade()['yielded'][0]['returning']['old_value'], 'stale'],
    'delete cascade returning deleted name' => [static fn (): mixed => $deleteCascade()['yielded'][1]['returning']['option_name'], 'blogname'],
    'delete cascade after trigger records names' => [static fn (): mixed => array_column(array_column($deleteCascade()['trigger_effects'], 'row'), 'name'), ['plugin_cache', 'blogname']],
    'delete cascade audit children are null parent rows' => [static fn (): mixed => array_slice(array_column($deleteCascade()['child'], 'option_id'), -2), [null, null]],
    'delete cascade has no violations' => [static fn (): mixed => $deleteCascade()['foreign_key_violations'], []],

    'deferred no action changes one row' => [static fn (): mixed => $deferredNoAction()['changes'], 1],
    'deferred no action parent key changes' => [static fn (): mixed => $deferredNoAction()['parent'][2]['option_id'], 503],
    'deferred no action child remains orphaned until commit' => [static fn (): mixed => $deferredNoAction()['child'][2]['option_id'], 3],
    'deferred no action returning new key' => [static fn (): mixed => $deferredNoAction()['yielded'][0]['returning']['option_id'], 503],
    'deferred no action returning old key' => [static fn (): mixed => $deferredNoAction()['yielded'][0]['returning']['old_option_id'], 3],
    'deferred no action statement violation count' => [static fn (): mixed => $deferredNoAction()['yielded'][0]['violations_before_after_triggers'], 1],
    'deferred no action final violation count' => [static fn (): mixed => $deferredNoAction()['yielded'][0]['violations_after_triggers'], 1],
    'deferred no action violation phase statement' => [static fn (): mixed => $deferredNoAction()['foreign_key_violations'][0]['phase'], 'statement'],
    'deferred no action violation phase after trigger' => [static fn (): mixed => $deferredNoAction()['foreign_key_violations'][1]['phase'], 'after-trigger'],
    'deferred no action records no child mutation actions' => [static fn (): mixed => $deferredNoAction()['foreign_key_actions'][0]['action'], 'no action'],

    'set null delete changes one row' => [static fn (): mixed => $setNullDelete()['changes'], 1],
    'set null delete remaining parent count' => [static fn (): mixed => count($setNullDelete()['parent']), 3],
    'set null delete child first key is null' => [static fn (): mixed => $setNullDelete()['child'][0]['option_id'], null],
    'set null delete returning star has old name' => [static fn (): mixed => $setNullDelete()['yielded'][0]['returning']['*']['option_name'], 'siteurl'],
    'set null delete action is set null' => [static fn (): mixed => $setNullDelete()['foreign_key_actions'][0]['action'], 'set-null'],
    'set null delete has no violations' => [static fn (): mixed => $setNullDelete()['foreign_key_violations'], []],

    'immediate no action update rejects orphan' => [static fn (): mixed => SQLiteTriggerForeignKeyReturningPlan::updateParents($parents, $children, ['option_id' => 700], static fn (array $row): bool => $row['option_id'] === 1, ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_update' => 'no action', 'deferred' => false]), InvalidArgumentException::class],
    'bad assignment column throws' => [static fn (): mixed => SQLiteTriggerForeignKeyReturningPlan::updateParents($parents, $children, ['bad-column' => 1], static fn (): bool => true, $fkCascade), InvalidArgumentException::class],
    'empty assignment throws' => [static fn (): mixed => SQLiteTriggerForeignKeyReturningPlan::updateParents($parents, $children, [], static fn (): bool => true, $fkCascade), InvalidArgumentException::class],
    'bad foreign key action throws' => [static fn (): mixed => SQLiteTriggerForeignKeyReturningPlan::deleteParents($parents, $children, static fn (): bool => true, ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_delete' => 'explode']), InvalidArgumentException::class],
    'bad returning alias throws' => [static fn (): mixed => SQLiteTriggerForeignKeyReturningPlan::deleteParents($parents, $children, static fn (array $row): bool => $row['option_id'] === 1, $fkCascade, [], [['expr' => 'option_id', 'as' => 'bad-alias']]), InvalidArgumentException::class],
    'missing returning column throws' => [static fn (): mixed => SQLiteTriggerForeignKeyReturningPlan::deleteParents($parents, $children, static fn (array $row): bool => $row['option_id'] === 1, $fkCascade, [], ['missing']), InvalidArgumentException::class],
    'bad trigger action throws' => [static fn (): mixed => SQLiteTriggerForeignKeyReturningPlan::updateParents($parents, $children, ['option_id' => 9], static fn (array $row): bool => $row['option_id'] === 1, $fkCascade, [['timing' => 'before', 'event' => 'update', 'action' => 'delete-parent']]), InvalidArgumentException::class],
    'bad when operator throws' => [static fn (): mixed => SQLiteTriggerForeignKeyReturningPlan::updateParents($parents, $children, ['option_id' => 9], static fn (array $row): bool => $row['option_id'] === 1, $fkCascade, [['timing' => 'before', 'event' => 'update', 'when' => ['new.option_id', 'LIKE', 9]]]), InvalidArgumentException::class],
    'missing child key throws' => [static fn (): mixed => SQLiteTriggerForeignKeyReturningPlan::deleteParents($parents, [['meta_id' => 1]], static fn (array $row): bool => $row['option_id'] === 1, $fkCascade), InvalidArgumentException::class],
    'missing trigger value column throws' => [static fn (): mixed => SQLiteTriggerForeignKeyReturningPlan::updateParents($parents, $children, ['option_id' => 9], static fn (array $row): bool => $row['option_id'] === 1, $fkCascade, [['timing' => 'before', 'event' => 'update', 'values' => ['missing' => 'new.missing']]]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['trigger foreign key returning current next33 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
