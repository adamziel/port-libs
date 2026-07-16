<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonbPatchGeneratedIndexPlan;

$tests = [];

$generatedColumns = [
    "plugin_enabled INTEGER GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{plugin:{enabled:true,source:\"wp\"}}'), '$.plugin.enabled')) VIRTUAL",
    "plugin_channel TEXT GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{plugin:{channel:\"stable\"}}'), '$.plugin.channel')) STORED",
    "plugin_priority INTEGER GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{plugin:{priority:10}}'), '$.plugin.priority')) VIRTUAL",
];

$indexes = [
    [
        'name' => 'idx_patch_enabled_autoload',
        'rootPage' => 44,
        'estimatedRows' => 200,
        'coveringColumns' => ['option_name', 'autoload', 'plugin_enabled'],
        'sql' => "CREATE INDEX idx_patch_enabled_autoload ON wp_options(plugin_enabled) WHERE autoload='yes'",
    ],
    [
        'name' => 'idx_patch_channel_order',
        'rootPage' => 45,
        'estimatedRows' => 80,
        'coveringColumns' => ['option_name', 'autoload', 'plugin_channel'],
        'sql' => 'CREATE INDEX idx_patch_channel_order ON wp_options(plugin_channel DESC)',
    ],
    [
        'name' => 'idx_patch_priority_expr',
        'rootPage' => 46,
        'estimatedRows' => 120,
        'coveringColumns' => ['option_name', 'option_value'],
        'sql' => "CREATE INDEX idx_patch_priority_expr ON wp_options(json_extract(jsonb_patch(option_value, '{plugin:{priority:10}}'), '$.plugin.priority')) WHERE autoload='no'",
    ],
    [
        'name' => 'idx_patch_priority_wrong_patch',
        'rootPage' => 47,
        'estimatedRows' => 20,
        'coveringColumns' => ['option_name'],
        'sql' => "CREATE INDEX idx_patch_priority_wrong_patch ON wp_options(json_extract(jsonb_patch(option_value, '{plugin:{priority:5}}'), '$.plugin.priority')) WHERE autoload='no'",
    ],
];

$column = static fn (string $name): array => ['column' => $name];
$generated = static fn (string $name): array => ['generatedColumn' => $name];
$patchExpression = static fn (string $patchJson = '{plugin:{priority:10}}', string $path = '$.plugin.priority'): array => [
    'function' => 'json_extract_jsonb_patch',
    'sourceColumn' => 'option_value',
    'patchJson' => $patchJson,
    'path' => $path,
];

$cases = [
    'generated equality uses autoload-proven partial index' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $generated('plugin_enabled'), 'right' => 1],
                ['operator' => '=', 'left' => $column('autoload'), 'right' => 'yes'],
            ],
        ],
        ['option_name', 'autoload'],
        [],
        'idx_patch_enabled_autoload',
        false,
        true,
    ],
    'generated equality rejects missing partial proof' => [
        ['operator' => '=', 'left' => $generated('plugin_enabled'), 'right' => 1],
        ['option_name'],
        [],
        null,
        null,
        null,
    ],
    'generated equality rejects mismatched partial proof' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $generated('plugin_enabled'), 'right' => 1],
                ['operator' => '=', 'left' => $column('autoload'), 'right' => 'no'],
            ],
        ],
        ['option_name'],
        [],
        null,
        null,
        null,
    ],
    'generated IN lookup keeps generated index usable' => [
        ['operator' => 'IN', 'left' => $generated('plugin_channel'), 'values' => ['stable', 'beta']],
        ['option_name', 'plugin_channel'],
        [['column' => 'plugin_channel', 'direction' => 'DESC']],
        'idx_patch_channel_order',
        true,
        true,
    ],
    'generated range lookup honors order direction' => [
        ['operator' => '>=', 'left' => $generated('plugin_channel'), 'right' => 'm'],
        ['option_name'],
        [['column' => 'plugin_channel', 'direction' => 'ASC']],
        'idx_patch_channel_order',
        false,
        true,
    ],
    'expression equality uses matching jsonb patch expression index' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $patchExpression(), 'right' => 10],
                ['operator' => '=', 'left' => $column('autoload'), 'right' => 'no'],
            ],
        ],
        ['option_name'],
        [],
        'idx_patch_priority_expr',
        false,
        true,
    ],
    'expression equality canonicalizes json5 patch text' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $patchExpression('{plugin:{priority:10,},}'), 'right' => 10],
                ['operator' => '=', 'left' => $column('autoload'), 'right' => 'no'],
            ],
        ],
        ['option_name'],
        [],
        'idx_patch_priority_expr',
        false,
        true,
    ],
    'expression range accepts reversed operand' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '<=', 'left' => 20, 'right' => $patchExpression()],
                ['operator' => '=', 'left' => $column('autoload'), 'right' => 'no'],
            ],
        ],
        ['option_name'],
        [],
        'idx_patch_priority_expr',
        false,
        true,
    ],
    'expression rejects different merge patch payload' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $patchExpression('{plugin:{priority:11}}'), 'right' => 11],
                ['operator' => '=', 'left' => $column('autoload'), 'right' => 'no'],
            ],
        ],
        ['option_name'],
        [],
        null,
        null,
        null,
    ],
    'expression rejects different json path' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $patchExpression('{plugin:{priority:10}}', '$.plugin.enabled'), 'right' => 1],
                ['operator' => '=', 'left' => $column('autoload'), 'right' => 'no'],
            ],
        ],
        ['option_name'],
        [],
        null,
        null,
        null,
    ],
    'expression rejects non scalar search value' => [
        [
            'operator' => 'AND',
            'terms' => [
                ['operator' => '=', 'left' => $patchExpression(), 'right' => ['bad']],
                ['operator' => '=', 'left' => $column('autoload'), 'right' => 'no'],
            ],
        ],
        ['option_name'],
        [],
        null,
        null,
        null,
    ],
];

