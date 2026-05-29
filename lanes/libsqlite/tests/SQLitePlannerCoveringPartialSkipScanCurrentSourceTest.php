<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteSkipScanStat4PartialOrderPlan;

$rows125 = static fn (): array => [
    ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'admin_email', 'option_value' => 'owner@example.test', 'kind' => 'core'],
    ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1', 'kind' => 'plugin'],
    ['rowid' => 3, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'option_value' => 'a:2', 'kind' => 'plugin'],
    ['rowid' => 4, 'autoload' => 'lazy', 'option_name' => 'Plugin_Delta', 'option_value' => 'a:3', 'kind' => 'plugin'],
    ['rowid' => 5, 'autoload' => 'lazy', 'option_name' => 'plugin_epsilon', 'option_value' => 'a:4', 'kind' => 'plugin'],
    ['rowid' => 6, 'autoload' => 'no', 'option_name' => '_transient_plugin_alpha', 'option_value' => 'tmp', 'kind' => 'transient'],
    ['rowid' => 7, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'option_value' => 'a:5', 'kind' => 'plugin'],
    ['rowid' => 8, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'null-name', 'kind' => 'plugin'],
    ['rowid' => 9, 'autoload' => 'yes', 'option_name' => 'plugin_alpha', 'option_value' => 'a:6', 'kind' => 'plugin'],
    ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'theme_mods_child', 'option_value' => 'theme', 'kind' => 'theme'],
];

$currentRows125 = static function () use ($rows125): array {
    $rows = $rows125();
    $rows[] = ['rowid' => 11, 'autoload' => 'no', 'option_name' => 'plugin_theta', 'option_value' => 'a:7', 'kind' => 'plugin'];
    $rows[] = ['rowid' => 12, 'autoload' => 'auto', 'option_name' => 'plugin_zeta', 'option_value' => 'a:8', 'kind' => 'plugin'];

    return $rows;
};

$stat4125 = static fn (): array => [
    ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 2, 'nLt' => 3, 'nDLt' => 2],
    ['prefix' => 'lazy', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
    ['prefix' => 'lazy', 'suffix' => 'plugin_epsilon', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2],
    ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 3, 'nLt' => 2, 'nDLt' => 1],
    ['prefix' => 'yes', 'suffix' => 'plugin_alpha', 'nEq' => 4, 'nLt' => 1, 'nDLt' => 1],
];

$currentStat4125 = static function () use ($stat4125): array {
    $samples = $stat4125();
    $samples[] = ['prefix' => 'auto', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 4, 'nDLt' => 3];
    $samples[] = ['prefix' => 'no', 'suffix' => 'plugin_theta', 'nEq' => 1, 'nLt' => 4, 'nDLt' => 2];

    return $samples;
};

$source125 = static function (array $overrides = []) use ($rows125, $stat4125): array {
    return $overrides + [
        'name' => 'prepared-main.wp_options@cookie1250',
        'schemaCookie' => 1250,
        'stat4Generation' => 12,
        'indexName' => 'idx_wp_options_autoload_plugin_covering',
        'rootPage' => 44,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_zzzz',
        'upperInclusive' => true,
        'collation' => 'NOCASE',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $rows125(),
        'stat4Samples' => $stat4125(),
    ];
};

$currentSource125 = static function (array $overrides = []) use ($source125, $currentRows125, $currentStat4125): array {
    return $overrides + [
        'name' => 'current-main.wp_options@cookie1251',
        'schemaCookie' => 1251,
        'stat4Generation' => 13,
        'indexName' => 'idx_wp_options_autoload_plugin_covering',
        'rootPage' => 47,
        'skippedColumn' => 'autoload',
        'rangeColumn' => 'option_name',
        'lowerInclusive' => 'plugin_',
        'upperBound' => 'plugin_zzzz',
        'upperInclusive' => true,
        'collation' => 'NOCASE',
        'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
        'rows' => $currentRows125(),
        'stat4Samples' => $currentStat4125(),
    ];
};

$partial125 = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL, 'plugin_'),
]);
$point125 = static fn (string $column, mixed $value): array => ['operator' => '=', 'left' => ['column' => $column], 'right' => $value];
$range125 = static fn (string $column, string $operator, mixed $value): array => ['operator' => $operator, 'left' => ['column' => $column], 'right' => $value];
$query125 = [$point125('kind', 'plugin'), $range125('option_name', '>=', 'plugin_')];
$order125 = [['column' => 'option_name']];
$needed125 = ['option_name', 'option_value'];

