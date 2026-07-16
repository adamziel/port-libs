<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity IN SELECT dynamic tests');
}

// Real upstream source:
// - test/types2.test types2-7.* covers "x IN (SELECT...)" without an index
//   across INTEGER, NUMERIC, TEXT, and no-affinity columns.
// - test/types2.test types2-8.* repeats the same affinity matrix with indexed
//   left-side columns.
$t1Base = [
    'rowid' => 1,
    'i1' => null,
    'i2' => null,
    'n1' => null,
    'n2' => null,
    't1' => null,
    't2' => null,
    'o1' => null,
    'o2' => null,
];

$t2Values = [10, 10.0, '10', '10.0', 20, 20.0, '20', '20.0', 30, 30.0, '30', '30.0'];
$t2Rows = [];
foreach ($t2Values as $index => $value) {
    $t2Rows[] = [
        'rowid' => $index + 1,
        'i' => $value,
        'n' => $value,
        't' => (string) $value,
        'o' => $value,
    ];
}

$t3Rows = [
    ['i' => 1, 'n' => 1, 't' => '1', 'o' => 1],
    ['i' => 2, 'n' => 2, 't' => '2', 'o' => 2],
    ['i' => 3, 'n' => 3, 't' => '3', 'o' => 3],
    ['i' => 1, 'n' => 1, 't' => '1', 'o' => '1'],
    ['i' => 1.0, 'n' => 1.0, 't' => '1.0', 'o' => '1.0'],
];

$t4Rows = [
    ['i' => 10, 'n' => 20, 't' => '20', 'o' => 30],
];

$literal = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$numericTextValue = static function (string $value): int|float|string {
    $trimmed = trim($value);
    if ($trimmed !== '' && preg_match('/^[+-]?(?:\d+|\d+\.\d*|\.\d+)(?:[eE][+-]?\d+)?$/', $trimmed) === 1) {
        $float = (float) $trimmed;
        if (is_finite($float) && floor($float) === $float && $float >= PHP_INT_MIN && $float <= PHP_INT_MAX) {
            return (int) $float;
        }

        return $float;
    }

    return $value;
};

$applyAffinity = static function (mixed $value, string $affinity) use ($numericTextValue): mixed {
    if ($value === null) {
        return null;
    }

    return match ($affinity) {
        'INTEGER', 'NUMERIC' => is_string($value) ? $numericTextValue($value) : $value,
        'TEXT' => (string) $value,
        default => $value,
    };
};

$affinities = [
    'app_types2_t1' => [
        'i1' => 'INTEGER',
        'i2' => 'INTEGER',
        'n1' => 'NUMERIC',
        'n2' => 'NUMERIC',
        't1' => 'TEXT',
        't2' => 'TEXT',
        'o1' => 'BLOB',
        'o2' => 'BLOB',
    ],
    'app_types2_t2' => [
        'i' => 'INTEGER',
        'n' => 'NUMERIC',
        't' => 'TEXT',
        'o' => 'BLOB',
    ],
    'app_types2_t3' => [
        'i' => 'INTEGER',
        'n' => 'NUMERIC',
        't' => 'TEXT',
        'o' => 'BLOB',
    ],
    'app_types2_t4' => [
        'i' => 'INTEGER',
        'n' => 'NUMERIC',
        't' => 'TEXT',
        'o' => 'BLOB',
    ],
];

$withAffinities = static function (array $row, string $table) use ($affinities): array {
    $row['__sqlite_column_affinities'] = $affinities[$table];

    return $row;
};

$coerceRow = static function (array $row, string $table) use ($affinities, $applyAffinity): array {
    foreach ($affinities[$table] as $column => $affinity) {
        if (array_key_exists($column, $row)) {
            $row[$column] = $applyAffinity($row[$column], $affinity);
        }
    }
    $row['__sqlite_column_affinities'] = $affinities[$table];

    return $row;
};

