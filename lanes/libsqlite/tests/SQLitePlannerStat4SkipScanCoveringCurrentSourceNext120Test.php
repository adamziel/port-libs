<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteSkipScanStat4PartialOrderPlan;

$rows120 = static fn (): array => [
    ['rowid' => 1, 'option_id' => 101, 'autoload' => 'auto', 'option_name' => 'admin_email', 'kind' => 'core', 'option_value' => 'a@example.test', 'blog_id' => 1],
    ['rowid' => 2, 'option_id' => 102, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'kind' => 'plugin', 'option_value' => '{"enabled":true}', 'blog_id' => 1],
    ['rowid' => 3, 'option_id' => 103, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'kind' => 'plugin', 'option_value' => '{"enabled":false}', 'blog_id' => 1],
    ['rowid' => 4, 'option_id' => 104, 'autoload' => 'auto', 'option_name' => 'plugin_delta', 'kind' => 'plugin', 'option_value' => '{"enabled":true}', 'blog_id' => 2],
    ['rowid' => 5, 'option_id' => 105, 'autoload' => 'lazy', 'option_name' => 'Plugin_Epsilon', 'kind' => 'plugin', 'option_value' => '{"enabled":true}', 'blog_id' => 2],
    ['rowid' => 6, 'option_id' => 106, 'autoload' => 'lazy', 'option_name' => 'plugin_zeta', 'kind' => 'plugin', 'option_value' => '{"enabled":false}', 'blog_id' => 2],
    ['rowid' => 7, 'option_id' => 107, 'autoload' => 'lazy', 'option_name' => 'widget_recent-posts', 'kind' => 'widget', 'option_value' => '{}', 'blog_id' => 1],
    ['rowid' => 8, 'option_id' => 108, 'autoload' => 'no', 'option_name' => '_transient_alpha', 'kind' => 'transient', 'option_value' => '1', 'blog_id' => 1],
    ['rowid' => 9, 'option_id' => 109, 'autoload' => 'no', 'option_name' => 'plugin_alpha', 'kind' => 'plugin', 'option_value' => '{"enabled":true}', 'blog_id' => 3],
    ['rowid' => 10, 'option_id' => 110, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'kind' => 'plugin', 'option_value' => '{"enabled":true}', 'blog_id' => 3],
    ['rowid' => 11, 'option_id' => 111, 'autoload' => 'no', 'option_name' => 'plugin_theta', 'kind' => 'plugin', 'option_value' => '{"enabled":false}', 'blog_id' => 3],
    ['rowid' => 12, 'option_id' => 112, 'autoload' => 'yes', 'option_name' => null, 'kind' => 'plugin', 'option_value' => '{}', 'blog_id' => 1],
    ['rowid' => 13, 'option_id' => 113, 'autoload' => 'yes', 'option_name' => 'blogname', 'kind' => 'core', 'option_value' => 'Port Libs', 'blog_id' => 1],
    ['rowid' => 14, 'option_id' => 114, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'kind' => 'plugin', 'option_value' => '{"enabled":true}', 'blog_id' => 4],
    ['rowid' => 15, 'option_id' => 115, 'autoload' => 'yes', 'option_name' => 'plugin_delta', 'kind' => 'plugin', 'option_value' => '{"enabled":true}', 'blog_id' => 4],
    ['rowid' => 16, 'option_id' => 116, 'autoload' => 'yes', 'option_name' => 'theme_mods_child', 'kind' => 'theme', 'option_value' => '{}', 'blog_id' => 1],
];

$stat120 = static fn (): array => [
    ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 2, 'nLt' => 3, 'nDLt' => 2],
    ['prefix' => 'auto', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 5, 'nDLt' => 3],
    ['prefix' => 'lazy', 'suffix' => 'plugin_epsilon', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'lazy', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
    ['prefix' => 'no', 'suffix' => 'plugin_alpha', 'nEq' => 4, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 3, 'nLt' => 5, 'nDLt' => 2],
    ['prefix' => 'no', 'suffix' => 'plugin_theta', 'nEq' => 2, 'nLt' => 8, 'nDLt' => 3],
    ['prefix' => 'yes', 'suffix' => 'plugin_alpha', 'nEq' => 5, 'nLt' => 2, 'nDLt' => 1],
    ['prefix' => 'yes', 'suffix' => 'plugin_delta', 'nEq' => 4, 'nLt' => 7, 'nDLt' => 2],
];

$range120 = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$point120 = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];

$partial120 = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL, 'plugin_'),
]);

