<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan;

$parents = [
    ['post_id' => 10, 'post_title' => 'Imported parent', 'slug' => 'parent'],
    ['post_id' => 20, 'post_title' => 'Imported child', 'slug' => 'child'],
    ['post_id' => 30, 'post_title' => 'Imported leaf', 'slug' => 'leaf'],
];
$children = [
    ['meta_id' => 1, 'post_id' => 10, 'meta_key' => '_source'],
    ['meta_id' => 2, 'post_id' => 20, 'meta_key' => '_source'],
    ['meta_id' => 3, 'post_id' => 30, 'meta_key' => '_source'],
];
$foreignKey = ['parent_key' => 'post_id', 'child_key' => 'post_id', 'on_update' => 'cascade', 'deferred' => true];
$returning = [
    'old.post_id',
    ['expr' => 'new.post_id', 'as' => 'new_post_id'],
    ['expr' => 'new.post_title', 'as' => 'title'],
    static fn (array $new, array $old, int $statement, int $depth): string => $statement . ':' . $depth . ':' . $old['post_id'] . '>' . $new['post_id'],
];
$triggers = [
    [
        'name' => 'wp_posts_au_enqueue_child',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'enqueue-update',
        'when' => ['old.post_id', '=', 10],
        'match' => 20,
        'set' => ['post_id' => 120, 'post_title' => 'Recursive child'],
        'values' => ['old_id' => 'old.post_id', 'new_id' => 'new.post_id'],
    ],
    [
        'name' => 'wp_posts_au_enqueue_leaf',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'enqueue-update',
        'when' => ['old.post_id', '=', 20],
        'match' => 30,
        'set' => ['post_id' => 130, 'post_title' => 'Recursive leaf'],
        'values' => ['old_id' => 'old.post_id', 'new_id' => 'new.post_id'],
    ],
    [
        'name' => 'wp_posts_au_orphan_audit',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'insert-child',
        'when' => ['old.post_id', '=', 20],
        'row' => ['meta_id' => 99, 'post_id' => 20, 'meta_key' => '_old_child_audit'],
        'values' => ['audit_id' => 20],
    ],
];

$run = static fn (array $updates = [], array $options = [], array $extraTriggers = []) => SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan::updateParents(
    $parents,
    $children,
    $updates === [] ? [['match' => 10, 'set' => ['post_id' => 110, 'post_title' => 'Rekeyed parent']]] : $updates,
    $foreignKey,
    array_merge($triggers, $extraTriggers),
    $returning,
    array_merge(['current_source' => 'current-wp-posts-import', 'next_source' => 'next-trigger-drain'], $options),
);

$recursive = static fn (): array => $run();
$nonRecursive = static fn (): array => $run([], ['recursive_triggers' => false]);
$noActionDeferred = static function () use ($parents, $children, $triggers, $returning): array {
    return SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan::updateParents(
        $parents,
        $children,
        [['match' => 10, 'set' => ['post_id' => 110, 'post_title' => 'Rekeyed parent']]],
        ['parent_key' => 'post_id', 'child_key' => 'post_id', 'on_update' => 'no action', 'deferred' => true],
        $triggers,
        $returning,
    );
};

