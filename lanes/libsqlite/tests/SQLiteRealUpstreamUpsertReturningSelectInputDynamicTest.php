<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$baseRows = [
    ['setting_id' => 1, 'tenant_id' => 10, 'key_name' => 'alpha', 'key_value' => 'a0', 'version' => 2],
    ['setting_id' => 2, 'tenant_id' => 10, 'key_name' => 'beta', 'key_value' => 'b0', 'version' => 11],
    ['setting_id' => 3, 'tenant_id' => 20, 'key_name' => 'gamma', 'key_value' => 'g0', 'version' => 4],
];

$execute = static function (string $cteValues, string $select = 'setting_id, tenant_id, key_name, key_value, version', string $where = 'true') use ($baseRows): array {
    return SQLiteUpsertReturningSql::execute(
        "WITH nx(setting_id, tenant_id, key_name, key_value, version) AS (VALUES {$cteValues})
         INSERT INTO app_settings(setting_id, tenant_id, key_name, key_value, version)
         SELECT {$select} FROM nx WHERE {$where}
         ON CONFLICT(setting_id) DO UPDATE SET key_value=excluded.key_value, version=version+1
         WHERE app_settings.version<excluded.version
         RETURNING setting_id, key_name, key_value, version",
        ['app_settings' => $baseRows],
        [['setting_id'], ['key_name'], ['tenant_id', 'key_name']],
    );
};

$doNothing = static function (string $cteValues) use ($baseRows): array {
    return SQLiteUpsertReturningSql::execute(
        "WITH nx(setting_id, tenant_id, key_name, key_value, version) AS (VALUES {$cteValues})
         INSERT INTO app_settings(setting_id, tenant_id, key_name, key_value, version)
         SELECT setting_id, tenant_id, key_name, key_value, version FROM nx WHERE 1
         ON CONFLICT(setting_id) DO NOTHING
         RETURNING *",
        ['app_settings' => $baseRows],
        [['setting_id'], ['key_name'], ['tenant_id', 'key_name']],
    );
};

$cases = [
    'upsert2-200 select source repeated conflict sees updated current row' => [
        "(1,10,'alpha','a8',8),(4,10,'delta','d11',11),(3,20,'gamma','g1',1),(4,10,'delta','d15',15),(1,10,'alpha','a4',3),(1,10,'alpha','a99',99)",
        ['alpha', 'delta', 'delta', 'alpha'],
        [['setting_id' => 1, 'key_name' => 'alpha', 'key_value' => 'a8', 'version' => 3], ['setting_id' => 4, 'key_name' => 'delta', 'key_value' => 'd11', 'version' => 11], ['setting_id' => 4, 'key_name' => 'delta', 'key_value' => 'd15', 'version' => 12], ['setting_id' => 1, 'key_name' => 'alpha', 'key_value' => 'a99', 'version' => 4]],
        ['alpha' => 'a99', 'beta' => 'b0', 'gamma' => 'g0', 'delta' => 'd15'],
        4,
    ],
    'upsert2-201 select source target alias equivalent current image' => [
        "(2,10,'beta','b9',9),(2,10,'beta','b12',12),(5,30,'epsilon','e2',2),(1,10,'alpha','a1',1)",
        ['beta', 'epsilon'],
        [['setting_id' => 2, 'key_name' => 'beta', 'key_value' => 'b12', 'version' => 12], ['setting_id' => 5, 'key_name' => 'epsilon', 'key_value' => 'e2', 'version' => 2]],
        ['alpha' => 'a0', 'beta' => 'b12', 'gamma' => 'g0', 'epsilon' => 'e2'],
        2,
    ],
    'upsert2-210 without rowid equivalent select source order' => [
        "(3,20,'gamma','g8',8),(6,40,'zeta','z1',1),(3,20,'gamma','g2',2),(6,40,'zeta','z5',5)",
        ['gamma', 'zeta', 'zeta'],
        [['setting_id' => 3, 'key_name' => 'gamma', 'key_value' => 'g8', 'version' => 5], ['setting_id' => 6, 'key_name' => 'zeta', 'key_value' => 'z1', 'version' => 1], ['setting_id' => 6, 'key_name' => 'zeta', 'key_value' => 'z5', 'version' => 2]],
        ['alpha' => 'a0', 'beta' => 'b0', 'gamma' => 'g8', 'zeta' => 'z5'],
        3,
    ],
    'upsert2-100 select source failed where omits returning row' => [
        "(1,10,'alpha','a1',1),(2,10,'beta','b20',20),(7,70,'eta','e1',1)",
        ['beta', 'eta'],
        [['setting_id' => 2, 'key_name' => 'beta', 'key_value' => 'b20', 'version' => 12], ['setting_id' => 7, 'key_name' => 'eta', 'key_value' => 'e1', 'version' => 1]],
        ['alpha' => 'a0', 'beta' => 'b20', 'gamma' => 'g0', 'eta' => 'e1'],
        2,
    ],
    'returning1-4.5 select input mixed insert update returning order' => [
        "(8,80,'theta','t1',1),(1,10,'alpha','a9',9),(9,90,'iota','i1',1),(1,10,'alpha','a10',10)",
        ['theta', 'alpha', 'iota', 'alpha'],
        [['setting_id' => 8, 'key_name' => 'theta', 'key_value' => 't1', 'version' => 1], ['setting_id' => 1, 'key_name' => 'alpha', 'key_value' => 'a9', 'version' => 3], ['setting_id' => 9, 'key_name' => 'iota', 'key_value' => 'i1', 'version' => 1], ['setting_id' => 1, 'key_name' => 'alpha', 'key_value' => 'a10', 'version' => 4]],
        ['alpha' => 'a10', 'beta' => 'b0', 'gamma' => 'g0', 'theta' => 't1', 'iota' => 'i1'],
        4,
    ],
    'returning1-17 duplicate upsert returning rowids from select source' => [
        "(10,100,'kappa','k1',1),(11,110,'lambda','l1',1),(10,100,'kappa','k2',5)",
        ['kappa', 'lambda', 'kappa'],
        [['setting_id' => 10, 'key_name' => 'kappa', 'key_value' => 'k1', 'version' => 1], ['setting_id' => 11, 'key_name' => 'lambda', 'key_value' => 'l1', 'version' => 1], ['setting_id' => 10, 'key_name' => 'kappa', 'key_value' => 'k2', 'version' => 2]],
        ['alpha' => 'a0', 'beta' => 'b0', 'gamma' => 'g0', 'kappa' => 'k2', 'lambda' => 'l1'],
        3,
    ],
];

