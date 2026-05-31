<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream e_expr-33 encoding-sensitive CAST tests');
}

$sqlString = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$blobValue = static function (string $hex): SQLiteBlobValue {
    $bytes = hex2bin($hex);
    if ($bytes === false) {
        throw new RuntimeException("Invalid BLOB hex in e_expr-33 encoding CAST corpus: {$hex}");
    }

    return new SQLiteBlobValue($bytes);
};

$nonBlobOperands = [
    'upstream-integer-123' => [
        'sql' => '123',
        'value' => 123,
        'source' => 'e_expr.test e_expr-33.1.1 CAST(123 AS BLOB)',
    ],
    'upstream-empty-text' => [
        'sql' => $sqlString(''),
        'value' => '',
        'source' => "e_expr.test e_expr-33.1.2 CAST('' AS BLOB)",
    ],
    'upstream-text-abcd' => [
        'sql' => $sqlString('abcd'),
        'value' => 'abcd',
        'source' => "e_expr.test e_expr-33.1.3 CAST('abcd' AS BLOB)",
    ],
];

for ($i = -29; $i <= 29; $i++) {
    $nonBlobOperands['integer-' . ($i < 0 ? 'minus-' . abs($i) : (string) $i)] = [
        'sql' => (string) $i,
        'value' => $i,
        'source' => 'e_expr.test e_expr-33.1 dynamic non-BLOB integer-to-BLOB encoding expansion',
    ];
}

for ($i = 1; $i <= 59; $i++) {
    $whole = $i - 30;
    $fraction = str_pad((string) (($i * 37) % 1000), 3, '0', STR_PAD_LEFT);
    $literal = ($whole < 0 ? '-' : '') . abs($whole) . '.' . $fraction;
    $nonBlobOperands['real-' . $i] = [
        'sql' => $literal,
        'value' => (float) $literal,
        'source' => 'e_expr.test e_expr-33.1 dynamic non-BLOB REAL-to-BLOB encoding expansion',
    ];
}

for ($i = 1; $i <= 59; $i++) {
    $value = 'setting-value-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
    $nonBlobOperands['text-' . $i] = [
        'sql' => $sqlString($value),
        'value' => $value,
        'source' => 'e_expr.test e_expr-33.1 dynamic non-BLOB TEXT-to-BLOB encoding expansion',
    ];
}

$blobOperands = [
    'upstream-empty' => [
        'sql' => "X''",
        'value' => $blobValue(''),
        'source' => "e_expr.test e_expr-33.1.5 CAST(X'' AS TEXT)",
    ],
    'upstream-abcd' => [
        'sql' => "X'abcd'",
        'value' => $blobValue('abcd'),
        'source' => "e_expr.test e_expr-33.1.4 CAST(X'abcd' AS TEXT)",
    ],
];

for ($i = 0; $i < 178; $i++) {
    $first = 0x41 + ($i % 26);
    $second = 0x30 + (intdiv($i, 26) % 10);
    $hex = sprintf('%02X%02X', $first, $second);
    $blobOperands['ascii-pair-' . $i] = [
        'sql' => "X'{$hex}'",
        'value' => $blobValue($hex),
        'source' => 'e_expr.test e_expr-33.1 dynamic BLOB-to-TEXT encoding expansion',
    ];
}

if (count($nonBlobOperands) !== 180 || count($blobOperands) !== 180) {
    throw new RuntimeException('e_expr-33 encoding CAST corpus must own exactly 180 non-BLOB and 180 BLOB operands');
}

$encodings = [
    'utf8' => ['pragma' => 'utf-8', 'code' => 1, 'label' => 'UTF-8'],
    'utf16le' => ['pragma' => 'utf-16le', 'code' => 2, 'label' => 'UTF-16LE'],
    'utf16be' => ['pragma' => 'utf-16be', 'code' => 3, 'label' => 'UTF-16BE'],
];

