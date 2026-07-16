<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions228 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 5, 'option_name' => 'transient_cache', 'autoload' => 'no', 'score' => 65],
];
$nextOptions228 = [
    ...$currentOptions228,
    ['option_id' => 6, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables228 = ['wp_options' => $currentOptions228];
$nextTables228 = ['wp_options' => $nextOptions228];

$sql228 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       row_number() OVER (ORDER BY score DESC, id) AS rn
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS rn
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS rn
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS rn
  FROM wp_options
 WHERE option_name IN ('siteurl')
 ORDER BY rn, label
 LIMIT 3 OFFSET 1
SQL;

$summary228 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCurrentPageDrain($sql228, $currentTables228, $nextTables228, $cursor);
$tests = [];

$tests['compound select window recursive limit current source current-page-drain status dependencies'] = static function (TestRunner $t) use ($summary228): void {
    $plan = $summary228();
    $t->same('compound-select-window-recursive-limit-current-source-current-page-drain-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-recursive-window-current-page-drain-current-page-drain', $plan['dependencies'], true));
    $t->contains('current-page drain acknowledgement', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source current-page-drain drain metadata'] = static function (TestRunner $t) use ($summary228): void {
    $drain = $summary228()['currentSourceDrainCurrentPageDrain'];
    $t->same(64, strlen($drain['currentDrainToken']));
    $t->same(2, $drain['requiredAckCount']);
    $t->same(['rewrite_rules', 'blogname'], $drain['currentLabels']);
    $t->same(['home', 'rewrite_rules', 'blogname'], $drain['nextLabels']);
    $t->same('held-until-current-page-drained', $drain['nextExposure']);
};

$tests['compound select window recursive limit current source current-page-drain next-source held labels'] = static function (TestRunner $t) use ($summary228): void {
    $drain = $summary228()['currentSourceDrainCurrentPageDrain'];
    $t->same(['home'], $drain['nextOnlyLabels']);
    $t->same([], $drain['currentOnlyLabels']);
    $t->same(['plugin_prime'], $drain['nextSkippedLabels']);
    $t->true($drain['tokensDiffer']);
    $t->same('compound-recursive-window-current-page-drain-current-page-drain-fences-next-source', $drain['yieldBoundary']);
};

$tests['compound select window recursive limit current source current-page-drain cursor carries drain token'] = static function (TestRunner $t) use ($summary228): void {
    $plan = $summary228();
    $cursor = $plan['cursor'];
    $t->same($plan['currentSourceDrainCurrentPageDrain']['currentDrainToken'], $cursor['currentDrainToken']);
    $t->same($plan['currentSourceDrainCurrentPageDrain']['requiredCurrentAcks'], $cursor['requiredCurrentAcks']);
    $t->same('held-until-current-page-drained', $cursor['nextExposure']);
};

$tests['compound select window recursive limit current source current-page-drain accepts fully acknowledged cursor'] = static function (TestRunner $t) use ($summary228): void {
    $plan = $summary228();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentAcks'] = $plan['currentSourceDrainCurrentPageDrain']['requiredCurrentAcks'];
    $again = $summary228($cursor);
    $t->same($plan['currentSourceDrainCurrentPageDrain']['currentDrainToken'], $again['currentSourceDrainCurrentPageDrain']['currentDrainToken']);
};

$tests['compound select window recursive limit current source current-page-drain rejects stale drain token'] = static function (TestRunner $t) use ($summary228): void {
    $cursor = $summary228()['cursor'];
    $cursor['currentDrainToken'] = str_repeat('e', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary228($cursor));
};

$tests['compound select window recursive limit current source current-page-drain rejects missing acknowledgement'] = static function (TestRunner $t) use ($summary228): void {
    $plan = $summary228();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentAcks'] = [$plan['currentSourceDrainCurrentPageDrain']['requiredCurrentAcks'][0]];
    $t->throws(InvalidArgumentException::class, static fn () => $summary228($cursor));
};

$tests['compound select window recursive limit current source current-page-drain rejects unexpected acknowledgement'] = static function (TestRunner $t) use ($summary228): void {
    $plan = $summary228();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentAcks'] = [...$plan['currentSourceDrainCurrentPageDrain']['requiredCurrentAcks'], str_repeat('a', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary228($cursor));
};

$tests['compound select window recursive limit current source current-page-drain executor parity'] = static function (TestRunner $t) use ($sql228, $currentTables228, $summary228): void {
    $t->same(SQLiteSelectSql::execute($sql228, $currentTables228), $summary228()['currentRows']);
};

$tests['compound select window recursive limit current source current-page-drain non overlap'] = static function (TestRunner $t) use ($summary228): void {
    $plan = $summary228();
    $t->contains('extends accepted next224', $plan['non_overlap']);
    $t->true(in_array('compound-recursive-window-current-limited-page-drain-current-page-drain', $plan['replanReasons'], true));
    $t->true(in_array('next-source-window-rank-held-until-current-page-acks-current-page-drain', $plan['replanReasons'], true));
};

foreach (range(1, 60) as $case) {
    $tests['compound select window recursive limit current source current-page-drain generated current-page drain ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 100 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 90 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_' . $case, 'autoload' => 'yes', 'score' => 80 + $case],
                ['option_id' => 4, 'option_name' => 'blog_' . $case, 'autoload' => 'yes', 'score' => 70 + $case],
                ['option_id' => 5, 'option_name' => 'cache_' . $case, 'autoload' => 'no', 'score' => 60 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 6, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 95 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (140 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS rn FROM q UNION ALL SELECT option_id AS id, option_name AS label, row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS rn FROM wp_options WHERE autoload = 'yes' INTERSECT SELECT option_id AS id, option_name AS label, row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS rn FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT option_id AS id, option_name AS label, row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS rn FROM wp_options WHERE option_name IN ('siteurl_{$case}') ORDER BY rn, label LIMIT 3 OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCurrentPageDrain($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentAcks'] = $plan['currentSourceDrainCurrentPageDrain']['requiredCurrentAcks'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCurrentPageDrain($sql, $tables, $nextTables, $cursor);

        $t->same(['rewrite_' . $case, 'blog_' . $case], $plan['currentSourceDrainCurrentPageDrain']['currentLabels']);
        $t->same(['home_' . $case, 'rewrite_' . $case, 'blog_' . $case], $plan['currentSourceDrainCurrentPageDrain']['nextLabels']);
        $t->same(['home_' . $case], $plan['currentSourceDrainCurrentPageDrain']['nextOnlyLabels']);
        $t->same(['plugin_' . $case], $plan['currentSourceDrainCurrentPageDrain']['nextSkippedLabels']);
        $t->same(2, $plan['currentSourceDrainCurrentPageDrain']['requiredAckCount']);
        $t->same($plan['currentSourceDrainCurrentPageDrain']['currentDrainToken'], $again['currentSourceDrainCurrentPageDrain']['currentDrainToken']);
    };
}

return $tests;
