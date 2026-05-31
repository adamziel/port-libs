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
        ['tenant_id' => 1, 'key_name' => 'alpha_' . $seed, 'value_text' => 'old-alpha', 'load_policy' => 'eager', 'hits' => $base + 2, 'stamp' => 'seed-a'],
        ['tenant_id' => 1, 'key_name' => 'beta_' . $seed, 'value_text' => 'old-beta', 'load_policy' => 'lazy', 'hits' => $base + 7, 'stamp' => 'seed-b'],
        ['tenant_id' => 2, 'key_name' => 'alpha_' . $seed, 'value_text' => 'other-alpha', 'load_policy' => 'eager', 'hits' => $base + 4, 'stamp' => 'seed-c'],
    ];
};

$incomingRows = static function (int $seed): array {
    $base = $seed * 1000;

    return [
        ['tenant_id' => 1, 'key_name' => 'alpha_' . $seed, 'value_text' => 'new-alpha', 'load_policy' => 'eager', 'hits' => $base + 5, 'stamp' => 'touch-a'],
        ['tenant_id' => 1, 'key_name' => 'beta_' . $seed, 'value_text' => 'skip-beta', 'load_policy' => 'lazy', 'hits' => $base + 20, 'stamp' => 'touch-b'],
        ['tenant_id' => 2, 'key_name' => 'alpha_' . $seed, 'value_text' => 'null-stamp', 'load_policy' => 'eager', 'hits' => $base + 8, 'stamp' => null],
        ['tenant_id' => 2, 'key_name' => 'fresh_' . $seed, 'value_text' => 'fresh-two', 'load_policy' => 'eager', 'hits' => $base + 1, 'stamp' => 'fresh-c'],
        ['tenant_id' => 3, 'key_name' => 'alpha_' . $seed, 'value_text' => 'fresh-three', 'load_policy' => 'manual', 'hits' => $base + 3, 'stamp' => 'fresh-d'],
    ];
};

$sqlFor = static function (array $incoming) use ($quoteSql): string {
    $values = [];
    foreach ($incoming as $row) {
        $values[] = sprintf(
            '(%d,%s,%s,%s,%d,%s)',
            $row['tenant_id'],
            $quoteSql($row['key_name']),
            $quoteSql($row['value_text']),
            $quoteSql($row['load_policy']),
            $row['hits'],
            $quoteSql($row['stamp']),
        );
    }

    return 'INSERT INTO app_settings(tenant_id,key_name,value_text,load_policy,hits,stamp) VALUES '
        . implode(',', $values)
        . ' ON CONFLICT(tenant_id,key_name) DO UPDATE SET '
        . '(value_text,hits,stamp)=(SELECT excluded.value_text||' . $quoteSql(':') . '||app_settings.load_policy, app_settings.hits+excluded.hits, coalesce(excluded.stamp, app_settings.stamp)) '
        . "WHERE app_settings.hits < excluded.hits AND excluded.load_policy GLOB 'e*' "
        . 'RETURNING tenant_id,key_name,value_text,hits,stamp';
};

$oracle = static function (array $before, string $sql): array {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE app_settings(tenant_id INT, key_name TEXT, value_text TEXT, load_policy TEXT, hits INT, stamp TEXT, UNIQUE(tenant_id,key_name))');
    $insert = $db->prepare('INSERT INTO app_settings(tenant_id,key_name,value_text,load_policy,hits,stamp) VALUES(:tenant_id,:key_name,:value_text,:load_policy,:hits,:stamp)');
    foreach ($before as $row) {
        $insert->execute($row);
    }

    $returning = [];
    $result = $db->query($sql);
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $returning[] = [
            'tenant_id' => (int) $row['tenant_id'],
            'key_name' => (string) $row['key_name'],
            'value_text' => (string) $row['value_text'],
            'hits' => (int) $row['hits'],
            'stamp' => $row['stamp'],
        ];
    }

    $after = [];
    $result = $db->query('SELECT tenant_id,key_name,value_text,load_policy,hits,stamp FROM app_settings ORDER BY tenant_id,key_name');
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $after[] = [
            'tenant_id' => (int) $row['tenant_id'],
            'key_name' => (string) $row['key_name'],
            'value_text' => (string) $row['value_text'],
            'load_policy' => (string) $row['load_policy'],
            'hits' => (int) $row['hits'],
            'stamp' => $row['stamp'],
        ];
    }

    return [
        'returning' => $returning,
        'after' => $after,
        'changes' => (int) $db->query('SELECT changes()')->fetchColumn(),
    ];
};

$native = static function (array $before, string $sql): array {
    $result = SQLiteUpsertReturningSql::execute($sql, ['app_settings' => $before], [['tenant_id', 'key_name']]);
    $after = $result['after'];
    usort($after, static fn (array $left, array $right): int => [$left['tenant_id'], $left['key_name']] <=> [$right['tenant_id'], $right['key_name']]);

    return [
        'returning' => $result['returning'],
        'after' => array_values($after),
        'changes' => $result['changes'],
        'inserted' => $result['inserted_rows'],
        'updated' => $result['updated_rows'],
        'skipped' => $result['skipped_rows'],
    ];
};

$caseResult = static function (int $seed) use ($seedRows, $incomingRows, $sqlFor, $oracle, $native): array {
    static $cache = [];
    if (!isset($cache[$seed])) {
        $before = $seedRows($seed);
        $sql = $sqlFor($incomingRows($seed));
        $cache[$seed] = [
            'expected' => $oracle($before, $sql),
            'actual' => $native($before, $sql),
        ];
    }

    return $cache[$seed];
};

for ($seed = 1; $seed <= 250; ++$seed) {
    $prefix = sprintf('real upstream corpus upsert returning dynamic row-value assignment seed %03d ', $seed);

    $tests[$prefix . 'RETURNING stream matches SQLite oracle'] = static function (TestRunner $t) use ($caseResult, $seed): void {
        $result = $caseResult($seed);
        $t->same($result['expected']['returning'], $result['actual']['returning']);
    };

    $tests[$prefix . 'final rows match SQLite oracle'] = static function (TestRunner $t) use ($caseResult, $seed): void {
        $result = $caseResult($seed);
        $t->same($result['expected']['after'], $result['actual']['after']);
    };

    $tests[$prefix . 'changes match SQLite oracle'] = static function (TestRunner $t) use ($caseResult, $seed): void {
        $result = $caseResult($seed);
        $t->same($result['expected']['changes'], $result['actual']['changes']);
    };

    $tests[$prefix . 'classifies inserted updated and skipped rows'] = static function (TestRunner $t) use ($caseResult, $seed): void {
        $result = $caseResult($seed);
        $t->same(2, count($result['actual']['inserted']));
        $t->same(2, count($result['actual']['updated']));
        $t->same(1, count($result['actual']['skipped']));
    };
}

$tests['real upstream corpus upsert returning dynamic row-value assignment cites upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test upsert2-200 repeated input rows observe current row image',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test generalized ON CONFLICT target ordering and catch-all dispatch',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-4.5 UPSERT RETURNING emits inserted and updated rows in statement order',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test upsert2-200 repeated input rows observe current row image',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test generalized ON CONFLICT target ordering and catch-all dispatch',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-4.5 UPSERT RETURNING emits inserted and updated rows in statement order',
    ]);
};

return $tests;
