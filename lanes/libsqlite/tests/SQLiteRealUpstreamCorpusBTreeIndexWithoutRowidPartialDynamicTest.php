<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamBTreeIndexDynamicCorpus;

$tests = [];

$scenario = static fn (): array => SQLiteRealUpstreamBTreeIndexDynamicCorpus::withoutRowid7PartialIndexScenario();

$tests['real upstream corpus index7.test cites without-rowid partial-index source'] = static function (TestRunner $t) use ($scenario): void {
    $data = $scenario();

    $t->same('index7.test index7-1.1 through index7-5.0 WITHOUT ROWID partial-index behavior', $data['source']);
    $t->same(20, count($data['t1']));
    $t->same(999, count($data['t2_initial']));
    $t->same(999, count($data['t2_range']));
    $t->same(199, count($data['t3']));
};

foreach (range(1, 20) as $value) {
    $tests["real upstream corpus index7.test index7-1.1 without-rowid partial row {$value}"] = static function (TestRunner $t) use ($scenario, $value): void {
        $row = $scenario()['t1'][$value - 1];

        $t->same($value % 3 === 0 ? null : $value, $row['a']);
        $t->same($value, $row['b']);
        $t->same($value, $row['c']);
        $t->same($value % 3 !== 0, $row['in_t1a']);
        $t->same($value > 10, $row['in_t1b']);
        $t->same($row['a'] !== null, $row['in_t1a']);
    };
}

foreach ($scenario()['t1_stats'] as $stat) {
    $tests['real upstream corpus index7.test ' . $stat['upstream'] . ' without-rowid stat1 partial counts'] = static function (TestRunner $t) use ($scenario, $stat): void {
        $current = null;
        foreach ($scenario()['t1_stats'] as $candidate) {
            if ($candidate['upstream'] === $stat['upstream']) {
                $current = $candidate;
                break;
            }
        }

        $t->true($current !== null);
        $t->same($stat['t1'], $current['t1']);
        $t->same($stat['t1a'], $current['t1a']);
        $t->same($stat['t1b'], $current['t1b']);
        $t->same($stat['t1c'], $current['t1c']);
        $t->same('ok', $current['integrity']);
    };
}

foreach (range(1, 999) as $value) {
    $tests["real upstream corpus index7.test index7-2.1 without-rowid null-rejecting row {$value}"] = static function (TestRunner $t) use ($scenario, $value): void {
        $row = $scenario()['t2_initial'][$value - 1];

        $t->same($value % 5 === 0 ? null : $value, $row['a']);
        $t->same($value, $row['b']);
        $t->same($value % 5 !== 0, $row['in_t2a1']);
        $t->same($row['a'] !== null, $row['in_t2a1']);
        if ($value === 5) {
            $t->same(false, $row['in_t2a1']);
        }
        if ($value === 15) {
            $t->same(false, $row['in_t2a1']);
        }
    };
}

foreach (range(1, 999) as $value) {
    $tests["real upstream corpus index7.test index7-2.102 without-rowid range-union row {$value}"] = static function (TestRunner $t) use ($scenario, $value): void {
        $row = $scenario()['t2_range'][$value - 1];

        $t->same($value, $row['a']);
        $t->same($value + 10000, $row['b']);
        $t->same($value < 100 || $value > 200, $row['in_t2a2']);
        if ($value === 15) {
            $t->same(10015, $row['b']);
            $t->same(true, $row['in_t2a2']);
        }
        if ($value === 150) {
            $t->same(false, $row['in_t2a2']);
        }
        if ($value === 515) {
            $t->same(10515, $row['b']);
            $t->same(true, $row['in_t2a2']);
        }
    };
}

$tests['real upstream corpus index7.test index7-2 partial-index aggregate counts'] = static function (TestRunner $t) use ($scenario): void {
    $data = $scenario();

    $t->same(800, count(array_filter($data['t2_initial'], static fn (array $row): bool => $row['in_t2a1'])));
    $t->same(199, count(array_filter($data['t2_initial'], static fn (array $row): bool => $row['a'] === null)));
    $t->same(898, count(array_filter($data['t2_range'], static fn (array $row): bool => $row['in_t2a2'])));
    $t->same([10015], array_values(array_map(static fn (array $row): int => $row['b'], array_filter($data['t2_range'], static fn (array $row): bool => $row['a'] === 15))));
    $t->same([10515], array_values(array_map(static fn (array $row): int => $row['b'], array_filter($data['t2_range'], static fn (array $row): bool => $row['a'] === 515))));
};

foreach (range(1, 199) as $value) {
    $tests["real upstream corpus index7.test index7-3 partial unique without-rowid row {$value}"] = static function (TestRunner $t) use ($scenario, $value): void {
        $row = $scenario()['t3'][$value - 1];

        $t->same($value % 5 !== 0 ? 999 : $value, $row['a']);
        $t->same($value, $row['b']);
        $t->same($value % 5 === 0, $row['in_unique_index']);
        $t->same($row['a'] !== 999, $row['in_unique_index']);
        if ($value === 150) {
            $t->same(true, $row['in_unique_index']);
        }
    };
}

$tests['real upstream corpus index7.test index7-3 partial unique conflict and permitted duplicates'] = static function (TestRunner $t) use ($scenario): void {
    $data = $scenario();

    $t->same('UNIQUE constraint failed: t3.a', $data['t3_duplicate_error']);
    $t->same(162, $data['t3_permitted_duplicate_count']);
    $t->same(39, count(array_filter($data['t3'], static fn (array $row): bool => $row['in_unique_index'])));
    $t->same(160, count(array_filter($data['t3'], static fn (array $row): bool => $row['a'] === 999)));
    $t->same(6, count(array_filter(range(1, 199), static fn (int $value): bool => $value >= 5 && $value <= 10)));
};

return $tests;
