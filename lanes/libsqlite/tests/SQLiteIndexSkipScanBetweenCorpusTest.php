<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexSkipScanPlan;

$rows = static fn (): array => [
    ['rowid' => 1, 'autoload' => 'no', 'option_name' => '_site_transient_update_plugins', 'option_value' => 'plugins'],
    ['rowid' => 2, 'autoload' => 'no', 'option_name' => '_transient_alpha', 'option_value' => 'alpha'],
    ['rowid' => 3, 'autoload' => 'no', 'option_name' => '_transient_beta', 'option_value' => 'beta'],
    ['rowid' => 4, 'autoload' => 'no', 'option_name' => '_transient_timeout_alpha', 'option_value' => '1700000000'],
    ['rowid' => 5, 'autoload' => 'no', 'option_name' => 'home', 'option_value' => 'https://example.test'],
    ['rowid' => 6, 'autoload' => 'yes', 'option_name' => '_transient_alpha', 'option_value' => 'yes-alpha'],
    ['rowid' => 7, 'autoload' => 'yes', 'option_name' => '_transient_timeout_beta', 'option_value' => '1700000100'],
    ['rowid' => 8, 'autoload' => 'yes', 'option_name' => 'blogname', 'option_value' => 'Example'],
    ['rowid' => 9, 'autoload' => 'auto', 'option_name' => '_transient_beta', 'option_value' => 'auto-beta'],
    ['rowid' => 10, 'autoload' => 'auto', 'option_name' => '_transient_timeout_beta', 'option_value' => '1700000200'],
    ['rowid' => 11, 'autoload' => 'auto', 'option_name' => null, 'option_value' => 'null-name'],
    ['rowid' => 12, 'autoload' => 'lazy', 'option_name' => '_TRANSIENT_GAMMA', 'option_value' => 'upper'],
    ['rowid' => 13, 'autoload' => 'lazy', 'option_name' => '_transient_delta  ', 'option_value' => 'padded'],
];

$plan = static function (
    mixed $lower = '_transient_',
    mixed $upper = '_transient_timeout_zzzz',
    bool $upperInclusive = true,
    ?int $limit = null,
    int $offset = 0,
    string $collation = 'BINARY',
) use ($rows): array {
    return SQLiteIndexSkipScanPlan::betweenRows(
        $rows(),
        'wp_options_autoload_name',
        'autoload',
        'option_name',
        $lower,
        $upper,
        $upperInclusive,
        $limit,
        $offset,
        $collation,
    );
};

