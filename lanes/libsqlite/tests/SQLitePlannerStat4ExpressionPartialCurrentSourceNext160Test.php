<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$expr160 = ['function' => 'lower', 'column' => 'option_name'];
$point160 = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$exprPoint160 = static fn (array $expr, mixed $value): array => ['operator' => '=', 'left' => $expr, 'right' => $value];
$exprRange160 = static fn (array $expr, string $operator, mixed $value): array => ['operator' => $operator, 'left' => $expr, 'right' => $value];
$and160 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];
$or160 = static fn (array ...$terms): array => ['operator' => 'OR', 'terms' => $terms];

$rows160 = static fn (): array => [
    ['rowid' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'site_id' => 1],
    ['rowid' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_beta', 'option_value' => 'beta-old', 'site_id' => 1],
    ['rowid' => 3, 'autoload' => 'no', 'option_name' => 'plugin_beta', 'option_value' => 'beta-lazy', 'site_id' => 1],
    ['rowid' => 4, 'autoload' => 'yes', 'option_name' => 'plugin_gamma', 'option_value' => 'gamma-old', 'site_id' => 1],
    ['rowid' => 5, 'autoload' => 'yes', 'option_name' => 'theme_mods', 'option_value' => 'theme-old', 'site_id' => 1],
];
$currentRows160 = static function () use ($rows160): array {
    $rows = $rows160();
    $rows[] = ['rowid' => 6, 'autoload' => 'yes', 'option_name' => 'PLUGIN_BETA', 'option_value' => 'beta-current', 'site_id' => 2];
    $rows[] = ['rowid' => 7, 'autoload' => 'yes', 'option_name' => 'plugin_delta', 'option_value' => 'delta', 'site_id' => 1];
    $rows[] = ['rowid' => 8, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'site_id' => 1];
    $rows[] = ['rowid' => 9, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'site_id' => 1];

    return $rows;
};

$samples160 = static fn (): array => [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 'yes']],
    ['neq' => '2 2', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_beta', 'yes']],
    ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_delta', 'yes']],
    ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_forms', 'yes']],
    ['neq' => '1 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['plugin_gamma', 'yes']],
];
$index160 = static fn (int $rootPage, int $estimatedRows, array $samples): array => [
    'name' => 'idx_wp_options_lower_name_autoload_partial_next160',
    'rootPage' => $rootPage,
    'estimatedRows' => $estimatedRows,
    'coveringColumns' => ['autoload', 'option_name', 'option_value', 'site_id'],
    'stat4Samples' => $samples,
    'sql' => "CREATE INDEX idx_wp_options_lower_name_autoload_partial_next160 ON wp_options(lower(option_name), autoload, site_id, option_value) WHERE autoload = 'yes' AND lower(option_name) >= 'plugin_'",
];
$source160 = static fn (array $overrides = []) => array_replace_recursive([
    'name' => 'prepared-main.wp_options@next160',
    'schemaCookie' => 1600,
    'stat4Generation' => 80,
    'rows' => $rows160(),
    'indexes' => [$index160(16001, 240, array_slice($samples160(), 0, 4))],
], $overrides);
$currentSource160 = static fn (array $overrides = []) => array_replace_recursive([
    'name' => 'current-main.wp_options@next160',
    'schemaCookie' => 1603,
    'stat4Generation' => 83,
    'rows' => $currentRows160(),
    'indexes' => [$index160(16031, 180, $samples160())],
], $overrides);

$armPoint160 = static fn (mixed $value) => $and160($point160('autoload', 'yes'), $exprPoint160($expr160, $value));
$armRange160 = static fn () => $and160($point160('autoload', 'yes'), $exprRange160($expr160, '>=', 'plugin_delta'), $exprRange160($expr160, '<=', 'plugin_forms'));
$predicate160 = static fn () => $or160($armPoint160('plugin_beta'), $armRange160());
$order160 = [$expr160, ['column' => 'site_id']];
$needed160 = ['option_name', 'option_value', 'site_id'];
$plan160 = static fn (?array $prepared = null, ?array $current = null, ?array $predicate = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeNext160(
    $prepared ?? $source160(),
    $current ?? $currentSource160(),
    $predicate ?? $predicate160(),
    $GLOBALS['order160'],
    $needed ?? $GLOBALS['needed160'],
);

$GLOBALS['order160'] = $order160;
$GLOBALS['needed160'] = $needed160;

$same160 = static function () use ($source160, $plan160): array {
    $source = $source160();

    return $plan160($source, $source);
};
$sameIndexRewrite160 = static fn () => $plan160(null, null, $or160($armPoint160('plugin_beta'), $armPoint160('plugin_gamma')));
$unproved160 = static fn () => $plan160(null, null, $or160($exprPoint160($expr160, 'plugin_beta'), $exprPoint160($expr160, 'plugin_gamma')));

return [
    'planner stat4 expression partial current source next160 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next160-ready', $plan160()['status']),
    'planner stat4 expression partial current source next160 selects current' => static fn (TestRunner $t) => $t->same('current', $plan160()['selectedSource']),
    'planner stat4 expression partial current source next160 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan160()['stalePreparedStatement']),
    'planner stat4 expression partial current source next160 reparses' => static fn (TestRunner $t) => $t->same(true, $plan160()['reprepareRequired']),
    'planner stat4 expression partial current source next160 schema changed' => static fn (TestRunner $t) => $t->same(true, $plan160()['schemaCookieChanged']),
    'planner stat4 expression partial current source next160 stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan160()['stat4GenerationChanged']),
    'planner stat4 expression partial current source next160 index changed' => static fn (TestRunner $t) => $t->same(true, $plan160()['indexSignatureChanged']),
    'planner stat4 expression partial current source next160 rows changed' => static fn (TestRunner $t) => $t->same(true, $plan160()['rowSignatureChanged']),
    'planner stat4 expression partial current source next160 prepared ready' => static fn (TestRunner $t) => $t->same(true, $plan160()['preparedSource']['ready']),
    'planner stat4 expression partial current source next160 current ready' => static fn (TestRunner $t) => $t->same(true, $plan160()['currentSource']['ready']),
    'planner stat4 expression partial current source next160 prepared strategy' => static fn (TestRunner $t) => $t->same('or-rowid-union', $plan160()['preparedSource']['strategy']),
    'planner stat4 expression partial current source next160 current strategy' => static fn (TestRunner $t) => $t->same('or-rowid-union', $plan160()['currentSource']['strategy']),
    'planner stat4 expression partial current source next160 selected strategy' => static fn (TestRunner $t) => $t->same('or-rowid-union', $plan160()['selectedPlan']['strategy']),
    'planner stat4 expression partial current source next160 selected partial' => static fn (TestRunner $t) => $t->same(true, $plan160()['selectedPlan']['partial']),
    'planner stat4 expression partial current source next160 selected covering' => static fn (TestRunner $t) => $t->same(true, $plan160()['selectedPlan']['covering']),
    'planner stat4 expression partial current source next160 selected stat4' => static fn (TestRunner $t) => $t->same(true, $plan160()['selectedPlan']['stat4Used']),
    'planner stat4 expression partial current source next160 arm count' => static fn (TestRunner $t) => $t->same(2, $plan160()['selectedPlan']['armCount']),
    'planner stat4 expression partial current source next160 dedupe required' => static fn (TestRunner $t) => $t->same(true, $plan160()['selectedPlan']['dedupeRowidsRequired']),
    'planner stat4 expression partial current source next160 no in rewrite' => static fn (TestRunner $t) => $t->same(null, $plan160()['selectedPlan']['inRewrite']),
    'planner stat4 expression partial current source next160 row count' => static fn (TestRunner $t) => $t->same(4, $plan160()['selectedPlan']['currentSourceRowCount']),
    'planner stat4 expression partial current source next160 rowids' => static fn (TestRunner $t) => $t->same([2, 6, 7, 8], $plan160()['selectedPlan']['currentSourceRowids']),
    'planner stat4 expression partial current source next160 keys' => static fn (TestRunner $t) => $t->same(['plugin_beta', 'plugin_beta', 'plugin_delta', 'plugin_forms'], $plan160()['selectedPlan']['currentSourceKeys']),
    'planner stat4 expression partial current source next160 deduped rowids' => static fn (TestRunner $t) => $t->same([2, 6, 7, 8], $plan160()['selectedPlan']['dedupedRowids']),
    'planner stat4 expression partial current source next160 first payload' => static fn (TestRunner $t) => $t->same('beta-old', $plan160()['unionRows'][0]['payload']['option_value']),
    'planner stat4 expression partial current source next160 second payload preserves case' => static fn (TestRunner $t) => $t->same('PLUGIN_BETA', $plan160()['unionRows'][1]['payload']['option_name']),
    'planner stat4 expression partial current source next160 range row payload' => static fn (TestRunner $t) => $t->same('forms', $plan160()['unionRows'][3]['payload']['option_value']),
    'planner stat4 expression partial current source next160 excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(3, $plan160()['selectedPlan']['currentSourceRowids'], true)),
    'planner stat4 expression partial current source next160 excludes outside theme' => static fn (TestRunner $t) => $t->same(false, in_array(5, $plan160()['selectedPlan']['currentSourceRowids'], true)),
    'planner stat4 expression partial current source next160 excludes null name' => static fn (TestRunner $t) => $t->same(false, in_array(9, $plan160()['selectedPlan']['currentSourceRowids'], true)),
    'planner stat4 expression partial current source next160 current next first' => static fn (TestRunner $t) => $t->same(6, $plan160()['currentNextRows'][0]['next']['rowid']),
    'planner stat4 expression partial current source next160 current next eof' => static fn (TestRunner $t) => $t->same(null, $plan160()['currentNextRows'][3]['next']),
    'planner stat4 expression partial current source next160 first arm index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_name_autoload_partial_next160', $plan160()['selectedPlan']['arms'][0]['name']),
    'planner stat4 expression partial current source next160 range arm operator' => static fn (TestRunner $t) => $t->same('range-<=', $plan160()['selectedPlan']['arms'][1]['operator']),
    'planner stat4 expression partial current source next160 point arm rows' => static fn (TestRunner $t) => $t->same(2, $plan160()['selectedPlan']['arms'][0]['estimatedRows']),
    'planner stat4 expression partial current source next160 range arm rows' => static fn (TestRunner $t) => $t->same(5, $plan160()['selectedPlan']['arms'][1]['estimatedRows']),
    'planner stat4 expression partial current source next160 cursor status' => static fn (TestRunner $t) => $t->same('stat4-or-partial-current-source-rowid-union', $plan160()['cursorTape']['status']),
    'planner stat4 expression partial current source next160 cursor source' => static fn (TestRunner $t) => $t->same('current', $plan160()['cursorTape']['source']),
    'planner stat4 expression partial current source next160 cursor rowids' => static fn (TestRunner $t) => $t->same([2, 6, 7, 8], $plan160()['cursorTape']['rowids']),
    'planner stat4 expression partial current source next160 cursor open ephemeral' => static fn (TestRunner $t) => $t->same('OpenEphemeral', $plan160()['cursorTape']['program'][0]['opcode']),
    'planner stat4 expression partial current source next160 cursor first seek' => static fn (TestRunner $t) => $t->same('SeekStat4', $plan160()['cursorTape']['program'][1]['opcode']),
    'planner stat4 expression partial current source next160 cursor first idxrowid' => static fn (TestRunner $t) => $t->same('IdxRowid', $plan160()['cursorTape']['program'][2]['opcode']),
    'planner stat4 expression partial current source next160 cursor reads columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'site_id'], array_column(array_slice($plan160()['cursorTape']['program'], 5, 3), 'column')),
    'planner stat4 expression partial current source next160 cursor result count' => static fn (TestRunner $t) => $t->same(4, $plan160()['cursorTape']['program'][8]['rowCount']),
    'planner stat4 expression partial current source next160 cursor next' => static fn (TestRunner $t) => $t->same('Next', $plan160()['cursorTape']['program'][9]['opcode']),
    'planner stat4 expression partial current source next160 fence name' => static fn (TestRunner $t) => $t->same('current-main.wp_options@next160', $plan160()['currentSourceFence']['name']),
    'planner stat4 expression partial current source next160 fence cookie' => static fn (TestRunner $t) => $t->same(1603, $plan160()['currentSourceFence']['schemaCookie']),
    'planner stat4 expression partial current source next160 fence stat4' => static fn (TestRunner $t) => $t->same(83, $plan160()['currentSourceFence']['stat4Generation']),
    'planner stat4 expression partial current source next160 fence signatures' => static fn (TestRunner $t) => $t->same([64, 64, 64, 64], array_map('strlen', [$plan160()['currentSourceFence']['sourceSignature'], $plan160()['currentSourceFence']['indexSignature'], $plan160()['currentSourceFence']['rowStreamSignature'], $plan160()['currentSourceFence']['armSignature']])),
    'planner stat4 expression partial current source next160 detail' => static fn (TestRunner $t) => $t->contains('STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT160', $plan160()['detail']),
    'planner stat4 expression partial current source next160 dependencies' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next160', $plan160()['dependencies'], true)),
    'planner stat4 expression partial current source next160 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan160()['dependency_closure']),
    'planner stat4 expression partial current source next160 non overlap' => static fn (TestRunner $t) => $t->contains('OR-rowid-union current-source row dedupe', $plan160()['non_overlap']),
    'planner stat4 expression partial current source next160 same source prepared' => static fn (TestRunner $t) => $t->same('prepared', $same160()['selectedSource']),
    'planner stat4 expression partial current source next160 same source rowids' => static fn (TestRunner $t) => $t->same([2], $same160()['selectedPlan']['currentSourceRowids']),
    'planner stat4 expression partial current source next160 same source not stale' => static fn (TestRunner $t) => $t->same(false, $same160()['stalePreparedStatement']),
    'planner stat4 expression partial current source next160 same index OR rewrites to IN and waits' => static fn (TestRunner $t) => $t->same('requires-next-stage', $sameIndexRewrite160()['status']),
    'planner stat4 expression partial current source next160 same index OR strategy' => static fn (TestRunner $t) => $t->same('or-to-in-partial-expression', $sameIndexRewrite160()['selectedPlan']['strategy']),
    'planner stat4 expression partial current source next160 unproved status' => static fn (TestRunner $t) => $t->same('requires-next-stage', $unproved160()['status']),
    'planner stat4 expression partial current source next160 unproved plan null' => static fn (TestRunner $t) => $t->same(null, $unproved160()['selectedPlan']),
    'planner stat4 expression partial current source next160 validates needed columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan160(null, null, null, [])),
    'planner stat4 expression partial current source next160 validates rows' => static function (TestRunner $t) use ($currentSource160, $plan160): void {
        $bad = $currentSource160();
        $bad['rows'][] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan160(null, $bad));
    },
    'planner stat4 expression partial current source next160 validates indexes' => static function (TestRunner $t) use ($currentSource160, $plan160): void {
        $bad = $currentSource160();
        $bad['indexes'] = [];
        $t->throws(InvalidArgumentException::class, static fn () => $plan160(null, $bad));
    },
    'planner stat4 expression partial current source next160 unsupported operator is not planned' => static function (TestRunner $t) use ($plan160, $and160, $point160, $expr160, $or160): void {
        $badPredicate = $or160($and160($point160('autoload', 'yes'), ['operator' => 'LIKE', 'left' => $expr160, 'right' => 'plugin_%']));
        $t->same('requires-next-stage', $plan160(null, null, $badPredicate)['status']);
    },
];
