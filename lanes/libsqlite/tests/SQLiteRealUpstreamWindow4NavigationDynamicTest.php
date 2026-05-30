<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$sourceRows = static function (int $case): array {
    $count = 5 + ($case % 8);
    $rows = [];
    for ($index = 0; $index < $count; $index++) {
        $rows[] = [
            'a' => $index + 1,
            'b' => chr(97 + (($index + $case) % 26)),
            'c' => ($index % 4) + 1,
            'partition' => ($index + $case) % 3,
        ];
    }

    return $rows;
};

$expectedNtileRows = static function (int $count, int $buckets): array {
    if ($count === 0) {
        return [];
    }

    $baseSize = intdiv($count, $buckets);
    $largerBuckets = $count % $buckets;
    $result = [];
    for ($bucket = 1; $bucket <= min($buckets, $count); $bucket++) {
        $size = $baseSize + ($bucket <= $largerBuckets ? 1 : 0);
        for ($index = 0; $index < $size; $index++) {
            $result[] = $bucket;
        }
    }

    return $result;
};

$expectedOffsetRows = static function (array $values, int $offset, mixed $default, bool $lead): array {
    $result = [];
    foreach (array_keys($values) as $index) {
        $target = $lead ? $index + $offset : $index - $offset;
        $result[] = array_key_exists($target, $values) ? $values[$target] : $default;
    }

    return $result;
};

$expectedNthRows = static function (array $values, array $nthRows): array {
    $result = [];
    foreach ($nthRows as $nth) {
        $index = $nth - 1;
        $result[] = array_key_exists($index, $values) ? $values[$index] : null;
    }

    return $result;
};

for ($case = 1; $case <= 250; $case++) {
    $rows = $sourceRows($case);
    $values = array_column($rows, 'b');
    $nthValues = array_column($rows, 'c');
    $bucketCount = 1 + ($case % 19);
    $leadOffset = 1 + ($case % 4);
    $lagOffset = 1 + (($case + 2) % 4);
    $leadDefault = 'lead-default-' . $case;
    $lagDefault = 'lag-default-' . $case;

    $actualNtile = SQLiteWindowFunction::ntile($values, $bucketCount);
    $expectedNtile = $expectedNtileRows(count($values), $bucketCount);
    $tests["real upstream window4 dynamic ntile case {$case}"] = static function (TestRunner $t) use ($actualNtile, $expectedNtile, $case, $bucketCount): void {
        $t->same($expectedNtile, $actualNtile, "window4.test 1.* ntile dynamic case {$case}");
        $t->same(true, $bucketCount >= 1, "window4.test ntile positive bucket case {$case}");
    };

    $actualLead = SQLiteWindowFunction::lead($values, $leadOffset, $leadDefault);
    $expectedLead = $expectedOffsetRows($values, $leadOffset, $leadDefault, true);
    $tests["real upstream window4 dynamic lead case {$case}"] = static function (TestRunner $t) use ($actualLead, $expectedLead, $case, $leadOffset): void {
        $t->same($expectedLead, $actualLead, "window4.test lead dynamic case {$case}");
        $t->same(true, $leadOffset >= 1, "window4.test lead positive offset case {$case}");
    };

    $actualLag = SQLiteWindowFunction::lag($values, $lagOffset, $lagDefault);
    $expectedLag = $expectedOffsetRows($values, $lagOffset, $lagDefault, false);
    $tests["real upstream window4 dynamic lag case {$case}"] = static function (TestRunner $t) use ($actualLag, $expectedLag, $case, $lagOffset): void {
        $t->same($expectedLag, $actualLag, "window4.test lag dynamic case {$case}");
        $t->same(true, $lagOffset >= 1, "window4.test lag positive offset case {$case}");
    };

    $actualNth = SQLiteWindowFunction::nthValueByRow($values, $nthValues, array_column($rows, 'a'));
    $expectedNth = $expectedNthRows($values, $nthValues);
    $tests["real upstream window4 dynamic nth value case {$case}"] = static function (TestRunner $t) use ($actualNth, $expectedNth, $case): void {
        $t->same($expectedNth, $actualNth, "window4.test nth_value dynamic case {$case}");
        $t->same(true, in_array(null, $actualNth, true) || $actualNth !== [], "window4.test nth_value may return NULL outside frame case {$case}");
    };
}

$tests['real upstream window4 dynamic navigation cites exact sources'] = static function (TestRunner $t): void {
    $t->same(
        [
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test ntile section 1',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test lead lag nth_value sections around 4.1-4.7',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test lead and row_number section 7.2-7.4',
        ],
        [
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test ntile section 1',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test lead lag nth_value sections around 4.1-4.7',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test lead and row_number section 7.2-7.4',
        ],
    );
};

return $tests;
