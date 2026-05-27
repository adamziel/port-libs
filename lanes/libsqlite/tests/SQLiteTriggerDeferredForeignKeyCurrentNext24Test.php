<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerDeferredForeignKeyPlan;

$tables = [
    'wp_posts' => [
        ['id' => 1, 'post_title' => 'Plugin settings'],
        ['id' => 2, 'post_title' => 'Theme settings'],
    ],
    'wp_postmeta' => [
        ['meta_id' => 10, 'post_id' => 1, 'meta_key' => '_source'],
        ['meta_id' => 11, 'post_id' => 2, 'meta_key' => '_source'],
    ],
    'wp_import_audit' => [],
];
$foreignKeys = [
    [
        'name' => 'postmeta_post',
        'parent_table' => 'wp_posts',
        'parent_key' => 'id',
        'child_table' => 'wp_postmeta',
        'child_key' => 'post_id',
        'on_delete' => 'NO ACTION',
        'deferred' => true,
    ],
    [
        'name' => 'audit_post',
        'parent_table' => 'wp_posts',
        'parent_key' => 'id',
        'child_table' => 'wp_import_audit',
        'child_key' => 'post_id',
        'on_delete' => 'NO ACTION',
        'deferred' => true,
    ],
];
$triggerInsert = [
    'operation' => 'insert',
    'table' => 'wp_import_audit',
    'trigger' => 'after_post_import',
    'row' => ['audit_id' => 100, 'post_id' => 3, 'message' => 'queued missing post'],
];
$insertMissingAudit = static fn (): array => SQLiteTriggerDeferredForeignKeyPlan::run($tables, [$triggerInsert], $foreignKeys);
$repairThenInsert = static fn (): array => SQLiteTriggerDeferredForeignKeyPlan::run($tables, [
    ['operation' => 'insert', 'table' => 'wp_posts', 'row' => ['id' => 3, 'post_title' => 'Imported page']],
    $triggerInsert,
], $foreignKeys);
$deleteParent = static fn (?array $source = null, ?array $fks = null): array => SQLiteTriggerDeferredForeignKeyPlan::run(
    $source ?? $tables,
    [['operation' => 'delete', 'table' => 'wp_posts', 'trigger' => 'after_post_delete', 'match' => ['id' => 1]]],
    $fks ?? $foreignKeys,
);

