<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicPlan;

$tests = [];

$columns = ['id', 'key_name', 'value_text', 'load_policy', 'counter'];
$defaults = ['load_policy' => 'lazy', 'counter' => 0];

for ($i = 0; $i < 125; ++$i) {
    $largeA = str_repeat('a', 128 + ($i % 7));
    $largeB = str_repeat('b', 128 + ($i % 5));
    $prefix = 'app_' . $i . '_';

    $replaceSuppression = static fn (): array => SQLiteUpsertReturningDynamicPlan::execute(
        [
            ['id' => 1, 'key_name' => $prefix . 'stable', 'value_text' => 'existing', 'load_policy' => 'active', 'counter' => 1],
        ],
        [
            ['id' => 2, 'key_name' => $prefix . 'stable', 'value_text' => 'candidate', 'load_policy' => 'active', 'counter' => 2],
        ],
        $columns,
        ['key_name'],
        $defaults,
        [],
        ['id', 'key_name', 'value_text', 'counter'],
        null,
        true,
        'id',
        [['id']],
    );

    $triggerOldImage = static fn (): array => SQLiteUpsertReturningDynamicPlan::execute(
        [
            ['id' => 11, 'key_name' => $prefix . 'first', 'value_text' => $largeA, 'load_policy' => 'active', 'counter' => 1],
            ['id' => 33, 'key_name' => $prefix . 'second', 'value_text' => $largeB, 'load_policy' => 'active', 'counter' => 1],
        ],
        [
            ['id' => 11, 'key_name' => $prefix . 'first', 'value_text' => $largeA, 'load_policy' => 'active', 'counter' => 2],
            ['id' => 11, 'key_name' => $prefix . 'first', 'value_text' => $largeA, 'load_policy' => 'active', 'counter' => 3],
            ['id' => 33, 'key_name' => $prefix . 'second', 'value_text' => $largeB, 'load_policy' => 'active', 'counter' => 4],
            ['id' => 33, 'key_name' => $prefix . 'second', 'value_text' => $largeB, 'load_policy' => 'active', 'counter' => 5],
        ],
        $columns,
        ['id'],
        $defaults,
        [
            'value_text' => 'excluded.value_text',
            'counter' => static fn (array $old, array $candidate): int => (int) $candidate['counter'],
        ],
        ['id', 'key_name', 'value_text', 'counter'],
    );

    $generatedReturning = static fn (): array => SQLiteUpsertReturningDynamicPlan::execute(
        [
            ['id' => 1, 'key_name' => $prefix . 'stored', 'value_text' => 'abc', 'load_policy' => 'active', 'counter' => 3],
        ],
        [
            ['id' => 1, 'key_name' => $prefix . 'stored', 'value_text' => 'updated', 'load_policy' => 'active', 'counter' => 4],
            ['id' => 2, 'key_name' => $prefix . 'inserted', 'value_text' => 'fresh', 'load_policy' => 'lazy', 'counter' => 5],
        ],
        $columns,
        ['id'],
        $defaults,
        [
            'value_text' => 'excluded.value_text',
            'counter' => 'excluded.counter',
        ],
        ['id', 'key_name', 'value_text', 'counter'],
    );

    $casePrefix = sprintf('real upstream upsert returning dynamic current %03d ', $i);

    $tests[$casePrefix . 'upsert1-1100 skips secondary-key conflict before rowid replace'] = static function (TestRunner $t) use ($replaceSuppression): void {
        $t->same(['skip'], array_column($replaceSuppression()['decisions'], 'action'));
    };
    $tests[$casePrefix . 'upsert1-1100 preserves original rowid when do-nothing target matches'] = static function (TestRunner $t) use ($replaceSuppression): void {
        $t->same(1, $replaceSuppression()['after'][0]['id']);
    };
    $tests[$casePrefix . 'upsert1-1100 emits no returning row for skipped candidate'] = static function (TestRunner $t) use ($replaceSuppression): void {
        $t->same([], $replaceSuppression()['returning_rows']);
    };
    $tests[$casePrefix . 'upsert1-1100 records candidate and old conflict key'] = static function (TestRunner $t) use ($replaceSuppression, $prefix): void {
        $decision = $replaceSuppression()['decisions'][0];
        $t->same([['key_name' => $prefix . 'stable'], ['key_name' => $prefix . 'stable']], [$decision['candidate_key'], $decision['conflict_key']]);
    };

    $tests[$casePrefix . 'upsert1-1300 trigger old image matches unchanged large text'] = static function (TestRunner $t) use ($triggerOldImage, $largeA): void {
        $t->same($largeA, $triggerOldImage()['returning_rows'][0]['_old']['value_text']);
    };
    $tests[$casePrefix . 'upsert1-1300 repeated source sees updated current image'] = static function (TestRunner $t) use ($triggerOldImage): void {
        $t->same([2, 3, 4, 5], array_column($triggerOldImage()['returning_rows'], 'counter'));
    };
    $tests[$casePrefix . 'upsert1-1300 keeps row cardinality after duplicate select input'] = static function (TestRunner $t) use ($triggerOldImage): void {
        $t->same(2, count($triggerOldImage()['after']));
    };

    $tests[$casePrefix . 'returning1-5 generated-column style returning uses final row image'] = static function (TestRunner $t) use ($generatedReturning): void {
        $t->same(['updated', 'fresh'], array_column($generatedReturning()['returning_rows'], 'value_text'));
    };
}

return $tests;
