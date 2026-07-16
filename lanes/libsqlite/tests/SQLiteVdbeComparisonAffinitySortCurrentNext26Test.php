<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeSortCompare;

$tests = [];

$left = ['yes', 'Plugin_02 ', '02', null, new SQLiteBlobValue('9'), 8];
$right = ['YES', 'plugin_2', 2, 'fallback', 9, 7];
$steps = static fn (): array => SQLiteVdbeSortCompare::comparisonSteps(
    $left,
    $right,
    'GGCGCD',
    ['NOCASE', 'RTRIM', 'BINARY', 'BINARY', 'BINARY', 'BINARY'],
    [false, false, false, false, true, false],
    [null, null, null, 'LAST', null, null]
);

$stepCases = [
    'records slot count stops at null decision' => static fn (array $s): mixed => count($s),
    'slot zero records index' => static fn (array $s): mixed => $s[0]['index'],
    'slot zero records text affinity' => static fn (array $s): mixed => $s[0]['affinity'],
    'slot zero records nocase collation' => static fn (array $s): mixed => $s[0]['collation'],
    'slot zero converts left to text' => static fn (array $s): mixed => $s[0]['leftStorageClass'],
    'slot zero converts right to text' => static fn (array $s): mixed => $s[0]['rightStorageClass'],
    'slot zero nocase compare is equal' => static fn (array $s): mixed => $s[0]['comparison'],
    'slot zero result remains equal' => static fn (array $s): mixed => $s[0]['result'],
    'slot zero is not deciding' => static fn (array $s): mixed => $s[0]['decided'],
    'slot one records rtrim collation' => static fn (array $s): mixed => $s[1]['collation'],
    'slot one keeps text affinity' => static fn (array $s): mixed => $s[1]['affinity'],
    'slot one comparison sees underscore before digit' => static fn (array $s): mixed => $s[1]['comparison'] <=> 0,
    'slot one decides before numeric slot' => static fn (array $s): mixed => $s[1]['decided'],
];

$stepExpected = [
    'records slot count stops at null decision' => 2,
    'slot zero records index' => 0,
    'slot zero records text affinity' => 'TEXT',
    'slot zero records nocase collation' => 'NOCASE',
    'slot zero converts left to text' => 'text',
    'slot zero converts right to text' => 'text',
    'slot zero nocase compare is equal' => 0,
    'slot zero result remains equal' => 0,
    'slot zero is not deciding' => false,
    'slot one records rtrim collation' => 'RTRIM',
    'slot one keeps text affinity' => 'TEXT',
    'slot one comparison sees underscore before digit' => -1,
    'slot one decides before numeric slot' => true,
];

foreach ($stepCases as $name => $read) {
    $tests['vdbe comparison affinity sort current next26 ' . $name] = static function (TestRunner $t) use ($steps, $read, $stepExpected, $name): void {
        $t->same($stepExpected[$name], $read($steps()));
    };
}

$numericSteps = static fn (): array => SQLiteVdbeSortCompare::comparisonSteps(
    ['autoload', '0002', new SQLiteBlobValue('2'), 4],
    ['autoload', 2, 2, 5],
    'GCCD',
    ['BINARY', 'BINARY', 'BINARY', 'BINARY'],
    [false, false, true, false]
);

$numericCases = [
    'numeric slot count reaches final id' => static fn (array $s): mixed => count($s),
    'numeric string coerces to integer' => static fn (array $s): mixed => $s[1]['left'],
    'numeric string storage is integer' => static fn (array $s): mixed => $s[1]['leftStorageClass'],
    'numeric integer storage remains integer' => static fn (array $s): mixed => $s[1]['rightStorageClass'],
    'numeric string comparison ties' => static fn (array $s): mixed => $s[1]['result'],
    'numeric blob coerces to integer' => static fn (array $s): mixed => $s[2]['left'],
    'numeric blob descending tie stays zero' => static fn (array $s): mixed => $s[2]['result'],
    'final id comparison decides' => static fn (array $s): mixed => $s[3]['decided'],
    'final id comparison ascends' => static fn (array $s): mixed => $s[3]['result'],
];