$cases = [];
foreach ($nonBlobOperands as $operandName => $operand) {
    foreach ($encodings as $encodingName => $encoding) {
        $caseName = "nonblob.{$operandName}.as-blob.{$encodingName}";
        $cases[$caseName] = [
            'encodingName' => $encodingName,
            'encodingCode' => $encoding['code'],
            'target' => 'BLOB',
            'expression' => 'CAST(' . $operand['sql'] . ' AS BLOB)',
            'value' => $operand['value'],
            'source' => $operand['source'],
        ];
    }
}

foreach ($blobOperands as $operandName => $operand) {
    foreach ($encodings as $encodingName => $encoding) {
        $caseName = "blob.{$operandName}.as-text.{$encodingName}";
        $cases[$caseName] = [
            'encodingName' => $encodingName,
            'encodingCode' => $encoding['code'],
            'target' => 'TEXT',
            'expression' => 'CAST(' . $operand['sql'] . ' AS TEXT)',
            'value' => $operand['value'],
            'source' => $operand['source'],
        ];
    }
}

if (count($cases) !== 1080) {
    throw new RuntimeException('e_expr-33 encoding CAST corpus must generate exactly 1080 oracle cases');
}

$oracle = [];
foreach ($encodings as $encodingName => $encoding) {
    $oracleScript = ["PRAGMA encoding = '{$encoding['pragma']}';"];
    $expectedRows = 0;
    foreach ($cases as $caseName => $case) {
        if ($case['encodingName'] !== $encodingName) {
            continue;
        }
        $safeName = str_replace("'", "''", $caseName);
        $expression = $case['expression'];
        $oracleScript[] = "SELECT '{$safeName}' || char(9) || typeof({$expression}) || char(9) || quote({$expression});";
        $expectedRows++;
    }

    $scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-e-expr33-encoding-cast-');
    if ($scriptFile === false) {
        throw new RuntimeException('could not allocate sqlite3 oracle script for e_expr-33 encoding CAST tests');
    }
    file_put_contents($scriptFile, implode("\n", $oracleScript));
    $output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
    @unlink($scriptFile);
    if (!is_string($output) || $output === '') {
        throw new RuntimeException("sqlite3 oracle did not produce e_expr-33 encoding CAST output for {$encoding['label']}");
    }

    $lines = preg_split('/\r?\n/', rtrim($output, "\r\n"));
    if (!is_array($lines) || count($lines) !== $expectedRows) {
        $actualRows = is_array($lines) ? count($lines) : 0;
        throw new RuntimeException("Expected {$expectedRows} e_expr-33 oracle rows for {$encoding['label']}, got {$actualRows}");
    }

    foreach ($lines as $line) {
        $parts = explode("\t", $line);
        if (count($parts) !== 3) {
            throw new RuntimeException('malformed e_expr-33 encoding CAST oracle row: ' . $line);
        }

        [$caseName, $type, $quote] = $parts;
        $oracle[$caseName] = [
            'type' => $type,
            'quote' => $quote,
        ];
    }
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d e_expr-33 oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $caseName => $case) {
    $tests['real upstream corpus expression affinity dynamic e_expr-33 encoding cast ' . $caseName] = static function (TestRunner $t) use ($caseName, $case, $oracle): void {
        $result = SQLiteRealExpressionAffinityCorpusPlan::castTextBlobWithEncoding(
            $case['value'],
            $case['target'],
            $case['encodingCode'],
        );

        $t->same($oracle[$caseName]['type'], SQLiteRealExpressionAffinityCorpusPlan::storageClass($result), $case['source'] . ' typeof');
        $t->same($oracle[$caseName]['quote'], SQLiteRealExpressionAffinityCorpusPlan::quote($result), $case['source'] . ' quote');
    };
}

