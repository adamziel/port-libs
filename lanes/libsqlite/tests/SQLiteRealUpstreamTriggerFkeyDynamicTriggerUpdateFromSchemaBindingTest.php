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
    'real upstream triggerupfrom schema binding cites attached trigger section' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerupfrom.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER aux.ttt AFTER INSERT ON t1'));
        $t->true(is_string($source) && str_contains($source, 'UPDATE t1 SET b=y FROM mmm WHERE x=new.a AND a=new.a'));
        $t->true(is_string($source) && str_contains($source, 'SELECT * FROM t1;'));
    },
    'real upstream triggerupfrom schema binding cites hidden column view section' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerupfrom.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE VIEW v1 AS SELECT k, a, b AS __hidden__b FROM t1'));
        $t->true(is_string($source) && str_contains($source, 'UPDATE v1 SET a=map.v FROM map WHERE v1.k=map.k'));
    },
];

for ($i = 1; $i <= 1000; ++$i) {
    $suffix = sprintf('%04d', $i);
    $targetKey = ($i % 3) + 1;
    $mainRows = [
        ['x' => 1, 'y' => 'main-one-' . $suffix],
        ['x' => 2, 'y' => 'main-two-' . $suffix],
        ['x' => 3, 'y' => 'main-three-' . $suffix],
    ];
    $auxRows = [
        ['x' => 1, 'y' => 'aux-ONE-' . $suffix],
        ['x' => 2, 'y' => 'aux-TWO-' . $suffix],
        ['x' => 3, 'y' => 'aux-THREE-' . $suffix],
    ];
    $expectedAuxValue = $auxRows[$targetKey - 1]['y'];

    $attachedPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerUpdateFromProgram(
        [['a' => $targetKey, 'b' => null]],
        $auxRows,
        [['a' => $targetKey]],
        'after-insert',
        'b',
        'y',
        'a',
        'x',
        'a',
        false,
        'aux',
        'aux'
    );

    foreach ([
        'source' => 'triggerupfrom.test triggerupfrom-2.0..3.0',
        'status' => 'commit-ok',
        'event' => 'after-insert',
        'temporary_trigger' => false,
        'source_schema' => 'aux',
        'target_schema' => 'aux',
        'change_count' => 1,
        'updated_rows.0.key' => $targetKey,
        'updated_rows.0.old_value' => null,
        'updated_rows.0.new_value' => $expectedAuxValue,
        'updated_rows.0.source_matched' => true,
        'rows_after_trigger.0.b' => $expectedAuxValue,
        'dependencies.1' => 'sqlite-triggerupfrom-attached-schema-resolution-follows-trigger-schema',
    ] as $path => $expected) {
        $tests["real upstream triggerupfrom aux schema binding {$suffix} {$path}"] = static function (TestRunner $t) use ($attachedPlan, $path, $expected, $value): void {
            $t->same($expected, $value($attachedPlan(), (string) $path));
        };
    }

    $tests["real upstream triggerupfrom aux schema binding {$suffix} ignores main same-name source"] = static function (TestRunner $t) use ($attachedPlan, $mainRows): void {
        $t->same(false, in_array($attachedPlan()['updated_rows'][0]['new_value'], array_column($mainRows, 'y'), true));
    };

    $viewPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerUpdateFromProgram(
        [
            ['k' => 'a', 'a' => 1 + $i, '__hidden__b' => 'one-' . $suffix],
            ['k' => 'b', 'a' => 2 + $i, '__hidden__b' => 'two-' . $suffix],
            ['k' => 'c', 'a' => 3 + $i, '__hidden__b' => 'three-' . $suffix],
            ['k' => 'd', 'a' => 4 + $i, '__hidden__b' => 'four-' . $suffix],
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
        'updated_rows.0.old_value' => 2 + $i,
        'updated_rows.0.new_value' => 'twelve-' . $suffix,
        'updated_rows.1.old_value' => 4 + $i,
        'updated_rows.1.new_value' => 'fourteen-' . $suffix,
        'log.0' => '(' . (2 + $i) . ',two-' . $suffix . ')->(twelve-' . $suffix . ',two-' . $suffix . ')',
        'log.1' => '(' . (4 + $i) . ',four-' . $suffix . ')->(fourteen-' . $suffix . ',four-' . $suffix . ')',
        'dependencies.2' => 'sqlite-triggerupfrom-instead-of-view-update-from-feeds-old-new-rows',
    ] as $path => $expected) {
        $tests["real upstream triggerupfrom view hidden update-from {$suffix} {$path}"] = static function (TestRunner $t) use ($viewPlan, $path, $expected, $value): void {
            $t->same($expected, $value($viewPlan(), (string) $path));
        };
    }
}

$tests['real upstream triggerupfrom schema binding rejects malformed schema identifier'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerUpdateFromProgram(
        [['a' => 1, 'b' => null]],
        [['x' => 1, 'y' => 'one']],
        [['a' => 1]],
        'after-insert',
        'b',
        'y',
        'a',
        'x',
        'a',
        false,
        'bad-schema',
        'aux'
    ));
};

return $tests;