$numericExpected = [
    'numeric slot count reaches final id' => 4,
    'numeric string coerces to integer' => 2,
    'numeric string storage is integer' => 'integer',
    'numeric integer storage remains integer' => 'integer',
    'numeric string comparison ties' => 0,
    'numeric blob coerces to integer' => 2,
    'numeric blob descending tie stays zero' => 0,
    'final id comparison decides' => true,
    'final id comparison ascends' => -1,
];

foreach ($numericCases as $name => $read) {
    $tests['vdbe comparison affinity sort current next26 ' . $name] = static function (TestRunner $t) use ($numericSteps, $read, $numericExpected, $name): void {
        $t->same($numericExpected[$name], $read($numericSteps()));
    };
}

$nullSteps = static fn (): array => SQLiteVdbeSortCompare::comparisonSteps(
    ['yes', null],
    ['yes', 'autoload'],
    'GG',
    ['BINARY', 'BINARY'],
    [false, false],
    [null, 'LAST']
);

$nullCases = [
    'null placement reaches second slot' => static fn (array $s): mixed => count($s),
    'null placement records requested last' => static fn (array $s): mixed => $s[1]['nulls'],
    'null placement coerced left is null' => static fn (array $s): mixed => $s[1]['left'],
    'null placement right remains text' => static fn (array $s): mixed => $s[1]['rightStorageClass'],
    'null placement comparison puts null last' => static fn (array $s): mixed => $s[1]['comparison'],
    'null placement result puts null last' => static fn (array $s): mixed => $s[1]['result'],
    'null placement decides' => static fn (array $s): mixed => $s[1]['decided'],
];

$nullExpected = [
    'null placement reaches second slot' => 2,
    'null placement records requested last' => 'LAST',
    'null placement coerced left is null' => null,
    'null placement right remains text' => 'text',
    'null placement comparison puts null last' => 1,
    'null placement result puts null last' => 1,
    'null placement decides' => true,
];

foreach ($nullCases as $name => $read) {
    $tests['vdbe comparison affinity sort current next26 ' . $name] = static function (TestRunner $t) use ($nullSteps, $read, $nullExpected, $name): void {
        $t->same($nullExpected[$name], $read($nullSteps()));
    };
}

$descendingNullSteps = static fn (): array => SQLiteVdbeSortCompare::comparisonSteps(
    ['yes', null],
    ['yes', 'autoload'],
    'GG',
    ['BINARY', 'BINARY'],
    [false, true],
    [null, null]
);

$tests['vdbe comparison affinity sort current next26 default null comparison reverses under descending'] = static function (TestRunner $t) use ($descendingNullSteps): void {
    $t->same(1, $descendingNullSteps()[1]['result']);
};

$tests['vdbe comparison affinity sort current next26 explicit nulls first is not reversed by descending'] = static function (TestRunner $t): void {
    $steps = SQLiteVdbeSortCompare::comparisonSteps(['yes', null], ['yes', 'autoload'], 'GG', ['BINARY', 'BINARY'], [false, true], [null, 'FIRST']);
    $t->same(-1, $steps[1]['result']);
};

$rows = [
    ['option_id' => 10, 'autoload' => 'yes', 'option_name' => 'plugin_02', 'priority' => '02', 'kind' => 'plugin'],
    ['option_id' => 11, 'autoload' => 'YES', 'option_name' => 'Plugin_2 ', 'priority' => 2, 'kind' => 'plugin'],
    ['option_id' => 12, 'autoload' => 'yes', 'option_name' => 'plugin_10', 'priority' => '10', 'kind' => 'plugin'],
    ['option_id' => 13, 'autoload' => 'no', 'option_name' => 'cache', 'priority' => null, 'kind' => 'cache'],
    ['option_id' => 14, 'autoload' => 'no', 'option_name' => 'cache ', 'priority' => '1', 'kind' => 'cache'],
    ['option_id' => 15, 'autoload' => null, 'option_name' => 'network', 'priority' => '3', 'kind' => 'network'],
    ['option_id' => 16, 'autoload' => 'yes', 'option_name' => 'plugin_2', 'priority' => new SQLiteBlobValue('2'), 'kind' => 'plugin'],
    ['option_id' => 17, 'autoload' => 'yes', 'option_name' => 'plugin_2', 'priority' => '2.0', 'kind' => 'plugin'],
];

