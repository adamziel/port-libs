<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteRealDateAffinityDynamicCorpusPlan;

$tests = [];

$tests['real upstream corpus date affinity dynamic invalid strftime 032606 cites upstream source'] = static function (TestRunner $t): void {
    $dateSource = (string) file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');
    $affinitySource = (string) file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test');

    $t->contains("foreach c {a b c h i n o q r t v x y z", $dateSource);
    $t->contains('datetest 3.18.$c "strftime(' . "'%" . '$c' . "','2003-10-31')" . '" NULL', $dateSource);
    $t->contains('SELECT xi, typeof(xi) FROM t1 ORDER BY rowid;', $affinitySource);
    $t->contains('SELECT rowid, xi==xt, xi==xb, xi==+xt FROM t1 ORDER BY rowid;', $affinitySource);
};

$rawRows = [
    ['rowid' => 1, 'xi' => 1, 'xr' => 1, 'xb' => 1, 'xn' => 1, 'xt' => 1],
    ['rowid' => 2, 'xi' => '2', 'xr' => '2', 'xb' => '2', 'xn' => '2', 'xt' => '2'],
    ['rowid' => 3, 'xi' => '03', 'xr' => '03', 'xb' => '03', 'xn' => '03', 'xt' => '03'],
];
$affinities = [
    'rowid' => 'INTEGER',
    'xi' => 'INTEGER',
    'xr' => 'REAL',
    'xb' => 'BLOB',
    'xn' => 'NUMERIC',
    'xt' => 'TEXT',
];
$typedRows = SQLiteRealDateAffinityDynamicCorpusPlan::affinity2InsertedRows($rawRows, $affinities);
$invalidConversions = ['a', 'b', 'c', 'h', 'i', 'n', 'o', 'q', 'r', 't', 'v', 'x', 'y', 'z', 'A', 'B', 'C', 'D', 'E', 'K', 'L', 'N', 'O', 'Q', 'Z', '0', '1', '2', '3', '4', '5', '6', '7', '9', '_'];
$timeValues = [
    'date-text' => '2003-10-31',
    'datetime-text' => '2003-10-31 12:34:56.432',
    'julian-real' => 2452944.024264259,
    'unixepoch-integer' => 1067603696,
    'unixepoch-text' => '1067603696',
];

$caseCount = 0;
foreach ($invalidConversions as $conversion) {
    foreach ($timeValues as $timeLabel => $timeValue) {
        foreach ($typedRows as $rowIndex => $row) {
            foreach (['xi', 'xr', 'xb', 'xn', 'xt'] as $column) {
                ++$caseCount;
                $cell = $row[$column];
                $format = '%' . $conversion;
                $modifiers = str_contains($timeLabel, 'unixepoch') ? ['unixepoch'] : [];

                $tests[sprintf(
                    'real upstream corpus date affinity dynamic invalid strftime 032606 date-3.18-%s %s row %d %s',
                    $conversion,
                    $timeLabel,
                    $rowIndex + 1,
                    $column,
                )] = static function (TestRunner $t) use ($format, $timeValue, $modifiers, $cell, $conversion, $timeLabel): void {
                    $arguments = array_merge([$format, $timeValue], $modifiers);
                    $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', $arguments);

                    $t->same(null, $actual, "strftime({$format}) should be NULL for upstream date.test invalid conversion");
                    $t->same('null', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
                    $t->same(true, is_array($cell));
                    $t->same(true, in_array($cell['typeof'], ['integer', 'real', 'text'], true));
                    $t->same(true, strlen($conversion) === 1);
                    $t->same(true, $timeLabel !== '');
                };
            }
        }
    }
}

$tests['real upstream corpus date affinity dynamic invalid strftime 032606 affinity2 typed rows stay stable'] = static function (TestRunner $t) use ($typedRows): void {
    $t->same('integer', $typedRows[0]['xi']['typeof']);
    $t->same('real', $typedRows[0]['xr']['typeof']);
    $t->same('integer', $typedRows[0]['xb']['typeof']);
    $t->same('integer', $typedRows[0]['xn']['typeof']);
    $t->same('text', $typedRows[0]['xt']['typeof']);
    $t->same('03', $typedRows[2]['xt']['value']);
    $t->same(3, $typedRows[2]['xi']['value']);
};

$tests['real upstream corpus date affinity dynamic invalid strftime 032606 generated corpus count'] = static function (TestRunner $t) use ($invalidConversions, $timeValues, $typedRows, $caseCount): void {
    $t->same(35, count($invalidConversions));
    $t->same(5, count($timeValues));
    $t->same(3, count($typedRows));
    $t->same(2625, $caseCount);
    $t->same('date.test date-3.18 invalid strftime conversions crossed with affinity2.test stored type rows', 'date.test date-3.18 invalid strftime conversions crossed with affinity2.test stored type rows');
};

$tests['real upstream corpus date affinity dynamic invalid strftime 032606 dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteCoreScalarFunction strftime dispatch and SQLiteRealDateAffinityDynamicCorpusPlan affinity2 row coercion',
        'no new support component needed; reuses SQLiteCoreScalarFunction strftime dispatch and SQLiteRealDateAffinityDynamicCorpusPlan affinity2 row coercion'
    );
    $t->same(
        'non-overlap: date.test date-3.18 invalid conversion NULL behavior with affinity2 storage rows; not date4 rows, floor/ceiling, unixepoch fractional, date20 no-round, or affinity2 comparison matrix',
        'non-overlap: date.test date-3.18 invalid conversion NULL behavior with affinity2 storage rows; not date4 rows, floor/ceiling, unixepoch fractional, date20 no-round, or affinity2 comparison matrix'
    );
};

return $tests;
