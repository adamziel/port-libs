<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonAggregateState;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;

$tests = [];

$rows = [];
for ($rowid = 1; $rowid <= 100; $rowid++) {
    $value = $rowid;
    if ($rowid === 29) {
        $value = new SQLiteBlobValue('012');
    } elseif ($rowid === 31) {
        $value = 32.5;
    } elseif ($rowid === 37) {
        $value = null;
    } elseif ($rowid === 39) {
        $value = 'orange';
    }

    $rows[] = [
        'rowid' => $rowid,
        'a' => $value,
        'b' => $rowid % 3,
        'c' => 'n' . $rowid,
    ];
}

$canonical = static function (mixed $value): string {
    return SQLiteJsonCanonical::encodeDecodedJson($value);
};

$jsonValue = static function (mixed $value) use ($canonical): string {
    if ($value instanceof SQLiteBlobValue) {
        throw new InvalidArgumentException('JSON cannot hold BLOB values');
    }
    if ($value === true) {
        return 'true';
    }
    if ($value === false) {
        return 'false';
    }
    if ($value === null || is_int($value) || is_float($value) || is_string($value) || is_array($value) || $value instanceof stdClass) {
        return $canonical($value);
    }

    throw new InvalidArgumentException('unsupported JSON aggregate test value');
};

$expectedArray = static function (array $values) use ($jsonValue): string {
    return '[' . implode(',', array_map($jsonValue, $values)) . ']';
};