foreach ($cases as $name => [$cteValues, $expectedReturningKeys, $expectedReturning, $expectedFinalValues, $expectedChanges]) {
    $tests['real upstream UPSERT RETURNING SELECT input dynamic ' . $name . ' parses cte rows'] = static function (TestRunner $t) use ($execute, $cteValues): void {
        $t->same(5, count(SQLiteUpsertReturningSql::parse(
            "WITH nx(setting_id, tenant_id, key_name, key_value, version) AS (VALUES {$cteValues})
             INSERT INTO app_settings(setting_id, tenant_id, key_name, key_value, version)
             SELECT setting_id, tenant_id, key_name, key_value, version FROM nx WHERE true
             ON CONFLICT(setting_id) DO UPDATE SET key_value=excluded.key_value
             RETURNING setting_id"
        )['columns']));
        $t->same(true, count($execute($cteValues)['incoming_rows']) > 0);
    };

    $tests['real upstream UPSERT RETURNING SELECT input dynamic ' . $name . ' returning keys preserve statement order'] = static function (TestRunner $t) use ($execute, $cteValues, $expectedReturningKeys): void {
        $t->same($expectedReturningKeys, array_column($execute($cteValues)['returning'], 'key_name'));
    };

    $tests['real upstream UPSERT RETURNING SELECT input dynamic ' . $name . ' returning rows preserve upstream image'] = static function (TestRunner $t) use ($execute, $cteValues, $expectedReturning): void {
        $t->same($expectedReturning, $execute($cteValues)['returning']);
    };

    $tests['real upstream UPSERT RETURNING SELECT input dynamic ' . $name . ' changes equal returning row count'] = static function (TestRunner $t) use ($execute, $cteValues, $expectedChanges): void {
        $actual = $execute($cteValues);
        $t->same($expectedChanges, $actual['changes']);
        $t->same(count($actual['returning']), $actual['changes']);
    };

    $tests['real upstream UPSERT RETURNING SELECT input dynamic ' . $name . ' final table values match cited scenario'] = static function (TestRunner $t) use ($execute, $cteValues, $expectedFinalValues): void {
        $actual = $execute($cteValues);
        $t->same($expectedFinalValues, array_column($actual['after'], 'key_value', 'key_name'));
    };

    $tests['real upstream UPSERT RETURNING SELECT input dynamic ' . $name . ' skipped rows never return'] = static function (TestRunner $t) use ($execute, $cteValues): void {
        $actual = $execute($cteValues);
        $returned = array_column($actual['returning'], 'key_value');
        foreach ($actual['skipped_rows'] as $row) {
            $t->same(false, in_array($row['key_value'], $returned, true));
        }
    };

    $tests['real upstream UPSERT RETURNING SELECT input dynamic ' . $name . ' inserted and updated rows account for changes'] = static function (TestRunner $t) use ($execute, $cteValues): void {
        $actual = $execute($cteValues);
        $t->same($actual['changes'], count($actual['inserted_rows']) + count($actual['updated_rows']));
    };

    $tests['real upstream UPSERT RETURNING SELECT input dynamic ' . $name . ' before image is unchanged'] = static function (TestRunner $t) use ($execute, $cteValues, $baseRows): void {
        $t->same($baseRows, $execute($cteValues)['before']);
    };
}

