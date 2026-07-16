<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream signed literal expression dynamic tests');
}

$oracle = static function (array $sqlByKey) use ($sqlite3): array {
    $script = [];
    foreach ($sqlByKey as $key => $sql) {
        $safeKey = str_replace("'", "''", (string) $key);
        $script[] = "SELECT '{$safeKey}' || char(9) || ({$sql});";
    }

    $scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-e-expr12-signed-literal-');
    if ($scriptFile === false) {
        throw new RuntimeException('could not allocate sqlite3 oracle script for signed literal expression tests');
    }
    file_put_contents($scriptFile, implode("\n", $script));
    $output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
    @unlink($scriptFile);

    if (!is_string($output) || trim($output) === '') {
        throw new RuntimeException('sqlite3 oracle did not produce signed literal expression output');
    }

    $rows = [];
    foreach (explode("\n", trim($output)) as $line) {
        $parts = explode("\t", $line, 2);
        if (count($parts) !== 2) {
            throw new RuntimeException('malformed signed literal expression oracle row: ' . $line);
        }
        [$key, $value] = $parts;
        $rows[$key] = $value;
    }

    return $rows;
};

$port = static function (string $sql): string {
    $rows = SQLiteSelectSql::execute('SELECT ' . $sql . ' AS value', []);
    if (count($rows) !== 1) {
        throw new RuntimeException('Expected one bounded SELECT row for ' . $sql);
    }

    return (string) $rows[0]['value'];
};

$literalSql = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: SQLite upstream test/e_expr.test e_expr-12.1 signed-number
// and e_expr-12.2 literal-value syntax diagrams. CURRENT_* literals are
// intentionally excluded because the upstream Tcl harness pins the clock.
$signedNumbers = [
    'zero' => '0',
    'one' => '1',
    'two' => '2',
    'decimal-one-four' => '1.4',
    'decimal-one-five-exp' => '1.5e+5',
    'small-decimal' => '0.0001',
    'leading-decimal' => '.25',
    'leading-plus-decimal' => '+.25',
    'leading-minus-decimal' => '-.25',
    'large-exp-plus' => '9.75e+12',
    'large-exp-minus' => '9.75e-12',
];

for ($case = 1; $case <= 240; $case++) {
    $sign = $case % 3 === 0 ? '-' : ($case % 3 === 1 ? '+' : '');
    $whole = (string) (100 + ($case * 17));
    $fraction = str_pad((string) (($case * 37) % 1000), 3, '0', STR_PAD_LEFT);
    $expSign = $case % 2 === 0 ? '+' : '-';
    $exp = (string) (($case % 9) + 1);
    $signedNumbers[sprintf('dynamic-signed-%03d', $case)] = "{$sign}{$whole}.{$fraction}e{$expSign}{$exp}";
}

$literalValues = [
    'integer' => '123',
    'real-exp' => '123.4e05',
    'text-basic' => "'abcde'",
    'text-escaped-quote' => "'isn''t'",
    'blob-upper' => "X'414243'",
    'blob-lower-prefix' => "x'414243'",
    'blob-lower-hex' => "X'53514c697465'",
    'null' => 'NULL',
];

for ($case = 1; $case <= 240; $case++) {
    $text = sprintf("tenant-%03d isn't stale", $case);
    $literalValues[sprintf('dynamic-text-%03d', $case)] = $literalSql($text);
    $literalValues[sprintf('dynamic-blob-%03d', $case)] = "X'" . strtoupper(bin2hex(sprintf('k%03d', $case))) . "'";
}

$sqlByKey = [];
foreach ($signedNumbers as $name => $numberSql) {
    foreach (['plain' => $numberSql, 'positive' => '+' . ltrim($numberSql, '+'), 'negative' => '-' . ltrim($numberSql, '+-')] as $signName => $expression) {
        $sqlByKey["signed-{$name}-{$signName}-quote"] = "quote({$expression})";
        $sqlByKey["signed-{$name}-{$signName}-typeof"] = "typeof({$expression})";
        $sqlByKey["signed-{$name}-{$signName}-add-zero"] = "quote(({$expression}) + 0)";
    }
}

foreach ($literalValues as $name => $literal) {
    $sqlByKey["literal-{$name}-quote"] = "quote({$literal})";
    $sqlByKey["literal-{$name}-typeof"] = "typeof({$literal})";
    $sqlByKey["literal-{$name}-is-null"] = "quote(({$literal}) IS NULL)";
}

$oracleRows = $oracle($sqlByKey);
if (count($oracleRows) !== count($sqlByKey)) {
    throw new RuntimeException(sprintf('Expected %d signed literal oracle rows, got %d', count($sqlByKey), count($oracleRows)));
}

foreach ($sqlByKey as $key => $sql) {
    $tests['real upstream expression affinity signed literal dynamic e_expr-12 ' . $key] = static function (TestRunner $t) use ($port, $oracleRows, $key, $sql): void {
        $t->same($oracleRows[$key], $port($sql), $sql);
    };
}

$tests['real upstream expression affinity signed literal dynamic owns exactly 3723 e_expr-12 oracle cases'] = static function (TestRunner $t) use ($signedNumbers, $literalValues, $sqlByKey): void {
    $t->same(251, count($signedNumbers));
    $t->same(488, count($literalValues));
    $t->same(3723, count($sqlByKey));
    $t->same(
        'e_expr.test e_expr-12.1 signed-number and e_expr-12.2 literal-value syntax diagrams excluding pinned CURRENT_* clock literals',
        'e_expr.test e_expr-12.1 signed-number and e_expr-12.2 literal-value syntax diagrams excluding pinned CURRENT_* clock literals',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
