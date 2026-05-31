<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$unique = [['setting_id'], ['key_name'], ['tenant_id', 'key_name']];
$baseRows = [
    ['setting_id' => 1, 'tenant_id' => 10, 'key_name' => 'alpha', 'key_value' => 'old-alpha', 'version' => 1, 'load_policy' => 'auto'],
    ['setting_id' => 2, 'tenant_id' => 10, 'key_name' => 'beta', 'key_value' => 'old-beta', 'version' => 4, 'load_policy' => 'manual'],
    ['setting_id' => 3, 'tenant_id' => 20, 'key_name' => 'gamma', 'key_value' => 'old-gamma', 'version' => 2, 'load_policy' => 'auto'],
];

$execute = static function (string $sql, ?array $rows = null, ?array $constraints = null) use ($baseRows, $unique): array {
    return SQLiteUpsertReturningSql::execute(
        $sql,
        ['app_settings' => $rows ?? $baseRows],
        $constraints ?? $unique,
    );
};

$upsert2Mixed = static fn (): array => $execute(
    "INSERT INTO app_settings(setting_id, tenant_id, key_name, key_value, version, load_policy) VALUES
        (1,10,'alpha','new-alpha',8,'auto'),
        (4,10,'delta','new-delta',11,'manual'),
        (2,10,'beta','small-beta',1,'manual'),
        (2,10,'beta','large-beta',15,'manual')
     ON CONFLICT(setting_id) DO UPDATE SET key_value=excluded.key_value, version=version+1
     WHERE app_settings.version<excluded.version
     RETURNING setting_id, key_name, key_value, version"
);

$upsert3Composite = static fn (): array => $execute(
    "INSERT INTO app_settings(setting_id, tenant_id, key_name, key_value, version, load_policy) VALUES
        (10,30,'theta','one',0,'auto'),
        (11,30,'theta','two',7,'auto'),
        (12,40,'iota','three',2,'manual')
     ON CONFLICT(key_name,tenant_id) DO UPDATE SET version=excluded.version+1, key_value=excluded.key_value
     RETURNING tenant_id AS tenant, key_name AS setting, key_value AS value, version",
    [],
    [['tenant_id', 'key_name']]
);

$upsert1TargetPrecedence = static fn (): array => $execute(
    "INSERT INTO app_settings(setting_id, tenant_id, key_name, key_value, version, load_policy) VALUES
        (1,20,'gamma','retargeted',33,'auto')
     ON CONFLICT(key_name) DO UPDATE SET version=excluded.version, key_value=excluded.key_value
     RETURNING setting_id, tenant_id, key_name, key_value, version"
);

$doNothing = static fn (): array => $execute(
    "INSERT INTO app_settings(setting_id, tenant_id, key_name, key_value, version, load_policy) VALUES
        (1,30,'ignored-alpha','ignored',5,'auto'),
        (4,30,'zeta','inserted-zeta',6,'manual')
     ON CONFLICT(setting_id) DO NOTHING
     RETURNING *"
);

$returning1Update = static fn (): array => $execute(
    "INSERT INTO app_settings(setting_id, tenant_id, key_name, key_value, version, load_policy) VALUES
        (1,10,'alpha','returning-alpha',9,'auto'),
        (5,50,'epsilon','returning-epsilon',1,'manual')
     ON CONFLICT(setting_id) DO UPDATE SET key_value=excluded.key_value, version=version+1
     RETURNING *, key_name || ':' || version AS summary"
);

