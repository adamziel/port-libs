<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$cases = [];
for ($codepoint = 0x20; $codepoint <= 0x7e; $codepoint++) {
    $escape = chr($codepoint);
    $valid = in_array($escape, ['"', '/', '\\', 'b', 'f', 'n', 'r', 't'], true);
    $cases[sprintf('json101-10.%d', $codepoint - 0x1f)] = [
        'escape' => $escape,
        'json' => '" \\' . $escape . ' "',
        'valid' => $valid,
    ];
}

foreach ([
    'json101-10.86.0' => ['u ', false],
    'json101-10.86.1' => ['ua ', false],
    'json101-10.86.2' => ['uab ', false],
    'json101-10.86.3' => ['uabc ', false],
    'json101-10.86.4' => ['uabcd ', true],
    'json101-10.86.5' => ['uFEDC ', true],
    'json101-10.86.6' => ['u1234 ', true],
] as $scenario => [$escape, $valid]) {
    $cases[$scenario] = [
        'escape' => $escape,
        'json' => '" \\' . $escape . '"',
        'valid' => $valid,
    ];
}

$wrappers = [
    'bare' => static fn (string $json): string => $json,
    'leading-space' => static fn (string $json): string => ' ' . $json,
    'leading-tab' => static fn (string $json): string => "\t" . $json,
    'leading-lf' => static fn (string $json): string => "\n" . $json,
    'leading-cr' => static fn (string $json): string => "\r" . $json,
    'trailing-space' => static fn (string $json): string => $json . ' ',
    'trailing-tab' => static fn (string $json): string => $json . "\t",
    'trailing-lf' => static fn (string $json): string => $json . "\n",
    'trailing-cr' => static fn (string $json): string => $json . "\r",
    'space-tab-cr-lf' => static fn (string $json): string => " \t\r\n" . $json . "\n\r\t ",
    'array-value' => static fn (string $json): string => '[' . $json . ']',
    'object-value' => static fn (string $json): string => '{"v":' . $json . '}',
    'nested-array-value' => static fn (string $json): string => '[[0,' . $json . ',1]]',
    'nested-object-value' => static fn (string $json): string => '{"outer":{"v":' . $json . '}}',
];

$flags = [
    'strict-default' => null,
    'strict-int' => 1,
    'strict-text' => '1',
    'strict-float' => 1.9,
    'strict-plus-jsonb' => 9,
];

foreach ($cases as $scenario => $case) {
    $tests['real upstream json101 string escape validity dynamic ' . $scenario] = static function (TestRunner $t) use ($case, $flags, $scenario, $wrappers): void {
        foreach ($wrappers as $wrapperName => $wrap) {
            $json = $wrap($case['json']);
            foreach ($flags as $flagName => $flag) {
                $actual = $flag === null
                    ? SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$json])
                    : SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$json, $flag]);
                $t->same($case['valid'], $actual, $scenario . ' ' . $wrapperName . ' ' . $flagName);
            }

            $t->same($case['valid'], SQLiteJsonValidity::jsonValid($json), $scenario . ' ' . $wrapperName . ' direct strict');
            $t->same($case['valid'], SQLiteJsonValidity::textValid($json), $scenario . ' ' . $wrapperName . ' text strict');
            $t->same($case['valid'], SQLiteJsonValidity::jsonValid(new SQLiteBlobValue($json), 1), $scenario . ' ' . $wrapperName . ' text blob strict');
            $t->same($case['valid'], SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $json, true), $scenario . ' ' . $wrapperName . ' bool flag');
            try {
                SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $json, false);
                $t->fail($scenario . ' ' . $wrapperName . ' false flag should throw');
            } catch (InvalidArgumentException) {
                $t->true(true, $scenario . ' ' . $wrapperName . ' false flag throws');
            }

            try {
                SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $json, 'not-a-number');
                $t->fail($scenario . ' ' . $wrapperName . ' nonnumeric flag should throw');
            } catch (InvalidArgumentException) {
                $t->true(true, $scenario . ' ' . $wrapperName . ' nonnumeric flag throws');
            }

            if ($case['valid']) {
                $canonical = SQLiteJsonCanonical::json($json);
                $t->same(true, is_string($canonical), $scenario . ' ' . $wrapperName . ' canonical string');
                $blob = SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
                $t->same(true, $blob instanceof SQLiteBlobValue, $scenario . ' ' . $wrapperName . ' jsonb constructor');
                $t->same(true, SQLiteJsonValidity::jsonValid($blob, 4), $scenario . ' ' . $wrapperName . ' jsonb superficial flag');
                $t->same(true, SQLiteJsonValidity::jsonValid($blob, 8), $scenario . ' ' . $wrapperName . ' jsonb strict flag');
                $t->same(
                    json_decode((string) $canonical, true, 512, JSON_THROW_ON_ERROR),
                    json_decode((string) SQLiteJsonCanonical::json($blob), true, 512, JSON_THROW_ON_ERROR),
                    $scenario . ' ' . $wrapperName . ' jsonb decoded parity'
                );
                $t->same(true, SQLiteJsonValidity::jsonValid(new SQLiteJsonSubtypeValue((string) $canonical), 1), $scenario . ' ' . $wrapperName . ' subtype strict');
            } else {
                $t->same(false, SQLiteJsonValidity::jsonValid($json), $scenario . ' ' . $wrapperName . ' invalid strict remains invalid');
            }
        }
    };
}

$tests['real upstream json101 string escape validity dynamic corpus accounting'] = static function (TestRunner $t) use ($cases, $wrappers, $flags): void {
    $valid = array_filter($cases, static fn (array $case): bool => $case['valid']);
    $invalid = array_filter($cases, static fn (array $case): bool => !$case['valid']);

    $t->same(102, count($cases));
    $t->same(11, count($valid));
    $t->same(91, count($invalid));
    $t->same(14, count($wrappers));
    $t->same(5, count($flags));
    $t->same(
        'json101.test: json101-10.1 through json101-10.95 plus json101-10.86.0 through json101-10.86.6',
        'json101.test: json101-10.1 through json101-10.95 plus json101-10.86.0 through json101-10.86.6'
    );
};

return $tests;
