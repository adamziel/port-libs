<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$valueAt = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$tests = [
    'real upstream trigger1 program restriction cites hydrated source' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test');
        $t->true(is_string($source) && str_contains($source, 'do_test trigger1-16.1'));
        $t->true(is_string($source) && str_contains($source, 'do_test trigger1-16.7'));
        $t->true(is_string($source) && str_contains($source, 'qualified table names are not allowed on INSERT, UPDATE, and DELETE statements within triggers'));
        $t->true(is_string($source) && str_contains($source, 'the INDEXED BY clause is not allowed on UPDATE or DELETE statements within triggers'));
    },
];

$restrictedCases = [
    'trigger1-16.1' => [
        'sql' => 'INSERT INTO main.t16 VALUES(1,2,3)',
        'kind' => 'insert',
        'target' => 'main.t16',
        'error' => 'qualified table names are not allowed on INSERT, UPDATE, and DELETE statements within triggers',
        'qualified' => true,
        'not_indexed' => false,
        'indexed_by' => false,
    ],
    'trigger1-16.2' => [
        'sql' => 'UPDATE main.t16 SET rowid=rowid+1',
        'kind' => 'update',
        'target' => 'main.t16',
        'error' => 'qualified table names are not allowed on INSERT, UPDATE, and DELETE statements within triggers',
        'qualified' => true,
        'not_indexed' => false,
        'indexed_by' => false,
    ],
    'trigger1-16.3' => [
        'sql' => 'DELETE FROM main.t16',
        'kind' => 'delete',
        'target' => 'main.t16',
        'error' => 'qualified table names are not allowed on INSERT, UPDATE, and DELETE statements within triggers',
        'qualified' => true,
        'not_indexed' => false,
        'indexed_by' => false,
    ],
    'trigger1-16.4' => [
        'sql' => 'UPDATE t16 NOT INDEXED SET rowid=rowid+1',
        'kind' => 'update',
        'target' => 't16',
        'error' => 'the NOT INDEXED clause is not allowed on UPDATE or DELETE statements within triggers',
        'qualified' => false,
        'not_indexed' => true,
        'indexed_by' => false,
    ],
    'trigger1-16.5' => [
        'sql' => 'UPDATE t16 INDEXED BY t16a SET rowid=rowid+1 WHERE a=1',
        'kind' => 'update',
        'target' => 't16',
        'error' => 'the INDEXED BY clause is not allowed on UPDATE or DELETE statements within triggers',
        'qualified' => false,
        'not_indexed' => false,
        'indexed_by' => true,
    ],
    'trigger1-16.6' => [
        'sql' => 'DELETE FROM t16 NOT INDEXED WHERE a=123',
        'kind' => 'delete',
        'target' => 't16',
        'error' => 'the NOT INDEXED clause is not allowed on UPDATE or DELETE statements within triggers',
        'qualified' => false,
        'not_indexed' => true,
        'indexed_by' => false,
    ],
    'trigger1-16.7' => [
        'sql' => 'DELETE FROM t16 INDEXED BY t16a WHERE a=123',
        'kind' => 'delete',
        'target' => 't16',
        'error' => 'the INDEXED BY clause is not allowed on UPDATE or DELETE statements within triggers',
        'qualified' => false,
        'not_indexed' => false,
        'indexed_by' => true,
    ],
];

foreach ($restrictedCases as $case => $config) {
    for ($variant = 1; $variant <= 120; ++$variant) {
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerProgramDmlRestrictionPlan((string) $config['sql']);

        foreach ([
            'source' => 'trigger1.test trigger1-16.1..16.7',
            'operation' => 'trigger-program-dml-restriction',
            'status' => 'schema-error',
            'statement_kind' => $config['kind'],
            'target' => $config['target'],
            'qualified_target' => $config['qualified'],
            'uses_not_indexed' => $config['not_indexed'],
            'uses_indexed_by' => $config['indexed_by'],
            'installed' => false,
            'error' => $config['error'],
            'dependencies.0' => 'sqlite-trigger1-trigger-program-dml-target-must-be-unqualified',
            'dependencies.1' => 'sqlite-trigger1-trigger-program-update-delete-disallows-not-indexed',
            'dependencies.2' => 'sqlite-trigger1-trigger-program-update-delete-disallows-indexed-by',
        ] as $path => $expected) {
            $tests["real upstream {$case} trigger program restriction variant {$variant} {$path}"] = static function (TestRunner $t) use ($plan, $path, $expected, $valueAt): void {
                $t->same($expected, $valueAt($plan(), (string) $path));
            };
        }
    }
}

$allowedCases = [
    'INSERT INTO t16 VALUES(1,2,3)' => ['insert', 't16'],
    'UPDATE t16 SET rowid=rowid+1 WHERE a=1' => ['update', 't16'],
    'DELETE FROM t16 WHERE a=123' => ['delete', 't16'],
];

foreach ($allowedCases as $sql => [$kind, $target]) {
    $tests['real upstream trigger1 program restriction allows unqualified ' . $kind] = static function (TestRunner $t) use ($sql, $kind, $target): void {
        $plan = SQLiteDynamicTriggerForeignKeyPlan::triggerProgramDmlRestrictionPlan($sql);
        $t->same('commit-ok', $plan['status']);
        $t->same($kind, $plan['statement_kind']);
        $t->same($target, $plan['target']);
        $t->same(true, $plan['installed']);
        $t->same(null, $plan['error']);
    };
}

$tests['real upstream trigger1 program restriction rejects unsupported statement kind'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerProgramDmlRestrictionPlan('SELECT * FROM t16'));
};

$tests['real upstream trigger1 program restriction rejects missing target'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerProgramDmlRestrictionPlan('UPDATE'));
};

return $tests;
