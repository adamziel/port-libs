<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan;

$parents141 = [
    ['post_id' => 10, 'post_title' => 'Imported parent', 'slug' => 'parent'],
    ['post_id' => 20, 'post_title' => 'Imported child', 'slug' => 'child'],
    ['post_id' => 30, 'post_title' => 'Imported leaf', 'slug' => 'leaf'],
];
$children141 = [
    ['meta_id' => 1, 'post_id' => 10, 'meta_key' => '_source'],
    ['meta_id' => 2, 'post_id' => 20, 'meta_key' => '_source'],
    ['meta_id' => 3, 'post_id' => 30, 'meta_key' => '_source'],
];
$foreignKey141 = ['parent_key' => 'post_id', 'child_key' => 'post_id', 'on_update' => 'cascade', 'deferred' => true];
$updates141 = [['match' => 10, 'set' => ['post_id' => 110, 'post_title' => 'Rekeyed parent']]];
$triggers141 = [
    [
        'name' => 'wp_posts_au_enqueue_child',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'enqueue-update',
        'when' => ['old.post_id', '=', 10],
        'match' => 20,
        'set' => ['post_id' => 120, 'post_title' => 'Recursive child'],
    ],
    [
        'name' => 'wp_posts_au_enqueue_leaf',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'enqueue-update',
        'when' => ['old.post_id', '=', 20],
        'match' => 30,
        'set' => ['post_id' => 130, 'post_title' => 'Recursive leaf'],
    ],
    [
        'name' => 'wp_posts_au_orphan_audit',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'insert-child',
        'when' => ['old.post_id', '=', 20],
        'row' => ['meta_id' => 99, 'post_id' => 20, 'meta_key' => '_old_child_audit'],
    ],
];
$returning141 = [
    'old.post_id',
    ['expr' => 'new.post_id', 'as' => 'new_post_id'],
    ['expr' => 'new.post_title', 'as' => 'title'],
    static fn (array $new, array $old, int $statement, int $depth): string => $statement . ':' . $depth . ':' . $old['post_id'] . '>' . $new['post_id'],
];
$plan141 = static fn (array $options = []): array => SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan::commitBarrierRetry(
    $parents141,
    $children141,
    $updates141,
    $foreignKey141,
    $triggers141,
    $returning141,
    array_merge([
        'savepoint' => 'wp_import_batch',
        'current_source' => 'current-trigger-returning-yield',
        'next_source' => 'next-deferred-commit-check',
        'retry_source' => 'next-retry-after-rollback-to',
    ], $options),
);
$blocked141 = static fn (): array => $plan141();
$released141 = static fn (): array => $plan141(['rollback_to' => false]);
$noRetry141 = static fn (): array => $plan141(['retry_after_rollback' => false]);
$nonRecursive141 = static fn (): array => $plan141(['recursive_triggers' => false]);