$tests = [
    'recursive deferred fk returning emits three returning rows' => [static fn (): mixed => count($recursive()['returning_rows']), 3],
    'recursive deferred fk returning final parent ids' => [static fn (): mixed => array_column($recursive()['parent'], 'post_id'), [110, 120, 130]],
    'recursive deferred fk returning final titles' => [static fn (): mixed => array_column($recursive()['parent'], 'post_title'), ['Rekeyed parent', 'Recursive child', 'Recursive leaf']],
    'recursive deferred fk returning child cascade ids' => [static fn (): mixed => array_column(array_slice($recursive()['child'], 0, 3), 'post_id'), [110, 120, 130]],
    'recursive deferred fk returning audit child remains old key' => [static fn (): mixed => $recursive()['child'][3]['post_id'], 20],
    'recursive deferred fk returning reports deferred violation count' => [static fn (): mixed => count($recursive()['deferred_violations']), 1],
    'recursive deferred fk returning violation phase is commit' => [static fn (): mixed => $recursive()['deferred_violations'][0]['phase'], 'commit'],
    'recursive deferred fk returning violation child key' => [static fn (): mixed => $recursive()['deferred_violations'][0]['child_key'], 20],
    'recursive deferred fk returning violation child index' => [static fn (): mixed => $recursive()['deferred_violations'][0]['child_index'], 3],
    'recursive deferred fk returning commit status fails deferred check' => [static fn (): mixed => $recursive()['commit_status'], 'deferred-constraint-failed'],
    'recursive deferred fk returning yielded statement order' => [static fn (): mixed => array_column($recursive()['yielded'], 'statement'), [0, 1, 2]],
    'recursive deferred fk returning yielded depth order' => [static fn (): mixed => array_column($recursive()['yielded'], 'depth'), [0, 1, 2]],
    'recursive deferred fk returning yielded old keys' => [static fn (): mixed => array_column($recursive()['yielded'], 'old_key'), [10, 20, 30]],
    'recursive deferred fk returning yielded new keys' => [static fn (): mixed => array_column($recursive()['yielded'], 'new_key'), [110, 120, 130]],
    'recursive deferred fk returning returning old post id column' => [static fn (): mixed => array_column($recursive()['returning_rows'], 'post_id'), [10, 20, 30]],
    'recursive deferred fk returning returning new post id alias' => [static fn (): mixed => array_column($recursive()['returning_rows'], 'new_post_id'), [110, 120, 130]],
    'recursive deferred fk returning returning title alias' => [static fn (): mixed => array_column($recursive()['returning_rows'], 'title'), ['Rekeyed parent', 'Recursive child', 'Recursive leaf']],
    'recursive deferred fk returning returning callable trace' => [static fn (): mixed => array_column($recursive()['returning_rows'], 'expr3'), ['0:0:10>110', '1:1:20>120', '2:2:30>130']],
    'recursive deferred fk returning first yield current source' => [static fn (): mixed => $recursive()['yielded'][0]['current_source'], 'current-wp-posts-import'],
    'recursive deferred fk returning first yield next source' => [static fn (): mixed => $recursive()['yielded'][0]['next_source'], 'next-trigger-drain'],
    'recursive deferred fk returning result current source' => [static fn (): mixed => $recursive()['current_source'], 'current-wp-posts-import'],
    'recursive deferred fk returning result next source' => [static fn (): mixed => $recursive()['next_source'], 'next-trigger-drain'],
    'recursive deferred fk returning recursive flag true' => [static fn (): mixed => $recursive()['recursive_triggers'], true],
    'recursive deferred fk returning trigger effects names' => [static fn (): mixed => array_column($recursive()['trigger_effects'], 'trigger'), ['wp_posts_au_enqueue_child', 'wp_posts_au_enqueue_leaf', 'wp_posts_au_orphan_audit']],
    'recursive deferred fk returning trigger effect actions' => [static fn (): mixed => array_column($recursive()['trigger_effects'], 'action'), ['enqueue-update', 'enqueue-update', 'insert-child']],
    'recursive deferred fk returning trigger effect statements' => [static fn (): mixed => array_column($recursive()['trigger_effects'], 'statement'), [0, 1, 1]],
    'recursive deferred fk returning trigger effect depths' => [static fn (): mixed => array_column($recursive()['trigger_effects'], 'depth'), [0, 1, 1]],
    'recursive deferred fk returning first trigger old id value' => [static fn (): mixed => $recursive()['trigger_effects'][0]['row']['old_id'], 10],
    'recursive deferred fk returning second trigger new id value' => [static fn (): mixed => $recursive()['trigger_effects'][1]['row']['new_id'], 120],
    'recursive deferred fk returning audit trigger row value' => [static fn (): mixed => $recursive()['trigger_effects'][2]['row']['audit_id'], 20],
    'recursive deferred fk returning fk action count' => [static fn (): mixed => count($recursive()['foreign_key_actions']), 3],
    'recursive deferred fk returning fk action statements' => [static fn (): mixed => array_column($recursive()['foreign_key_actions'], 'statement'), [0, 1, 2]],
    'recursive deferred fk returning fk action depths' => [static fn (): mixed => array_column($recursive()['foreign_key_actions'], 'depth'), [0, 1, 2]],
    'recursive deferred fk returning fk action from keys' => [static fn (): mixed => array_column($recursive()['foreign_key_actions'], 'from'), [10, 20, 30]],
    'recursive deferred fk returning fk action to keys' => [static fn (): mixed => array_column($recursive()['foreign_key_actions'], 'to'), [110, 120, 130]],
    'recursive deferred fk returning fk action names are cascade' => [static fn (): mixed => array_column($recursive()['foreign_key_actions'], 'action'), ['cascade', 'cascade', 'cascade']],
    'recursive deferred fk returning dependency marker' => [static fn (): mixed => $recursive()['dependencies'][0], 'sqlite-trigger-deferred-fk-returning-recursive-current-source-next114'],
    'recursive deferred fk returning dependency ordering marker' => [static fn (): mixed => $recursive()['dependencies'][1], 'sqlite-returning-yield-before-recursive-after-trigger-drain'],
    'recursive deferred fk returning dependency deferred marker' => [static fn (): mixed => $recursive()['dependencies'][2], 'sqlite-deferred-foreign-key-check-at-commit'],

    'nonrecursive deferred fk returning emits only top statement' => [static fn (): mixed => count($nonRecursive()['returning_rows']), 1],
    'nonrecursive deferred fk returning updates only parent row' => [static fn (): mixed => array_column($nonRecursive()['parent'], 'post_id'), [110, 20, 30]],
    'nonrecursive deferred fk returning cascades only first child' => [static fn (): mixed => array_column($nonRecursive()['child'], 'post_id'), [110, 20, 30]],
    'nonrecursive deferred fk returning still records trigger effect' => [static fn (): mixed => $nonRecursive()['trigger_effects'][0]['trigger'], 'wp_posts_au_enqueue_child'],
    'nonrecursive deferred fk returning records recursive flag false' => [static fn (): mixed => $nonRecursive()['recursive_triggers'], false],
    'nonrecursive deferred fk returning has no deferred violation' => [static fn (): mixed => $nonRecursive()['deferred_violations'], []],
    'nonrecursive deferred fk returning commit status ok' => [static fn (): mixed => $nonRecursive()['commit_status'], 'ok'],
    'nonrecursive deferred fk returning callable trace only top row' => [static fn (): mixed => $nonRecursive()['returning_rows'][0]['expr3'], '0:0:10>110'],

    'no action deferred fk returning keeps child ids unchanged' => [static fn (): mixed => array_column(array_slice($noActionDeferred()['child'], 0, 3), 'post_id'), [10, 20, 30]],
    'no action deferred fk returning reports four violations after recursion' => [static fn (): mixed => count($noActionDeferred()['deferred_violations']), 4],
    'no action deferred fk returning records no action fk actions' => [static fn (): mixed => array_column($noActionDeferred()['foreign_key_actions'], 'action'), ['no action', 'no action', 'no action']],
    'no action deferred fk returning still yields recursive returning rows' => [static fn (): mixed => array_column($noActionDeferred()['returning_rows'], 'new_post_id'), [110, 120, 130]],
    'no action deferred fk returning commit status fails' => [static fn (): mixed => $noActionDeferred()['commit_status'], 'deferred-constraint-failed'],

    'recursive deferred fk returning star projection returns new row' => [static fn (): mixed => SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan::updateParents($parents, $children, [['match' => 10, 'set' => ['post_id' => 110]]], $foreignKey, [], ['*'])['returning_rows'][0]['*']['post_id'], 110],
    'recursive deferred fk returning missing parent update is skipped' => [static fn (): mixed => SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan::updateParents($parents, $children, [['match' => 404, 'set' => ['post_id' => 405]]], $foreignKey, [], ['new.post_id'])['returning_rows'], []],
    'recursive deferred fk returning malformed update throws' => [static fn (): mixed => SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan::updateParents($parents, $children, [['match' => 10]], $foreignKey), InvalidArgumentException::class],
    'recursive deferred fk returning empty projection throws' => [static fn (): mixed => SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan::updateParents($parents, $children, [['match' => 10, 'set' => ['post_id' => 110]]], $foreignKey, [], []), InvalidArgumentException::class],
    'recursive deferred fk returning bad alias throws' => [static fn (): mixed => SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan::updateParents($parents, $children, [['match' => 10, 'set' => ['post_id' => 110]]], $foreignKey, [], [['expr' => 'new.post_id', 'as' => 'bad-alias']]), InvalidArgumentException::class],
    'recursive deferred fk returning bad trigger action throws' => [static fn (): mixed => $run([], [], [['timing' => 'after', 'event' => 'update', 'action' => 'delete-parent']]), InvalidArgumentException::class],
    'recursive deferred fk returning bad when operator throws' => [static fn (): mixed => $run([], [], [['timing' => 'after', 'event' => 'update', 'when' => ['new.post_id', 'LIKE', 110]]]), InvalidArgumentException::class],
    'recursive deferred fk returning immediate no action throws' => [static fn (): mixed => SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan::updateParents($parents, $children, [['match' => 10, 'set' => ['post_id' => 110]]], ['parent_key' => 'post_id', 'child_key' => 'post_id', 'on_update' => 'no action', 'deferred' => false], [], ['new.post_id']), InvalidArgumentException::class],
    'recursive deferred fk returning max depth throws' => [static fn (): mixed => $run([], ['max_depth' => 1]), InvalidArgumentException::class],
    'recursive deferred fk returning malformed parent key throws' => [static fn (): mixed => SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan::updateParents($parents, $children, [['match' => 10, 'set' => ['post_id' => 110]]], ['parent_key' => 'bad-key', 'child_key' => 'post_id']), InvalidArgumentException::class],
];

foreach ($tests as $name => [$callback, $expected]) {
    $tests['trigger deferred fk returning recursive current source next114 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
    unset($tests[$name]);
}

return $tests;
