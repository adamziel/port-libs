<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan;

/**
 * @return list<string>
 */
function libsqlite_final_planner_stat4_dynamic_public_methods(string $class): array
{
    $reflection = new ReflectionClass($class);
    $methods = [];

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() === $class) {
            $methods[] = $method->getName();
        }
    }

    sort($methods, SORT_STRING);

    return $methods;
}

/**
 * @param list<string> $methods
 * @return list<string>
 */
function libsqlite_final_planner_stat4_dynamic_numbered_methods(array $methods): array
{
    return array_values(array_filter(
        $methods,
        static fn (string $method): bool => preg_match('/(?:^|[a-z])(?:next|currentNext|currentSourceNext)\d/i', $method) === 1
    ));
}

return [
    'final numbered planner stat4 dynamic production APIs stay consolidated' => static function (TestRunner $t): void {
        $methods = libsqlite_final_planner_stat4_dynamic_public_methods(SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::class);

        $t->true(in_array('materializeFinalPreparedHandoff', $methods, true));
        $t->true(in_array('materializeTerminalPreparedHandoff', $methods, true));
        $t->true(in_array('materializeStat4ExpressionPartialPreparedHandoff', $methods, true));
        $t->true(in_array('materializeStat4ExpressionPartialPreparedContinuation', $methods, true));
        $t->same([], libsqlite_final_planner_stat4_dynamic_numbered_methods($methods));
    },
    'final numbered planner stat4 dynamic cursor metadata stays canonical' => static function (TestRunner $t): void {
        $reflection = new ReflectionClass(SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::class);
        $method = $reflection->getMethod('preparedHandoffCursorMetadataForRange');
        $method->setAccessible(true);
        $ranges = $reflection->getMethod('preparedHandoffCursorMetadataRanges');
        $ranges->setAccessible(true);

        $finalMetadata = $method->invoke(null, 990, 1005);
        $terminalMetadata = $method->invoke(null, 958, 973);
        $genericMetadata = $method->invoke(null, 1006, 1021);
        $rangeMetadata = array_column($ranges->invoke(null), 'metadata');

        $t->same('PrepareStat4ExpressionPartialFinalPreparedHandoff', $finalMetadata['opcode']);
        $t->same('final-prepared-handoff-current-source-stat4-expression-partial-prep', $finalMetadata['mode']);
        $t->same('PrepareStat4ExpressionPartialFinalPreparedHandoff', $finalMetadata['canonicalOpcode']);
        $t->same('final-prepared-handoff-current-source-stat4-expression-partial-prep', $finalMetadata['canonicalMode']);
        $t->same('PrepareStat4ExpressionPartialTerminalPreparedHandoff', $terminalMetadata['opcode']);
        $t->same('terminal-prepared-handoff-current-source-stat4-expression-partial-prep', $terminalMetadata['mode']);
        $t->same('PrepareStat4ExpressionPartialPreparedHandoffRange', $genericMetadata['opcode']);
        $t->same('prepared-handoff-range-current-source-stat4-expression-partial-prep', $genericMetadata['mode']);
        $t->same([], array_values(array_filter($rangeMetadata, static fn (array $metadata): bool => array_key_exists('legacyOpcode', $metadata) || array_key_exists('legacyMode', $metadata))));
    },
    'final numbered planner stat4 dynamic source omits legacy cursor aliases' => static function (TestRunner $t): void {
        $source = file_get_contents(__DIR__ . '/../src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php');

        $t->same(false, str_contains((string) $source, 'legacyOpcode'));
        $t->same(false, str_contains((string) $source, 'legacyMode'));
        $t->same(0, preg_match('/PrepareStat4ExpressionPartialNext\d+Handoff/', (string) $source));
    },
];
