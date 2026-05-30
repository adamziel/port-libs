<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$eq189 = static fn (string $column, mixed $right): array => ['left' => ['column' => $column], 'operator' => '=', 'right' => $right];
$notNull189 = static fn (string $column): array => ['left' => ['column' => $column], 'operator' => 'IS NOT NULL'];
$between189 = static fn (string $expression, mixed $lower, mixed $upper): array => ['left' => ['expression' => $expression], 'operator' => 'BETWEEN', 'lower' => $lower, 'upper' => $upper];

$prepared189 = static fn (): array => [
    'name' => 'prepared-wp-options-payload-partial-stat4-expression-next189',
    'schemaCookie' => 1890,
    'stat4Generation' => 141,
    'rows' => [
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'updated_at' => 10],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms-old', 'updated_at' => 20],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo-old', 'updated_at' => 30],
    ],
    'indexes' => [[
        'name' => 'idx_wp_options_lower_desc_partial_stat4_next189',
        'rootPage' => 18901,
        'expression' => 'lower(option_name)',
        'expressionColumn' => '__expr_lower_option_name',
        'collation' => 'BINARY',
        'descending' => true,
        'partialPredicateTerms' => [
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '>=', 'right' => 'plugin_alpha'],
            ['left' => ['expression' => 'lower(option_name)'], 'operator' => '<=', 'right' => 'plugin_zulu'],
            ['left' => ['column' => 'autoload'], 'operator' => '=', 'right' => 'yes'],
            ['left' => ['column' => 'option_name'], 'operator' => 'IS NOT NULL'],
        ],
        'coveringColumns' => ['option_name', 'option_value', 'updated_at', 'autoload', 'blog_id'],
        'stat4Samples' => [
            ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
            ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_forms', 20]],
            ['neq' => '1 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_seo', 30]],
        ],
    ]],
];

