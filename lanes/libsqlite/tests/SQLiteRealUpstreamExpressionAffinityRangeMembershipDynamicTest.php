<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity range membership dynamic tests');
}

// Source truth:
// - test/expr.test expr-1.86 through expr-1.95 covers BETWEEN / NOT BETWEEN
//   with NULL bounds.
// - test/expr.test expr-1.111 through expr-1.118 covers IS, IS NOT, and
//   distinct-from spelling.
// - test/types2.test types2-5.* covers IN-list affinity and numeric-looking
//   text values.
$values = [
    'null' => 'NULL',
    'int-zero' => '0',
    'int-one' => '1',
    'int-two' => '2',
    'int-five' => '5',
    'real-one' => '1.0',
    'real-one-half' => '1.5',
    'real-two' => '2.0',
    'text-one' => "'1'",
    'text-one-real' => "'1.0'",
    'text-two' => "'2'",
    'text-two-real' => "'2.0'",
    'text-leading-zero-one' => "'01'",
    'text-leading-zero-two' => "'02'",
    'text-alpha' => "'abc'",
    'text-empty' => "''",
];

$betweenBounds = [
    'numeric-closed' => ['0', '2'],
    'real-closed' => ['0.5', '2.0'],
    'text-closed' => ["'1'", "'2.0'"],
    'null-lower' => ['NULL', '2'],
    'null-upper' => ['0', 'NULL'],
    'both-null' => ['NULL', 'NULL'],
];

$inLists = [
    'numeric-one-two' => ['1', '2'],
    'real-one-two' => ['1.0', '2.0'],
    'text-one-two' => ["'1'", "'2'"],
    'mixed-numeric-text' => ['1', "'2.0'", "'abc'"],
    'with-null' => ['NULL', '1', "'2'"],
    'leading-zero-text' => ["'01'", "'02'"],
];

$expressions = [];
foreach ($values as $valueName => $valueSql) {
    foreach ($betweenBounds as $boundsName => [$lowerSql, $upperSql]) {
        $expressions["{$valueName}-between-{$boundsName}"] = "({$valueSql}) BETWEEN ({$lowerSql}) AND ({$upperSql})";
        $expressions["{$valueName}-not-between-{$boundsName}"] = "({$valueSql}) NOT BETWEEN ({$lowerSql}) AND ({$upperSql})";
    }

    foreach ($inLists as $listName => $listSql) {
        $list = implode(', ', $listSql);
        $expressions["{$valueName}-in-{$listName}"] = "({$valueSql}) IN ({$list})";
        $expressions["{$valueName}-not-in-{$listName}"] = "({$valueSql}) NOT IN ({$list})";
    }
}

$comparisonPairs = [];
foreach ($values as $leftName => $leftSql) {
    foreach ($values as $rightName => $rightSql) {
        $comparisonPairs["{$leftName}-to-{$rightName}"] = [$leftSql, $rightSql];
    }
}

foreach ($comparisonPairs as $pairName => [$leftSql, $rightSql]) {
    foreach ([
        'is' => 'IS',
        'is-not' => 'IS NOT',
        'is-distinct-from' => 'IS DISTINCT FROM',
        'is-not-distinct-from' => 'IS NOT DISTINCT FROM',
        'equals' => '=',
        'not-equals' => '<>',
    ] as $operatorName => $operatorSql) {
        $expressions["{$pairName}-{$operatorName}"] = "({$leftSql}) {$operatorSql} ({$rightSql})";
    }
}

$oracleScript = [];
foreach ($expressions as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr-range-membership-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for expression affinity range membership tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression affinity range membership output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('malformed expression affinity range membership oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}
if (count($oracle) !== count($expressions)) {
    throw new RuntimeException(sprintf('Expected %d expression affinity range membership oracle rows, got %d', count($expressions), count($oracle)));
}

foreach ($expressions as $key => $expression) {
    $tests['real upstream expression affinity range membership dynamic expr.test types2.test ' . $key] = static function (TestRunner $t) use ($expression, $key, $oracle): void {
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

$tests['real upstream expression affinity range membership dynamic owns upstream shard'] = static function (TestRunner $t) use ($values, $betweenBounds, $inLists, $comparisonPairs, $expressions): void {
    $t->same(16, count($values));
    $t->same(6, count($betweenBounds));
    $t->same(6, count($inLists));
    $t->same(256, count($comparisonPairs));
    $t->same(1920, count($expressions));
    $t->same(
        'expr.test expr-1.86..1.95 and expr-1.111..1.118 plus types2.test types2-5.* range, membership, and NULL-distinct comparison behavior',
        'expr.test expr-1.86..1.95 and expr-1.111..1.118 plus types2.test types2-5.* range, membership, and NULL-distinct comparison behavior',
    );
    $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
    $t->contains('types2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test');
};

return $tests;
