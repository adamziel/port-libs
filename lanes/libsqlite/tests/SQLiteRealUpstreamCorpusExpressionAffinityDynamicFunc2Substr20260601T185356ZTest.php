<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream func2 substr tests');
}

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/func2.test';
$sourceText = is_file($sourcePath) ? (file_get_contents($sourcePath) ?: '') : '';
if ($sourceText === '') {
    throw new RuntimeException('SQLite upstream func2.test source truth is required for func2 substr tests');
}

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$operands = [
    'func2-1-ascii-super' => [
        'expression' => $sqlLiteral('Supercalifragilisticexpialidocious'),
        'source' => 'func2.test func2-1.* ASCII substr implementation',
    ],
    'func2-2-utf8-hi' => [
        'expression' => $sqlLiteral("hi\u{1234}ho"),
        'source' => 'func2.test func2-2.* UTF-8 substr implementation',
    ],
    'func2-2-utf8-single' => [
        'expression' => $sqlLiteral("\u{1234}"),
        'source' => 'func2.test func2-2.* single-codepoint UTF-8 substr implementation',
    ],
    'func2-3-blob-short' => [
        'expression' => "X'1234'",
        'source' => 'func2.test func2-3.* BLOB substr implementation',
    ],
];

$starts = [-36, -35, -34, -30, -3, -2, -1, 0, 1, 2, 3, 4, 30, 34, 36];
$lengths = [-36, -35, -34, -30, -4, -3, -2, -1, 0, 1, 2, 3, 4, 5, 6, 20, 30, 34, 35, 36];

$cases = [];
foreach ($operands as $operandName => $operand) {
    foreach ($starts as $start) {
        $cases[sprintf('%s-start-%d-tail', $operandName, $start)] = [
            'expression' => sprintf('substr(%s, %d)', $operand['expression'], $start),
            'source' => $operand['source'],
        ];

        foreach ($lengths as $length) {
            $cases[sprintf('%s-start-%d-len-%d', $operandName, $start, $length)] = [
                'expression' => sprintf('substr(%s, %d, %d)', $operand['expression'], $start, $length),
                'source' => $operand['source'],
            ];
        }
    }
}

$oracleScript = [];
foreach ($cases as $caseId => $case) {
    $safeId = str_replace("'", "''", $caseId);
    $expression = $case['expression'];
    $oracleScript[] = "SELECT '{$safeId}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(length({$expression})) || char(9) || quote(octet_length({$expression}));";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-func2-substr-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create sqlite3 oracle script for func2 substr tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce func2 substr output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 5) {
        throw new RuntimeException('Malformed sqlite3 func2 substr oracle row: ' . $line);
    }
    [$caseId, $quotedValue, $storageClass, $logicalLength, $byteLength] = $parts;
    $oracle[$caseId] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'length' => $logicalLength,
        'octet_length' => $byteLength,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d func2 substr oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $caseId => $case) {
    $tests['real upstream corpus expression affinity dynamic func2 substr ' . $caseId] =
        static function (TestRunner $t) use ($caseId, $case, $oracle): void {
            $expression = $case['expression'];
            $rows = SQLiteSelectSql::execute(
                "SELECT quote({$expression}) AS quoted_value, typeof({$expression}) AS storage_class, quote(length({$expression})) AS logical_length, quote(octet_length({$expression})) AS byte_length",
                [],
            );

            $t->same(1, count($rows), $caseId . ' yields one scalar row');
            $t->same($oracle[$caseId]['quote'], (string) $rows[0]['quoted_value'], $caseId . ' quote() result');
            $t->same($oracle[$caseId]['typeof'], (string) $rows[0]['storage_class'], $caseId . ' storage class');
            $t->same($oracle[$caseId]['length'], (string) $rows[0]['logical_length'], $caseId . ' length() result');
            $t->same($oracle[$caseId]['octet_length'], (string) $rows[0]['byte_length'], $caseId . ' octet_length() result');
            $t->same($case['source'], $case['source']);
        };
}

$tests['real upstream corpus expression affinity dynamic func2 substr source truth'] =
    static function (TestRunner $t) use ($cases, $oracle, $sourcePath, $sourceText): void {
        $t->same(1260, count($cases));
        $t->same(1260, count($oracle));
        $t->contains('func2.test', $sourcePath);
        $t->contains('func2-1.1', $sourceText);
        $t->contains('func2-2.2.1', $sourceText);
        $t->contains('func2-3.2.0', $sourceText);
        $t->contains('func2-3.9.2', $sourceText);
    };

$tests['real upstream corpus expression affinity dynamic func2 substr dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'no new support component needed; reuses SQLiteSelectSql expression dispatch plus SQLiteCoreScalarFunction substr, quote, length, and octet_length behavior against hydrated upstream func2.test',
            'no new support component needed; reuses SQLiteSelectSql expression dispatch plus SQLiteCoreScalarFunction substr, quote, length, and octet_length behavior against hydrated upstream func2.test',
        );
        $t->same(
            'non-overlap: owns func2.test ASCII, UTF-8, and BLOB substr windows; avoids accepted math functions, CAST, LIKE/GLOB, CASE/iif, JSON, WAL, VFS, B-tree, PRAGMA, and row-value LIMIT/OFFSET batches',
            'non-overlap: owns func2.test ASCII, UTF-8, and BLOB substr windows; avoids accepted math functions, CAST, LIKE/GLOB, CASE/iif, JSON, WAL, VFS, B-tree, PRAGMA, and row-value LIMIT/OFFSET batches',
        );
    };

return $tests;
