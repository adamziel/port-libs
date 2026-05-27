<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteIndexSkipScanPlan;

$rows = static fn (): array => [
    ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'admin_email', 'option_value' => 'owner@example.test', 'kind' => 'core'],
    ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'option_value' => 'A', 'kind' => 'plugin'],
    ['rowid' => 3, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'option_value' => 'B', 'kind' => 'plugin'],
    ['rowid' => 4, 'autoload' => 'auto', 'option_name' => 'theme_mods_default', 'option_value' => 'T', 'kind' => 'theme'],
    ['rowid' => 5, 'autoload' => 'no', 'option_name' => '_transient_alpha', 'option_value' => 'ta', 'kind' => 'transient'],
    ['rowid' => 6, 'autoload' => 'no', 'option_name' => 'plugin_alpha', 'option_value' => 'NA', 'kind' => 'plugin'],
    ['rowid' => 7, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'option_value' => 'NG', 'kind' => 'plugin'],
    ['rowid' => 8, 'autoload' => 'no', 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'kind' => 'core'],
    ['rowid' => 9, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'missing', 'kind' => 'plugin'],
    ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'blogname', 'option_value' => 'Example', 'kind' => 'core'],
    ['rowid' => 11, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'YA', 'kind' => 'plugin'],
    ['rowid' => 12, 'autoload' => 'yes', 'option_name' => 'plugin_delta', 'option_value' => 'YD', 'kind' => 'plugin'],
    ['rowid' => 13, 'autoload' => 'yes', 'option_name' => 'theme_mods_child', 'option_value' => 'TC', 'kind' => 'theme'],
    ['rowid' => 14, 'autoload' => 'lazy', 'option_name' => 'Plugin_Epsilon', 'option_value' => 'LE', 'kind' => 'plugin'],
    ['rowid' => 15, 'autoload' => 'lazy', 'option_name' => 'plugin_zeta  ', 'option_value' => 'LZ', 'kind' => 'plugin'],
    ['rowid' => 16, 'autoload' => 'lazy', 'option_name' => 'widget_recent-posts', 'option_value' => 'W', 'kind' => 'widget'],
];

$column = static fn (string $name): array => ['column' => $name];
$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];

$pluginPartial = new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL, 'plugin_');
$pluginKindPartial = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL, 'plugin_'),
]);
$pluginOrThemePartial = new SQLiteIndexPredicate('', SQLiteIndexPredicate::OR, [
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL, 'plugin_'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::BETWEEN, ['lower' => 'theme_', 'upper' => 'theme_\uffff']),
]);

$plan = static function (
    SQLiteIndexPredicate $partial,
    array $terms,
    mixed $lower = 'plugin_',
    mixed $upper = 'plugin_zzzz',
    bool $upperInclusive = true,
    ?int $limit = null,
    int $offset = 0,
    string $collation = 'BINARY',
) use ($rows): array {
    return SQLiteIndexSkipScanPlan::betweenPartialRows(
        $rows(),
        'idx_autoload_option_partial',
        'autoload',
        'option_name',
        $lower,
        $upper,
        $partial,
        $terms,
        $upperInclusive,
        $limit,
        $offset,
        $collation,
    );
};

