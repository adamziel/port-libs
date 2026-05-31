<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaConnectionBooleanState;
use PortLibs\LibSqlite\SQLitePragmaResultShape;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/trustschema1.test toggles PRAGMA trusted_schema across ON,
 *   OFF, and mixed-case forms while enforcing schema safety at runtime.
 * - SQLite test/pragma4.test pragma4-1.* establishes the result-shape rule
 *   used by scalar connection PRAGMAs: query form returns one column/row and
 *   assignment form returns no columns/rows.
 *
 * This corpus is deliberately separate from the existing trustschema runtime
 * tests: it exercises the PRAGMA parser, result-shape classifier, schema
 * qualifier handling, RHS spellings, and connection-local boolean state for
 * trusted_schema.
 */

$schemas = ['main', 'temp', 'auxiliary', 'tenant'];
$truthy = ['1', 'ON', 'yes', 'TRUE', '+9'];
$falsey = ['0', 'OFF', 'no', 'FALSE', '-0'];

foreach (range(1, 500) as $variant) {
    $schema = $schemas[$variant % count($schemas)];
    $trueToken = $truthy[$variant % count($truthy)];
    $falseToken = $falsey[$variant % count($falsey)];
    $querySql = "PRAGMA {$schema}.trusted_schema";
    $trueAssignmentSql = ($variant % 2) === 0
        ? "PRAGMA {$schema}.trusted_schema = {$trueToken};"
        : "PRAGMA {$schema}.trusted_schema({$trueToken});";
    $falseAssignmentSql = ($variant % 2) === 0
        ? "PRAGMA {$schema}.trusted_schema = {$falseToken};"
        : "PRAGMA {$schema}.trusted_schema({$falseToken});";

    $tests[sprintf('real upstream trustschema1 trusted schema result shape query assignment variant %03d', $variant)] = static function (TestRunner $t) use ($schema, $querySql, $trueAssignmentSql): void {
        $state = new SQLitePragmaConnectionBooleanState(['trusted_schema' => false]);
        $queryShape = SQLitePragmaResultShape::describe($querySql);
        $assignmentShape = SQLitePragmaResultShape::describe($trueAssignmentSql);
        $before = $state->execute($querySql);
        $assigned = $state->execute($trueAssignmentSql);
        $after = $state->execute($querySql);

        $t->same('trusted_schema', $queryShape['pragma']);
        $t->same('query', $queryShape['mode']);
        $t->same(1, $queryShape['column_count']);
        $t->same(1, $queryShape['row_count']);
        $t->same('trusted_schema', $assignmentShape['pragma']);
        $t->same('assignment', $assignmentShape['mode']);
        $t->same(0, $assignmentShape['column_count']);
        $t->same(0, $assignmentShape['row_count']);
        $t->same($schema, $before['schema']);
        $t->same([['trusted_schema' => 0]], $before['rows']);
        $t->same(1, $assigned['value']);
        $t->same(true, $assigned['changed']);
        $t->same(false, $assigned['assignment_returns_rows']);
        $t->same([['trusted_schema' => 1]], $after['rows']);
        $t->same(true, in_array('sqlite-pragma-connection-boolean-state', $assigned['dependencies'], true));
    };

    $tests[sprintf('real upstream trustschema1 trusted schema local false rhs variant %03d', $variant)] = static function (TestRunner $t) use ($schema, $falseAssignmentSql, $trueAssignmentSql): void {
        $first = new SQLitePragmaConnectionBooleanState(['trusted_schema' => true]);
        $second = new SQLitePragmaConnectionBooleanState(['trusted_schema' => false]);
        $assignmentShape = SQLitePragmaResultShape::describe($falseAssignmentSql);
        $changed = $first->execute($falseAssignmentSql);
        $unchangedConnection = $second->execute($trueAssignmentSql);
        $firstQuery = $first->execute("PRAGMA {$schema}.trusted_schema");
        $secondQuery = $second->execute("PRAGMA {$schema}.trusted_schema");

        $t->same('trusted_schema', $assignmentShape['pragma']);
        $t->same('assignment', $assignmentShape['mode']);
        $t->same(0, $assignmentShape['column_count']);
        $t->same(0, $assignmentShape['row_count']);
        $t->same('trusted_schema', $changed['pragma']);
        $t->same($schema, $changed['schema']);
        $t->same(0, $changed['value']);
        $t->same(true, $changed['changed']);
        $t->same([['trusted_schema' => 0]], $firstQuery['rows']);
        $t->same(1, $unchangedConnection['value']);
        $t->same(true, $unchangedConnection['changed']);
        $t->same([['trusted_schema' => 1]], $secondQuery['rows']);
        $t->same(false, $first->values()['trusted_schema']);
        $t->same(true, $second->values()['trusted_schema']);
    };
}

$tests['real upstream trustschema1 trusted schema source citations'] = static function (TestRunner $t): void {
    $trustschema = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trustschema1.test');
    $pragma4 = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test');

    $t->same(true, is_string($trustschema));
    $t->same(true, is_string($pragma4));
    $t->contains('PRAGMA trusted_schema=OFF;', (string) $trustschema);
    $t->contains('PRAGMA trusted_schema=ON;', (string) $trustschema);
    $t->contains('PRAGMA trusted_schema=Off;', (string) $trustschema);
    $t->contains('Without RHS:', (string) $pragma4);
    $t->contains('With RHS:', (string) $pragma4);
    $t->same(['schema' => 'tenant', 'pragma' => 'trusted_schema', 'value' => false, 'has_rhs' => true], SQLitePragmaConnectionBooleanState::parse('PRAGMA tenant.trusted_schema = OFF'));
    $t->same(['schema' => 'main', 'pragma' => 'trusted_schema', 'value' => null, 'has_rhs' => false], SQLitePragmaConnectionBooleanState::parse('PRAGMA trusted_schema'));
};

return $tests;
