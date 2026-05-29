<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFrameExcludeFilterCurrentSourceNext;

$currentRows = [
    ['rowid' => 1, 'site' => 1, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'bytes' => 20, 'include' => 1],
    ['rowid' => 2, 'site' => 1, 'option_name' => 'cron', 'autoload' => 'no', 'bytes' => 20, 'include' => 0],
    ['rowid' => 3, 'site' => 1, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'bytes' => 30, 'include' => '1'],
    ['rowid' => 4, 'site' => 1, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'bytes' => 40, 'include' => null],
    ['rowid' => 5, 'site' => 2, 'option_name' => 'network_active_plugins', 'autoload' => 'yes', 'bytes' => 25, 'include' => true],
    ['rowid' => 6, 'site' => 2, 'option_name' => 'network_cron', 'autoload' => 'no', 'bytes' => 25, 'include' => '0'],
    ['rowid' => 7, 'site' => 2, 'option_name' => 'network_options', 'autoload' => 'yes', 'bytes' => 35, 'include' => '0.5'],
];

$nextRows = [
    ['rowid' => 1, 'site' => 1, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'bytes' => 20, 'include' => 1],
    ['rowid' => 3, 'site' => 1, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'bytes' => 30, 'include' => '1'],
    ['rowid' => 4, 'site' => 1, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'bytes' => 40, 'include' => null],
    ['rowid' => 8, 'site' => 1, 'option_name' => 'translation_updates', 'autoload' => 'no', 'bytes' => 40, 'include' => -1],
    ['rowid' => 5, 'site' => 2, 'option_name' => 'network_active_plugins', 'autoload' => 'yes', 'bytes' => 25, 'include' => true],
    ['rowid' => 7, 'site' => 2, 'option_name' => 'network_options', 'autoload' => 'yes', 'bytes' => 35, 'include' => '0.5'],
    ['rowid' => 9, 'site' => 2, 'option_name' => 'network_theme_mods', 'autoload' => 'yes', 'bytes' => 45, 'include' => 1],
];

$options = [
    'valueColumn' => 'bytes',
    'partitionColumns' => ['site'],
    'orderColumns' => ['bytes', 'option_name'],
    'filterColumn' => 'include',
    'preceding' => 0,
    'following' => 1,
    'partitionAffinities' => ['INTEGER'],
    'orderAffinities' => ['NUMERIC', 'TEXT'],
    'orderCollations' => ['BINARY', 'NOCASE'],
    'frameUnit' => 'GROUPS',
    'exclude' => 'CURRENT ROW',
    'rowidColumn' => 'rowid',
    'separator' => '|',
];

$planFor = static fn (array $extra = []): array => SQLiteWindowFrameExcludeFilterCurrentSourceNext::plan($currentRows, $nextRows, array_replace($options, $extra));
$field = static fn (array $rows, string $name): array => array_column($rows, $name);
$currentField = static fn (string $name): array => $field($planFor()['current'], $name);
$nextField = static fn (string $name): array => $field($planFor()['next'], $name);

$tests = [];

