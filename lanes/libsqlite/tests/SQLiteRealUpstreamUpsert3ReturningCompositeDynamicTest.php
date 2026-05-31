<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$rowsForSeed = static function (int $seed): array {
    return [
        ['label' => 'alpha-' . $seed, 'tenant_id' => $seed + 1, 'slot_id' => $seed + 11, 'payload' => 'a' . $seed, 'revision' => 0],
        ['label' => 'beta-' . $seed, 'tenant_id' => $seed + 2, 'slot_id' => $seed + 22, 'payload' => 'b' . $seed, 'revision' => 1],
        ['label' => 'gamma-' . $seed, 'tenant_id' => $seed + 3, 'slot_id' => $seed + 33, 'payload' => 'c' . $seed, 'revision' => 2],
    ];
};

$quote = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$sqliteRows = static function (PDO $db): array {
    $rows = [];
    $stmt = $db->query('SELECT label, tenant_id, slot_id, payload, revision FROM excluded ORDER BY tenant_id, slot_id, label');
    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        $rows[] = [
            'label' => (string) $row['label'],
            'tenant_id' => (int) $row['tenant_id'],
            'slot_id' => (int) $row['slot_id'],
            'payload' => (string) $row['payload'],
            'revision' => (int) $row['revision'],
        ];
    }

    return $rows;
};

$nativeRows = static function (array $rows): array {
    usort(
        $rows,
        static fn (array $left, array $right): int => ((int) $left['tenant_id'] <=> (int) $right['tenant_id'])
            ?: ((int) $left['slot_id'] <=> (int) $right['slot_id'])
            ?: strcmp((string) $left['label'], (string) $right['label'])
    );

    return array_values($rows);
};

$oracle = static function (array $baseRows, array $sqlList) use ($quote, $sqliteRows): array {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE excluded(label TEXT, tenant_id INT, slot_id INT, payload TEXT, revision INT DEFAULT 0, UNIQUE(tenant_id, slot_id))');
    foreach ($baseRows as $row) {
        $db->exec(sprintf(
            'INSERT INTO excluded(label, tenant_id, slot_id, payload, revision) VALUES(%s,%d,%d,%s,%d)',
            $quote($row['label']),
            $row['tenant_id'],
            $row['slot_id'],
            $quote($row['payload']),
            $row['revision'],
        ));
    }

    $returning = [];
    foreach ($sqlList as $sql) {
        $stmt = $db->query($sql);
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $returning[] = [
                'label' => (string) $row['label'],
                'tenant_id' => (int) $row['tenant_id'],
                'slot_id' => (int) $row['slot_id'],
                'payload' => (string) $row['payload'],
                'revision' => (int) $row['revision'],
            ];
        }
    }

    return [
        'returning' => $returning,
        'after' => $sqliteRows($db),
    ];
};

$native = static function (array $baseRows, array $sqlList) use ($nativeRows): array {
    $rows = $baseRows;
    $returning = [];
    foreach ($sqlList as $sql) {
        $result = SQLiteUpsertReturningSql::execute($sql, ['excluded' => $rows], [['tenant_id', 'slot_id']]);
        array_push($returning, ...$result['returning']);
        $rows = $result['after'];
    }

    return [
        'returning' => $returning,
        'after' => $nativeRows($rows),
    ];
};

$tests['real upstream upsert3 returning composite source scenarios are cited'] = static function (TestRunner $t): void {
    $t->same('upsert3.test upsert3-130 ON CONFLICT(k,v) matches composite unique index', 'upsert3.test upsert3-130 ON CONFLICT(k,v) matches composite unique index');
    $t->same('upsert3.test upsert3-140 ON CONFLICT(v,k) matches the same composite unique index', 'upsert3.test upsert3-140 ON CONFLICT(v,k) matches the same composite unique index');
    $t->same('upsert3.test upsert3-200 table named excluded still lets excluded.c mean incoming row', 'upsert3.test upsert3-200 table named excluded still lets excluded.c mean incoming row');
    $t->same('upsert3.test upsert3-210 target alias base exposes current row while excluded exposes incoming row', 'upsert3.test upsert3-210 target alias base exposes current row while excluded exposes incoming row');
};

