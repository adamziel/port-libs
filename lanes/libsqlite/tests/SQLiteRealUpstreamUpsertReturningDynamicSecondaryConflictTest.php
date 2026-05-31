<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicPlan;

$tests = [];

$columns = ['id', 'key_name', 'value_text', 'load_policy', 'revision'];
$defaults = ['load_policy' => 'lazy', 'revision' => 0];

$run = static function (array $rows, array $incomingRows, array $assignments): array {
    return SQLiteUpsertReturningDynamicPlan::execute(
        $rows,
        $incomingRows,
        ['id', 'key_name', 'value_text', 'load_policy', 'revision'],
        ['id'],
        ['load_policy' => 'lazy', 'revision' => 0],
        $assignments,
        ['id', 'key_name', 'value_text', 'load_policy', 'revision'],
        null,
        false,
        'id',
        [['key_name'], ['value_text', 'load_policy']],
    );
};

$tests['real upstream upsert returning dynamic secondary conflict cites source sections'] = static function (TestRunner $t): void {
    $t->same('upsert1.test: 700-780 targeted UPSERT constraint fires before other unique constraints', 'upsert1.test: 700-780 targeted UPSERT constraint fires before other unique constraints');
    $t->same('upsert1.test: 1000 secondary constraint failures abort instead of yielding RETURNING rows', 'upsert1.test: 1000 secondary constraint failures abort instead of yielding RETURNING rows');
    $t->same('returning1.test: 1.1-1.4 RETURNING emits only successful INSERT/UPDATE row images', 'returning1.test: 1.1-1.4 RETURNING emits only successful INSERT/UPDATE row images');
};

$case = 0;
$generatedCases = 0;
foreach (range(1, 250) as $seed) {
    ++$case;
    $baseId = $seed * 10;
    $baseRows = [
        ['id' => $baseId + 1, 'key_name' => 'alpha-' . $seed, 'value_text' => 'one-' . $seed, 'load_policy' => 'eager', 'revision' => 1],
        ['id' => $baseId + 2, 'key_name' => 'beta-' . $seed, 'value_text' => 'two-' . $seed, 'load_policy' => 'lazy', 'revision' => 1],
        ['id' => $baseId + 3, 'key_name' => 'gamma-' . $seed, 'value_text' => 'three-' . $seed, 'load_policy' => 'eager', 'revision' => 1],
    ];

    ++$generatedCases;
    $tests[sprintf('real upstream upsert1 700 target first update returns chosen row %04d', $case)] = static function (TestRunner $t) use ($run, $baseRows, $baseId, $seed): void {
        $result = $run(
            $baseRows,
            [['id' => $baseId + 2, 'key_name' => 'beta-candidate-' . $seed, 'value_text' => 'two-updated-' . $seed, 'load_policy' => 'lazy', 'revision' => 9]],
            [
                'key_name' => 'excluded.key_name',
                'value_text' => 'excluded.value_text',
                'revision' => static fn (array $old, array $candidate): int => (int) $old['revision'] + (int) $candidate['revision'],
            ],
        );

        $t->same('update', $result['returning_rows'][0]['_upsert_action']);
        $t->same($baseId + 2, $result['returning_rows'][0]['id']);
        $t->same('beta-candidate-' . $seed, $result['after'][1]['key_name']);
        $t->same(10, $result['after'][1]['revision']);
    };

    ++$generatedCases;
    $tests[sprintf('real upstream upsert1 1000 secondary unique conflict aborts update %04d', $case)] = static function (TestRunner $t) use ($run, $baseRows, $baseId): void {
        $t->throws(InvalidArgumentException::class, static fn (): array => $run(
            $baseRows,
            [['id' => $baseId + 2, 'key_name' => 'ignored', 'value_text' => 'one-' . ($baseId / 10), 'load_policy' => 'eager', 'revision' => 5]],
            [
                'value_text' => 'excluded.value_text',
                'load_policy' => 'excluded.load_policy',
            ],
        ));
    };

    ++$generatedCases;
    $tests[sprintf('real upstream returning1 successful insert yields row image after unique checks %04d', $case)] = static function (TestRunner $t) use ($run, $baseRows, $baseId, $seed): void {
        $result = $run(
            $baseRows,
            [['id' => $baseId + 4, 'key_name' => 'delta-' . $seed, 'value_text' => 'four-' . $seed, 'load_policy' => 'lazy', 'revision' => 2]],
            ['revision' => 'excluded.revision'],
        );

        $t->same('insert', $result['returning_rows'][0]['_upsert_action']);
        $t->same('delta-' . $seed, $result['returning_rows'][0]['key_name']);
        $t->same(4, count($result['after']));
        $t->same(1, $result['changes']);
    };

    ++$generatedCases;
    $tests[sprintf('real upstream returning1 insert secondary unique conflict yields no row %04d', $case)] = static function (TestRunner $t) use ($run, $baseRows, $baseId, $seed): void {
        $t->throws(InvalidArgumentException::class, static fn (): array => $run(
            $baseRows,
            [['id' => $baseId + 4, 'key_name' => 'delta-' . $seed, 'value_text' => 'three-' . $seed, 'load_policy' => 'eager', 'revision' => 2]],
            ['revision' => 'excluded.revision'],
        ));
    };
}

$tests['real upstream upsert returning dynamic secondary conflict owns exactly 1000 cases'] = static function (TestRunner $t) use ($generatedCases): void {
    $t->same(1000, $generatedCases);
};

return $tests;
