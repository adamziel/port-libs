<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamBTreeIndexDynamicCorpus;

$tests = [];

$scenario = static fn (): array => SQLiteRealUpstreamBTreeIndexDynamicCorpus::partialIndexScenario();

$tests['real upstream corpus index6.test cites partial-index upstream source'] = static function (TestRunner $t) use ($scenario): void {
    $data = $scenario();

    $t->same('index6.test index6-1.1 through index6-2.104', $data['source']);
    $t->same(20, count($data['t1']));
    $t->same(999, count($data['t2']));
};

$tests['real upstream corpus index6.test index6-1.1 partial index counts'] = static function (TestRunner $t) use ($scenario): void {
    $data = $scenario();

    $t->same(14, count($data['t1a_rowids']));
    $t->same(10, count($data['t1b_rowids']));
    $t->same(14, count(array_filter($data['t1'], static fn (array $row): bool => $row['a'] !== null)));
    $t->same(20, count(array_filter($data['t1'], static fn (array $row): bool => $row['b'] !== null)));
    $t->same('ok', $data['t1_stat_steps']['index6-1.10']['integrity']);
};

foreach (range(1, 20) as $value) {
    $tests["real upstream corpus index6.test index6-1.1 t1 partial row {$value}"] = static function (TestRunner $t) use ($scenario, $value): void {
        $data = $scenario();
        $row = $data['t1'][$value - 1];
        $inT1a = in_array($row['rowid'], $data['t1a_rowids'], true);
        $inT1b = in_array($row['rowid'], $data['t1b_rowids'], true);

        $t->same($value, $row['rowid']);
        $t->same($value % 3 === 0 ? null : $value, $row['a']);
        $t->same($value, $row['b']);
        $t->same($value, $row['c']);
        $t->same($row['a'] !== null, $inT1a);
        $t->same($value > 10, $inT1b);
        $t->same($value % 3 !== 0, $row['a'] !== null);
    };
}

foreach ($scenario()['t1_stat_steps'] as $step => $expected) {
    $tests["real upstream corpus index6.test {$step} stat1 partial index row counts"] = static function (TestRunner $t) use ($scenario, $step, $expected): void {
        $stats = $scenario()['t1_stat_steps'][$step];

        $t->same($expected['table'], $stats['table']);
        $t->same($expected['t1a'], $stats['t1a']);
        $t->same($expected['t1b'], $stats['t1b']);
        $t->same($expected['integrity'], $stats['integrity']);
        $t->same(array_key_exists('t1c', $expected), array_key_exists('t1c', $stats));
        if (array_key_exists('t1c', $expected)) {
            $t->same($expected['t1c'], $stats['t1c']);
        }
    };
}

$tests['real upstream corpus index6.test index6-2.1 t2 null split count'] = static function (TestRunner $t) use ($scenario): void {
    $data = $scenario();

    $t->same(500, $data['t2a1_count']);
    $t->same(range(1, 999, 2), $data['t2a1_rowids']);
    $t->same(898, $data['t2a2_count']);
    $t->same(true, in_array(15, $data['t2a2_rowids'], true));
    $t->same(false, in_array(150, $data['t2a2_rowids'], true));
    $t->same(true, in_array(515, $data['t2a2_rowids'], true));
};

foreach (range(1, 999) as $value) {
    $tests["real upstream corpus index6.test index6-2 t2 partial planner row {$value}"] = static function (TestRunner $t) use ($scenario, $value): void {
        $data = $scenario();
        $row = $data['t2'][$value - 1];
        $inNullRejectingIndex = in_array($value, $data['t2a1_rowids'], true);
        $inRangeUnionIndex = in_array($value, $data['t2a2_rowids'], true);
        $termImpliesNullRejecting = SQLiteRealUpstreamBTreeIndexDynamicCorpus::partialIndexTermImplies(
            ['column' => 'a', 'operator' => '=', 'value' => $value],
            'a IS NOT NULL',
        );
        $termImpliesRangeUnion = SQLiteRealUpstreamBTreeIndexDynamicCorpus::partialIndexTermImplies(
            ['column' => 'a', 'operator' => '=', 'value' => $value],
            'a<100 OR a>200',
        );

        $t->same($value, $row['rowid']);
        $t->same($value, $row['a']);
        $t->same($value + 10000, $row['b']);
        $t->same(($value % 2) === 1, $inNullRejectingIndex);
        $t->same(true, $termImpliesNullRejecting);
        $t->same($value < 100 || $value > 200, $inRangeUnionIndex);
        $t->same($value < 100 || $value > 200, $termImpliesRangeUnion);
        $t->same($inRangeUnionIndex, $termImpliesRangeUnion);
    };
}

$tests['real upstream corpus index6.test partial index rejects unrelated term implication'] = static function (TestRunner $t): void {
    $t->same(false, SQLiteRealUpstreamBTreeIndexDynamicCorpus::partialIndexTermImplies(['column' => 'b', 'operator' => '=', 'value' => 15], 'a IS NOT NULL'));
    $t->same(false, SQLiteRealUpstreamBTreeIndexDynamicCorpus::partialIndexTermImplies(['column' => 'a', 'operator' => '=', 'value' => 150], 'a<100 OR a>200'));
    $t->same(false, SQLiteRealUpstreamBTreeIndexDynamicCorpus::partialIndexTermImplies(['column' => 'a', 'operator' => 'IS NOT NULL'], 'a<100 OR a>200'));
};

return $tests;