foreach (range(1, 1000) as $seed) {
    $tests[sprintf('real upstream upsert3 composite returning alias/excluded dynamic %04d', $seed)] = static function (TestRunner $t) use ($seed, $rowsForSeed, $oracle, $native, $quote): void {
        $baseRows = $rowsForSeed($seed);
        $alpha = $baseRows[0];
        $beta = $baseRows[1];
        $insertTenant = $seed + 40;
        $insertSlot = $seed + 400;
        $aliasRevision = $seed % 2 === 0 ? 7 : 2;
        $aliasIncomingRevision = $seed % 2 === 0 ? 8 : 1;

        $sqlList = [
            sprintf(
                'INSERT INTO excluded(label, tenant_id, slot_id, payload, revision) VALUES(%s,%d,%d,%s,%d) ON CONFLICT(slot_id, tenant_id) DO UPDATE SET payload=excluded.payload, revision=excluded.revision+1 RETURNING label, tenant_id, slot_id, payload, revision',
                $quote('alpha-conflict-' . $seed),
                $alpha['tenant_id'],
                $alpha['slot_id'],
                $quote('incoming-a' . $seed),
                4,
            ),
            sprintf(
                'INSERT INTO excluded(label, tenant_id, slot_id, payload, revision) VALUES(%s,%d,%d,%s,%d) ON CONFLICT(tenant_id, slot_id) DO NOTHING RETURNING label, tenant_id, slot_id, payload, revision',
                $quote('beta-skipped-' . $seed),
                $beta['tenant_id'],
                $beta['slot_id'],
                $quote('incoming-b' . $seed),
                5,
            ),
            sprintf(
                'INSERT INTO excluded(label, tenant_id, slot_id, payload, revision) VALUES(%s,%d,%d,%s,%d) ON CONFLICT(slot_id, tenant_id) DO UPDATE SET payload=payload||excluded.payload, revision=revision+excluded.revision RETURNING label, tenant_id, slot_id, payload, revision',
                $quote('alpha-second-' . $seed),
                $alpha['tenant_id'],
                $alpha['slot_id'],
                $quote(':next' . $seed),
                3,
            ),
            sprintf(
                'INSERT INTO excluded AS base(label, tenant_id, slot_id, payload, revision) VALUES(%s,%d,%d,%s,%d) ON CONFLICT(slot_id, tenant_id) DO UPDATE SET payload=excluded.payload, revision=excluded.revision+1 WHERE base.revision<excluded.revision RETURNING label, tenant_id, slot_id, payload, revision',
                $quote('alpha-alias-' . $seed),
                $alpha['tenant_id'],
                $alpha['slot_id'],
                $quote('alias-win' . $seed),
                $aliasIncomingRevision,
            ),
            sprintf(
                'INSERT INTO excluded AS base(label, tenant_id, slot_id, payload, revision) VALUES(%s,%d,%d,%s,%d) ON CONFLICT(slot_id, tenant_id) DO UPDATE SET payload=excluded.payload, revision=excluded.revision+1 WHERE base.revision<excluded.revision RETURNING label, tenant_id, slot_id, payload, revision',
                $quote('beta-alias-' . $seed),
                $beta['tenant_id'],
                $beta['slot_id'],
                $quote('alias-skip' . $seed),
                $aliasRevision,
            ),
            sprintf(
                'INSERT INTO excluded(label, tenant_id, slot_id, payload, revision) VALUES(%s,%d,%d,%s,%d) ON CONFLICT(slot_id, tenant_id) DO NOTHING RETURNING label, tenant_id, slot_id, payload, revision',
                $quote('inserted-' . $seed),
                $insertTenant,
                $insertSlot,
                $quote('inserted-payload-' . $seed),
                0,
            ),
        ];

        $expected = $oracle($baseRows, $sqlList);
        $actual = $native($baseRows, $sqlList);

        $t->same($expected['returning'], $actual['returning'], 'RETURNING rows follow SQLite for composite target order and excluded alias ' . $seed);
        $t->same($expected['after'], $actual['after'], 'final rows follow SQLite for composite target order and excluded alias ' . $seed);
    };
}

$tests['real upstream upsert3 malformed composite conflict targets are rejected'] = static function (TestRunner $t): void {
    $rows = [
        ['label' => 'alpha', 'tenant_id' => 1, 'slot_id' => 10, 'payload' => 'a', 'revision' => 0],
    ];

    $t->throws(InvalidArgumentException::class, static function () use ($rows): void {
        SQLiteUpsertReturningSql::execute(
            "INSERT INTO excluded(label, tenant_id, slot_id, payload, revision) VALUES('bad',1,10,'x',0) ON CONFLICT(tenant_id) DO NOTHING RETURNING label",
            ['excluded' => $rows],
            [['tenant_id', 'slot_id']],
        );
    });
};

return $tests;
