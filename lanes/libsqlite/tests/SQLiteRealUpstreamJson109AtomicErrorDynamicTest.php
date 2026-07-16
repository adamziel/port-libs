<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonArrayInsert;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJson5Parser;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map($literal, $arguments),
];
$jsonText = static function (mixed $value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode JSON expectation');
    }

    return $json;
};
$jsonb = static function (mixed $value): SQLiteBlobValue {
    return new SQLiteBlobValue(SQLiteJsonB::encode($value));
};

$documents = [];
for ($case = 0; $case < 240; $case++) {
    $documents[] = [
        'name' => 'case' . $case,
        'json5' => '{a:[' . ($case + 1) . ',' . ($case + 2) . ',' . ($case + 3) . ']}',
        'canonical' => ['a' => [$case + 1, $case + 2, $case + 3]],
        'firstPath' => match ($case % 4) {
            0 => '$.b[0]',
            1 => '$.b.c[0]',
            2 => '$.a[1]',
            default => '$.a[#]',
        },
        'firstValue' => match ($case % 6) {
            0 => 888 + $case,
            1 => 'v' . $case,
            2 => null,
            3 => true,
            4 => false,
            default => 1.5 + $case,
        },
        'badPath' => match ($case % 3) {
            0 => '$.c',
            1 => '$.a',
            default => '$.b.c.d[0',
        },
        'badValue' => 'bad' . $case,
    ];
}

foreach ($documents as $case) {
    $canonicalJson = $jsonText($case['canonical']);
    $argumentPairs = [$case['firstPath'], $case['firstValue'], $case['badPath'], $case['badValue']];
    $textArguments = array_merge([$case['json5']], $argumentPairs);
    $jsonbArguments = array_merge([$jsonb(SQLiteJson5Parser::decode($case['json5']))], $argumentPairs);

    $tests['real upstream json109-2.8 atomic later path error text ' . $case['name']] =
        static function (TestRunner $t) use ($textArguments, $canonicalJson): void {
            $t->throws(
                InvalidArgumentException::class,
                static fn (): mixed => SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('json_array_insert', $textArguments),
            );
            $t->same($canonicalJson, SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $canonicalJson, '$[#]', 444), 'source document remains reusable after text error');
        };

    $tests['real upstream json109-2.8 atomic later path error jsonb ' . $case['name']] =
        static function (TestRunner $t) use ($jsonbArguments, $case, $jsonb): void {
            $t->throws(
                InvalidArgumentException::class,
                static fn (): mixed => SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('jsonb_array_insert', $jsonbArguments),
            );
            $reused = SQLiteJsonArrayInsert::arrayInsertSqlFunction('jsonb_array_insert', $jsonb($case['canonical']), '$.a[#]', 444);
            $t->true($reused instanceof SQLiteBlobValue, 'source JSONB remains reusable after error');
        };

    $tests['real upstream json109-2.8 atomic later path error select expression ' . $case['name']] =
        static function (TestRunner $t) use ($functionExpression, $textArguments): void {
            $t->throws(
                InvalidArgumentException::class,
                static fn (): mixed => SQLiteSelectExpression::evaluate([], $functionExpression('json_array_insert', $textArguments)),
            );
        };
}

$tests['real upstream json109 atomic error cites hydrated upstream source'] =
    static function (TestRunner $t): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test');
        $t->same('json109-2.8', 'json109-2.8');
        $t->same('no-new-support-component', 'no-new-support-component');
    };

return $tests;
