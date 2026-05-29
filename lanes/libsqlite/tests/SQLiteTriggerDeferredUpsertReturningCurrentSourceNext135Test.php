<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerDeferredUpsertReturningCurrentSourceNextPlan;

$parents135 = [
    ['post_id' => 10, 'post_title' => 'Existing page'],
    ['post_id' => 20, 'post_title' => 'Existing post'],
];
$rows135 = [
    ['meta_id' => 1, 'post_id' => 10, 'meta_key' => '_source', 'meta_value' => 'old', 'revision' => 1, 'source' => 'seed'],
    ['meta_id' => 2, 'post_id' => 20, 'meta_key' => '_source', 'meta_value' => 'old-child', 'revision' => 1, 'source' => 'seed'],
];
$incoming135 = [
    ['meta_id' => 3, 'post_id' => 10, 'meta_key' => '_import_batch', 'meta_value' => 'batch-a', 'revision' => 1, 'source' => 'import'],
    ['meta_id' => 4, 'post_id' => 10, 'meta_key' => '_source', 'meta_value' => 'updated-source', 'revision' => 2, 'source' => 'import'],
    ['meta_id' => 5, 'post_id' => 999, 'meta_key' => '_missing_parent', 'meta_value' => 'orphan', 'revision' => 1, 'source' => 'import'],
];
$assignments135 = [
    'meta_id' => static fn (array $old, array $incoming): int => $old['meta_id'],
    'post_id' => static fn (array $old, array $incoming): int => $incoming['post_id'],
    'meta_value' => static fn (array $old, array $incoming): string => $incoming['meta_value'],
    'revision' => static fn (array $old, array $incoming): int => $old['revision'] + 1,
    'source' => static fn (array $old, array $incoming): string => $incoming['source'],
];
$triggers135 = [
    [
        'name' => 'wp_postmeta_ai_stamp',
        'timing' => 'after',
        'event' => 'insert',
        'mutate_target' => true,
        'set' => ['source' => 'after-insert-trigger'],
        'values' => ['key' => 'new.meta_key', 'post_id' => 'new.post_id'],
    ],
    [
        'name' => 'wp_postmeta_bu_audit',
        'timing' => 'before',
        'event' => 'update',
        'values' => ['old_value' => 'old.meta_value', 'new_value' => 'new.meta_value'],
    ],
    [
        'name' => 'wp_postmeta_au_stamp',
        'timing' => 'after',
        'event' => 'update',
        'mutate_target' => true,
        'set' => ['source' => 'after-update-trigger'],
        'values' => ['key' => 'new.meta_key', 'revision' => 'new.revision'],
    ],
];
$returning135 = [
    'meta_id',
    'post_id',
    'meta_key',
    'meta_value',
    'source',
    ['expr' => 'new.revision', 'as' => 'next_revision'],
    static fn (array $new, ?array $old, string $action, int $statement): string => $statement . ':' . $action . ':' . ($old['revision'] ?? 0) . '>' . $new['revision'],
];
$foreignKey135 = [
    'child_table' => 'wp_postmeta',
    'parent_table' => 'wp_posts',
    'child_key' => 'post_id',
    'parent_key' => 'post_id',
    'deferred' => true,
];

$plan135 = static fn (array $incoming = null, array $parents = null, array $options = [], ?callable $where = null): array => SQLiteTriggerDeferredUpsertReturningCurrentSourceNextPlan::executeDeferredCommit(
    $rows135,
    $incoming ?? $incoming135,
    ['meta_key'],
    $assignments135,
    $triggers135,
    $returning135,
    $parents ?? $parents135,
    $foreignKey135,
    array_merge([
        'transaction' => 'wp_import_txn',
        'current_source' => 'current-upsert-returning-yield',
        'next_source' => 'next-deferred-commit',
    ], $options),
    $where,
);
$blocked135 = static fn (): array => $plan135();
$valid135 = static fn (): array => $plan135([
    ['meta_id' => 3, 'post_id' => 10, 'meta_key' => '_import_batch', 'meta_value' => 'batch-a', 'revision' => 1, 'source' => 'import'],
    ['meta_id' => 4, 'post_id' => 10, 'meta_key' => '_source', 'meta_value' => 'updated-source', 'revision' => 2, 'source' => 'import'],
]);
$held135 = static fn (): array => $plan135($incoming135, $parents135, ['rollback_transaction' => false]);
$immediate135 = static fn (): array => SQLiteTriggerDeferredUpsertReturningCurrentSourceNextPlan::executeDeferredCommit(
    $rows135,
    $incoming135,
    ['meta_key'],
    $assignments135,
    $triggers135,
    $returning135,
    $parents135,
    array_merge($foreignKey135, ['deferred' => false]),
);

