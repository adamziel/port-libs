<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteSkipScanStat4PartialOrderPlan;

$rows = static fn (): array => [
    ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'admin_email', 'kind' => 'core'],
    ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'kind' => 'plugin'],
    ['rowid' => 3, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'kind' => 'plugin'],
    ['rowid' => 4, 'autoload' => 'auto', 'option_name' => 'theme_mods_default', 'kind' => 'theme'],
    ['rowid' => 5, 'autoload' => 'lazy', 'option_name' => 'Plugin_Epsilon', 'kind' => 'plugin'],
    ['rowid' => 6, 'autoload' => 'lazy', 'option_name' => 'plugin_zeta', 'kind' => 'plugin'],
    ['rowid' => 7, 'autoload' => 'lazy', 'option_name' => 'widget_recent-posts', 'kind' => 'widget'],
    ['rowid' => 8, 'autoload' => 'no', 'option_name' => '_transient_alpha', 'kind' => 'transient'],
    ['rowid' => 9, 'autoload' => 'no', 'option_name' => 'plugin_alpha', 'kind' => 'plugin'],
    ['rowid' => 10, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'kind' => 'plugin'],
    ['rowid' => 11, 'autoload' => 'yes', 'option_name' => null, 'kind' => 'plugin'],
    ['rowid' => 12, 'autoload' => 'yes', 'option_name' => 'blogname', 'kind' => 'core'],
    ['rowid' => 13, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'kind' => 'plugin'],
    ['rowid' => 14, 'autoload' => 'yes', 'option_name' => 'plugin_delta', 'kind' => 'plugin'],
    ['rowid' => 15, 'autoload' => 'yes', 'option_name' => 'theme_mods_child', 'kind' => 'theme'],
];

$stat4 = static fn (): array => [
    ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 2, 'nLt' => 3, 'nDLt' => 2],
    ['prefix' => 'lazy', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'no', 'suffix' => 'plugin_alpha', 'nEq' => 4, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 3, 'nLt' => 5, 'nDLt' => 2],
    ['prefix' => 'yes', 'suffix' => 'plugin_alpha', 'nEq' => 5, 'nLt' => 2, 'nDLt' => 1],
    ['prefix' => 'yes', 'suffix' => 'plugin_delta', 'nEq' => 4, 'nLt' => 7, 'nDLt' => 2],
];

$range = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$between = static fn (string $column, mixed $lower, mixed $upper): array => ['operator' => 'BETWEEN', 'left' => ['column' => $column], 'lower' => $lower, 'upper' => $upper];
$point = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];

$partial = new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL, 'plugin_');
$kindPartial = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL, 'plugin_'),
]);

$plan = static function (
    array $terms,
    array $orderBy = [['column' => 'option_name']],
    mixed $lower = 'plugin_',
    mixed $upper = 'plugin_zzzz',
    ?array $samples = null,
    ?SQLiteIndexPredicate $where = null,
    string $collation = 'BINARY',
) use ($rows, $stat4, $partial): array {
    return SQLiteSkipScanStat4PartialOrderPlan::plan(
        $rows(),
        'idx_autoload_option_partial_stat4',
        'autoload',
        'option_name',
        $lower,
        $upper,
        $where ?? $partial,
        $terms,
        $samples ?? $stat4(),
        $orderBy,
        true,
        $collation,
    );
};