$plan125 = static fn (?array $prepared = null, ?array $current = null, ?array $needed = null, ?array $order = null): array => SQLiteSkipScanStat4PartialOrderPlan::coveringCurrentSource(
    $prepared ?? $source125(),
    $current ?? $currentSource125(),
    $partial125,
    $query125,
    $order ?? $order125,
    $needed ?? $needed125,
);

$tests = [
    'planner covering partial skipscan current source current-source selects current when stale' => static fn (TestRunner $t) => $t->same('current', $plan125()['selectedSource']),
    'planner covering partial skipscan current source current-source marks stale prepared' => static fn (TestRunner $t) => $t->same(true, $plan125()['stalePreparedStatement']),
    'planner covering partial skipscan current source current-source requires reprepare' => static fn (TestRunner $t) => $t->same(true, $plan125()['reprepareRequired']),
    'planner covering partial skipscan current source current-source detects schema cookie' => static fn (TestRunner $t) => $t->same(true, $plan125()['schemaCookieChanged']),
    'planner covering partial skipscan current source current-source detects stat4 generation' => static fn (TestRunner $t) => $t->same(true, $plan125()['stat4GenerationChanged']),
    'planner covering partial skipscan current source current-source detects root page' => static fn (TestRunner $t) => $t->same(true, $plan125()['indexRootChanged']),
    'planner covering partial skipscan current source current-source detects row signature' => static fn (TestRunner $t) => $t->same(true, $plan125()['rowSignatureChanged']),
    'planner covering partial skipscan current source current-source detects stat4 signature' => static fn (TestRunner $t) => $t->same(true, $plan125()['stat4SignatureChanged']),
    'planner covering partial skipscan current source current-source keeps covering signature stable' => static fn (TestRunner $t) => $t->same(false, $plan125()['coveringSignatureChanged']),
    'planner covering partial skipscan current source current-source status usable' => static fn (TestRunner $t) => $t->same('usable', $plan125()['status']),
    'planner covering partial skipscan current source current-source current fence name' => static fn (TestRunner $t) => $t->same('current-main.wp_options@cookie1251', $plan125()['currentSourceFence']['name']),
    'planner covering partial skipscan current source current-source current fence root' => static fn (TestRunner $t) => $t->same(47, $plan125()['currentSourceFence']['rootPage']),
    'planner covering partial skipscan current source current-source selected rowids include current insertions' => static fn (TestRunner $t) => $t->same([2, 3, 12, 4, 5, 7, 11, 9], $plan125()['selectedPlan']['rowids']),
    'planner covering partial skipscan current source current-source prepared rowids omit current insertions' => static fn (TestRunner $t) => $t->same([2, 3, 4, 5, 7, 9], $plan125()['preparedSource']['rowids']),
    'planner covering partial skipscan current source current-source current summary rowids' => static fn (TestRunner $t) => $t->same([2, 3, 12, 4, 5, 7, 11, 9], $plan125()['currentSource']['rowids']),
    'planner covering partial skipscan current source current-source current covered row count' => static fn (TestRunner $t) => $t->same(8, $plan125()['currentSource']['coveredRowCount']),
    'planner covering partial skipscan current source current-source selected covered row count' => static fn (TestRunner $t) => $t->same(8, $plan125()['selectedPlan']['coveredRowCount']),
    'planner covering partial skipscan current source current-source selected covering true' => static fn (TestRunner $t) => $t->same(true, $plan125()['selectedPlan']['covering']),
    'planner covering partial skipscan current source current-source table seek avoided' => static fn (TestRunner $t) => $t->same(false, $plan125()['selectedPlan']['tableSeekRequired']),
    'planner covering partial skipscan current source current-source uses covering block sort mode' => static fn (TestRunner $t) => $t->same('covering-skipscan-block-sort', $plan125()['selectedPlan']['coveringMode']),
    'planner covering partial skipscan current source current-source keeps partial current next order mode' => static fn (TestRunner $t) => $t->same('partial-current-next', $plan125()['selectedPlan']['orderByMode']),
    'planner covering partial skipscan current source current-source keeps temp sort evidence' => static fn (TestRunner $t) => $t->same(true, $plan125()['selectedPlan']['blockSortRequired']),
    'planner covering partial skipscan current source current-source estimates rows from current stat4' => static fn (TestRunner $t) => $t->same(7, $plan125()['selectedPlan']['estimatedRows']),
    'planner covering partial skipscan current source current-source estimates cost from current stat4' => static fn (TestRunner $t) => $t->same(46, $plan125()['selectedPlan']['estimatedCost']),
    'planner covering partial skipscan current source current-source current samples used' => static fn (TestRunner $t) => $t->same(8, $plan125()['selectedPlan']['stat4SamplesUsed']),
    'planner covering partial skipscan current source current-source current auto range samples' => static fn (TestRunner $t) => $t->same(3, $plan125()['selectedPlan']['stat4CurrentNextByPrefix'][0]['rangeSamples']),
    'planner covering partial skipscan current source current-source current no range samples' => static fn (TestRunner $t) => $t->same(2, $plan125()['selectedPlan']['stat4CurrentNextByPrefix'][2]['rangeSamples']),
    'planner covering partial skipscan current source current-source current next row evidence' => static fn (TestRunner $t) => $t->same('plugin_beta', $plan125()['selectedPlan']['currentNextCoveringRows'][0]['next']['covering']['option_name']),
    'planner covering partial skipscan current source current-source final next row is null' => static fn (TestRunner $t) => $t->same(null, $plan125()['selectedPlan']['currentNextCoveringRows'][7]['next']),
    'planner covering partial skipscan current source current-source cursor starts at prefix rewind' => static fn (TestRunner $t) => $t->same('RewindPrefix', $plan125()['selectedPlan']['cursorProgram'][0]['opcode']),
    'planner covering partial skipscan current source current-source cursor reads only needed columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value'], $plan125()['selectedPlan']['cursorProgram'][3]['columns']),
    'planner covering partial skipscan current source current-source detail reports reprepare' => static fn (TestRunner $t) => $t->contains('REPREPARE COVERING PARTIAL SKIP-SCAN current-main.wp_options@cookie1251', $plan125()['detail']),
    'planner covering partial skipscan current source current-source dependency marker' => static fn (TestRunner $t) => $t->same(['sqlite-sqlplanner-covering-partial-skipscan-current-source'], $plan125()['dependencies']),
];

