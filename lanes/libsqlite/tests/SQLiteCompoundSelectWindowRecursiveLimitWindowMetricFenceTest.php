<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions236 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 140],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 118],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 96],
    ['option_id' => 4, 'option_name' => 'plugin_old', 'autoload' => 'yes', 'score' => 82],
    ['option_id' => 5, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 44],
];
$nextOptions236 = [
    ...$currentOptions236,
    ['option_id' => 6, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 128],
    ['option_id' => 7, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 107],
];
$currentTables236 = ['wp_options' => $currentOptions236];
$nextTables236 = ['wp_options' => $nextOptions236];

$sql236 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 152)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 9
      FROM q
     WHERE id < 9
     ORDER BY score DESC
     LIMIT 8 OFFSET 1
)
SELECT id,
       label,
       avg(score) OVER (
           ORDER BY score DESC
           ROWS BETWEEN 1 PRECEDING AND CURRENT ROW
       ) AS metric
  FROM q
UNION
SELECT option_id AS id,
       option_name AS label,
       first_value(score) OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
           ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING
       ) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT id, label, metric
  FROM (
       SELECT id,
              label,
              avg(score) OVER (
                  ORDER BY score DESC
                  ROWS BETWEEN 1 PRECEDING AND CURRENT ROW
              ) AS metric
         FROM q
       UNION
       SELECT option_id AS id,
              option_name AS label,
              first_value(score) OVER (
                  PARTITION BY autoload
                  ORDER BY score DESC, option_id
                  ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING
              ) AS metric
         FROM wp_options
        WHERE autoload = 'yes'
  )
EXCEPT
SELECT id, label, metric
  FROM (
       SELECT id,
              label,
              avg(score) OVER (
                  ORDER BY score DESC
                  ROWS BETWEEN 1 PRECEDING AND CURRENT ROW
              ) AS metric
         FROM q
        WHERE id = 4
       UNION
       SELECT option_id AS id,
              option_name AS label,
              first_value(score) OVER (
                  PARTITION BY autoload
                  ORDER BY score DESC, option_id
                  ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING
              ) AS metric
         FROM wp_options
        WHERE option_name = 'plugin_old'
  )
 ORDER BY metric DESC, id
 LIMIT 6 OFFSET 1
SQL;

$summary236 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareWindowMetricFence($sql236, $currentTables236, $nextTables236, $cursor);
$tests = [];

$tests['compound select window recursive limit current source window-metric-fence status dependencies'] = static function (TestRunner $t) use ($summary236): void {
    $plan = $summary236();
    $t->same('compound-select-window-recursive-limit-current-source-window-metric-fence-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-recursive-window-metric-fence-window-metric-fence', $plan['dependencies'], true));
    $t->contains('per-row window metric acknowledgement fence', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source window-metric-fence metric fence shape'] = static function (TestRunner $t) use ($summary236): void {
    $fence = $summary236()['windowMetricFenceWindowMetricFence'];
    $t->same(64, strlen($fence['currentMetricFenceToken']));
    $t->same(6, $fence['requiredMetricAckCount']);
    $t->same(6, count($fence['requiredMetricAcks']));
    $t->same('held-until-current-window-metric-acks-match', $fence['nextExposure']);
    $t->same('compound-window-window-metric-fence-current-window-metric-fence', $fence['yieldBoundary']);
};

$tests['compound select window recursive limit current source window-metric-fence current and next labels'] = static function (TestRunner $t) use ($summary236): void {
    $fence = $summary236()['windowMetricFenceWindowMetricFence'];
    $t->same(['siteurl', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'home', 'seed:2:3:4:5:6'], $fence['currentMetricLabels']);
    $t->same(['siteurl', 'seed:2:3', 'seed:2:3:4', 'plugin_prime', 'seed:2:3:4:5', 'home'], $fence['nextMetricLabels']);
    $t->same(['plugin_prime'], $fence['nextOnlyMetricLabels']);
};

$tests['compound select window recursive limit current source window-metric-fence detects metric drift'] = static function (TestRunner $t) use ($summary236): void {
    $fence = $summary236()['windowMetricFenceWindowMetricFence'];
    $t->same(['seed:2:3:4:5', 'home'], $fence['metricDriftLabels']);
    $t->true($fence['currentMetricSignatures'][1] !== $fence['nextMetricSignatures'][2]);
};

$tests['compound select window recursive limit current source window-metric-fence cursor carries metric fence'] = static function (TestRunner $t) use ($summary236): void {
    $plan = $summary236();
    $cursor = $plan['cursor'];
    $t->same($plan['windowMetricFenceWindowMetricFence']['currentMetricFenceToken'], $cursor['currentMetricFenceTokenWindowMetricFence']);
    $t->same($plan['windowMetricFenceWindowMetricFence']['requiredMetricAcks'], $cursor['requiredMetricAcksWindowMetricFence']);
    $t->same('held-until-current-window-metric-acks-match', $cursor['nextExposure']);
};