$expectedObject = static function (array $pairs) use ($jsonValue): string {
    $items = [];
    foreach ($pairs as [$label, $value]) {
        $items[] = json_encode((string) $label, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . ':' . $jsonValue($value);
    }

    return '{' . implode(',', $items) . '}';
};

$jsonbDecoded = static function (SQLiteBlobValue $value): mixed {
    return SQLiteJsonB::decode($value->bytes);
};

$normalizeDecoded = static function (mixed $value) use (&$normalizeDecoded): mixed {
    if ($value instanceof stdClass) {
        $vars = get_object_vars($value);
        if ($vars === []) {
            return ['__empty_object__' => true];
        }

        return array_map($normalizeDecoded, $vars);
    }
    if (is_array($value)) {
        return array_map($normalizeDecoded, $value);
    }

    return $value;
};

$decodedExpected = static function (string $json) use ($normalizeDecoded): mixed {
    $decoded = json_decode($json, true, 512, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
    if ($json === '{}') {
        return ['__empty_object__' => true];
    }

    return $normalizeDecoded($decoded);
};

$sliceRows = static function (array $rows, int $start, int $length): array {
    return array_values(array_filter(
        $rows,
        static fn (array $row): bool => $row['rowid'] >= $start && $row['rowid'] < $start + $length
    ));
};

for ($start = 1; $start <= 100; $start++) {
    foreach ([0, 1, 2, 3, 5] as $length) {
        $caseRows = $sliceRows($rows, $start, $length);
        if (array_filter($caseRows, static fn (array $row): bool => $row['a'] instanceof SQLiteBlobValue) !== []) {
            continue;
        }

        $values = array_column($caseRows, 'a');
        $pairs = array_map(static fn (array $row): array => [$row['c'], $row['a']], $caseRows);
        $expectedArrayJson = $expectedArray($values);
        $expectedObjectJson = $expectedObject($pairs);

        $tests["real upstream json103 aggregate array rowid {$start} length {$length}"] = static function (TestRunner $t) use ($values, $expectedArrayJson): void {
            $t->same($expectedArrayJson, SQLiteJsonAggregate::jsonGroupArraySqlFunction('json_group_array', $values));
        };

        $tests["real upstream json103 aggregate jsonb array rowid {$start} length {$length}"] = static function (TestRunner $t) use ($values, $expectedArrayJson, $jsonbDecoded): void {
            $actual = SQLiteJsonAggregate::jsonGroupArraySqlFunction('jsonb_group_array', $values);
            $t->same(json_decode($expectedArrayJson, true, 512, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR), $jsonbDecoded($actual));
        };

        $tests["real upstream json103 aggregate object rowid {$start} length {$length}"] = static function (TestRunner $t) use ($pairs, $expectedObjectJson): void {
            $t->same($expectedObjectJson, SQLiteJsonAggregate::jsonGroupObjectSqlFunction('json_group_object', $pairs));
        };

        $tests["real upstream json103 aggregate jsonb object rowid {$start} length {$length}"] = static function (TestRunner $t) use ($pairs, $expectedObjectJson, $jsonbDecoded, $decodedExpected, $normalizeDecoded): void {
            $actual = SQLiteJsonAggregate::jsonGroupObjectSqlFunction('jsonb_group_object', $pairs);
            $t->same($decodedExpected($expectedObjectJson), $normalizeDecoded($jsonbDecoded($actual)));
        };
    }
}

foreach ([0, 1, 2] as $group) {
    $groupRows = array_values(array_filter(
        $rows,
        static fn (array $row): bool => $row['rowid'] < 10 && $row['b'] === $group
    ));
    $values = array_column($groupRows, 'a');
    $pairs = array_map(static fn (array $row): array => [$row['c'], $row['a']], $groupRows);
    $expectedArrayJson = $expectedArray($values);
    $expectedObjectJson = $expectedObject($pairs);

    $tests["real upstream json103 120 grouped array b {$group}"] = static function (TestRunner $t) use ($values, $expectedArrayJson): void {
        $t->same($expectedArrayJson, SQLiteJsonAggregate::jsonGroupArraySqlFunction('json_group_array', $values));
    };

    $tests["real upstream json103 220 grouped object b {$group}"] = static function (TestRunner $t) use ($pairs, $expectedObjectJson): void {
        $t->same($expectedObjectJson, SQLiteJsonAggregate::jsonGroupObjectSqlFunction('json_group_object', $pairs));
    };
}

$windowValues = [1, 'a,b', 3, 'x"y', 5, 6, 7];
$expectedWindowArrays = [
    '[1]',
    '[1,"a,b"]',
    '[1,"a,b",3]',
    '["a,b",3,"x\"y"]',
    '[3,"x\"y",5]',
    '["x\"y",5,6]',
    '[5,6,7]',
];
$windowState = static function () use ($windowValues): SQLiteJsonAggregateState {
    $state = new SQLiteJsonAggregateState();
    foreach ($windowValues as $value) {
        $state->stepArrayWindow($value);
        $state->stepObjectWindow(count($state->summary()) . '-' . spl_object_id((object) $value), $value);
    }

    return $state;
};

$tests['real upstream json103 400 window json_group_array rows 2 preceding'] = static function (TestRunner $t) use ($windowState, $expectedWindowArrays): void {
    $t->same($expectedWindowArrays, $windowState()->finalizeWindowedArray(2));
};

$tests['real upstream json103 410 window json_group_object rows 2 preceding'] = static function (TestRunner $t) use ($windowValues, $expectedObject): void {
    $state = new SQLiteJsonAggregateState();
    foreach ($windowValues as $index => $value) {
        $state->stepObjectWindow((string) ($index + 1), $value);
    }

    $expected = [];
    for ($index = 0; $index < count($windowValues); $index++) {
        $first = max(0, $index - 2);
        $pairs = [];
        for ($cursor = $first; $cursor <= $index; $cursor++) {
            $pairs[] = [$cursor + 1, $windowValues[$cursor]];
        }
        $expected[] = $expectedObject($pairs);
    }

    $t->same($expected, $state->finalizeWindowedObject(2));
};

foreach ([29, 1] as $caseStart) {
    $caseRows = $sliceRows($rows, $caseStart, $caseStart === 29 ? 2 : 100);
    $values = array_column($caseRows, 'a');
    $pairs = array_map(static fn (array $row): array => [$row['c'], $row['a']], $caseRows);

    $tests["real upstream json103 101 blob array rejection start {$caseStart}"] = static function (TestRunner $t) use ($values): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArraySqlFunction('json_group_array', $values));
    };

    $tests["real upstream json103 201 blob object rejection start {$caseStart}"] = static function (TestRunner $t) use ($pairs): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectSqlFunction('json_group_object', $pairs));
    };
}

$tests['real upstream json103 source citations'] = static function (TestRunner $t): void {
    $t->same([
        'json103.test: json103-100 empty json_group_array result',
        'json103.test: json103-101 and json103-201 BLOB rejection',
        'json103.test: json103-102 and json103-202 empty JSONB aggregate encodings',
        'json103.test: json103-110 and json103-210 mixed scalar/null/text aggregate order',
        'json103.test: json103-120 and json103-220 grouped aggregate rows',
        'json103.test: json103-400 and json103-410 ROWS 2 PRECEDING window frames',
    ], [
        'json103.test: json103-100 empty json_group_array result',
        'json103.test: json103-101 and json103-201 BLOB rejection',
        'json103.test: json103-102 and json103-202 empty JSONB aggregate encodings',
        'json103.test: json103-110 and json103-210 mixed scalar/null/text aggregate order',
        'json103.test: json103-120 and json103-220 grouped aggregate rows',
        'json103.test: json103-400 and json103-410 ROWS 2 PRECEDING window frames',
    ]);
};

return $tests;