$tests = [
    'skip scan between visits one loop per distinct leading autoload value' => static function (TestRunner $t) use ($plan): void {
        $p = $plan();
        $t->same(['auto', 'lazy', 'no', 'yes'], array_column($p['loops'], 'prefix'));
    },
    'skip scan between reports repeated seeks for unconstrained leading column' => static function (TestRunner $t) use ($plan): void {
        $p = $plan();
        $t->same(4, $p['estimatedSeeks']);
        $t->same(true, $p['usesSkipScan']);
    },
    'skip scan between returns rowids in composite index order' => static function (TestRunner $t) use ($plan): void {
        $t->same([9, 10, 13, 2, 3, 4, 6, 7], $plan()['rowids']);
    },
    'skip scan between preserves matching row payloads' => static function (TestRunner $t) use ($plan): void {
        $rows = $plan()['rows'];
        $t->same('auto-beta', $rows[0]['option_value']);
        $t->same('1700000100', $rows[7]['option_value']);
    },
    'skip scan between counts rows examined inside each prefix loop' => static function (TestRunner $t) use ($plan): void {
        $t->same([3, 2, 5, 3], array_column($plan()['loops'], 'examined'));
    },
    'skip scan between counts rows matched inside each prefix loop' => static function (TestRunner $t) use ($plan): void {
        $t->same([2, 1, 3, 2], array_column($plan()['loops'], 'matched'));
    },
    'skip scan between records per-prefix matched rowids' => static function (TestRunner $t) use ($plan): void {
        $loops = $plan()['loops'];
        $t->same([9, 10], $loops[0]['rowids']);
        $t->same([6, 7], $loops[3]['rowids']);
    },
    'skip scan between omits null range keys from bounded scans' => static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan()['omittedNullRangeRows']);
    },
    'skip scan between supports exclusive upper bound' => static function (TestRunner $t) use ($plan): void {
        $t->same([9, 13, 2, 3, 6], $plan('_transient_', '_transient_timeout_alpha', false)['rowids']);
    },
    'skip scan between supports inclusive upper bound' => static function (TestRunner $t) use ($plan): void {
        $t->same([9, 13, 2, 3, 4, 6], $plan('_transient_', '_transient_timeout_alpha', true)['rowids']);
    },
    'skip scan between supports open lower bound' => static function (TestRunner $t) use ($plan): void {
        $t->same([9, 10, 12, 13, 1, 2, 3, 4, 6, 7], $plan(null, '_transient_timeout_zzzz', true)['rowids']);
    },
    'skip scan between supports open upper bound' => static function (TestRunner $t) use ($plan): void {
        $t->same([9, 10, 13, 2, 3, 4, 5, 6, 7, 8], $plan('_transient_', null, true)['rowids']);
    },
    'skip scan between applies limit after prefix-loop order' => static function (TestRunner $t) use ($plan): void {
        $t->same([9, 10, 13], $plan('_transient_', '_transient_timeout_zzzz', true, 3)['rowids']);
    },
    'skip scan between applies offset before limit' => static function (TestRunner $t) use ($plan): void {
        $t->same([2, 3, 4], $plan('_transient_', '_transient_timeout_zzzz', true, 3, 3)['rowids']);
    },
    'skip scan between returns empty rows for zero limit while retaining loop evidence' => static function (TestRunner $t) use ($plan): void {
        $p = $plan('_transient_', '_transient_timeout_zzzz', true, 0);
        $t->same([], $p['rowids']);
        $t->same([2, 1, 3, 2], array_column($p['loops'], 'matched'));
    },
    'skip scan between handles offset past the result set' => static function (TestRunner $t) use ($plan): void {
        $t->same([], $plan('_transient_', '_transient_timeout_zzzz', true, 10, 99)['rowids']);
    },
    'skip scan between supports nocase range comparison' => static function (TestRunner $t) use ($plan): void {
        $t->same([9, 10, 13, 12, 2, 3, 4, 6, 7], $plan('_TRANSIENT_', '_TRANSIENT_TIMEOUT_ZZZZ', true, null, 0, 'NOCASE')['rowids']);
    },
    'skip scan between keeps binary comparison case-sensitive by default' => static function (TestRunner $t) use ($plan): void {
        $t->same([9, 10, 12, 13, 1, 2, 3, 4, 6, 7], $plan('_TRANSIENT_', '_transient_timeout_zzzz', true)['rowids']);
    },
    'skip scan between supports rtrim range comparison' => static function (TestRunner $t) use ($plan): void {
        $t->same([13], $plan('_transient_delta', '_transient_delta', true, null, 0, 'RTRIM')['rowids']);
    },
    'skip scan between excludes rtrim equal upper when exclusive' => static function (TestRunner $t) use ($plan): void {
        $t->same([], $plan('_transient_delta', '_transient_delta', false, null, 0, 'RTRIM')['rowids']);
    },
];

$rangeCases = [
    'alpha beta band' => ['_transient_alpha', '_transient_beta', true, [9, 2, 3, 6]],
    'timeout band' => ['_transient_timeout_', '_transient_timeout_zzzz', true, [10, 4, 7]],
    'timeout lower open-ended' => ['_transient_timeout_', null, true, [10, 4, 5, 7, 8]],
    'below transient band' => [null, '_site_transient_update_plugins', true, [12, 1]],
    'empty strict high band' => ['zz', null, true, []],
    'single alpha inclusive' => ['_transient_alpha', '_transient_alpha', true, [2, 6]],
    'single alpha exclusive' => ['_transient_alpha', '_transient_alpha', false, []],
    'auto beta exact band' => ['_transient_beta', '_transient_beta', true, [9, 3]],
    'padded binary exact' => ['_transient_delta  ', '_transient_delta  ', true, [13]],
    'padded binary untrimmed miss' => ['_transient_delta', '_transient_delta', true, []],
    'upper-case binary exact' => ['_TRANSIENT_GAMMA', '_TRANSIENT_GAMMA', true, [12]],
    'full text range' => [null, null, true, null],
];

foreach ($rangeCases as $label => [$lower, $upper, $inclusive, $expected]) {
    $tests["skip scan between range case {$label}"] = static function (TestRunner $t) use ($plan, $lower, $upper, $inclusive, $expected): void {
        if ($expected === null) {
            $t->throws(InvalidArgumentException::class, fn () => $plan($lower, $upper, $inclusive));
            return;
        }
        $t->same($expected, $plan($lower, $upper, $inclusive)['rowids']);
    };
}

$prefixCases = [
    'auto' => [2, [9, 10]],
    'lazy' => [1, [13]],
    'no' => [3, [2, 3, 4]],
    'yes' => [2, [6, 7]],
];

foreach ($prefixCases as $prefix => [$matched, $rowids]) {
    $tests["skip scan between loop {$prefix} records matched count"] = static function (TestRunner $t) use ($plan, $prefix, $matched): void {
        $loops = array_column($plan()['loops'], null, 'prefix');
        $t->same($matched, $loops[$prefix]['matched']);
    };
    $tests["skip scan between loop {$prefix} records rowids"] = static function (TestRunner $t) use ($plan, $prefix, $rowids): void {
        $loops = array_column($plan()['loops'], null, 'prefix');
        $t->same($rowids, $loops[$prefix]['rowids']);
    };
}