$plan120 = static function (
    array $needed = ['option_id', 'option_name', 'autoload', 'option_value', 'blog_id'],
    array $covering = ['autoload', 'option_name', 'option_id', 'option_value', 'blog_id'],
    array $orderBy = [['column' => 'option_name']],
    mixed $lower = 'plugin_',
    mixed $upper = 'plugin_zzzz',
    bool $upperInclusive = true,
    string $collation = 'BINARY',
    ?array $rows = null,
    ?array $samples = null,
) use ($rows120, $stat120, $partial120, $point120, $range120): ?array {
    return SQLiteSkipScanStat4PartialOrderPlan::coveringCurrentSourcePlan(
        $rows ?? $rows120(),
        'idx_wp_options_autoload_name_covering_stat4_next120',
        'autoload',
        'option_name',
        $lower,
        $upper,
        $partial120,
        [$point120('kind', 'plugin'), $range120('option_name', '>=', 'plugin_')],
        $samples ?? $stat120(),
        $orderBy,
        $covering,
        $needed,
        $upperInclusive,
        $collation,
    );
};

$tests = [
    'planner stat4 skipscan covering current source next120 selects usable plan' => static fn (TestRunner $t) => $t->same('usable', $plan120()['status']),
    'planner stat4 skipscan covering current source next120 records dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-stat4-skipscan-covering-current-source-next120'], $plan120()['dependencies']),
    'planner stat4 skipscan covering current source next120 uses covering index' => static fn (TestRunner $t) => $t->same(true, $plan120()['covering']),
    'planner stat4 skipscan covering current source next120 avoids table seek' => static fn (TestRunner $t) => $t->same(false, $plan120()['tableSeekRequired']),
    'planner stat4 skipscan covering current source next120 has no deferred seek opcode' => static fn (TestRunner $t) => $t->same(null, $plan120()['deferredSeekOpcode']),
    'planner stat4 skipscan covering current source next120 keeps skip scan loops' => static fn (TestRunner $t) => $t->same(['auto', 'lazy', 'no', 'yes'], array_column($plan120()['loops'], 'prefix')),
    'planner stat4 skipscan covering current source next120 rowids come from range' => static fn (TestRunner $t) => $t->same([2, 3, 4, 6, 9, 10, 11, 14, 15], $plan120()['rowids']),
    'planner stat4 skipscan covering current source next120 covered row count' => static fn (TestRunner $t) => $t->same(9, $plan120()['coveredRowCount']),
    'planner stat4 skipscan covering current source next120 keeps needed columns' => static fn (TestRunner $t) => $t->same(['option_id', 'option_name', 'autoload', 'option_value', 'blog_id'], $plan120()['neededColumns']),
    'planner stat4 skipscan covering current source next120 keeps index columns' => static fn (TestRunner $t) => $t->same(['autoload', 'option_name', 'option_id', 'option_value', 'blog_id'], $plan120()['coveringColumns']),
    'planner stat4 skipscan covering current source next120 detail says covering' => static fn (TestRunner $t) => $t->same(true, str_contains($plan120()['detail'], 'USING COVERING INDEX')),
    'planner stat4 skipscan covering current source next120 mode keeps block sort' => static fn (TestRunner $t) => $t->same('covering-skipscan-block-sort', $plan120()['coveringMode']),
    'planner stat4 skipscan covering current source next120 keeps partial order mode' => static fn (TestRunner $t) => $t->same('partial-current-next', $plan120()['orderByMode']),
    'planner stat4 skipscan covering current source next120 block sort stays required' => static fn (TestRunner $t) => $t->same(true, $plan120()['blockSortRequired']),
    'planner stat4 skipscan covering current source next120 sort blocks by prefixes' => static fn (TestRunner $t) => $t->same(4, $plan120()['sortBlockCount']),
    'planner stat4 skipscan covering current source next120 stat4 samples used' => static fn (TestRunner $t) => $t->same(10, $plan120()['stat4SamplesUsed']),
    'planner stat4 skipscan covering current source next120 estimated rows' => static fn (TestRunner $t) => $t->same(8, $plan120()['estimatedRows']),
    'planner stat4 skipscan covering current source next120 estimated cost' => static fn (TestRunner $t) => $t->same(48, $plan120()['estimatedCost']),
    'planner stat4 skipscan covering current source next120 first covering option id' => static fn (TestRunner $t) => $t->same(102, $plan120()['currentNextCoveringRows'][0]['current']['covering']['option_id']),
    'planner stat4 skipscan covering current source next120 first covering option name' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan120()['currentNextCoveringRows'][0]['current']['covering']['option_name']),
    'planner stat4 skipscan covering current source next120 first covering value' => static fn (TestRunner $t) => $t->same('{"enabled":true}', $plan120()['currentNextCoveringRows'][0]['current']['covering']['option_value']),
    'planner stat4 skipscan covering current source next120 first next option id' => static fn (TestRunner $t) => $t->same(103, $plan120()['currentNextCoveringRows'][0]['next']['covering']['option_id']),
    'planner stat4 skipscan covering current source next120 last next is null' => static fn (TestRunner $t) => $t->same(null, $plan120()['currentNextCoveringRows'][8]['next']),
    'planner stat4 skipscan covering current source next120 source offsets increase' => static fn (TestRunner $t) => $t->same(range(0, 8), array_map(static fn (array $pair): int => $pair['current']['sourceOffset'], $plan120()['currentNextCoveringRows'])),
    'planner stat4 skipscan covering current source next120 rowids mirror current evidence' => static fn (TestRunner $t) => $t->same($plan120()['rowids'], array_map(static fn (array $pair): int => $pair['current']['rowid'], $plan120()['currentNextCoveringRows'])),
    'planner stat4 skipscan covering current source next120 cursor opens index prefix' => static fn (TestRunner $t) => $t->same('RewindPrefix', $plan120()['cursorProgram'][0]['opcode']),
    'planner stat4 skipscan covering current source next120 cursor seeks lower' => static fn (TestRunner $t) => $t->same(['opcode' => 'SeekGE', 'column' => 'option_name', 'value' => 'plugin_'], $plan120()['cursorProgram'][1]),
    'planner stat4 skipscan covering current source next120 cursor stops upper' => static fn (TestRunner $t) => $t->same(['opcode' => 'IdxGT', 'column' => 'option_name', 'value' => 'plugin_zzzz'], $plan120()['cursorProgram'][2]),
    'planner stat4 skipscan covering current source next120 cursor reads columns from index' => static fn (TestRunner $t) => $t->same(['opcode' => 'Column', 'source' => 'index', 'columns' => ['option_id', 'option_name', 'autoload', 'option_value', 'blog_id']], $plan120()['cursorProgram'][3]),
    'planner stat4 skipscan covering current source next120 cursor advances next' => static fn (TestRunner $t) => $t->same(['opcode' => 'Next', 'target' => 'index'], $plan120()['cursorProgram'][4]),
    'planner stat4 skipscan covering current source next120 narrowed upper rowids' => static fn (TestRunner $t) => $t->same([2, 3, 4, 9, 10, 14, 15], $plan120(upper: 'plugin_gamma')['rowids']),
    'planner stat4 skipscan covering current source next120 narrowed upper covered rows' => static fn (TestRunner $t) => $t->same(7, $plan120(upper: 'plugin_gamma')['coveredRowCount']),
    'planner stat4 skipscan covering current source next120 exclusive upper rowids' => static fn (TestRunner $t) => $t->same([2, 3, 4, 9, 14, 15], $plan120(upper: 'plugin_gamma', upperInclusive: false)['rowids']),
    'planner stat4 skipscan covering current source next120 exclusive upper stop opcode' => static fn (TestRunner $t) => $t->same('IdxGE', $plan120(upper: 'plugin_gamma', upperInclusive: false)['cursorProgram'][2]['opcode']),
    'planner stat4 skipscan covering current source next120 nocase admits uppercase row' => static fn (TestRunner $t) => $t->same(true, in_array(105, array_map(static fn (array $pair): int => $pair['current']['covering']['option_id'], $plan120(lower: 'PLUGIN_', upper: 'PLUGIN_ZZZZ', collation: 'NOCASE')['currentNextCoveringRows']), true)),
    'planner stat4 skipscan covering current source next120 nocase covered row count' => static fn (TestRunner $t) => $t->same(10, $plan120(lower: 'PLUGIN_', upper: 'PLUGIN_ZZZZ', collation: 'NOCASE')['coveredRowCount']),
    'planner stat4 skipscan covering current source next120 full order avoids block sort' => static fn (TestRunner $t) => $t->same('covering-skipscan', $plan120(orderBy: [['column' => 'autoload'], ['column' => 'option_name']])['coveringMode']),
    'planner stat4 skipscan covering current source next120 full order is satisfied' => static fn (TestRunner $t) => $t->same(true, $plan120(orderBy: [['column' => 'autoload'], ['column' => 'option_name']])['orderBySatisfied']),
    'planner stat4 skipscan covering current source next120 reverse cursor starts last prefix' => static fn (TestRunner $t) => $t->same('LastPrefix', $plan120(orderBy: [['column' => 'option_name', 'direction' => 'DESC']])['cursorProgram'][0]['opcode']),
    'planner stat4 skipscan covering current source next120 reverse cursor advances prev' => static fn (TestRunner $t) => $t->same('Prev', $plan120(orderBy: [['column' => 'option_name', 'direction' => 'DESC']])['cursorProgram'][4]['opcode']),
    'planner stat4 skipscan covering current source next120 missing covering column returns rejected plan' => static fn (TestRunner $t) => $t->same(false, $plan120(['option_id', 'option_name', 'option_value'], ['autoload', 'option_name', 'option_id'])['covering']),
    'planner stat4 skipscan covering current source next120 rejected column is reported' => static fn (TestRunner $t) => $t->same(['option_value'], $plan120(['option_id', 'option_name', 'option_value'], ['autoload', 'option_name', 'option_id'])['coveringRejectedColumns']),
    'planner stat4 skipscan covering current source next120 rejected plan requires table seek' => static fn (TestRunner $t) => $t->same(true, $plan120(['option_id', 'option_name', 'option_value'], ['autoload', 'option_name', 'option_id'])['tableSeekRequired']),
    'planner stat4 skipscan covering current source next120 rejected plan has deferred seek' => static fn (TestRunner $t) => $t->same('DeferredSeek', $plan120(['option_id', 'option_name', 'option_value'], ['autoload', 'option_name', 'option_id'])['deferredSeekOpcode']),
    'planner stat4 skipscan covering current source next120 missing partial proof returns null' => static function (TestRunner $t) use ($rows120, $stat120, $partial120, $range120): void {
        $candidate = SQLiteSkipScanStat4PartialOrderPlan::coveringCurrentSourcePlan(
            $rows120(),
            'idx_wp_options_autoload_name_covering_stat4_next120',
            'autoload',
            'option_name',
            'plugin_',
            'plugin_zzzz',
            $partial120,
            [$range120('option_name', '>=', 'plugin_')],
            $stat120(),
            [['column' => 'option_name']],
            ['autoload', 'option_name', 'option_id'],
            ['option_id'],
        );
        $t->same(null, $candidate);
    },
    'planner stat4 skipscan covering current source next120 validates empty covering columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan120(covering: [])),
    'planner stat4 skipscan covering current source next120 validates empty needed columns' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan120(needed: [])),
    'planner stat4 skipscan covering current source next120 validates bad covering column' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan120(covering: ['autoload', ''])),
    'planner stat4 skipscan covering current source next120 validates bad needed column' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan120(needed: ['option_id', ''])),
    'planner stat4 skipscan covering current source next120 validates row covering payload column' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan120(rows: [['rowid' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'kind' => 'plugin', 'option_id' => 1]])),
    'planner stat4 skipscan covering current source next120 validates stat4 counters' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan120(samples: [['prefix' => 'yes', 'suffix' => 'plugin_alpha', 'nEq' => -1, 'nLt' => 0, 'nDLt' => 0]])),
    'planner stat4 skipscan covering current source next120 stat4 current for auto prefix' => static fn (TestRunner $t) => $t->same('plugin_alpha', $plan120()['stat4CurrentNextByPrefix'][0]['current']['suffix']),
    'planner stat4 skipscan covering current source next120 stat4 next for auto prefix' => static fn (TestRunner $t) => $t->same('plugin_beta', $plan120()['stat4CurrentNextByPrefix'][0]['next']['suffix']),
    'planner stat4 skipscan covering current source next120 loop estimate samples by prefix' => static fn (TestRunner $t) => $t->same([3, 2, 3, 2], array_column($plan120()['stat4LoopEstimates'], 'rangeSamples')),
    'planner stat4 skipscan covering current source next120 skipped partial rows remain counted' => static fn (TestRunner $t) => $t->same(7, $plan120()['skippedPartialRows']),
    'planner stat4 skipscan covering current source next120 omitted null range rows remain counted' => static fn (TestRunner $t) => $t->same(0, $plan120()['omittedNullRangeRows']),
    'planner stat4 skipscan covering current source next120 no rejected columns for covering plan' => static fn (TestRunner $t) => $t->same([], $plan120()['coveringRejectedColumns']),
];

return $tests;
