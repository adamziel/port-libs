<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerDeferrableFkSavepointPlan;

$tables = [
    'wp_posts' => [
        ['id' => 1, 'post_title' => 'Home'],
        ['id' => 2, 'post_title' => 'Plugin settings'],
    ],
    'wp_postmeta' => [
        ['meta_id' => 10, 'post_id' => 1, 'meta_key' => '_source'],
        ['meta_id' => 11, 'post_id' => 2, 'meta_key' => '_source'],
    ],
    'wp_import_audit' => [],
];

$foreignKeys = [
    ['name' => 'meta_post', 'parent_table' => 'wp_posts', 'parent_key' => 'id', 'child_table' => 'wp_postmeta', 'child_key' => 'post_id', 'deferred' => true],
    ['name' => 'audit_post', 'parent_table' => 'wp_posts', 'parent_key' => 'id', 'child_table' => 'wp_import_audit', 'child_key' => 'post_id', 'deferred' => false],
];

$rollbackChild = static fn (): array => SQLiteTriggerDeferrableFkSavepointPlan::run($tables, [
    ['operation' => 'savepoint', 'name' => 'wp_import'],
    ['operation' => 'insert', 'table' => 'wp_postmeta', 'trigger' => 'after_import_meta', 'row' => ['meta_id' => 20, 'post_id' => 99, 'meta_key' => '_import']],
    ['operation' => 'savepoint', 'name' => 'plugin_meta'],
    ['operation' => 'insert', 'table' => 'wp_postmeta', 'trigger' => 'after_plugin_meta', 'row' => ['meta_id' => 21, 'post_id' => 100, 'meta_key' => '_plugin']],
    ['operation' => 'rollback_to', 'name' => 'plugin_meta'],
    ['operation' => 'insert', 'table' => 'wp_posts', 'row' => ['id' => 99, 'post_title' => 'Imported page']],
    ['operation' => 'release', 'name' => 'plugin_meta'],
    ['operation' => 'release', 'name' => 'wp_import'],
], $foreignKeys);

$releaseViolation = static fn (): array => SQLiteTriggerDeferrableFkSavepointPlan::run($tables, [
    ['operation' => 'savepoint', 'name' => 'wp_import'],
    ['operation' => 'insert', 'table' => 'wp_postmeta', 'trigger' => 'after_plugin_meta', 'row' => ['meta_id' => 22, 'post_id' => 100, 'meta_key' => '_plugin']],
    ['operation' => 'release', 'name' => 'wp_import'],
], $foreignKeys);

$parentDeleteRollback = static fn (): array => SQLiteTriggerDeferrableFkSavepointPlan::run($tables, [
    ['operation' => 'savepoint', 'name' => 'delete_pass'],
    ['operation' => 'delete', 'table' => 'wp_posts', 'trigger' => 'after_post_delete', 'match' => ['id' => 1]],
    ['operation' => 'rollback_to', 'name' => 'delete_pass'],
    ['operation' => 'release', 'name' => 'delete_pass'],
], $foreignKeys);

$parentDeleteViolation = static fn (): array => SQLiteTriggerDeferrableFkSavepointPlan::run($tables, [
    ['operation' => 'savepoint', 'name' => 'delete_pass'],
    ['operation' => 'delete', 'table' => 'wp_posts', 'trigger' => 'after_post_delete', 'match' => ['id' => 1]],
    ['operation' => 'release', 'name' => 'delete_pass'],
], $foreignKeys);

$deferAllImmediate = static fn (): array => SQLiteTriggerDeferrableFkSavepointPlan::run($tables, [
    ['operation' => 'savepoint', 'name' => 'audit_pass'],
    ['operation' => 'insert', 'table' => 'wp_import_audit', 'trigger' => 'after_audit_insert', 'row' => ['audit_id' => 30, 'post_id' => 77, 'message' => 'queued']],
    ['operation' => 'insert', 'table' => 'wp_posts', 'row' => ['id' => 77, 'post_title' => 'Deferred audit parent']],
    ['operation' => 'release', 'name' => 'audit_pass'],
], $foreignKeys, true);