$tests = [
    'planner skipscan partial current next28 proves lower-bound partial predicate' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')]);
        $t->same('usable', $p['status']);
    },
    'planner skipscan partial current next28 marks plan as partial' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')]);
        $t->same(true, $p['partial']);
    },
    'planner skipscan partial current next28 records implied predicate evidence' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')]);
        $t->same(true, $p['partialPredicateImplied']);
    },
    'planner skipscan partial current next28 returns only partial-index plugin rows' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')]);
        $t->same([2, 3, 15, 6, 7, 11, 12], $p['rowids']);
    },
    'planner skipscan partial current next28 omits rows outside partial index image' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')]);
        $t->same(5, $p['skippedPartialRows']);
    },
    'planner skipscan partial current next28 keeps one loop per partial leading value' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')]);
        $t->same(['auto', 'lazy', 'no', 'yes'], array_column($p['loops'], 'prefix'));
    },
    'planner skipscan partial current next28 reports skip scan seeks' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')]);
        $t->same(4, $p['estimatedSeeks']);
    },
    'planner skipscan partial current next28 uses skip scan with multiple partial prefixes' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')]);
        $t->same(true, $p['usesSkipScan']);
    },
    'planner skipscan partial current next28 counts matched rows by prefix' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')]);
        $t->same([2, 1, 2, 2], array_column($p['loops'], 'matched'));
    },
    'planner skipscan partial current next28 records auto prefix rowids' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $loops = array_column($plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')])['loops'], null, 'prefix');
        $t->same([2, 3], $loops['auto']['rowids']);
    },
    'planner skipscan partial current next28 records no prefix rowids' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $loops = array_column($plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')])['loops'], null, 'prefix');
        $t->same([6, 7], $loops['no']['rowids']);
    },
    'planner skipscan partial current next28 records yes prefix rowids' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $loops = array_column($plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')])['loops'], null, 'prefix');
        $t->same([11, 12], $loops['yes']['rowids']);
    },
    'planner skipscan partial current next28 applies limit after current next loop order' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')], 'plugin_', 'plugin_zzzz', true, 3);
        $t->same([2, 3, 15], $p['rowids']);
    },
    'planner skipscan partial current next28 applies offset before limit' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')], 'plugin_', 'plugin_zzzz', true, 3, 3);
        $t->same([6, 7, 11], $p['rowids']);
    },
    'planner skipscan partial current next28 returns empty rows for zero limit but keeps loops' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')], 'plugin_', 'plugin_zzzz', true, 0);
        $t->same([], $p['rowids']);
    },
    'planner skipscan partial current next28 retains loop matches for zero limit' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')], 'plugin_', 'plugin_zzzz', true, 0);
        $t->same([2, 1, 2, 2], array_column($p['loops'], 'matched'));
    },
    'planner skipscan partial current next28 supports exclusive upper bound' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')], 'plugin_', 'plugin_delta', false);
        $t->same([2, 3, 6, 11], $p['rowids']);
    },
    'planner skipscan partial current next28 supports inclusive upper bound' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')], 'plugin_', 'plugin_delta', true);
        $t->same([2, 3, 6, 11, 12], $p['rowids']);
    },
    'planner skipscan partial current next28 rejects unsafe broad query partial proof' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'option_')]);
        $t->same('unusable', $p['status']);
    },
    'planner skipscan partial current next28 reports unusable proof reason' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'option_')]);
        $t->same('query constraints do not imply partial-index WHERE predicate', $p['reason']);
    },
    'planner skipscan partial current next28 leaves unusable plan without loops' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'option_')]);
        $t->same([], $p['loops']);
    },
    'planner skipscan partial current next28 leaves unusable plan without rowids' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'option_')]);
        $t->same([], $p['rowids']);
    },
    'planner skipscan partial current next28 proves between query against partial lower bound' => static function (TestRunner $t) use ($plan, $pluginPartial, $between): void {
        $p = $plan($pluginPartial, [$between('option_name', 'plugin_alpha', 'plugin_gamma')], 'plugin_alpha', 'plugin_gamma');
        $t->same([2, 3, 6, 7, 11, 12], $p['rowids']);
    },
    'planner skipscan partial current next28 rejects between query below partial lower bound' => static function (TestRunner $t) use ($plan, $pluginPartial, $between): void {
        $p = $plan($pluginPartial, [$between('option_name', 'admin_', 'plugin_gamma')], 'admin_', 'plugin_gamma');
        $t->same('unusable', $p['status']);
    },
    'planner skipscan partial current next28 proves in-list when every value satisfies partial' => static function (TestRunner $t) use ($plan, $pluginPartial, $column): void {
        $p = $plan($pluginPartial, [['operator' => 'IN', 'left' => $column('option_name'), 'values' => ['plugin_alpha', 'plugin_beta']]], 'plugin_', 'plugin_zzzz');
        $t->same('usable', $p['status']);
    },
    'planner skipscan partial current next28 rejects in-list with outside value' => static function (TestRunner $t) use ($plan, $pluginPartial, $column): void {
        $p = $plan($pluginPartial, [['operator' => 'IN', 'left' => $column('option_name'), 'values' => ['plugin_alpha', 'admin_email']]], 'plugin_', 'plugin_zzzz');
        $t->same('unusable', $p['status']);
    },
    'planner skipscan partial current next28 proves and-connected kind plus name partial' => static function (TestRunner $t) use ($plan, $pluginKindPartial, $point, $range): void {
        $p = $plan($pluginKindPartial, [$point('kind', 'plugin'), $range('option_name', '>=', 'plugin_alpha')]);
        $t->same('usable', $p['status']);
    },
    'planner skipscan partial current next28 filters and-connected partial rows by kind' => static function (TestRunner $t) use ($plan, $pluginKindPartial, $point, $range): void {
        $p = $plan($pluginKindPartial, [$point('kind', 'plugin'), $range('option_name', '>=', 'plugin_alpha')]);
        $t->same([2, 3, 15, 6, 7, 11, 12], $p['rowids']);
    },
    'planner skipscan partial current next28 rejects and partial missing equality term' => static function (TestRunner $t) use ($plan, $pluginKindPartial, $range): void {
        $p = $plan($pluginKindPartial, [$range('option_name', '>=', 'plugin_alpha')]);
        $t->same('unusable', $p['status']);
    },
    'planner skipscan partial current next28 rejects and partial missing range proof' => static function (TestRunner $t) use ($plan, $pluginKindPartial, $point, $range): void {
        $p = $plan($pluginKindPartial, [$point('kind', 'plugin'), $range('option_name', '>=', 'option_')]);
        $t->same('unusable', $p['status']);
    },
    'planner skipscan partial current next28 proves or partial through plugin arm' => static function (TestRunner $t) use ($plan, $pluginOrThemePartial, $range): void {
        $p = $plan($pluginOrThemePartial, [$range('option_name', '>=', 'plugin_alpha')]);
        $t->same('usable', $p['status']);
    },
    'planner skipscan partial current next28 proves or partial through theme between arm' => static function (TestRunner $t) use ($plan, $pluginOrThemePartial, $between): void {
        $p = $plan($pluginOrThemePartial, [$between('option_name', 'theme_mods_', 'theme_mods_z')], 'theme_', 'theme_z');
        $t->same([4, 13], $p['rowids']);
    },
    'planner skipscan partial current next28 rejects or partial when neither arm is implied' => static function (TestRunner $t) use ($plan, $pluginOrThemePartial, $range): void {
        $p = $plan($pluginOrThemePartial, [$range('option_name', '<', 'admin_')], null, 'admin_');
        $t->same('unusable', $p['status']);
    },
    'planner skipscan partial current next28 supports nocase partial proof' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'PLUGIN_ALPHA')], 'PLUGIN_', 'PLUGIN_ZZZZ', true, null, 0, 'NOCASE');
        $t->same('usable', $p['status']);
    },
    'planner skipscan partial current next28 returns nocase plugin rows' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'PLUGIN_ALPHA')], 'PLUGIN_', 'PLUGIN_ZZZZ', true, null, 0, 'NOCASE');
        $t->same([2, 3, 14, 15, 6, 7, 11, 12], $p['rowids']);
    },
    'planner skipscan partial current next28 keeps binary proof case sensitive' => static function (TestRunner $t) use ($plan, $pluginPartial, $range): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'PLUGIN_ALPHA')], 'PLUGIN_', 'PLUGIN_ZZZZ');
        $t->same('unusable', $p['status']);
    },
    'planner skipscan partial current next28 supports rtrim row filtering' => static function (TestRunner $t) use ($plan, $point): void {
        $partial = new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::EQUALS, 'plugin_zeta');
        $p = $plan($partial, [$point('option_name', 'plugin_zeta  ')], 'plugin_zeta', 'plugin_zeta', true, null, 0, 'RTRIM');
        $t->same([15], $p['rowids']);
    },
    'planner skipscan partial current next28 preserves omitted null range count after partial filter' => static function (TestRunner $t) use ($plan, $column): void {
        $partial = new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin');
        $p = $plan($partial, [['operator' => 'IN', 'left' => $column('kind'), 'values' => ['plugin']]], 'plugin_', 'plugin_zzzz');
        $t->same(1, $p['omittedNullRangeRows']);
    },
    'planner skipscan partial current next28 can be single-prefix without skip scan flag' => static function (TestRunner $t): void {
        $partial = new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin');
        $p = SQLiteIndexSkipScanPlan::betweenPartialRows(
            [
                ['rowid' => 21, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'kind' => 'plugin'],
                ['rowid' => 22, 'autoload' => 'yes', 'option_name' => 'plugin_beta', 'kind' => 'plugin'],
                ['rowid' => 23, 'autoload' => 'yes', 'option_name' => 'siteurl', 'kind' => 'core'],
            ],
            'idx_single_prefix_partial',
            'autoload',
            'option_name',
            'plugin_',
            'plugin_z',
            $partial,
            [['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin']],
        );
        $t->same(false, $p['usesSkipScan']);
    },
    'planner skipscan partial current next28 single-prefix still returns matching rows' => static function (TestRunner $t): void {
        $partial = new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin');
        $p = SQLiteIndexSkipScanPlan::betweenPartialRows(
            [
                ['rowid' => 21, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'kind' => 'plugin'],
                ['rowid' => 22, 'autoload' => 'yes', 'option_name' => 'plugin_beta', 'kind' => 'plugin'],
                ['rowid' => 23, 'autoload' => 'yes', 'option_name' => 'siteurl', 'kind' => 'core'],
            ],
            'idx_single_prefix_partial',
            'autoload',
            'option_name',
            'plugin_',
            'plugin_z',
            $partial,
            [['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin']],
        );
        $t->same([21, 22], $p['rowids']);
    },
];

