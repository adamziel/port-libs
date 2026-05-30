<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaResultColumnPlan;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma4.test pragma4-1.* verifies PRAGMA
 * result-column arity. Read-form pragmas operate as one-column statements,
 * assignment/call write forms operate as zero-column statements, and
 * shrink_memory/case_sensitive_like never produce rows. This corpus ports that
 * statement-shape behavior into the PHP PRAGMA planner at high volume with
 * schema-qualified and case-varied forms.
 */

$readWritePragmas = [
    'application_id' => ['10', 'main'],
    'automatic_index' => ['1', 'temp'],
    'auto_vacuum' => ['1', 'main'],
    'cache_size' => ['-100', 'main'],
    'cache_spill' => ['1', 'temp'],
    'cell_size_check' => ['1', 'main'],
    'checkpoint_fullfsync' => ['1', 'main'],
    'count_changes' => ['1', 'temp'],
    'default_cache_size' => ['100', 'main'],
    'defer_foreign_keys' => ['1', 'main'],
    'empty_result_callbacks' => ['1', 'temp'],
    'encoding' => ["'utf-8'", 'main'],
    'foreign_keys' => ['1', 'main'],
    'full_column_names' => ['1', 'temp'],
    'fullfsync' => ['1', 'main'],
    'ignore_check_constraints' => ['1', 'main'],
    'page_size' => ['512', 'main'],
    'query_only' => ['false', 'temp'],
    'read_uncommitted' => ['true', 'main'],
    'recursive_triggers' => ['false', 'main'],
    'reverse_unordered_selects' => ['false', 'temp'],
    'schema_version' => ['211', 'main'],
    'short_column_names' => ['1', 'main'],
    'synchronous' => ['full', 'temp'],
    'temp_store' => ['memory', 'temp'],
    'user_version' => ['405', 'main'],
    'writable_schema' => ['1', 'main'],
];

$noResultPragmas = [
    'shrink_memory' => ['10', 'main'],
    'case_sensitive_like' => ['1', 'main'],
];

$caseVariants = static function (string $name, int $variant): string {
    if (($variant % 3) === 0) {
        return strtoupper($name);
    }
    if (($variant % 3) === 1) {
        return strtolower($name);
    }

    return implode('_', array_map(
        static fn (string $part): string => ucfirst($part),
        explode('_', strtolower($name)),
    ));
};

$statement = static function (string $pragma, string $value, string $schema, int $variant, bool $write): string {
    $name = $GLOBALS['libsqlite_pragma4_case_variants']($pragma, $variant);
    $qualified = ($variant % 4) === 0 ? $schema . '.' . $name : $name;
    if (!$write) {
        return 'PRAGMA ' . $qualified;
    }
    if (($variant % 2) === 0) {
        return 'PRAGMA ' . $qualified . ' = ' . $value;
    }

    return 'PRAGMA ' . $qualified . '(' . $value . ')';
};
$GLOBALS['libsqlite_pragma4_case_variants'] = $caseVariants;

$addCase = static function (string $name, callable $callback) use (&$tests): void {
    $tests['real upstream pragma4 result-column dynamic ' . $name] = static function (TestRunner $t) use ($callback): void {
        $plan = $callback();

        $t->same('ok', $plan['status']);
        $t->same('sqlite-upstream-pragma4-result-column-arity', $plan['source']);
        $t->same(true, array_key_exists('result_columns', $plan));
        $t->same(true, $plan['result_columns'] === 0 || $plan['result_columns'] === 1);
        $t->same(true, $plan['pragma'] !== '');
    };
};

foreach (range(1, 18) as $variant) {
    foreach ($readWritePragmas as $pragma => [$value, $schema]) {
        $addCase("pragma4-1 read form {$pragma} exposes one column variant {$variant}", static function () use ($statement, $pragma, $value, $schema, $variant): array {
            $plan = SQLitePragmaResultColumnPlan::plan($statement($pragma, $value, $schema, $variant, false));
            if ($plan['result_columns'] !== 1 || $plan['has_rhs'] !== false || $plan['pragma'] !== strtolower($pragma)) {
                throw new RuntimeException('Unexpected read-form PRAGMA result-column plan');
            }

            return $plan;
        });

        $addCase("pragma4-1 write form {$pragma} exposes zero columns variant {$variant}", static function () use ($statement, $pragma, $value, $schema, $variant): array {
            $plan = SQLitePragmaResultColumnPlan::plan($statement($pragma, $value, $schema, $variant, true));
            if ($plan['result_columns'] !== 0 || $plan['has_rhs'] !== true || $plan['pragma'] !== strtolower($pragma)) {
                throw new RuntimeException('Unexpected write-form PRAGMA result-column plan');
            }

            return $plan;
        });
    }
}

foreach (range(1, 14) as $variant) {
    foreach ($noResultPragmas as $pragma => [$value, $schema]) {
        $addCase("pragma4-1 no-result read form {$pragma} exposes zero columns variant {$variant}", static function () use ($statement, $pragma, $value, $schema, $variant): array {
            $plan = SQLitePragmaResultColumnPlan::plan($statement($pragma, $value, $schema, $variant, false));
            if ($plan['result_columns'] !== 0 || $plan['has_rhs'] !== false || $plan['pragma'] !== strtolower($pragma)) {
                throw new RuntimeException('Unexpected no-result read-form PRAGMA plan');
            }

            return $plan;
        });

        $addCase("pragma4-1 no-result write form {$pragma} exposes zero columns variant {$variant}", static function () use ($statement, $pragma, $value, $schema, $variant): array {
            $plan = SQLitePragmaResultColumnPlan::plan($statement($pragma, $value, $schema, $variant, true));
            if ($plan['result_columns'] !== 0 || $plan['has_rhs'] !== true || $plan['pragma'] !== strtolower($pragma)) {
                throw new RuntimeException('Unexpected no-result write-form PRAGMA plan');
            }

            return $plan;
        });
    }
}

return $tests;
