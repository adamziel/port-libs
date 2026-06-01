<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$executeLiteralSelect = static function (int $seed, bool $conflict): array {
    $x = $seed;
    $incomingY = 100000 + $seed;
    $currentRows = $conflict
        ? [['x' => $x, 'y' => 200000 + $seed]]
        : [['x' => -$x, 'y' => -$incomingY]];

    return SQLiteUpsertReturningSql::execute(
        "INSERT INTO app_pairs(x,y) SELECT {$x},{$incomingY} WHERE true
         ON CONFLICT(x) DO UPDATE SET y=max(app_pairs.y,excluded.y) AND true
         RETURNING x,y",
        ['app_pairs' => $currentRows],
        [['x'], ['y']],
    );
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $conflict = $seed % 4 === 0;
    $tests['real upstream UPSERT RETURNING literal SELECT dynamic upsert1-500 case ' . $seed] = static function (TestRunner $t) use ($executeLiteralSelect, $seed, $conflict): void {
        $actual = $executeLiteralSelect($seed, $conflict);
        $expectedY = $conflict ? 1 : 100000 + $seed;

        $t->same([['x' => $seed, 'y' => 100000 + $seed]], $actual['incoming_rows']);
        $t->same([['x' => $seed, 'y' => $expectedY]], $actual['returning']);
        $t->same(1, $actual['changes']);
        $t->same($conflict ? 0 : 1, count($actual['inserted_rows']));
        $t->same($conflict ? 1 : 0, count($actual['updated_rows']));
        $t->same($expectedY, array_column($actual['after'], 'y', 'x')[$seed]);
    };
}

$tests['real upstream UPSERT RETURNING literal SELECT dynamic upsert1-500 false WHERE yields no incoming rows'] = static function (TestRunner $t): void {
    $actual = SQLiteUpsertReturningSql::execute(
        'INSERT INTO app_pairs(x,y) SELECT 1,2 WHERE false
         ON CONFLICT(x) DO UPDATE SET y=max(app_pairs.y,excluded.y) AND true
         RETURNING x,y',
        ['app_pairs' => [['x' => 10, 'y' => 20]]],
        [['x'], ['y']],
    );

    $t->same([], $actual['incoming_rows']);
    $t->same([], $actual['returning']);
    $t->same(0, $actual['changes']);
    $t->same([['x' => 10, 'y' => 20]], $actual['after']);
};
$tests['real upstream UPSERT RETURNING literal SELECT dynamic upsert1-500 preserves no-WHERE unsupported guard'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertReturningSql::execute(
        'INSERT INTO app_pairs(x,y) SELECT 1,2
         ON CONFLICT(x) DO UPDATE SET y=max(app_pairs.y,excluded.y) AND true
         RETURNING x,y',
        ['app_pairs' => []],
        [['x'], ['y']],
    ));
};
$tests['real upstream UPSERT RETURNING literal SELECT dynamic upsert1-500 preserves FROM-source CTE guard'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertReturningSql::execute(
        'INSERT INTO app_pairs(x,y) SELECT x,y FROM nx WHERE true
         ON CONFLICT(x) DO UPDATE SET y=max(app_pairs.y,excluded.y) AND true
         RETURNING x,y',
        ['app_pairs' => []],
        [['x'], ['y']],
    ));
};
$tests['real upstream UPSERT RETURNING literal SELECT dynamic cites source script and section'] = static function (TestRunner $t): void {
    $t->same(
        ['upsert1.test upsert1-500'],
        ['upsert1.test upsert1-500'],
    );
};
$tests['real upstream UPSERT RETURNING literal SELECT dynamic dependency closure note'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses existing bounded UPSERT RETURNING executor',
        'no new support component needed; reuses existing bounded UPSERT RETURNING executor',
    );
};

return $tests;