$errorCases = [
    'empty index name' => static fn () => SQLiteIndexSkipScanPlan::betweenRows($rows(), '', 'autoload', 'option_name', 'a', 'z'),
    'empty skipped column' => static fn () => SQLiteIndexSkipScanPlan::betweenRows($rows(), 'idx', '', 'option_name', 'a', 'z'),
    'empty range column' => static fn () => SQLiteIndexSkipScanPlan::betweenRows($rows(), 'idx', 'autoload', '', 'a', 'z'),
    'same skipped and range column' => static fn () => SQLiteIndexSkipScanPlan::betweenRows($rows(), 'idx', 'autoload', 'autoload', 'a', 'z'),
    'missing bounds' => static fn () => SQLiteIndexSkipScanPlan::betweenRows($rows(), 'idx', 'autoload', 'option_name', null, null),
    'negative limit' => static fn () => SQLiteIndexSkipScanPlan::betweenRows($rows(), 'idx', 'autoload', 'option_name', 'a', 'z', true, -1),
    'negative offset' => static fn () => SQLiteIndexSkipScanPlan::betweenRows($rows(), 'idx', 'autoload', 'option_name', 'a', 'z', true, null, -1),
    'unsupported collation' => static fn () => SQLiteIndexSkipScanPlan::betweenRows($rows(), 'idx', 'autoload', 'option_name', 'a', 'z', true, null, 0, 'WPSLUG'),
    'missing skipped column' => static fn () => SQLiteIndexSkipScanPlan::betweenRows([['rowid' => 1, 'option_name' => 'a']], 'idx', 'autoload', 'option_name', 'a', 'z'),
    'missing range column' => static fn () => SQLiteIndexSkipScanPlan::betweenRows([['rowid' => 1, 'autoload' => 'no']], 'idx', 'autoload', 'option_name', 'a', 'z'),
];

foreach ($errorCases as $label => $callback) {
    $tests["skip scan between rejects {$label}"] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

$numericRows = static fn (): array => [
    ['rowid' => 21, 'kind' => 'a', 'score' => 1],
    ['rowid' => 22, 'kind' => 'a', 'score' => 5],
    ['rowid' => 23, 'kind' => 'b', 'score' => 3],
    ['rowid' => 24, 'kind' => 'b', 'score' => 9],
    ['rowid' => 25, 'kind' => 'c', 'score' => null],
];

$tests['skip scan between supports numeric suffix ranges'] = static function (TestRunner $t) use ($numericRows): void {
    $p = SQLiteIndexSkipScanPlan::betweenRows($numericRows(), 'idx_kind_score', 'kind', 'score', 2, 8);
    $t->same([22, 23], $p['rowids']);
};
$tests['skip scan between reports numeric prefix loops'] = static function (TestRunner $t) use ($numericRows): void {
    $p = SQLiteIndexSkipScanPlan::betweenRows($numericRows(), 'idx_kind_score', 'kind', 'score', 2, 8);
    $t->same(['a', 'b', 'c'], array_column($p['loops'], 'prefix'));
};
$tests['skip scan between omits null numeric suffix rows'] = static function (TestRunner $t) use ($numericRows): void {
    $p = SQLiteIndexSkipScanPlan::betweenRows($numericRows(), 'idx_kind_score', 'kind', 'score', 2, 8);
    $t->same(1, $p['omittedNullRangeRows']);
};
$tests['skip scan between applies numeric exclusive upper bound'] = static function (TestRunner $t) use ($numericRows): void {
    $p = SQLiteIndexSkipScanPlan::betweenRows($numericRows(), 'idx_kind_score', 'kind', 'score', 1, 5, false);
    $t->same([21, 23], $p['rowids']);
};
$tests['skip scan between uses a single loop without skip scan flag'] = static function (TestRunner $t): void {
    $p = SQLiteIndexSkipScanPlan::betweenRows([
        ['rowid' => 31, 'autoload' => 'yes', 'option_name' => '_transient_a'],
        ['rowid' => 32, 'autoload' => 'yes', 'option_name' => '_transient_b'],
    ], 'idx', 'autoload', 'option_name', '_transient_', '_transient_z');
    $t->same(false, $p['usesSkipScan']);
    $t->same(1, $p['estimatedSeeks']);
};
$tests['skip scan between keeps returned metadata alongside rows'] = static function (TestRunner $t) use ($plan): void {
    $p = $plan('_transient_', '_transient_beta', true);
    $t->same('wp_options_autoload_name', $p['indexName']);
    $t->same('autoload', $p['skippedColumn']);
    $t->same('option_name', $p['rangeColumn']);
};

return $tests;
