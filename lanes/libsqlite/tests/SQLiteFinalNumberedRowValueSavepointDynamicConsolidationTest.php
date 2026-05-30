<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return list<string>
 */
function libsqlite_final_rowvalue_savepoint_dynamic_public_methods(string $class): array
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
function libsqlite_final_rowvalue_savepoint_dynamic_numbered_methods(array $methods): array
{
    return array_values(array_filter(
        $methods,
        static fn (string $method): bool => preg_match('/(?:^|[a-z])(?:next|currentNext|currentSourceNext)\d/i', $method) === 1
    ));
}

return [
    'final numbered rowvalue savepoint dynamic production APIs stay consolidated' => static function (TestRunner $t): void {
        $rowValueMethods = libsqlite_final_rowvalue_savepoint_dynamic_public_methods(SQLiteRowValueUpdateDeleteReturningSavepointPlan::class);
        $dynamicSelectMethods = libsqlite_final_rowvalue_savepoint_dynamic_public_methods(SQLiteSelectSql::class);

        $t->true(in_array('executeUpdateDeleteReturningSavepointBatch', $rowValueMethods, true));
        $t->true(in_array('executeRollbackToSavepoint', $rowValueMethods, true));
        $t->true(in_array('execute', $dynamicSelectMethods, true));
        $t->same([], libsqlite_final_rowvalue_savepoint_dynamic_numbered_methods($rowValueMethods));
        $t->same([], libsqlite_final_rowvalue_savepoint_dynamic_numbered_methods($dynamicSelectMethods));
    },
];
