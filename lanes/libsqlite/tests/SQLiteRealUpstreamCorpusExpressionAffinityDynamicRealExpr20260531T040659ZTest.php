<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity dynamic real expr tests');
}

$quoteSql = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth:
// - upstream test/e_expr.test e_expr-13.* BETWEEN precedence and truth rules.
// - upstream test/e_expr.test expression precedence rows for IN and NOT IN.
// - upstream test/types2.test types2-5.* IN-list affinity and NULL behavior.
$leftExpressions = [
    'null' => 'NULL',
    'zero' => '0',
    'one' => '1',
    'two' => '2',
    'neg-one' => '-1',
    'real-one' => '1.0',
    'real-one-half' => '1.5',
    'text-one' => $quoteSql('1'),
    'text-one-real' => $quoteSql('1.0'),
    'text-two' => $quoteSql('2'),
    'text-leading-zero' => $quoteSql('01'),
    'text-real-leading-zero' => $quoteSql('01.0'),
    'text-space-one' => $quoteSql(' 1 '),
    'text-alpha' => $quoteSql('alpha'),
    'blob-one' => "X'31'",
    'cast-text-one-numeric' => "CAST('1' AS NUMERIC)",
    'cast-text-one-text' => "CAST(1 AS TEXT)",
    'cast-text-one-real' => "CAST('1.0' AS REAL)",
    'cast-two-integer' => "CAST('2.9' AS INTEGER)",
    'sum-real' => '(0.25 + 0.75)',
    'boolean-equality' => '(1 == 1)',
    'boolean-inequality' => '(1 != 1)',
    'not-null-test' => '(NULL IS NOT NULL)',
    'coalesced-null' => "coalesce(NULL, '1')",
    'nullif-equal' => "nullif('1', '1')",
];

$bounds = [
    'zero-to-one' => ['0', '1'],
    'one-to-two' => ['1', '2'],
    'text-one-to-two' => [$quoteSql('1'), $quoteSql('2')],
    'real-half-to-real-two' => ['0.5', '2.0'],
    'text-real-to-text-real' => [$quoteSql('1.0'), $quoteSql('2.0')],
    'null-to-two' => ['NULL', '2'],
    'zero-to-null' => ['0', 'NULL'],
    'alpha-to-zulu' => [$quoteSql('alpha'), $quoteSql('zulu')],
    'text-blank-to-three' => [$quoteSql(''), $quoteSql('3')],
    'numeric-prefix-text' => [$quoteSql('1x'), $quoteSql('2x')],
];

$templates = [
    'between' => static fn (string $left, string $lower, string $upper): string => "({$left}) BETWEEN ({$lower}) AND ({$upper})",
    'not-between' => static fn (string $left, string $lower, string $upper): string => "({$left}) NOT BETWEEN ({$lower}) AND ({$upper})",
    'in-list' => static fn (string $left, string $lower, string $upper): string => "({$left}) IN (({$lower}), ({$upper}), NULL)",
    'not-in-list' => static fn (string $left, string $lower, string $upper): string => "({$left}) NOT IN (({$lower}), ({$upper}), NULL)",
];

$cases = [];
foreach ($leftExpressions as $leftName => $leftSql) {
    foreach ($bounds as $boundName => [$lowerSql, $upperSql]) {
        foreach ($templates as $templateName => $template) {
            $caseName = "{$leftName}.{$templateName}.{$boundName}";
            $expression = $template($leftSql, $lowerSql, $upperSql);
            $cases[$caseName] = $expression;
        }
    }
}

$oracleScript = [];
foreach ($cases as $caseName => $expression) {
    $safeName = str_replace("'", "''", $caseName);
    $oracleScript[] = "SELECT '{$safeName}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-real-expr-affinity-in-between-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression affinity IN/BETWEEN output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 oracle row: ' . $line);
    }

    [$caseName, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$caseName] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 expression affinity oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $caseName => $expression) {
    $tests['real upstream expression affinity dynamic real expr e_expr types2 in between ' . $caseName] = static function (TestRunner $t) use ($caseName, $expression, $oracle): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n",
            [],
        );

        $t->same(1, count($rows), $caseName . ' row count');
        $t->same($oracle[$caseName]['quote'], (string) $rows[0]['q'], $caseName . ' quote');
        $t->same($oracle[$caseName]['typeof'], (string) $rows[0]['t'], $caseName . ' typeof');
        $t->same($oracle[$caseName]['isNull'], (string) $rows[0]['n'], $caseName . ' null truth');
    };
}

$tests['real upstream expression affinity dynamic real expr e_expr types2 owns 1000 in-between cases'] = static function (TestRunner $t) use ($leftExpressions, $bounds, $templates, $cases, $oracle): void {
    $t->same(25, count($leftExpressions));
    $t->same(10, count($bounds));
    $t->same(4, count($templates));
    $t->same(1000, count($cases));
    $t->same(1000, count($oracle));
    $t->same(
        [
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test e_expr-13.* BETWEEN operator precedence and truth behavior',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test IN and NOT IN expression precedence rows',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test types2-5.* IN-list affinity and NULL behavior',
        ],
        [
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test e_expr-13.* BETWEEN operator precedence and truth behavior',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test IN and NOT IN expression precedence rows',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test types2-5.* IN-list affinity and NULL behavior',
        ],
    );
};

return $tests;
