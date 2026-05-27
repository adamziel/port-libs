<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaForeignKeyCheckDeferredPlan;

$tables = [
    'wp_posts' => [
        ['rowid' => 1, 'ID' => 1, 'post_name' => 'hello-world'],
        ['rowid' => 2, 'ID' => 2, 'post_name' => 'sample-page'],
    ],
    'wp_postmeta' => [
        ['rowid' => 10, 'meta_id' => 10, 'post_id' => 1, 'meta_key' => '_edit_lock'],
        ['rowid' => 11, 'meta_id' => 11, 'post_id' => 2, 'meta_key' => '_thumbnail_id'],
    ],
    'wp_term_taxonomy' => [
        ['rowid' => 20, 'term_id' => 5, 'taxonomy' => 'category'],
        ['rowid' => 21, 'term_id' => 7, 'taxonomy' => 'post_tag'],
    ],
    'wp_term_relationships' => [
        ['rowid' => 30, 'object_id' => 1, 'term_id' => 5, 'taxonomy' => 'category'],
    ],
];

$foreignKeys = [
    ['id' => 0, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => ['post_id' => 'ID']],
    ['id' => 1, 'table' => 'wp_term_relationships', 'parent' => 'wp_posts', 'columns' => ['object_id' => 'ID']],
    ['id' => 2, 'table' => 'wp_term_relationships', 'parent' => 'wp_term_taxonomy', 'columns' => ['term_id' => 'term_id', 'taxonomy' => 'taxonomy']],
];

$plan = static fn (array $operations): array => SQLitePragmaForeignKeyCheckDeferredPlan::plan($tables, $foreignKeys, $operations);

$deferredInsertRepair = static fn (): array => $plan([
    ['op' => 'insert', 'table' => 'wp_postmeta', 'row' => ['rowid' => 12, 'meta_id' => 12, 'post_id' => 99, 'meta_key' => '_missing']],
    ['op' => 'check', 'label' => 'after_child_insert'],
    ['op' => 'insert', 'table' => 'wp_posts', 'row' => ['rowid' => 99, 'ID' => 99, 'post_name' => 'imported']],
    ['op' => 'check', 'label' => 'after_parent_repair'],
    ['op' => 'commit'],
]);

$savepointRollback = static fn (): array => $plan([
    ['op' => 'savepoint', 'name' => 'import_batch'],
    ['op' => 'delete', 'table' => 'wp_posts', 'where' => ['ID' => 1]],
    ['op' => 'check', 'label' => 'after_parent_delete'],
    ['op' => 'rollback_to', 'name' => 'import_batch'],
    ['op' => 'check', 'label' => 'after_rollback_to'],
    ['op' => 'release', 'name' => 'import_batch'],
    ['op' => 'commit'],
]);

$targeted = static fn (): array => $plan([
    ['op' => 'insert', 'table' => 'wp_postmeta', 'row' => ['rowid' => 13, 'meta_id' => 13, 'post_id' => 404, 'meta_key' => '_orphan']],
    ['op' => 'insert', 'table' => 'wp_term_relationships', 'row' => ['rowid' => 31, 'object_id' => 2, 'term_id' => 7, 'taxonomy' => 'missing']],
    ['op' => 'check', 'label' => 'all_current'],
    ['op' => 'check', 'label' => 'postmeta_only', 'table' => 'wp_postmeta'],
    ['op' => 'check', 'label' => 'relationships_only', 'table' => 'wp_term_relationships'],
]);

$compositeRepair = static fn (): array => $plan([
    ['op' => 'insert', 'table' => 'wp_term_relationships', 'row' => ['rowid' => 32, 'object_id' => 2, 'term_id' => 9, 'taxonomy' => 'category']],
    ['op' => 'check', 'label' => 'missing_composite'],
    ['op' => 'insert', 'table' => 'wp_term_taxonomy', 'row' => ['rowid' => 22, 'term_id' => 9, 'taxonomy' => 'category']],
    ['op' => 'check', 'label' => 'composite_repaired'],
    ['op' => 'commit'],
]);

$updateRepair = static fn (): array => $plan([
    ['op' => 'update', 'table' => 'wp_postmeta', 'where' => ['meta_id' => 11], 'set' => ['post_id' => 707]],
    ['op' => 'check', 'label' => 'after_bad_update'],
    ['op' => 'update', 'table' => 'wp_postmeta', 'where' => ['meta_id' => 11], 'set' => ['post_id' => 2]],
    ['op' => 'check', 'label' => 'after_update_repair'],
    ['op' => 'commit'],
]);

