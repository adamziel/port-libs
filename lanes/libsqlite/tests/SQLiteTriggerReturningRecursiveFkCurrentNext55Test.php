<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerForeignKeyReturningPlan;

$parents = [
    ['post_id' => 0, 'post_title' => 'Unassigned'],
    ['post_id' => 10, 'post_title' => 'Imported alpha'],
    ['post_id' => 20, 'post_title' => 'Imported beta'],
];
$children = [
    ['meta_id' => 100, 'post_id' => 10, 'meta_key' => '_import_batch'],
    ['meta_id' => 101, 'post_id' => 10, 'meta_key' => '_thumbnail_id'],
    ['meta_id' => 102, 'post_id' => 20, 'meta_key' => '_import_batch'],
];
$fk = [
    'parent_key' => 'post_id',
    'child_key' => 'post_id',
    'on_update' => 'SET DEFAULT',
    'on_delete' => 'SET DEFAULT',
    'child_default' => 0,
    'deferred' => true,
];
$triggers = [
    [
        'name' => 'before_post_rekey',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'set-new',
        'when' => ['new.post_id', '=', 99],
        'set' => ['post_title' => 'Rekeyed import'],
    ],
    [
        'name' => 'after_post_rekey_audit',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'insert-child',
        'row' => ['meta_id' => 990, 'post_id' => 'new.post_id', 'meta_key' => '_rekey_audit'],
        'values' => ['post_id' => 'new.post_id', 'meta_key' => '_rekey_audit'],
    ],
    [
        'name' => 'after_post_delete_audit',
        'timing' => 'after',
        'event' => 'delete',
        'action' => 'insert-child',
        'row' => ['meta_id' => 991, 'post_id' => 'old.post_id', 'meta_key' => '_delete_audit'],
        'values' => ['post_id' => 'old.post_id', 'meta_key' => '_delete_audit'],
    ],
];
$returning = [
    'old.post_id',
    ['expr' => 'new.post_id', 'as' => 'new_post_id'],
    ['expr' => 'new.post_title', 'as' => 'title'],
    static fn (array $row, array $old, string $event): string => $event . ':' . $old['post_id'] . '>' . $row['post_id'],
];

$updateDefault = static fn (): array => SQLiteTriggerForeignKeyReturningPlan::updateParents(
    $parents,
    $children,
    ['post_id' => 99],
    static fn (array $row): bool => $row['post_id'] === 10,
    $fk,
    $triggers,
    $returning,
    'post_id'
);
$deleteDefault = static fn (): array => SQLiteTriggerForeignKeyReturningPlan::deleteParents(
    $parents,
    $children,
    static fn (array $row): bool => $row['post_id'] === 10,
    $fk,
    $triggers,
    ['old.post_id', ['expr' => 'old.post_title', 'as' => 'deleted_title']],
    'post_id'
);
$updateMissingDefault = static fn (): array => SQLiteTriggerForeignKeyReturningPlan::updateParents(
    array_values(array_filter($parents, static fn (array $row): bool => $row['post_id'] !== 0)),
    $children,
    ['post_id' => 99],
    static fn (array $row): bool => $row['post_id'] === 10,
    $fk,
    [],
    ['new.post_id'],
    'post_id'
);
$deleteImmediateMissingDefault = static function () use ($parents, $children, $fk): array {
    $immediate = $fk;
    $immediate['deferred'] = false;
    $immediate['child_default'] = 404;

    return SQLiteTriggerForeignKeyReturningPlan::deleteParents(
        $parents,
        $children,
        static fn (array $row): bool => $row['post_id'] === 10,
        $immediate,
        [],
        ['old.post_id'],
        'post_id'
    );
};

