<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [];

$sourceFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_droptrigger.test';
$tests['real upstream corpus trigger fkey dynamic drop trigger cites schema resolution section'] = static function (TestRunner $t) use ($sourceFile): void {
    $source = file_get_contents($sourceFile);
    $t->true(is_string($source) && str_contains($source, 'DROP TRIGGER main.tr1'));
    $t->true(is_string($source) && str_contains($source, 'DROP TRIGGER aux.tr1'));
};
$tests['real upstream corpus trigger fkey dynamic drop trigger cites firing removal section'] = static function (TestRunner $t) use ($sourceFile): void {
    $source = file_get_contents($sourceFile);
    $t->true(is_string($source) && str_contains($source, 'set ::triggers_fired'));
    $t->true(is_string($source) && str_contains($source, 'Once removed, the trigger definition is no'));
};
$tests['real upstream corpus trigger fkey dynamic drop trigger cites update delete coverage'] = static function (TestRunner $t) use ($sourceFile): void {
    $source = file_get_contents($sourceFile);
    $t->true(is_string($source) && str_contains($source, 'droptrigger_reopen_db UPDATE'));
    $t->true(is_string($source) && str_contains($source, 'droptrigger_reopen_db DELETE'));
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

$dropCases = [
    ['DROP TRIGGER main.tr1', 'main.tr1', false, 't2', 'main.tr1', ['main.tr2']],
    ['DROP TRIGGER IF EXISTS main.tr1', 'main.tr1', true, 't2', 'main.tr1', ['main.tr2']],
    ['DROP TRIGGER tr1', 'tr1', false, 't1', 'temp.tr1', []],
    ['DROP TRIGGER IF EXISTS tr1', 'tr1', true, 't1', 'temp.tr1', []],
    ['DROP TRIGGER aux.tr1', 'aux.tr1', false, 't3', 'aux.tr1', ['aux.tr2', 'aux.tr3']],
    ['DROP TRIGGER IF EXISTS aux.tr1', 'aux.tr1', true, 't3', 'aux.tr1', ['aux.tr2', 'aux.tr3']],
    ['DROP TRIGGER tr2', 'tr2', false, 't2', 'main.tr2', ['main.tr1']],
    ['DROP TRIGGER tr3', 'tr3', false, 't3', 'aux.tr3', ['aux.tr1', 'aux.tr2']],
    ['DROP TRIGGER IF EXISTS aux.xxx', 'aux.xxx', true, 't3', null, ['aux.tr1', 'aux.tr2', 'aux.tr3']],
    ['DROP TRIGGER IF EXISTS missing', 'missing', true, 't2', null, ['main.tr1', 'main.tr2']],
];

$events = ['insert', 'update', 'delete'];
for ($i = 0; $i < 250; ++$i) {
    $event = $events[$i % count($events)];
    [$label, $dropName, $ifExists, $table, $dropped, $expectedAfter] = $dropCases[$i % count($dropCases)];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::dropTriggerSchemaResolutionPlan(
        $baseTriggers($event),
        $dropName,
        $event,
        $table,
        $ifExists
    );
    $case = sprintf('e_droptrigger.test dynamic %03d %s %s on %s', $i + 1, $label, $event, $table);

    foreach ([
        'source' => 'e_droptrigger.test e_droptrigger-1..4',
        'operation' => 'drop-trigger-schema-resolution-and-fired-program-removal',
        'status' => 'commit-ok',
        'drop_name' => $dropName,
        'if_exists' => $ifExists,
        'dropped_trigger' => $dropped,
        'event' => $event,
        'table' => $table,
        'fired_after' => $expectedAfter,
        'schema_rows_removed' => $dropped === null ? 0 : 1,
        'dependencies.0' => 'sqlite-drop-trigger-removes-schema-row',
        'dependencies.1' => 'sqlite-drop-trigger-unqualified-schema-search-order',
        'dependencies.2' => 'sqlite-drop-trigger-removed-program-no-longer-fires',
        'dependencies.3' => 'sqlite-drop-trigger-if-exists-allows-missing-trigger',
    ] as $path => $expected) {
        $tests['real upstream corpus trigger fkey dynamic drop trigger ' . $case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests['real upstream corpus trigger fkey dynamic drop trigger ' . $case . ' removed trigger is absent from remaining schema'] = static function (TestRunner $t) use ($plan, $dropped): void {
        $actual = $plan();
        if ($dropped === null) {
            $t->same(6, count($actual['remaining_trigger_names']));
            return;
        }

        $t->same(false, in_array($dropped, $actual['remaining_trigger_names'], true));
    };
    $tests['real upstream corpus trigger fkey dynamic drop trigger ' . $case . ' before firing list contains event table programs'] = static function (TestRunner $t) use ($plan, $expectedAfter, $dropped): void {
        $actual = $plan();
        $t->true(count($actual['fired_before']) >= count($expectedAfter));
        if ($dropped !== null) {
            $t->true(in_array($dropped, $actual['fired_before'], true));
        }
    };
}

$tests['real upstream corpus trigger fkey dynamic drop trigger rejects missing trigger without if exists'] = static function (TestRunner $t) use ($baseTriggers): void {
    $actual = SQLiteDynamicTriggerForeignKeyPlan::dropTriggerSchemaResolutionPlan($baseTriggers('insert'), 'missing', 'insert', 't2');
    $t->same('schema-error', $actual['status']);
    $t->same('no such trigger: missing', $actual['error']);
};

$tests['real upstream corpus trigger fkey dynamic drop trigger rejects malformed qualified name'] = static function (TestRunner $t) use ($baseTriggers): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::dropTriggerSchemaResolutionPlan($baseTriggers('insert'), 'main.aux.tr1', 'insert', 't2'));
};

return $tests;