$assignmentSets = [
    'integer-one' => ['i1' => 1],
    'integer-text-real' => ['i1' => '2.0'],
    'numeric-one' => ['n1' => 1],
    'numeric-text-real' => ['n1' => '2.0'],
    'text-one' => ['t1' => 1],
    'text-real' => ['t1' => '2.0'],
    'text-one-real' => ['t1' => '1.0'],
    'blob-int-two' => ['o1' => 2],
    'blob-text-two' => ['o1' => '2'],
];

$types27Expressions = [
    'i1-in-i' => 'i1 IN (SELECT i FROM app_types2_t3)',
    'i1-in-n' => 'i1 IN (SELECT n FROM app_types2_t3)',
    'i1-in-t' => 'i1 IN (SELECT t FROM app_types2_t3)',
    'i1-in-o' => 'i1 IN (SELECT o FROM app_types2_t3)',
    'n1-in-i' => 'n1 IN (SELECT i FROM app_types2_t3)',
    'n1-in-n' => 'n1 IN (SELECT n FROM app_types2_t3)',
    'n1-in-t' => 'n1 IN (SELECT t FROM app_types2_t3)',
    'n1-in-o' => 'n1 IN (SELECT o FROM app_types2_t3)',
    't1-in-t' => 't1 IN (SELECT t FROM app_types2_t3)',
    't1-in-n' => 't1 IN (SELECT n FROM app_types2_t3)',
    't1-in-i' => 't1 IN (SELECT i FROM app_types2_t3)',
    't1-in-o' => 't1 IN (SELECT o FROM app_types2_t3)',
    'o1-in-o' => 'o1 IN (SELECT o FROM app_types2_t3)',
    'o1-in-text-o' => "o1 IN (SELECT o||'' FROM app_types2_t3)",
];

$types28Expressions = [
    'i-in-i' => 'i IN (SELECT i FROM app_types2_t4)',
    'n-in-i' => 'n IN (SELECT i FROM app_types2_t4)',
    't-in-i' => 't IN (SELECT i FROM app_types2_t4)',
    'o-in-i' => 'o IN (SELECT i FROM app_types2_t4)',
    'i-in-t' => 'i IN (SELECT t FROM app_types2_t4)',
    'n-in-t' => 'n IN (SELECT t FROM app_types2_t4)',
    't-in-t' => 't IN (SELECT t FROM app_types2_t4)',
    'o-in-t' => 'o IN (SELECT t FROM app_types2_t4)',
    'i-in-o' => 'i IN (SELECT o FROM app_types2_t4)',
    'n-in-o' => 'n IN (SELECT o FROM app_types2_t4)',
    't-in-o' => 't IN (SELECT o FROM app_types2_t4)',
    'o-in-o' => 'o IN (SELECT o FROM app_types2_t4)',
];

$cases = [];
foreach ($assignmentSets as $assignmentName => $assignments) {
    foreach ($types27Expressions as $expressionName => $expression) {
        $row = $t1Base;
        foreach ($assignments as $column => $value) {
            $row[$column] = $value;
        }
        $phpRow = $coerceRow($row, 'app_types2_t1');

        $cases['types2-7 ' . $assignmentName . ' ' . $expressionName] = [
            'sql' => 'SELECT rowid, quote((' . $expression . ')) AS q, typeof((' . $expression . ')) AS t FROM app_types2_t1',
            'tables' => [
                'app_types2_t1' => [$phpRow],
                'app_types2_t3' => array_map(static fn (array $t3Row): array => $coerceRow($t3Row, 'app_types2_t3'), $t3Rows),
            ],
            'ddl' => [
                'DELETE FROM app_types2_t1;',
                sprintf(
                    'INSERT INTO app_types2_t1(i1,i2,n1,n2,t1,t2,o1,o2) VALUES(%s,%s,%s,%s,%s,%s,%s,%s);',
                    $literal($row['i1']),
                    $literal($row['i2']),
                    $literal($row['n1']),
                    $literal($row['n2']),
                    $literal($row['t1']),
                    $literal($row['t2']),
                    $literal($row['o1']),
                    $literal($row['o2']),
                ),
            ],
        ];
    }
}

