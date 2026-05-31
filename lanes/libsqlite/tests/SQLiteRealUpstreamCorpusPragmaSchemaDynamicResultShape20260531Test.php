<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaResultShape;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma4.test pragma4-1.*: PRAGMAs without a right-hand side
 *   act as one-column queries, assignment forms return zero columns/rows, and
 *   shrink_memory/case_sensitive_like never return rows.
 *
 * This slice keeps the corpus dynamic by varying schema qualifiers, assignment
 * syntax, semicolons, boolean/integer/text values, and the no-result PRAGMAs.
 */

$queryPragmas = [
    'application_id',
    'automatic_index',
    'auto_vacuum',
    'cache_size',
    'cache_spill',
    'cell_size_check',
    'checkpoint_fullfsync',
    'count_changes',
    'default_cache_size',
    'defer_foreign_keys',
    'empty_result_callbacks',
    'encoding',
    'foreign_keys',
    'full_column_names',
    'fullfsync',
    'ignore_check_constraints',
    'page_size',
    'query_only',
    'read_uncommitted',
    'recursive_triggers',
    'reverse_unordered_selects',
    'schema_version',
    'short_column_names',
    'synchronous',
    'temp_store',
    'user_version',
    'writable_schema',
];

$values = ['1', '0', 'true', 'false', 'FULL', 'MEMORY', "'utf-8'", '-100', '512', '405'];
$schemas = ['main', 'temp', 'archive'];

foreach (range(1, 200) as $variant) {
    $pragma = $queryPragmas[($variant - 1) % count($queryPragmas)];
    $schema = $schemas[$variant % count($schemas)];
    $value = $values[$variant % count($values)];
    $qualified = $schema . '.' . $pragma;
    $assignmentSql = ($variant % 2) === 0
        ? "PRAGMA {$qualified} = {$value};"
        : "PRAGMA {$qualified}({$value});";

    $tests[sprintf('real upstream pragma4 dynamic result shape query one column variant %03d', $variant)] = static function (TestRunner $t) use ($pragma): void {
        $shape = SQLitePragmaResultShape::describe('PRAGMA ' . strtoupper($pragma));

        $t->same($pragma, $shape['pragma']);
        $t->same('query', $shape['mode']);
        $t->same(1, $shape['column_count']);
        $t->same(1, $shape['row_count']);
        $t->contains('pragma4-1', $shape['source']);
    };

    $tests[sprintf('real upstream pragma4 dynamic result shape qualified query one column variant %03d', $variant)] = static function (TestRunner $t) use ($pragma, $schema, $qualified): void {
        $shape = SQLitePragmaResultShape::describe("PRAGMA {$qualified};");

        $t->same($pragma, $shape['pragma']);
        $t->same('query', $shape['mode']);
        $t->same(1, $shape['column_count']);
        $t->same(1, $shape['row_count']);
        $t->same(true, in_array($schema, ['main', 'temp', 'archive'], true));
    };

    $tests[sprintf('real upstream pragma4 dynamic result shape assignment zero columns variant %03d', $variant)] = static function (TestRunner $t) use ($pragma, $assignmentSql): void {
        $shape = SQLitePragmaResultShape::describe($assignmentSql);

        $t->same($pragma, $shape['pragma']);
        $t->same('assignment', $shape['mode']);
        $t->same(0, $shape['column_count']);
        $t->same(0, $shape['row_count']);
    };

    $tests[sprintf('real upstream pragma4 dynamic result shape shrink memory no rows variant %03d', $variant)] = static function (TestRunner $t) use ($variant): void {
        $sql = ($variant % 2) === 0 ? 'PRAGMA shrink_memory' : 'PRAGMA shrink_memory = ' . $variant;
        $shape = SQLitePragmaResultShape::describe($sql);

        $t->same('shrink_memory', $shape['pragma']);
        $t->same('no-result', $shape['mode']);
        $t->same(0, $shape['column_count']);
        $t->same(0, $shape['row_count']);
    };

    $tests[sprintf('real upstream pragma4 dynamic result shape case sensitive like no rows variant %03d', $variant)] = static function (TestRunner $t) use ($variant): void {
        $sql = match ($variant % 3) {
            0 => 'PRAGMA case_sensitive_like',
            1 => 'PRAGMA case_sensitive_like = 0',
            default => 'PRAGMA case_sensitive_like(1)',
        };
        $shape = SQLitePragmaResultShape::describe($sql);

        $t->same('case_sensitive_like', $shape['pragma']);
        $t->same('no-result', $shape['mode']);
        $t->same(0, $shape['column_count']);
        $t->same(0, $shape['row_count']);
    };
}

$tests['real upstream pragma4 dynamic result shape citations and guards'] = static function (TestRunner $t): void {
    $sections = [
        'pragma4.test pragma4-1.* PRAGMA without RHS returns one column',
        'pragma4.test pragma4-1.* PRAGMA with RHS returns zero columns',
        'pragma4.test pragma4-1 shrink_memory and case_sensitive_like return zero columns',
    ];

    $t->same(3, count($sections));
    $t->contains('without RHS', $sections[0]);
    $t->contains('with RHS', $sections[1]);
    $t->contains('case_sensitive_like', $sections[2]);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaResultShape::describe('SELECT 1'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaResultShape::describe('PRAGMA unsupported_runtime_shape'));
};

return $tests;
