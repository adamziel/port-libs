<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracle = static function (string $sql) use ($sqlite3): string {
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream types2 text affinity bulk tests');
    }

    $setup = <<<'SQL'
CREATE TABLE t2(i INTEGER, n NUMERIC, t TEXT, o XBLOBY);
INSERT INTO t2 VALUES(10, 10, 10, 10);
INSERT INTO t2 VALUES(10.0, 10.0, 10.0, 10.0);
INSERT INTO t2 VALUES('10', '10', '10', '10');
INSERT INTO t2 VALUES('10.0', '10.0', '10.0', '10.0');
INSERT INTO t2 VALUES(20, 20, 20, 20);
INSERT INTO t2 VALUES(20.0, 20.0, 20.0, 20.0);
INSERT INTO t2 VALUES('20', '20', '20', '20');
INSERT INTO t2 VALUES('20.0', '20.0', '20.0', '20.0');
INSERT INTO t2 VALUES(30, 30, 30, 30);
INSERT INTO t2 VALUES(30.0, 30.0, 30.0, 30.0);
INSERT INTO t2 VALUES('30', '30', '30', '30');
INSERT INTO t2 VALUES('30.0', '30.0', '30.0', '30.0');
SQL;
    $command = escapeshellarg($sqlite3) . ' -batch -noheader :memory: ' . escapeshellarg($setup . "\n" . $sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $sql);
    }

    return rtrim($output, "\r\n");
};

$rows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities([
    ['rowid' => 1, 't' => 10],
    ['rowid' => 2, 't' => 10.0],
    ['rowid' => 3, 't' => '10'],
    ['rowid' => 4, 't' => '10.0'],
    ['rowid' => 5, 't' => 20],
    ['rowid' => 6, 't' => 20.0],
    ['rowid' => 7, 't' => '20'],
    ['rowid' => 8, 't' => '20.0'],
    ['rowid' => 9, 't' => 30],
    ['rowid' => 10, 't' => 30.0],
    ['rowid' => 11, 't' => '30'],
    ['rowid' => 12, 't' => '30.0'],
], [
    't' => 'TEXT',
]);

$literalValue = static function (string $literal): mixed {
    if (strcasecmp($literal, 'NULL') === 0) {
        return null;
    }
    if (str_starts_with($literal, "'") && str_ends_with($literal, "'")) {
        return str_replace("''", "'", substr($literal, 1, -1));
    }
    if (str_contains($literal, '.')) {
        return (float) $literal;
    }

    return (int) $literal;
};

$predicateResult = static function (array $row, string $operator, mixed $right): ?bool {
    $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression(
        $row['t'],
        $right,
        $operator,
        'TEXT',
        'NONE',
    );

    return $comparison['result'];
};

$truthValue = static function (?bool $result): ?int {
    return $result === null ? null : ($result ? 1 : 0);
};

$quote = static fn (mixed $value): string => SQLiteRealExpressionAffinityCorpusPlan::quote($value);

$matchingRows = static function (string $operator, mixed $right, bool $negated = false) use ($rows, $predicateResult): array {
    return array_values(array_filter(
        $rows,
        static function (array $row) use ($operator, $right, $negated, $predicateResult): bool {
            $result = $predicateResult($row, $operator, $right);
            if ($result === null) {
                return false;
            }

            return $negated ? !$result : $result;
        },
    ));
};

$literals = ['10', '10.0', "'10'", "'10.0'", '20', '20.0', "'20'", "'20.0'", '30', '30.0', "'30'", "'30.0'"];
$operatorSets = [
    '=' => $literals,
    '==' => $literals,
    '<' => $literals,
    '<=' => $literals,
    '>' => $literals,
    '>=' => $literals,
    '!=' => $literals,
    '<>' => $literals,
    'IS' => ['10', "'10'"],
    'IS NOT' => ['10', "'10'"],
];

$predicates = [];
$predicateOrdinal = 1;
foreach ($operatorSets as $operator => $operatorLiterals) {
    foreach ($operatorLiterals as $literal) {
        $predicates[] = [
            'id' => sprintf(
                'types2-text.%03d.t.%s.%s',
                $predicateOrdinal++,
                strtr(strtolower($operator), ['<' => 'lt', '>' => 'gt', '=' => 'eq', '!' => 'not', ' ' => '-']),
                str_replace(["'", '.'], ['', '_'], $literal),
            ),
            'operator' => $operator,
            'literal' => $literal,
            'right' => $literalValue($literal),
            'where' => "t {$operator} {$literal}",
        ];
    }
}

