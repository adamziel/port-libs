<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$expr = ['function' => 'lower', 'column' => 'option_name'];
$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$exprPoint = static fn (mixed $value): array => ['operator' => '=', 'left' => $expr, 'right' => $value];
$exprRange = static fn (string $operator, mixed $value): array => ['operator' => $operator, 'left' => $expr, 'right' => $value];
$and = static fn (array ...$terms): array => ['operator' => 'AND', 'terms' => $terms];
$or = static fn (array ...$terms): array => ['operator' => 'OR', 'terms' => $terms];

$preparedRows = [
    ['rowid' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'alpha-old', 'site_id' => 1],
    ['rowid' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_beta', 'option_value' => 'beta-old', 'site_id' => 1],
    ['rowid' => 3, 'autoload' => 'no', 'option_name' => 'plugin_beta', 'option_value' => 'beta-lazy', 'site_id' => 1],
    ['rowid' => 4, 'autoload' => 'yes', 'option_name' => 'plugin_gamma', 'option_value' => 'gamma-old', 'site_id' => 1],
];
$currentRows = $preparedRows;
$currentRows[] = ['rowid' => 5, 'autoload' => 'yes', 'option_name' => 'PLUGIN_BETA', 'option_value' => 'beta-current', 'site_id' => 2];
$currentRows[] = ['rowid' => 6, 'autoload' => 'yes', 'option_name' => 'plugin_delta', 'option_value' => 'delta', 'site_id' => 1];
$currentRows[] = ['rowid' => 7, 'autoload' => 'yes', 'option_name' => 'plugin_forms', 'option_value' => 'forms', 'site_id' => 1];

$samples = [
    ['neq' => '1 1', 'nlt' => '0 0', 'ndlt' => '0 0', 'sample' => ['plugin_alpha', 'yes']],
    ['neq' => '2 2', 'nlt' => '1 1', 'ndlt' => '1 1', 'sample' => ['plugin_beta', 'yes']],
    ['neq' => '1 1', 'nlt' => '3 2', 'ndlt' => '2 2', 'sample' => ['plugin_delta', 'yes']],
    ['neq' => '1 1', 'nlt' => '4 3', 'ndlt' => '3 3', 'sample' => ['plugin_forms', 'yes']],
    ['neq' => '1 1', 'nlt' => '5 4', 'ndlt' => '4 4', 'sample' => ['plugin_gamma', 'yes']],
];
$index = static fn (int $rootPage, int $estimatedRows, array $stat4): array => [
    'name' => 'idx_wp_options_lower_name_autoload_partial_next160',
    'rootPage' => $rootPage,
    'estimatedRows' => $estimatedRows,
    'coveringColumns' => ['autoload', 'option_name', 'option_value', 'site_id'],
    'stat4Samples' => $stat4,
    'sql' => "CREATE INDEX idx_wp_options_lower_name_autoload_partial_next160 ON wp_options(lower(option_name), autoload, site_id, option_value) WHERE autoload = 'yes' AND lower(option_name) >= 'plugin_'",
];
$source = static fn (string $name, int $cookie, int $stat4Generation, array $rows, array $stat4, int $rootPage): array => [
    'name' => $name,
    'schemaCookie' => $cookie,
    'stat4Generation' => $stat4Generation,
    'rows' => $rows,
    'indexes' => [$index($rootPage, 180, $stat4)],
];
$predicate = $or(
    $and($point('autoload', 'yes'), $exprPoint('plugin_beta')),
    $and($point('autoload', 'yes'), $exprRange('>=', 'plugin_delta'), $exprRange('<=', 'plugin_forms')),
);

$plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4OrRowidUnion(
    $source('prepared-main.wp_options@next160', 1600, 80, $preparedRows, array_slice($samples, 0, 4), 16001),
    $source('current-main.wp_options@next160', 1603, 83, $currentRows, $samples, 16031),
    $predicate,
    [$expr, ['column' => 'site_id']],
    ['option_name', 'option_value', 'site_id'],
);

if (in_array('--self-test', $argv, true)) {
    assert($plan['status'] === 'stat4-expression-partial-current-source-next160-ready');
    assert($plan['selectedSource'] === 'current');
    assert(($plan['selectedPlan']['currentSourceRowids'] ?? []) === [2, 5, 6, 7]);
    echo "wordpress-planner-stat4-expression-partial-current-source-next160 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'strategy' => $plan['selectedPlan']['strategy'] ?? null,
    'rowids' => $plan['selectedPlan']['currentSourceRowids'] ?? [],
    'keys' => $plan['selectedPlan']['currentSourceKeys'] ?? [],
    'firstPayload' => $plan['unionRows'][0]['payload'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