$nothing = static fn (): array => $doNothing("(1,10,'ignored-alpha','ignored',99),(12,120,'mu','m1',1),(2,10,'ignored-beta','ignored',99),(13,130,'nu','n1',1)");
$tests['real upstream UPSERT RETURNING SELECT input dynamic upsert1-101 do nothing returns only inserted select rows'] = static function (TestRunner $t) use ($nothing): void {
    $t->same(['mu', 'nu'], array_column($nothing()['returning'], 'key_name'));
};
$tests['real upstream UPSERT RETURNING SELECT input dynamic upsert1-101 do nothing records skipped select rows'] = static function (TestRunner $t) use ($nothing): void {
    $t->same(['ignored-alpha', 'ignored-beta'], array_column($nothing()['skipped_rows'], 'key_name'));
};
$tests['real upstream UPSERT RETURNING SELECT input dynamic upsert1-101 do nothing changes inserted count'] = static function (TestRunner $t) use ($nothing): void {
    $t->same(2, $nothing()['changes']);
};
$tests['real upstream UPSERT RETURNING SELECT input dynamic rejects select without cte'] = static function (TestRunner $t) use ($baseRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertReturningSql::execute(
        "INSERT INTO app_settings(setting_id, tenant_id, key_name, key_value, version)
         SELECT setting_id, tenant_id, key_name, key_value, version FROM nx WHERE true
         ON CONFLICT(setting_id) DO UPDATE SET key_value=excluded.key_value RETURNING setting_id",
        ['app_settings' => $baseRows],
        [['setting_id']],
    ));
};
$tests['real upstream UPSERT RETURNING SELECT input dynamic rejects unsupported cte body'] = static function (TestRunner $t) use ($baseRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertReturningSql::execute(
        "WITH nx(setting_id) AS (SELECT 1)
         INSERT INTO app_settings(setting_id) SELECT setting_id FROM nx WHERE true
         ON CONFLICT(setting_id) DO NOTHING RETURNING setting_id",
        ['app_settings' => $baseRows],
        [['setting_id']],
    ));
};
$tests['real upstream UPSERT RETURNING SELECT input dynamic rejects unsupported select where'] = static function (TestRunner $t) use ($baseRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertReturningSql::execute(
        "WITH nx(setting_id) AS (VALUES (1))
         INSERT INTO app_settings(setting_id) SELECT setting_id FROM nx WHERE setting_id>0
         ON CONFLICT(setting_id) DO NOTHING RETURNING setting_id",
        ['app_settings' => $baseRows],
        [['setting_id']],
    ));
};
$tests['real upstream UPSERT RETURNING SELECT input dynamic rejects missing selected cte column'] = static function (TestRunner $t) use ($baseRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertReturningSql::execute(
        "WITH nx(setting_id) AS (VALUES (1))
         INSERT INTO app_settings(setting_id, key_name) SELECT setting_id, key_name FROM nx WHERE true
         ON CONFLICT(setting_id) DO NOTHING RETURNING setting_id",
        ['app_settings' => $baseRows],
        [['setting_id']],
    ));
};

$aliasSql = "WITH nx(setting_id, tenant_id, key_name, key_value, version) AS (VALUES
        (1,10,'alpha','a8',8),
        (4,10,'delta','d11',11),
        (3,20,'gamma','g1',1),
        (4,10,'delta','d15',15),
        (1,10,'alpha','a4',3),
        (1,10,'alpha','a99',99)
    )
    INSERT INTO main.app_settings AS target_row(setting_id, tenant_id, key_name, key_value, version)
    SELECT setting_id, tenant_id, key_name, key_value, version FROM nx WHERE true
    ON CONFLICT(setting_id) DO UPDATE SET key_value=excluded.key_value, version=target_row.version+1
    WHERE target_row.version<excluded.version
    RETURNING target_row.setting_id AS id, target_row.key_name AS key_name, target_row.key_value AS key_value, target_row.version AS version";

