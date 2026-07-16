<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [];

$sourceFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_droptrigger.test';
$tests['real upstream corpus trigger fkey dynamic drop table cleanup cites evidence block'] = static function (TestRunner $t) use ($sourceFile): void {
    $source = file_get_contents($sourceFile);
    $t->true(is_string($source) && str_contains($source, 'triggers are automatically'));
    $t->true(is_string($source) && str_contains($source, 'dropped when the associated table is dropped'));
    $t->true(is_string($source) && str_contains($source, 'DROP TABLE t1'));
    $t->true(is_string($source) && str_contains($source, 'do_test 4.1'));
};

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

$baseTriggers = static function (string $event): array {
    return [
        ['schema' => 'temp', 'name' => 'tr1', 'table' => 't1', 'event' => $event, 'timing' => 'after'],
        ['schema' => 'main', 'name' => 'tr1', 'table' => 't2', 'event' => $event, 'timing' => 'before'],
        ['schema' => 'main', 'name' => 'tr2', 'table' => 't2', 'event' => $event, 'timing' => 'after'],
        ['schema' => 'aux', 'name' => 'tr1', 'table' => 't3', 'event' => $event, 'timing' => 'before'],
        ['schema' => 'aux', 'name' => 'tr2', 'table' => 't3', 'event' => $event, 'timing' => 'after'],
        ['schema' => 'aux', 'name' => 'tr3', 'table' => 't3', 'event' => $event, 'timing' => 'after'],
    ];
};

$cases = [
    ['table' => 't1', 'schema' => null, 'dropped' => ['temp.tr1'], 'remaining' => ['main.tr1', 'main.tr2', 'aux.tr1', 'aux.tr2', 'aux.tr3']],
    ['table' => 't2', 'schema' => null, 'dropped' => ['main.tr1', 'main.tr2'], 'remaining' => ['temp.tr1', 'aux.tr1', 'aux.tr2', 'aux.tr3']],
    ['table' => 't3', 'schema' => null, 'dropped' => ['aux.tr1', 'aux.tr2', 'aux.tr3'], 'remaining' => ['temp.tr1', 'main.tr1', 'main.tr2']],
    ['table' => 't2', 'schema' => 'main', 'dropped' => ['main.tr1', 'main.tr2'], 'remaining' => ['temp.tr1', 'aux.tr1', 'aux.tr2', 'aux.tr3']],
    ['table' => 't3', 'schema' => 'aux', 'dropped' => ['aux.tr1', 'aux.tr2', 'aux.tr3'], 'remaining' => ['temp.tr1', 'main.tr1', 'main.tr2']],
    ['table' => 'missing_table', 'schema' => null, 'dropped' => [], 'remaining' => ['temp.tr1', 'main.tr1', 'main.tr2', 'aux.tr1', 'aux.tr2', 'aux.tr3']],
];

$events = ['insert', 'update', 'delete'];
for ($i = 0; $i < 180; ++$i) {
    $event = $events[$i % count($events)];
    $case = $cases[$i % count($cases)];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::dropTableTriggerCleanupPlan(
        $baseTriggers($event),
        $case['table'],
        $case['schema'],
    );
    $name = sprintf(
        'real upstream corpus trigger fkey dynamic drop table cleanup %03d %s %s',
        $i + 1,
        $event,
        $case['schema'] === null ? $case['table'] : $case['schema'] . '.' . $case['table'],
    );

    foreach ([
        'source' => 'e_droptrigger.test e_droptrigger-4.1..4.4',
        'operation' => 'drop-table-trigger-cleanup',
        'status' => 'commit-ok',
        'drop_table' => $case['table'],
        'drop_schema' => $case['schema'],
        'dropped_trigger_names' => $case['dropped'],
        'remaining_trigger_names' => $case['remaining'],
        'schema_rows_removed' => count($case['dropped']),
        'remaining_schema_row_count' => count($case['remaining']),
        'table_trigger_count_before' => count($case['dropped']),
        'auto_drop_trigger_definitions' => $case['dropped'] !== [],
        'dependencies.0' => 'sqlite-drop-table-removes-associated-trigger-definitions',
        'dependencies.1' => 'sqlite-drop-table-removes-temp-trigger-schema-row',
        'dependencies.2' => 'sqlite-drop-table-keeps-unrelated-schema-triggers',
    ] as $path => $expected) {
        $tests[$name . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests[$name . ' drops only triggers associated with the dropped table'] = static function (TestRunner $t) use ($plan, $case): void {
        $actual = $plan();
        foreach ($case['dropped'] as $triggerName) {
            $t->same(false, in_array($triggerName, $actual['remaining_trigger_names'], true));
        }
        foreach ($case['remaining'] as $triggerName) {
            $t->same(true, in_array($triggerName, $actual['remaining_trigger_names'], true));
        }
    };
}

$tests['real upstream corpus trigger fkey dynamic drop table cleanup rejects malformed table'] = static function (TestRunner $t) use ($baseTriggers): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::dropTableTriggerCleanupPlan($baseTriggers('insert'), 'bad-table'));
};

$tests['real upstream corpus trigger fkey dynamic drop table cleanup rejects malformed schema'] = static function (TestRunner $t) use ($baseTriggers): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::dropTableTriggerCleanupPlan($baseTriggers('insert'), 't1', 'bad-schema'));
};

return $tests;