$tests['compound select window recursive limit current source window-metric-fence accepts metric acknowledgements'] = static function (TestRunner $t) use ($summary236): void {
    $plan = $summary236();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedMetricAcksWindowMetricFence'] = $plan['windowMetricFenceWindowMetricFence']['requiredMetricAcks'];
    $again = $summary236($cursor);
    $t->same($plan['windowMetricFenceWindowMetricFence']['currentMetricFenceToken'], $again['windowMetricFenceWindowMetricFence']['currentMetricFenceToken']);
};

$tests['compound select window recursive limit current source window-metric-fence rejects stale metric token'] = static function (TestRunner $t) use ($summary236): void {
    $cursor = $summary236()['cursor'];
    $cursor['currentMetricFenceTokenWindowMetricFence'] = str_repeat('f', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary236($cursor));
};

$tests['compound select window recursive limit current source window-metric-fence rejects incomplete metric acks'] = static function (TestRunner $t) use ($summary236): void {
    $plan = $summary236();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedMetricAcksWindowMetricFence'] = array_slice($plan['windowMetricFenceWindowMetricFence']['requiredMetricAcks'], 0, 5);
    $t->throws(InvalidArgumentException::class, static fn () => $summary236($cursor));
};

$tests['compound select window recursive limit current source window-metric-fence rejects unexpected metric ack'] = static function (TestRunner $t) use ($summary236): void {
    $plan = $summary236();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedMetricAcksWindowMetricFence'] = [...$plan['windowMetricFenceWindowMetricFence']['requiredMetricAcks'], str_repeat('9', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary236($cursor));
};

$tests['compound select window recursive limit current source window-metric-fence executor parity'] = static function (TestRunner $t) use ($sql236, $currentTables236, $summary236): void {
    $t->same(SQLiteSelectSql::execute($sql236, $currentTables236), $summary236()['currentRows']);
};

$tests['compound select window recursive limit current source window-metric-fence non overlap'] = static function (TestRunner $t) use ($summary236): void {
    $plan = $summary236();
    $t->contains('extends accepted final-order-resume', $plan['non_overlap']);
    $t->true(in_array('compound-window-metric-ack-fence-window-metric-fence', $plan['replanReasons'], true));
    $t->true(in_array('recursive-window-metric-drift-holds-next-source-window-metric-fence', $plan['replanReasons'], true));
};

foreach (range(1, 60) as $case) {
    $tests['compound select window recursive limit current source window-metric-fence generated metric fence ' . $case] = static function (TestRunner $t) use ($case): void {
        $finalLimit = 4 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 140 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 118 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_rules_' . $case, 'autoload' => 'yes', 'score' => 96 + $case],
                ['option_id' => 4, 'option_name' => 'plugin_old_' . $case, 'autoload' => 'yes', 'score' => 82 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_prime_' . $case, 'autoload' => 'yes', 'score' => 128 + $case];
        $nextTables['wp_options'][] = ['option_id' => 6, 'option_name' => 'theme_mods_next_' . $case, 'autoload' => 'yes', 'score' => 107 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (152 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 9 FROM q WHERE id < 9 ORDER BY score DESC LIMIT 8 OFFSET 1) SELECT id, label, avg(score) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS metric FROM q UNION SELECT option_id AS id, option_name AS label, first_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes' INTERSECT SELECT id, label, metric FROM (SELECT id, label, avg(score) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS metric FROM q UNION SELECT option_id AS id, option_name AS label, first_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes') EXCEPT SELECT id, label, metric FROM (SELECT id, label, avg(score) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS metric FROM q WHERE id = 4 UNION SELECT option_id AS id, option_name AS label, first_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM wp_options WHERE option_name = 'plugin_old_{$case}') ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareWindowMetricFence($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedMetricAcksWindowMetricFence'] = $plan['windowMetricFenceWindowMetricFence']['requiredMetricAcks'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareWindowMetricFence($sql, $tables, $nextTables, $cursor);

        $t->same($finalLimit, $plan['windowMetricFenceWindowMetricFence']['requiredMetricAckCount']);
        $t->same($finalLimit, count($plan['windowMetricFenceWindowMetricFence']['currentMetricLabels']));
        $t->true(in_array('plugin_prime_' . $case, $plan['windowMetricFenceWindowMetricFence']['nextOnlyMetricLabels'], true));
        $t->same($plan['windowMetricFenceWindowMetricFence']['currentMetricFenceToken'], $again['windowMetricFenceWindowMetricFence']['currentMetricFenceToken']);
        $t->same('held-until-current-window-metric-acks-match', $again['cursor']['nextExposure']);
    };
}

return $tests;
