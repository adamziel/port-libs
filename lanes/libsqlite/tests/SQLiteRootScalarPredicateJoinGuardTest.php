<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteSelectPredicate;
use PortLibs\LibSqlite\SQLiteSelectResult;

$tests = [];

$tests['root scalar predicate join guard rejects malformed direct datetime value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['bad-date']));
    $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['2026-05-26', 'weekday 7']));
    $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['01:02:03x']));
};

$tests['root scalar predicate join guard requires callback for column match residuals'] = static function (TestRunner $t): void {
    $rows = [
        ['setting_id' => 1, 'key_name' => 'siteurl'],
    ];

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectPredicate::filter($rows, [
        'operator' => 'MATCH',
        'left' => ['column' => 'key_name'],
        'right' => 'siteurl',
    ]));
    $t->same(true, SQLiteSelectPredicate::evaluate([], [
        'operator' => 'MATCH',
        'left' => ['type' => 'literal', 'value' => 'siteurl'],
        'right' => ['type' => 'literal', 'value' => 'siteurl'],
    ]));
};

$tests['root scalar predicate join guard rejects empty left join null-extension metadata'] = static function (TestRunner $t): void {
    $leftRows = [
        ['setting_id' => 1, 'key_name' => 'siteurl'],
    ];
    $rightRows = [
        ['setting_id' => 1, 'meta_key' => 'public'],
    ];

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectResult::leftJoin(
        $leftRows,
        $rightRows,
        static fn (): bool => false,
        []
    ));
    $t->same([], SQLiteSelectResult::leftJoin([], $rightRows, static fn (): bool => true, []));
};

return $tests;