$cases135 = [
    'status records current source behavior' => [static fn (): mixed => $blocked135()['status'], 'trigger-deferred-upsert-returning-current-source-next135-ready'],
    'transaction name is preserved' => [static fn (): mixed => $blocked135()['transaction'], 'wp_import_txn'],
    'current source is preserved' => [static fn (): mixed => $blocked135()['current_source'], 'current-upsert-returning-yield'],
    'next source is preserved' => [static fn (): mixed => $blocked135()['next_source'], 'next-deferred-commit'],
    'foreign key mode is deferred' => [static fn (): mixed => $blocked135()['deferred'], true],
    'commit is blocked by deferred foreign key' => [static fn (): mixed => $blocked135()['commit_blocked'], true],
    'commit status reports deferred failure' => [static fn (): mixed => $blocked135()['commit_status'], 'deferred-foreign-key-failed'],
    'transaction rollback is requested' => [static fn (): mixed => $blocked135()['rollback_transaction'], true],
    'three current returning rows are yielded' => [static fn (): mixed => count($blocked135()['current_returning']), 3],
    'next returning is suppressed after commit failure' => [static fn (): mixed => $blocked135()['next_returning'], []],
    'discarded next returning count matches yielded rows' => [static fn (): mixed => $blocked135()['discarded_next_returning_count'], 3],
    'statement changes are visible before commit' => [static fn (): mixed => $blocked135()['statement_changes_before_commit'], 3],
    'changes are zero after transaction rollback' => [static fn (): mixed => $blocked135()['changes'], 0],
    'returning ids preserve insert update insert order' => [static fn (): mixed => array_column($blocked135()['current_returning'], 'meta_id'), [3, 1, 5]],
    'returning post ids include orphan before commit' => [static fn (): mixed => array_column($blocked135()['current_returning'], 'post_id'), [10, 10, 999]],
    'returning keys preserve statement order' => [static fn (): mixed => array_column($blocked135()['current_returning'], 'meta_key'), ['_import_batch', '_source', '_missing_parent']],
    'returning values use assigned row images' => [static fn (): mixed => array_column($blocked135()['current_returning'], 'meta_value'), ['batch-a', 'updated-source', 'orphan']],
    'returning source is captured before after-trigger mutation' => [static fn (): mixed => array_column($blocked135()['current_returning'], 'source'), ['import', 'import', 'import']],
    'returning revisions use inserted and updated images' => [static fn (): mixed => array_column($blocked135()['current_returning'], 'next_revision'), [1, 2, 1]],
    'returning callable traces statement action and old revision' => [static fn (): mixed => array_column($blocked135()['current_returning'], 'expr6'), ['0:insert:0>1', '1:update:1>2', '2:insert:0>1']],
    'yield stream statements are ordered' => [static fn (): mixed => array_column($blocked135()['yield_stream'], 'statement'), [0, 1, 2]],
    'yield stream actions preserve insert update insert' => [static fn (): mixed => array_column($blocked135()['yield_stream'], 'action'), ['insert', 'update', 'insert']],
    'yield stream records transaction' => [static fn (): mixed => array_column($blocked135()['yield_stream'], 'transaction'), ['wp_import_txn', 'wp_import_txn', 'wp_import_txn']],
    'yield stream marks commit blocked after yield' => [static fn (): mixed => array_column($blocked135()['yield_stream'], 'commit_blocked_after_yield'), [true, true, true]],
    'yield stream is not savepoint rollback marked' => [static fn (): mixed => array_column($blocked135()['yield_stream'], 'rolled_back_after_yield'), [false, false, false]],
    'after statement includes inserted orphan before commit' => [static fn (): mixed => array_column($blocked135()['after_statement'], 'meta_key'), ['_source', '_source', '_import_batch', '_missing_parent']],
    'after statement sources include after triggers' => [static fn (): mixed => array_column($blocked135()['after_statement'], 'source'), ['after-update-trigger', 'seed', 'after-insert-trigger', 'after-insert-trigger']],
    'after failed commit keeps statement image' => [static fn (): mixed => array_column($blocked135()['after_failed_commit'], 'post_id'), [10, 20, 10, 999]],
    'transaction rollback restores original keys' => [static fn (): mixed => array_column($blocked135()['after_transaction_rollback'], 'meta_key'), ['_source', '_source']],
    'transaction rollback restores original values' => [static fn (): mixed => array_column($blocked135()['after_transaction_rollback'], 'meta_value'), ['old', 'old-child']],
    'transaction rollback restores original sources' => [static fn (): mixed => array_column($blocked135()['after_transaction_rollback'], 'source'), ['seed', 'seed']],
    'inserted rows are discarded by rollback' => [static fn (): mixed => $blocked135()['inserted_rows'], []],
    'updated rows are discarded by rollback' => [static fn (): mixed => $blocked135()['updated_rows'], []],
    'skipped rows remain empty' => [static fn (): mixed => $blocked135()['skipped_rows'], []],
    'deferred violation count is one' => [static fn (): mixed => count($blocked135()['deferred_violations']), 1],
    'deferred violation table is child table' => [static fn (): mixed => $blocked135()['deferred_violations'][0]['table'], 'wp_postmeta'],
    'deferred violation parent table is recorded' => [static fn (): mixed => $blocked135()['deferred_violations'][0]['parent'], 'wp_posts'],
    'deferred violation value is orphan key' => [static fn (): mixed => $blocked135()['deferred_violations'][0]['value'], 999],
    'deferred violation waits until commit' => [static fn (): mixed => $blocked135()['deferred_violations'][0]['deferred_until'], 'commit'],
    'trigger effects include insert stamp update audit update stamp insert stamp' => [static fn (): mixed => array_column($blocked135()['trigger_effects_before_commit'], 'trigger'), ['wp_postmeta_ai_stamp', 'wp_postmeta_bu_audit', 'wp_postmeta_au_stamp', 'wp_postmeta_ai_stamp']],
    'before update audit saw old value' => [static fn (): mixed => $blocked135()['trigger_effects_before_commit'][1]['row']['old_value'], 'old'],
    'before update audit saw new value' => [static fn (): mixed => $blocked135()['trigger_effects_before_commit'][1]['row']['new_value'], 'updated-source'],
    'parent keys are preserved' => [static fn (): mixed => $blocked135()['parent_keys'], [10, 20]],
    'restored child keys are original after rollback' => [static fn (): mixed => $blocked135()['restored_child_keys'], [10, 20]],
    'dependencies include next135 marker' => [static fn (): mixed => in_array('sqlite-trigger-deferred-upsert-returning-current-source-next135', $blocked135()['dependencies'], true), true],
    'dependencies include returning before deferred commit marker' => [static fn (): mixed => in_array('sqlite-returning-yield-before-deferred-fk-commit', $blocked135()['dependencies'], true), true],
    'dependencies include next-source block marker' => [static fn (): mixed => in_array('sqlite-deferred-fk-blocks-next-source-after-upsert-returning', $blocked135()['dependencies'], true), true],
    'dependencies include transaction rollback marker' => [static fn (): mixed => in_array('sqlite-transaction-rollback-restores-current-source-after-deferred-fk', $blocked135()['dependencies'], true), true],
    'valid path commit is ok' => [static fn (): mixed => $valid135()['commit_status'], 'ok'],
    'valid path keeps next returning rows' => [static fn (): mixed => array_column($valid135()['next_returning'], 'meta_key'), ['_import_batch', '_source']],
    'valid path keeps changes' => [static fn (): mixed => $valid135()['changes'], 2],
    'valid path has no violations' => [static fn (): mixed => $valid135()['deferred_violations'], []],
    'valid path keeps trigger-mutated statement rows' => [static fn (): mixed => array_column($valid135()['after_transaction_rollback'], 'source'), ['after-update-trigger', 'seed', 'after-insert-trigger']],
    'valid path yield stream is not commit blocked' => [static fn (): mixed => array_column($valid135()['yield_stream'], 'commit_blocked_after_yield'), [false, false]],
    'held failed transaction keeps statement image' => [static fn (): mixed => array_column($held135()['after_transaction_rollback'], 'post_id'), [10, 20, 10, 999]],
    'held failed transaction keeps inserted rows visible' => [static fn (): mixed => array_column($held135()['inserted_rows'], 'meta_key'), ['_import_batch', '_missing_parent']],
    'held failed transaction keeps updated rows visible' => [static fn (): mixed => array_column($held135()['updated_rows'], 'meta_key'), ['_source']],
    'immediate mode bypasses deferred commit validation' => [static fn (): mixed => $immediate135()['commit_status'], 'ok'],
    'where skip avoids returning and violation' => [static fn (): mixed => $plan135($incoming135, $parents135, [], static fn (?array $old, array $incoming): bool => $incoming['meta_key'] !== '_source')['current_returning'][1]['meta_key'], '_missing_parent'],
    'where skip records skipped update' => [static fn (): mixed => array_column($plan135($incoming135, $parents135, [], static fn (?array $old, array $incoming): bool => $incoming['meta_key'] !== '_source')['skipped_rows'], 'meta_key'), ['_source']],
    'custom transaction is accepted' => [static fn (): mixed => $plan135($incoming135, $parents135, ['transaction' => 'wp_retry_txn'])['transaction'], 'wp_retry_txn'],
    'bad transaction throws' => [static fn (): mixed => $plan135($incoming135, $parents135, ['transaction' => 'bad-name']), InvalidArgumentException::class],
    'bad child key throws' => [static fn (): mixed => SQLiteTriggerDeferredUpsertReturningCurrentSourceNextPlan::executeDeferredCommit($rows135, $incoming135, ['meta_key'], $assignments135, $triggers135, $returning135, $parents135, array_merge($foreignKey135, ['child_key' => 'bad-key'])), InvalidArgumentException::class],
    'missing parent key throws' => [static fn (): mixed => SQLiteTriggerDeferredUpsertReturningCurrentSourceNextPlan::executeDeferredCommit($rows135, $incoming135, ['meta_key'], $assignments135, $triggers135, $returning135, [['id' => 10]], $foreignKey135), InvalidArgumentException::class],
    'missing child key throws' => [static fn (): mixed => SQLiteTriggerDeferredUpsertReturningCurrentSourceNextPlan::executeDeferredCommit([['meta_id' => 9, 'meta_key' => 'x']], [['meta_id' => 10, 'meta_key' => 'y']], ['meta_key'], [], [], ['meta_key'], $parents135, $foreignKey135), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases135 as $name => [$callback, $expected]) {
    $tests['trigger deferred upsert returning current source next135 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