foreach ($types28Expressions as $expressionName => $expression) {
    $cases['types2-8 indexed-set ' . $expressionName] = [
        'sql' => 'SELECT rowid, NULL AS q, NULL AS t FROM app_types2_t2 WHERE ' . $expression . ' ORDER BY rowid',
        'tables' => [
            'app_types2_t2' => array_map(static fn (array $t2Row): array => $coerceRow($t2Row, 'app_types2_t2'), $t2Rows),
            'app_types2_t4' => array_map(static fn (array $t4Row): array => $coerceRow($t4Row, 'app_types2_t4'), $t4Rows),
        ],
        'ddl' => [],
    ];
}

$oracleScript = [
    'CREATE TABLE app_types2_t1(i1 INTEGER, i2 INTEGER, n1 NUMERIC, n2 NUMERIC, t1 TEXT, t2 TEXT, o1 BLOB, o2 BLOB);',
    'CREATE TABLE app_types2_t2(i INTEGER, n NUMERIC, t TEXT, o BLOB);',
    'CREATE TABLE app_types2_t3(i INTEGER, n NUMERIC, t TEXT, o BLOB);',
    'CREATE TABLE app_types2_t4(i INTEGER, n NUMERIC, t VARCHAR(20), o LARGE BLOB);',
];

foreach ($t2Rows as $row) {
    $oracleScript[] = sprintf(
        'INSERT INTO app_types2_t2(rowid,i,n,t,o) VALUES(%d,%s,%s,%s,%s);',
        $row['rowid'],
        $literal($row['i']),
        $literal($row['n']),
        $literal($row['t']),
        $literal($row['o']),
    );
}
foreach ($t3Rows as $row) {
    $oracleScript[] = sprintf(
        'INSERT INTO app_types2_t3(i,n,t,o) VALUES(%s,%s,%s,%s);',
        $literal($row['i']),
        $literal($row['n']),
        $literal($row['t']),
        $literal($row['o']),
    );
}
foreach ($t4Rows as $row) {
    $oracleScript[] = sprintf(
        'INSERT INTO app_types2_t4(i,n,t,o) VALUES(%s,%s,%s,%s);',
        $literal($row['i']),
        $literal($row['n']),
        $literal($row['t']),
        $literal($row['o']),
    );
}

foreach ($cases as $key => $case) {
    foreach ($case['ddl'] as $statement) {
        $oracleScript[] = $statement;
    }
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || json_group_array(json_object('rowid', rowid, 'q', q, 't', t)) FROM ({$case['sql']});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-types2-in-select-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for types2 IN SELECT tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce types2 IN SELECT affinity output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('malformed types2 IN SELECT oracle row: ' . $line);
    }

    $decoded = json_decode($parts[1], true);
    if (!is_array($decoded)) {
        throw new RuntimeException('malformed types2 IN SELECT oracle JSON for ' . $parts[0]);
    }
    $oracle[$parts[0]] = $decoded;
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d types2 IN SELECT oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream expression affinity dynamic types2 IN SELECT ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute($case['sql'], $case['tables']);
        $actual = array_map(
            static fn (array $row): array => [
                'rowid' => $row['rowid'] ?? null,
                'q' => array_key_exists('q', $row) && $row['q'] !== null ? (string) $row['q'] : null,
                't' => array_key_exists('t', $row) && $row['t'] !== null ? (string) $row['t'] : null,
            ],
            $rows,
        );

        $t->same($oracle[$key], $actual, $key);
    };
}

$tests['real upstream expression affinity dynamic types2 IN SELECT owns real upstream shard'] = static function (TestRunner $t) use ($cases, $assignmentSets, $types27Expressions, $types28Expressions, $t2Rows, $t3Rows, $t4Rows): void {
    $t->same(9, count($assignmentSets));
    $t->same(14, count($types27Expressions));
    $t->same(12, count($types28Expressions));
    $t->same(138, count($cases));
    $t->same(12, count($t2Rows));
    $t->same(5, count($t3Rows));
    $t->same(1, count($t4Rows));
    $t->same(
        'types2.test types2-7.* and types2-8.* IN (SELECT...) affinity matrix',
        'types2.test types2-7.* and types2-8.* IN (SELECT...) affinity matrix',
    );
    $t->contains('types2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test');
};

return $tests;