$immediateViolation = static fn (): array => SQLiteTriggerDeferrableFkSavepointPlan::run($tables, [
    ['operation' => 'insert', 'table' => 'wp_import_audit', 'trigger' => 'after_audit_insert', 'row' => ['audit_id' => 31, 'post_id' => 78, 'message' => 'bad']],
], $foreignKeys);

$updateRepair = static fn (): array => SQLiteTriggerDeferrableFkSavepointPlan::run($tables, [
    ['operation' => 'savepoint', 'name' => 'meta_relink'],
    ['operation' => 'update', 'table' => 'wp_postmeta', 'trigger' => 'before_meta_relink', 'match' => ['meta_id' => 10], 'set' => ['post_id' => 4]],
    ['operation' => 'insert', 'table' => 'wp_posts', 'row' => ['id' => 4, 'post_title' => 'Replacement']],
    ['operation' => 'release', 'name' => 'meta_relink'],
], $foreignKeys);

$nestedRollback = static fn (): array => SQLiteTriggerDeferrableFkSavepointPlan::run($tables, [
    ['operation' => 'savepoint', 'name' => 'outer'],
    ['operation' => 'insert', 'table' => 'wp_posts', 'row' => ['id' => 5, 'post_title' => 'Outer']],
    ['operation' => 'savepoint', 'name' => 'inner'],
    ['operation' => 'insert', 'table' => 'wp_posts', 'row' => ['id' => 6, 'post_title' => 'Inner']],
    ['operation' => 'rollback_to', 'name' => 'outer'],
    ['operation' => 'release', 'name' => 'outer'],
], $foreignKeys);

$restrictKeys = $foreignKeys;
$restrictKeys[0]['on_delete'] = 'restrict';
$restrictDelete = static fn (): array => SQLiteTriggerDeferrableFkSavepointPlan::run($tables, [
    ['operation' => 'savepoint', 'name' => 'delete_pass'],
    ['operation' => 'delete', 'table' => 'wp_posts', 'trigger' => 'after_post_delete', 'match' => ['id' => 1]],
], $restrictKeys);

