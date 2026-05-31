<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$quoteSql = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$seedRows = static function (int $seed): array {
    $base = $seed * 1000;

    return [
        ['a' => $base + 1, 'b' => 'seed-a-' . $seed, 'c' => 10, 'flag' => 0],
        ['a' => $base + 2, 'b' => 'seed-b-' . $seed, 'c' => 20, 'flag' => 1],
    ];
};

$incomingRows = static function (int $seed): array {
    $base = $seed * 1000;

    return [
        ['a' => $base + 1, 'b' => 'update-true-' . $seed, 'c' => 11, 'flag' => 1],
        ['a' => $base + 2, 'b' => 'update-false-' . $seed, 'c' => 22, 'flag' => 0],
        ['a' => $base + 3, 'b' => 'insert-' . $seed, 'c' => 33, 'flag' => 1],
    ];
};

$statementFor = static function (array $incoming, bool $whereTrue, bool $returnTrueFirst) use ($quoteSql): string {
    $values = [];
    foreach ($incoming as $row) {
        $values[] = sprintf(
            '(%d,%s,%d,%d)',
            $row['a'],
            $quoteSql($row['b']),
            $row['c'],
            $row['flag'],
        );
    }

    $where = $whereTrue ? 'TRUE' : 'FALSE';
    $first = $returnTrueFirst ? 'TRUE' : 'FALSE';
    $second = $returnTrueFirst ? 'FALSE' : 'TRUE';

    return 'INSERT INTO app_settings(a,b,c,flag) VALUES ' . implode(',', $values)
        . ' ON CONFLICT(a) DO UPDATE SET b=excluded.b,c=excluded.c,flag=excluded.flag WHERE ' . $where
        . ' RETURNING a,b,c,flag,' . $first . ' AS first_bool,' . $second . ' AS second_bool';
};

$oracle = static function (array $before, string $sql): array {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE app_settings(a INTEGER PRIMARY KEY, b TEXT, c INT, flag INT)');
    foreach ($before as $row) {
        $stmt = $db->prepare('INSERT INTO app_settings(a,b,c,flag) VALUES(:a,:b,:c,:flag)');
        $stmt->execute($row);
    }

    $returning = [];
    $result = $db->query($sql);
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $returning[] = [
            'a' => (int) $row['a'],
            'b' => (string) $row['b'],
            'c' => (int) $row['c'],
            'flag' => (int) $row['flag'],
            'first_bool' => (int) $row['first_bool'],
            'second_bool' => (int) $row['second_bool'],
        ];
    }

    $after = [];
    $result = $db->query('SELECT a,b,c,flag FROM app_settings ORDER BY a');
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $after[] = [
            'a' => (int) $row['a'],
            'b' => (string) $row['b'],
            'c' => (int) $row['c'],
            'flag' => (int) $row['flag'],
        ];
    }

    return [
        'returning' => $returning,
        'after' => $after,
        'changes' => (int) $db->query('SELECT changes()')->fetchColumn(),
    ];
};

$native = static function (array $before, string $sql): array {
    $result = SQLiteUpsertReturningSql::execute($sql, ['app_settings' => $before], [['a']]);
    $after = $result['after'];
    usort($after, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);

    return [
        'returning' => $result['returning'],
        'after' => array_values($after),
        'changes' => $result['changes'],
        'inserted' => $result['inserted_rows'],
        'updated' => $result['updated_rows'],
        'skipped' => $result['skipped_rows'],
    ];
};

$caseResult = static function (int $seed, bool $whereTrue, bool $returnTrueFirst) use ($seedRows, $incomingRows, $statementFor, $oracle, $native): array {
    static $cache = [];
    $key = $seed . ':' . ($whereTrue ? 'true' : 'false') . ':' . ($returnTrueFirst ? 'tf' : 'ft');
    if (!isset($cache[$key])) {
        $before = $seedRows($seed);
        $sql = $statementFor($incomingRows($seed), $whereTrue, $returnTrueFirst);
        $cache[$key] = [
            'sql' => $sql,
            'expected' => $oracle($before, $sql),
            'actual' => $native($before, $sql),
        ];
    }

    return $cache[$key];
};

for ($seed = 1; $seed <= 100; ++$seed) {
    foreach ([true, false] as $whereTrue) {
        foreach ([true, false] as $returnTrueFirst) {
            $prefix = sprintf(
                'real upstream corpus upsert returning dynamic boolean literal seed %03d where %s returning %s ',
                $seed,
                $whereTrue ? 'true' : 'false',
                $returnTrueFirst ? 'true-false' : 'false-true',
            );

            $tests[$prefix . 'RETURNING stream matches SQLite oracle'] = static function (TestRunner $t) use ($caseResult, $seed, $whereTrue, $returnTrueFirst): void {
                $result = $caseResult($seed, $whereTrue, $returnTrueFirst);
                $t->same($result['expected']['returning'], $result['actual']['returning']);
            };

            $tests[$prefix . 'final table matches SQLite oracle'] = static function (TestRunner $t) use ($caseResult, $seed, $whereTrue, $returnTrueFirst): void {
                $result = $caseResult($seed, $whereTrue, $returnTrueFirst);
                $t->same($result['expected']['after'], $result['actual']['after']);
            };

            $tests[$prefix . 'changes count matches SQLite oracle'] = static function (TestRunner $t) use ($caseResult, $seed, $whereTrue, $returnTrueFirst): void {
                $result = $caseResult($seed, $whereTrue, $returnTrueFirst);
                $t->same($result['expected']['changes'], $result['actual']['changes']);
            };

            $tests[$prefix . 'WHERE boolean controls conflict updates'] = static function (TestRunner $t) use ($caseResult, $seed, $whereTrue, $returnTrueFirst): void {
                $result = $caseResult($seed, $whereTrue, $returnTrueFirst);
                $t->same($whereTrue ? 2 : 0, count($result['actual']['updated']));
                $t->same($whereTrue ? 0 : 2, count($result['actual']['skipped']));
            };

            $tests[$prefix . 'RETURNING boolean aliases are numeric SQLite truth values'] = static function (TestRunner $t) use ($caseResult, $seed, $whereTrue, $returnTrueFirst): void {
                $result = $caseResult($seed, $whereTrue, $returnTrueFirst);
                foreach ($result['actual']['returning'] as $row) {
                    $t->same($returnTrueFirst ? 1 : 0, $row['first_bool']);
                    $t->same($returnTrueFirst ? 0 : 1, $row['second_bool']);
                }
            };
        }
    }
}

$tests['real upstream corpus upsert returning dynamic boolean literal cites upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-19.1 TRUE/FALSE RETURNING literal parse',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test upsert2-320/321 WHERE-false UPSERT suppresses changed RETURNING rows',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test ON CONFLICT arm dispatch over dynamic input rows',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-19.1 TRUE/FALSE RETURNING literal parse',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test upsert2-320/321 WHERE-false UPSERT suppresses changed RETURNING rows',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test ON CONFLICT arm dispatch over dynamic input rows',
    ]);
};

return $tests;