foreach ($cases as $name => [$predicate, $neededColumns, $orderBy, $expectedName, $expectedOrderSatisfied, $expectedCovering]) {
    $tests['jsonb patch indexed generated current next27 plan ' . $name] = static function (TestRunner $t) use ($indexes, $generatedColumns, $predicate, $neededColumns, $orderBy, $expectedName, $expectedOrderSatisfied, $expectedCovering): void {
        $plan = SQLiteJsonbPatchGeneratedIndexPlan::choose($indexes, $generatedColumns, $predicate, $orderBy, $neededColumns);
        $t->same($expectedName, $plan['name'] ?? null);
        if ($expectedName !== null) {
            $t->same(true, $plan['usable']);
            $t->same(true, $plan['residualPredicateRequired']);
            $t->same($expectedOrderSatisfied, $plan['orderBySatisfied']);
            $t->same($expectedCovering, $plan['covering']);
            $t->same(true, $plan['estimatedRows'] > 0);
        }
    };
}

$tests['jsonb patch indexed generated current next27 ranks lowest usable generated plan'] = static function (TestRunner $t) use ($indexes, $generatedColumns, $generated): void {
    $plans = SQLiteJsonbPatchGeneratedIndexPlan::rankedPlans(
        $indexes,
        $generatedColumns,
        ['operator' => 'IN', 'left' => $generated('plugin_channel'), 'values' => ['stable', 'beta', null]],
        [['column' => 'plugin_channel', 'direction' => 'DESC']],
        ['option_name', 'autoload'],
    );

    $t->same('idx_patch_channel_order', $plans[0]['name']);
    $t->same(['stable', 'beta', null], $plans[0]['values']);
    $t->same(16, $plans[0]['estimatedRows']);
    $t->same(16, $plans[0]['estimatedCost']);
    $t->same('$.plugin.channel', $plans[0]['path']);
    $t->same('{"plugin":{"channel":"stable"}}', $plans[0]['patchJson']);
};

$tests['jsonb patch indexed generated current next27 applies same merge patch to copied option payloads'] = static function (TestRunner $t): void {
    $settings = new SQLiteBlobValue(SQLiteJsonB::encode([
        'plugin' => [
            'enabled' => false,
            'priority' => 2,
            'rules' => [
                ['name' => 'cache', 'enabled' => false],
            ],
        ],
    ]));
    $patch = "{plugin:{enabled:true,priority:10,rules:[{name:'cache',enabled:true},{name:'seo',enabled:true}]}}";
    $patched = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $settings, $patch);

    $t->same(true, $patched instanceof SQLiteBlobValue);
    $decoded = SQLiteJsonB::decode($patched->bytes);
    $t->same(true, $decoded['plugin']['enabled']);
    $t->same(10, $decoded['plugin']['priority']);
    $t->same('seo', $decoded['plugin']['rules'][1]['name']);
    $t->same(true, $decoded['plugin']['rules'][1]['enabled']);
};

$tests['jsonb patch indexed generated current next27 rejects malformed generated expression patch json'] = static function (TestRunner $t) use ($indexes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonbPatchGeneratedIndexPlan::choose(
        $indexes,
        ["bad_value TEXT GENERATED ALWAYS AS (json_extract(jsonb_patch(option_value, '{bad:'), '$.bad')) VIRTUAL"],
        ['operator' => '=', 'left' => ['generatedColumn' => 'bad_value'], 'right' => 1],
    ));
};

return $tests;
