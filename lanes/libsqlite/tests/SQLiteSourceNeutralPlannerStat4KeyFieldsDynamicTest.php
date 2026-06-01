<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

$methodSource = static function (string $method): string {
    $reflection = new ReflectionMethod(SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::class, $method);
    $file = $reflection->getFileName();
    if (!is_string($file)) {
        throw new RuntimeException('Unable to locate STAT4 planner source file');
    }

    $lines = file($file);
    if ($lines === false) {
        throw new RuntimeException('Unable to read STAT4 planner source file');
    }

    return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
};

$domainMatches = static function (array $methods) use ($methodSource): array {
    $matches = [];
    $pattern = '/wp_|wp_options|blog_id|blogId|option_name|optionName|option_value|optionValue|autoload|Autoload/';

    foreach ($methods as $method) {
        $source = $methodSource($method);
        if (preg_match_all($pattern, $source, $methodMatches) > 0) {
            foreach ($methodMatches[0] as $match) {
                $matches[] = $method . ': ' . $match;
            }
        }
    }

    return $matches;
};

$callPrivate = static function (string $method, mixed ...$args): mixed {
    $reflection = new ReflectionMethod(SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::class, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke(null, ...$args);
};

$genericIndex = [
    'name' => 'idx_app_settings_lower_name',
    'expression' => 'lower(key_name)',
    'stat4KeyFields' => ['keyColumn' => 'key_name', 'tenantColumn' => 'tenant_id'],
    'partialPredicateTerms' => [
        ['left' => ['expression' => 'lower(key_name)'], 'operator' => '>=', 'right' => 'module_forms'],
        ['left' => ['expression' => 'lower(key_name)'], 'operator' => '<=', 'right' => 'module_zulu'],
        ['left' => ['column' => 'tenant_id'], 'operator' => '=', 'right' => 1],
    ],
];

return [
    'planner stat4 prepared handoff key fields are source neutral' => static fn (TestRunner $t) => $t->same([], $domainMatches([
        'handoffFenceForStat4ExpressionPartialPreparedBridge',
        'materializeRangeRows',
        'selectedRangeRowsExpressionIndex',
        'expressionRangeRowsRange',
        'rangeRowsMatchingRows',
        'expressionValueStat4CurrentRange',
        'handoffFenceForPreparedHandoff',
        'preparedHandoffFenceForRange',
        'keyColumnForPreparedHandoff',
        'indexForPreparedHandoffKeyFields',
        'expressionKeyForPreparedHandoff',
    ])),
    'planner stat4 prepared handoff key column uses generic metadata' => static fn (TestRunner $t) => $t->same('key_name', $callPrivate('keyColumnForPreparedHandoff', ['indexes' => [$genericIndex]])),
    'planner stat4 prepared handoff expression key uses generic row field' => static fn (TestRunner $t) => $t->same('module_zulu', $callPrivate('expressionKeyForPreparedHandoff', ['key_name' => 'Module_Zulu', 'tenant_id' => 1], 'key_name')),
    'planner stat4 current range expression uses generic lower key field' => static fn (TestRunner $t) => $t->same('module_zulu', $callPrivate('expressionValueStat4CurrentRange', ['key_name' => 'Module_Zulu'], 'lower(key_name)')),
    'planner stat4 current range json expression uses generic value field' => static fn (TestRunner $t) => $t->same('search', $callPrivate('expressionValueStat4CurrentRange', ['key_value' => '{"module":"search"}'], 'json_extract(key_value,$.module)')),
];