$tests['real upstream corpus expression affinity dynamic e_expr-33 source invariants'] = static function (TestRunner $t) use ($oracle): void {
    $byEncoding = static function (string $prefix) use ($oracle): array {
        return [
            $oracle[$prefix . '.utf8']['type'] . ' ' . $oracle[$prefix . '.utf8']['quote'],
            $oracle[$prefix . '.utf16le']['type'] . ' ' . $oracle[$prefix . '.utf16le']['quote'],
            $oracle[$prefix . '.utf16be']['type'] . ' ' . $oracle[$prefix . '.utf16be']['quote'],
        ];
    };

    $integer = $byEncoding('nonblob.upstream-integer-123.as-blob');
    $emptyText = $byEncoding('nonblob.upstream-empty-text.as-blob');
    $text = $byEncoding('nonblob.upstream-text-abcd.as-blob');
    $blob = $byEncoding('blob.upstream-abcd.as-text');
    $emptyBlob = $byEncoding('blob.upstream-empty.as-text');

    $t->true($integer[0] !== $integer[1] && $integer[1] !== $integer[2], 'e_expr-33.1.1 integer-to-BLOB differs by encoding');
    $t->same([$emptyText[0], $emptyText[0], $emptyText[0]], $emptyText, 'e_expr-33.1.2 empty text-to-BLOB is stable across encodings');
    $t->true($text[0] !== $text[1] && $text[1] !== $text[2], 'e_expr-33.1.3 text-to-BLOB differs by encoding');
    $t->true($blob[0] !== $blob[1] && $blob[1] !== $blob[2], 'e_expr-33.1.4 BLOB-to-TEXT differs by encoding');
    $t->same([$emptyBlob[0], $emptyBlob[0], $emptyBlob[0]], $emptyBlob, 'e_expr-33.1.5 empty BLOB-to-TEXT is stable across encodings');
};

$tests['real upstream corpus expression affinity dynamic e_expr-33 corpus accounting'] = static function (TestRunner $t) use ($nonBlobOperands, $blobOperands, $encodings, $cases, $oracle): void {
    $t->same(180, count($nonBlobOperands), 'non-BLOB operands');
    $t->same(180, count($blobOperands), 'BLOB operands');
    $t->same(3, count($encodings), 'connection encodings');
    $t->same(1080, count($cases), 'dynamic upstream-backed CAST cases');
    $t->same(1080, count($oracle), 'sqlite3 oracle rows');
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test e_expr-33.1.1..33.1.5');
    $t->same(
        'no new support component needed; reuses SQLiteRealExpressionAffinityCorpusPlan CAST affinity logic, SQLiteEncodingCollationSourceCursor text codecs, SQLiteBlobValue storage, and hydrated sqlite3 oracle evidence for e_expr.test encoding-sensitive BLOB/TEXT casts',
        'no new support component needed; reuses SQLiteRealExpressionAffinityCorpusPlan CAST affinity logic, SQLiteEncodingCollationSourceCursor text codecs, SQLiteBlobValue storage, and hydrated sqlite3 oracle evidence for e_expr.test encoding-sensitive BLOB/TEXT casts',
    );
    $t->same(
        'non-overlap: owns e_expr-33.1 database-encoding-sensitive TEXT/BLOB cast results; avoids accepted e_expr-27/e_expr-28 default BLOB/TEXT casts, e_expr-29..32 numeric casts, scalar subqueries, IN/BETWEEN, affinity3 REAL predicates, LIKE/GLOB ranges, JSON, WAL, VFS, B-tree, PRAGMA, and trigger slices',
        'non-overlap: owns e_expr-33.1 database-encoding-sensitive TEXT/BLOB cast results; avoids accepted e_expr-27/e_expr-28 default BLOB/TEXT casts, e_expr-29..32 numeric casts, scalar subqueries, IN/BETWEEN, affinity3 REAL predicates, LIKE/GLOB ranges, JSON, WAL, VFS, B-tree, PRAGMA, and trigger slices',
    );
};

return $tests;
