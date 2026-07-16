<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$expr157 = ['function' => 'lower', 'column' => 'option_name'];
$point157 = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$in157 = static fn (array $left, array $values): array => ['operator' => 'IN', 'left' => $left, 'values' => $values];
$and157 = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];

$rows157 = static fn (): array => [
    ['rowid' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Alpha', 'option_value' => 'alpha-old', 'site_id' => 1],
    ['rowid' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_beta', 'option_value' => 'beta-old', 'site_id' => 1],
    ['rowid' => 3, 'autoload' => 'no', 'option_name' => 'plugin_beta', 'option_value' => 'beta-lazy', 'site_id' => 1],
    ['rowid' => 4, 'autoload' => 'yes', 'option_name' => 'plugin_delta', 'option_value' => 'delta', 'site_id' => 1],
    ['rowid' => 5, 'autoload' => 'yes', 'option_name' => 'plugin_stable', 'option_value' => 'stable-old', 'site_id' => 1],
    ['rowid' => 6, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'site_id' => 1],
];
$currentRows157 = static function () use ($rows157): array {
    $rows = $rows157();
    $rows[] = ['rowid' => 7, 'autoload' => 'yes', 'option_name' => 'PLUGIN_BETA', 'option_value' => 'beta-new', 'site_id' => 2];
    $rows[] = ['rowid' => 8, 'autoload' => 'yes', 'option_name' => 'plugin_stable', 'option_value' => 'stable-new', 'site_id' => 2];

    return $rows;
};
$nextRows157 = static function () use ($currentRows157): array {
    $rows = $currentRows157();
    $rows[] = ['rowid' => 9, 'autoload' => 'yes', 'option_name' => 'plugin_rc', 'option_value' => 'rc', 'site_id' => 1];

    return $rows;
};

$index157 = static fn (array $samples, int $rootPage = 15701, int $estimatedRows = 240): array => [
    'name' => 'idx_wp_options_lower_name_autoload_stat4_partial_coveringReprepare',
    'rootPage' => $rootPage,
    'estimatedRows' => $estimatedRows,
    'coveringColumns' => ['option_name', 'option_value', 'autoload', 'site_id'],
    'coveringExpressions' => [$GLOBALS['expr157']],
    'stat4Samples' => $samples,
    'sql' => "CREATE INDEX idx_wp_options_lower_name_autoload_stat4_partial_coveringReprepare ON wp_options(lower(option_name), autoload, site_id, option_value) WHERE autoload = 'yes'",
];
$preparedSamples157 = static fn (): array => [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 'yes']],
    ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_beta', 'yes']],
    ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_delta', 'yes']],
    ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_stable', 'yes']],
];
$currentSamples157 = static fn (): array => [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 'yes']],
    ['neq' => '2 2', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_beta', 'yes']],
    ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_delta', 'yes']],
    ['neq' => '2 2', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_stable', 'yes']],
];

$source157 = static function (array $overrides = []) use ($rows157, $preparedSamples157, $index157): array {
    return array_replace_recursive([
        'name' => 'prepared-main.wp_options@coveringReprepare',
        'schemaCookie' => 1570,
        'stat4Generation' => 70,
        'rows' => $rows157(),
        'indexes' => [$index157($preparedSamples157(), 15701, 240)],
    ], $overrides);
};
$currentSource157 = static function (array $overrides = []) use ($currentRows157, $currentSamples157, $index157): array {
    return array_replace_recursive([
        'name' => 'current-main.wp_options@coveringReprepare',
        'schemaCookie' => 1571,
        'stat4Generation' => 71,
        'rows' => $currentRows157(),
        'indexes' => [$index157($currentSamples157(), 15711, 180)],
    ], $overrides);
};
$nextSource157 = static function (array $overrides = []) use ($nextRows157, $currentSamples157, $index157): array {
    $samples = $currentSamples157();
    $samples[] = ['neq' => '1 1', 'nlt' => '6 4', 'ndlt' => '4 4', 'sample' => ['plugin_rc', 'yes']];

    return array_replace_recursive([
        'name' => 'next-main.wp_options@coveringReprepare',
        'schemaCookie' => 1572,
        'stat4Generation' => 72,
        'rows' => $nextRows157(),
        'indexes' => [$index157($samples, 15721, 190)],
    ], $overrides);
};

$predicate157 = static fn (): array => $and157(
    $point157('autoload', 'yes'),
    $in157($GLOBALS['expr157'], ['plugin_beta', 'plugin_stable'])
);
$needed157 = ['option_name', 'option_value', 'autoload', 'site_id'];
$order157 = [$expr157, ['column' => 'site_id']];
$plan157 = static fn (?array $prepared = null, ?array $current = null, ?array $predicate = null, ?array $next = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceCoveringReprepare(
    $prepared ?? $source157(),
    $current ?? $currentSource157(),
    $predicate ?? $predicate157(),
    $GLOBALS['order157'],
    $GLOBALS['needed157'],
    [$GLOBALS['expr157']],
    $next,
);

$same157 = static function () use ($source157, $plan157): array {
    $source = $source157();

    return $plan157($source, $source);
};
$staleCoveringReprepare = static fn (): array => $plan157(null, null, null, $nextSource157());
$freshCoveringReprepare = static fn (): array => $plan157(null, null, null, $currentSource157());
$unusable157 = static fn (): array => $plan157(null, null, $and157($in157($GLOBALS['expr157'], ['plugin_beta'])));

$tests = [
    'planner stat4 expression partial current source coveringReprepare status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-coveringReprepare-ready', $plan157()['status']),
    'planner stat4 expression partial current source coveringReprepare selects current' => static fn (TestRunner $t) => $t->same('current', $plan157()['selectedSource']),
    'planner stat4 expression partial current source coveringReprepare marks stale' => static fn (TestRunner $t) => $t->same(true, $plan157()['stalePreparedStatement']),
    'planner stat4 expression partial current source coveringReprepare reparses' => static fn (TestRunner $t) => $t->same(true, $plan157()['reprepareRequired']),
    'planner stat4 expression partial current source coveringReprepare schema changed' => static fn (TestRunner $t) => $t->same(true, $plan157()['schemaCookieChanged']),
    'planner stat4 expression partial current source coveringReprepare stat4 changed' => static fn (TestRunner $t) => $t->same(true, $plan157()['stat4GenerationChanged']),
    'planner stat4 expression partial current source coveringReprepare index changed' => static fn (TestRunner $t) => $t->same(true, $plan157()['indexSignatureChanged']),
    'planner stat4 expression partial current source coveringReprepare row changed' => static fn (TestRunner $t) => $t->same(true, $plan157()['rowSignatureChanged']),
    'planner stat4 expression partial current source coveringReprepare stat4 signature changed' => static fn (TestRunner $t) => $t->same(true, $plan157()['stat4SignatureChanged']),
    'planner stat4 expression partial current source coveringReprepare selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_name_autoload_stat4_partial_coveringReprepare', $plan157()['selectedPlan']['name']),
    'planner stat4 expression partial current source coveringReprepare selected root' => static fn (TestRunner $t) => $t->same(15711, $plan157()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source coveringReprepare ready flag' => static fn (TestRunner $t) => $t->same(true, $plan157()['selectedPlan']['coveringReprepareReady']),
    'planner stat4 expression partial current source coveringReprepare source label' => static fn (TestRunner $t) => $t->same('current-stat4-expression-partial', $plan157()['selectedPlan']['coveringReprepareSource']),
    'planner stat4 expression partial current source coveringReprepare uses stat4' => static fn (TestRunner $t) => $t->same(true, $plan157()['selectedPlan']['stat4Estimate'] !== null),
    'planner stat4 expression partial current source coveringReprepare matched samples' => static fn (TestRunner $t) => $t->same(2, $plan157()['selectedPlan']['stat4MatchedSamples']),
    'planner stat4 expression partial current source coveringReprepare estimated rows' => static fn (TestRunner $t) => $t->same(4, $plan157()['selectedPlan']['estimatedRows']),
    'planner stat4 expression partial current source coveringReprepare covered count' => static fn (TestRunner $t) => $t->same(4, $plan157()['selectedPlan']['coveredRowCount']),
    'planner stat4 expression partial current source coveringReprepare rowids' => static fn (TestRunner $t) => $t->same([2, 7, 5, 8], $plan157()['selectedPlan']['coveringReprepareRowids']),
    'planner stat4 expression partial current source coveringReprepare keys' => static fn (TestRunner $t) => $t->same(['plugin_beta', 'plugin_beta', 'plugin_stable', 'plugin_stable'], $plan157()['selectedPlan']['coveringReprepareKeys']),
    'planner stat4 expression partial current source coveringReprepare names preserve case' => static fn (TestRunner $t) => $t->same(['plugin_beta', 'PLUGIN_BETA', 'plugin_stable', 'plugin_stable'], $plan157()['selectedPlan']['coveringReprepareCoveringNames']),
    'planner stat4 expression partial current source coveringReprepare excludes autoload no' => static fn (TestRunner $t) => $t->same(false, in_array(3, $plan157()['selectedPlan']['coveringReprepareRowids'], true)),
    'planner stat4 expression partial current source coveringReprepare excludes unmatched delta' => static fn (TestRunner $t) => $t->same(false, in_array(4, $plan157()['selectedPlan']['coveringReprepareRowids'], true)),
    'planner stat4 expression partial current source coveringReprepare excludes null expression' => static fn (TestRunner $t) => $t->same(false, in_array(6, $plan157()['selectedPlan']['coveringReprepareRowids'], true)),
    'planner stat4 expression partial current source coveringReprepare covering true' => static fn (TestRunner $t) => $t->same(true, $plan157()['selectedPlan']['covering']),
    'planner stat4 expression partial current source coveringReprepare order requires residual sort' => static fn (TestRunner $t) => $t->same(false, $plan157()['selectedPlan']['orderBySatisfied']),
    'planner stat4 expression partial current source coveringReprepare current next rowids' => static fn (TestRunner $t) => $t->same([7, 5, 8, null], array_map(static fn (array $pair): mixed => $pair['next']['rowid'] ?? null, $plan157()['currentNextRows'])),
    'planner stat4 expression partial current source coveringReprepare first payload' => static fn (TestRunner $t) => $t->same('beta-old', $plan157()['currentNextRows'][0]['current']['covering']['option_value']),
    'planner stat4 expression partial current source coveringReprepare second expression payload' => static fn (TestRunner $t) => $t->same('plugin_beta', $plan157()['currentNextRows'][1]['current']['coveringExpressions']['lower(option_name)']),
    'planner stat4 expression partial current source coveringReprepare cursor open' => static fn (TestRunner $t) => $t->same('OpenRead', $plan157()['selectedPlan']['coveringReprepareCursorProgram'][0]['opcode']),
    'planner stat4 expression partial current source coveringReprepare cursor seek' => static fn (TestRunner $t) => $t->same('SeekStat4', $plan157()['selectedPlan']['coveringReprepareCursorProgram'][1]['opcode']),
    'planner stat4 expression partial current source coveringReprepare cursor keys' => static fn (TestRunner $t) => $t->same(['plugin_beta', 'plugin_stable'], $plan157()['selectedPlan']['coveringReprepareCursorProgram'][1]['keys']),
    'planner stat4 expression partial current source coveringReprepare cursor columns' => static fn (TestRunner $t) => $t->same($GLOBALS['needed157'], array_column(array_slice($plan157()['selectedPlan']['coveringReprepareCursorProgram'], 2, 4), 'column')),
    'planner stat4 expression partial current source coveringReprepare cursor expression column' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan157()['selectedPlan']['coveringReprepareCursorProgram'][6]['expression']),
    'planner stat4 expression partial current source coveringReprepare cursor next' => static fn (TestRunner $t) => $t->same('Next', $plan157()['selectedPlan']['coveringReprepareCursorProgram'][7]['opcode']),
    'planner stat4 expression partial current source coveringReprepare fence source' => static fn (TestRunner $t) => $t->same('current-main.wp_options@coveringReprepare', $plan157()['currentSourceFence']['name']),
    'planner stat4 expression partial current source coveringReprepare fence cookie' => static fn (TestRunner $t) => $t->same(1571, $plan157()['currentSourceFence']['schemaCookie']),
    'planner stat4 expression partial current source coveringReprepare fence stat4' => static fn (TestRunner $t) => $t->same(71, $plan157()['currentSourceFence']['stat4Generation']),
    'planner stat4 expression partial current source coveringReprepare fence source signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan157()['currentSourceFence']['sourceSignature'])),
    'planner stat4 expression partial current source coveringReprepare fence index signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan157()['currentSourceFence']['indexSignature'])),
    'planner stat4 expression partial current source coveringReprepare fence row stream signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan157()['currentSourceFence']['rowStreamSignature'])),
    'planner stat4 expression partial current source coveringReprepare fence key signature' => static fn (TestRunner $t) => $t->same(64, strlen($plan157()['currentSourceFence']['matchedKeySignature'])),
    'planner stat4 expression partial current source coveringReprepare detail' => static fn (TestRunner $t) => $t->contains('STAT4 EXPRESSION PARTIAL CURRENT SOURCE COVERING REPREPARE', $plan157()['detail']),
    'planner stat4 expression partial current source coveringReprepare dependencies' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-coveringReprepare', $plan157()['dependencies'], true)),
    'planner stat4 expression partial current source coveringReprepare dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan157()['dependency_closure']),
    'planner stat4 expression partial current source coveringReprepare non overlap' => static fn (TestRunner $t) => $t->contains('STAT4 expression partial current-source row materialization', $plan157()['non_overlap']),
    'planner stat4 expression partial current source coveringReprepare same source prepared' => static fn (TestRunner $t) => $t->same('prepared', $same157()['selectedSource']),
    'planner stat4 expression partial current source coveringReprepare same source not stale' => static fn (TestRunner $t) => $t->same(false, $same157()['stalePreparedStatement']),
    'planner stat4 expression partial current source coveringReprepare same rowids' => static fn (TestRunner $t) => $t->same([2, 5], $same157()['selectedPlan']['coveringReprepareRowids']),
    'planner stat4 expression partial current source coveringReprepare stale next blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $staleCoveringReprepare()['status']),
    'planner stat4 expression partial current source coveringReprepare stale next not admitted' => static fn (TestRunner $t) => $t->same(false, $staleCoveringReprepare()['nextSourceAdmitted']),
    'planner stat4 expression partial current source coveringReprepare stale next reasons' => static fn (TestRunner $t) => $t->same(['schema-cookie', 'stat4-generation', 'index-signature', 'row-signature', 'stat4-signature'], $staleCoveringReprepare()['nextSource']['replanReasons']),
    'planner stat4 expression partial current source coveringReprepare stale next name' => static fn (TestRunner $t) => $t->same('next-main.wp_options@coveringReprepare', $staleCoveringReprepare()['nextSource']['name']),
    'planner stat4 expression partial current source coveringReprepare fresh next admitted' => static fn (TestRunner $t) => $t->same(true, $freshCoveringReprepare()['nextSourceAdmitted']),
    'planner stat4 expression partial current source coveringReprepare fresh next ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-coveringReprepare-ready', $freshCoveringReprepare()['status']),
    'planner stat4 expression partial current source coveringReprepare unproved partial reprepare' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $unusable157()['status']),
    'planner stat4 expression partial current source coveringReprepare unproved no selected plan' => static fn (TestRunner $t) => $t->same(null, $unusable157()['selectedPlan']),
    'planner stat4 expression partial current source coveringReprepare validates rows' => static function (TestRunner $t) use ($currentSource157, $plan157): void {
        $bad = $currentSource157(['rows' => ['bad-row']]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan157(null, $bad));
    },
    'planner stat4 expression partial current source coveringReprepare validates indexes' => static function (TestRunner $t) use ($currentSource157, $plan157): void {
        $bad = $currentSource157();
        $bad['indexes'] = [];
        $t->throws(InvalidArgumentException::class, static fn () => $plan157(null, $bad));
    },
    'planner stat4 expression partial current source coveringReprepare validates stat4 counters' => static function (TestRunner $t) use ($currentSource157, $plan157): void {
        $bad = $currentSource157();
        $bad['indexes'][0]['stat4Samples'][0]['neq'] = 0;
        $t->throws(InvalidArgumentException::class, static fn () => $plan157(null, $bad));
    },
];

$GLOBALS['expr157'] = $expr157;
$GLOBALS['needed157'] = $needed157;
$GLOBALS['order157'] = $order157;

return $tests;
