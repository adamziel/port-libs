<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$selectWithSqlite = static function (string $sql) use ($sqlite3): string {
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream e_expr null/type bulk tests');
    }

    $command = escapeshellarg($sqlite3) . ' -batch -noheader :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $sql);
    }

    return rtrim($output, "\r\n");
};

$selectWithPort = static function (string $sql): string {
    $rows = SQLiteSelectSql::execute($sql, []);
    $row = $rows[0] ?? [];

    return implode('|', array_map(
        static fn (mixed $value): string => $value === null ? '' : (string) $value,
        array_values($row),
    ));
};

// Real upstream source: SQLite test/e_expr.test e_expr-7.* binary operator
// result type matrix and e_expr-8.2.* IS / IS NOT NULL-comparison matrix.
// The literals and operator set mirror the supported non-short-circuit subset
// from those Tcl loops.
$literals = [
    1 => "'abc'",
    2 => "'hexadecimal'",
    3 => "''",
    4 => '123',
    5 => '-123',
    6 => '0',
    7 => '123.4',
    8 => '0.0',
    9 => '-123.4',
    10 => "X'ABCDEF'",
    11 => "X''",
    12 => "X'0000'",
    13 => 'NULL',
];

$binaryOperators = [
    'concat' => '||',
    'mul' => '*',
    'div' => '/',
    'mod' => '%',
];

$typeCases = [];
foreach ($binaryOperators as $operatorName => $operator) {
    foreach ($literals as $rhsId => $rhs) {
        foreach ($literals as $lhsId => $lhs) {
            $typeCases[] = [
                'upstream_id' => sprintf('e_expr-7.%s.%d.%d', $operatorName, $rhsId, $lhsId),
                'sql' => sprintf('SELECT typeof(%s %s %s)', $lhs, $operator, $rhs),
            ];
        }
    }
}

foreach ($typeCases as $case) {
    $tests['real upstream e_expr binary result type bulk ' . $case['upstream_id']] = static function (TestRunner $t) use ($case, $selectWithSqlite, $selectWithPort): void {
        $t->same($selectWithSqlite($case['sql']), $selectWithPort($case['sql']), $case['upstream_id'] . ' ' . $case['sql']);
    };
}

$isCases = [];
foreach ($literals as $rhsId => $rhs) {
    foreach ($literals as $lhsId => $lhs) {
        $isCases[] = [
            'upstream_id' => sprintf('e_expr-8.2.%d.%d.1', $rhsId, $lhsId),
            'sql' => sprintf('SELECT %s IS %s, %s IS NOT %s', $lhs, $rhs, $lhs, $rhs),
        ];
        $isCases[] = [
            'upstream_id' => sprintf('e_expr-8.2.%d.%d.2', $rhsId, $lhsId),
            'sql' => sprintf('SELECT (%s IS %s) IS NULL, (%s IS NOT %s) IS NULL', $lhs, $rhs, $lhs, $rhs),
        ];
    }
}

foreach ($isCases as $case) {
    $tests['real upstream e_expr is null comparison bulk ' . $case['upstream_id']] = static function (TestRunner $t) use ($case, $selectWithSqlite, $selectWithPort): void {
        $t->same($selectWithSqlite($case['sql']), $selectWithPort($case['sql']), $case['upstream_id'] . ' ' . $case['sql']);
    };
}

$tests['real upstream e_expr null type bulk owns 1014 upstream cases'] = static function (TestRunner $t) use ($typeCases, $isCases, $literals, $binaryOperators): void {
    $t->same(676, count($typeCases));
    $t->same(338, count($isCases));
    $t->same(13, count($literals));
    $t->same(4, count($binaryOperators));
    $t->same('e_expr-7.concat.1.1', $typeCases[0]['upstream_id']);
    $t->same('e_expr-8.2.13.13.2', $isCases[array_key_last($isCases)]['upstream_id']);
};

return $tests;