$tests = [
    'trigger returning set default update changes one parent' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same(1, $updateDefault()['changes']);
    },
    'trigger returning set default update keeps parent count' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same(3, count($updateDefault()['parent']));
    },
    'trigger returning set default update rewrites parent key' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same([0, 99, 20], array_column($updateDefault()['parent'], 'post_id'));
    },
    'trigger returning set default before trigger rewrites title' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same('Rekeyed import', $updateDefault()['parent'][1]['post_title']);
    },
    'trigger returning set default yields pre after-trigger image' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same('Rekeyed import', $updateDefault()['yielded'][0]['returning']['title']);
    },
    'trigger returning set default yielded old key' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same(10, $updateDefault()['yielded'][0]['returning']['post_id']);
    },
    'trigger returning set default yielded new key alias' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same(99, $updateDefault()['yielded'][0]['returning']['new_post_id']);
    },
    'trigger returning set default yielded callable expression' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same('update:10>99', $updateDefault()['yielded'][0]['returning']['expr3']);
    },
    'trigger returning set default yield event is update' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same('update', $updateDefault()['yielded'][0]['event']);
    },
    'trigger returning set default yield ordinal is zero' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same(0, $updateDefault()['yielded'][0]['ordinal']);
    },
    'trigger returning set default yield old key field' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same(10, $updateDefault()['yielded'][0]['old_key']);
    },
    'trigger returning set default yield new key field' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same(99, $updateDefault()['yielded'][0]['new_key']);
    },
    'trigger returning set default update rewrites first matching child' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same(0, $updateDefault()['child'][0]['post_id']);
    },
    'trigger returning set default update rewrites second matching child' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same(0, $updateDefault()['child'][1]['post_id']);
    },
    'trigger returning set default update leaves unrelated child' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same(20, $updateDefault()['child'][2]['post_id']);
    },
    'trigger returning set default update appends after trigger child' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same(990, $updateDefault()['child'][3]['meta_id']);
    },
    'trigger returning set default after trigger child references new key' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same(99, $updateDefault()['child'][3]['post_id']);
    },
    'trigger returning set default after trigger child keeps audit key' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same('_rekey_audit', $updateDefault()['child'][3]['meta_key']);
    },
    'trigger returning set default records two fk actions' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same(2, count($updateDefault()['foreign_key_actions']));
    },
    'trigger returning set default first action is set default' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same('set-default', $updateDefault()['foreign_key_actions'][0]['action']);
    },
    'trigger returning set default action event is update' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same('update', $updateDefault()['foreign_key_actions'][0]['event']);
    },
    'trigger returning set default action old key' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same(10, $updateDefault()['foreign_key_actions'][0]['from']);
    },
    'trigger returning set default action default key' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same(0, $updateDefault()['foreign_key_actions'][0]['to']);
    },
    'trigger returning set default action child index order' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same([0, 1], array_column($updateDefault()['foreign_key_actions'], 'child_index'));
    },
    'trigger returning set default records before trigger effect' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same('before_post_rekey', $updateDefault()['trigger_effects'][0]['trigger']);
    },
    'trigger returning set default records before trigger timing' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same('before', $updateDefault()['trigger_effects'][0]['timing']);
    },
    'trigger returning set default records before trigger action' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same('set-new', $updateDefault()['trigger_effects'][0]['action']);
    },
    'trigger returning set default records after trigger effect' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same('after_post_rekey_audit', $updateDefault()['trigger_effects'][1]['trigger']);
    },
    'trigger returning set default records after trigger timing' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same('after', $updateDefault()['trigger_effects'][1]['timing']);
    },
    'trigger returning set default records after trigger row value' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same(99, $updateDefault()['trigger_effects'][1]['row']['post_id']);
    },
    'trigger returning set default has no violation when default parent exists' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same([], $updateDefault()['foreign_key_violations']);
    },
    'trigger returning set default reports zero pre after-trigger violations' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same(0, $updateDefault()['yielded'][0]['violations_before_after_triggers']);
    },
    'trigger returning set default reports zero after-trigger violations' => static function (TestRunner $t) use ($updateDefault): void {
        $t->same(0, $updateDefault()['yielded'][0]['violations_after_triggers']);
    },
    'trigger returning delete set default changes one parent' => static function (TestRunner $t) use ($deleteDefault): void {
        $t->same(1, $deleteDefault()['changes']);
    },
    'trigger returning delete set default removes parent row' => static function (TestRunner $t) use ($deleteDefault): void {
        $t->same([0, 20], array_column($deleteDefault()['parent'], 'post_id'));
    },
    'trigger returning delete set default rewrites first child' => static function (TestRunner $t) use ($deleteDefault): void {
        $t->same(0, $deleteDefault()['child'][0]['post_id']);
    },
    'trigger returning delete set default rewrites second child' => static function (TestRunner $t) use ($deleteDefault): void {
        $t->same(0, $deleteDefault()['child'][1]['post_id']);
    },
    'trigger returning delete set default leaves unrelated child' => static function (TestRunner $t) use ($deleteDefault): void {
        $t->same(20, $deleteDefault()['child'][2]['post_id']);
    },
    'trigger returning delete after trigger child references deleted key' => static function (TestRunner $t) use ($deleteDefault): void {
        $t->same(10, $deleteDefault()['child'][3]['post_id']);
    },
    'trigger returning delete deferred violation is after trigger only' => static function (TestRunner $t) use ($deleteDefault): void {
        $t->same('after-trigger', $deleteDefault()['foreign_key_violations'][0]['phase']);
    },
    'trigger returning delete deferred violation child key' => static function (TestRunner $t) use ($deleteDefault): void {
        $t->same(10, $deleteDefault()['foreign_key_violations'][0]['child_key']);
    },
    'trigger returning delete deferred violation child index' => static function (TestRunner $t) use ($deleteDefault): void {
        $t->same(3, $deleteDefault()['foreign_key_violations'][0]['child_index']);
    },
    'trigger returning delete set default action names' => static function (TestRunner $t) use ($deleteDefault): void {
        $t->same(['set-default', 'set-default'], array_column($deleteDefault()['foreign_key_actions'], 'action'));
    },
    'trigger returning delete set default action events' => static function (TestRunner $t) use ($deleteDefault): void {
        $t->same(['delete', 'delete'], array_column($deleteDefault()['foreign_key_actions'], 'event'));
    },
    'trigger returning delete set default action target keys' => static function (TestRunner $t) use ($deleteDefault): void {
        $t->same([0, 0], array_column($deleteDefault()['foreign_key_actions'], 'to'));
    },
    'trigger returning delete yielded old key projection' => static function (TestRunner $t) use ($deleteDefault): void {
        $t->same(10, $deleteDefault()['yielded'][0]['returning']['post_id']);
    },
    'trigger returning delete yielded deleted title alias' => static function (TestRunner $t) use ($deleteDefault): void {
        $t->same('Imported alpha', $deleteDefault()['yielded'][0]['returning']['deleted_title']);
    },
    'trigger returning delete yield event is delete' => static function (TestRunner $t) use ($deleteDefault): void {
        $t->same('delete', $deleteDefault()['yielded'][0]['event']);
    },
    'trigger returning delete yield new key is old row key' => static function (TestRunner $t) use ($deleteDefault): void {
        $t->same(10, $deleteDefault()['yielded'][0]['new_key']);
    },
    'trigger returning set default child defaults map overrides scalar default' => static function (TestRunner $t) use ($parents, $children, $fk): void {
        $mapped = $fk;
        $mapped['child_default'] = 404;
        $mapped['child_defaults'] = ['post_id' => 0];
        $result = SQLiteTriggerForeignKeyReturningPlan::deleteParents($parents, $children, static fn (array $row): bool => $row['post_id'] === 10, $mapped, [], ['old.post_id'], 'post_id');
        $t->same([0, 0, 20], array_column($result['child'], 'post_id'));
    },
    'trigger returning set default missing default parent records statement violation' => static function (TestRunner $t) use ($updateMissingDefault): void {
        $t->same('statement', $updateMissingDefault()['foreign_key_violations'][0]['phase']);
    },
    'trigger returning set default missing default child key' => static function (TestRunner $t) use ($updateMissingDefault): void {
        $t->same(0, $updateMissingDefault()['foreign_key_violations'][0]['child_key']);
    },
    'trigger returning set default missing default yield counts violation' => static function (TestRunner $t) use ($updateMissingDefault): void {
        $t->same(2, $updateMissingDefault()['yielded'][0]['violations_before_after_triggers']);
    },
    'trigger returning set default immediate missing parent raises' => static function (TestRunner $t) use ($deleteImmediateMissingDefault): void {
        $t->throws(InvalidArgumentException::class, $deleteImmediateMissingDefault);
    },
    'trigger returning set default rejects malformed child defaults' => static function (TestRunner $t) use ($parents, $children, $fk): void {
        $broken = $fk;
        $broken['child_defaults'] = 'post_id=0';
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerForeignKeyReturningPlan::deleteParents($parents, $children, static fn (array $row): bool => $row['post_id'] === 10, $broken, [], ['old.post_id'], 'post_id'));
    },
];

return $tests;