$tests = [
    'trigger deferred fk records inserted trigger row' => static function (TestRunner $t) use ($insertMissingAudit): void {
        $t->same([100], array_column($insertMissingAudit()['tables']['wp_import_audit'], 'audit_id'));
    },
    'trigger deferred fk preserves existing parent rows before commit check' => static function (TestRunner $t) use ($insertMissingAudit): void {
        $t->same([1, 2], array_column($insertMissingAudit()['tables']['wp_posts'], 'id'));
    },
    'trigger deferred fk records trigger event name' => static function (TestRunner $t) use ($insertMissingAudit): void {
        $t->same('after_post_import', $insertMissingAudit()['events'][0]['trigger']);
    },
    'trigger deferred fk records inserted event action' => static function (TestRunner $t) use ($insertMissingAudit): void {
        $t->same('insert-row', $insertMissingAudit()['events'][0]['action']);
    },
    'trigger deferred fk queues child check for trigger insert' => static function (TestRunner $t) use ($insertMissingAudit): void {
        $t->same('child-check', $insertMissingAudit()['deferred'][0]['kind']);
    },
    'trigger deferred fk queues named audit foreign key' => static function (TestRunner $t) use ($insertMissingAudit): void {
        $t->same('audit_post', $insertMissingAudit()['deferred'][0]['foreign_key']);
    },
    'trigger deferred fk records missing parent key' => static function (TestRunner $t) use ($insertMissingAudit): void {
        $t->same(3, $insertMissingAudit()['deferred'][0]['parent_key']);
    },
    'trigger deferred fk records deferred flag' => static function (TestRunner $t) use ($insertMissingAudit): void {
        $t->same(true, $insertMissingAudit()['deferred'][0]['deferred']);
    },
    'trigger deferred fk reports commit blocked for missing parent' => static function (TestRunner $t) use ($insertMissingAudit): void {
        $t->same('commit-blocked', $insertMissingAudit()['commit_status']);
    },
    'trigger deferred fk records missing parent violation' => static function (TestRunner $t) use ($insertMissingAudit): void {
        $t->same('missing-parent-at-commit', $insertMissingAudit()['violations'][0]['reason']);
    },
    'trigger deferred fk violation keeps trigger name' => static function (TestRunner $t) use ($insertMissingAudit): void {
        $t->same('after_post_import', $insertMissingAudit()['violations'][0]['trigger']);
    },
    'trigger deferred fk counts trigger insert change' => static function (TestRunner $t) use ($insertMissingAudit): void {
        $t->same(1, $insertMissingAudit()['changes']);
    },
    'trigger deferred fk repaired parent commits cleanly' => static function (TestRunner $t) use ($repairThenInsert): void {
        $t->same('commit-ok', $repairThenInsert()['commit_status']);
    },
    'trigger deferred fk repaired parent has no violations' => static function (TestRunner $t) use ($repairThenInsert): void {
        $t->same([], $repairThenInsert()['violations']);
    },
    'trigger deferred fk repaired parent keeps new post' => static function (TestRunner $t) use ($repairThenInsert): void {
        $t->same([1, 2, 3], array_column($repairThenInsert()['tables']['wp_posts'], 'id'));
    },
    'trigger deferred fk repaired parent records two events' => static function (TestRunner $t) use ($repairThenInsert): void {
        $t->same(['insert-row', 'insert-row'], array_column($repairThenInsert()['events'], 'action'));
    },
    'trigger deferred fk repaired parent counts both changes' => static function (TestRunner $t) use ($repairThenInsert): void {
        $t->same(2, $repairThenInsert()['changes']);
    },
    'trigger deferred fk null child key skips deferred check' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $result = SQLiteTriggerDeferredForeignKeyPlan::run($tables, [['operation' => 'insert', 'table' => 'wp_import_audit', 'row' => ['audit_id' => 101, 'post_id' => null]]], $foreignKeys);
        $t->same([], $result['deferred']);
    },
    'trigger deferred fk null child key commits ok' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $result = SQLiteTriggerDeferredForeignKeyPlan::run($tables, [['operation' => 'insert', 'table' => 'wp_import_audit', 'row' => ['audit_id' => 101, 'post_id' => null]]], $foreignKeys);
        $t->same('commit-ok', $result['commit_status']);
    },
    'trigger deferred fk update queues changed child check' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $result = SQLiteTriggerDeferredForeignKeyPlan::run($tables, [['operation' => 'update', 'table' => 'wp_postmeta', 'trigger' => 'after_meta_relink', 'match' => ['meta_id' => 10], 'set' => ['post_id' => 9]]], $foreignKeys);
        $t->same('child-check', $result['deferred'][0]['kind']);
    },
    'trigger deferred fk update records before row image' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $result = SQLiteTriggerDeferredForeignKeyPlan::run($tables, [['operation' => 'update', 'table' => 'wp_postmeta', 'trigger' => 'after_meta_relink', 'match' => ['meta_id' => 10], 'set' => ['post_id' => 9]]], $foreignKeys);
        $t->same(1, $result['events'][0]['before']['post_id']);
    },
    'trigger deferred fk update commits blocked for missing parent' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $result = SQLiteTriggerDeferredForeignKeyPlan::run($tables, [['operation' => 'update', 'table' => 'wp_postmeta', 'match' => ['meta_id' => 10], 'set' => ['post_id' => 9]]], $foreignKeys);
        $t->same('commit-blocked', $result['commit_status']);
    },
    'trigger deferred fk update keeps changed child value' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $result = SQLiteTriggerDeferredForeignKeyPlan::run($tables, [['operation' => 'update', 'table' => 'wp_postmeta', 'match' => ['meta_id' => 10], 'set' => ['post_id' => 9]]], $foreignKeys);
        $t->same([9, 2], array_column($result['tables']['wp_postmeta'], 'post_id'));
    },
    'trigger deferred fk update to existing parent commits ok' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $result = SQLiteTriggerDeferredForeignKeyPlan::run($tables, [['operation' => 'update', 'table' => 'wp_postmeta', 'match' => ['meta_id' => 10], 'set' => ['post_id' => 2]]], $foreignKeys);
        $t->same('commit-ok', $result['commit_status']);
    },
    'trigger deferred fk update to existing parent has no violations' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $result = SQLiteTriggerDeferredForeignKeyPlan::run($tables, [['operation' => 'update', 'table' => 'wp_postmeta', 'match' => ['meta_id' => 10], 'set' => ['post_id' => 2]]], $foreignKeys);
        $t->same([], $result['violations']);
    },
    'trigger deferred fk delete parent queues parent delete check' => static function (TestRunner $t) use ($deleteParent): void {
        $t->same('parent-delete-check', $deleteParent()['deferred'][0]['kind']);
    },
    'trigger deferred fk delete parent records deleted key' => static function (TestRunner $t) use ($deleteParent): void {
        $t->same(1, $deleteParent()['deferred'][0]['parent_key']);
    },
    'trigger deferred fk delete parent reports referencing child violation' => static function (TestRunner $t) use ($deleteParent): void {
        $t->same('referenced-parent-deleted-at-commit', $deleteParent()['violations'][0]['reason']);
    },
    'trigger deferred fk delete parent removes row before commit' => static function (TestRunner $t) use ($deleteParent): void {
        $t->same([2], array_column($deleteParent()['tables']['wp_posts'], 'id'));
    },
    'trigger deferred fk delete parent preserves child row until commit rejection' => static function (TestRunner $t) use ($deleteParent): void {
        $t->same([10, 11], array_column($deleteParent()['tables']['wp_postmeta'], 'meta_id'));
    },
    'trigger deferred fk delete unreferenced parent commits ok' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $source = $tables;
        $source['wp_posts'][] = ['id' => 4, 'post_title' => 'Unused'];
        $result = SQLiteTriggerDeferredForeignKeyPlan::run($source, [['operation' => 'delete', 'table' => 'wp_posts', 'match' => ['id' => 4]]], $foreignKeys);
        $t->same('commit-ok', $result['commit_status']);
    },
    'trigger deferred fk delete unreferenced parent leaves two posts' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $source = $tables;
        $source['wp_posts'][] = ['id' => 4, 'post_title' => 'Unused'];
        $result = SQLiteTriggerDeferredForeignKeyPlan::run($source, [['operation' => 'delete', 'table' => 'wp_posts', 'match' => ['id' => 4]]], $foreignKeys);
        $t->same([1, 2], array_column($result['tables']['wp_posts'], 'id'));
    },
    'trigger deferred fk restrict blocks parent delete immediately' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $fks = $foreignKeys;
        $fks[0]['on_delete'] = 'RESTRICT';
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerDeferredForeignKeyPlan::run($tables, [['operation' => 'delete', 'table' => 'wp_posts', 'match' => ['id' => 1]]], $fks));
    },
    'trigger deferred fk restrict permits unreferenced delete' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $source = $tables;
        $source['wp_posts'][] = ['id' => 4, 'post_title' => 'Unused'];
        $fks = $foreignKeys;
        $fks[0]['on_delete'] = 'RESTRICT';
        $result = SQLiteTriggerDeferredForeignKeyPlan::run($source, [['operation' => 'delete', 'table' => 'wp_posts', 'match' => ['id' => 4]]], $fks);
        $t->same('commit-ok', $result['commit_status']);
    },
    'trigger deferred fk rollback preview restores tables' => static function (TestRunner $t) use ($tables, $triggerInsert, $foreignKeys): void {
        $preview = SQLiteTriggerDeferredForeignKeyPlan::rollbackPreview($tables, [$triggerInsert], $foreignKeys);
        $t->same([], $preview['rollback']['tables']['wp_import_audit']);
    },
    'trigger deferred fk rollback preview clears deferred queue' => static function (TestRunner $t) use ($tables, $triggerInsert, $foreignKeys): void {
        $preview = SQLiteTriggerDeferredForeignKeyPlan::rollbackPreview($tables, [$triggerInsert], $foreignKeys);
        $t->same([], $preview['rollback']['deferred']);
    },
    'trigger deferred fk rollback preview clears violations' => static function (TestRunner $t) use ($tables, $triggerInsert, $foreignKeys): void {
        $preview = SQLiteTriggerDeferredForeignKeyPlan::rollbackPreview($tables, [$triggerInsert], $foreignKeys);
        $t->same([], $preview['rollback']['violations']);
    },
    'trigger deferred fk rollback preview reports rolled back' => static function (TestRunner $t) use ($tables, $triggerInsert, $foreignKeys): void {
        $preview = SQLiteTriggerDeferredForeignKeyPlan::rollbackPreview($tables, [$triggerInsert], $foreignKeys);
        $t->same('rolled-back', $preview['rollback']['commit_status']);
    },
    'trigger deferred fk rollback preview keeps after image separate' => static function (TestRunner $t) use ($tables, $triggerInsert, $foreignKeys): void {
        $preview = SQLiteTriggerDeferredForeignKeyPlan::rollbackPreview($tables, [$triggerInsert], $foreignKeys);
        $t->same([100], array_column($preview['after']['wp_import_audit'], 'audit_id'));
    },
    'trigger deferred fk multi statement preserves statement indexes' => static function (TestRunner $t) use ($repairThenInsert): void {
        $t->same([1], array_column($repairThenInsert()['deferred'], 'statement'));
    },
    'trigger deferred fk multi trigger checks keep operation names' => static function (TestRunner $t) use ($repairThenInsert): void {
        $t->same(['insert'], array_column($repairThenInsert()['deferred'], 'operation'));
    },
    'trigger deferred fk only queues matching child table' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $result = SQLiteTriggerDeferredForeignKeyPlan::run($tables, [['operation' => 'insert', 'table' => 'wp_posts', 'row' => ['id' => 5, 'post_title' => 'Standalone']]], $foreignKeys);
        $t->same([], $result['deferred']);
    },
    'trigger deferred fk missing update match is no-op by default' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $result = SQLiteTriggerDeferredForeignKeyPlan::run($tables, [['operation' => 'update', 'table' => 'wp_postmeta', 'match' => ['meta_id' => 99], 'set' => ['post_id' => 9]]], $foreignKeys);
        $t->same(0, $result['changes']);
    },
    'trigger deferred fk required update match raises' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerDeferredForeignKeyPlan::run($tables, [['operation' => 'update', 'table' => 'wp_postmeta', 'match' => ['meta_id' => 99], 'set' => ['post_id' => 9], 'require_match' => true]], $foreignKeys));
    },
    'trigger deferred fk rejects unsupported operation' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerDeferredForeignKeyPlan::run($tables, [['operation' => 'replace', 'table' => 'wp_posts', 'row' => ['id' => 9]]], $foreignKeys));
    },
    'trigger deferred fk rejects malformed trigger name' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerDeferredForeignKeyPlan::run($tables, [['operation' => 'insert', 'table' => 'wp_import_audit', 'trigger' => 'bad-trigger', 'row' => ['audit_id' => 9, 'post_id' => 1]]], $foreignKeys));
    },
    'trigger deferred fk rejects missing statement table' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerDeferredForeignKeyPlan::run($tables, [['operation' => 'insert', 'table' => 'missing', 'row' => ['id' => 1]]], $foreignKeys));
    },
    'trigger deferred fk rejects malformed parent table' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $fks = $foreignKeys;
        $fks[0]['parent_table'] = 'bad-table';
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerDeferredForeignKeyPlan::run($tables, [], $fks));
    },
    'trigger deferred fk rejects unsupported delete action' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $fks = $foreignKeys;
        $fks[0]['on_delete'] = 'CASCADE';
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerDeferredForeignKeyPlan::run($tables, [], $fks));
    },
    'trigger deferred fk rejects missing child column' => static function (TestRunner $t) use ($tables, $foreignKeys): void {
        $broken = $tables;
        unset($broken['wp_postmeta'][0]['post_id']);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerDeferredForeignKeyPlan::run($broken, [['operation' => 'update', 'table' => 'wp_postmeta', 'match' => ['meta_id' => 10], 'set' => ['meta_key' => '_broken']]], $foreignKeys));
    },
    'trigger deferred fk table result keys are sorted' => static function (TestRunner $t) use ($insertMissingAudit): void {
        $t->same(['wp_import_audit', 'wp_postmeta', 'wp_posts'], array_keys($insertMissingAudit()['tables']));
    },
    'trigger deferred fk event row carries payload text' => static function (TestRunner $t) use ($insertMissingAudit): void {
        $t->same('queued missing post', $insertMissingAudit()['events'][0]['row']['message']);
    },
    'trigger deferred fk violation carries child table' => static function (TestRunner $t) use ($insertMissingAudit): void {
        $t->same('wp_import_audit', $insertMissingAudit()['violations'][0]['child_table']);
    },
    'trigger deferred fk violation carries parent table' => static function (TestRunner $t) use ($insertMissingAudit): void {
        $t->same('wp_posts', $insertMissingAudit()['violations'][0]['parent_table']);
    },
];

return $tests;
