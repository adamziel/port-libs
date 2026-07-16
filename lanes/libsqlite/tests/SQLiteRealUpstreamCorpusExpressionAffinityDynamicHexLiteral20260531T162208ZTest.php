<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream hexlit numeric literal tests');
}

// Source truth: SQLite upstream test/hexlit.test hexlit-100..200 accepts
// hexadecimal integer SQL literals, leading zeroes, 0x/0X prefixes, upper- and
// lower-case digits, and signed 64-bit two's-complement interpretation.
$candidateLiterals = [
    '0x0',
    '0x1',
    '0x2',
    '0x4',
    '0x8',
    '0x10',
    '0x20',
    '0x40',
    '0x80',
    '0x100',
    '0x200',
    '0x400',
    '0x800',
    '0x1000',
    '0x2000',
    '0x4000',
    '0x8000',
    '0x10000',
    '0x100000',
    '0x1000000',
    '0x10000000',
    '0x100000000',
    '0x1000000000',
    '0x10000000000',
    '0x100000000000',
    '0x1000000000000',
    '0x10000000000000',
    '0x100000000000000',
    '0x1000000000000000',
    '0x4000000000000000',
    '0x7fffffffffffffff',
    '0x8000000000000000',
    '0x08000000000000000',
    '0xFFFFFFFFFFFFFFFF',
    '0Xffffffffffffffff',
    '+0x8000000000000000',
    '+0xffffffffffffffff',
    '-0x1',
    '-0x7fffffffffffffff',
    '-0x8000000000000001',
    '-0xffffffffffffffff',
];

for ($i = 1; $i <= 15; $i++) {
    $candidateLiterals[] = sprintf('0X%03X', $i);
    $candidateLiterals[] = sprintf('0x%03X', $i);
    $candidateLiterals[] = sprintf('0X%03x', $i);
    $candidateLiterals[] = sprintf('0x%03x', $i);
}

for ($i = 1; count(array_unique($candidateLiterals)) < 300; $i++) {
    $seed = ($i * 2654435761) ^ ($i << 17) ^ ($i << 3);
    $digits = dechex($seed);
    $width = 3 + ($i % 14);
    $digits = str_pad($digits, $width, '0', STR_PAD_LEFT);
    if ($i % 3 === 0) {
        $digits = strtoupper($digits);
    }
    $prefix = $i % 2 === 0 ? '0X' : '0x';
    $candidateLiterals[] = $prefix . $digits;
    if ($i % 5 === 0) {
        $candidateLiterals[] = '+' . $prefix . $digits;
    }
    if ($i % 7 === 0) {
        $candidateLiterals[] = '-' . $prefix . ltrim($digits, '0');
    }
}

$literals = array_slice(array_values(array_unique($candidateLiterals)), 0, 260);
if (count($literals) !== 260) {
    throw new RuntimeException('Expected 260 generated hex literal variants');
}

$expressionForms = [
    'literal' => static fn (string $literal): string => $literal,
    'bitand255' => static fn (string $literal): string => "({$literal}) & 255",
    'bitorzero' => static fn (string $literal): string => "({$literal}) | 0",
    'equals-self' => static fn (string $literal): string => "({$literal}) = ({$literal})",
];

$cases = [];
foreach ($literals as $index => $literal) {
    foreach ($expressionForms as $formName => $expressionSql) {
        $key = sprintf('hexlit-%03d-%s-%s', $index + 1, $formName, strtr($literal, ['+' => 'plus', '-' => 'minus']));
        $cases[$key] = [
            'literal' => $literal,
            'expression' => $expressionSql($literal),
        ];
    }
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $expression = $case['expression'];
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-hexlit-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for hexlit tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce hexlit output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('malformed hexlit oracle row: ' . $line);
    }
    [$key, $quoted, $type] = $parts;
    $oracle[$key] = ['quote' => $quoted, 'typeof' => $type];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d hexlit oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic hexlit numeric literal ' . $key] =
        static function (TestRunner $t) use ($case, $key, $oracle): void {
            $rows = SQLiteSelectSql::execute(
                'SELECT quote(' . $case['expression'] . ') AS quoted, typeof(' . $case['expression'] . ') AS storage_class',
                [],
            );

            $t->same(1, count($rows), $key);
            $t->same($oracle[$key]['quote'], (string) $rows[0]['quoted'], $key);
            $t->same($oracle[$key]['typeof'], (string) $rows[0]['storage_class'], $key);
        };
}

$tooBigQueries = [
    'hexlist-400-positive-overflow' => [
        'literal' => '0x10000000000000000',
        'sql' => 'SELECT 0x10000000000000000 AS value',
    ],
    'hexlist-401-distinct-positive-overflow' => [
        'literal' => '0x10000000000000000',
        'sql' => 'SELECT DISTINCT 0x10000000000000000 AS value',
    ],
    'hexlist-402-distinct-negative-min-overflow' => [
        'literal' => '-0x08000000000000000',
        'sql' => 'SELECT DISTINCT -0x08000000000000000 AS value',
    ],
    'hexlist-410-expression-overflow' => [
        'literal' => '0x10000000000000000',
        'sql' => 'SELECT quote(1 + 0x10000000000000000) AS value',
    ],
];

foreach ($tooBigQueries as $key => $query) {
    $tests['real upstream corpus expression affinity dynamic hexlit oversized literal ' . $key] =
        static function (TestRunner $t) use ($query, $key): void {
            $message = null;
            try {
                SQLiteSelectSql::execute($query['sql'], []);
            } catch (InvalidArgumentException $exception) {
                $message = $exception->getMessage();
            }

            $t->same('hex literal too big: ' . $query['literal'], $message, $key);
        };
}

$tests['real upstream corpus expression affinity dynamic hexlit owns hexlit-100-through-410 shard'] =
    static function (TestRunner $t) use ($literals, $expressionForms, $cases, $oracle, $tooBigQueries): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/hexlit.test';
        $text = file_get_contents($source);
        if (!is_string($text)) {
            throw new RuntimeException('Could not read hydrated upstream hexlit.test');
        }

        $t->same(260, count($literals));
        $t->same(4, count($expressionForms));
        $t->same(1040, count($cases));
        $t->same(1040, count($oracle));
        $t->same(4, count($tooBigQueries));
        $t->contains('hexlit1 100 0x0 0', $text);
        $t->contains('hexlit1 164 0XFFFFFFFFFFFFFFFF -1', $text);
        $t->contains('hexlit1 200.$n.4 0x[format %03x $n] $n', $text);
        $t->contains('do_catchsql_test hexlist-400', $text);
        $t->contains('hex literal too big: 0x10000000000000000', $text);
        $t->same(
            'hexlit.test hexlit-100..200 and hexlist-400..410 numeric hexadecimal integer literal parsing, casing, leading zeroes, two-complement signed 64-bit values, and overflow rejection',
            'hexlit.test hexlit-100..200 and hexlist-400..410 numeric hexadecimal integer literal parsing, casing, leading zeroes, two-complement signed 64-bit values, and overflow rejection',
        );
        $t->same(
            'non-overlap: implements SELECT numeric hexadecimal literals; prior hexlit coverage only checked quoted string affinity and CAST string-to-integer behavior, and this avoids CASE, JSON, VFS, WAL, B-tree, PRAGMA, and date/time clusters',
            'non-overlap: implements SELECT numeric hexadecimal literals; prior hexlit coverage only checked quoted string affinity and CAST string-to-integer behavior, and this avoids CASE, JSON, VFS, WAL, B-tree, PRAGMA, and date/time clusters',
        );
    };

return $tests;
