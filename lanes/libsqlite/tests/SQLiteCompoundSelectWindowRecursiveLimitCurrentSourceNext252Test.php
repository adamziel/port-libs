<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions252 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$nextOptions252 = [
    ...$currentOptions252,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables252 = ['wp_options' => $currentOptions252];
$nextTables252 = ['wp_options' => $nextOptions252];

$sql252 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 130)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       rank() OVER (ORDER BY score DESC) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT id,
       label,
       metric
  FROM (
       SELECT id,
              label,
              rank() OVER (ORDER BY score DESC) AS metric
         FROM q
       UNION ALL
       SELECT option_id AS id,
              option_name AS label,
              row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
         FROM wp_options
        WHERE autoload = 'yes'
  )
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE option_name IN ('siteurl')
 ORDER BY metric, label
 LIMIT 4 OFFSET 1
SQL;

$summary252 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareFinalPageYieldWatermark($sql252, $currentTables252, $nextTables252, $cursor);
$tests = [];

$tests['compound select window recursive limit current source final-page-yield-watermark status dependencies'] = static function (TestRunner $t) use ($summary252): void {
    $plan = $summary252();
    $t->same('compound-select-window-recursive-limit-current-source-final-page-yield-watermark-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-window-recursive-final-page-yield-watermark-final-page-yield-watermark', $plan['dependencies'], true));
    $t->contains('final pages', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source final-page-yield-watermark keeps recursive-window-promotion-epoch epoch'] = static function (TestRunner $t) use ($summary252): void {
    $plan = $summary252();
    $t->same(3, $plan['compoundRecursiveWindowPromotionEpochRecursiveWindowPromotionEpoch']['requiredPromotionEpochAckCount']);
    $t->same(['plugin_prime'], $plan['compoundRecursiveWindowPromotionEpochRecursiveWindowPromotionEpoch']['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $plan['compoundRecursiveWindowPromotionEpochRecursiveWindowPromotionEpoch']['currentOnlyLabels']);
};

$tests['compound select window recursive limit current source final-page-yield-watermark watermark token shape'] = static function (TestRunner $t) use ($summary252): void {
    $watermark = $summary252()['compoundFinalPageYieldWatermarkFinalPageYieldWatermark'];
    $t->same(64, strlen($watermark['finalPageYieldWatermarkToken']));
    $t->same(64, strlen($watermark['currentFinalPageToken']));
    $t->same(64, strlen($watermark['nextFinalPageToken']));
    $t->same(5, $watermark['requiredFinalPageYieldAckCount']);
    $t->same('watermark:', substr($watermark['requiredFinalPageYieldAcks'][0], 0, 10));
    $t->same('current-page:', substr($watermark['requiredFinalPageYieldAcks'][1], 0, 13));
    $t->same('next-page:', substr($watermark['requiredFinalPageYieldAcks'][2], 0, 10));
};

$tests['compound select window recursive limit current source final-page-yield-watermark final page rows'] = static function (TestRunner $t) use ($summary252): void {
    $watermark = $summary252()['compoundFinalPageYieldWatermarkFinalPageYieldWatermark'];
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], array_column($watermark['currentFinalPageRows'], 'label'));
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], array_column($watermark['nextFinalPageRows'], 'label'));
    $t->same([2, 2, 3, 3], array_column($watermark['currentFinalPageRows'], 'metric'));
    $t->same([2, 2, 3, 3], array_column($watermark['nextFinalPageRows'], 'metric'));
};

$tests['compound select window recursive limit current source final-page-yield-watermark binds epoch tokens'] = static function (TestRunner $t) use ($summary252): void {
    $plan = $summary252();
    $watermark = $plan['compoundFinalPageYieldWatermarkFinalPageYieldWatermark'];
    $epoch = $plan['compoundRecursiveWindowPromotionEpochRecursiveWindowPromotionEpoch'];
    $t->same($epoch['promotionEpochToken'], $watermark['promotionEpochToken']);
    $t->same($epoch['recursiveLineageToken'], $watermark['recursiveLineageToken']);
    $t->same($epoch['windowMetricToken'], $watermark['windowMetricToken']);
    $t->same(true, $watermark['currentPageChanged']);
};

