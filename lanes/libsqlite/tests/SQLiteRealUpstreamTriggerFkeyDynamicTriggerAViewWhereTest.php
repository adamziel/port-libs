<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

/**
 * @param array<string,mixed> $array
 */
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
    'real upstream triggerA view where corpus cites hydrated upstream source' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerA.test');
        $t->true(is_string($source) && str_contains($source, 'do_test triggerA-2.1'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER r1d INSTEAD OF DELETE ON v1'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER r5u INSTEAD OF UPDATE ON v5'));
    },
];

$expectations = [
    'v1' => [
        'delete' => ['view_row_count' => 10, 'matched_row_count' => 3, 'first_log_row' => ['old_a' => 'five', 'old_b' => 5], 'last_log_row' => ['old_a' => 'three', 'old_b' => 3]],
        'update' => ['view_row_count' => 10, 'matched_row_count' => 3, 'first_log_row' => ['old_a' => 'five', 'old_b' => 5, 'new_c' => 'five-extra', 'new_d' => 5], 'last_log_row' => ['old_a' => 'three', 'old_b' => 3, 'new_c' => 'three-extra', 'new_d' => 3]],
    ],
    'v2' => [
        'delete' => ['view_row_count' => 7, 'matched_row_count' => 2, 'first_log_row' => ['old_a' => 'five', 'old_b' => 5], 'last_log_row' => ['old_a' => 'three', 'old_b' => 3]],
        'update' => ['view_row_count' => 7, 'matched_row_count' => 2, 'first_log_row' => ['old_a' => 'five', 'old_b' => 5, 'new_c' => 'five-extra', 'new_d' => 5], 'last_log_row' => ['old_a' => 'three', 'old_b' => 3, 'new_c' => 'three-extra', 'new_d' => 3]],
    ],
    'v3' => [
        'delete' => ['view_row_count' => 20, 'matched_row_count' => 3, 'first_log_row' => ['old_a' => '8'], 'last_log_row' => ['old_a' => 'eight']],
        'update' => ['view_row_count' => 20, 'matched_row_count' => 3, 'first_log_row' => ['old_a' => '8', 'new_b' => '8-extra'], 'last_log_row' => ['old_a' => 'eight', 'new_b' => 'eight-extra']],
    ],
    'v4' => [
        'delete' => ['view_row_count' => 13, 'matched_row_count' => 2, 'first_log_row' => ['old_a' => '8'], 'last_log_row' => ['old_a' => '9']],
        'update' => ['view_row_count' => 13, 'matched_row_count' => 2, 'first_log_row' => ['old_a' => '8', 'new_b' => '8-extra'], 'last_log_row' => ['old_a' => '9', 'new_b' => '9-extra']],
    ],
];

for ($seed = 1; $seed <= 100; ++$seed) {
    $expectations['v5']['delete'] = [
        'view_row_count' => 10,
        'matched_row_count' => 3,
        'first_log_row' => ['old_a' => 3, 'old_b' => 305 + $seed],
        'last_log_row' => ['old_a' => 5, 'old_b' => 504 + $seed],
    ];
    $expectations['v5']['update'] = [
        'view_row_count' => 10,
        'matched_row_count' => 3,
        'first_log_row' => ['old_a' => 3, 'old_b' => 305 + $seed, 'new_c' => 3, 'new_d' => 9900305 + $seed],
        'last_log_row' => ['old_a' => 5, 'old_b' => 504 + $seed, 'new_c' => 5, 'new_d' => 9900504 + $seed],
    ];

    foreach ($expectations as $view => $events) {
        foreach ($events as $event => $expected) {
            $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::insteadOfViewWhereRoutingPlan($seed, (string) $view, (string) $event);
            $name = sprintf('real upstream triggerA.test triggerA-2 view where seed %03d %s %s', $seed, $view, $event);

            foreach ([
                'source' => 'triggerA.test triggerA-2.1..2.11',
                'operation' => 'instead-of-view-trigger-where-routing',
                'status' => 'commit-ok',
                'view' => $view,
                'event' => $event,
                'seed' => $seed,
                'view_row_count' => $expected['view_row_count'],
                'matched_row_count' => $expected['matched_row_count'],
                'trigger_log_count' => $expected['matched_row_count'],
                'first_log_row' => $expected['first_log_row'],
                'last_log_row' => $expected['last_log_row'],
                'dependencies.0' => 'sqlite-triggerA-instead-of-trigger-view-where-routing',
                'dependencies.1' => 'sqlite-triggerA-compound-view-materialization-before-trigger',
                'dependencies.2' => 'sqlite-triggerA-join-view-materialization-before-trigger',
            ] as $path => $expectedValue) {
                $tests[$name . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expectedValue, $value): void {
                    $t->same($expectedValue, $value($plan(), (string) $path));
                };
            }
        }
    }
}

$tests['real upstream triggerA malformed view is rejected'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::insteadOfViewWhereRoutingPlan(1, 'bad-view', 'delete')
    );
};

$tests['real upstream triggerA malformed event is rejected'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::insteadOfViewWhereRoutingPlan(1, 'v1', 'insert')
    );
};

return $tests;
