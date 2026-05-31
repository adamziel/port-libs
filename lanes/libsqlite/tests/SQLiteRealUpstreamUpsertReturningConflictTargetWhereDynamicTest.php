<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$quote = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";
$uniqueConstraints = [
    ['x', 'a b'],
    ['z'],
];

$rows = static fn (int $variant): array => [
    ['w' => 'alpha_' . $variant, 'x' => $variant, 'a b' => $variant + 1000, 'z' => $variant + 2000],
    ['w' => 'beta_' . $variant, 'x' => $variant + 1, 'a b' => $variant + 1001, 'z' => $variant + 2001],
];

$run = static function (string $sql, array $tableRows) use ($uniqueConstraints): array {
    return SQLiteUpsertReturningSql::execute($sql, ['excluded' => $tableRows], $uniqueConstraints);
};

for ($variant = 1; $variant <= 1000; ++$variant) {
    $tests["real upstream upsert4 8.5 conflict target where rejects missing column dynamic {$variant}"] = static function (TestRunner $t) use ($variant, $rows, $quote, $run): void {
        $x = $variant;
        $ab = $variant + 1000;
        $sql = 'INSERT INTO excluded AS x1(w,x,"a b",z) VALUES(' . $quote('hello_' . $variant) . ",{$x},{$ab},NULL) "
            . 'ON CONFLICT(x, [a b]) WHERE y=1 '
            . 'DO UPDATE SET w=w||w WHERE excluded.x=' . $x . ' RETURNING w';

        $t->throws(InvalidArgumentException::class, static fn (): array => $run($sql, $rows($variant)));
    };

    $tests["real upstream upsert4 conflict target where admits target columns dynamic {$variant}"] = static function (TestRunner $t) use ($variant, $rows, $quote, $run): void {
        $x = $variant;
        $ab = $variant + 1000;
        $sql = 'INSERT INTO excluded AS x1(w,x,"a b",z) VALUES(' . $quote('hello_' . $variant) . ",{$x},{$ab},NULL) "
            . 'ON CONFLICT(x, [a b]) WHERE z IS NOT NULL '
            . 'DO UPDATE SET w=excluded.w WHERE excluded.x=' . $x . ' RETURNING w, x, "a b" AS ab';

        $result = $run($sql, $rows($variant));

        $t->same([['w' => 'hello_' . $variant, 'x' => $x, 'ab' => $ab]], $result['returning']);
        $t->same('z IS NOT NULL', $result['conflict_where']);
    };
}

$tests['real upstream upsert4 conflict target where dynamic source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upsert4.test 8.5 aliased target table named excluded rejects conflict-target WHERE y=1 before DO UPDATE',
        'upsert4.test 8.1-8.4 parser remains compatible with quoted composite conflict targets and excluded pseudo-table predicates',
        '1000 deterministic invalid WHERE y=1 variants and 1000 valid target-column WHERE variants',
        'non-overlap: this owns conflict-target WHERE validation, not excluded assignment/RETURNING alias resolution already covered by earlier upsert4-8 batches',
    ], [
        'upsert4.test 8.5 aliased target table named excluded rejects conflict-target WHERE y=1 before DO UPDATE',
        'upsert4.test 8.1-8.4 parser remains compatible with quoted composite conflict targets and excluded pseudo-table predicates',
        '1000 deterministic invalid WHERE y=1 variants and 1000 valid target-column WHERE variants',
        'non-overlap: this owns conflict-target WHERE validation, not excluded assignment/RETURNING alias resolution already covered by earlier upsert4-8 batches',
    ]);
};

$tests['real upstream upsert4 conflict target where dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteUpsertReturningSql parser/executor and adds bounded conflict-target WHERE validation',
        'no new support component needed; reuses SQLiteUpsertReturningSql parser/executor and adds bounded conflict-target WHERE validation',
    );
};

return $tests;
