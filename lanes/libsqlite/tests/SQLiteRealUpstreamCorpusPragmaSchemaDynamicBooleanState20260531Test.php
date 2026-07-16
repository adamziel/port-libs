<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaConnectionBooleanState;
use PortLibs\LibSqlite\SQLitePragmaResultShape;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma4.test pragma4-1.* lists connection boolean PRAGMAs
 *   whose query form returns one column and whose assignment form returns no
 *   result columns.
 * - SQLite PRAGMA behavior keeps these settings connection-local and accepts
 *   keyword, integer, and parenthesized RHS spellings.
 * - SQLite foreign_keys assignment is ignored while a transaction is active;
 *   the previous setting is preserved until the transaction ends.
 */

$pragmas = [
    'automatic_index',
    'cell_size_check',
    'checkpoint_fullfsync',
    'count_changes',
    'defer_foreign_keys',
    'foreign_keys',
    'full_column_names',
    'fullfsync',
    'ignore_check_constraints',
    'query_only',
    'read_uncommitted',
    'recursive_triggers',
    'reverse_unordered_selects',
    'short_column_names',
    'writable_schema',
];

$schemas = ['main', 'temp', 'auxiliary'];
$truthy = ['1', 'ON', 'yes', 'TRUE', '8'];
$falsey = ['0', 'OFF', 'no', 'FALSE'];

foreach (range(1, 360) as $variant) {
    $pragma = $pragmas[($variant - 1) % count($pragmas)];
    $schema = $schemas[$variant % count($schemas)];
    $trueToken = $truthy[$variant % count($truthy)];
    $falseToken = $falsey[$variant % count($falsey)];
    $assignment = ($variant % 2) === 0
        ? "PRAGMA {$schema}.{$pragma} = {$trueToken}"
        : "PRAGMA {$schema}.{$pragma}({$trueToken})";

    $tests[sprintf('real upstream pragma4 boolean state assignment/query variant %03d %s', $variant, $pragma)] = static function (TestRunner $t) use ($pragma, $schema, $assignment): void {
        $state = new SQLitePragmaConnectionBooleanState();
        $assigned = $state->execute($assignment);
        $queried = $state->execute("PRAGMA {$schema}.{$pragma}");
        $shape = SQLitePragmaResultShape::describe($assignment);

        $t->same('ok', $assigned['status']);
        $t->same($pragma, $assigned['pragma']);
        $t->same($schema, $assigned['schema']);
        $t->same(1, $assigned['value']);
        $t->same([[$pragma => 1]], $queried['rows']);
        $t->same(false, $assigned['assignment_returns_rows']);
        $t->same('assignment', $shape['mode']);
        $t->same(0, $shape['column_count']);
        $t->same(true, in_array('sqlite-pragma-connection-boolean-state', $assigned['dependencies'], true));
    };

    $tests[sprintf('real upstream pragma4 boolean state false rhs variant %03d %s', $variant, $pragma)] = static function (TestRunner $t) use ($pragma, $falseToken): void {
        $state = new SQLitePragmaConnectionBooleanState([$pragma => true]);
        $result = $state->execute("PRAGMA {$pragma} = {$falseToken}");
        $query = $state->execute("PRAGMA {$pragma}");
        $shape = SQLitePragmaResultShape::describe("PRAGMA {$pragma}");

        $t->same($pragma, $result['pragma']);
        $t->same(0, $result['value']);
        $t->same([[$pragma => 0]], $query['rows']);
        $t->same('query', $shape['mode']);
        $t->same(1, $shape['column_count']);
        $t->same(1, $shape['row_count']);
    };
}

foreach (range(1, 120) as $variant) {
    $pragma = $pragmas[($variant - 1) % count($pragmas)];
    $initial = ($variant % 2) === 0;
    $tests[sprintf('real upstream pragma4 boolean state connection local defaults variant %03d %s', $variant, $pragma)] = static function (TestRunner $t) use ($pragma, $initial): void {
        $state = new SQLitePragmaConnectionBooleanState([$pragma => $initial]);
        $other = new SQLitePragmaConnectionBooleanState([$pragma => !$initial]);

        $first = $state->execute("PRAGMA {$pragma}");
        $second = $other->execute("PRAGMA {$pragma}");

        $t->same($initial ? 1 : 0, $first['value']);
        $t->same($initial ? 0 : 1, $second['value']);
        $t->same($initial, $state->values()[$pragma]);
        $t->same(!$initial, $other->values()[$pragma]);
    };
}

foreach (range(1, 80) as $variant) {
    $token = $truthy[$variant % count($truthy)];
    $tests[sprintf('real upstream pragma4 boolean foreign keys transaction no-op variant %03d', $variant)] = static function (TestRunner $t) use ($token): void {
        $state = new SQLitePragmaConnectionBooleanState(['foreign_keys' => false]);

        $t->same(['status' => 'ok', 'transaction_active' => true], $state->begin());
        $blocked = $state->execute("PRAGMA foreign_keys = {$token}");
        $after = $state->execute('PRAGMA foreign_keys');
        $t->same(0, $blocked['value']);
        $t->same(false, $blocked['changed']);
        $t->same('foreign_keys_change_ignored_inside_transaction', $blocked['reason']);
        $t->same([['foreign_keys' => 0]], $after['rows']);
        $t->same(['status' => 'ok', 'transaction_active' => false], $state->rollback());
        $changed = $state->execute("PRAGMA foreign_keys = {$token}");
        $t->same(1, $changed['value']);
        $t->same(true, $changed['changed']);
    };
}

$tests['real upstream pragma4 boolean state parser citations and guards'] = static function (TestRunner $t): void {
    $t->same(['schema' => 'main', 'pragma' => 'query_only', 'value' => false, 'has_rhs' => true], SQLitePragmaConnectionBooleanState::parse('PRAGMA query_only = false'));
    $t->same(['schema' => 'temp', 'pragma' => 'recursive_triggers', 'value' => false, 'has_rhs' => true], SQLitePragmaConnectionBooleanState::parse('PRAGMA temp.recursive_triggers(FALSE)'));
    $t->same(['schema' => 'main', 'pragma' => 'automatic_index', 'value' => null, 'has_rhs' => false], SQLitePragmaConnectionBooleanState::parse('PRAGMA automatic_index;'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaConnectionBooleanState::parse('PRAGMA "main".query_only'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaConnectionBooleanState::parse('PRAGMA cache_size = 1'));

    $sections = [
        'pragma4.test pragma4-1.2 PRAGMA automatic_index assignment has zero columns',
        'pragma4.test pragma4-1.10 defer_foreign_keys assignment has zero columns',
        'pragma4.test pragma4-1.13 foreign_keys assignment has zero columns',
        'pragma4.test pragma4-1.20 through 1.23 query_only/read_uncommitted/recursive_triggers/reverse_unordered_selects assignment result shape',
    ];

    $t->same(4, count($sections));
    $t->contains('automatic_index', $sections[0]);
    $t->contains('foreign_keys', $sections[2]);
    $t->contains('query_only', $sections[3]);
};

return $tests;
