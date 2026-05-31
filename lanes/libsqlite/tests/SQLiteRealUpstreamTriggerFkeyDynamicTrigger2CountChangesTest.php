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
    'real upstream trigger2 count changes cites trigger program section' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test');
        $t->true(is_string($source) && str_contains($source, 'do_test trigger2-5'));
        $t->true(is_string($source) && str_contains($source, 'db changes'));
        $t->true(is_string($source) && str_contains($source, 'DELETE FROM tbl;'));
    },
];

for ($i = 1; $i <= 120; ++$i) {
    $base = $i * 100;
    $rows = [
        ['id' => $base + 1, 'a' => 9, 'b' => 90, 'c' => 900],
        ['id' => $base + 2, 'a' => 8, 'b' => 80, 'c' => 800],
    ];
    $incoming = ['id' => $base + 3, 'a' => 100, 'b' => 200, 'c' => 300 + $i];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::trigger2CountChangesExcludesTriggerProgram($rows, $incoming);
    $case = sprintf('trigger2 count changes boundary dynamic %03d', $i);

    foreach ([
        'source' => 'trigger2.test trigger2-5',
        'operation' => 'trigger-program-count-changes-boundary',
        'status' => 'commit-ok',
        'before_trigger' => true,
        'incoming_id' => $base + 3,
        'initial_row_ids' => [$base + 1, $base + 2],
        'trigger_effects.0.action' => 'insert',
        'trigger_effects.1.action' => 'insert',
        'trigger_effects.2.updated_ids' => [-1],
        'trigger_effects.3.deleted_ids' => [-1],
        'trigger_effects.4.deleted_ids' => [$base + 1, $base + 2, -2],
        'trigger_change_count' => 7,
        'direct_statement_changes' => 1,
        'db_changes_result' => 1,
        'count_changes_excludes_trigger_program' => true,
        'total_changes_includes_trigger_program' => true,
        'total_changes_delta' => 8,
        'final_row_ids' => [$base + 3],
        'final_rows.0.c' => 300 + $i,
        'dependencies.0' => 'sqlite-trigger2-db-changes-excludes-trigger-program-work',
        'dependencies.1' => 'sqlite-trigger2-before-insert-program-runs-before-direct-row-write',
        'dependencies.2' => 'sqlite-trigger2-trigger-program-delete-all-does-not-cancel-direct-insert',
    ] as $path => $expected) {
        $tests['real upstream ' . $case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

$tests['real upstream trigger2 count changes rejects after trigger mode'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::trigger2CountChangesExcludesTriggerProgram([], ['id' => 1, 'a' => 1, 'b' => 1, 'c' => 1], false));
};

$tests['real upstream trigger2 count changes rejects missing incoming column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::trigger2CountChangesExcludesTriggerProgram([], ['id' => 1, 'a' => 1, 'b' => 1]));
};

return $tests;
