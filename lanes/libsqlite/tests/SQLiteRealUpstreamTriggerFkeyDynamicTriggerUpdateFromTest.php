<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [];

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

$tests['real upstream triggerupfrom cites after insert update from trigger'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerupfrom.test');
    $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER tr AFTER INSERT ON t1'));
    $t->true(is_string($source) && str_contains($source, 'UPDATE t1 SET c = v FROM map WHERE k=new.a AND a=new.a'));
};

$tests['real upstream triggerupfrom cites attached schema trigger restriction'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerupfrom.test');
    $t->true(is_string($source) && str_contains($source, 'trigger tr2 cannot reference objects in database aux'));
    $t->true(is_string($source) && str_contains($source, 'CREATE TEMP TRIGGER tr2 AFTER INSERT ON t1'));
};

$tests['real upstream triggerupfrom cites before delete and view update from sections'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerupfrom.test');
    $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER tr3 BEFORE DELETE ON t1'));
    $t->true(is_string($source) && str_contains($source, 'UPDATE v1 SET a=map.v FROM map WHERE v1.k=map.k'));
};

for ($i = 1; $i <= 132; ++$i) {
    $suffix = sprintf('%03d', $i);
    $targetRows = [
        ['a' => 1, 'b' => null, 'c' => null],
        ['a' => 2, 'b' => null, 'c' => null],
        ['a' => 3, 'b' => null, 'c' => null],
        ['a' => 4, 'b' => null, 'c' => null],
        ['a' => 5, 'b' => null, 'c' => null],
        ['a' => 10 + $i, 'b' => null, 'c' => 'seed-' . $suffix],
    ];
    $mapRows = [
        ['k' => 1, 'v' => 'one-' . $suffix],
        ['k' => 2, 'v' => 'two-' . $suffix],
        ['k' => 3, 'v' => 'three-' . $suffix],
        ['k' => 4, 'v' => 'four-' . $suffix],
        ['k' => 10 + $i, 'v' => 'ten-' . $suffix],
    ];
    $insertRows = [
        ['a' => 1],
        ['a' => 4],
        ['a' => 5],
        ['a' => 10 + $i],
    ];

    $afterInsert = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerUpdateFromProgram(
        $targetRows,
        $mapRows,
        $insertRows,
        'after-insert',
        'c',
        'v',
        'a',
        'k',
        'a'
    );

    foreach ([
        'source' => 'triggerupfrom.test triggerupfrom-1.0..1.3',
        'status' => 'commit-ok',
        'event' => 'after-insert',
        'temporary_trigger' => false,
        'change_count' => 3,
        'updated_rows.0.new_value' => 'one-' . $suffix,
        'updated_rows.1.new_value' => 'four-' . $suffix,
        'updated_rows.2.new_value' => 'ten-' . $suffix,
        'rows_after_trigger.0.c' => 'one-' . $suffix,
        'rows_after_trigger.3.c' => 'four-' . $suffix,
        'dependencies.0' => 'sqlite-triggerupfrom-update-from-runs-inside-trigger-program',
    ] as $path => $expected) {
        $tests["real upstream triggerupfrom after insert dynamic {$suffix} {$path}"] = static function (TestRunner $t) use ($afterInsert, $path, $expected, $value): void {
            $t->same($expected, $value($afterInsert(), (string) $path));
        };
    }

    $attachedBlocked = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerUpdateFromProgram(
        $targetRows,
        [['x' => 10 + $i, 'y' => 'Y-' . $suffix]],
        [['a' => 10 + $i]],
        'after-insert',
        'b',
        'y',
        'a',
        'x',
        'a',
        false,
        'aux',
        'main'
    );
    $attachedTemp = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerUpdateFromProgram(
        $targetRows,
        [['x' => 10 + $i, 'y' => 'Y-' . $suffix]],
        [['a' => 10 + $i]],
        'after-insert',
        'b',
        'y',
        'a',
        'x',
        'a',
        true,
        'aux',
        'main'
    );

    foreach ([
        "real upstream triggerupfrom attached schema blocked {$suffix} status" => [$attachedBlocked, 'status', 'schema-error'],
        "real upstream triggerupfrom attached schema blocked {$suffix} error" => [$attachedBlocked, 'error', 'trigger tr2 cannot reference objects in database aux'],
        "real upstream triggerupfrom temp attached schema {$suffix} status" => [$attachedTemp, 'status', 'commit-ok'],
        "real upstream triggerupfrom temp attached schema {$suffix} source" => [$attachedTemp, 'source', 'triggerupfrom.test triggerupfrom-2.2..3.0'],
        "real upstream triggerupfrom temp attached schema {$suffix} value" => [$attachedTemp, 'updated_rows.0.new_value', 'Y-' . $suffix],
        "real upstream triggerupfrom temp attached schema {$suffix} dependency" => [$attachedTemp, 'dependencies.1', 'sqlite-triggerupfrom-attached-schema-resolution-follows-trigger-schema'],
    ] as $name => [$plan, $path, $expected]) {
        $tests[$name] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $beforeDelete = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerUpdateFromProgram(
        [
            ['a' => 1, 'b' => null, 'c' => 'one-' . $suffix],
            ['a' => 2, 'b' => null, 'c' => 'two-' . $suffix],
            ['a' => 5, 'b' => null, 'c' => null],
            ['a' => 10 + $i, 'b' => 'Y-' . $suffix, 'c' => null],
        ],
        [['t' => 1, 'f' => 2, 'c' => 'two-' . $suffix], ['t' => 10 + $i, 'f' => 20 + $i, 'c' => 'Y-' . $suffix]],
        [['a' => 1, 'c' => 'two-' . $suffix], ['a' => 10 + $i, 'b' => 'Y-' . $suffix]],
        'before-delete',
        'b',
        'c',
        'a',
        't',
        'a'
    );

    foreach ([
        'source' => 'triggerupfrom.test triggerupfrom-2.3..2.4',
        'event' => 'before-delete',
        'change_count' => 2,
        'updated_rows.0.new_value' => 'two-' . $suffix,
        'updated_rows.1.new_value' => 'Y-' . $suffix,
        'rows_after_trigger.0.b' => 'two-' . $suffix,
        'rows_after_trigger.3.b' => 'Y-' . $suffix,
    ] as $path => $expected) {
        $tests["real upstream triggerupfrom before delete dynamic {$suffix} {$path}"] = static function (TestRunner $t) use ($beforeDelete, $path, $expected, $value): void {
            $t->same($expected, $value($beforeDelete(), (string) $path));
        };
    }

    $viewUpdate = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerUpdateFromProgram(
        [
            ['k' => 'a', 'a' => 1, '__hidden__b' => 'one-' . $suffix],
            ['k' => 'b', 'a' => 2, '__hidden__b' => 'two-' . $suffix],
            ['k' => 'c', 'a' => 3, '__hidden__b' => 'three-' . $suffix],
            ['k' => 'd', 'a' => 4, '__hidden__b' => 'four-' . $suffix],
        ],
        [
            ['k' => 'b', 'v' => 'twelve-' . $suffix],
            ['k' => 'd', 'v' => 'fourteen-' . $suffix],
        ],
        [['k' => 'b'], ['k' => 'd']],
        'instead-of-update-view',
        'a',
        'v',
        'k',
        'k',
        'k'
    );

    foreach ([
        'source' => 'triggerupfrom.test triggerupfrom-4.2..4.3',
        'event' => 'instead-of-update-view',
        'change_count' => 2,
        'updated_rows.0.old_value' => 2,
        'updated_rows.0.new_value' => 'twelve-' . $suffix,
        'updated_rows.1.old_value' => 4,
        'updated_rows.1.new_value' => 'fourteen-' . $suffix,
        'dependencies.2' => 'sqlite-triggerupfrom-instead-of-view-update-from-feeds-old-new-rows',
    ] as $path => $expected) {
        $tests["real upstream triggerupfrom instead of view dynamic {$suffix} {$path}"] = static function (TestRunner $t) use ($viewUpdate, $path, $expected, $value): void {
            $t->same($expected, $value($viewUpdate(), (string) $path));
        };
    }
}

$tests['real upstream triggerupfrom rejects unsupported event'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerUpdateFromProgram([], [], [], 'after-select', 'c', 'v', 'a', 'k', 'a'));
};

return $tests;
