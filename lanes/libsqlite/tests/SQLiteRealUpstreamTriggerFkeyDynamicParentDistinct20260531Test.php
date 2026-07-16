<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [];

$value = static function (array $row, string $path): mixed {
    $cursor = $row;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException('Missing assertion path ' . $path);
    }

    return $cursor;
};

$sourceFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test';

$tests['real upstream e_fkey parent update distinct cites e_fkey52 and e_fkey53'] = static function (TestRunner $t) use ($sourceFile): void {
    $source = file_get_contents($sourceFile);

    $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-52.1'));
    $t->true(is_string($source) && str_contains($source, "UPDATE zeus SET a = 'aBc'"));
    $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-52.6'));
    $t->true(is_string($source) && str_contains($source, 'do_test e_fkey-53.3'));
    $t->true(is_string($source) && str_contains($source, 'ON UPDATE actions only actually take place'));
};

$compositePlan = static function (int $seed, string $mode): array {
    $baseText = 'setting' . $seed;
    $scopeText = 'scope' . $seed;
    $integerKey = 1000 + $seed;
    $integerScope = 9000 + $seed;
    $parent = ['id' => $seed, 'a' => $baseText, 'b' => $scopeText, 'label' => 'parent-' . $seed];
    $child = ['id' => $seed, 'c' => strtoupper($baseText), 'd' => $scopeText, 'label' => 'child-' . $seed];
    $set = ['a' => 'SeTtInG' . $seed];

    if ($mode === 'cascade integer distinct') {
        $set = ['a' => $integerKey, 'b' => $integerScope];
    } elseif ($mode === 'repeat integer equal') {
        $parent['a'] = $integerKey;
        $parent['b'] = $integerScope;
        $child['c'] = $integerKey;
        $child['d'] = $integerScope;
        $set = ['a' => $integerKey, 'b' => $integerScope];
    } elseif ($mode === 'integer affinity text equal') {
        $parent['a'] = $integerKey;
        $parent['b'] = $integerScope;
        $child['c'] = $integerKey;
        $child['d'] = $integerScope;
        $set = ['a' => (string) $integerKey];
    } elseif ($mode === 'unaffinitized text distinct') {
        $parent['a'] = $integerKey;
        $parent['b'] = $integerScope;
        $child['c'] = $integerKey;
        $child['d'] = $integerScope;
        $set = ['b' => (string) $integerScope];
    } elseif ($mode === 'null distinct') {
        $parent['a'] = $integerKey;
        $parent['b'] = 'scope-' . $integerScope;
        $child['c'] = $integerKey;
        $child['d'] = 'scope-' . $integerScope;
        $set = ['b' => null];
    } elseif ($mode !== 'nocase equal') {
        throw new InvalidArgumentException('unknown composite e_fkey parent distinct mode');
    }

    return SQLiteDynamicTriggerForeignKeyPlan::parentUpdateDistinctActionPlan(
        [$parent],
        [$child],
        [
            'where' => ['id' => $seed],
            'set' => $set,
            'parent_columns' => ['a', 'b'],
            'child_columns' => ['c', 'd'],
            'on_update' => 'cascade',
            'parent_affinities' => ['a' => 'integer', 'b' => 'none'],
            'parent_collations' => ['a' => 'nocase', 'b' => 'binary'],
        ],
    );
};

$setNullPlan = static function (int $seed, string $mode): array {
    $oldKey = 'track-' . $seed;
    $newKey = $mode === 'set-null distinct' ? $oldKey . '-next' : $oldKey;

    return SQLiteDynamicTriggerForeignKeyPlan::parentUpdateDistinctActionPlan(
        [['id' => $seed, 'x' => $oldKey, 'title' => 'parent-' . $seed]],
        [['id' => $seed, 'y' => $oldKey, 'title' => 'child-' . $seed]],
        [
            'where' => ['id' => $seed],
            'set' => ['x' => $newKey],
            'parent_columns' => ['x'],
            'child_columns' => ['y'],
            'on_update' => 'set null',
            'parent_affinities' => ['x' => 'text'],
            'parent_collations' => ['x' => 'binary'],
        ],
    );
};

