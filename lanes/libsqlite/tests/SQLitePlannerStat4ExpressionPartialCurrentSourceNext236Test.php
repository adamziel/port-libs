<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq236 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$like236 = static fn (string $column, string $right): array => ['left' => ['column' => $column], 'operator' => 'LIKE', 'right' => $right];
$notNull236 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between236 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared236 = static fn (): array => [
    'name' => 'prepared-wp-options-stat4-density-next236',
    'schemaCookie' => 2360,
    'stat4Generation' => 236,
    'rows' => [
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu-old', 'updated_at' => 60],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_density_next236',
        'rootPage' => 23601,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'descending' => true,
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_forms'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<=', 'right' => 'plugin_zulu'],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'partialGroupedOrPredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
        ]],
        'partialGroupedLikePredicateArms' => [[
            ['left' => ['column' => 'blog_id'], 'operator' => '=', 'right' => 1],
            ['left' => ['column' => 'option_name'], 'operator' => 'LIKE', 'right' => 'plugin_%'],
        ]],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
            ['neq' => '1 1', 'nlt' => '3 3', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60]],
        ],
    ]],
];

$current236 = static function (array $statOverrides = [], ?array $rows = null) use ($prepared236): array {
    $source = $prepared236();
    $source['name'] = 'current-wp-options-stat4-density-next236';
    $source['schemaCookie'] = 2368;
    $source['stat4Generation'] = 386;
    $source['indexes'][0]['rootPage'] = 23688;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '3 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '3 1', 'ndlt' => '1 1', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '4 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ['neq' => '1 1', 'nlt' => '5 3', 'ndlt' => '3 3', 'sample' => ['plugin_zulu', 60]],
        ['neq' => '1 1', 'nlt' => '6 4', 'ndlt' => '4 4', 'sample' => ['theme_mods_current', 90]],
    ];
    foreach ($statOverrides as $sampleOffset => $values) {
        foreach ($values as $name => $value) {
            $source['indexes'][0]['stat4Samples'][$sampleOffset][$name] = $value;
        }
    }
    $source['rows'] = $rows ?? [
        ['rowid' => 90, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_current', 'option_value' => 'theme', 'updated_at' => 90],
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-anchor', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy-a', 'updated_at' => 21],
        ['rowid' => 22, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'PLUGIN_FORMS', 'option_value' => 'forms-copy-b', 'updated_at' => 22],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
        ['rowid' => 80, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'other-blog', 'updated_at' => 80],
    ];

    return $source;
};

$terms236 = static fn (): array => [
    $between236('LOWER(option_name)', 'plugin_forms', 'plugin_zulu'),
    $eq236('autoload', 'yes'),
    $notNull236('option_name'),
    $eq236('blog_id', 1),
    $like236('option_name', 'plugin_%'),
];