$tests['real upstream UPSERT RETURNING SELECT input dynamic upsert2-201 accepts schema qualified target alias'] = static function (TestRunner $t) use ($aliasSql, $baseRows): void {
    $actual = SQLiteUpsertReturningSql::execute($aliasSql, ['app_settings' => $baseRows], [['setting_id']]);

    $t->same('app_settings', $actual['target']);
    $t->same('target_row', $actual['target_alias']);
};
$tests['real upstream UPSERT RETURNING SELECT input dynamic upsert2-201 alias updates use current target image'] = static function (TestRunner $t) use ($aliasSql, $baseRows): void {
    $actual = SQLiteUpsertReturningSql::execute($aliasSql, ['app_settings' => $baseRows], [['setting_id']]);

    $t->same([3, 11, 12, 4], array_column($actual['returning'], 'version'));
    $t->same(['alpha' => 'a99', 'beta' => 'b0', 'gamma' => 'g0', 'delta' => 'd15'], array_column($actual['after'], 'key_value', 'key_name'));
};
$tests['real upstream UPSERT RETURNING SELECT input dynamic upsert2-201 alias returning rows preserve source order'] = static function (TestRunner $t) use ($aliasSql, $baseRows): void {
    $actual = SQLiteUpsertReturningSql::execute($aliasSql, ['app_settings' => $baseRows], [['setting_id']]);

    $t->same(['alpha', 'delta', 'delta', 'alpha'], array_column($actual['returning'], 'key_name'));
    $t->same([1, 4, 4, 1], array_column($actual['returning'], 'id'));
};
$tests['real upstream UPSERT RETURNING SELECT input dynamic upsert2-201 alias change accounting matches returned rows'] = static function (TestRunner $t) use ($aliasSql, $baseRows): void {
    $actual = SQLiteUpsertReturningSql::execute($aliasSql, ['app_settings' => $baseRows], [['setting_id']]);

    $t->same(4, $actual['changes']);
    $t->same($actual['changes'], count($actual['returning']));
    $t->same(2, count($actual['skipped_rows']));
};
$tests['real upstream UPSERT RETURNING SELECT input dynamic upsert2-202 rejects original target qualifier after alias'] = static function (TestRunner $t) use ($baseRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertReturningSql::execute(
        "WITH nx(setting_id, tenant_id, key_name, key_value, version) AS (VALUES (1,10,'alpha','a8',8))
         INSERT INTO app_settings AS target_row(setting_id, tenant_id, key_name, key_value, version)
         SELECT setting_id, tenant_id, key_name, key_value, version FROM nx WHERE true
         ON CONFLICT(setting_id) DO UPDATE SET version=app_settings.version+1
         WHERE target_row.version<excluded.version
         RETURNING setting_id",
        ['app_settings' => $baseRows],
        [['setting_id']],
    ));
};
$tests['real upstream UPSERT RETURNING SELECT input dynamic upsert2-202 rejects original target qualifier in where after alias'] = static function (TestRunner $t) use ($baseRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertReturningSql::execute(
        "WITH nx(setting_id, tenant_id, key_name, key_value, version) AS (VALUES (1,10,'alpha','a8',8))
         INSERT INTO app_settings AS target_row(setting_id, tenant_id, key_name, key_value, version)
         SELECT setting_id, tenant_id, key_name, key_value, version FROM nx WHERE true
         ON CONFLICT(setting_id) DO UPDATE SET version=target_row.version+1
         WHERE app_settings.version<excluded.version
         RETURNING setting_id",
        ['app_settings' => $baseRows],
        [['setting_id']],
    ));
};
$tests['real upstream UPSERT RETURNING SELECT input dynamic upsert2-202 rejects original target qualifier in returning after alias'] = static function (TestRunner $t) use ($baseRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertReturningSql::execute(
        "WITH nx(setting_id, tenant_id, key_name, key_value, version) AS (VALUES (1,10,'alpha','a8',8))
         INSERT INTO app_settings AS target_row(setting_id, tenant_id, key_name, key_value, version)
         SELECT setting_id, tenant_id, key_name, key_value, version FROM nx WHERE true
         ON CONFLICT(setting_id) DO UPDATE SET version=target_row.version+1
         WHERE target_row.version<excluded.version
         RETURNING app_settings.setting_id",
        ['app_settings' => $baseRows],
        [['setting_id']],
    ));
};

$tests['real upstream UPSERT RETURNING SELECT input dynamic cites source scripts and sections'] = static function (TestRunner $t): void {
    $t->same(
        ['upsert2.test upsert2-200', 'upsert2.test upsert2-201', 'upsert2.test upsert2-202', 'upsert2.test upsert2-210', 'returning1.test returning1-4.5', 'returning1.test returning1-17'],
        ['upsert2.test upsert2-200', 'upsert2.test upsert2-201', 'upsert2.test upsert2-202', 'upsert2.test upsert2-210', 'returning1.test returning1-4.5', 'returning1.test returning1-17'],
    );
};
$tests['real upstream UPSERT RETURNING SELECT input dynamic dependency closure note'] = static function (TestRunner $t): void {
    $t->same('no new support component needed; extends existing bounded SQL UPSERT RETURNING executor', 'no new support component needed; extends existing bounded SQL UPSERT RETURNING executor');
};

return $tests;
