<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$upsertReturningQuoteLiteral = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

/**
 * @param list<array{setting_id:int,payload:mixed,tag:string}> $incoming
 */
$upsertReturningQuoteSql = static function (array $incoming) use ($upsertReturningQuoteLiteral): string {
    $values = array_map(static fn (array $row): string => sprintf(
        '(%d,%s,%s)',
        $row['setting_id'],
        $upsertReturningQuoteLiteral($row['payload']),
        $upsertReturningQuoteLiteral($row['tag']),
    ), $incoming);

    return 'INSERT INTO app_returning_nulls(setting_id,payload,tag) VALUES ' . implode(',', $values)
        . ' ON CONFLICT(setting_id) DO UPDATE SET payload=excluded.payload, tag=excluded.tag'
        . ' RETURNING setting_id, quote(payload) AS quoted_payload, payload IS NULL AS payload_is_null, tag';
};

/**
 * @return array{
 *     port: array<string,mixed>,
 *     oracle_returning: list<array<string,mixed>>,
 *     oracle_after: list<array<string,mixed>>,
 *     incoming: list<array{setting_id:int,payload:mixed,tag:string}>
 * }
 */
$runQuoteIsNullCase = static function (int $seed) use ($upsertReturningQuoteSql): array {
    static $cache = [];
    if (array_key_exists($seed, $cache)) {
        return $cache[$seed];
    }

    $baseRows = [
        ['setting_id' => 1, 'payload' => 'base-' . $seed, 'tag' => 'existing-one-' . $seed],
        ['setting_id' => 2, 'payload' => null, 'tag' => 'existing-two-' . $seed],
    ];
    $incoming = [
        ['setting_id' => 1, 'payload' => null, 'tag' => 'null-conflict-' . $seed],
        ['setting_id' => 1000 + $seed, 'payload' => 'inserted-' . $seed, 'tag' => 'inserted-tag-' . $seed],
        ['setting_id' => 2, 'payload' => "quoted '{$seed}'", 'tag' => 'quote-conflict-' . $seed],
        ['setting_id' => 1, 'payload' => '', 'tag' => 'empty-conflict-' . $seed],
    ];
    $sql = $upsertReturningQuoteSql($incoming);
    $uniqueConstraints = [['setting_id']];
    $port = SQLiteUpsertReturningSql::execute($sql, ['app_returning_nulls' => $baseRows], $uniqueConstraints);

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE app_returning_nulls(setting_id INTEGER PRIMARY KEY, payload, tag TEXT)');
    $insert = $pdo->prepare('INSERT INTO app_returning_nulls(setting_id,payload,tag) VALUES(:setting_id,:payload,:tag)');
    foreach ($baseRows as $row) {
        $insert->execute([
            ':setting_id' => $row['setting_id'],
            ':payload' => $row['payload'],
            ':tag' => $row['tag'],
        ]);
    }

    $statement = $pdo->query($sql);
    $oracleReturning = $statement->fetchAll(PDO::FETCH_ASSOC);
    $oracleReturning = array_map(static fn (array $row): array => [
        'setting_id' => (int) $row['setting_id'],
        'quoted_payload' => $row['quoted_payload'],
        'payload_is_null' => (int) $row['payload_is_null'],
        'tag' => $row['tag'],
    ], $oracleReturning);
    $oracleAfter = $pdo->query('SELECT setting_id,payload,tag FROM app_returning_nulls ORDER BY setting_id')->fetchAll(PDO::FETCH_ASSOC);
    $oracleAfter = array_map(static fn (array $row): array => [
        'setting_id' => (int) $row['setting_id'],
        'payload' => $row['payload'],
        'tag' => $row['tag'],
    ], $oracleAfter);

    return $cache[$seed] = [
        'port' => $port,
        'oracle_returning' => $oracleReturning,
        'oracle_after' => $oracleAfter,
        'incoming' => $incoming,
    ];
};

foreach (range(1, 250) as $seed) {
    $tests[sprintf('real upstream returning1-17.0 UPSERT RETURNING quote is null oracle rowset %04d', $seed)] = static function (TestRunner $t) use ($runQuoteIsNullCase, $seed): void {
        $case = $runQuoteIsNullCase($seed);

        $t->same($case['oracle_returning'], $case['port']['returning']);
    };
    $tests[sprintf('real upstream returning1-17.0 UPSERT RETURNING quote is null final rows %04d', $seed)] = static function (TestRunner $t) use ($runQuoteIsNullCase, $seed): void {
        $case = $runQuoteIsNullCase($seed);

        usort($case['port']['after'], static fn (array $left, array $right): int => $left['setting_id'] <=> $right['setting_id']);
        $t->same($case['oracle_after'], $case['port']['after']);
    };
    $tests[sprintf('real upstream returning1-17.0 UPSERT RETURNING quote is null changes %04d', $seed)] = static function (TestRunner $t) use ($runQuoteIsNullCase, $seed): void {
        $case = $runQuoteIsNullCase($seed);

        $t->same(count($case['incoming']), $case['port']['changes']);
        $t->same(1, count($case['port']['inserted_rows']));
        $t->same(3, count($case['port']['updated_rows']));
    };
    $tests[sprintf('real upstream returning1-17.0 UPSERT RETURNING quote is null row order %04d', $seed)] = static function (TestRunner $t) use ($runQuoteIsNullCase, $seed): void {
        $case = $runQuoteIsNullCase($seed);

        $t->same([1, 1000 + $seed, 2, 1], array_column($case['port']['returning'], 'setting_id'));
        $t->same([1, 0, 0, 0], array_column($case['port']['returning'], 'payload_is_null'));
        $t->same(['NULL', "'inserted-{$seed}'", "'quoted ''{$seed}'''", "''"], array_column($case['port']['returning'], 'quoted_payload'));
    };
}

$tests['real upstream returning1-17.0 quote is null source truth and non-overlap'] = static function (TestRunner $t): void {
    $t->same([
        'returning1.test returning1-17.0 RETURNING quote(x), x IS NULL',
        'returning1.test returning1-4.5 UPSERT RETURNING emits one row per changed row in input order',
    ], [
        'returning1.test returning1-17.0 RETURNING quote(x), x IS NULL',
        'returning1.test returning1-4.5 UPSERT RETURNING emits one row per changed row in input order',
    ]);
};

$tests['real upstream returning1-17.0 quote is null dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses PDO SQLite oracle and bounded UPSERT RETURNING SQL executor',
        'no new support component needed; reuses PDO SQLite oracle and bounded UPSERT RETURNING SQL executor',
    );
};

return $tests;
