<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonEach;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$jsonbSettings = SQLiteJsonB::encode([
    'plugin' => [
        'enabled' => true,
        'rules' => [
            ['name' => 'seo', 'enabled' => true],
            ['name' => 'cache', 'enabled' => false],
        ],
        'dotted.key' => 'quoted',
    ],
    'priority' => 7,
]);

$inputs = [
    'strict_settings_text' => '{"plugin":{"enabled":true,"title":"Cache","rules":[{"name":"seo"},{"name":"cache"}]},"priority":7}',
    'json5_settings_text' => "{plugin:{enabled:false,title:'Cache',rules:['seo','cache',],},priority:+7}",
    'jsonb_settings_blob' => new SQLiteBlobValue($jsonbSettings),
    'sql_null_option_value' => null,
];

$reports = [];
foreach ($inputs as $name => $value) {
    $plannerConstraints = [
        ['column' => 'json', 'operator' => '=', 'value' => $value],
        ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
    ];
    $objectRuleConstraints = [
        ['column' => 'json', 'operator' => '=', 'value' => $value],
        ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ];
    $namePatternConstraints = [
        ['column' => 'json', 'operator' => '=', 'value' => $value],
        ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.rules[%]'],
        ['column' => 'value', 'operator' => 'GLOB', 'value' => '*cache*'],
    ];
    $nameNotPatternConstraints = [
        ['column' => 'json', 'operator' => '=', 'value' => $value],
        ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
        ['column' => 'fullkey', 'operator' => 'NOT LIKE', 'value' => '$.plugin.rules[0]%'],
        ['column' => 'value', 'operator' => 'NOT GLOB', 'value' => '*seo*'],
    ];
    $ruleInConstraints = [
        ['column' => 'json', 'operator' => '=', 'value' => $value],
        ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
        ['column' => 'key', 'operator' => 'IN', 'value' => [0, 1]],
    ];
    $containerAtomConstraints = [
        ['column' => 'json', 'operator' => '=', 'value' => $value],
        ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
        ['column' => 'atom', 'operator' => 'IS NOT DISTINCT FROM', 'value' => null],
    ];
    $scalarAtomConstraints = [
        ['column' => 'json', 'operator' => '=', 'value' => $value],
        ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
        ['column' => 'atom', 'operator' => 'IS DISTINCT FROM', 'value' => null],
    ];
    $priorityRangeConstraints = [
        ['column' => 'json', 'operator' => '=', 'value' => $value],
        ['column' => 'root', 'operator' => '=', 'value' => '$'],
        ['column' => 'atom', 'operator' => '>=', 'value' => 7],
        ['column' => 'atom', 'operator' => '<', 'value' => 8],
    ];
    $priorityBetweenConstraints = [
        ['column' => 'json', 'operator' => '=', 'value' => $value],
        ['column' => 'root', 'operator' => '=', 'value' => '$'],
        ['column' => 'atom', 'operator' => 'BETWEEN', 'value' => [6, 7]],
    ];
    $regexp = static function (string $pattern, string $candidate): bool {
        $matched = preg_match('/' . str_replace('/', '\\/', $pattern) . '/', $candidate);
        if ($matched === false) {
            throw new RuntimeException("Invalid JSON table REGEXP pattern: {$pattern}");
        }

        return $matched === 1;
    };
    $ruleRegexpConstraints = [
        ['column' => 'json', 'operator' => '=', 'value' => $value],
        ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
        ['column' => 'value', 'operator' => 'REGEXP', 'value' => ['pattern' => 'cache|seo', 'regexp' => $regexp]],
        ['column' => 'fullkey', 'operator' => 'NOT REGEXP', 'value' => ['pattern' => '\\[0\\]', 'regexp' => $regexp]],
    ];
    $reports[] = [
        'name' => $name,
        'rootRows' => normalizeJsonEachRows(SQLiteJsonEach::jsonEachSqlFunction('JSON_EACH', $value)),
        'pluginRows' => normalizeJsonEachRows(SQLiteJsonEach::jsonEachSqlFunctionArguments('JSON_EACH', [$value, '$.plugin'])),
        'rulesRows' => normalizeJsonEachRows(SQLiteJsonEach::jsonEachSqlFunctionArguments('JSON_EACH', [$value, '$.plugin.rules'])),
        'plannedRulesRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::rows('JSON_EACH', $plannerConstraints)),
        'filteredObjectRuleRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $objectRuleConstraints)),
        'filteredCachePatternRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $namePatternConstraints)),
        'filteredNotPatternRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $nameNotPatternConstraints)),
        'filteredRuleInRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $ruleInConstraints)),
        'filteredContainerAtomRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $containerAtomConstraints)),
        'filteredScalarAtomRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $scalarAtomConstraints)),
        'filteredPriorityRangeRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $priorityRangeConstraints)),
        'filteredPriorityBetweenRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $priorityBetweenConstraints)),
        'filteredRuleRegexpRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $ruleRegexpConstraints)),
        'planner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $plannerConstraints)),
        'filteredPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $objectRuleConstraints)),
        'patternFilteredPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $namePatternConstraints)),
        'notPatternFilteredPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $nameNotPatternConstraints)),
        'inFilteredPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $ruleInConstraints)),
        'containerAtomPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $containerAtomConstraints)),
        'scalarAtomPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $scalarAtomConstraints)),
        'rangeFilteredPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $priorityRangeConstraints)),
        'betweenFilteredPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $priorityBetweenConstraints)),
        'regexpFilteredPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $ruleRegexpConstraints)),
        'dispatch' => [
            'sqlFunction' => 'JSON_EACH',
            'caseInsensitive' => true,
            'argumentVector' => true,
        ],
    ];
}

echo json_encode([
    'reports' => $reports,
    'wordpressUse' => 'Local-only wp_options option_value expansion that mirrors bounded SQLite json_each() rows, hidden json/root constraint planning, and visible type, LIKE/GLOB, NOT LIKE/NOT GLOB, REGEXP/NOT REGEXP, IN-list, IS DISTINCT FROM, IS NOT DISTINCT FROM, range, and BETWEEN residual filtering for strict JSON, JSON5 text, JSONB blobs, missing paths, and SQL NULL before copied plugin settings are imported.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function normalizeJsonEachRows(array $rows): array
{
    return array_map(
        static function (array $row): array {
            if ($row['json'] instanceof SQLiteBlobValue) {
                $row['json'] = [
                    'type' => 'blob',
                    'hexPrefix' => strtoupper(substr(bin2hex($row['json']->bytes), 0, 24)),
                ];
            }

            return $row;
        },
        $rows,
    );
}

/**
 * @param array<string, mixed> $plan
 * @return array<string, mixed>
 */
function normalizeJsonTablePlan(array $plan): array
{
    return normalizeJsonTableValue($plan);
}

function normalizeJsonTableValue(mixed $value): mixed
{
    if ($value instanceof SQLiteBlobValue) {
        return [
            'type' => 'blob',
            'hexPrefix' => strtoupper(substr(bin2hex($value->bytes), 0, 24)),
        ];
    }
    if ($value instanceof Closure) {
        return 'callable';
    }

    if (!is_array($value)) {
        return $value;
    }

    foreach ($value as $key => $child) {
        $value[$key] = normalizeJsonTableValue($child);
    }

    return $value;
}
