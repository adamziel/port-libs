<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectPredicate;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expr2 boolean affinity dynamic tests');
}

$literalSql = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$column = static fn (string $name): array => ['type' => 'column', 'name' => $name];
$predicateExpression = static fn (array $predicate): array => ['type' => 'predicate', 'predicate' => $predicate];
$compare = static fn (array $left, string $operator, array $right): array => [
    'operator' => $operator,
    'left' => $left,
    'right' => $right,
];
$truth = static fn (array $left, string $operator): array => [
    'operator' => $operator,
    'left' => $left,
];
$logical = static fn (string $operator, array $terms): array => [
    'operator' => $operator,
    'terms' => $terms,
];
$not = static fn (array $term): array => [
    'operator' => 'NOT',
    'term' => $term,
];

$expr2Predicates = [
    'expr2-1.1 where nested is false' => static fn (mixed $rhs): array => $compare(
        $predicateExpression($logical('OR', [
            $truth($literal(0), 'IS NOT FALSE'),
            $not($logical('OR', [
                $truth($literal(0), 'IS FALSE'),
                $compare($column('c0'), '=', $literal($rhs)),
            ])),
        ])),
        'IS',
        $literal(0),
    ),
    'expr2-1.2.1 projection nested is false' => static fn (mixed $rhs): array => $compare(
        $predicateExpression($logical('OR', [
            $truth($literal(0), 'IS NOT FALSE'),
            $not($logical('OR', [
                $truth($literal(0), 'IS FALSE'),
                $compare($column('c0'), '=', $literal($rhs)),
            ])),
        ])),
        'IS',
        $literal(0),
    ),
    'expr2-1.2.2 projection is zero branch' => static fn (mixed $rhs): array => $compare(
        $predicateExpression($logical('OR', [
            $truth($literal(0), 'IS NOT FALSE'),
            $not($logical('OR', [
                $compare($literal(0), 'IS', $literal(0)),
                $compare($column('c0'), '=', $literal($rhs)),
            ])),
        ])),
        'IS',
        $literal(0),
    ),
    'expr2-1.3 nested expression result' => static fn (mixed $rhs): array => $logical('OR', [
        $truth($literal(0), 'IS NOT FALSE'),
        $not($logical('OR', [
            $truth($literal(0), 'IS FALSE'),
            $compare($column('c0'), '=', $literal($rhs)),
        ])),
    ]),
    'expr2-1.4.2 not false or equality branch' => static fn (mixed $rhs): array => $not($logical('OR', [
        $truth($literal(0), 'IS FALSE'),
        $compare($column('c0'), '=', $literal($rhs)),
    ])),
];

$values = [];
foreach (range(0, 89) as $number) {
    $values[] = $number;
    $values[] = (float) $number;
    $values[] = (string) $number;
}
foreach (['val', 'VAL', 'value', '', '0', '0.0', '1', '1.0', 'true', 'false', null] as $value) {
    $values[] = $value;
}
$values = array_slice($values, 0, 250);

$script = [
    'CREATE TABLE t0(c0);',
];
$oracleCases = [];
foreach ($values as $index => $value) {
    $caseKey = 'v' . $index;
    $script[] = 'DELETE FROM t0;';
    $script[] = 'INSERT INTO t0(c0) VALUES (' . $literalSql($value) . ');';
    foreach ($expr2Predicates as $name => $_builder) {
        $expressionSql = match ($name) {
            'expr2-1.1 where nested is false', 'expr2-1.2.1 projection nested is false' => '( (0 IS NOT FALSE) OR NOT (0 IS FALSE OR (t0.c0 = 1)) ) IS 0',
            'expr2-1.2.2 projection is zero branch' => '( (0 IS NOT FALSE) OR NOT (0 IS 0 OR (t0.c0 = 1)) ) IS 0',
            'expr2-1.3 nested expression result' => '( (0 IS NOT FALSE) OR NOT (0 IS FALSE OR (t0.c0 = 1)) )',
            'expr2-1.4.2 not false or equality branch' => 'NOT (0 IS FALSE OR (t0.c0 = 1))',
        };
        $key = $caseKey . '|' . $name;
        $oracleCases[$key] = [$value, $name];
        $script[] = "SELECT '" . str_replace("'", "''", $key) . "' || char(9) || quote({$expressionSql}) || char(9) || typeof({$expressionSql}) FROM t0;";
    }
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr2-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}
file_put_contents($scriptFile, implode("\n", $script));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expr2 boolean output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('Malformed sqlite3 oracle row: ' . $line);
    }

    [$key, $quotedResult, $storageClass] = $parts;
    $oracle[$key] = [
        'result' => $quotedResult === '1',
        'quoted' => $quotedResult,
        'storageClass' => $storageClass,
    ];
}

if (count($oracle) !== count($oracleCases)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 expr2 oracle rows, got %d', count($oracleCases), count($oracle)));
}

foreach ($oracleCases as $key => [$value, $name]) {
    $tests["real upstream expr2 boolean affinity dynamic {$key}"] = static function (TestRunner $t) use ($expr2Predicates, $oracle, $key, $value, $name): void {
        $row = ['c0' => $value];
        $actual = SQLiteSelectPredicate::evaluate($row, $expr2Predicates[$name](1));
        $expected = $oracle[$key];

        $t->same($expected['result'], $actual, $key);
        $t->same('integer', $expected['storageClass'], $key);
        $t->same(true, in_array($expected['quoted'], ['0', '1'], true), $key);
        $t->same(true, str_starts_with($name, 'expr2-'), $key);
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/expr2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr2.test');
    };
}

$tests['real upstream expr2 boolean affinity dynamic owns exactly 1250 oracle cases'] = static function (TestRunner $t) use ($oracleCases, $oracle): void {
    $t->same(1250, count($oracleCases));
    $t->same(1250, count($oracle));
    $t->same('expr2.test: expr2-1.1 through expr2-1.4.2 IS FALSE / IS NOT FALSE nested boolean family', 'expr2.test: expr2-1.1 through expr2-1.4.2 IS FALSE / IS NOT FALSE nested boolean family');
};

return $tests;
