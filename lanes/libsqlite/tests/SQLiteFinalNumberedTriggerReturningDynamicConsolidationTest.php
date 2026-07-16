<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan;

/**
 * @return list<class-string>
 */
function libsqlite_final_trigger_returning_dynamic_trigger_classes(): array
{
    $classes = [];
    $files = glob(__DIR__ . '/../src/*Trigger*.php') ?: [];

    foreach ($files as $file) {
        $source = file_get_contents($file);
        if (!is_string($source)) {
            continue;
        }
        if (preg_match('/namespace\s+([^;]+);/m', $source, $namespace) !== 1) {
            continue;
        }
        if (preg_match_all('/\b(?:final\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)\b/m', $source, $matches) < 1) {
            continue;
        }
        foreach ($matches[1] as $class) {
            $classes[] = $namespace[1] . '\\' . $class;
        }
    }

    sort($classes, SORT_STRING);

    /** @var list<class-string> $classes */
    return $classes;
}

/**
 * @return list<string>
 */
function libsqlite_final_trigger_returning_dynamic_public_methods(string $class): array
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
 * @param list<string> $names
 * @return list<string>
 */
function libsqlite_final_trigger_returning_dynamic_numbered_names(array $names): array
{
    return array_values(array_filter(
        $names,
        static fn (string $name): bool => preg_match('/(?:^|[a-z])(?:next|currentNext|currentSourceNext)\d/i', $name) === 1
    ));
}

return [
    'final numbered trigger returning dynamic production APIs stay consolidated' => static function (TestRunner $t): void {
        $classes = libsqlite_final_trigger_returning_dynamic_trigger_classes();

        $t->true(in_array(SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::class, $classes, true));
        $t->true(in_array(SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::class, $classes, true));
        $t->same([], libsqlite_final_trigger_returning_dynamic_numbered_names($classes));

        foreach ($classes as $class) {
            $t->same([], libsqlite_final_trigger_returning_dynamic_numbered_names(
                libsqlite_final_trigger_returning_dynamic_public_methods($class)
            ));
        }
    },
    'final trigger returning dynamic canonical entry points stay available' => static function (TestRunner $t): void {
        $returningMethods = libsqlite_final_trigger_returning_dynamic_public_methods(SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::class);
        $upsertMethods = libsqlite_final_trigger_returning_dynamic_public_methods(SQLiteTriggerRecursiveViewUpsertCurrentSourceNextPlan::class);

        $t->true(in_array('executeCurrentReturningGenerationSeal', $returningMethods, true));
        $t->true(in_array('executeCurrentSourceEpochReceipt', $returningMethods, true));
        $t->true(in_array('executeCurrentSourceCursorClose', $returningMethods, true));
        $t->true(in_array('currentReturningSnapshotAcknowledgement', $returningMethods, true));
        $t->true(in_array('executeCurrentSourceViewUpsertHandoff', $upsertMethods, true));
    },
];
