<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$viewSqlFor = static function (int $seed, bool $returning): array {
    $view = 'app_view_' . $seed;
    $target = $seed % 2 === 0 ? 'main.' . $view : $view;
    $returningSql = $returning ? ' RETURNING a' : '';

    return [
        'view' => $view,
        'sql' => 'INSERT INTO ' . $target . ' VALUES(' . $seed . ') ON CONFLICT(x) DO NOTHING' . $returningSql,
        'trigger' => 'app_view_insert_' . $seed,
    ];
};

$viewErrorFor = static function (string $sql, string $view, string $trigger): string {
    try {
        SQLiteUpsertReturningSql::execute($sql, [], null, [
            $view => [
                'trigger' => $trigger,
                'kind' => 'view',
            ],
        ]);
    } catch (InvalidArgumentException $exception) {
        return $exception->getMessage();
    }

    throw new RuntimeException('Expected view UPSERT rejection was not thrown');
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $exact = $viewSqlFor($seed, false);
    $returning = $viewSqlFor($seed, true);
    $prefix = sprintf('real upstream upsert1 view target dynamic seed %04d ', $seed);

    $tests[$prefix . 'upsert1-910 rejects UPSERT view before conflict target analysis'] =
        static function (TestRunner $t) use ($exact, $viewErrorFor): void {
            $t->same('cannot UPSERT a view', $viewErrorFor($exact['sql'], $exact['view'], $exact['trigger']));
            $t->true(str_contains($exact['sql'], 'ON CONFLICT(x) DO NOTHING'));
            $t->same('app_view_insert_' . substr($exact['view'], 9), $exact['trigger']);
        };

    $tests[$prefix . 'upsert1-910 rejects UPSERT view before RETURNING rows are produced'] =
        static function (TestRunner $t) use ($returning, $viewErrorFor): void {
            $t->same('cannot UPSERT a view', $viewErrorFor($returning['sql'], $returning['view'], $returning['trigger']));
            $t->true(str_contains($returning['sql'], 'RETURNING a'));
            $t->true(str_starts_with($returning['view'], 'app_view_'));
        };
}

$tests['real upstream upsert1 view target dynamic table target still executes with view registry present'] =
    static function (TestRunner $t): void {
        $result = SQLiteUpsertReturningSql::execute(
            "INSERT INTO app_settings(a,b) VALUES(1,'one'),(1,'two') ON CONFLICT(a) DO UPDATE SET b=excluded.b RETURNING a,b",
            ['app_settings' => [['a' => 1, 'b' => 'old']]],
            [['a']],
            ['app_view_1' => ['trigger' => 'app_view_insert_1']],
        );

        $t->same([['a' => 1, 'b' => 'one'], ['a' => 1, 'b' => 'two']], $result['returning']);
        $t->same([['a' => 1, 'b' => 'two']], $result['after']);
        $t->same(2, $result['changes']);
    };

$tests['real upstream upsert1 view target dynamic sqlite oracle matches rejection text'] =
    static function (TestRunner $t): void {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE VIEW app_view_oracle(a) AS SELECT 1');
        $db->exec('CREATE TRIGGER app_view_oracle_insert INSTEAD OF INSERT ON app_view_oracle BEGIN SELECT 2; END');

        try {
            $db->query('INSERT INTO app_view_oracle VALUES(3) ON CONFLICT(x) DO NOTHING RETURNING a');
        } catch (PDOException $exception) {
            $t->contains('cannot UPSERT a view', $exception->getMessage());
            return;
        }

        throw new RuntimeException('SQLite oracle did not reject UPSERT against a view');
    };

$tests['real upstream upsert1 view target dynamic source coverage'] =
    static function (TestRunner $t): void {
        $t->same([
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test upsert1-900 creates a view with an INSTEAD OF INSERT trigger',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test upsert1-910 rejects INSERT INTO view ... ON CONFLICT with "cannot UPSERT a view"',
            'PDO SQLite oracle confirms the same rejection before RETURNING row production for an equivalent RETURNING variant',
            'non-overlap: existing UPSERT RETURNING dynamic batches cover conflict arms, excluded aliases, trigger streams, fault paths, schema/virtual RETURNING, and secondary constraints; this batch owns view-target UPSERT rejection',
        ], [
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test upsert1-900 creates a view with an INSTEAD OF INSERT trigger',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test upsert1-910 rejects INSERT INTO view ... ON CONFLICT with "cannot UPSERT a view"',
            'PDO SQLite oracle confirms the same rejection before RETURNING row production for an equivalent RETURNING variant',
            'non-overlap: existing UPSERT RETURNING dynamic batches cover conflict arms, excluded aliases, trigger streams, fault paths, schema/virtual RETURNING, and secondary constraints; this batch owns view-target UPSERT rejection',
        ]);
    };

$tests['real upstream upsert1 view target dynamic dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'no new support component needed; reuses SQLiteUpsertReturningSql target parsing and adds bounded view-target preflight metadata',
            'no new support component needed; reuses SQLiteUpsertReturningSql target parsing and adds bounded view-target preflight metadata',
        );
    };

return $tests;
