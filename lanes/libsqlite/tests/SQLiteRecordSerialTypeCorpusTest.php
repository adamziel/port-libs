<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteRecord;

$tests = [];

$normalize = static function (mixed $value): mixed {
    if ($value instanceof SQLiteBlobValue) {
        return ['blob' => bin2hex($value->bytes)];
    }

    if (is_array($value)) {
        return array_map(static fn (mixed $item): mixed => $item instanceof SQLiteBlobValue ? ['blob' => bin2hex($item->bytes)] : $item, $value);
    }

    return $value;
};

$roundTripCases = [
    'null serial type 0' => [[null], [0]],
    'zero serial type 8' => [[0], [8]],
    'one serial type 9' => [[1], [9]],
    'positive one byte integer' => [[127], [1]],
    'negative one byte integer' => [[-128], [1]],
    'positive two byte boundary' => [[128], [2]],
    'negative two byte boundary' => [[-129], [2]],
    'positive three byte boundary' => [[32768], [3]],
    'negative three byte boundary' => [[-32769], [3]],
    'positive four byte boundary' => [[8388608], [4]],
    'negative four byte boundary' => [[-8388609], [4]],
    'positive six byte boundary' => [[2147483648], [5]],
    'negative six byte boundary' => [[-2147483649], [5]],
    'positive eight byte boundary' => [[140737488355328], [6]],
    'negative eight byte boundary' => [[-140737488355329], [6]],
    'php int max eight byte' => [[PHP_INT_MAX], [6]],
    'php int min eight byte' => [[PHP_INT_MIN], [6]],
    'float serial type 7' => [[3.5], [7]],
    'negative float serial type 7' => [[-0.25], [7]],
    'empty blob serial type 12' => [[SQLiteRecord::blob('')], [12], ['']],
    'one byte blob serial type 14' => [[SQLiteRecord::blob("\x00")], [14], ["\x00"]],
    'three byte blob serial type 18' => [[SQLiteRecord::blob("\x00\x7f\xff")], [18], ["\x00\x7f\xff"]],
    'empty text serial type 13' => [[''], [13]],
    'one byte text serial type 15' => [['a'], [15]],
    'three byte text serial type 19' => [['abc'], [19]],
    'utf8 text serial type counts bytes' => [['Å'], [17]],
    'mixed scalar record' => [[null, 0, 1, -1, 128, 3.5, 'wp'], [0, 8, 9, 1, 2, 7, 17]],
    'mixed text and blob record' => [['autoload', SQLiteRecord::blob('yes'), 'no'], [29, 18, 17], ['autoload', 'yes', 'no']],
    'large text expands header varint' => [[str_repeat('x', 60)], [133]],
    'large blob expands serial varint' => [[SQLiteRecord::blob(str_repeat("\xff", 70))], [152], [str_repeat("\xff", 70)]],
];

foreach ($roundTripCases as $name => $case) {
    $tests['record serial corpus round trip ' . $name] = static function (TestRunner $t) use ($case, $normalize): void {
        [$values, $serialTypes] = $case;
        $expectedValues = $case[2] ?? $values;
        $record = SQLiteRecord::parse(SQLiteRecord::encode($values));
        $t->same($serialTypes, $record->serialTypes, 'serial type sequence');
        $t->same($normalize($expectedValues), $normalize($record->values), 'record value round trip');
    };
}

$utf16Cases = [
    'utf16le ascii text byte count' => ['site', 2, [29]],
    'utf16be ascii text byte count' => ['site', 3, [29]],
    'utf16le non ascii text byte count' => ['Å', 2, [17]],
    'utf16be non ascii text byte count' => ['Å', 3, [17]],
    'utf16le supplementary pair byte count' => ['😀', 2, [21]],
    'utf16be supplementary pair byte count' => ['😀', 3, [21]],
    'utf16le mixed record' => [['name', '😀', 42], 2, [29, 21, 1]],
    'utf16be mixed record' => [['name', '😀', 42], 3, [29, 21, 1]],
];

foreach ($utf16Cases as $name => [$values, $encoding, $serialTypes]) {
    $tests['record serial corpus ' . $name] = static function (TestRunner $t) use ($values, $encoding, $serialTypes, $normalize): void {
        $values = is_array($values) ? $values : [$values];
        $record = SQLiteRecord::parse(SQLiteRecord::encode($values, $encoding), $encoding);
        $t->same($serialTypes, $record->serialTypes, 'UTF-16 serial type sequence');
        $t->same($normalize($values), $normalize($record->values), 'UTF-16 value round trip');
    };
}

$malformedCases = [
    'header size outside payload' => "\x05",
    'integer field truncated' => "\x02\x01",
    'reserved serial type 10 rejected' => "\x02\x0a",
    'reserved serial type 11 rejected' => "\x02\x0b",
    'odd text serial type truncated' => "\x02\x0f",
    'even blob serial type truncated' => "\x02\x10",
    'record body has trailing bytes' => "\x01x",
    'varint header is incomplete' => str_repeat("\x80", 9),
];

foreach ($malformedCases as $name => $payload) {
    $tests['record serial corpus malformed ' . $name] = static function (TestRunner $t) use ($payload): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteRecord::parse($payload));
    };
}

return $tests;