$tests += [
    'planner covering partial skipscan current source current-source reuses identical prepared source' => static function (TestRunner $t) use ($source125, $plan125): void {
        $source = $source125(['name' => 'prepared-main.wp_options@cookie1250']);
        $t->same('prepared', $plan125($source, $source)['selectedSource']);
    },
    'planner covering partial skipscan current source current-source identical source not stale' => static function (TestRunner $t) use ($source125, $plan125): void {
        $source = $source125(['name' => 'prepared-main.wp_options@cookie1250']);
        $t->same(false, $plan125($source, $source)['stalePreparedStatement']);
    },
    'planner covering partial skipscan current source current-source identical source row count' => static function (TestRunner $t) use ($source125, $plan125): void {
        $source = $source125(['name' => 'prepared-main.wp_options@cookie1250']);
        $t->same(6, $plan125($source, $source)['selectedPlan']['coveredRowCount']);
    },
    'planner covering partial skipscan current source current-source identical source detail reports reuse' => static function (TestRunner $t) use ($source125, $plan125): void {
        $source = $source125(['name' => 'prepared-main.wp_options@cookie1250']);
        $t->contains('REUSE PREPARED COVERING PARTIAL SKIP-SCAN', $plan125($source, $source)['detail']);
    },
    'planner covering partial skipscan current source current-source covering change is fenced' => static function (TestRunner $t) use ($source125, $currentSource125, $plan125): void {
        $current = $currentSource125(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same(true, $plan125($source125(), $current)['coveringSignatureChanged']);
    },
    'planner covering partial skipscan current source current-source missing covering column rejects current covering' => static function (TestRunner $t) use ($source125, $currentSource125, $plan125): void {
        $current = $currentSource125(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same(false, $plan125($source125(), $current)['selectedPlan']['covering']);
    },
    'planner covering partial skipscan current source current-source missing covering column requires table seek' => static function (TestRunner $t) use ($source125, $currentSource125, $plan125): void {
        $current = $currentSource125(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same(true, $plan125($source125(), $current)['selectedPlan']['tableSeekRequired']);
    },
    'planner covering partial skipscan current source current-source missing covering column names rejection' => static function (TestRunner $t) use ($source125, $currentSource125, $plan125): void {
        $current = $currentSource125(['coveringColumns' => ['autoload', 'option_name', 'kind']]);
        $t->same(['option_value'], $plan125($source125(), $current)['selectedPlan']['coveringRejectedColumns']);
    },
    'planner covering partial skipscan current source current-source narrower current upper bound reduces rowids' => static function (TestRunner $t) use ($source125, $currentSource125, $plan125): void {
        $current = $currentSource125(['upperBound' => 'plugin_gamma']);
        $t->same([2, 3, 4, 5, 7, 9], $plan125($source125(), $current)['selectedPlan']['rowids']);
    },
    'planner covering partial skipscan current source current-source exclusive current upper bound removes boundary' => static function (TestRunner $t) use ($source125, $currentSource125, $plan125): void {
        $current = $currentSource125(['upperBound' => 'plugin_gamma', 'upperInclusive' => false]);
        $t->same([2, 3, 4, 5, 9], $plan125($source125(), $current)['selectedPlan']['rowids']);
    },
    'planner covering partial skipscan current source current-source binary collation excludes uppercase row' => static function (TestRunner $t) use ($source125, $currentSource125, $plan125): void {
        $current = $currentSource125(['collation' => 'BINARY']);
        $t->same([2, 3, 12, 5, 7, 11, 9], $plan125($source125(), $current)['selectedPlan']['rowids']);
    },
    'planner covering partial skipscan current source current-source reverse order uses last prefix' => static function (TestRunner $t) use ($source125, $currentSource125, $plan125): void {
        $p = $plan125($source125(), $currentSource125(), null, [['column' => 'option_name', 'direction' => 'DESC']]);
        $t->same('LastPrefix', $p['selectedPlan']['cursorProgram'][0]['opcode']);
    },
    'planner covering partial skipscan current source current-source full order avoids block sort' => static function (TestRunner $t) use ($source125, $currentSource125, $plan125): void {
        $p = $plan125($source125(), $currentSource125(), null, [['column' => 'autoload'], ['column' => 'option_name']]);
        $t->same(false, $p['selectedPlan']['blockSortRequired']);
    },
    'planner covering partial skipscan current source current-source full order uses covering skipscan mode' => static function (TestRunner $t) use ($source125, $currentSource125, $plan125): void {
        $p = $plan125($source125(), $currentSource125(), null, [['column' => 'autoload'], ['column' => 'option_name']]);
        $t->same('covering-skipscan', $p['selectedPlan']['coveringMode']);
    },
    'planner covering partial skipscan current source current-source full order cost omits sort penalty' => static function (TestRunner $t) use ($source125, $currentSource125, $plan125): void {
        $p = $plan125($source125(), $currentSource125(), null, [['column' => 'autoload'], ['column' => 'option_name']]);
        $t->same(39, $p['selectedPlan']['estimatedCost']);
    },
    'planner covering partial skipscan current source current-source validates source rows' => static function (TestRunner $t) use ($source125, $currentSource125, $plan125): void {
        $bad = $source125(['rows' => ['not-a-row']]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan125($bad, $currentSource125()));
    },
    'planner covering partial skipscan current source current-source validates source name' => static function (TestRunner $t) use ($source125, $currentSource125, $plan125): void {
        $bad = $source125(['name' => '']);
        $t->throws(InvalidArgumentException::class, static fn () => $plan125($bad, $currentSource125()));
    },
    'planner covering partial skipscan current source current-source validates schema cookie' => static function (TestRunner $t) use ($source125, $currentSource125, $plan125): void {
        $bad = $source125(['schemaCookie' => -1]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan125($bad, $currentSource125()));
    },
    'planner covering partial skipscan current source current-source validates stat4 generation' => static function (TestRunner $t) use ($source125, $currentSource125, $plan125): void {
        $bad = $source125(['stat4Generation' => -1]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan125($bad, $currentSource125()));
    },
    'planner covering partial skipscan current source current-source validates root page' => static function (TestRunner $t) use ($source125, $currentSource125, $plan125): void {
        $bad = $source125(['rootPage' => -1]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan125($bad, $currentSource125()));
    },
    'planner covering partial skipscan current source current-source validates covering list' => static function (TestRunner $t) use ($source125, $currentSource125, $plan125): void {
        $bad = $source125(['coveringColumns' => ['option_name', '']]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan125($bad, $currentSource125()));
    },
    'planner covering partial skipscan current source current-source validates needed columns' => static function (TestRunner $t) use ($source125, $currentSource125, $plan125): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan125($source125(), $currentSource125(), ['']));
    },
    'planner covering partial skipscan current source current-source validates stat4 counters' => static function (TestRunner $t) use ($source125, $currentSource125, $plan125): void {
        $bad = $currentSource125(['stat4Samples' => [['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => -1, 'nLt' => 0, 'nDLt' => 0]]]);
        $t->throws(InvalidArgumentException::class, static fn () => $plan125($source125(), $bad));
    },
];

return $tests;
