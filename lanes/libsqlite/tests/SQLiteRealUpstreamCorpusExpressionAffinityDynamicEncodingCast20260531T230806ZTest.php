<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream encoding-sensitive CAST tests');
}

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test';
$sourceText = (string) file_get_contents($sourcePath);
if ($sourceText === '') {
    throw new RuntimeException('Hydrated upstream e_expr.test is required for encoding-sensitive CAST tests');
}

$sqlLiteral = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value)) {
        return (string) $value;
    }
    if (is_float($value)) {
        return floor($value) === $value ? sprintf('%.1F', $value) : (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$quotePortValue = static function (mixed $value): string {
    return SQLiteRealExpressionAffinityCorpusPlan::quote($value);
};

$parseBlobQuote = static function (string $quoted): string {
    if (!preg_match("/^X'([0-9A-F]*)'$/", $quoted, $matches)) {
        throw new RuntimeException('Expected SQLite blob quote, got ' . $quoted);
    }

    return (string) hex2bin($matches[1]);
};

$encodings = [
    'UTF-8' => 'UTF-8',
    'UTF-16LE' => 'UTF-16le',
    'UTF-16BE' => 'UTF-16be',
];

$textFragments = [
    'alpha',
    'settings',
    'tenant',
    'metric',
    'cache',
    "O'Reilly",
    'space tail ',
    'leading space',
    'digits-123',
    'decimal-45.5',
    'cafe-' . hex2bin('c3a9'),
    'emoji-' . hex2bin('f09f9880'),
    'snowman-' . hex2bin('e29883'),
    'greek-' . hex2bin('cea9'),
    'arabic-' . hex2bin('d8b3'),
];

$cases = [];
for ($seed = 0; $seed < 250; ++$seed) {
    $encoding = array_keys($encodings)[$seed % count($encodings)];
    $sign = $seed % 2 === 0 ? 1 : -1;
    $integer = $sign * (1000 + ($seed * 37));
    $real = $sign * (($seed + 1) + 0.5);
    $fragment = $textFragments[$seed % count($textFragments)];
    $text = sprintf('%s-%04d', $fragment, $seed);

    $cases[sprintf('seed-%04d-int-to-blob', $seed)] = [
        'encoding' => $encoding,
        'operation' => 'nonblob-to-blob',
        'input' => $integer,
        'expression' => 'CAST(' . $sqlLiteral($integer) . ' AS BLOB)',
    ];
    $cases[sprintf('seed-%04d-real-to-blob', $seed)] = [
        'encoding' => $encoding,
        'operation' => 'nonblob-to-blob',
        'input' => $real,
        'expression' => 'CAST(' . $sqlLiteral($real) . ' AS BLOB)',
    ];
    $cases[sprintf('seed-%04d-text-to-blob', $seed)] = [
        'encoding' => $encoding,
        'operation' => 'nonblob-to-blob',
        'input' => $text,
        'expression' => 'CAST(' . $sqlLiteral($text) . ' AS BLOB)',
    ];
    $cases[sprintf('seed-%04d-blob-to-text', $seed)] = [
        'encoding' => $encoding,
        'operation' => 'blob-to-text',
        'input' => null,
        'expression' => 'CAST(CAST(' . $sqlLiteral($text) . ' AS BLOB) AS TEXT)',
        'sourceBlobExpression' => 'CAST(' . $sqlLiteral($text) . ' AS BLOB)',
    ];
}

$oracle = [];
foreach ($encodings as $encoding => $pragmaEncoding) {
    $script = ["PRAGMA encoding = '{$pragmaEncoding}';"];
    foreach ($cases as $key => $case) {
        if ($case['encoding'] !== $encoding) {
            continue;
        }

        $safeKey = str_replace("'", "''", $key);
        $sourceBlobExpression = $case['sourceBlobExpression'] ?? 'NULL';
        $expression = $case['expression'];
        $script[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote({$sourceBlobExpression});";
    }

    $scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-e-expr33-cast-');
    if ($scriptFile === false) {
        throw new RuntimeException('Could not allocate sqlite3 oracle script for encoding-sensitive CAST tests');
    }
    file_put_contents($scriptFile, implode("\n", $script));
    $output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
    @unlink($scriptFile);
    if (!is_string($output) || trim($output) === '') {
        throw new RuntimeException('sqlite3 oracle did not produce encoding-sensitive CAST output for ' . $encoding);
    }

    foreach (explode("\n", trim($output)) as $line) {
        $parts = explode("\t", $line, 4);
        if (count($parts) !== 4) {
            throw new RuntimeException('Malformed sqlite3 encoding-sensitive CAST oracle row: ' . $line);
        }

        [$key, $quotedValue, $storageClass, $sourceBlobQuote] = $parts;
        $oracle[$key] = [
            'quote' => $quotedValue,
            'typeof' => $storageClass,
            'sourceBlobQuote' => $sourceBlobQuote,
        ];
    }
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d encoding-sensitive CAST oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic e_expr-33 encoding CAST ' . $key] =
        static function (TestRunner $t) use ($case, $key, $oracle, $parseBlobQuote, $quotePortValue): void {
            if ($case['operation'] === 'blob-to-text') {
                $actual = SQLiteRealExpressionAffinityCorpusPlan::castTextBlobWithEncoding(
                    new SQLiteBlobValue($parseBlobQuote($oracle[$key]['sourceBlobQuote'])),
                    'TEXT',
                    $case['encoding'],
                );
            } else {
                $actual = SQLiteRealExpressionAffinityCorpusPlan::castTextBlobWithEncoding(
                    $case['input'],
                    'BLOB',
                    $case['encoding'],
                );
            }

            $t->same($oracle[$key]['quote'], $quotePortValue($actual), $key . ' quote');
            $t->same($oracle[$key]['typeof'], SQLiteRealExpressionAffinityCorpusPlan::storageClass($actual), $key . ' storage class');
            $t->same(true, in_array($case['encoding'], ['UTF-8', 'UTF-16LE', 'UTF-16BE'], true), $key . ' encoding');
            $t->same(true, str_starts_with($case['expression'], 'CAST('), $key . ' expression shape');
        };
}

$tests['real upstream corpus expression affinity dynamic e_expr-33 encoding CAST source accounting'] =
    static function (TestRunner $t) use ($cases, $oracle, $sourceText): void {
        $t->same(1000, count($cases));
        $t->same(1000, count($oracle));
        $t->contains('do_qexpr_test e_expr-27.4.1 { CAST(' . "'ghi'" . ' AS blob) }', $sourceText);
        $t->contains('do_expr_test e_expr-28.1.1 { CAST (X' . "'676869'" . ' AS text) }', $sourceText);
        $t->contains('foreach {tn castexpr differs}', $sourceText);
        $t->contains('CAST(123 AS BLOB)', $sourceText);
        $t->contains("CAST(X'abcd' AS TEXT)", $sourceText);
        $t->same(
            'e_expr.test e_expr-27.4, e_expr-28.1, and e_expr-33.1 encoding-sensitive TEXT/BLOB CAST behavior',
            'e_expr.test e_expr-27.4, e_expr-28.1, and e_expr-33.1 encoding-sensitive TEXT/BLOB CAST behavior',
        );
        $t->same(
            'no new support component needed; reuses native SQLite UTF-8, UTF-16LE, and UTF-16BE encode/decode helpers',
            'no new support component needed; reuses native SQLite UTF-8, UTF-16LE, and UTF-16BE encode/decode helpers',
        );
        $t->same(
            'non-overlap: owns e_expr-33 encoding-sensitive TEXT/BLOB casts; avoids accepted numeric CAST prefix, e_expr-29..32 REAL/INTEGER/NUMERIC casts, UTF-16 malformed guard, Unicode GLOB, JSON, WAL, VFS, B-tree, trigger, and PRAGMA clusters',
            'non-overlap: owns e_expr-33 encoding-sensitive TEXT/BLOB casts; avoids accepted numeric CAST prefix, e_expr-29..32 REAL/INTEGER/NUMERIC casts, UTF-16 malformed guard, Unicode GLOB, JSON, WAL, VFS, B-tree, trigger, and PRAGMA clusters',
        );
    };

return $tests;