$cases141 = [
    'savepoint name is carried into barrier' => [static fn (): mixed => $blocked141()['savepoint'], 'wp_import_batch'],
    'current source is statement source' => [static fn (): mixed => $blocked141()['current_source'], 'current-trigger-returning-yield'],
    'next source is commit source' => [static fn (): mixed => $blocked141()['next_source'], 'next-deferred-commit-check'],
    'retry source is recorded' => [static fn (): mixed => $blocked141()['retry_source'], 'next-retry-after-rollback-to'],
    'commit is blocked by deferred fk' => [static fn (): mixed => $blocked141()['commit_blocked'], true],
    'barrier names deferred returning failure' => [static fn (): mixed => $blocked141()['commit_barrier'], 'deferred-fk-blocks-commit-after-returning-yield'],
    'rolled back flag is true' => [static fn (): mixed => $blocked141()['rolled_back'], true],
    'yielded returning rows include three updates' => [static fn (): mixed => count($blocked141()['yielded_returning_rows']), 3],
    'yielded returning old ids remain visible' => [static fn (): mixed => array_column($blocked141()['yielded_returning_rows'], 'post_id'), [10, 20, 30]],
    'yielded returning new ids remain visible' => [static fn (): mixed => array_column($blocked141()['yielded_returning_rows'], 'new_post_id'), [110, 120, 130]],
    'yielded returning titles remain visible' => [static fn (): mixed => array_column($blocked141()['yielded_returning_rows'], 'title'), ['Rekeyed parent', 'Recursive child', 'Recursive leaf']],
    'yielded returning expression order remains visible' => [static fn (): mixed => array_column($blocked141()['yielded_returning_rows'], 'expr3'), ['0:0:10>110', '1:1:20>120', '2:2:30>130']],
    'blocked rollback makes no returning rows durable' => [static fn (): mixed => $blocked141()['durable_returning_rows'], []],
    'invalidated returning rows are counted' => [static fn (): mixed => $blocked141()['invalidated_returning_count'], 3],
    'invalidated rows keep ordinal order' => [static fn (): mixed => array_column($blocked141()['invalidated_returning_rows'], 'ordinal'), [0, 1, 2]],
    'invalidated rows carry savepoint name' => [static fn (): mixed => array_column($blocked141()['invalidated_returning_rows'], 'savepoint'), ['wp_import_batch', 'wp_import_batch', 'wp_import_batch']],
    'invalidated rows carry yield source' => [static fn (): mixed => array_column($blocked141()['invalidated_returning_rows'], 'yield_source'), ['current-trigger-returning-yield', 'current-trigger-returning-yield', 'current-trigger-returning-yield']],
    'invalidated rows carry blocked source' => [static fn (): mixed => array_column($blocked141()['invalidated_returning_rows'], 'blocked_source'), ['next-deferred-commit-check', 'next-deferred-commit-check', 'next-deferred-commit-check']],
    'invalidated rows are not durable' => [static fn (): mixed => array_column($blocked141()['invalidated_returning_rows'], 'durable'), [false, false, false]],
    'invalidated row payload retains first new id' => [static fn (): mixed => $blocked141()['invalidated_returning_rows'][0]['row']['new_post_id'], 110],
    'deferred queue at commit contains orphan audit' => [static fn (): mixed => $blocked141()['deferred_queue_at_commit'][0]['child_key'], 20],
    'deferred queue after rollback is clear' => [static fn (): mixed => $blocked141()['deferred_queue_after_rollback'], []],
    'attempt after statement commit is blocked' => [static fn (): mixed => $blocked141()['attempt']['after_statement']['commit_status'], 'deferred-constraint-failed'],
    'attempt after savepoint commit is ok' => [static fn (): mixed => $blocked141()['attempt']['after_savepoint']['commit_status'], 'ok-after-rollback-to-savepoint'],
    'attempt after savepoint restores parent ids' => [static fn (): mixed => array_column($blocked141()['attempt']['after_savepoint']['parent'], 'post_id'), [10, 20, 30]],
    'attempt after savepoint restores child ids' => [static fn (): mixed => array_column($blocked141()['attempt']['after_savepoint']['child'], 'post_id'), [10, 20, 30]],
    'attempt discarded returning count remains visible' => [static fn (): mixed => $blocked141()['attempt']['discarded_returning_count'], 3],
    'source transition records statement source' => [static fn (): mixed => $blocked141()['source_transition']['statement'], 'current-trigger-returning-yield'],
    'source transition records commit attempt' => [static fn (): mixed => $blocked141()['source_transition']['commit_attempt'], 'next-deferred-commit-check'],
    'source transition records retry source' => [static fn (): mixed => $blocked141()['source_transition']['retry'], 'next-retry-after-rollback-to'],
    'source transition makes retry next visible' => [static fn (): mixed => $blocked141()['source_transition']['next_visible'], 'next-retry-after-rollback-to'],
    'retry plan is present' => [static fn (): mixed => is_array($blocked141()['retry']), true],
    'retry plan is admitted after blocked commit' => [static fn (): mixed => $blocked141()['retry']['admitted'], true],
    'retry plan uses retry source' => [static fn (): mixed => $blocked141()['retry']['source'], 'next-retry-after-rollback-to'],
    'retry starts from restored parent keys' => [static fn (): mixed => $blocked141()['retry']['parent_keys'], [10, 20, 30]],
    'retry starts from restored child keys' => [static fn (): mixed => $blocked141()['retry']['child_keys'], [10, 20, 30]],
    'retry keeps pending update keys' => [static fn (): mixed => $blocked141()['retry']['pending_updates'], [10]],
    'retry deferred queue is clear' => [static fn (): mixed => $blocked141()['retry']['deferred_queue'], []],
    'retry has no pre-yielded returning rows' => [static fn (): mixed => $blocked141()['retry']['returning_rows'], []],
    'retry status names restored source' => [static fn (): mixed => $blocked141()['retry']['status'], 'retry-from-restored-savepoint-image'],
    'dependencies include savepoint marker' => [static fn (): mixed => in_array('sqlite-trigger-deferred-returning-savepoint', $blocked141()['dependencies'], true), true],
    'dependencies include commit barrier marker' => [static fn (): mixed => in_array('sqlite-trigger-deferred-returning-commit-barrier', $blocked141()['dependencies'], true), true],
    'dependencies include deferred fk barrier marker' => [static fn (): mixed => in_array('sqlite-deferred-fk-commit-barrier-after-returning', $blocked141()['dependencies'], true), true],
    'dependencies include retry marker' => [static fn (): mixed => in_array('sqlite-savepoint-current-source-retry-after-deferred-commit-failure', $blocked141()['dependencies'], true), true],
    'release path is not rolled back' => [static fn (): mixed => $released141()['rolled_back'], false],
    'release path keeps commit blocked' => [static fn (): mixed => $released141()['commit_blocked'], true],
    'release path keeps returning rows durable until caller rolls back transaction' => [static fn (): mixed => array_column($released141()['durable_returning_rows'], 'new_post_id'), [110, 120, 130]],
    'release path does not invalidate returning rows' => [static fn (): mixed => $released141()['invalidated_returning_rows'], []],
    'release path reports next source visible' => [static fn (): mixed => $released141()['source_transition']['next_visible'], 'next-deferred-commit-check'],
    'release path retry is absent' => [static fn (): mixed => $released141()['retry'], null],
    'no retry option suppresses retry plan' => [static fn (): mixed => $noRetry141()['retry'], null],
    'no retry option still invalidates rows' => [static fn (): mixed => $noRetry141()['invalidated_returning_count'], 3],
    'non recursive yields one row' => [static fn (): mixed => count($nonRecursive141()['yielded_returning_rows']), 1],
    'non recursive commit is admitted' => [static fn (): mixed => $nonRecursive141()['commit_blocked'], false],
    'non recursive invalidates no rows' => [static fn (): mixed => $nonRecursive141()['invalidated_returning_count'], 0],
    'non recursive durable row survives clean commit' => [static fn (): mixed => array_column($nonRecursive141()['durable_returning_rows'], 'new_post_id'), [110]],
    'non recursive next source remains visible' => [static fn (): mixed => $nonRecursive141()['source_transition']['next_visible'], 'next-deferred-commit-check'],
    'non recursive retry starts from restored parents' => [static fn (): mixed => $nonRecursive141()['retry']['parent_keys'], [10, 20, 30]],
    'non recursive retry is not admitted' => [static fn (): mixed => $nonRecursive141()['retry']['admitted'], false],
    'non recursive retry status says not needed' => [static fn (): mixed => $nonRecursive141()['retry']['status'], 'retry-not-needed'],
    'custom retry source is accepted' => [static fn (): mixed => $plan141(['retry_source' => 'retry_two'])['retry']['source'], 'retry_two'],
    'bad savepoint throws' => [static fn (): mixed => $plan141(['savepoint' => 'bad-name']), InvalidArgumentException::class],
    'bad parent key throws' => [static fn (): mixed => SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan::commitBarrierRetry($parents141, $children141, $updates141, ['parent_key' => 'bad-key', 'child_key' => 'post_id']), InvalidArgumentException::class],
    'missing child key throws from retry key collection' => [static function () use ($parents141, $updates141, $foreignKey141): mixed {
        return SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan::commitBarrierRetry($parents141, [['meta_id' => 1]], $updates141, $foreignKey141);
    }, InvalidArgumentException::class],
];

foreach ($cases141 as $name => [$callback, $expected]) {
    $tests['trigger deferred returning commit barrier ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