$rangeCases = [
    'plugin alpha beta' => ['plugin_alpha', 'plugin_beta', true, [2, 3, 6, 11]],
    'plugin gamma high' => ['plugin_gamma', 'plugin_zzzz', true, [15, 7]],
    'open lower plugin beta' => [null, 'plugin_beta', true, [2, 3, 6, 11]],
    'open upper plugin gamma' => ['plugin_gamma', null, true, [4, 15, 16, 7, 8, 13]],
    'exclusive plugin gamma' => ['plugin_', 'plugin_gamma', false, [2, 3, 6, 11, 12]],
    'empty high band' => ['zzzz', null, true, []],
];

foreach ($rangeCases as $label => [$lower, $upper, $inclusive, $expected]) {
    $tests["planner skipscan partial current next28 range case {$label}"] = static function (TestRunner $t) use ($plan, $pluginPartial, $range, $lower, $upper, $inclusive, $expected): void {
        $p = $plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')], $lower, $upper, $inclusive);
        $t->same($expected, $p['rowids']);
    };
}

$prefixCases = [
    'auto' => [2, [2, 3]],
    'lazy' => [1, [15]],
    'no' => [2, [6, 7]],
    'yes' => [2, [11, 12]],
];

foreach ($prefixCases as $prefix => [$matched, $rowids]) {
    $tests["planner skipscan partial current next28 prefix {$prefix} matched count"] = static function (TestRunner $t) use ($plan, $pluginPartial, $range, $prefix, $matched): void {
        $loops = array_column($plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')])['loops'], null, 'prefix');
        $t->same($matched, $loops[$prefix]['matched']);
    };
    $tests["planner skipscan partial current next28 prefix {$prefix} rowids"] = static function (TestRunner $t) use ($plan, $pluginPartial, $range, $prefix, $rowids): void {
        $loops = array_column($plan($pluginPartial, [$range('option_name', '>=', 'plugin_alpha')])['loops'], null, 'prefix');
        $t->same($rowids, $loops[$prefix]['rowids']);
    };
}

return $tests;
