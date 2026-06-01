<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowRowValueUpsertCurrentSourcePlan;

$tests = [];

$baseRows = [
    ['key_name' => 'base_url', 'version' => 1, 'priority' => 10, 'load_policy' => 'yes', 'key_value' => 'old-site'],
    ['key_name' => 'landing_url', 'version' => 1, 'priority' => 5, 'load_policy' => 'yes', 'key_value' => 'old-landing_url'],
    ['key_name' => 'site_title', 'version' => 2, 'priority' => 20, 'load_policy' => 'no', 'key_value' => 'old-title'],
    ['key_name' => null, 'version' => 1, 'priority' => 1, 'load_policy' => 'no', 'key_value' => 'anon'],
];

$incomingRows = [
    ['key_name' => 'base_url', 'version' => 1, 'priority' => 12, 'load_policy' => 'yes', 'key_value' => 'site-a'],
    ['key_name' => 'base_url', 'version' => 1, 'priority' => 11, 'load_policy' => 'yes', 'key_value' => 'site-stale'],
    ['key_name' => 'base_url', 'version' => 1, 'priority' => 18, 'load_policy' => 'yes', 'key_value' => 'site-b'],
    ['key_name' => 'new_module', 'version' => 1, 'priority' => 4, 'load_policy' => 'no', 'key_value' => 'enabled'],
    ['key_name' => 'landing_url', 'version' => 2, 'priority' => 4, 'load_policy' => 'yes', 'key_value' => 'landing_url-version'],
    ['key_name' => 'site_title', 'version' => 2, 'priority' => null, 'load_policy' => 'no', 'key_value' => 'title-null'],
    ['key_name' => null, 'version' => 5, 'priority' => 50, 'load_policy' => 'no', 'key_value' => 'anon-two'],
];

$plan = static fn (string $operator = '>', int $preceding = 1, int $following = 1, string $exclude = 'NO OTHERS'): array => SQLiteWindowRowValueUpsertCurrentSourcePlan::execute(
    $baseRows,
    $incomingRows,
    ['key_name'],
    ['version', 'priority'],
    $operator,
    $preceding,
    $following,
    $exclude,
);

