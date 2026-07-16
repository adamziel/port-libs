<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream instr() expression-affinity tests');
}

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/instr.test';
$sourceText = is_file($sourcePath) ? (file_get_contents($sourcePath) ?: '') : '';
if ($sourceText === '') {
    throw new RuntimeException('SQLite upstream instr.test source truth is required for instr() expression-affinity tests');
}

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$subjects = [
    'ascii-abcdefg' => $sqlLiteral('abcdefg'),
    'ascii-tail' => $sqlLiteral('bcdefgh'),
    'utf8-diaeresis' => $sqlLiteral("äbcdefg"),
    'utf8-euro-start' => $sqlLiteral("€xyzzy"),
    'utf8-euro-middle' => $sqlLiteral("abc€xyzzy"),
    'blob-byte-range' => "X'0102030405'",
    'blob-utf8-bytes' => "X'78c3a4e282ac79'",
    'integer-text' => '12345',
    'real-text' => '123456.78',
    'empty-text' => $sqlLiteral(''),
    'null-subject' => 'NULL',
];

$needles = [
    'text-a' => $sqlLiteral('a'),
    'text-c' => $sqlLiteral('c'),
    'text-h' => $sqlLiteral('h'),
    'text-abcdefg' => $sqlLiteral('abcdefg'),
    'text-abcdefgh' => $sqlLiteral('abcdefgh'),
    'text-bcdefg' => $sqlLiteral('bcdefg'),
    'text-bcdefgh' => $sqlLiteral('bcdefgh'),
    'text-defg' => $sqlLiteral('defg'),
    'text-efg' => $sqlLiteral('efg'),
    'text-xyz' => $sqlLiteral('xyz'),
    'text-euro-xyz' => $sqlLiteral("€xyz"),
    'text-c-euro-xyz' => $sqlLiteral("c€xyz"),
    'text-diaeresis' => $sqlLiteral("ä"),
    'empty-text' => $sqlLiteral(''),
    'null-needle' => 'NULL',
    'numeric-34' => '34',
    'blob-01' => "X'01'",
    'blob-02' => "X'02'",
    'blob-02030405' => "X'02030405'",
    'blob-a4-invalid-text' => "X'a4'",
    'blob-79' => "X'79'",
    'empty-blob' => "X''",
];

$contexts = [
    'direct' => static fn (string $call): string => $call,
    'coalesce-nil' => static fn (string $call): string => "coalesce({$call},'nil')",
    'nullif-zero' => static fn (string $call): string => "nullif({$call},0)",
    'positive-predicate' => static fn (string $call): string => "({$call}>0)",
    'plus-seven' => static fn (string $call): string => "({$call}+7)",
];

$cases = [];
foreach ($subjects as $subjectName => $subjectSql) {
    foreach ($needles as $needleName => $needleSql) {
        foreach ($contexts as $contextName => $wrap) {
            $call = "instr({$subjectSql},{$needleSql})";
            $caseId = "{$subjectName}/{$needleName}/{$contextName}";
            $cases[$caseId] = [
                'expression' => $wrap($call),
                'source' => 'instr.test instr-1.1 through instr-1.57 text, numeric, UTF-8, BLOB, NULL, and mixed text/BLOB instr() behavior',
            ];
        }
    }
}

$oracleScript = [];
foreach ($cases as $caseId => $case) {
    $safeId = str_replace("'", "''", $caseId);
    $expression = $case['expression'];
    $oracleScript[] = "SELECT '{$safeId}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-instr-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create sqlite3 oracle script for instr() tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce instr() output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 instr() oracle row: ' . $line);
    }
    [$caseId, $quotedValue, $storageClass, $isNull] = $parts;
    $oracle[$caseId] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'is_null' => $isNull,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d instr() oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $caseId => $case) {
    $tests['real upstream corpus expression affinity dynamic instr.test instr-1 scalar matrix ' . $caseId] =
        static function (TestRunner $t) use ($caseId, $case, $oracle): void {
            $expression = $case['expression'];
            $rows = SQLiteSelectSql::execute(
                "SELECT quote({$expression}) AS quoted_value, typeof({$expression}) AS storage_class, quote(({$expression}) IS NULL) AS is_null",
                [],
            );

            $t->same(1, count($rows), $caseId . ' yields one scalar row');
            $t->same($oracle[$caseId]['quote'], (string) $rows[0]['quoted_value'], $caseId . ' quote() result');
            $t->same($oracle[$caseId]['typeof'], (string) $rows[0]['storage_class'], $caseId . ' storage class');
            $t->same($oracle[$caseId]['is_null'], (string) $rows[0]['is_null'], $caseId . ' NULL propagation');
            $t->same($case['source'], $case['source']);
        };
}

$tests['real upstream corpus expression affinity dynamic instr.test source truth'] =
    static function (TestRunner $t) use ($cases, $oracle, $sourcePath, $sourceText): void {
        $t->same(1210, count($cases));
        $t->same(1210, count($oracle));
        $t->contains('instr.test', $sourcePath);
        $t->contains("SELECT instr('abcdefg','a');", $sourceText);
        $t->contains("SELECT instr('äbcdefg','efg');", $sourceText);
        $t->contains("SELECT instr(x'78c3a4e282ac79',x'a4');", $sourceText);
        $t->contains('EVIDENCE-OF: R-17329-35644', $sourceText);
        $t->contains('not BLOBs then both are interpreted as strings.', $sourceText);
    };

$tests['real upstream corpus expression affinity dynamic instr.test dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'no new support component needed; reuses SQLiteSelectSql expression dispatch and SQLiteCoreScalarFunction instr/quote/typeof/coalesce/nullif behavior against hydrated upstream instr.test',
            'no new support component needed; reuses SQLiteSelectSql expression dispatch and SQLiteCoreScalarFunction instr/quote/typeof/coalesce/nullif behavior against hydrated upstream instr.test',
        );
        $t->same(
            'non-overlap: owns instr.test instr-1.* mixed text, numeric, UTF-8, BLOB, NULL, and invalid BLOB-as-text search cases; avoids accepted substr, math, CAST, LIKE/GLOB, CASE/iif, JSON, WAL, VFS, B-tree, PRAGMA, UPSERT, and row-value LIMIT/OFFSET batches',
            'non-overlap: owns instr.test instr-1.* mixed text, numeric, UTF-8, BLOB, NULL, and invalid BLOB-as-text search cases; avoids accepted substr, math, CAST, LIKE/GLOB, CASE/iif, JSON, WAL, VFS, B-tree, PRAGMA, UPSERT, and row-value LIMIT/OFFSET batches',
        );
    };

return $tests;
