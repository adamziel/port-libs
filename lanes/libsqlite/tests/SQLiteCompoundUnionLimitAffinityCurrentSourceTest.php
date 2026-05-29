<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundUnionLimitAffinityCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'rank_value' => 1, 'payload' => 'core', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'rank_value' => '1', 'payload' => 'core', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'active_plugins', 'rank_value' => 2, 'payload' => 'plugins', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'theme_mods', 'rank_value' => '2', 'payload' => 'theme', 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'transient_feed', 'rank_value' => 4, 'payload' => 'cache', 'autoload' => 'no'],
];
$currentStage = [
    ['option_id' => 101, 'option_name' => 'siteurl_copy', 'rank_value' => 1.0, 'payload' => 'core', 'autoload' => 'yes'],
    ['option_id' => 102, 'option_name' => 'home_text_copy', 'rank_value' => '1', 'payload' => 'core', 'autoload' => 'yes'],
    ['option_id' => 103, 'option_name' => 'plugins_copy', 'rank_value' => 2.0, 'payload' => 'plugins', 'autoload' => 'yes'],
    ['option_id' => 104, 'option_name' => 'theme_text_copy', 'rank_value' => '2', 'payload' => 'theme', 'autoload' => 'yes'],
    ['option_id' => 105, 'option_name' => 'late_stage', 'rank_value' => 5, 'payload' => 'late', 'autoload' => 'no'],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 6, 'option_name' => 'new_numeric_boundary', 'rank_value' => 3, 'payload' => 'new', 'autoload' => 'yes'],
];
$nextStage = [
    ...$currentStage,
    ['option_id' => 106, 'option_name' => 'new_numeric_duplicate', 'rank_value' => 3.0, 'payload' => 'new', 'autoload' => 'yes'],
    ['option_id' => 107, 'option_name' => 'new_text_boundary', 'rank_value' => '3', 'payload' => 'new', 'autoload' => 'yes'],
];

$currentTables = ['wp_options' => $currentOptions, 'wp_option_stage' => $currentStage];
$nextTables = ['wp_options' => $nextOptions, 'wp_option_stage' => $nextStage];
$sql = <<<'SQL'
SELECT rank_value AS rank_value, payload AS payload
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT rank_value AS rank_value, payload AS payload
  FROM wp_option_stage
 WHERE autoload = 'yes'
 ORDER BY rank_value ASC, payload ASC
 LIMIT 4 OFFSET 1
SQL;
$summary = static fn (): array => SQLiteCompoundUnionLimitAffinityCurrentSourceNextPlan::compareUnionLimitAffinity($sql, $currentTables, $nextTables);

$tests = [];

$tests['compound union limit affinity current source union-limit-affinity status dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-union-limit-affinity-current-source-ready', $plan['status']);
    $t->same([
        'sqlite-compound-union-affinity-row-key',
        'sqlite-compound-union-final-limit-boundary',
        'sqlite-current-source-next-compound-boundary',
    ], $plan['dependencies']);
};

$tests['compound union limit affinity current source union-limit-affinity compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION'], $compound['operators']);
    $t->same(['rank_value', 'payload'], $compound['orderColumns']);
    $t->same(4, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
};

$tests['compound union limit affinity current source union-limit-affinity current union keeps text affinities distinct'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentDistinctRows'];
    $t->same([1, 2, '1', '2'], array_column($rows, 'rank_value'));
    $t->same(['core', 'plugins', 'core', 'theme'], array_column($rows, 'payload'));
};

$tests['compound union limit affinity current source union-limit-affinity next union admits numeric and text boundary rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextDistinctRows'];
    $t->same([1, 2, 3, '1', '2', '3'], array_column($rows, 'rank_value'));
    $t->same(['core', 'plugins', 'new', 'core', 'theme', 'new'], array_column($rows, 'payload'));
};

$tests['compound union limit affinity current source union-limit-affinity duplicate rows use numeric equality only'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same([[1.0, 'core'], [2.0, 'plugins'], ['1', 'core'], ['2', 'theme']], array_map(static fn (array $entry): array => array_values($entry['row']), $plan['affinity']['currentSkippedDuplicates']));
    $t->same([[1.0, 'core'], [2.0, 'plugins'], [3.0, 'new'], ['1', 'core'], ['2', 'theme']], array_map(static fn (array $entry): array => array_values($entry['row']), $plan['affinity']['nextSkippedDuplicates']));
    $t->same(['numeric:1', 'string:core'], $plan['affinity']['currentSkippedDuplicates'][0]['classes']);
    $t->same(['numeric:3', 'string:new'], $plan['affinity']['nextSkippedDuplicates'][2]['classes']);
};