$plan236 = static fn (int $limit = 6, int $offset = 0, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceStat4DensityVectorValidation(
    $prepared ?? $prepared236(),
    $current ?? $current236(),
    $terms ?? $terms236(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);

$staleNeq236 = static fn (): array => $plan236(6, 0, null, $current236([0 => ['neq' => '2 1']]));
$staleNlt236 = static fn (): array => $plan236(6, 0, null, $current236([2 => ['nlt' => '5 2']]));
$staleNdlt236 = static fn (): array => $plan236(6, 0, null, $current236([3 => ['ndlt' => '2 3']]));
$staleAll236 = static fn (): array => $plan236(6, 0, null, $current236([1 => ['neq' => '2 1', 'nlt' => '4 1', 'ndlt' => '2 1']]));

$tests = [
    'planner stat4 expression partial current source next236 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next236-ready', $plan236()['status']),
    'planner stat4 expression partial current source next236 selected current' => static fn (TestRunner $t) => $t->same('current', $plan236()['selectedSource']),
    'planner stat4 expression partial current source next236 inherits next233' => static fn (TestRunner $t) => $t->same(true, $plan236()['selectedPlan']['next233Ready']),
    'planner stat4 expression partial current source next236 ready flag' => static fn (TestRunner $t) => $t->same(true, $plan236()['selectedPlan']['next236Ready']),
    'planner stat4 expression partial current source next236 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_density_next236', $plan236()['selectedPlan']['name']),
    'planner stat4 expression partial current source next236 root page' => static fn (TestRunner $t) => $t->same(23688, $plan236()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next236 matched rowids' => static fn (TestRunner $t) => $t->same([60, 30, 50, 20, 21, 22], $plan236()['matchedRowids']),
    'planner stat4 expression partial current source next236 projected duplicate payload' => static fn (TestRunner $t) => $t->same('forms-copy-b', $plan236()['projectedRows'][5]['option_value']),
    'planner stat4 expression partial current source next236 partial row count' => static fn (TestRunner $t) => $t->same(6, $plan236()['stat4DensityVectorGuard']['currentPartialRowCount']),
    'planner stat4 expression partial current source next236 distinct count' => static fn (TestRunner $t) => $t->same(4, $plan236()['stat4DensityVectorGuard']['currentDistinctExpressionKeyCount']),
    'planner stat4 expression partial current source next236 sample row count' => static fn (TestRunner $t) => $t->same(4, $plan236()['stat4DensityVectorGuard']['sampleRowCount']),
    'planner stat4 expression partial current source next236 validated count' => static fn (TestRunner $t) => $t->same(4, $plan236()['stat4DensityVectorGuard']['validatedSampleRowCount']),
    'planner stat4 expression partial current source next236 rejected count' => static fn (TestRunner $t) => $t->same(0, $plan236()['stat4DensityVectorGuard']['rejectedSampleRowCount']),
    'planner stat4 expression partial current source next236 validated rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $plan236()['stat4DensityVectorGuard']['validatedSampleRowids']),
    'planner stat4 expression partial current source next236 rejected rowids' => static fn (TestRunner $t) => $t->same([], $plan236()['stat4DensityVectorGuard']['rejectedSampleRowids']),
    'planner stat4 expression partial current source next236 rejected reasons' => static fn (TestRunner $t) => $t->same([], $plan236()['stat4DensityVectorGuard']['rejectedReasons']),
    'planner stat4 expression partial current source next236 current keys' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_forms', 'plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_zulu'], $plan236()['stat4DensityVectorGuard']['currentExpressionKeys']),
    'planner stat4 expression partial current source next236 sample keys' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_mail', 'plugin_seo', 'plugin_zulu'], array_column($plan236()['stat4DensityVectorGuard']['sampleRows'], 'sampleKey')),
    'planner stat4 expression partial current source next236 sample rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], array_column($plan236()['stat4DensityVectorGuard']['sampleRows'], 'rowid')),
    'planner stat4 expression partial current source next236 expected neq' => static fn (TestRunner $t) => $t->same([3, 1, 1, 1], array_map(static fn (array $row): int => $row['expected']['neq'], $plan236()['stat4DensityVectorGuard']['sampleRows'])),
    'planner stat4 expression partial current source next236 expected nlt' => static fn (TestRunner $t) => $t->same([0, 3, 4, 5], array_map(static fn (array $row): int => $row['expected']['nlt'], $plan236()['stat4DensityVectorGuard']['sampleRows'])),
    'planner stat4 expression partial current source next236 expected ndlt' => static fn (TestRunner $t) => $t->same([0, 1, 2, 3], array_map(static fn (array $row): int => $row['expected']['ndlt'], $plan236()['stat4DensityVectorGuard']['sampleRows'])),
    'planner stat4 expression partial current source next236 actual neq' => static fn (TestRunner $t) => $t->same([3, 1, 1, 1], array_map(static fn (array $row): int => $row['actual']['neq'], $plan236()['stat4DensityVectorGuard']['sampleRows'])),
    'planner stat4 expression partial current source next236 actual nlt' => static fn (TestRunner $t) => $t->same([0, 3, 4, 5], array_map(static fn (array $row): int => $row['actual']['nlt'], $plan236()['stat4DensityVectorGuard']['sampleRows'])),
    'planner stat4 expression partial current source next236 actual ndlt' => static fn (TestRunner $t) => $t->same([0, 1, 2, 3], array_map(static fn (array $row): int => $row['actual']['ndlt'], $plan236()['stat4DensityVectorGuard']['sampleRows'])),
    'planner stat4 expression partial current source next236 all density matches' => static fn (TestRunner $t) => $t->same([true, true, true, true], array_column($plan236()['stat4DensityVectorGuard']['sampleRows'], 'densityMatchesCurrentRows')),
    'planner stat4 expression partial current source next236 all rows exist' => static fn (TestRunner $t) => $t->same([true, true, true, true], array_column($plan236()['stat4DensityVectorGuard']['sampleRows'], 'rowExistsInPartialSet')),
    'planner stat4 expression partial current source next236 all accepted' => static fn (TestRunner $t) => $t->same([true, true, true, true], array_column($plan236()['stat4DensityVectorGuard']['sampleRows'], 'accepted')),
    'planner stat4 expression partial current source next236 selected rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $plan236()['selectedPlan']['next236ValidatedSampleRowids']),
    'planner stat4 expression partial current source next236 selected rejected rowids' => static fn (TestRunner $t) => $t->same([], $plan236()['selectedPlan']['next236RejectedSampleRowids']),
    'planner stat4 expression partial current source next236 selected rejected reasons' => static fn (TestRunner $t) => $t->same([], $plan236()['selectedPlan']['next236RejectedReasons']),
    'planner stat4 expression partial current source next236 density signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan236()['stat4DensityVectorGuard']['densitySignature'])),
    'planner stat4 expression partial current source next236 proof signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan236()['stat4DensityVectorGuard']['proofSignature'])),
    'planner stat4 expression partial current source next236 selected density signature' => static fn (TestRunner $t) => $t->same($plan236()['stat4DensityVectorGuard']['densitySignature'], $plan236()['selectedPlan']['next236DensitySignature']),
    'planner stat4 expression partial current source next236 selected proof signature' => static fn (TestRunner $t) => $t->same($plan236()['stat4DensityVectorGuard']['proofSignature'], $plan236()['selectedPlan']['next236ProofSignature']),
    'planner stat4 expression partial current source next236 stat4 density signature' => static fn (TestRunner $t) => $t->same($plan236()['stat4DensityVectorGuard']['densitySignature'], $plan236()['stat4Fence']['next236DensitySignature']),
    'planner stat4 expression partial current source next236 stat4 proof signature' => static fn (TestRunner $t) => $t->same($plan236()['stat4DensityVectorGuard']['proofSignature'], $plan236()['stat4Fence']['next236ProofSignature']),
    'planner stat4 expression partial current source next236 stat4 ready' => static fn (TestRunner $t) => $t->same(true, $plan236()['stat4Fence']['next236DensityReady']),
    'planner stat4 expression partial current source next236 cursor appended' => static fn (TestRunner $t) => $t->same('ValidateCurrentSourceStat4DensityVectors', $plan236()['cursorProgram'][array_key_last($plan236()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next236 cursor mode' => static fn (TestRunner $t) => $t->same('next236-current-source-stat4-expression-partial-density-vector', $plan236()['cursorProgram'][array_key_last($plan236()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next236 cursor rowids' => static fn (TestRunner $t) => $t->same([20, 50, 30, 60], $plan236()['cursorProgram'][array_key_last($plan236()['cursorProgram'])]['validatedSampleRowids']),
    'planner stat4 expression partial current source next236 cursor partial count' => static fn (TestRunner $t) => $t->same(6, $plan236()['cursorProgram'][array_key_last($plan236()['cursorProgram'])]['currentPartialRowCount']),
    'planner stat4 expression partial current source next236 cursor distinct count' => static fn (TestRunner $t) => $t->same(4, $plan236()['cursorProgram'][array_key_last($plan236()['cursorProgram'])]['currentDistinctExpressionKeyCount']),
    'planner stat4 expression partial current source next236 cursor signature' => static fn (TestRunner $t) => $t->same($plan236()['stat4DensityVectorGuard']['proofSignature'], $plan236()['cursorProgram'][array_key_last($plan236()['cursorProgram'])]['signature']),
    'planner stat4 expression partial current source next236 detail' => static fn (TestRunner $t) => $t->contains('NEXT236 DENSITY VECTOR', $plan236()['detail']),
    'planner stat4 expression partial current source next236 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next236', $plan236()['dependencies'], true)),
    'planner stat4 expression partial current source next236 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan236()['dependency_closure']),
    'planner stat4 expression partial current source next236 non overlap' => static fn (TestRunner $t) => $t->contains('density vectors', $plan236()['non_overlap']),
    'planner stat4 expression partial current source next236 stale neq blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-density-reprepare', $staleNeq236()['status']),
    'planner stat4 expression partial current source next236 stale neq rejected' => static fn (TestRunner $t) => $t->same([20], $staleNeq236()['stat4DensityVectorGuard']['rejectedSampleRowids']),
    'planner stat4 expression partial current source next236 stale neq reason' => static fn (TestRunner $t) => $t->same('density-mismatch-neq', $staleNeq236()['stat4DensityVectorGuard']['rejectedReasons'][20]),
    'planner stat4 expression partial current source next236 stale neq actual preserved' => static fn (TestRunner $t) => $t->same(['neq' => 3, 'nlt' => 0, 'ndlt' => 0], $staleNeq236()['stat4DensityVectorGuard']['sampleRows'][0]['actual']),
    'planner stat4 expression partial current source next236 stale nlt blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-density-reprepare', $staleNlt236()['status']),
    'planner stat4 expression partial current source next236 stale nlt rejected' => static fn (TestRunner $t) => $t->same([30], $staleNlt236()['stat4DensityVectorGuard']['rejectedSampleRowids']),
    'planner stat4 expression partial current source next236 stale nlt reason' => static fn (TestRunner $t) => $t->same('density-mismatch-nlt', $staleNlt236()['stat4DensityVectorGuard']['rejectedReasons'][30]),
    'planner stat4 expression partial current source next236 stale ndlt blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-stat4-density-reprepare', $staleNdlt236()['status']),
    'planner stat4 expression partial current source next236 stale ndlt rejected' => static fn (TestRunner $t) => $t->same([60], $staleNdlt236()['stat4DensityVectorGuard']['rejectedSampleRowids']),
    'planner stat4 expression partial current source next236 stale ndlt reason' => static fn (TestRunner $t) => $t->same('density-mismatch-ndlt', $staleNdlt236()['stat4DensityVectorGuard']['rejectedReasons'][60]),
    'planner stat4 expression partial current source next236 stale all reason' => static fn (TestRunner $t) => $t->same('density-mismatch-neq-nlt-ndlt', $staleAll236()['stat4DensityVectorGuard']['rejectedReasons'][50]),
    'planner stat4 expression partial current source next236 stale all cursor not appended' => static fn (TestRunner $t) => $t->same(false, in_array('ValidateCurrentSourceStat4DensityVectors', array_column($staleAll236()['cursorProgram'], 'opcode'), true)),
    'planner stat4 expression partial current source next236 malformed neq' => static function (TestRunner $t) use ($current236, $plan236): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan236(6, 0, null, $current236([0 => ['neq' => 'bad']])));
    },
    'planner stat4 expression partial current source next236 missing rows' => static function (TestRunner $t) use ($current236, $plan236): void {
        $current = $current236();
        unset($current['rows']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan236(6, 0, null, $current));
    },
    'planner stat4 expression partial current source next236 invalid limit' => static function (TestRunner $t) use ($plan236): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan236(-1, 0));
    },
    'planner stat4 expression partial current source next236 invalid offset' => static function (TestRunner $t) use ($plan236): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan236(1, -1));
    },
];

foreach (range(1, 18) as $case) {
    $tests['planner stat4 expression partial current source next236 repeated density signature ' . $case] = static function (TestRunner $t) use ($plan236, $case): void {
        $plan = $plan236(5 + ($case % 2), 0);
        $t->same($plan['stat4DensityVectorGuard']['proofSignature'], $plan['selectedPlan']['next236ProofSignature']);
    };
}

return $tests;