$tests['compound select window recursive limit current source final-page-yield-watermark cursor carries watermark'] = static function (TestRunner $t) use ($summary252): void {
    $plan = $summary252();
    $cursor = $plan['cursor'];
    $watermark = $plan['compoundFinalPageYieldWatermarkFinalPageYieldWatermark'];
    $t->same($watermark['finalPageYieldWatermarkToken'], $cursor['finalPageYieldWatermarkTokenFinalPageYieldWatermark']);
    $t->same($watermark['currentFinalPageToken'], $cursor['currentFinalPageTokenFinalPageYieldWatermark']);
    $t->same($watermark['nextFinalPageToken'], $cursor['nextFinalPageTokenFinalPageYieldWatermark']);
    $t->same($watermark['requiredFinalPageYieldAcks'], $cursor['requiredFinalPageYieldAcksFinalPageYieldWatermark']);
    $t->same('held-until-final-page-yield-watermark-acks-match', $cursor['nextExposureFinalPageYieldWatermark']);
};

$tests['compound select window recursive limit current source final-page-yield-watermark accepts exact watermark acknowledgements'] = static function (TestRunner $t) use ($summary252): void {
    $plan = $summary252();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcks'] = $plan['currentSourceDequeue']['requiredCurrentDequeueAcks'];
    $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
    $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
    $cursor['acknowledgedPromotionTickets'] = $plan['compoundNextSourcePromotionSnapshot']['requiredPromotionTickets'];
    $cursor['acknowledgedPromotionEpochAcksRecursiveWindowPromotionEpoch'] = $plan['compoundRecursiveWindowPromotionEpochRecursiveWindowPromotionEpoch']['requiredPromotionEpochAcks'];
    $cursor['acknowledgedFinalPageYieldAcksFinalPageYieldWatermark'] = $plan['compoundFinalPageYieldWatermarkFinalPageYieldWatermark']['requiredFinalPageYieldAcks'];
    $again = $summary252($cursor);
    $t->same($plan['compoundFinalPageYieldWatermarkFinalPageYieldWatermark']['finalPageYieldWatermarkToken'], $again['compoundFinalPageYieldWatermarkFinalPageYieldWatermark']['finalPageYieldWatermarkToken']);
    $t->same($plan['compoundFinalPageYieldWatermarkFinalPageYieldWatermark']['nextFinalPageToken'], $again['compoundFinalPageYieldWatermarkFinalPageYieldWatermark']['nextFinalPageToken']);
};

$tests['compound select window recursive limit current source final-page-yield-watermark rejects stale watermark token'] = static function (TestRunner $t) use ($summary252): void {
    $cursor = $summary252()['cursor'];
    $cursor['finalPageYieldWatermarkTokenFinalPageYieldWatermark'] = str_repeat('a', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary252($cursor));
};

$tests['compound select window recursive limit current source final-page-yield-watermark rejects stale current page token'] = static function (TestRunner $t) use ($summary252): void {
    $cursor = $summary252()['cursor'];
    $cursor['currentFinalPageTokenFinalPageYieldWatermark'] = str_repeat('b', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary252($cursor));
};

$tests['compound select window recursive limit current source final-page-yield-watermark rejects stale next page token'] = static function (TestRunner $t) use ($summary252): void {
    $cursor = $summary252()['cursor'];
    $cursor['nextFinalPageTokenFinalPageYieldWatermark'] = str_repeat('c', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary252($cursor));
};

$tests['compound select window recursive limit current source final-page-yield-watermark rejects missing watermark ack'] = static function (TestRunner $t) use ($summary252): void {
    $cursor = $summary252()['cursor'];
    $cursor['acknowledgedFinalPageYieldAcksFinalPageYieldWatermark'] = array_slice($cursor['requiredFinalPageYieldAcksFinalPageYieldWatermark'], 0, 4);
    $t->throws(InvalidArgumentException::class, static fn () => $summary252($cursor));
};