$tests = [
    'planner skipscan stat4 partial order current next36 is usable after partial proof' => static function (TestRunner $t) use ($plan, $range): void {
        $t->same('usable', $plan([$range('option_name', '>=', 'plugin_alpha')])['status']);
    },
    'planner skipscan stat4 partial order current next36 keeps skip scan flag' => static function (TestRunner $t) use ($plan, $range): void {
        $t->same(true, $plan([$range('option_name', '>=', 'plugin_alpha')])['usesSkipScan']);
    },
    'planner skipscan stat4 partial order current next36 preserves partial proof evidence' => static function (TestRunner $t) use ($plan, $range): void {
        $t->same(true, $plan([$range('option_name', '>=', 'plugin_alpha')])['partialPredicateImplied']);
    },
    'planner skipscan stat4 partial order current next36 returns partial index rowids' => static function (TestRunner $t) use ($plan, $range): void {
        $t->same([2, 3, 6, 9, 10, 13, 14], $plan([$range('option_name', '>=', 'plugin_alpha')])['rowids']);
    },
    'planner skipscan stat4 partial order current next36 omits null suffix before skipscan range loop' => static function (TestRunner $t) use ($plan, $range): void {
        $t->same(0, $plan([$range('option_name', '>=', 'plugin_alpha')])['omittedNullRangeRows']);
    },
    'planner skipscan stat4 partial order current next36 uses all in-range stat4 samples' => static function (TestRunner $t) use ($plan, $range): void {
        $t->same(7, $plan([$range('option_name', '>=', 'plugin_alpha')])['stat4SamplesUsed']);
    },
    'planner skipscan stat4 partial order current next36 estimates rows from per prefix samples' => static function (TestRunner $t) use ($plan, $range): void {
        $t->same([2, 1, 3, 4], array_column($plan([$range('option_name', '>=', 'plugin_alpha')])['stat4LoopEstimates'], 'estimatedRows'));
    },
    'planner skipscan stat4 partial order current next36 sums stat4 estimates' => static function (TestRunner $t) use ($plan, $range): void {
        $t->same(10, $plan([$range('option_name', '>=', 'plugin_alpha')])['estimatedRows']);
    },
    'planner skipscan stat4 partial order current next36 charges seek plus block sort cost' => static function (TestRunner $t) use ($plan, $range): void {
        $t->same(52, $plan([$range('option_name', '>=', 'plugin_alpha')])['estimatedCost']);
    },
    'planner skipscan stat4 partial order current next36 reports partial order mode' => static function (TestRunner $t) use ($plan, $range): void {
        $t->same('partial-current-next', $plan([$range('option_name', '>=', 'plugin_alpha')])['orderByMode']);
    },
    'planner skipscan stat4 partial order current next36 requires block sort for suffix only ordering' => static function (TestRunner $t) use ($plan, $range): void {
        $p = $plan([$range('option_name', '>=', 'plugin_alpha')]);
        $t->same(true, $p['partialOrderBy']);
        $t->same(true, $p['blockSortRequired']);
    },
    'planner skipscan stat4 partial order current next36 breaks sort by skipped column' => static function (TestRunner $t) use ($plan, $range): void {
        $t->same(['autoload'], $plan([$range('option_name', '>=', 'plugin_alpha')])['sortBreakColumns']);
    },
    'planner skipscan stat4 partial order current next36 detail names temp btree right order by' => static function (TestRunner $t) use ($plan, $range): void {
        $t->same('SEARCH USING SKIP-SCAN idx_autoload_option_partial_stat4 (ANY(autoload) AND option_name RANGE) USING STAT4 USE TEMP B-TREE FOR RIGHT PART OF ORDER BY', $plan([$range('option_name', '>=', 'plugin_alpha')])['detail']);
    },
    'planner skipscan stat4 partial order current next36 full index order satisfies skipped plus range order' => static function (TestRunner $t) use ($plan, $range): void {
        $p = $plan([$range('option_name', '>=', 'plugin_alpha')], [['column' => 'autoload'], ['column' => 'option_name']]);
        $t->same('full', $p['orderByMode']);
        $t->same(true, $p['orderBySatisfied']);
    },
    'planner skipscan stat4 partial order current next36 full order removes block sort penalty' => static function (TestRunner $t) use ($plan, $range): void {
        $t->same(42, $plan([$range('option_name', '>=', 'plugin_alpha')], [['column' => 'autoload'], ['column' => 'option_name']])['estimatedCost']);
    },
    'planner skipscan stat4 partial order current next36 prefix only order is satisfied' => static function (TestRunner $t) use ($plan, $range): void {
        $p = $plan([$range('option_name', '>=', 'plugin_alpha')], [['column' => 'autoload']]);
        $t->same('prefix-only', $p['orderByMode']);
        $t->same(true, $p['orderBySatisfied']);
    },
    'planner skipscan stat4 partial order current next36 unrelated order uses external sort' => static function (TestRunner $t) use ($plan, $range): void {
        $p = $plan([$range('option_name', '>=', 'plugin_alpha')], [['column' => 'kind']]);
        $t->same('external-sort', $p['orderByMode']);
        $t->same(['kind'], $p['sortBreakColumns']);
    },
    'planner skipscan stat4 partial order current next36 no order has no sort requirement' => static function (TestRunner $t) use ($plan, $range): void {
        $p = $plan([$range('option_name', '>=', 'plugin_alpha')], []);
        $t->same('none', $p['orderByMode']);
        $t->same(false, $p['blockSortRequired']);
    },
    'planner skipscan stat4 partial order current next36 narrowed upper bound uses subset samples' => static function (TestRunner $t) use ($plan, $range): void {
        $p = $plan([$range('option_name', '>=', 'plugin_alpha')], [['column' => 'option_name']], 'plugin_', 'plugin_beta');
        $t->same([2, 1, 4, 5], array_column($p['stat4LoopEstimates'], 'estimatedRows'));
    },
    'planner skipscan stat4 partial order current next36 narrowed upper bound filters rowids' => static function (TestRunner $t) use ($plan, $range): void {
        $t->same([2, 3, 9, 13], $plan([$range('option_name', '>=', 'plugin_alpha')], [['column' => 'option_name']], 'plugin_', 'plugin_beta')['rowids']);
    },
    'planner skipscan stat4 partial order current next36 between proof is accepted' => static function (TestRunner $t) use ($plan, $between): void {
        $t->same('usable', $plan([$between('option_name', 'plugin_alpha', 'plugin_gamma')], [['column' => 'option_name']], 'plugin_alpha', 'plugin_gamma')['status']);
    },
    'planner skipscan stat4 partial order current next36 between proof narrows rowids' => static function (TestRunner $t) use ($plan, $between): void {
        $t->same([2, 3, 9, 10, 13, 14], $plan([$between('option_name', 'plugin_alpha', 'plugin_gamma')], [['column' => 'option_name']], 'plugin_alpha', 'plugin_gamma')['rowids']);
    },
    'planner skipscan stat4 partial order current next36 nocase query admits uppercase sample range' => static function (TestRunner $t) use ($plan, $range): void {
        $p = $plan([$range('option_name', '>=', 'PLUGIN_ALPHA')], [['column' => 'option_name']], 'PLUGIN_', 'PLUGIN_ZZZZ', null, null, 'NOCASE');
        $t->same([2, 3, 5, 6, 9, 10, 13, 14], $p['rowids']);
    },
    'planner skipscan stat4 partial order current next36 binary uppercase proof is rejected' => static function (TestRunner $t) use ($plan, $range): void {
        $t->same('unusable', $plan([$range('option_name', '>=', 'PLUGIN_ALPHA')], [['column' => 'option_name']], 'PLUGIN_', 'PLUGIN_ZZZZ')['status']);
    },
    'planner skipscan stat4 partial order current next36 and partial proof keeps plugin kind rows' => static function (TestRunner $t) use ($plan, $kindPartial, $point, $range): void {
        $p = $plan([$point('kind', 'plugin'), $range('option_name', '>=', 'plugin_alpha')], [['column' => 'option_name']], 'plugin_', 'plugin_zzzz', null, $kindPartial);
        $t->same([2, 3, 6, 9, 10, 13, 14], $p['rowids']);
    },
    'planner skipscan stat4 partial order current next36 and partial proof rejects missing kind' => static function (TestRunner $t) use ($plan, $kindPartial, $range): void {
        $t->same('unusable', $plan([$range('option_name', '>=', 'plugin_alpha')], [['column' => 'option_name']], 'plugin_', 'plugin_zzzz', null, $kindPartial)['status']);
    },
    'planner skipscan stat4 partial order current next36 fallback estimate uses matched rows without samples' => static function (TestRunner $t) use ($plan, $range): void {
        $p = $plan([$range('option_name', '>=', 'plugin_alpha')], [['column' => 'autoload'], ['column' => 'option_name']], 'plugin_', 'plugin_zzzz', []);
        $t->same([2, 1, 2, 2], array_column($p['stat4LoopEstimates'], 'estimatedRows'));
    },
    'planner skipscan stat4 partial order current next36 rejects malformed sample counter' => static function (TestRunner $t) use ($plan, $range): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan([$range('option_name', '>=', 'plugin_alpha')], [['column' => 'option_name']], 'plugin_', 'plugin_zzzz', [['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => -1, 'nLt' => 0, 'nDLt' => 0]]));
    },
    'planner skipscan stat4 partial order current next36 rejects missing index name' => static function (TestRunner $t) use ($rows, $partial, $range, $stat4): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSkipScanStat4PartialOrderPlan::plan($rows(), '', 'autoload', 'option_name', 'plugin_', 'plugin_zzzz', $partial, [$range('option_name', '>=', 'plugin_alpha')], $stat4()));
    },
    'planner skipscan stat4 partial order current next36 rejects same skipped and range column' => static function (TestRunner $t) use ($rows, $partial, $range, $stat4): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSkipScanStat4PartialOrderPlan::plan($rows(), 'bad', 'option_name', 'option_name', 'plugin_', 'plugin_zzzz', $partial, [$range('option_name', '>=', 'plugin_alpha')], $stat4()));
    },
];