$nestedSavepoint = static fn (): array => $plan([
    ['op' => 'savepoint', 'name' => 'outer_batch'],
    ['op' => 'insert', 'table' => 'wp_postmeta', 'row' => ['rowid' => 14, 'meta_id' => 14, 'post_id' => 808, 'meta_key' => '_outer_bad']],
    ['op' => 'savepoint', 'name' => 'inner_batch'],
    ['op' => 'insert', 'table' => 'wp_postmeta', 'row' => ['rowid' => 15, 'meta_id' => 15, 'post_id' => 909, 'meta_key' => '_inner_bad']],
    ['op' => 'check', 'label' => 'both_bad'],
    ['op' => 'rollback_to', 'name' => 'inner_batch'],
    ['op' => 'check', 'label' => 'outer_bad_only'],
    ['op' => 'insert', 'table' => 'wp_posts', 'row' => ['rowid' => 808, 'ID' => 808, 'post_name' => 'outer-repair']],
    ['op' => 'check', 'label' => 'outer_repaired'],
    ['op' => 'release', 'name' => 'inner_batch'],
    ['op' => 'release', 'name' => 'outer_batch'],
    ['op' => 'commit'],
]);

$tests = [
    'pragma foreign key check deferred current reports transient insert violation count' => static function (TestRunner $t) use ($deferredInsertRepair): void {
        $t->same(1, $deferredInsertRepair()['snapshots']['after_child_insert']['deferred_violations']);
    },
    'pragma foreign key check deferred current reports transient insert table' => static function (TestRunner $t) use ($deferredInsertRepair): void {
        $t->same('wp_postmeta', $deferredInsertRepair()['snapshots']['after_child_insert']['rows'][0]['table']);
    },
    'pragma foreign key check deferred current reports transient insert rowid' => static function (TestRunner $t) use ($deferredInsertRepair): void {
        $t->same(12, $deferredInsertRepair()['snapshots']['after_child_insert']['rows'][0]['rowid']);
    },
    'pragma foreign key check deferred current reports transient insert parent' => static function (TestRunner $t) use ($deferredInsertRepair): void {
        $t->same('wp_posts', $deferredInsertRepair()['snapshots']['after_child_insert']['rows'][0]['parent']);
    },
    'pragma foreign key check deferred current reports transient insert fkid' => static function (TestRunner $t) use ($deferredInsertRepair): void {
        $t->same(0, $deferredInsertRepair()['snapshots']['after_child_insert']['rows'][0]['fkid']);
    },
    'pragma foreign key check deferred current clears after parent repair' => static function (TestRunner $t) use ($deferredInsertRepair): void {
        $t->same([], $deferredInsertRepair()['snapshots']['after_parent_repair']['rows']);
    },
    'pragma foreign key check deferred current commit succeeds after repair' => static function (TestRunner $t) use ($deferredInsertRepair): void {
        $t->same(true, $deferredInsertRepair()['committed']);
    },
    'pragma foreign key check deferred current final status ok after repair' => static function (TestRunner $t) use ($deferredInsertRepair): void {
        $t->same('ok', $deferredInsertRepair()['status']);
    },
    'pragma foreign key check deferred current keeps repaired child row' => static function (TestRunner $t) use ($deferredInsertRepair): void {
        $t->same(3, count($deferredInsertRepair()['tables']['wp_postmeta']));
    },
    'pragma foreign key check deferred current keeps repaired parent row' => static function (TestRunner $t) use ($deferredInsertRepair): void {
        $t->same(99, $deferredInsertRepair()['tables']['wp_posts'][2]['ID']);
    },
    'pragma foreign key check deferred current savepoint delete reports two violations' => static function (TestRunner $t) use ($savepointRollback): void {
        $t->same(2, $savepointRollback()['snapshots']['after_parent_delete']['deferred_violations']);
    },
    'pragma foreign key check deferred current savepoint delete reports postmeta first' => static function (TestRunner $t) use ($savepointRollback): void {
        $t->same('wp_postmeta', $savepointRollback()['snapshots']['after_parent_delete']['rows'][0]['table']);
    },
    'pragma foreign key check deferred current savepoint delete reports postmeta rowid' => static function (TestRunner $t) use ($savepointRollback): void {
        $t->same(10, $savepointRollback()['snapshots']['after_parent_delete']['rows'][0]['rowid']);
    },
    'pragma foreign key check deferred current savepoint delete reports relationship second' => static function (TestRunner $t) use ($savepointRollback): void {
        $t->same('wp_term_relationships', $savepointRollback()['snapshots']['after_parent_delete']['rows'][1]['table']);
    },
    'pragma foreign key check deferred current savepoint delete reports relationship rowid' => static function (TestRunner $t) use ($savepointRollback): void {
        $t->same(30, $savepointRollback()['snapshots']['after_parent_delete']['rows'][1]['rowid']);
    },
    'pragma foreign key check deferred current rollback clears rows' => static function (TestRunner $t) use ($savepointRollback): void {
        $t->same([], $savepointRollback()['snapshots']['after_rollback_to']['rows']);
    },
    'pragma foreign key check deferred current rollback restores parent count' => static function (TestRunner $t) use ($savepointRollback): void {
        $t->same(2, count($savepointRollback()['snapshots']['after_rollback_to']['tables']['wp_posts']));
    },
    'pragma foreign key check deferred current release then commit succeeds' => static function (TestRunner $t) use ($savepointRollback): void {
        $t->same(true, $savepointRollback()['committed']);
    },
    'pragma foreign key check deferred current targeted all reports two rows' => static function (TestRunner $t) use ($targeted): void {
        $t->same(2, $targeted()['snapshots']['all_current']['deferred_violations']);
    },
    'pragma foreign key check deferred current targeted all rowids preserve fk order' => static function (TestRunner $t) use ($targeted): void {
        $t->same([13, 31], array_column($targeted()['snapshots']['all_current']['rows'], 'rowid'));
    },
    'pragma foreign key check deferred current targeted postmeta reports only postmeta' => static function (TestRunner $t) use ($targeted): void {
        $t->same(['wp_postmeta'], array_column($targeted()['snapshots']['postmeta_only']['rows'], 'table'));
    },
    'pragma foreign key check deferred current targeted postmeta keeps rowid' => static function (TestRunner $t) use ($targeted): void {
        $t->same([13], array_column($targeted()['snapshots']['postmeta_only']['rows'], 'rowid'));
    },
    'pragma foreign key check deferred current targeted relationship reports only relationship' => static function (TestRunner $t) use ($targeted): void {
        $t->same(['wp_term_relationships'], array_column($targeted()['snapshots']['relationships_only']['rows'], 'table'));
    },
    'pragma foreign key check deferred current targeted relationship keeps composite fkid' => static function (TestRunner $t) use ($targeted): void {
        $t->same([2], array_column($targeted()['snapshots']['relationships_only']['rows'], 'fkid'));
    },
    'pragma foreign key check deferred current targeted final status records violations' => static function (TestRunner $t) use ($targeted): void {
        $t->same('deferred-violations', $targeted()['status']);
    },
    'pragma foreign key check deferred current targeted final violation count' => static function (TestRunner $t) use ($targeted): void {
        $t->same(2, $targeted()['deferred_violations']);
    },
    'pragma foreign key check deferred current composite reports missing composite row' => static function (TestRunner $t) use ($compositeRepair): void {
        $t->same([32], array_column($compositeRepair()['snapshots']['missing_composite']['rows'], 'rowid'));
    },
    'pragma foreign key check deferred current composite reports parent table' => static function (TestRunner $t) use ($compositeRepair): void {
        $t->same('wp_term_taxonomy', $compositeRepair()['snapshots']['missing_composite']['rows'][0]['parent']);
    },
    'pragma foreign key check deferred current composite reports fkid' => static function (TestRunner $t) use ($compositeRepair): void {
        $t->same(2, $compositeRepair()['snapshots']['missing_composite']['rows'][0]['fkid']);
    },
    'pragma foreign key check deferred current composite clears after parent insert' => static function (TestRunner $t) use ($compositeRepair): void {
        $t->same([], $compositeRepair()['snapshots']['composite_repaired']['rows']);
    },
    'pragma foreign key check deferred current composite commit succeeds' => static function (TestRunner $t) use ($compositeRepair): void {
        $t->same(true, $compositeRepair()['committed']);
    },
    'pragma foreign key check deferred current composite parent table grows' => static function (TestRunner $t) use ($compositeRepair): void {
        $t->same(3, count($compositeRepair()['tables']['wp_term_taxonomy']));
    },
    'pragma foreign key check deferred current update reports violation' => static function (TestRunner $t) use ($updateRepair): void {
        $t->same(1, $updateRepair()['snapshots']['after_bad_update']['deferred_violations']);
    },
    'pragma foreign key check deferred current update reports updated rowid' => static function (TestRunner $t) use ($updateRepair): void {
        $t->same(11, $updateRepair()['snapshots']['after_bad_update']['rows'][0]['rowid']);
    },
    'pragma foreign key check deferred current update reports fkid zero' => static function (TestRunner $t) use ($updateRepair): void {
        $t->same(0, $updateRepair()['snapshots']['after_bad_update']['rows'][0]['fkid']);
    },
    'pragma foreign key check deferred current update repair clears violation' => static function (TestRunner $t) use ($updateRepair): void {
        $t->same([], $updateRepair()['snapshots']['after_update_repair']['rows']);
    },
    'pragma foreign key check deferred current update repair restores child value' => static function (TestRunner $t) use ($updateRepair): void {
        $t->same(2, $updateRepair()['tables']['wp_postmeta'][1]['post_id']);
    },
    'pragma foreign key check deferred current unrepaired commit fails' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan([
            ['op' => 'insert', 'table' => 'wp_postmeta', 'row' => ['rowid' => 99, 'meta_id' => 99, 'post_id' => 777, 'meta_key' => '_bad']],
            ['op' => 'commit'],
        ]));
    },
    'pragma foreign key check deferred current rejects unknown rollback target' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan([
            ['op' => 'rollback_to', 'name' => 'missing'],
        ]));
    },
    'pragma foreign key check deferred current rejects unknown release target' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan([
            ['op' => 'release', 'name' => 'missing'],
        ]));
    },
    'pragma foreign key check deferred current rejects malformed operation' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan([
            ['table' => 'wp_postmeta'],
        ]));
    },
    'pragma foreign key check deferred current rejects unsupported operation' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan([
            ['op' => 'vacuum'],
        ]));
    },
    'pragma foreign key check deferred current rejects malformed table name' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan([
            ['op' => 'insert', 'table' => 'bad-name', 'row' => ['rowid' => 1]],
        ]));
    },
    'pragma foreign key check deferred current rejects malformed row column' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan([
            ['op' => 'insert', 'table' => 'wp_postmeta', 'row' => ['bad-name' => 1]],
        ]));
    },
    'pragma foreign key check deferred current rejects malformed check label' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan([
            ['op' => 'check', 'label' => 'bad-name'],
        ]));
    },
    'pragma foreign key check deferred current rejects malformed check target' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan([
            ['op' => 'check', 'label' => 'ok', 'table' => 'bad-name'],
        ]));
    },
    'pragma foreign key check deferred current missing delete target is harmless' => static function (TestRunner $t) use ($plan): void {
        $result = $plan([
            ['op' => 'delete', 'table' => 'wp_posts', 'where' => ['ID' => 999]],
            ['op' => 'check', 'label' => 'after_noop_delete'],
        ]);
        $t->same([], $result['snapshots']['after_noop_delete']['rows']);
    },
    'pragma foreign key check deferred current update with no matches is harmless' => static function (TestRunner $t) use ($plan): void {
        $result = $plan([
            ['op' => 'update', 'table' => 'wp_postmeta', 'where' => ['meta_id' => 999], 'set' => ['post_id' => 777]],
            ['op' => 'check', 'label' => 'after_noop_update'],
        ]);
        $t->same([], $result['snapshots']['after_noop_update']['rows']);
    },
    'pragma foreign key check deferred current nested savepoint reports both violations' => static function (TestRunner $t) use ($nestedSavepoint): void {
        $t->same(2, $nestedSavepoint()['snapshots']['both_bad']['deferred_violations']);
    },
    'pragma foreign key check deferred current nested rollback preserves outer violation' => static function (TestRunner $t) use ($nestedSavepoint): void {
        $t->same([14], array_column($nestedSavepoint()['snapshots']['outer_bad_only']['rows'], 'rowid'));
    },
    'pragma foreign key check deferred current nested repair clears outer violation' => static function (TestRunner $t) use ($nestedSavepoint): void {
        $t->same([], $nestedSavepoint()['snapshots']['outer_repaired']['rows']);
    },
    'pragma foreign key check deferred current nested savepoint commits after repair' => static function (TestRunner $t) use ($nestedSavepoint): void {
        $t->same(true, $nestedSavepoint()['committed']);
    },
];

return $tests;