$tests['compound select window recursive limit current source final-page-yield-watermark rejects unexpected watermark ack'] = static function (TestRunner $t) use ($summary252): void {
    $cursor = $summary252()['cursor'];
    $cursor['acknowledgedFinalPageYieldAcksFinalPageYieldWatermark'] = [...$cursor['requiredFinalPageYieldAcksFinalPageYieldWatermark'], 'watermark:' . str_repeat('d', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary252($cursor));
};

$tests['compound select window recursive limit current source final-page-yield-watermark executor parity'] = static function (TestRunner $t) use ($sql252, $currentTables252, $summary252): void {
    $t->same(SQLiteSelectSql::execute($sql252, $currentTables252), $summary252()['currentRows']);
};

$tests['compound select window recursive limit current source final-page-yield-watermark non overlap'] = static function (TestRunner $t) use ($summary252): void {
    $plan = $summary252();
    $t->contains('extends accepted recursive-window-promotion-epoch', $plan['non_overlap']);
    $t->true(in_array('compound-window-recursive-final-page-yield-watermark-final-page-yield-watermark', $plan['replanReasons'], true));
    $t->true(in_array('next-source-held-until-final-page-yield-watermark-acks-final-page-yield-watermark', $plan['replanReasons'], true));
};

foreach (range(1, 66) as $case) {
    $tests['compound select window recursive limit current source final-page-yield-watermark generated final page watermark ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 100 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 90 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_' . $case, 'autoload' => 'yes', 'score' => 80 + $case],
                ['option_id' => 4, 'option_name' => 'blog_' . $case, 'autoload' => 'yes', 'score' => 70 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 95 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (130 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes' INTERSECT SELECT id, label, metric FROM (SELECT id, label, rank() OVER (ORDER BY score DESC) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes') EXCEPT SELECT option_id AS id, option_name AS label, row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE option_name IN ('siteurl_{$case}') ORDER BY metric, label LIMIT 4 OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareFinalPageYieldWatermark($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentDequeueAcks'] = $plan['currentSourceDequeue']['requiredCurrentDequeueAcks'];
        $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
        $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
        $cursor['acknowledgedPromotionTickets'] = $plan['compoundNextSourcePromotionSnapshot']['requiredPromotionTickets'];
        $cursor['acknowledgedPromotionEpochAcksRecursiveWindowPromotionEpoch'] = $plan['compoundRecursiveWindowPromotionEpochRecursiveWindowPromotionEpoch']['requiredPromotionEpochAcks'];
        $cursor['acknowledgedFinalPageYieldAcksFinalPageYieldWatermark'] = $plan['compoundFinalPageYieldWatermarkFinalPageYieldWatermark']['requiredFinalPageYieldAcks'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareFinalPageYieldWatermark($sql, $tables, $nextTables, $cursor);
        $watermark = $plan['compoundFinalPageYieldWatermarkFinalPageYieldWatermark'];

        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3', 'home_' . $case, 'seed_' . $case . ':2:3:4'], array_column($watermark['nextFinalPageRows'], 'label'));
        $t->same(['plugin_' . $case], $watermark['nextOnlyLabels']);
        $t->same(['rewrite_' . $case], $watermark['currentOnlyLabels']);
        $t->same([2, 2, 3, 3], array_column($watermark['nextFinalPageRows'], 'metric'));
        $t->same(5, $watermark['requiredFinalPageYieldAckCount']);
        $t->same(true, $watermark['currentPageChanged']);
        $t->same($watermark['finalPageYieldWatermarkToken'], $again['compoundFinalPageYieldWatermarkFinalPageYieldWatermark']['finalPageYieldWatermarkToken']);
        $t->same('held-until-final-page-yield-watermark-acks-match', $again['cursor']['nextExposureFinalPageYieldWatermark']);
    };
}

return $tests;
