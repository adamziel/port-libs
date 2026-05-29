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

$summary228 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext228($sql228, $currentTables228, $nextTables228, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next228 status dependencies'] = static function (TestRunner $t) use ($summary228): void {
    $plan = $summary228();
    $t->same('compound-select-window-recursive-limit-current-source-next228-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-recursive-window-current-page-drain-next228', $plan['dependencies'], true));
    $t->contains('current-page drain acknowledgement', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next228 drain metadata'] = static function (TestRunner $t) use ($summary228): void {
    $drain = $summary228()['currentSourceDrainNext228'];
    $t->same(64, strlen($drain['currentDrainToken']));
    $t->same(2, $drain['requiredAckCount']);
    $t->same(['rewrite_rules', 'blogname'], $drain['currentLabels']);
    $t->same(['home', 'rewrite_rules', 'blogname'], $drain['nextLabels']);
    $t->same('held-until-current-page-drained', $drain['nextExposure']);
};

$tests['compound select window recursive limit current source next228 next-source held labels'] = static function (TestRunner $t) use ($summary228): void {
    $drain = $summary228()['currentSourceDrainNext228'];
    $t->same(['home'], $drain['nextOnlyLabels']);
    $t->same([], $drain['currentOnlyLabels']);
    $t->same(['plugin_prime'], $drain['nextSkippedLabels']);
    $t->true($drain['tokensDiffer']);
    $t->same('compound-recursive-window-next228-current-page-drain-fences-next-source', $drain['yieldBoundary']);
};

$tests['compound select window recursive limit current source next228 cursor carries drain token'] = static function (TestRunner $t) use ($summary228): void {
    $plan = $summary228();
    $cursor = $plan['cursor'];
    $t->same($plan['currentSourceDrainNext228']['currentDrainToken'], $cursor['currentDrainToken']);
    $t->same($plan['currentSourceDrainNext228']['requiredCurrentAcks'], $cursor['requiredCurrentAcks']);
    $t->same('held-until-current-page-drained', $cursor['nextExposure']);
};

$tests['compound select window recursive limit current source next228 accepts fully acknowledged cursor'] = static function (TestRunner $t) use ($summary228): void {
    $plan = $summary228();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentAcks'] = $plan['currentSourceDrainNext228']['requiredCurrentAcks'];
    $again = $summary228($cursor);
    $t->same($plan['currentSourceDrainNext228']['currentDrainToken'], $again['currentSourceDrainNext228']['currentDrainToken']);
};

$tests['compound select window recursive limit current source next228 rejects stale drain token'] = static function (TestRunner $t) use ($summary228): void {
    $cursor = $summary228()['cursor'];
    $cursor['currentDrainToken'] = str_repeat('e', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary228($cursor));
};

$tests['compound select window recursive limit current source next228 rejects missing acknowledgement'] = static function (TestRunner $t) use ($summary228): void {
    $plan = $summary228();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentAcks'] = [$plan['currentSourceDrainNext228']['requiredCurrentAcks'][0]];
    $t->throws(InvalidArgumentException::class, static fn () => $summary228($cursor));
};

$tests['compound select window recursive limit current source next228 rejects unexpected acknowledgement'] = static function (TestRunner $t) use ($summary228): void {
    $plan = $summary228();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentAcks'] = [...$plan['currentSourceDrainNext228']['requiredCurrentAcks'], str_repeat('a', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary228($cursor));
};

$tests['compound select window recursive limit current source next228 executor parity'] = static function (TestRunner $t) use ($sql228, $currentTables228, $summary228): void {
    $t->same(SQLiteSelectSql::execute($sql228, $currentTables228), $summary228()['currentRows']);
};

$tests['compound select window recursive limit current source next228 non overlap'] = static function (TestRunner $t) use ($summary228): void {
    $plan = $summary228();
    $t->contains('extends accepted next224', $plan['non_overlap']);
    $t->true(in_array('compound-recursive-window-current-limited-page-drain-next228', $plan['replanReasons'], true));
    $t->true(in_array('next-source-window-rank-held-until-current-page-acks-next228', $plan['replanReasons'], true));
};

foreach (range(1, 60) as $case) {
    $tests['compound select window recursive limit current source next228 generated current-page drain ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext228($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentAcks'] = $plan['currentSourceDrainNext228']['requiredCurrentAcks'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext228($sql, $tables, $nextTables, $cursor);

        $t->same(['rewrite_' . $case, 'blog_' . $case], $plan['currentSourceDrainNext228']['currentLabels']);
        $t->same(['home_' . $case, 'rewrite_' . $case, 'blog_' . $case], $plan['currentSourceDrainNext228']['nextLabels']);
        $t->same(['home_' . $case], $plan['currentSourceDrainNext228']['nextOnlyLabels']);
        $t->same(['plugin_' . $case], $plan['currentSourceDrainNext228']['nextSkippedLabels']);
        $t->same(2, $plan['currentSourceDrainNext228']['requiredAckCount']);
        $t->same($plan['currentSourceDrainNext228']['currentDrainToken'], $again['currentSourceDrainNext228']['currentDrainToken']);
    };
}

return $tests;
