<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$assertAdmission = static function (TestRunner $t, array $target, ?string $where, array $indexes, bool $expected, ?string $expectedIndex = null): void {
    $admission = SQLiteUpsertDoUpdateWherePlan::admitConflictTarget($target, $indexes, $where);
    $t->same($expected, $admission['matched']);
    if ($expectedIndex !== null) {
        $t->same($expectedIndex, $admission['index']);
    }
    if (!$expected) {
        $t->same('ON CONFLICT clause does not match any PRIMARY KEY or UNIQUE constraint', $admission['reason']);
    }
};

$collationIndexes = [
    ['name' => 'app_primary', 'terms' => [['column' => 'setting_id']]],
    ['name' => 'app_compound_nocase', 'terms' => [
        ['column' => 'label', 'collation' => 'nocase'],
        ['column' => 'tenant_id'],
        ['column' => 'scope_name'],
    ]],
];
$collationCases = [
    'upsert4-2 compound reordered nocase target matches' => [
        [['column' => 'label', 'collation' => 'NOCASE'], 'tenant_id', 'scope_name'],
        null,
        true,
        'app_compound_nocase',
    ],
    'upsert4-2 missing target collation still matches inherited index collation' => [
        ['label', 'tenant_id', 'scope_name'],
        null,
        false,
        null,
    ],
    'upsert4-2 wrong middle collation is rejected' => [
        ['label', ['column' => 'tenant_id', 'collation' => 'nocase'], 'scope_name'],
        null,
        false,
        null,
    ],
    'upsert4-2 primary target matches separate unique constraint' => [
        ['setting_id'],
        null,
        true,
        'app_primary',
    ],
    'upsert4-2 partial where on full target is rejected' => [
        ['label', 'tenant_id', 'scope_name'],
        'setting_id!=0',
        false,
        null,
    ],
    'upsert4-2 duplicate expression target is rejected' => [
        ['scope_name', 'tenant_id', 'tenant_id'],
        'setting_id!=0',
        false,
        null,
    ],
    'upsert4-2 both target and index collations must agree' => [
        [
            ['column' => 'label', 'collation' => 'nocase'],
            ['column' => 'tenant_id', 'collation' => 'nocase'],
            'scope_name',
        ],
        null,
        false,
        null,
    ],
];

for ($variant = 0; $variant < 110; ++$variant) {
    foreach ($collationCases as $caseName => [$target, $where, $expected, $expectedIndex]) {
        $label = sprintf('%s dynamic variant %03d', $caseName, $variant);
        $tests[$label . ' admits or rejects the real upstream conflict target'] = static function (TestRunner $t) use ($assertAdmission, $target, $where, $collationIndexes, $expected, $expectedIndex): void {
            $assertAdmission($t, $target, $where, $collationIndexes, $expected, $expectedIndex);
        };
    }
}

$expressionIndexes = [
    ['name' => 'app_expr_nocase', 'terms' => [[
        'expr' => "'x' || key_name",
        'collation' => 'nocase',
    ]]],
];
$expressionCases = [
    'upsert4-3 catch-all expression target follows index expression' => [
        [["expr" => "'x' || key_name", 'collation' => 'nocase']],
        true,
    ],
    'upsert4-3 redundant parentheses preserve expression target' => [
        [["expr" => "((('x' || key_name)))", 'collation' => 'nocase']],
        true,
    ],
    'upsert4-3 binary collation does not match nocase expression index' => [
        [["expr" => "('x' || key_name)", 'collation' => 'binary']],
        false,
    ],
    'upsert4-3 reversed concatenation expression is a distinct index term' => [
        [["expr" => "key_name || 'x'", 'collation' => 'nocase']],
        false,
    ],
];

for ($variant = 0; $variant < 120; ++$variant) {
    foreach ($expressionCases as $caseName => [$target, $expected]) {
        $label = sprintf('%s dynamic variant %03d', $caseName, $variant);
        $tests[$label . ' compares normalized expression index terms'] = static function (TestRunner $t) use ($assertAdmission, $target, $expressionIndexes, $expected): void {
            $assertAdmission($t, $target, null, $expressionIndexes, $expected, $expected ? 'app_expr_nocase' : null);
        };
    }
}