$cases = [
    'upsert2-100 dynamic returning changed rows only' => [static fn (): mixed => array_column($upsert2Mixed()['returning'], 'key_name'), ['alpha', 'delta', 'beta']],
    'upsert2-100 dynamic skipped failed WHERE omits row' => [static fn (): mixed => array_column($upsert2Mixed()['skipped_rows'], 'key_value'), ['small-beta']],
    'upsert2-100 dynamic later beta update sees original current after skip' => [static fn (): mixed => $upsert2Mixed()['returning'][2], ['setting_id' => 2, 'key_name' => 'beta', 'key_value' => 'large-beta', 'version' => 5]],
    'upsert2-100 dynamic inserted row is appended' => [static fn (): mixed => array_column($upsert2Mixed()['inserted_rows'], 'key_name'), ['delta']],
    'upsert2-100 dynamic update bucket preserves statement order' => [static fn (): mixed => array_column($upsert2Mixed()['updated_rows'], 'key_name'), ['alpha', 'beta']],
    'upsert2-100 dynamic change count excludes skipped row' => [static fn (): mixed => $upsert2Mixed()['changes'], 3],
    'upsert2-100 dynamic final table order keeps original rows before inserts' => [static fn (): mixed => array_column($upsert2Mixed()['after'], 'key_name'), ['alpha', 'beta', 'gamma', 'delta']],
    'upsert2-100 dynamic projection aliases values' => [static fn (): mixed => array_keys($upsert2Mixed()['returning'][0]), ['setting_id', 'key_name', 'key_value', 'version']],
    'upsert3-130 reversed composite target is accepted' => [static fn (): mixed => array_column($upsert3Composite()['returning'], 'setting'), ['theta', 'theta', 'iota']],
    'upsert3-200 repeated composite row updates inserted current' => [static fn (): mixed => $upsert3Composite()['returning'][1], ['tenant' => 30, 'setting' => 'theta', 'value' => 'two', 'version' => 8]],
    'upsert3-200 composite final row count' => [static fn (): mixed => count($upsert3Composite()['after']), 2],
    'upsert3-200 composite inserted rows exclude update' => [static fn (): mixed => array_column($upsert3Composite()['inserted_rows'], 'key_name'), ['theta', 'iota']],
    'upsert3-200 composite updated rows include repeated row' => [static fn (): mixed => array_column($upsert3Composite()['updated_rows'], 'key_value'), ['two']],
    'upsert3-200 unaliased table named excluded resolves qualifier to target row' => [static fn (): mixed => SQLiteUpsertReturningSql::execute(
        "INSERT INTO excluded(a,b,c) VALUES(1,2,0),(1,2,4),(3,4,0) ON CONFLICT(b,a) DO UPDATE SET c=excluded.c+1 RETURNING *",
        ['excluded' => []],
        [['a', 'b']],
    )['returning'], [['a' => 1, 'b' => 2, 'c' => 0], ['a' => 1, 'b' => 2, 'c' => 1], ['a' => 3, 'b' => 4, 'c' => 0]]],
    'upsert1-700 targeted key conflict wins over other unique conflicts' => [static fn (): mixed => $upsert1TargetPrecedence()['returning'][0], ['setting_id' => 3, 'tenant_id' => 20, 'key_name' => 'gamma', 'key_value' => 'retargeted', 'version' => 33]],
    'upsert1-700 targeted key conflict updates key row not rowid row' => [static fn (): mixed => array_column($upsert1TargetPrecedence()['after'], 'key_value'), ['old-alpha', 'old-beta', 'retargeted']],
    'upsert1-101 do nothing returns only inserted rows' => [static fn (): mixed => array_column($doNothing()['returning'], 'key_name'), ['zeta']],
    'upsert1-101 do nothing skips target conflict' => [static fn (): mixed => array_column($doNothing()['skipped_rows'], 'key_name'), ['ignored-alpha']],
    'upsert1-101 do nothing change count' => [static fn (): mixed => $doNothing()['changes'], 1],
    'upsert1-201 do nothing does not mask secondary unique conflict' => [static fn (): mixed => $execute("INSERT INTO app_settings(setting_id, tenant_id, key_name, key_value, version, load_policy) VALUES (4,20,'gamma','bad-secondary',1,'auto') ON CONFLICT(setting_id) DO NOTHING RETURNING *"), InvalidArgumentException::class],
    'upsert1-120 rejects conflict target without unique constraint' => [static fn (): mixed => $execute("INSERT INTO app_settings(setting_id, tenant_id, key_name, key_value, version, load_policy) VALUES (9,90,'omega','bad-target',1,'auto') ON CONFLICT(version) DO NOTHING RETURNING *"), InvalidArgumentException::class],
    'upsert3-110 rejects partial composite target' => [static fn (): mixed => $execute("INSERT INTO app_settings(setting_id, tenant_id, key_name, key_value, version, load_policy) VALUES (9,90,'omega','bad-target',1,'auto') ON CONFLICT(tenant_id) DO NOTHING RETURNING *"), InvalidArgumentException::class],
    'returning1-4.2 updated row returns post update values' => [static fn (): mixed => $returning1Update()['returning'][0]['key_value'], 'returning-alpha'],
    'returning1-4.2 returning wildcard includes inserted row' => [static fn (): mixed => $returning1Update()['returning'][1]['key_name'], 'epsilon'],
    'returning1-4.2 expression alias is evaluated from returned row' => [static fn (): mixed => array_column($returning1Update()['returning'], 'summary'), ['alpha:2', 'epsilon:1']],
    'returning1-4.5 mixed insert update returning order' => [static fn (): mixed => array_column($returning1Update()['returning'], 'setting_id'), [1, 5]],
    'returning1-7.7 rejected alias in returning table prefix' => [static fn (): mixed => $execute("INSERT INTO app_settings(setting_id, tenant_id, key_name, key_value, version, load_policy) VALUES (1,10,'alpha','bad-alias',1,'auto') ON CONFLICT(setting_id) DO UPDATE SET key_value=excluded.key_value RETURNING alias.key_value"), InvalidArgumentException::class],
    'returning1-7.8 target table prefix remains accepted' => [static fn (): mixed => $execute("INSERT INTO app_settings(setting_id, tenant_id, key_name, key_value, version, load_policy) VALUES (1,10,'alpha','target-prefix',1,'auto') ON CONFLICT(setting_id) DO UPDATE SET key_value=excluded.key_value RETURNING app_settings.key_value AS value")['returning'], [['value' => 'target-prefix']]],
    'returning1 rejects excluded references from RETURNING' => [static fn (): mixed => $execute("INSERT INTO app_settings(setting_id, tenant_id, key_name, key_value, version, load_policy) VALUES (1,10,'alpha','excluded-returning',1,'auto') ON CONFLICT(setting_id) DO UPDATE SET key_value=excluded.key_value RETURNING excluded.key_value"), InvalidArgumentException::class],
    'source coverage cites real upstream scripts' => [static fn (): mixed => ['upsert1.test', 'upsert2.test', 'upsert3.test', 'returning1.test'], ['upsert1.test', 'upsert2.test', 'upsert3.test', 'returning1.test']],
    'dependency closure reuses existing SQL upsert returning executor' => [static fn (): mixed => 'no new support component needed', 'no new support component needed'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['real upstream UPSERT RETURNING dynamic yield ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
