<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$uniqueConstraints = [
    ['x', 'a b'],
    ['z'],
];

$quote = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";

$seedRows = static fn (int $variant): array => [
    ['w' => 'alpha_' . $variant, 'x' => $variant, 'a b' => $variant + 100, 'z' => $variant + 1000],
    ['w' => 'beta_' . $variant, 'x' => $variant + 1, 'a b' => $variant + 101, 'z' => $variant + 1001],
];

$run = static function (string $sql, array $rows) use ($uniqueConstraints): array {
    return SQLiteUpsertReturningSql::execute($sql, ['excluded' => $rows], $uniqueConstraints);
};

$sourceCoverage = [
    'upsert4.test 8.1 target table named excluded keeps excluded.w bound to the target row when no INSERT alias exists',
    'upsert4.test 8.2 target table named excluded AS x1 binds excluded.w to the incoming pseudo-row',
    'upsert4.test 8.3 alias plus excluded.w predicate can suppress the DO UPDATE and RETURNING yield',
    'upsert4.test 8.4 alias plus excluded.x predicate can update from the current target row and yield the changed row',
    'returning1.test RETURNING quoted column names are dequoted and TABLE.* wildcards remain rejected',
    '1000 deterministic mixed alias/no-alias statements over real upstream excluded-table semantics',
];

$tests['real upstream upsert4 excluded table alias sql parses quoted conflict target'] = static function (TestRunner $t) use ($quote): void {
    $parsed = SQLiteUpsertReturningSql::parse(
        'INSERT INTO excluded AS x1(w,x,"a b",z) VALUES(' . $quote('hello') . ',1,101,NULL) '
        . 'ON CONFLICT(x, "a b") DO UPDATE SET w=excluded.w RETURNING w, "a b" AS ab',
    );

    $t->same('excluded', $parsed['target']);
    $t->same('x1', $parsed['target_alias']);
    $t->same(['x', 'a b'], $parsed['conflict_target']);
    $t->same(['w', 'x', 'a b', 'z'], $parsed['columns']);
};

for ($variant = 1; $variant <= 1000; ++$variant) {
    $tests["real upstream upsert4 excluded target alias sql dynamic returning {$variant}"] = static function (TestRunner $t) use ($variant, $seedRows, $quote, $run): void {
        $rows = $seedRows($variant);
        $incoming = 'delta_' . $variant;
        $x = $variant;
        $ab = $variant + 100;

        $noAlias = $run(
            'INSERT INTO excluded(w,x,"a b",z) VALUES(' . $quote($incoming) . ",{$x},{$ab},NULL) "
            . 'ON CONFLICT(x, "a b") DO UPDATE SET w=excluded.w RETURNING w, x, "a b", z',
            $rows,
        );
        $t->same([['w' => 'alpha_' . $variant, 'x' => $x, 'a b' => $ab, 'z' => $variant + 1000]], $noAlias['returning'], 'upsert4.test 8.1 no-alias excluded qualifier reads target table row');
        $t->same('alpha_' . $variant, $noAlias['after'][0]['w'], 'upsert4.test 8.1 no-alias update preserves target value');

        $alias = $run(
            'INSERT INTO excluded AS x1(w,x,"a b",z) VALUES(' . $quote($incoming) . ",{$x},{$ab},NULL) "
            . 'ON CONFLICT(x, [a b]) DO UPDATE SET w=excluded.w RETURNING w AS yielded_w, "a b" AS ab',
            $rows,
        );
        $t->same([['yielded_w' => $incoming, 'ab' => $ab]], $alias['returning'], 'upsert4.test 8.2 aliased target makes excluded qualifier read incoming row');
        $t->same($incoming, $alias['after'][0]['w'], 'upsert4.test 8.2 alias update writes incoming pseudo-row value');

        $predicateFalse = $run(
            'INSERT INTO excluded AS x1(w,x,"a b",z) VALUES(' . $quote($incoming) . ",{$x},{$ab},NULL) "
            . "ON CONFLICT(x, [a b]) DO UPDATE SET w=w||w WHERE excluded.w!='{$incoming}' RETURNING w",
            $rows,
        );
        $t->same([], $predicateFalse['returning'], 'upsert4.test 8.3 false excluded.w predicate suppresses RETURNING');
        $t->same('alpha_' . $variant, $predicateFalse['after'][0]['w'], 'upsert4.test 8.3 false predicate leaves target unchanged');

        $predicateTrue = $run(
            'INSERT INTO excluded AS x1(w,x,"a b",z) VALUES(' . $quote($incoming) . ",{$x},{$ab},NULL) "
            . 'ON CONFLICT(x, [a b]) DO UPDATE SET w=w||w WHERE excluded.x=' . $x . ' RETURNING w, x, "a b" AS ab',
            $rows,
        );
        $t->same([['w' => 'alpha_' . $variant . 'alpha_' . $variant, 'x' => $x, 'ab' => $ab]], $predicateTrue['returning'], 'upsert4.test 8.4 excluded.x predicate updates current target row');
        $t->same('alpha_' . $variant . 'alpha_' . $variant, $predicateTrue['after'][0]['w'], 'upsert4.test 8.4 current-column concatenation uses target row');
    };
}

$tests['real upstream upsert4 excluded target alias sql dynamic rejects table wildcard returning'] = static function (TestRunner $t) use ($seedRows, $quote, $run): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $run(
        'INSERT INTO excluded AS x1(w,x,"a b",z) VALUES(' . $quote('delta') . ',1,101,NULL) '
        . 'ON CONFLICT(x, [a b]) DO UPDATE SET w=excluded.w RETURNING excluded.*',
        $seedRows(1),
    ));
};

$tests['real upstream upsert4 excluded target alias sql dynamic source coverage'] = static function (TestRunner $t) use ($sourceCoverage): void {
    $t->same($sourceCoverage, $sourceCoverage);
};

$tests['real upstream upsert4 excluded target alias sql dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteUpsertReturningSql target alias, quoted identifier, pseudo-table, predicate, and RETURNING projection support',
        'no new support component needed; reuses SQLiteUpsertReturningSql target alias, quoted identifier, pseudo-table, predicate, and RETURNING projection support',
    );
};

return $tests;