$current189 = static function () use ($prepared189): array {
    $source = $prepared189();
    $source['name'] = 'current-wp-options-payload-partial-stat4-expression-next189';
    $source['schemaCookie'] = 1899;
    $source['stat4Generation'] = 166;
    $source['indexes'][0]['rootPage'] = 18988;
    $source['indexes'][0]['stat4Samples'] = [
        ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 10]],
        ['neq' => '1 1', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_cache', 40]],
        ['neq' => '2 1', 'nlt' => '2 2', 'ndlt' => '2 2', 'sample' => ['plugin_forms', 20]],
        ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_mail', 50]],
        ['neq' => '1 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['plugin_seo', 30]],
        ['neq' => '1 1', 'nlt' => '6 5', 'ndlt' => '5 5', 'sample' => ['plugin_zulu', 60]],
    ];
    $source['rows'] = [
        ['rowid' => 60, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_zulu', 'option_value' => 'zulu', 'updated_at' => 60],
        ['rowid' => 30, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'seo', 'updated_at' => 30],
        ['rowid' => 50, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Mail', 'option_value' => 'mail', 'updated_at' => 50],
        ['rowid' => 20, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'updated_at' => 20],
        ['rowid' => 21, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_Forms', 'option_value' => 'forms-copy', 'updated_at' => 21],
        ['rowid' => 40, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'option_value' => 'cache', 'updated_at' => 40],
        ['rowid' => 10, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha', 'updated_at' => 10],
        ['rowid' => 70, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'lazy', 'updated_at' => 70],
        ['rowid' => 80, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods_twentysix', 'option_value' => 'theme', 'updated_at' => 80],
        ['rowid' => 90, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'updated_at' => 90],
    ];

    return $source;
};

$terms189 = static fn (): array => [
    $between189('LOWER(option_name)', 'plugin_alpha', 'plugin_zulu'),
    $eq189('autoload', 'yes'),
    $notNull189('option_name'),
];
$plan189 = static fn (int $limit = 4, int $offset = 1, ?array $prepared = null, ?array $current = null, ?array $terms = null, ?array $needed = null): array => SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourceDuplicateRunFence(
    $prepared ?? $prepared189(),
    $current ?? $current189(),
    $terms ?? $terms189(),
    $needed ?? ['option_name', 'option_value', 'updated_at', 'blog_id'],
    $limit,
    $offset,
);
$drift189 = static function () use ($current189, $plan189): array {
    $current = $current189();
    foreach ($current['rows'] as &$row) {
        if (($row['rowid'] ?? null) === 50) {
            $row['option_name'] = 'theme_mods_twentysix';
        }
    }
    unset($row);

    return $plan189(4, 1, null, $current);
};
$autoloadDrift189 = static function () use ($current189, $plan189): array {
    $current = $current189();
    foreach ($current['rows'] as &$row) {
        if (($row['rowid'] ?? null) === 20) {
            $row['autoload'] = 'no';
        }
    }
    unset($row);

    return $plan189(4, 1, null, $current);
};
$nullName189 = static function () use ($current189, $plan189): array {
    $current = $current189();
    foreach ($current['rows'] as &$row) {
        if (($row['rowid'] ?? null) === 20) {
            $row['option_name'] = null;
        }
    }
    unset($row);

    return $plan189(4, 1, null, $current);
};

$tests = [
    'planner stat4 expression partial current source next189 status ready' => static fn (TestRunner $t) => $t->same('stat4-expression-partial-current-source-next189-ready', $plan189()['status']),
    'planner stat4 expression partial current source next189 selected source' => static fn (TestRunner $t) => $t->same('current', $plan189()['selectedSource']),
    'planner stat4 expression partial current source next189 stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan189()['stalePreparedStatement']),
    'planner stat4 expression partial current source next189 selected index' => static fn (TestRunner $t) => $t->same('idx_wp_options_lower_desc_partial_stat4_next189', $plan189()['selectedPlan']['name']),
    'planner stat4 expression partial current source next189 root page' => static fn (TestRunner $t) => $t->same(18988, $plan189()['selectedPlan']['rootPage']),
    'planner stat4 expression partial current source next189 inherited next185 ready' => static fn (TestRunner $t) => $t->same(true, $plan189()['selectedPlan']['next185Ready']),
    'planner stat4 expression partial current source next189 selected ready' => static fn (TestRunner $t) => $t->same(true, $plan189()['selectedPlan']['next189Ready']),
    'planner stat4 expression partial current source next189 matched rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21], $plan189()['matchedRowids']),
    'planner stat4 expression partial current source next189 matched keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms'], $plan189()['matchedExpressionKeys']),
    'planner stat4 expression partial current source next189 fence ready' => static fn (TestRunner $t) => $t->same(true, $plan189()['currentPayloadPartialFence']['ready']),
    'planner stat4 expression partial current source next189 expression normalized' => static fn (TestRunner $t) => $t->same('lower(option_name)', $plan189()['currentPayloadPartialFence']['expression']),
    'planner stat4 expression partial current source next189 fence rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21], $plan189()['currentPayloadPartialFence']['rowids']),
    'planner stat4 expression partial current source next189 no rejected rowids' => static fn (TestRunner $t) => $t->same([], $plan189()['currentPayloadPartialFence']['rejectedRowids']),
    'planner stat4 expression partial current source next189 selected rejected rowids empty' => static fn (TestRunner $t) => $t->same([], $plan189()['selectedPlan']['next189RejectedRowids']),
    'planner stat4 expression partial current source next189 sample rejected rowids empty' => static fn (TestRunner $t) => $t->same([], $plan189()['currentPayloadPartialFence']['sampleRejectedRowids']),
    'planner stat4 expression partial current source next189 predicate check count' => static fn (TestRunner $t) => $t->same(10, $plan189()['selectedPlan']['next189PredicateCheckCount']),
    'planner stat4 expression partial current source next189 check count' => static fn (TestRunner $t) => $t->same(4, count($plan189()['currentPayloadPartialFence']['checks'])),
    'planner stat4 expression partial current source next189 check sources' => static fn (TestRunner $t) => $t->same(['current', 'current', 'current', 'current'], array_column($plan189()['currentPayloadPartialFence']['checks'], 'source')),
    'planner stat4 expression partial current source next189 check actual keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms'], array_column($plan189()['currentPayloadPartialFence']['checks'], 'actualExpressionKey')),
    'planner stat4 expression partial current source next189 check expected keys' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'plugin_mail', 'plugin_forms', 'plugin_forms'], array_column($plan189()['currentPayloadPartialFence']['checks'], 'expectedExpressionKey')),
    'planner stat4 expression partial current source next189 partial flags' => static fn (TestRunner $t) => $t->same([true, true, true, true], array_column($plan189()['currentPayloadPartialFence']['checks'], 'partialPredicateReady')),
    'planner stat4 expression partial current source next189 ready flags' => static fn (TestRunner $t) => $t->same([true, true, true, true], array_column($plan189()['currentPayloadPartialFence']['checks'], 'ready')),
    'planner stat4 expression partial current source next189 reason lists empty' => static fn (TestRunner $t) => $t->same([[], [], [], []], array_column($plan189()['currentPayloadPartialFence']['checks'], 'reasons')),
    'planner stat4 expression partial current source next189 sample check count' => static fn (TestRunner $t) => $t->same(6, count($plan189()['currentPayloadPartialFence']['sampleChecks'])),
    'planner stat4 expression partial current source next189 sample check rowids' => static fn (TestRunner $t) => $t->same([10, 40, 20, 50, 30, 60], array_column($plan189()['currentPayloadPartialFence']['sampleChecks'], 'rowid')),
    'planner stat4 expression partial current source next189 sample check ready flags' => static fn (TestRunner $t) => $t->same([true, true, true, true, true, true], array_column($plan189()['currentPayloadPartialFence']['sampleChecks'], 'ready')),
    'planner stat4 expression partial current source next189 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan189()['currentPayloadPartialFence']['signature'])),
    'planner stat4 expression partial current source next189 stat4 signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan189()['stat4Fence']['next189PayloadPartialSignature'])),
    'planner stat4 expression partial current source next189 payload signature length' => static fn (TestRunner $t) => $t->same(64, strlen($plan189()['currentPayloadPartialFence']['checks'][0]['payloadSignature'])),
    'planner stat4 expression partial current source next189 cursor opcode' => static fn (TestRunner $t) => $t->same('CurrentPayloadPartialFence', $plan189()['cursorProgram'][array_key_last($plan189()['cursorProgram'])]['opcode']),
    'planner stat4 expression partial current source next189 cursor mode' => static fn (TestRunner $t) => $t->same('next189-current-source-stat4-expression-partial-payload', $plan189()['cursorProgram'][array_key_last($plan189()['cursorProgram'])]['mode']),
    'planner stat4 expression partial current source next189 cursor ready' => static fn (TestRunner $t) => $t->same(true, $plan189()['cursorProgram'][array_key_last($plan189()['cursorProgram'])]['ready']),
    'planner stat4 expression partial current source next189 cursor rowids' => static fn (TestRunner $t) => $t->same([30, 50, 20, 21], $plan189()['cursorProgram'][array_key_last($plan189()['cursorProgram'])]['rowids']),
    'planner stat4 expression partial current source next189 projected row retained' => static fn (TestRunner $t) => $t->same('mail', $plan189()['projectedRows'][1]['option_value']),
    'planner stat4 expression partial current source next189 duplicate projected row retained' => static fn (TestRunner $t) => $t->same('forms-copy', $plan189()['projectedRows'][3]['option_value']),
    'planner stat4 expression partial current source next189 sample delta inherited' => static fn (TestRunner $t) => $t->same(true, $plan189()['sampleDeltaFence']['changed']),
    'planner stat4 expression partial current source next189 provenance inherited' => static fn (TestRunner $t) => $t->same(['plugin_seo', 'Plugin_Mail', 'plugin_forms', 'Plugin_Forms'], array_column($plan189()['currentSourceRowProvenance'], 'option_name')),
    'planner stat4 expression partial current source next189 detail' => static fn (TestRunner $t) => $t->contains('NEXT189 PAYLOAD PARTIAL FENCE', $plan189()['detail']),
    'planner stat4 expression partial current source next189 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-sqlplanner-stat4-expression-partial-current-source-next189', $plan189()['dependencies'], true)),
    'planner stat4 expression partial current source next189 dependency closure' => static fn (TestRunner $t) => $t->contains('no new support component needed', $plan189()['dependency_closure']),
    'planner stat4 expression partial current source next189 non overlap' => static fn (TestRunner $t) => $t->contains('current payloads still satisfy', $plan189()['non_overlap']),
    'planner stat4 expression partial current source next189 expression drift blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $drift189()['status']),
    'planner stat4 expression partial current source next189 expression drift rejected rowid' => static fn (TestRunner $t) => $t->same([50], $drift189()['currentPayloadPartialFence']['sampleRejectedRowids']),
    'planner stat4 expression partial current source next189 expression drift selected rejected rowid' => static fn (TestRunner $t) => $t->same([50], $drift189()['selectedPlan']['next189RejectedRowids']),
    'planner stat4 expression partial current source next189 expression drift reason' => static fn (TestRunner $t) => $t->same(['stat4-sample-key-drift', 'partial-predicate-<='], $drift189()['currentPayloadPartialFence']['sampleChecks'][3]['reasons']),
    'planner stat4 expression partial current source next189 expression drift actual key' => static fn (TestRunner $t) => $t->same('theme_mods_twentysix', $drift189()['currentPayloadPartialFence']['sampleChecks'][3]['actualExpressionKey']),
    'planner stat4 expression partial current source next189 autoload drift blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $autoloadDrift189()['status']),
    'planner stat4 expression partial current source next189 autoload drift rejected rowid' => static fn (TestRunner $t) => $t->same([20], $autoloadDrift189()['currentPayloadPartialFence']['sampleRejectedRowids']),
    'planner stat4 expression partial current source next189 autoload drift reason' => static fn (TestRunner $t) => $t->same(['partial-predicate-='], $autoloadDrift189()['currentPayloadPartialFence']['sampleChecks'][2]['reasons']),
    'planner stat4 expression partial current source next189 null name blocked' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $nullName189()['status']),
    'planner stat4 expression partial current source next189 null name rejected rowid' => static fn (TestRunner $t) => $t->same([20], $nullName189()['currentPayloadPartialFence']['sampleRejectedRowids']),
    'planner stat4 expression partial current source next189 null name reasons' => static fn (TestRunner $t) => $t->same(['stat4-sample-key-drift', 'partial-predicate->=', 'partial-predicate-is-not-null'], $nullName189()['currentPayloadPartialFence']['sampleChecks'][2]['reasons']),
    'planner stat4 expression partial current source next189 zero window blocks' => static fn (TestRunner $t) => $t->same('requires-current-source-reprepare', $plan189(0, 0)['status']),
    'planner stat4 expression partial current source next189 zero window checks empty' => static fn (TestRunner $t) => $t->same([], $plan189(0, 0)['currentPayloadPartialFence']['checks']),
    'planner stat4 expression partial current source next189 tail rowids' => static fn (TestRunner $t) => $t->same([21, 40, 10], $plan189(5, 4)['currentPayloadPartialFence']['rowids']),
    'planner stat4 expression partial current source next189 tail keys' => static fn (TestRunner $t) => $t->same(['plugin_forms', 'plugin_cache', 'plugin_alpha'], array_column($plan189(5, 4)['currentPayloadPartialFence']['checks'], 'actualExpressionKey')),
    'planner stat4 expression partial current source next189 invalid expression' => static function (TestRunner $t) use ($current189, $plan189): void {
        $bad = $current189();
        $bad['indexes'][0]['expression'] = 'json_extract(option_value, "$.kind")';
        $t->throws(InvalidArgumentException::class, static fn () => $plan189(1, 0, null, $bad));
    },
    'planner stat4 expression partial current source next189 invalid predicate operator' => static function (TestRunner $t) use ($current189, $plan189): void {
        $bad = $current189();
        $bad['indexes'][0]['partialPredicateTerms'][0]['operator'] = 'LIKE';
        $t->throws(InvalidArgumentException::class, static fn () => $plan189(1, 0, null, $bad));
    },
    'planner stat4 expression partial current source next189 invalid current rows' => static function (TestRunner $t) use ($current189, $plan189): void {
        $bad = $current189();
        $bad['rows'] = 'bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan189(1, 0, null, $bad));
    },
    'planner stat4 expression partial current source next189 missing predicate terms' => static function (TestRunner $t) use ($current189, $plan189): void {
        $bad = $current189();
        unset($bad['indexes'][0]['partialPredicateTerms']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan189(1, 0, null, $bad));
    },
];

foreach (range(1, 14) as $case) {
    $tests['planner stat4 expression partial current source next189 repeated payload fence ' . $case] = static function (TestRunner $t) use ($plan189, $case): void {
        $plan = $plan189(1 + ($case % 4), $case % 5);
        $t->same(count($plan['currentPayloadPartialFence']['rowids']), count($plan['currentPayloadPartialFence']['checks']));
    };
}

return $tests;
