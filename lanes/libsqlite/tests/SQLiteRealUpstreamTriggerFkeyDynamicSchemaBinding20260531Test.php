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
    'real upstream triggerD schema binding cites main temp shadow block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerD.test');

        $t->true(is_string($source) && str_contains($source, 'do_test triggerD-3.1'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TEMP TABLE t300(x);'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER main.r300 AFTER INSERT ON t300'));
    },
    'real upstream triggerD schema binding cites temp trigger block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerD.test');

        $t->true(is_string($source) && str_contains($source, 'do_test triggerD-3.2'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER temp.r301 AFTER INSERT ON t300'));
        $t->true(is_string($source) && str_contains($source, 'SELECT * FROM t301;'));
    },
    'real upstream triggerD schema binding cites attached reparse block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerD.test');

        $t->true(is_string($source) && str_contains($source, 'do_test triggerD-4.1'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER db2.trig AFTER INSERT ON db2.t2'));
        $t->true(is_string($source) && str_contains($source, 'do_test triggerD-4.2'));
    },
];

for ($i = 1; $i <= 125; ++$i) {
    $mainValue = 2 + $i;
    $tempValue = 3 + $i;
    $attachedValue = 120 + $i;
    $attachedSecond = 230 + $i;
    $tempTrigger = $i % 2 === 0;
    $attachedSchema = $i % 3 === 0 ? 'audit' : 'archive';
    $inserts = [
        ['schema' => 'main', 'table' => 't300', 'value' => $mainValue],
        ['schema' => 'temp', 'table' => 't300', 'value' => $tempValue],
        ['schema' => $attachedSchema, 'table' => 't2', 'value' => $attachedValue],
        ['schema' => $attachedSchema, 'table' => 't2', 'value' => $attachedSecond],
    ];
    if ($i % 5 === 0) {
        $inserts[] = ['schema' => 'main', 'table' => 'unrelated', 'value' => 900 + $i];
    }
    if ($i % 7 === 0) {
        $inserts[] = ['schema' => 'temp', 'table' => 'unrelated', 'value' => 1000 + $i];
    }

    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerSchemaBindingResolution($inserts, $tempTrigger, $attachedSchema);
    $expectedTemp = $tempTrigger ? [20000 + $tempValue] : [];
    $expectedLog = array_merge([10000 + $mainValue], $expectedTemp, [$attachedValue, $attachedSecond]);
    $case = sprintf('real upstream triggerD schema binding dynamic %03d', $i);

    foreach ([
        'source' => 'triggerD.test triggerD-3.1..4.2',
        'operation' => 'trigger-schema-binding-resolution',
        'status' => 'commit-ok',
        'temp_trigger' => $tempTrigger,
        'attached_schema' => $attachedSchema,
        'log_values' => $expectedLog,
        'main_trigger_values' => [10000 + $mainValue],
        'temp_trigger_values' => $expectedTemp,
        'attached_trigger_values' => [$attachedValue, $attachedSecond],
        'log_count' => count($expectedLog),
        'dependencies.0' => 'sqlite-triggerD-main-trigger-binds-main-table-not-temp-shadow',
        'dependencies.1' => 'sqlite-triggerD-temp-trigger-binds-temp-table-shadow',
        'dependencies.2' => 'sqlite-triggerD-attached-trigger-reparse-ignores-qualified-target-prefix',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests[$case . ' main insert is not captured by temp trigger'] = static function (TestRunner $t) use ($plan, $mainValue): void {
        $actual = $plan();

        $t->same([10000 + $mainValue], $actual['main_trigger_values']);
        $t->true(!in_array(20000 + $mainValue, $actual['log_values'], true));
    };
    $tests[$case . ' temp insert is not captured by main trigger'] = static function (TestRunner $t) use ($plan, $tempValue): void {
        $actual = $plan();

        $t->true(!in_array(10000 + $tempValue, $actual['log_values'], true));
    };
    $tests[$case . ' attached trigger preserves same-schema log target'] = static function (TestRunner $t) use ($plan, $attachedSchema): void {
        $attached = array_values(array_filter($plan()['log_rows'], static fn (array $row): bool => $row['trigger'] === $attachedSchema . '.trig'));

        $t->same($attachedSchema, $attached[0]['target_schema']);
        $t->same($attachedSchema, $attached[1]['target_schema']);
    };
    $tests[$case . ' unrelated table inserts do not fire schema triggers'] = static function (TestRunner $t) use ($plan, $i): void {
        $actual = $plan();

        $t->true(!in_array(900 + $i, $actual['log_values'], true));
        $t->true(!in_array(1000 + $i, $actual['log_values'], true));
    };
}

$tests['real upstream triggerD schema binding rejects unknown schema'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerSchemaBindingResolution([
        ['schema' => 'other', 'table' => 't300', 'value' => 1],
    ]));
};

$tests['real upstream triggerD schema binding rejects malformed attached schema'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerSchemaBindingResolution([], false, 'bad-schema'));
};

return $tests;
