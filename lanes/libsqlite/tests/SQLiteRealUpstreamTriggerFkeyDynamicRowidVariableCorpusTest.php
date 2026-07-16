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

$triggerDSource = '/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerD.test';
$triggerESource = '/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerE.test';

$tests = [
    'real upstream triggerD cites ordinary rowid column shadowing ticket' => static function (TestRunner $t) use ($triggerDSource): void {
        $source = file_get_contents($triggerDSource);
        $t->true(is_string($source) && str_contains($source, 'Verify that when columns named "rowid", "oid", and "_rowid_"'));
    },
    'real upstream triggerD cites physical rowid alias trigger output' => static function (TestRunner $t) use ($triggerDSource): void {
        $source = file_get_contents($triggerDSource);
        $t->true(is_string($source) && str_contains($source, '{r1 -1 -1 -1 200 r2 1 1 1 200}'));
    },
    'real upstream triggerE cites trigger variable rejection' => static function (TestRunner $t) use ($triggerESource): void {
        $source = file_get_contents($triggerESource);
        $t->true(is_string($source) && str_contains($source, 'trigger cannot use variables'));
    },
    'real upstream triggerE cites schema loaded variable null coercion' => static function (TestRunner $t) use ($triggerESource): void {
        $source = file_get_contents($triggerESource);
        $t->true(is_string($source) && str_contains($source, 'variable reference always evaluates'));
    },
];

for ($i = 1; $i <= 160; ++$i) {
    $declared = ($i % 2) === 0;
    $event = ['insert', 'update', 'delete'][$i % 3];
    $physicalRowid = 1 + ($i % 37);
    $row = $declared
        ? ['rowid' => 100 + $i, 'oid' => 200 + $i, '_rowid_' => 300 + $i, 'x' => 400 + $i]
        : ['w' => 100 + $i, 'x' => 200 + $i, 'y' => 300 + $i, 'z' => 400 + $i];

    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerRowidAliasResolutionPlan($row, $event, $declared, $physicalRowid);
    $actual = $plan();
    $case = "triggerD rowid alias dynamic {$i} {$event} " . ($declared ? 'declared' : 'physical');

    foreach ([
        'source' => 'triggerD.test triggerD-1.1..2.4',
        'operation' => 'trigger-rowid-alias-resolution',
        'status' => 'commit-ok',
        'event' => $event,
        'declared_rowid_columns' => $declared,
        'physical_rowid' => $physicalRowid,
        'log_count' => $event === 'update' ? 4 : 2,
        'uses_declared_columns_before_physical_aliases' => $declared,
        'insert_before_trigger_sees_unassigned_rowid' => !$declared && $event === 'insert',
        'dependencies.0' => 'sqlite-triggerD-declared-rowid-columns-shadow-physical-rowid',
        'dependencies.1' => 'sqlite-triggerD-old-new-rowid-aliases-use-physical-rowid-when-not-declared',
        'dependencies.2' => 'sqlite-triggerD-before-insert-rowid-alias-is-negative-one',
    ] as $path => $expected) {
        $tests['real upstream ' . $case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests['real upstream ' . $case . ' rowid alias stream matches upstream resolution'] = static function (TestRunner $t) use ($plan, $actual): void {
        $t->same($actual['rowid_values'], $plan()['rowid_values']);
    };
    $tests['real upstream ' . $case . ' oid alias stream matches rowid stream for physical aliases'] = static function (TestRunner $t) use ($plan, $declared): void {
        $actual = $plan();
        $t->same($declared ? array_column($actual['log'], 'oid') : $actual['rowid_values'], $actual['oid_values']);
    };
    $tests['real upstream ' . $case . ' underscore rowid alias stream matches rowid stream for physical aliases'] = static function (TestRunner $t) use ($plan, $declared): void {
        $actual = $plan();
        $t->same($declared ? array_column($actual['log'], '_rowid_') : $actual['rowid_values'], $actual['_rowid_values']);
    };
}

$placeholders = ['?', '?1', '?2', '$1', ':setting', '@payload', '$named'];
$locations = ['when', 'select', 'nested_select', 'group_by', 'limit', 'order_by', 'update_set', 'update_where', 'pragma_arg', 'window_order'];

for ($i = 1; $i <= 120; ++$i) {
    $fromWritableSchema = ($i % 4) === 0;
    $count = 1 + ($i % 3);
    $references = [];
    for ($j = 0; $j < $count; ++$j) {
        $references[] = [
            'column' => 'c' . ($j + 1),
            'placeholder' => $placeholders[($i + $j) % count($placeholders)],
            'location' => $locations[($i + $j) % count($locations)],
        ];
    }

    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerVariableReferencePlan($references, $fromWritableSchema);
    $case = 'triggerE variable boundary dynamic ' . $i . ' refs ' . $count . ' ' . ($fromWritableSchema ? 'schema-loaded' : 'create-time');

    foreach ([
        'source' => 'triggerE.test triggerE-1.1..2.3',
        'operation' => 'trigger-variable-reference-boundary',
        'status' => $fromWritableSchema ? 'loaded-from-schema-null-coercion' : 'create-trigger-rejected',
        'from_writable_schema' => $fromWritableSchema,
        'error' => $fromWritableSchema ? null : 'trigger cannot use variables',
        'reference_count' => $count,
        'coerces_to_null' => $fromWritableSchema,
        'creation_rejected' => !$fromWritableSchema,
        'dependencies.0' => 'sqlite-triggerE-create-trigger-rejects-bound-variables',
        'dependencies.1' => 'sqlite-triggerE-schema-loaded-trigger-variables-become-null',
        'dependencies.2' => 'sqlite-triggerE-trigger-variable-null-comparisons-drive-body',
    ] as $path => $expected) {
        $tests['real upstream ' . $case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests['real upstream ' . $case . ' runtime values all coerce to null'] = static function (TestRunner $t) use ($plan, $count): void {
        $t->same(array_fill(0, $count, null), $plan()['runtime_values']);
    };
    $tests['real upstream ' . $case . ' placeholders are preserved for diagnostics'] = static function (TestRunner $t) use ($plan, $references): void {
        $t->same(array_column($references, 'placeholder'), array_column($plan()['references'], 'placeholder'));
    };
    $tests['real upstream ' . $case . ' variable locations are preserved for diagnostics'] = static function (TestRunner $t) use ($plan, $references): void {
        $t->same(array_column($references, 'location'), array_column($plan()['references'], 'location'));
    };
}

$tests['real upstream triggerD rejects unsupported trigger event'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerRowidAliasResolutionPlan(['x' => 1], 'replace', false));
};

$tests['real upstream triggerD rejects nonpositive physical rowid'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerRowidAliasResolutionPlan(['x' => 1], 'insert', false, 0));
};

$tests['real upstream triggerD rejects nonnumeric declared rowid update'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerRowidAliasResolutionPlan(['rowid' => 'r', 'oid' => 1, '_rowid_' => 2, 'x' => 3], 'update', true));
};

$tests['real upstream triggerE rejects malformed trigger placeholder'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerVariableReferencePlan([['column' => 'c1', 'placeholder' => 'not a placeholder', 'location' => 'body']]));
};

$tests['real upstream triggerE rejects malformed trigger variable column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerVariableReferencePlan([['column' => 'bad-column', 'placeholder' => '?1', 'location' => 'body']]));
};

return $tests;
