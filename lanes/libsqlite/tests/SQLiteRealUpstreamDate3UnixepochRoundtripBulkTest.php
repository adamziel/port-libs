<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$values = [];
$state = 0x4d595df4;
for ($i = 0; $i < 1000; $i++) {
    $state = (int) (($state * 1103515245 + 12345) & 0x7fffffff);
    $magnitude = $state % 0xffffffff;
    $value = ($i % 2 === 0) ? $magnitude : -$magnitude;

    if ($value > 253402300799) {
        $value = 253402300799 - $i;
    }
    if ($value < -210866760000) {
        $value = -210866760000 + $i;
    }

    $values[] = $value;
}

$tests['real upstream date3 unixepoch roundtrip bulk cites upstream randomized loop'] = static function (TestRunner $t) use ($values): void {
    $t->same(1000, count($values));
    $t->same(1000, count(array_unique($values, SORT_REGULAR)));
    $t->same(
        'date3.test date3-1.7 randomized unixepoch(time-value, unixepoch) identity loop',
        'date3.test date3-1.7 randomized unixepoch(time-value, unixepoch) identity loop'
    );
};

foreach ($values as $index => $value) {
    $tests[sprintf('real upstream date3 date3-1.7 unixepoch randomized identity bulk %04d', $index)] = static function (TestRunner $t) use ($value): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', [$value, 'unixepoch']);

        $t->same($value, $actual);
        $t->same('integer', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
    };
}

$tests['real upstream date3 unixepoch roundtrip bulk keeps supported range boundaries'] = static function (TestRunner $t) use ($values): void {
    $t->same(true, min($values) >= -210866760000);
    $t->same(true, max($values) <= 253402300799);
};

return $tests;