$cases = [
    'source ids are sha256 strings' => [static fn (): mixed => [strlen($planFor()['current_source_id']), strlen($planFor()['next_source_id'])], [64, 64]],
    'source changed after copied option source mutation' => [static fn (): mixed => $planFor()['source_changed'], true],
    'current source row count recorded' => [static fn (): mixed => $planFor()['current_count'], 7],
    'next source row count recorded' => [static fn (): mixed => $planFor()['next_count'], 7],
    'current offset defaults to zero' => [static fn (): mixed => $planFor()['current_offset'], 0],
    'next offset defaults to zero' => [static fn (): mixed => $planFor()['next_offset'], 0],
    'dependency tags identify current source window slice' => [static fn (): mixed => in_array('sqlite-window-current-source-next', $planFor()['dependencies'], true), true],
    'current rows preserve sorted row numbers' => [static fn (): mixed => $currentField('row_number'), [1, 2, 3, 4, 5, 6, 7]],
    'next rows preserve sorted row numbers' => [static fn (): mixed => $nextField('row_number'), [1, 2, 3, 4, 5, 6, 7]],
    'current raw frames include current and following groups' => [static fn (): mixed => $currentField('rawFrameRowids'), [[1, 2], [2, 3], [3, 4], [4], [5, 6], [6, 7], [7]]],
    'current exclude current rowids' => [static fn (): mixed => $currentField('frameRowids'), [[2], [3], [4], [], [6], [7], []]],
    'current excluded rowids report only active row' => [static fn (): mixed => $currentField('excludedRowids'), [[1], [2], [3], [4], [5], [6], [7]]],
    'current filtered rowids apply truthiness after exclusion' => [static fn (): mixed => $currentField('filteredRowids'), [[], [3], [], [], [], [7], []]],
    'current frame values include unfiltered excluded frame' => [static fn (): mixed => $currentField('frameValues'), [[20], [30], [40], [], [25], [35], []]],
    'current filtered values omit false and null filter rows' => [static fn (): mixed => $currentField('filteredValues'), [[], [30], [], [], [], [35], []]],
    'current count all follows excluded frame' => [static fn (): mixed => $currentField('countAll'), [1, 1, 1, 0, 1, 1, 0]],
    'current count value follows filtered frame' => [static fn (): mixed => $currentField('countValue'), [0, 1, 0, 0, 0, 1, 0]],
    'current sums follow filtered frame' => [static fn (): mixed => $currentField('sum'), [null, 30, null, null, null, 35, null]],
    'current totals follow filtered frame' => [static fn (): mixed => $currentField('total'), [0.0, 30.0, 0.0, 0.0, 0.0, 35.0, 0.0]],
    'current group concat follows filtered frame' => [static fn (): mixed => $currentField('groupConcat'), [null, '30', null, null, null, '35', null]],
    'current next same partition boundary captured' => [static fn (): mixed => $currentField('nextSamePartition'), [true, true, true, false, true, true, false]],
    'current next same peer tracks option peers' => [static fn (): mixed => $currentField('nextSamePeer'), [false, false, false, false, false, false, false]],
    'next raw frames reflect deleted cron and added translation row' => [static fn (): mixed => $nextField('rawFrameRowids'), [[1, 3], [3, 4], [4, 8], [8], [5, 7], [7, 9], [9]]],
    'next frame rowids exclude current independently' => [static fn (): mixed => $nextField('frameRowids'), [[3], [4], [8], [], [7], [9], []]],
    'next excluded rowids report next source current row' => [static fn (): mixed => $nextField('excludedRowids'), [[1], [3], [4], [8], [5], [7], [9]]],
    'next filtered rowids include negative truthy update row' => [static fn (): mixed => $nextField('filteredRowids'), [[3], [], [8], [], [7], [9], []]],
    'next filtered values include translation update bytes' => [static fn (): mixed => $nextField('filteredValues'), [[30], [], [40], [], [35], [45], []]],
    'next sums are recomputed from next source only' => [static fn (): mixed => $nextField('sum'), [30, null, 40, null, 35, 45, null]],
    'next group concat uses supplied separator' => [static fn (): mixed => $nextField('groupConcat'), ['30', null, '40', null, '35', '45', null]],
    'next same peer captures translation duplicate bytes' => [static fn (): mixed => $nextField('nextSamePeer'), [false, false, false, false, false, false, false]],
    'current source id differs when next source rows differ' => [static fn (): mixed => $planFor()['current_source_id'] === $planFor()['next_source_id'], false],
    'identical sources report unchanged' => [static fn (): mixed => SQLiteWindowFrameExcludeFilterCurrentSourceNext::plan($currentRows, $currentRows, $options)['source_changed'], false],
    'resume current offset skips sorted current rows' => [static fn (): mixed => array_column($planFor(['cursor' => ['current_offset' => 2, 'next_offset' => 1]])['current'], 'currentRowid'), [3, 4, 5, 6, 7]],
    'resume next offset skips sorted next rows' => [static fn (): mixed => array_column($planFor(['cursor' => ['current_offset' => 2, 'next_offset' => 1]])['next'], 'currentRowid'), [3, 4, 8, 5, 7, 9]],
    'resume source cursor accepts matching ids' => [static fn (): mixed => (static function () use ($planFor): int {
        $plan = $planFor();
        return count($planFor(['cursor' => ['current_source_id' => $plan['current_source_id'], 'next_source_id' => $plan['next_source_id'], 'current_offset' => 6, 'next_offset' => 6]])['current']);
    })(), 1],
    'rows unit current source excludes current physical row' => [static fn (): mixed => array_column($planFor(['frameUnit' => 'ROWS', 'following' => 2])['current'], 'frameRowids'), [[2, 3], [3, 4], [4], [], [6, 7], [7], []]],
    'range unit current source follows numeric range' => [static fn (): mixed => array_column($planFor(['frameUnit' => 'RANGE', 'orderColumns' => ['bytes'], 'orderAffinities' => ['NUMERIC'], 'orderCollations' => [], 'following' => 10])['current'], 'frameRowids'), [[2, 3], [1, 3], [4], [], [6, 7], [5, 7], []]],
    'exclude group removes whole current peer group' => [static fn (): mixed => array_column($planFor(['exclude' => 'GROUP'])['current'], 'frameRowids'), [[2], [3], [4], [], [6], [7], []]],
    'exclude ties keeps current row identity' => [static fn (): mixed => array_column($planFor(['exclude' => 'TIES'])['current'], 'frameRowids'), [[1, 2], [2, 3], [3, 4], [4], [5, 6], [6, 7], [7]]],
    'no filter column keeps excluded frame as filtered rowids' => [static fn (): mixed => array_column($planFor(['filterColumn' => null])['current'], 'filteredRowids'), [[2], [3], [4], [], [6], [7], []]],
    'descending order is source-stable' => [static fn (): mixed => array_column($planFor(['orderDescending' => [true, false]])['current'], 'currentRowid'), [4, 3, 1, 2, 7, 5, 6]],
    'offset at source end returns empty current summaries' => [static fn (): mixed => $planFor(['cursor' => ['current_offset' => 7]])['current'], []],
    'offset at source end returns empty next summaries' => [static fn (): mixed => $planFor(['cursor' => ['next_offset' => 7]])['next'], []],
    'empty current source is accepted' => [static fn (): mixed => SQLiteWindowFrameExcludeFilterCurrentSourceNext::plan([], $nextRows, $options)['current'], []],
    'empty next source is accepted' => [static fn (): mixed => SQLiteWindowFrameExcludeFilterCurrentSourceNext::plan($currentRows, [], $options)['next'], []],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['window frame exclude filter current source next ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rejects stale current source cursor' => static fn () => $planFor(['cursor' => ['current_source_id' => str_repeat('0', 64)]]),
    'rejects stale next source cursor' => static fn () => $planFor(['cursor' => ['next_source_id' => str_repeat('1', 64)]]),
    'rejects negative current offset' => static fn () => $planFor(['cursor' => ['current_offset' => -1]]),
    'rejects too large next offset' => static fn () => $planFor(['cursor' => ['next_offset' => 8]]),
    'rejects non-list current rows' => static fn () => SQLiteWindowFrameExcludeFilterCurrentSourceNext::plan([2 => $currentRows[0]], $nextRows, $options),
    'rejects empty value column' => static fn () => $planFor(['valueColumn' => '']),
    'rejects empty order columns' => static fn () => $planFor(['orderColumns' => []]),
    'rejects bad order descending list' => static fn () => $planFor(['orderDescending' => ['no']]),
    'rejects bad frame offset type' => static fn () => $planFor(['following' => '1']),
    'rejects missing row value column through cursor' => static fn () => SQLiteWindowFrameExcludeFilterCurrentSourceNext::plan([['rowid' => 1]], [], $options),
];

foreach ($throws as $name => $callback) {
    $tests['window frame exclude filter current source next ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