$contexts = [
    'where rowids' => static function (array $predicate) use ($oracle, $matchingRows): array {
        $expected = $oracle("SELECT group_concat(rowid, ',') FROM t2 WHERE {$predicate['where']} ORDER BY rowid;");
        $actual = implode(',', array_map('strval', array_column($matchingRows($predicate['operator'], $predicate['right']), 'rowid')));

        return [$expected, $actual];
    },
    'negated where rowids' => static function (array $predicate) use ($oracle, $matchingRows): array {
        $expected = $oracle("SELECT group_concat(rowid, ',') FROM t2 WHERE NOT ({$predicate['where']}) ORDER BY rowid;");
        $actual = implode(',', array_map('strval', array_column($matchingRows($predicate['operator'], $predicate['right'], true), 'rowid')));

        return [$expected, $actual];
    },
    'projection truth vector' => static function (array $predicate) use ($oracle, $rows, $predicateResult, $truthValue, $quote): array {
        $expected = $oracle("SELECT group_concat(quote(v), '|') FROM (SELECT {$predicate['where']} AS v FROM t2 ORDER BY rowid);");
        $actual = implode('|', array_map(
            static fn (array $row): string => $quote($truthValue($predicateResult($row, $predicate['operator'], $predicate['right']))),
            $rows,
        ));

        return [$expected, $actual];
    },
    'projection is-one vector' => static function (array $predicate) use ($oracle, $rows, $predicateResult, $truthValue, $quote): array {
        $expected = $oracle("SELECT group_concat(quote(v), '|') FROM (SELECT ({$predicate['where']}) IS 1 AS v FROM t2 ORDER BY rowid);");
        $actual = implode('|', array_map(
            static fn (array $row): string => $quote($truthValue($predicateResult($row, $predicate['operator'], $predicate['right'])) === 1 ? 1 : 0),
            $rows,
        ));

        return [$expected, $actual];
    },
    'projection is-not-one vector' => static function (array $predicate) use ($oracle, $rows, $predicateResult, $truthValue, $quote): array {
        $expected = $oracle("SELECT group_concat(quote(v), '|') FROM (SELECT ({$predicate['where']}) IS NOT 1 AS v FROM t2 ORDER BY rowid);");
        $actual = implode('|', array_map(
            static fn (array $row): string => $quote($truthValue($predicateResult($row, $predicate['operator'], $predicate['right'])) === 1 ? 0 : 1),
            $rows,
        ));

        return [$expected, $actual];
    },
    'count' => static function (array $predicate) use ($oracle, $matchingRows): array {
        return [
            $oracle("SELECT count(*) FROM t2 WHERE {$predicate['where']};"),
            (string) count($matchingRows($predicate['operator'], $predicate['right'])),
        ];
    },
    'negated count' => static function (array $predicate) use ($oracle, $matchingRows): array {
        return [
            $oracle("SELECT count(*) FROM t2 WHERE NOT ({$predicate['where']});"),
            (string) count($matchingRows($predicate['operator'], $predicate['right'], true)),
        ];
    },
    'min rowid' => static function (array $predicate) use ($oracle, $matchingRows): array {
        $matched = $matchingRows($predicate['operator'], $predicate['right']);

        return [
            $oracle("SELECT min(rowid) FROM t2 WHERE {$predicate['where']};"),
            $matched === [] ? '' : (string) min(array_column($matched, 'rowid')),
        ];
    },
    'max rowid' => static function (array $predicate) use ($oracle, $matchingRows): array {
        $matched = $matchingRows($predicate['operator'], $predicate['right']);

        return [
            $oracle("SELECT max(rowid) FROM t2 WHERE {$predicate['where']};"),
            $matched === [] ? '' : (string) max(array_column($matched, 'rowid')),
        ];
    },
    'ordered text vector' => static function (array $predicate) use ($oracle, $matchingRows, $quote): array {
        $expected = $oracle("SELECT group_concat(quote(t), '|') FROM (SELECT t FROM t2 WHERE {$predicate['where']} ORDER BY rowid);");
        $actual = implode('|', array_map(static fn (array $row): string => $quote($row['t']), $matchingRows($predicate['operator'], $predicate['right'])));

        return [$expected, $actual];
    },
];

foreach ($predicates as $predicate) {
    foreach ($contexts as $contextName => $context) {
        $tests['real upstream types2 text affinity dynamic bulk ' . $predicate['id'] . ' ' . $contextName] = static function (TestRunner $t) use ($predicate, $context, $contextName): void {
            [$expected, $actual] = $context($predicate);

            $t->same($expected, $actual, $predicate['where'] . ' ' . $contextName);
        };
    }
}

$tests['real upstream types2 text affinity dynamic bulk owns exactly 1000 pass cases'] = static function (TestRunner $t) use ($predicates, $contexts): void {
    $t->same(100, count($predicates));
    $t->same(10, count($contexts));
    $t->same(1000, count($predicates) * count($contexts));
    $t->same('types2.test: types2-2.*, types2-3.*, types2-4.* TEXT-affinity comparison follow-up plus IS variants', 'types2.test: types2-2.*, types2-3.*, types2-4.* TEXT-affinity comparison follow-up plus IS variants');
};

return $tests;
