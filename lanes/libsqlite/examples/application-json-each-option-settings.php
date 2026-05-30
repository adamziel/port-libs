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
            'dotted_key' => 'escaped',
        ],
    'priority' => 7,
]);

$inputs = [
    'strict_settings_text' => '{"plugin":{"enabled":true,"title":"Cache","rules":[{"name":"seo"},{"name":"cache"}],"dotted_key":"escaped"},"priority":7}',
    'json5_settings_text' => "{plugin:{enabled:false,title:'Cache',rules:['seo','cache',],dotted_key:'escaped',},priority:+7}",
    'jsonb_settings_blob' => new SQLiteBlobValue($jsonbSettings),
    'malformed_jsonb_settings_blob' => new SQLiteBlobValue("\x1c\x00"),
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
    $escapedLikeConstraints = [
        ['column' => 'json', 'operator' => '=', 'value' => $value],
        ['column' => 'root', 'operator' => '=', 'value' => '$.plugin'],
        ['column' => 'key', 'operator' => 'LIKE', 'value' => ['pattern' => 'dotted!_key', 'escape' => '!']],
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
    $containerAtomIsNullConstraints = [
        ['column' => 'json', 'operator' => '=', 'value' => $value],
        ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
        ['column' => 'atom', 'operator' => 'IS NULL'],
    ];
    $scalarAtomConstraints = [
        ['column' => 'json', 'operator' => '=', 'value' => $value],
        ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
        ['column' => 'atom', 'operator' => 'IS DISTINCT FROM', 'value' => null],
    ];
    $scalarAtomIsNotNullConstraints = [
        ['column' => 'json', 'operator' => '=', 'value' => $value],
        ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
        ['column' => 'atom', 'operator' => 'IS NOT NULL'],
    ];
    $priorityRangeConstraints = [
        ['column' => 'json', 'operator' => '=', 'value' => $value],
        ['column' => 'root', 'operator' => '=', 'value' => '$'],
        ['column' => 'atom', 'operator' => '>=', 'value' => 7],
        ['column' => 'atom', 'operator' => '<', 'value' => 8],
    ];
    $priorityNumericEqualityConstraints = [
        ['column' => 'json', 'operator' => '=', 'value' => $value],
        ['column' => 'root', 'operator' => '=', 'value' => '$'],
        ['column' => 'atom', 'operator' => '=', 'value' => 7.0],
        ['column' => 'atom', 'operator' => 'IN', 'value' => [7.0, 9.0]],
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
    $match = static function (string $pattern, string $candidate): bool {
        return in_array($candidate, explode(' ', $pattern), true);
    };
    $ruleMatchConstraints = [
        ['column' => 'json', 'operator' => '=', 'value' => $value],
        ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
        ['column' => 'value', 'operator' => 'MATCH', 'value' => ['pattern' => '{"name":"seo"} {"name":"cache"} seo cache', 'match' => $match]],
        ['column' => 'fullkey', 'operator' => 'NOT MATCH', 'value' => ['pattern' => '$.plugin.rules[0]', 'match' => $match]],
    ];
    $orderedRuleConstraints = [
        ['column' => 'json', 'operator' => '=', 'value' => $value],
        ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
        ['column' => 'type', 'operator' => 'IN', 'value' => ['object', 'text']],
    ];
    $rowidAliasConstraints = [
        ['column' => 'json', 'operator' => '=', 'value' => $value],
        ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [1, 2]],
        ['column' => '_rowid_', 'operator' => 'NOT IN', 'value' => [1]],
    ];
    $validatedPlanner = SQLiteJsonTablePlan::validatedPlan('JSON_EACH', $plannerConstraints);
    if ($validatedPlanner['jsonValid'] === false) {
        $reports[] = [
            'name' => $name,
            'validatedPlanner' => normalizeJsonTablePlan($validatedPlanner),
            'malformedInputSkippedRows' => true,
        ];
        continue;
    }

    $reports[] = [
        'name' => $name,
        'rootRows' => normalizeJsonEachRows(SQLiteJsonEach::jsonEachSqlFunction('JSON_EACH', $value)),
        'pluginRows' => normalizeJsonEachRows(SQLiteJsonEach::jsonEachSqlFunctionArguments('JSON_EACH', [$value, '$.plugin'])),
        'rulesRows' => normalizeJsonEachRows(SQLiteJsonEach::jsonEachSqlFunctionArguments('JSON_EACH', [$value, '$.plugin.rules'])),
        'plannedRulesRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::rows('JSON_EACH', $plannerConstraints)),
        'visibleRuleRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::visibleRows('JSON_EACH', $plannerConstraints)),
        'projectedRuleRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::projectedRows('JSON_EACH', $plannerConstraints, [
            'rowid',
            'key',
            'value',
            'type',
            'atom',
            'fullkey',
            'json',
            'root',
        ])),
        'filteredObjectRuleRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $objectRuleConstraints)),
        'filteredCachePatternRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $namePatternConstraints)),
        'filteredEscapedLikeRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $escapedLikeConstraints)),
        'filteredNotPatternRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $nameNotPatternConstraints)),
        'filteredRuleInRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $ruleInConstraints)),
        'filteredContainerAtomRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $containerAtomConstraints)),
        'filteredContainerAtomIsNullRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $containerAtomIsNullConstraints)),
        'filteredScalarAtomRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $scalarAtomConstraints)),
        'filteredScalarAtomIsNotNullRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $scalarAtomIsNotNullConstraints)),
        'filteredPriorityRangeRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $priorityRangeConstraints)),
        'filteredPriorityNumericEqualityRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $priorityNumericEqualityConstraints)),
        'filteredPriorityBetweenRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $priorityBetweenConstraints)),
        'filteredRuleRegexpRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $ruleRegexpConstraints)),
        'filteredRuleMatchRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $ruleMatchConstraints)),
        'filteredRowidAliasRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::filteredRows('JSON_EACH', $rowidAliasConstraints)),
        'orderedPagedRuleRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::orderedRows('JSON_EACH', $orderedRuleConstraints, [
            ['column' => 'type', 'direction' => 'ASC'],
            ['column' => 'key', 'direction' => 'DESC'],
        ], 2)),
        'orderedRowidAliasRows' => normalizeJsonEachRows(SQLiteJsonTablePlan::orderedRows('JSON_EACH', $plannerConstraints, [
            ['column' => '_rowid_', 'direction' => 'DESC'],
        ], 1)),
        'planner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $plannerConstraints)),
        'validatedPlanner' => normalizeJsonTablePlan($validatedPlanner),
        'filteredPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $objectRuleConstraints)),
        'patternFilteredPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $namePatternConstraints)),
        'escapedLikeFilteredPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $escapedLikeConstraints)),
        'notPatternFilteredPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $nameNotPatternConstraints)),
        'inFilteredPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $ruleInConstraints)),
        'containerAtomPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $containerAtomConstraints)),
        'containerAtomIsNullPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $containerAtomIsNullConstraints)),
        'scalarAtomPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $scalarAtomConstraints)),
        'scalarAtomIsNotNullPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $scalarAtomIsNotNullConstraints)),
        'rangeFilteredPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $priorityRangeConstraints)),
        'numericEqualityPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $priorityNumericEqualityConstraints)),
        'betweenFilteredPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $priorityBetweenConstraints)),
        'regexpFilteredPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $ruleRegexpConstraints)),
        'matchFilteredPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $ruleMatchConstraints)),
        'rowidAliasFilteredPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $rowidAliasConstraints)),
        'orderedPagedPlanner' => normalizeJsonTablePlan(SQLiteJsonTablePlan::plan('JSON_EACH', $orderedRuleConstraints)),
        'dispatch' => [
            'sqlFunction' => 'JSON_EACH',
            'caseInsensitive' => true,
            'argumentVector' => true,
        ],
    ];
}

echo json_encode([
    'reports' => $reports,
    'applicationUse' => 'Local-only wp_options option_value expansion that mirrors bounded SQLite json_each() rows, SELECT * visible-column projection, explicit hidden json/root and rowid projection, hidden json/root constraint planning, planner-level malformed JSONB diagnostics, visible type, LIKE/GLOB, LIKE ESCAPE, NOT LIKE/NOT GLOB, REGEXP/NOT REGEXP, MATCH/NOT MATCH, IN-list, numeric equality, IS NULL, IS NOT NULL, IS DISTINCT FROM, IS NOT DISTINCT FROM, range, BETWEEN, rowid/_rowid_/oid alias residual filtering, and ORDER BY/LIMIT preview paging for strict JSON, JSON5 text, JSONB blobs, missing paths, and SQL NULL before copied plugin settings are imported.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function normalizeJsonEachRows(array $rows): array
{
    return array_map(
        static function (array $row): array {
            if (($row['json'] ?? null) instanceof SQLiteBlobValue) {
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
