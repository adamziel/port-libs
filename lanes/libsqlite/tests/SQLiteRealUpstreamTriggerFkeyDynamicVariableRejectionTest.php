<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$value = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$tests = [
    'real upstream trigger2 variable rejection cites trigger body parameter case' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test');
        $t->true(is_string($source) && str_contains($source, 'trigger cannot use variables'));
        $t->true(is_string($source) && str_contains($source, 'INSERT INTO t2(a,b,c,d,e) VALUES(91,NULL,93,94,?1)'));
    },
];

$timings = ['before', 'after', 'instead of'];
$events = ['insert', 'update', 'delete'];
$parameterForms = [
    '?1' => 'INSERT INTO app_child(a,b,c,d,e) VALUES(91,NULL,93,94,?1)',
    '?' => 'UPDATE app_child SET e=? WHERE a=91',
    ':tenant_key' => 'DELETE FROM app_child WHERE e=:tenant_key',
    '@setting_key' => 'INSERT INTO app_child(a,b,c,d,e) VALUES(92,NULL,93,94,@setting_key)',
    '$load_policy' => 'UPDATE app_child SET e=$load_policy WHERE a=92',
];

for ($i = 1; $i <= 84; ++$i) {
    $timing = $timings[$i % count($timings)];
    $event = $events[$i % count($events)];
    $parameterName = array_keys($parameterForms)[$i % count($parameterForms)];
    $badStatement = $parameterForms[$parameterName];
    $statements = [
        'INSERT INTO app_audit(event_name, row_key) VALUES(' . "'seen'" . ', new.a)',
        $badStatement,
        'UPDATE app_child SET c=10 WHERE a=new.a',
    ];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerProgramVariableUseRejection(
        $statements,
        $timing,
        $event,
        'app_parent'
    );
    $case = 'trigger2-11 trigger body rejects variable ' . $parameterName . ' dynamic ' . $i;

    foreach ([
        'source' => 'trigger2.test trigger2-11.1..11.2',
        'operation' => 'trigger-program-variable-use-rejection',
        'status' => 'parse-error',
        'error' => 'trigger cannot use variables',
        'timing' => $timing,
        'event' => $event,
        'target_table' => 'app_parent',
        'statement_count' => 3,
        'bad_statement_count' => 1,
        'bad_statement_indexes.0' => 1,
        'bad_statements.0.statement' => $badStatement,
        'dependencies.0' => 'sqlite-trigger2-trigger-program-rejects-qmark-parameters',
        'dependencies.1' => 'sqlite-trigger2-trigger-program-rejects-named-parameters',
        'dependencies.2' => 'sqlite-trigger2-trigger-parse-error-before-install',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($i = 1; $i <= 40; ++$i) {
    $timing = $timings[$i % count($timings)];
    $event = $events[$i % count($events)];
    $statements = [
        'INSERT INTO app_audit(event_name, row_key) VALUES(' . "'seen'" . ', new.a)',
        'UPDATE app_child SET c=10 WHERE a=new.a',
    ];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerProgramVariableUseRejection(
        $statements,
        $timing,
        $event,
        'app_parent'
    );
    $case = 'trigger2-11 trigger body accepts literal old new references dynamic ' . $i;

    foreach ([
        'source' => 'trigger2.test trigger2-11.1..11.2',
        'operation' => 'trigger-program-variable-use-rejection',
        'status' => 'commit-ok',
        'error' => null,
        'timing' => $timing,
        'event' => $event,
        'target_table' => 'app_parent',
        'statement_count' => 2,
        'bad_statement_count' => 0,
        'bad_statement_indexes' => [],
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

return $tests;