$prefixCases = [
    'auto' => [2, 2, 2, [2, 3]],
    'lazy' => [1, 1, 1, [6]],
    'no' => [2, 2, 3, [9, 10]],
    'yes' => [2, 2, 4, [13, 14]],
];

foreach ($prefixCases as $prefix => [$matched, $sampleCount, $estimate, $rowids]) {
    $tests["planner skipscan stat4 partial order current next36 {$prefix} loop matched"] = static function (TestRunner $t) use ($plan, $range, $prefix, $matched): void {
        $loops = array_column($plan([$range('option_name', '>=', 'plugin_alpha')])['stat4LoopEstimates'], null, 'prefix');
        $t->same($matched, $loops[$prefix]['matched']);
    };
    $tests["planner skipscan stat4 partial order current next36 {$prefix} sample count"] = static function (TestRunner $t) use ($plan, $range, $prefix, $sampleCount): void {
        $loops = array_column($plan([$range('option_name', '>=', 'plugin_alpha')])['stat4LoopEstimates'], null, 'prefix');
        $t->same($sampleCount, $loops[$prefix]['sampleCount']);
    };
    $tests["planner skipscan stat4 partial order current next36 {$prefix} estimate"] = static function (TestRunner $t) use ($plan, $range, $prefix, $estimate): void {
        $loops = array_column($plan([$range('option_name', '>=', 'plugin_alpha')])['stat4LoopEstimates'], null, 'prefix');
        $t->same($estimate, $loops[$prefix]['estimatedRows']);
    };
    $tests["planner skipscan stat4 partial order current next36 {$prefix} rowids"] = static function (TestRunner $t) use ($plan, $range, $prefix, $rowids): void {
        $loops = array_column($plan([$range('option_name', '>=', 'plugin_alpha')])['stat4LoopEstimates'], null, 'prefix');
        $t->same($rowids, $loops[$prefix]['rowids']);
    };
}

$orderCases = [
    'range suffix order partial' => [[['column' => 'option_name']], 'partial-current-next', false, true],
    'range suffix desc still partial' => [[['column' => 'option_name', 'direction' => 'DESC']], 'partial-current-next', false, true],
    'skipped and suffix full order' => [[['column' => 'autoload'], ['column' => 'option_name']], 'full', true, false],
    'skipped prefix only order' => [[['column' => 'autoload']], 'prefix-only', true, false],
    'unknown expression order external' => [[['column' => 'lower(option_name)']], 'external-sort', false, true],
    'kind order external' => [[['column' => 'kind']], 'external-sort', false, true],
];

foreach ($orderCases as $label => [$orderBy, $mode, $satisfied, $blockSort]) {
    $tests["planner skipscan stat4 partial order current next36 order {$label}"] = static function (TestRunner $t) use ($plan, $range, $orderBy, $mode, $satisfied, $blockSort): void {
        $p = $plan([$range('option_name', '>=', 'plugin_alpha')], $orderBy);
        $t->same($mode, $p['orderByMode']);
        $t->same($satisfied, $p['orderBySatisfied']);
        $t->same($blockSort, $p['blockSortRequired']);
    };
}

return $tests;
