<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonbGeneratedCascadePlan;

$jsonb = static fn (array $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$parents = [
    ['setting_id' => 1, 'key_name' => 'site_one_settings', 'key_value' => $jsonb(['site' => ['id' => 'site-1', 'theme' => 'twentysixteen'], 'source' => 'current'])],
    ['setting_id' => 2, 'key_name' => 'site_two_settings', 'key_value' => $jsonb(['site' => ['id' => 'site-2', 'theme' => 'twentytwenty'], 'source' => 'current'])],
    ['setting_id' => 3, 'key_name' => 'site_three_settings', 'key_value' => $jsonb(['site' => ['id' => 'site-3', 'theme' => 'classic'], 'source' => 'current'])],
];
$children = [
    ['meta_id' => 10, 'site_key' => 'site-1', 'meta_key' => 'home_url'],
    ['meta_id' => 11, 'site_key' => 'site-1', 'meta_key' => 'upload_path'],
    ['meta_id' => 12, 'site_key' => 'site-2', 'meta_key' => 'rewrite_rules'],
    ['meta_id' => 13, 'site_key' => null, 'meta_key' => 'orphan_import'],
    ['meta_id' => 14, 'site_key' => 'site-3', 'meta_key' => 'template'],
];
$foreignKey = [
    'parent_column' => 'site_key',
    'source_column' => 'key_value',
    'json_path' => '$.site.id',
    'child_column' => 'site_key',
    'rowid_column' => 'setting_id',
    'on_update' => 'CASCADE',
    'on_delete' => 'CASCADE',
];

$run = static fn (
    array $updates = [['site_key' => 'site-1', 'new_site_key' => 'site-1-imported']],
    array $deletes = ['site-2'],
    ?array $fk = null,
    ?array $parentRows = null,
    ?array $childRows = null,
): array => SQLiteJsonbGeneratedCascadePlan::plan(
    $parentRows ?? $parents,
    $childRows ?? $children,
    $updates,
    $deletes,
    $fk ?? $foreignKey,
);

return [
    'jsonb table generated cascade current next53 hydrates generated parent keys' => static fn (TestRunner $t) => $t->same(['site-1', 'site-2', 'site-3'], array_column($run()['before_parent'], 'site_key')),
    'jsonb table generated cascade current next53 updates generated key from jsonb path' => static fn (TestRunner $t) => $t->same(['site-1-imported', 'site-3'], array_column($run()['after_parent'], 'site_key')),
    'jsonb table generated cascade current next53 rewrites first child key' => static fn (TestRunner $t) => $t->same('site-1-imported', $run()['after_child'][0]['site_key']),
    'jsonb table generated cascade current next53 rewrites second child key' => static fn (TestRunner $t) => $t->same('site-1-imported', $run()['after_child'][1]['site_key']),
    'jsonb table generated cascade current next53 deletes child of deleted generated parent' => static fn (TestRunner $t) => $t->same([10, 11, 13, 14], array_column($run()['after_child'], 'meta_id')),
    'jsonb table generated cascade current next53 preserves null child key' => static fn (TestRunner $t) => $t->same(null, $run()['after_child'][2]['site_key']),
    'jsonb table generated cascade current next53 preserves unrelated generated child key' => static fn (TestRunner $t) => $t->same('site-3', $run()['after_child'][3]['site_key']),
    'jsonb table generated cascade current next53 counts generated update and cascades' => static fn (TestRunner $t) => $t->same(5, $run()['changes']),
    'jsonb table generated cascade current next53 records action count' => static fn (TestRunner $t) => $t->same(5, $run()['action_count']),
    'jsonb table generated cascade current next53 first action is parent jsonb update' => static fn (TestRunner $t) => $t->same('update-parent-generated-jsonb', $run()['actions'][0]['action']),
    'jsonb table generated cascade current next53 parent action records old key' => static fn (TestRunner $t) => $t->same('site-1', $run()['actions'][0]['old_key']),
    'jsonb table generated cascade current next53 parent action records new key' => static fn (TestRunner $t) => $t->same('site-1-imported', $run()['actions'][0]['new_key']),
    'jsonb table generated cascade current next53 parent action records neutral rowid' => static fn (TestRunner $t) => $t->same(1, $run()['actions'][0]['rowid']),
    'jsonb table generated cascade current next53 parent action records path' => static fn (TestRunner $t) => $t->same('$.site.id', $run()['actions'][0]['json_path']),
    'jsonb table generated cascade current next53 child cascade actions follow parent update' => static fn (TestRunner $t) => $t->same(['cascade-update-child-generated-key', 'cascade-update-child-generated-key'], array_slice(array_column($run()['actions'], 'action'), 1, 2)),
    'jsonb table generated cascade current next53 delete parent action follows child updates' => static fn (TestRunner $t) => $t->same('delete-parent-generated-jsonb', $run()['actions'][3]['action']),
    'jsonb table generated cascade current next53 delete child action records old child' => static fn (TestRunner $t) => $t->same('rewrite_rules', $run()['actions'][4]['old_child']['meta_key']),
    'jsonb table generated cascade current next53 decoded parent jsonb stores new generated key' => static function (TestRunner $t) use ($run): void {
        $decoded = SQLiteJsonB::decode($run()['after_parent'][0]['key_value']->bytes);
        $t->same('site-1-imported', $decoded['site']['id']);
    },
    'jsonb table generated cascade current next53 decoded parent jsonb preserves sibling payload' => static function (TestRunner $t) use ($run): void {
        $decoded = SQLiteJsonB::decode($run()['after_parent'][0]['key_value']->bytes);
        $t->same('twentysixteen', $decoded['site']['theme']);
    },
    'jsonb table generated cascade current next53 deletes parent row by generated key' => static fn (TestRunner $t) => $t->same(['site_one_settings', 'site_three_settings'], array_column($run()['after_parent'], 'key_name')),
    'jsonb table generated cascade current next53 leaves no violations after cascades' => static fn (TestRunner $t) => $t->same([], $run()['violations']),
    'jsonb table generated cascade current next53 before child row order captured' => static fn (TestRunner $t) => $t->same([10, 11, 12, 13, 14], array_column($run()['before_child'], 'meta_id')),
    'jsonb table generated cascade current next53 after child row order stable after delete' => static fn (TestRunner $t) => $t->same([10, 11, 13, 14], array_column($run()['after_child'], 'meta_id')),
    'jsonb table generated cascade current next53 no-op missing update key' => static fn (TestRunner $t) => $t->same(['site-1', 'site-2', 'site-3'], array_column($run([['site_key' => 'missing', 'new_site_key' => 'fresh']], [])['after_parent'], 'site_key')),
    'jsonb table generated cascade current next53 no-op missing delete key' => static fn (TestRunner $t) => $t->same(5, count($run([], ['missing'])['after_child'])),
    'jsonb table generated cascade current next53 update without delete preserves parent count' => static fn (TestRunner $t) => $t->same(3, count($run([['site_key' => 'site-1', 'new_site_key' => 'site-1-imported']], [])['after_parent'])),
    'jsonb table generated cascade current next53 update without delete preserves child count' => static fn (TestRunner $t) => $t->same(5, count($run([['site_key' => 'site-1', 'new_site_key' => 'site-1-imported']], [])['after_child'])),
    'jsonb table generated cascade current next53 update without delete changes three rows' => static fn (TestRunner $t) => $t->same(3, $run([['site_key' => 'site-1', 'new_site_key' => 'site-1-imported']], [])['changes']),
    'jsonb table generated cascade current next53 same generated key updates parent only' => static fn (TestRunner $t) => $t->same(1, $run([['site_key' => 'site-1', 'new_site_key' => 'site-1']], [])['changes']),
    'jsonb table generated cascade current next53 same generated key does not cascade children' => static fn (TestRunner $t) => $t->same([10, 11, 12, 13, 14], array_column($run([['site_key' => 'site-1', 'new_site_key' => 'site-1']], [])['after_child'], 'meta_id')),
    'jsonb table generated cascade current next53 generic new parent key field is accepted' => static fn (TestRunner $t) => $t->same('site-1-network', $run([['site_key' => 'site-1', 'new_parent_key' => 'site-1-network']], [])['after_parent'][0]['site_key']),
    'jsonb table generated cascade current next53 on update no action leaves child violation' => static function (TestRunner $t) use ($run, $foreignKey): void {
        $fk = $foreignKey;
        $fk['on_update'] = 'NO ACTION';
        $t->same('site-1', $run([['site_key' => 'site-1', 'new_site_key' => 'site-1-imported']], [], $fk)['violations'][0]['missing_parent_key']);
    },
    'jsonb table generated cascade current next53 on update no action still mutates parent' => static function (TestRunner $t) use ($run, $foreignKey): void {
        $fk = $foreignKey;
        $fk['on_update'] = 'NO ACTION';
        $t->same('site-1-imported', $run([['site_key' => 'site-1', 'new_site_key' => 'site-1-imported']], [], $fk)['after_parent'][0]['site_key']);
    },
    'jsonb table generated cascade current next53 on delete no action leaves child violation' => static function (TestRunner $t) use ($run, $foreignKey): void {
        $fk = $foreignKey;
        $fk['on_delete'] = 'NO ACTION';
        $t->same('site-2', $run([], ['site-2'], $fk)['violations'][0]['missing_parent_key']);
    },
    'jsonb table generated cascade current next53 on delete set null clears child key' => static function (TestRunner $t) use ($run, $foreignKey): void {
        $fk = $foreignKey;
        $fk['on_delete'] = 'SET NULL';
        $t->same(null, $run([], ['site-2'], $fk)['after_child'][2]['site_key']);
    },
    'jsonb table generated cascade current next53 on delete set default rewrites child key' => static function (TestRunner $t) use ($run, $foreignKey): void {
        $fk = $foreignKey;
        $fk['on_delete'] = 'SET DEFAULT';
        $fk['default'] = 'network-default';
        $t->same('network-default', $run([], ['site-2'], $fk)['after_child'][2]['site_key']);
    },
    'jsonb table generated cascade current next53 set default records action name' => static function (TestRunner $t) use ($run, $foreignKey): void {
        $fk = $foreignKey;
        $fk['on_delete'] = 'SET DEFAULT';
        $fk['default'] = 'network-default';
        $t->same('cascade-default-child-generated-key', $run([], ['site-2'], $fk)['actions'][1]['action']);
    },
    'jsonb table generated cascade current next53 text json parent source hydrates key' => static function (TestRunner $t) use ($run, $parents): void {
        $textParents = $parents;
        $textParents[0]['key_value'] = '{"site":{"id":"site-1","theme":"text-json"}}';
        $t->same('site-1-imported', $run([['site_key' => 'site-1', 'new_site_key' => 'site-1-imported']], [], null, $textParents)['after_parent'][0]['site_key']);
    },
    'jsonb table generated cascade current next53 text json mutation returns jsonb blob' => static function (TestRunner $t) use ($run, $parents): void {
        $textParents = $parents;
        $textParents[0]['key_value'] = '{"site":{"id":"site-1","theme":"text-json"}}';
        $t->true($run([['site_key' => 'site-1', 'new_site_key' => 'site-1-imported']], [], null, $textParents)['after_parent'][0]['key_value'] instanceof SQLiteBlobValue);
    },
    'jsonb table generated cascade current next53 missing generated path creates null key' => static function (TestRunner $t) use ($run, $parents): void {
        $broken = $parents;
        $broken[0]['key_value'] = new SQLiteBlobValue(SQLiteJsonB::encode(['site' => ['theme' => 'missing']]));
        $t->same(null, $run([], [], null, $broken)['before_parent'][0]['site_key']);
    },
    'jsonb table generated cascade current next53 null generated parent does not satisfy child' => static function (TestRunner $t) use ($run, $parents, $children): void {
        $broken = $parents;
        $broken[0]['key_value'] = new SQLiteBlobValue(SQLiteJsonB::encode(['site' => ['theme' => 'missing']]));
        $t->same('site-1', $run([], [], null, $broken, $children)['violations'][0]['missing_parent_key']);
    },
    'jsonb table generated cascade current next53 missing child key column rejected' => static function (TestRunner $t) use ($run, $children): void {
        $broken = $children;
        unset($broken[0]['site_key']);
        $t->throws(InvalidArgumentException::class, static fn () => $run([], [], null, null, $broken));
    },
    'jsonb table generated cascade current next53 missing parent source column rejected' => static function (TestRunner $t) use ($run, $parents): void {
        $broken = $parents;
        unset($broken[0]['key_value']);
        $t->throws(InvalidArgumentException::class, static fn () => $run([], [], null, $broken));
    },
    'jsonb table generated cascade current next53 malformed json path rejected' => static function (TestRunner $t) use ($run, $foreignKey): void {
        $fk = $foreignKey;
        $fk['json_path'] = '$.site[';
        $t->throws(InvalidArgumentException::class, static fn () => $run([], [], $fk));
    },
    'jsonb table generated cascade current next53 malformed parent column rejected' => static function (TestRunner $t) use ($run, $foreignKey): void {
        $fk = $foreignKey;
        $fk['parent_column'] = 'bad-column';
        $t->throws(InvalidArgumentException::class, static fn () => $run([], [], $fk));
    },
    'jsonb table generated cascade current next53 malformed source column rejected' => static function (TestRunner $t) use ($run, $foreignKey): void {
        $fk = $foreignKey;
        $fk['source_column'] = 'bad-column';
        $t->throws(InvalidArgumentException::class, static fn () => $run([], [], $fk));
    },
    'jsonb table generated cascade current next53 malformed child column rejected' => static function (TestRunner $t) use ($run, $foreignKey): void {
        $fk = $foreignKey;
        $fk['child_column'] = 'bad-column';
        $t->throws(InvalidArgumentException::class, static fn () => $run([], [], $fk));
    },
    'jsonb table generated cascade current next53 unsupported update action rejected' => static function (TestRunner $t) use ($run, $foreignKey): void {
        $fk = $foreignKey;
        $fk['on_update'] = 'SET NULL';
        $t->throws(InvalidArgumentException::class, static fn () => $run([], [], $fk));
    },
    'jsonb table generated cascade current next53 unsupported delete action rejected' => static function (TestRunner $t) use ($run, $foreignKey): void {
        $fk = $foreignKey;
        $fk['on_delete'] = 'RESTRICT';
        $t->throws(InvalidArgumentException::class, static fn () => $run([], [], $fk));
    },
    'jsonb table generated cascade current next53 missing update old key rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([['new_site_key' => 'x']], [])),
    'jsonb table generated cascade current next53 missing update new key rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([['site_key' => 'site-1']], [])),
    'jsonb table generated cascade current next53 malformed source value rejected' => static function (TestRunner $t) use ($run, $parents): void {
        $broken = $parents;
        $broken[0]['key_value'] = 123;
        $t->throws(InvalidArgumentException::class, static fn () => $run([], [], null, $broken));
    },
    'jsonb table generated cascade current next53 records normalized foreign key metadata' => static fn (TestRunner $t) => $t->same(['site_key', 'key_value', '$.site.id', 'site_key', 'setting_id', 'cascade', 'cascade'], array_values(array_intersect_key($run()['foreign_key'], array_flip(['parent_column', 'source_column', 'json_path', 'child_column', 'rowid_column', 'on_update', 'on_delete'])))),
];