$trace = static fn (): array => SQLiteVdbeSortCompare::sortedRowTrace(
    $rows,
    ['autoload', 'priority', 'option_name', 'option_id'],
    'GCGD',
    ['NOCASE', 'BINARY', 'RTRIM', 'BINARY'],
    [false, false, false, false],
    ['LAST', 'LAST', 'LAST', null]
);

$traceCases = [
    'trace row count matches input' => static fn (array $r): mixed => count($r),
    'trace ordered option ids' => static fn (array $r): mixed => array_map(static fn (array $e): int => $e['row']['option_id'], $r),
    'trace first sequence is original cache row' => static fn (array $r): mixed => $r[0]['sequence'],
    'trace first has no previous sequence' => static fn (array $r): mixed => $r[0]['previousSequence'],
    'trace second compares after first' => static fn (array $r): mixed => $r[1]['comparison'],
    'trace second previous sequence points to first' => static fn (array $r): mixed => $r[1]['previousSequence'],
    'trace plugin first sequence preserves input order' => static fn (array $r): mixed => $r[2]['sequence'],
    'trace plugin second previous sequence is first plugin' => static fn (array $r): mixed => $r[3]['previousSequence'],
    'trace plugin third previous sequence is second plugin' => static fn (array $r): mixed => $r[4]['previousSequence'],
    'trace plugin equal priorities compare by option name' => static fn (array $r): mixed => $r[3]['steps'][2]['result'] <=> 0,
    'trace plugin blob priority was coerced before name compare' => static fn (array $r): mixed => $r[4]['steps'][1]['leftStorageClass'],
    'trace malformed later priority is after numeric ten' => static fn (array $r): mixed => $r[6]['row']['option_id'],
    'trace null autoload is last' => static fn (array $r): mixed => $r[7]['row']['option_id'],
    'trace last comparison records nulls last' => static fn (array $r): mixed => $r[7]['steps'][0]['nulls'],
    'trace last comparison decides on first slot' => static fn (array $r): mixed => $r[7]['steps'][0]['decided'],
];

$traceExpected = [
    'trace row count matches input' => 8,
    'trace ordered option ids' => [14, 13, 11, 10, 16, 17, 12, 15],
    'trace first sequence is original cache row' => 4,
    'trace first has no previous sequence' => null,
    'trace second compares after first' => -1,
    'trace second previous sequence points to first' => 4,
    'trace plugin first sequence preserves input order' => 1,
    'trace plugin second previous sequence is first plugin' => 1,
    'trace plugin third previous sequence is second plugin' => 0,
    'trace plugin equal priorities compare by option name' => -1,
    'trace plugin blob priority was coerced before name compare' => 'integer',
    'trace malformed later priority is after numeric ten' => 12,
    'trace null autoload is last' => 15,
    'trace last comparison records nulls last' => 'LAST',
    'trace last comparison decides on first slot' => true,
];

foreach ($traceCases as $name => $read) {
    $tests['vdbe comparison affinity sort current next26 ' . $name] = static function (TestRunner $t) use ($trace, $read, $traceExpected, $name): void {
        $t->same($traceExpected[$name], $read($trace()));
    };
}

$stableRows = [
    ['option_id' => 20, 'priority' => '02', 'name' => 'same'],
    ['option_id' => 21, 'priority' => 2, 'name' => 'same'],
    ['option_id' => 22, 'priority' => new SQLiteBlobValue('2'), 'name' => 'same'],
];
$stableTrace = static fn (): array => SQLiteVdbeSortCompare::sortedRowTrace($stableRows, ['priority', 'name'], 'CG', ['BINARY', 'BINARY']);