$cases = [
    'rollback child status ok after parent repair' => [static fn (): mixed => $rollbackChild()['status'], 'commit-ok'],
    'rollback child leaves only repaired imported meta' => [static fn (): mixed => array_column($rollbackChild()['tables']['wp_postmeta'], 'meta_id'), [10, 11, 20]],
    'rollback child removes nested plugin meta row' => [static fn (): mixed => in_array(21, array_column($rollbackChild()['tables']['wp_postmeta'], 'meta_id'), true), false],
    'rollback child keeps repaired parent row' => [static fn (): mixed => array_column($rollbackChild()['tables']['wp_posts'], 'id'), [1, 2, 99]],
    'rollback child deferred queue has one check' => [static fn (): mixed => count($rollbackChild()['deferred']), 1],
    'rollback child deferred check is meta fk' => [static fn (): mixed => $rollbackChild()['deferred'][0]['foreign_key'], 'meta_post'],
    'rollback child deferred check key is repaired' => [static fn (): mixed => $rollbackChild()['deferred'][0]['parent_key'], 99],
    'rollback child deferred check trigger survives' => [static fn (): mixed => $rollbackChild()['deferred'][0]['trigger'], 'after_import_meta'],
    'rollback child deferred check statement index survives' => [static fn (): mixed => $rollbackChild()['deferred'][0]['statement'], 1],
    'rollback child plugin deferred entry is removed' => [static fn (): mixed => array_column($rollbackChild()['deferred'], 'parent_key'), [99]],
    'rollback child events include rollback to nested savepoint' => [static fn (): mixed => array_column($rollbackChild()['events'], 'action'), ['savepoint', 'insert-row', 'savepoint', 'insert-row', 'rollback-to', 'insert-row', 'release', 'release']],
    'rollback child leaves no open savepoints' => [static fn (): mixed => $rollbackChild()['savepoints'], []],
    'rollback child change count rewinds nested insert' => [static fn (): mixed => $rollbackChild()['changes'], 2],
    'rollback child violations empty' => [static fn (): mixed => $rollbackChild()['violations'], []],

    'release violation status blocked' => [static fn (): mixed => $releaseViolation()['status'], 'commit-blocked'],
    'release violation keeps inserted child until commit rejection' => [static fn (): mixed => array_column($releaseViolation()['tables']['wp_postmeta'], 'meta_id'), [10, 11, 22]],
    'release violation records missing parent at commit' => [static fn (): mixed => $releaseViolation()['violations'][0]['reason'], 'missing-parent-at-commit'],
    'release violation keeps trigger name' => [static fn (): mixed => $releaseViolation()['violations'][0]['trigger'], 'after_plugin_meta'],
    'release violation parent key is missing value' => [static fn (): mixed => $releaseViolation()['violations'][0]['parent_key'], 100],
    'release violation deferred queue survives release' => [static fn (): mixed => count($releaseViolation()['deferred']), 1],
    'release violation leaves no savepoints' => [static fn (): mixed => $releaseViolation()['savepoints'], []],
    'release violation change count is one' => [static fn (): mixed => $releaseViolation()['changes'], 1],

    'parent delete rollback status ok' => [static fn (): mixed => $parentDeleteRollback()['status'], 'commit-ok'],
    'parent delete rollback restores parent ids' => [static fn (): mixed => array_column($parentDeleteRollback()['tables']['wp_posts'], 'id'), [1, 2]],
    'parent delete rollback clears deferred queue' => [static fn (): mixed => $parentDeleteRollback()['deferred'], []],
    'parent delete rollback rewinds changes' => [static fn (): mixed => $parentDeleteRollback()['changes'], 0],
    'parent delete rollback events show delete then rollback' => [static fn (): mixed => array_column($parentDeleteRollback()['events'], 'action'), ['savepoint', 'delete-row', 'rollback-to', 'release']],

    'parent delete violation status blocked' => [static fn (): mixed => $parentDeleteViolation()['status'], 'commit-blocked'],
    'parent delete violation removes parent before commit' => [static fn (): mixed => array_column($parentDeleteViolation()['tables']['wp_posts'], 'id'), [2]],
    'parent delete violation preserves child rows' => [static fn (): mixed => array_column($parentDeleteViolation()['tables']['wp_postmeta'], 'post_id'), [1, 2]],
    'parent delete violation reason is referenced parent' => [static fn (): mixed => $parentDeleteViolation()['violations'][0]['reason'], 'referenced-parent-deleted-at-commit'],
    'parent delete violation trigger is recorded' => [static fn (): mixed => $parentDeleteViolation()['violations'][0]['trigger'], 'after_post_delete'],
    'parent delete violation deferred kind' => [static fn (): mixed => $parentDeleteViolation()['deferred'][0]['kind'], 'parent-delete-check'],
    'parent delete violation statement index' => [static fn (): mixed => $parentDeleteViolation()['deferred'][0]['statement'], 1],

    'defer all immediate status ok after repair' => [static fn (): mixed => $deferAllImmediate()['status'], 'commit-ok'],
    'defer all queues initially immediate audit fk' => [static fn (): mixed => $deferAllImmediate()['deferred'][0]['foreign_key'], 'audit_post'],
    'defer all marks immediate check deferred' => [static fn (): mixed => $deferAllImmediate()['deferred'][0]['deferred'], true],
    'defer all final audit row remains' => [static fn (): mixed => array_column($deferAllImmediate()['tables']['wp_import_audit'], 'post_id'), [77]],
    'defer all final parent row repairs check' => [static fn (): mixed => array_column($deferAllImmediate()['tables']['wp_posts'], 'id'), [1, 2, 77]],
    'defer all changes include audit and parent insert' => [static fn (): mixed => $deferAllImmediate()['changes'], 2],

    'immediate violation status blocked at statement' => [static fn (): mixed => $immediateViolation()['status'], 'commit-blocked'],
    'immediate violation is not queued deferred' => [static fn (): mixed => $immediateViolation()['deferred'], []],
    'immediate violation reason is statement missing parent' => [static fn (): mixed => $immediateViolation()['violations'][0]['reason'], 'missing-parent-at-statement'],
    'immediate violation retains audit row for statement evidence' => [static fn (): mixed => array_column($immediateViolation()['tables']['wp_import_audit'], 'audit_id'), [31]],
    'immediate violation trigger is recorded' => [static fn (): mixed => $immediateViolation()['violations'][0]['trigger'], 'after_audit_insert'],

    'update repair status ok' => [static fn (): mixed => $updateRepair()['status'], 'commit-ok'],
    'update repair child key changed' => [static fn (): mixed => array_column($updateRepair()['tables']['wp_postmeta'], 'post_id'), [4, 2]],
    'update repair parent inserted' => [static fn (): mixed => array_column($updateRepair()['tables']['wp_posts'], 'id'), [1, 2, 4]],
    'update repair event has before image' => [static fn (): mixed => $updateRepair()['events'][1]['before']['post_id'], 1],
    'update repair deferred operation update' => [static fn (): mixed => $updateRepair()['deferred'][0]['operation'], 'update'],
    'update repair changes include update and insert' => [static fn (): mixed => $updateRepair()['changes'], 2],

    'nested rollback keeps only original parent rows' => [static fn (): mixed => array_column($nestedRollback()['tables']['wp_posts'], 'id'), [1, 2]],
    'nested rollback closes inner savepoint with rollback' => [static fn (): mixed => $nestedRollback()['savepoints'], []],
    'nested rollback change count returns to frame' => [static fn (): mixed => $nestedRollback()['changes'], 0],
    'nested rollback events include inner savepoint before rollback' => [static fn (): mixed => array_column($nestedRollback()['events'], 'action'), ['savepoint', 'insert-row', 'savepoint', 'insert-row', 'rollback-to', 'release']],

    'restrict delete reports statement violation' => [static fn (): mixed => $restrictDelete()['violations'][0]['reason'], 'restrict-parent-delete-at-statement'],
    'restrict delete does not queue deferred parent check' => [static fn (): mixed => $restrictDelete()['deferred'], []],
    'restrict delete status blocked' => [static fn (): mixed => $restrictDelete()['status'], 'commit-blocked'],
    'restrict delete parent row is removed in preview' => [static fn (): mixed => array_column($restrictDelete()['tables']['wp_posts'], 'id'), [2]],

    'bad savepoint release throws' => [static fn (): mixed => SQLiteTriggerDeferrableFkSavepointPlan::run($tables, [['operation' => 'release', 'name' => 'missing']], $foreignKeys), InvalidArgumentException::class],
    'bad rollback savepoint throws' => [static fn (): mixed => SQLiteTriggerDeferrableFkSavepointPlan::run($tables, [['operation' => 'rollback_to', 'name' => 'missing']], $foreignKeys), InvalidArgumentException::class],
    'bad operation throws' => [static fn (): mixed => SQLiteTriggerDeferrableFkSavepointPlan::run($tables, [['operation' => 'vacuum']], $foreignKeys), InvalidArgumentException::class],
    'bad trigger name throws' => [static fn (): mixed => SQLiteTriggerDeferrableFkSavepointPlan::run($tables, [['operation' => 'insert', 'table' => 'wp_postmeta', 'trigger' => 'bad-trigger', 'row' => ['meta_id' => 1, 'post_id' => 1]]], $foreignKeys), InvalidArgumentException::class],
    'bad child column throws' => [static fn (): mixed => SQLiteTriggerDeferrableFkSavepointPlan::run(['wp_posts' => $tables['wp_posts'], 'wp_postmeta' => [['meta_id' => 1]], 'wp_import_audit' => []], [['operation' => 'delete', 'table' => 'wp_posts', 'match' => ['id' => 1]]], $foreignKeys), InvalidArgumentException::class],
    'bad delete action throws' => [static function () use ($tables, $foreignKeys): mixed {
        $bad = $foreignKeys;
        $bad[0]['on_delete'] = 'cascade';
        return SQLiteTriggerDeferrableFkSavepointPlan::run($tables, [], $bad);
    }, InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['trigger deferrable fk savepoint current next36 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
