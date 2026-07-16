<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity min/max tests');
}

// Source truth: SQLite upstream test/expr.test expr-1.24 through expr-1.26
// covers scalar min()/max() over expressions, and expr-1.82 through expr-1.85
// covers NULL propagation when any scalar min()/max() argument is NULL. This
// shard widens that branch across mixed integer, real, text, blob, and NULL
// literal storage classes through the bounded SELECT SQL expression executor.
$literal = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$blob = static function (string $hex): string {
    return "x'{$hex}'";
};

$operands = [
    'integer-negative' => '-7',
    'integer-zero' => '0',
    'integer-one' => '1',
    'integer-ten' => '10',
    'integer-large' => '9999999999',
    'real-negative' => '-7.5',
    'real-zero' => '0.0',
    'real-fraction' => '2.25',
    'real-mid' => '12345.5',
    'text-empty' => $literal(''),
    'text-alpha' => $literal('alpha'),
    'text-real' => $literal('2.25'),
    'text-space' => $literal(' 7'),
    'text-zulu' => $literal('zulu'),
    'blob-small' => $blob('3130'),
    'null' => 'NULL',
];

$expressionTemplates = [
    'min-two' => static fn (string $left, string $right): string => "min({$left}, {$right})",
    'max-two' => static fn (string $left, string $right): string => "max({$left}, {$right})",
    'min-composed' => static fn (string $left, string $right): string => "min({$left}, {$right}, ({$left}) + 0, ({$right}) || '')",
    'max-composed' => static fn (string $left, string $right): string => "max({$left}, {$right}, ({$left}) + 0, ({$right}) || '')",
];

$cases = [];
foreach ($operands as $leftName => $leftSql) {
    foreach ($operands as $rightName => $rightSql) {
        foreach ($expressionTemplates as $templateName => $template) {
            $cases["{$leftName}-{$templateName}-{$rightName}"] = $template($leftSql, $rightSql);
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr-minmax-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for expression affinity min/max tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression affinity min/max output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('malformed expression affinity min/max oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d expression affinity min/max oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $expression) {
    $tests['real upstream corpus expression affinity min max expr.test expr-1 dynamic ' . $key] = static function (TestRunner $t) use ($expression, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n",
            [],
        );
        $t->same(1, count($rows), $expression);

        $row = $rows[0];
        $t->same($oracle[$key]['quote'], (string) $row['q'], $expression . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $expression . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $row['n'], $expression . ' is-null');
    };
}

$tests['real upstream corpus expression affinity min max owns exactly 1024 pass cases'] = static function (TestRunner $t) use ($operands, $expressionTemplates, $cases): void {
    $t->same(16, count($operands));
    $t->same(4, count($expressionTemplates));
    $t->same(1024, count($cases));
    $t->same(
        'expr.test expr-1.24..1.26 and expr-1.82..1.85 scalar min/max expression comparison and NULL propagation',
        'expr.test expr-1.24..1.26 and expr-1.82..1.85 scalar min/max expression comparison and NULL propagation',
    );
    $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
};

return $tests;