$stableCases = [
    'stable trace preserves duplicate key ids' => static fn (array $r): mixed => array_map(static fn (array $e): int => $e['row']['option_id'], $r),
    'stable trace second marks stable tie' => static fn (array $r): mixed => $r[1]['stableTie'],
    'stable trace third marks stable tie' => static fn (array $r): mixed => $r[2]['stableTie'],
    'stable trace second comparison is zero' => static fn (array $r): mixed => $r[1]['comparison'],
    'stable trace third comparison is zero' => static fn (array $r): mixed => $r[2]['comparison'],
    'stable trace duplicate row keeps own sequence' => static fn (array $r): mixed => $r[2]['sequence'],
    'stable trace duplicate previous sequence advances' => static fn (array $r): mixed => $r[2]['previousSequence'],
];

$stableExpected = [
    'stable trace preserves duplicate key ids' => [20, 21, 22],
    'stable trace second marks stable tie' => true,
    'stable trace third marks stable tie' => true,
    'stable trace second comparison is zero' => 0,
    'stable trace third comparison is zero' => 0,
    'stable trace duplicate row keeps own sequence' => 2,
    'stable trace duplicate previous sequence advances' => 1,
];

foreach ($stableCases as $name => $read) {
    $tests['vdbe comparison affinity sort current next26 ' . $name] = static function (TestRunner $t) use ($stableTrace, $read, $stableExpected, $name): void {
        $t->same($stableExpected[$name], $read($stableTrace()));
    };
}

$tests['vdbe comparison affinity sort current next26 sorted trace rejects missing sort column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeSortCompare::sortedRowTrace([['id' => 1]], ['missing'], 'D'));
};

$tests['vdbe comparison affinity sort current next26 comparison steps reject bad null placement'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeSortCompare::comparisonSteps([null], [1], 'D', [], [], ['MIDDLE']));
};

$tests['vdbe comparison affinity sort current next26 comparison steps reject bad collation'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeSortCompare::comparisonSteps(['a'], ['b'], 'G', ['LOCALIZED']));
};

$tests['vdbe comparison affinity sort current next26 comparison steps reject non-list records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeSortCompare::comparisonSteps(['name' => 'a'], ['b'], 'G'));
};

$tests['vdbe comparison affinity sort current next26 sorted trace rejects associative columns'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeSortCompare::sortedRowTrace([['id' => 1]], ['id' => 'id'], 'D'));
};

$tests['vdbe comparison affinity sort current next26 sorted trace rejects empty columns'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVdbeSortCompare::sortedRowTrace([['id' => 1]], [], 'D'));
};

$tests['vdbe comparison affinity sort current next26 comparison result matches compare records'] = static function (TestRunner $t) use ($left, $right): void {
    $steps = SQLiteVdbeSortCompare::comparisonSteps($left, $right, 'GGCGCD', ['NOCASE', 'RTRIM', 'BINARY', 'BINARY', 'BINARY', 'BINARY'], [false, false, false, false, true, false], [null, null, null, 'LAST', null, null]);
    $last = $steps[array_key_last($steps)];

    $t->same($last['result'], SQLiteVdbeSortCompare::compareRecords($left, $right, 'GGCGCD', ['NOCASE', 'RTRIM', 'BINARY', 'BINARY', 'BINARY', 'BINARY'], [false, false, false, false, true, false], [null, null, null, 'LAST', null, null]));
};

$tests['vdbe comparison affinity sort current next26 cursor follows sorted trace order'] = static function (TestRunner $t) use ($rows, $trace): void {
    $cursor = SQLiteVdbeSortCompare::cursor($rows, ['autoload', 'priority', 'option_name', 'option_id'], 'GCGD', ['NOCASE', 'BINARY', 'RTRIM', 'BINARY'], [false, false, false, false], ['LAST', 'LAST', 'LAST', null]);
    $seen = [];
    while (($row = $cursor->nextRow()) !== null) {
        $seen[] = $row['option_id'];
    }

    $t->same(array_map(static fn (array $entry): int => $entry['row']['option_id'], $trace()), $seen);
};

return $tests;