$tests['compound union limit affinity current source union-limit-affinity final limit current boundary'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same([2, '1', '2'], array_column($rows, 'rank_value'));
    $t->same(['plugins', 'core', 'theme'], array_column($rows, 'payload'));
    $trace = $summary()['limitTrace']['current'];
    $t->same(4, $trace['preLimitCount']);
    $t->same(3, $trace['acceptedCount']);
    $t->same([[1, 'core']], array_map(static fn (array $row): array => array_values($row), $trace['skippedBeforeOffset']));
    $t->same([], $trace['truncatedAfterLimit']);
};

$tests['compound union limit affinity current source union-limit-affinity final limit next boundary'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same([2, 3, '1', '2'], array_column($rows, 'rank_value'));
    $t->same(['plugins', 'new', 'core', 'theme'], array_column($rows, 'payload'));
    $trace = $summary()['limitTrace']['next'];
    $t->same(6, $trace['preLimitCount']);
    $t->same(4, $trace['acceptedCount']);
    $t->same([[1, 'core']], array_map(static fn (array $row): array => array_values($row), $trace['skippedBeforeOffset']));
    $t->same([['3', 'new']], array_map(static fn (array $row): array => array_values($row), $trace['truncatedAfterLimit']));
};

$tests['compound union limit affinity current source union-limit-affinity class diagnostics and replan reasons'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same(['numeric:2', 'string:plugins'], $plan['affinity']['currentBoundaryClasses']['first']);
    $t->same(['string:2', 'string:theme'], $plan['affinity']['currentBoundaryClasses']['last']);
    $t->same(['numeric:2', 'string:plugins'], $plan['affinity']['nextBoundaryClasses']['first']);
    $t->same(['string:2', 'string:theme'], $plan['affinity']['nextBoundaryClasses']['last']);
    $t->true(in_array('union-distinct-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('limited-union-boundary-changed', $plan['replanReasons'], true));
    $t->true(in_array('compound-union-limit-after-affinity-distinct', $plan['replanReasons'], true));
};

$tests['compound union limit affinity current source union-limit-affinity changed signatures name boundary'] = static function (TestRunner $t) use ($summary): void {
    $changed = implode("\n", $summary()['changedSignatures']);
    $t->true(str_contains($changed, '"rank_value":3'));
    $t->true(str_contains($changed, '"payload":"new"'));
    $t->same(false, str_contains($changed, 'late_stage'));
};

$tests['compound union limit affinity current source union-limit-affinity rejects union all'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundUnionLimitAffinityCurrentSourceNextPlan::compareUnionLimitAffinity(
        "SELECT rank_value, payload FROM wp_options UNION ALL SELECT rank_value, payload FROM wp_option_stage ORDER BY rank_value LIMIT 3",
        $currentTables,
        $currentTables,
    ));
};

$tests['compound union limit affinity current source union-limit-affinity rejects no limit'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundUnionLimitAffinityCurrentSourceNextPlan::compareUnionLimitAffinity(
        "SELECT rank_value, payload FROM wp_options UNION SELECT rank_value, payload FROM wp_option_stage ORDER BY rank_value",
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 42) as $case) {
    $tests['compound union limit affinity current source union-limit-affinity generated duplicate boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['rank_value' => 1, 'payload' => 'same_' . $case, 'autoload' => 'yes'],
                ['rank_value' => (string) (1 + ($case % 3)), 'payload' => 'text_' . $case, 'autoload' => 'yes'],
                ['rank_value' => 4 + ($case % 5), 'payload' => 'numeric_' . $case, 'autoload' => 'yes'],
            ],
            'wp_option_stage' => [
                ['rank_value' => 1.0, 'payload' => 'same_' . $case, 'autoload' => 'yes'],
                ['rank_value' => (string) (1 + ($case % 3)), 'payload' => 'text_' . $case, 'autoload' => 'yes'],
                ['rank_value' => '9', 'payload' => 'tail_' . $case, 'autoload' => 'yes'],
            ],
        ];
        $rows = SQLiteSelectSql::execute(
            "SELECT rank_value, payload FROM wp_options UNION SELECT rank_value, payload FROM wp_option_stage ORDER BY rank_value ASC, payload ASC LIMIT 4 OFFSET 1",
            $tables,
        );

        $t->same(3, count($rows));
        $t->same('numeric_' . $case, $rows[0]['payload']);
        $t->same('text_' . $case, $rows[1]['payload']);
        $t->same('tail_' . $case, $rows[2]['payload']);
    };
}

return $tests;