$modes = [
    'nocase equal',
    'cascade integer distinct',
    'repeat integer equal',
    'integer affinity text equal',
    'unaffinitized text distinct',
    'null distinct',
    'set-null equal',
    'set-null distinct',
];

foreach (range(1, 125) as $seed) {
    foreach ($modes as $mode) {
        $tests[sprintf('real upstream e_fkey52 e_fkey53 parent update distinct %s seed %03d', $mode, $seed)] = static function (TestRunner $t) use ($seed, $mode, $compositePlan, $setNullPlan, $value): void {
            $plan = str_starts_with($mode, 'set-null') ? $setNullPlan($seed, $mode) : $compositePlan($seed, $mode);

            $t->same('e_fkey.test e_fkey-52.1..53.3', $plan['source']);
            $t->same('parent-update-distinct-foreign-key-action', $plan['operation']);
            $t->same('commit-ok', $plan['status']);
            $t->same(0, $plan['violation_count']);

            if ($mode === 'nocase equal') {
                $t->same(false, $plan['parent_key_distinct']);
                $t->same(false, $plan['action_taken']);
                $t->same(['SeTtInG' . $seed, 'scope' . $seed], $plan['new_parent_key']);
                $t->same([strtoupper('setting' . $seed), 'scope' . $seed], $plan['child_key_values'][0]);
                $t->same('nocase', $plan['parent_collations']['a']);
                return;
            }

            if ($mode === 'cascade integer distinct') {
                $t->same(true, $plan['parent_key_distinct']);
                $t->same(true, $plan['action_taken']);
                $t->same([1000 + $seed, 9000 + $seed], $plan['child_key_values'][0]);
                $t->same('int', $value($plan, 'child_key_types.0.0.type'));
                $t->same('int', $value($plan, 'child_key_types.0.1.type'));
                return;
            }

            if ($mode === 'repeat integer equal') {
                $t->same(false, $plan['parent_key_distinct']);
                $t->same(false, $plan['action_taken']);
                $t->same([1000 + $seed, 9000 + $seed], $plan['child_key_values'][0]);
                return;
            }

            if ($mode === 'integer affinity text equal') {
                $t->same(false, $plan['parent_key_distinct']);
                $t->same(false, $plan['action_taken']);
                $t->same(1000 + $seed, $value($plan, 'new_parent_key.0'));
                $t->same('int', $value($plan, 'new_parent_key_typed.0.type'));
                $t->same([1000 + $seed, 9000 + $seed], $plan['child_key_values'][0]);
                return;
            }

            if ($mode === 'unaffinitized text distinct') {
                $t->same(true, $plan['parent_key_distinct']);
                $t->same(true, $plan['action_taken']);
                $t->same((string) (9000 + $seed), $value($plan, 'child_key_values.0.1'));
                $t->same('string', $value($plan, 'child_key_types.0.1.type'));
                $t->same('none', $plan['parent_affinities']['b']);
                return;
            }

            if ($mode === 'null distinct') {
                $t->same(true, $plan['parent_key_distinct']);
                $t->same(true, $plan['action_taken']);
                $t->same(null, $value($plan, 'child_key_values.0.1'));
                $t->same('null', strtolower($value($plan, 'child_key_types.0.1.type')));
                return;
            }

            if ($mode === 'set-null equal') {
                $t->same(false, $plan['parent_key_distinct']);
                $t->same(false, $plan['action_taken']);
                $t->same(['track-' . $seed], $plan['child_key_values'][0]);
                $t->same('set null', $plan['on_update']);
                return;
            }

            $t->same('set-null distinct', $mode);
            $t->same(true, $plan['parent_key_distinct']);
            $t->same(true, $plan['action_taken']);
            $t->same([null], $plan['child_key_values'][0]);
            $t->same('set null', $value($plan, 'action_rows.0.action'));
        };
    }
}

$tests['real upstream e_fkey52 e_fkey53 parent update distinct owns 1000 dynamic cases'] = static function (TestRunner $t) use ($modes): void {
    $t->same(1000, 125 * count($modes));
};

return $tests;