$partialIndexes = [
    ['name' => 'app_key_active', 'terms' => [['column' => 'key_name']], 'where' => 'load_policy>0'],
    ['name' => 'app_tenant_nocase', 'terms' => [['column' => 'tenant_id']], 'where' => "key_name='site' COLLATE nocase"],
];
$partialCases = [
    'upsert4-4 exact partial predicate matches key index' => [
        ['key_name'],
        'load_policy > 0',
        true,
        'app_key_active',
    ],
    'upsert4-4 omitted partial predicate is not enough for partial key index' => [
        ['key_name'],
        null,
        false,
        null,
    ],
    'upsert4-4 widened partial predicate is rejected' => [
        ['key_name'],
        'load_policy >= 0',
        false,
        null,
    ],
    'upsert4-4 exact nocase partial predicate matches tenant index' => [
        ['tenant_id'],
        "key_name='site' COLLATE nocase",
        true,
        'app_tenant_nocase',
    ],
    'upsert4-4 binary partial predicate is a different index predicate' => [
        ['tenant_id'],
        "key_name='site' COLLATE binary",
        false,
        null,
    ],
];

for ($variant = 0; $variant < 120; ++$variant) {
    foreach ($partialCases as $caseName => [$target, $where, $expected, $expectedIndex]) {
        $label = sprintf('%s dynamic variant %03d', $caseName, $variant);
        $tests[$label . ' preserves partial index predicate identity'] = static function (TestRunner $t) use ($assertAdmission, $target, $where, $partialIndexes, $expected, $expectedIndex): void {
            $assertAdmission($t, $target, $where, $partialIndexes, $expected, $expectedIndex);
        };
    }
}

$replaceRows = static fn (): array => [
    ['setting_id' => 1, 'tenant_id' => 1, 'rank_value' => 1, 'payload' => 'one'],
    ['setting_id' => 2, 'tenant_id' => 2, 'rank_value' => 2, 'payload' => 'two'],
];
$identity = static fn (string $column): callable => static fn (array $current, array $incoming): mixed => $incoming[$column];

for ($variant = 0; $variant < 150; ++$variant) {
    $incoming = [[
        'setting_id' => 3 + $variant,
        'tenant_id' => 1,
        'rank_value' => 1,
        'payload' => 'replacement-' . $variant,
    ]];
    $doNothing = static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace(
        $replaceRows(),
        $incoming,
        [['target' => ['tenant_id'], 'action' => 'nothing']],
        [['setting_id'], ['tenant_id'], ['rank_value']],
    );
    $doUpdateTenant = static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace(
        $replaceRows(),
        $incoming,
        [[
            'target' => ['tenant_id'],
            'assignments' => ['payload' => $identity('payload')],
        ]],
        [['setting_id'], ['tenant_id'], ['rank_value']],
    );
    $doUpdateRank = static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace(
        $replaceRows(),
        $incoming,
        [[
            'target' => ['rank_value'],
            'assignments' => ['payload' => $identity('payload')],
        ]],
        [['setting_id'], ['tenant_id'], ['rank_value']],
    );

    $label = sprintf('upsert4-6 insert-or-replace precedence dynamic variant %03d ', $variant);
    $tests[$label . 'handles ON CONFLICT DO NOTHING before replace conflict deletion'] = static function (TestRunner $t) use ($doNothing): void {
        $result = $doNothing();
        $t->same(0, $result['changes']);
        $t->same(['one', 'two'], array_column($result['after'], 'payload'));
    };
    $tests[$label . 'updates the tenant conflict row before considering replace deletion'] = static function (TestRunner $t) use ($doUpdateTenant, $variant): void {
        $result = $doUpdateTenant();
        $t->same(1, $result['changes']);
        $t->same('replacement-' . $variant, $result['after'][0]['payload']);
        $t->same([1], array_column($result['returning_rows'], 'setting_id'));
    };
    $tests[$label . 'updates the rank conflict row when that arm is selected first'] = static function (TestRunner $t) use ($doUpdateRank, $variant): void {
        $result = $doUpdateRank();
        $t->same(1, $result['changes']);
        $t->same('replacement-' . $variant, $result['after'][0]['payload']);
        $t->same([1], array_column($result['returning_rows'], 'setting_id'));
    };
}

return $tests;