$cases = [
    'status ready' => [static fn (): mixed => $plan()['status'], 'window-rowvalue-upsert-current-source-ready'],
    'detail names current source' => [static fn (): mixed => str_contains($plan()['detail'], 'current source'), true],
    'before rows are preserved' => [static fn (): mixed => $plan()['before'][0]['key_value'], 'old-site'],
    'changes include inserts and accepted updates only' => [static fn (): mixed => $plan()['changes'], 5],
    'updated rows include first base_url update' => [static fn (): mixed => $plan()['updated_rows'][0]['key_value'], 'site-a'],
    'second base_url row skips against just updated current source' => [static fn (): mixed => $plan()['skipped_rows'][0]['key_value'], 'site-stale'],
    'third base_url row sees current source and updates again' => [static fn (): mixed => $plan()['updated_rows'][1]['key_value'], 'site-b'],
    'inserted nonconflicting module appears' => [static fn (): mixed => $plan()['inserted_rows'][0]['key_name'], 'new_module'],
    'higher version with lower priority updates lexicographically' => [static fn (): mixed => $plan()['updated_rows'][2]['key_value'], 'landing_url-version'],
    'null decisive tail makes site_title conflict skip' => [static fn (): mixed => $plan()['skipped_rows'][1]['key_name'], 'site_title'],
    'null conflict key does not conflict' => [static fn (): mixed => $plan()['inserted_rows'][1]['key_value'], 'anon-two'],
    'after rows keep target order before appended inserts' => [static fn (): mixed => array_column($plan()['after'], 'key_name'), ['base_url', 'landing_url', 'site_title', null, 'new_module', null]],
    'after base_url contains latest accepted update' => [static fn (): mixed => $plan()['after'][0]['key_value'], 'site-b'],
    'after landing_url contains version update' => [static fn (): mixed => $plan()['after'][1]['version'], 2],
    'after site_title remains unchanged after null predicate' => [static fn (): mixed => $plan()['after'][2]['key_value'], 'old-title'],
    'returning rows preserve statement change order' => [static fn (): mixed => array_column($plan()['returning_rows'], 'key_value'), ['site-a', 'site-b', 'enabled', 'landing_url-version', 'anon-two']],
    'returning rows annotate update action' => [static fn (): mixed => array_column($plan()['returning_rows'], '_upsert_action'), ['update', 'update', 'insert', 'update', 'insert']],
    'returning rows annotate statement sequence' => [static fn (): mixed => array_column($plan()['returning_rows'], 'sequence'), [1, 3, 4, 5, 7]],
    'decision actions preserve incoming order' => [static fn (): mixed => array_column($plan()['decisions'], 'action'), ['update', 'skip', 'update', 'insert', 'update', 'skip', 'insert']],
    'decision sources are current source' => [static fn (): mixed => array_unique(array_column($plan()['decisions'], 'source')), ['current-source']],
    'first decision current tuple' => [static fn (): mixed => $plan()['decisions'][0]['current_tuple'], [1, 10]],
    'second decision current tuple observes first update' => [static fn (): mixed => $plan()['decisions'][1]['current_tuple'], [1, 12]],
    'third decision current tuple observes skipped row did not apply' => [static fn (): mixed => $plan()['decisions'][2]['current_tuple'], [1, 12]],
    'title null decision predicate is unknown' => [static fn (): mixed => $plan()['decisions'][5]['predicate'], null],
    'insert decision has null predicate' => [static fn (): mixed => $plan()['decisions'][3]['predicate'], null],
    'insert decision has no current tuple' => [static fn (): mixed => $plan()['decisions'][3]['current_tuple'], null],
    'window row count matches returning changes' => [static fn (): mixed => count($plan()['window_rows']), 5],
    'window frames use returning order first row' => [static fn (): mixed => $plan()['window_rows'][0]['frame'], [0, 1]],
    'window frames use returning order middle row' => [static fn (): mixed => $plan()['window_rows'][2]['frame'], [1, 2, 3]],
    'window frames clamp final row' => [static fn (): mixed => $plan()['window_rows'][4]['frame'], [3, 4]],
    'window priority sums first row' => [static fn (): mixed => $plan()['window_rows'][0]['frame_priority_sum'], 30],
    'window priority sums middle row' => [static fn (): mixed => $plan()['window_rows'][2]['frame_priority_sum'], 26],
    'window priority sums final row' => [static fn (): mixed => $plan()['window_rows'][4]['frame_priority_sum'], 54],
    'window priority concat skips nulls' => [static fn (): mixed => $plan()['window_rows'][4]['frame_priority_concat'], '4,50'],
    'window first names follow bounded frame' => [static fn (): mixed => array_column($plan()['window_rows'], 'first_key_name'), ['base_url', 'base_url', 'base_url', 'new_module', 'landing_url']],
    'window last names follow bounded frame' => [static fn (): mixed => array_column($plan()['window_rows'], 'last_key_name'), ['base_url', 'new_module', 'landing_url', null, null]],
    'exclude current row removes current from first frame' => [static fn (): mixed => $plan('>', 1, 1, 'CURRENT ROW')['window_rows'][0]['frame'], [1]],
    'exclude current row middle sum' => [static fn (): mixed => $plan('>', 1, 1, 'CURRENT ROW')['window_rows'][2]['frame_priority_sum'], 22],
    'zero width frame isolates each returning row' => [static fn (): mixed => array_column($plan('>', 0, 0)['window_rows'], 'frame'), [[0], [1], [2], [3], [4]]],
    'wide frame covers all returning rows' => [static fn (): mixed => $plan('>', 99, 99)['window_rows'][2]['frame'], [0, 1, 2, 3, 4]],
    'wide frame sum includes all non null priorities' => [static fn (): mixed => $plan('>', 99, 99)['window_rows'][2]['frame_priority_sum'], 88],
    'greater equal lets equal-or-newer site stale update apply' => [static fn (): mixed => array_column($plan('>=')['returning_rows'], 'key_value'), ['site-a', 'site-b', 'enabled', 'landing_url-version', 'anon-two']],
    'less than only accepts inserts because conflict tuples are not lower' => [static fn (): mixed => array_column($plan('<')['returning_rows'], 'key_value'), ['enabled', 'anon-two']],
    'less than first decision skips higher tuple' => [static fn (): mixed => $plan('<')['decisions'][0]['action'], 'skip'],
    'less than second decision updates original current source' => [static fn (): mixed => $plan('<')['decisions'][1]['current_tuple'], [1, 10]],
    'equality accepts exact tuple conflicts plus inserts' => [static fn (): mixed => array_column($plan('=')['returning_rows'], 'key_value'), ['enabled', 'anon-two']],
    'not equal accepts decisive differences' => [static fn (): mixed => array_column($plan('!=')['returning_rows'], 'key_value'), ['site-a', 'site-stale', 'site-b', 'enabled', 'landing_url-version', 'anon-two']],
    'is not treats null tuple as distinct' => [static fn (): mixed => $plan('IS NOT')['decisions'][5]['predicate'], true],
    'is not updates site_title null tail' => [static fn (): mixed => $plan('IS NOT')['after'][2]['key_value'], 'title-null'],
    'is only exact tuple conflicts skip changed rows' => [static fn (): mixed => array_column($plan('IS')['returning_rows'], 'key_value'), ['enabled', 'anon-two']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['window rowvalue upsert source-neutral defaults ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['window rowvalue upsert source-neutral defaults rejects unsupported operator'] = static function (TestRunner $t) use ($baseRows, $incomingRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowRowValueUpsertCurrentSourcePlan::execute($baseRows, $incomingRows, ['key_name'], ['version', 'priority'], 'BETWEEN'));
};

$tests['window rowvalue upsert source-neutral defaults rejects one-column row value'] = static function (TestRunner $t) use ($baseRows, $incomingRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowRowValueUpsertCurrentSourcePlan::execute($baseRows, $incomingRows, ['key_name'], ['priority']));
};

$tests['window rowvalue upsert source-neutral defaults rejects missing incoming row-value column'] = static function (TestRunner $t) use ($baseRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowRowValueUpsertCurrentSourcePlan::execute($baseRows, [['key_name' => 'base_url', 'version' => 2]], ['key_name'], ['version', 'priority']));
};

$tests['window rowvalue upsert source-neutral defaults rejects negative frame offset'] = static function (TestRunner $t) use ($baseRows, $incomingRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowRowValueUpsertCurrentSourcePlan::execute($baseRows, $incomingRows, ['key_name'], ['version', 'priority'], '>', -1, 1));
};

$tests['window rowvalue upsert source-neutral defaults rejects nonnumeric window priority'] = static function (TestRunner $t) use ($baseRows): void {
    $badIncoming = [['key_name' => 'new_text', 'version' => 1, 'priority' => 'high', 'load_policy' => 'no', 'key_value' => 'bad']];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowRowValueUpsertCurrentSourcePlan::execute($baseRows, $badIncoming, ['key_name'], ['version', 'priority']));
};

return $tests;
