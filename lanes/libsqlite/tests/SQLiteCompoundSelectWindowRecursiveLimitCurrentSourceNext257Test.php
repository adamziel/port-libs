<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions257 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$nextOptions257 = [
    ...$currentOptions257,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables257 = ['wp_options' => $currentOptions257];
$nextTables257 = ['wp_options' => $nextOptions257];

$sql257 = <<<'SQL'
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

$summary257 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareSourceSwitchCheckpoint($sql257, $currentTables257, $nextTables257, $cursor);
$tests = [];

$tests['compound select window recursive limit current source source-switch-checkpoint status dependencies'] = static function (TestRunner $t) use ($summary257): void {
    $plan = $summary257();
    $t->same('compound-select-window-recursive-limit-current-source-source-switch-checkpoint-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-window-recursive-source-switch-checkpoint-source-switch-checkpoint', $plan['dependencies'], true));
    $t->contains('source-switch checkpoint', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source source-switch-checkpoint keeps final-page-yield-watermark watermark'] = static function (TestRunner $t) use ($summary257): void {
    $plan = $summary257();
    $watermark = $plan['compoundFinalPageYieldWatermarkFinalPageYieldWatermark'];
    $checkpoint = $plan['compoundSourceSwitchCheckpointSourceSwitchCheckpoint'];
    $t->same($watermark['finalPageYieldWatermarkToken'], $checkpoint['finalPageYieldWatermarkToken']);
    $t->same($watermark['currentFinalPageToken'], $checkpoint['currentFinalPageToken']);
    $t->same($watermark['nextFinalPageToken'], $checkpoint['nextFinalPageToken']);
};

$tests['compound select window recursive limit current source source-switch-checkpoint checkpoint token shape'] = static function (TestRunner $t) use ($summary257): void {
    $checkpoint = $summary257()['compoundSourceSwitchCheckpointSourceSwitchCheckpoint'];
    $t->same(64, strlen($checkpoint['sourceSwitchCheckpointToken']));
    $t->same(64, strlen($checkpoint['sourceSwitchDeltaToken']));
    $t->same(10, $checkpoint['requiredSourceSwitchReceiptCount']);
    $t->same('held-until-source-switch-checkpoint-receipts-match', $checkpoint['nextExposure']);
};

$tests['compound select window recursive limit current source source-switch-checkpoint ordered pages'] = static function (TestRunner $t) use ($summary257): void {
    $checkpoint = $summary257()['compoundSourceSwitchCheckpointSourceSwitchCheckpoint'];
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], array_column($checkpoint['orderedCurrentPage'], 'label'));
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], array_column($checkpoint['orderedNextPage'], 'label'));
    $t->same([2, 2, 3, 3], array_column($checkpoint['orderedCurrentPage'], 'metric'));
    $t->same([2, 2, 3, 3], array_column($checkpoint['orderedNextPage'], 'metric'));
};

$tests['compound select window recursive limit current source source-switch-checkpoint delta labels'] = static function (TestRunner $t) use ($summary257): void {
    $checkpoint = $summary257()['compoundSourceSwitchCheckpointSourceSwitchCheckpoint'];
    $t->same(['plugin_prime'], $checkpoint['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $checkpoint['currentOnlyLabels']);
    $t->same(2, $checkpoint['deltaLabelCount']);
    $t->same(4, $checkpoint['currentRowCount']);
    $t->same(4, $checkpoint['nextRowCount']);
};

$tests['compound select window recursive limit current source source-switch-checkpoint lineage and metrics are bound'] = static function (TestRunner $t) use ($summary257): void {
    $plan = $summary257();
    $checkpoint = $plan['compoundSourceSwitchCheckpointSourceSwitchCheckpoint'];
    $watermark = $plan['compoundFinalPageYieldWatermarkFinalPageYieldWatermark'];
    $t->same($watermark['recursiveLineageToken'], $checkpoint['recursiveLineageToken']);
    $t->same($watermark['windowMetricToken'], $checkpoint['windowMetricToken']);
    $t->same(true, $watermark['currentPageChanged']);
};

$tests['compound select window recursive limit current source source-switch-checkpoint cursor carries checkpoint'] = static function (TestRunner $t) use ($summary257): void {
    $plan = $summary257();
    $cursor = $plan['cursor'];
    $checkpoint = $plan['compoundSourceSwitchCheckpointSourceSwitchCheckpoint'];
    $t->same($checkpoint['sourceSwitchCheckpointToken'], $cursor['sourceSwitchCheckpointTokenSourceSwitchCheckpoint']);
    $t->same($checkpoint['sourceSwitchDeltaToken'], $cursor['sourceSwitchDeltaTokenSourceSwitchCheckpoint']);
    $t->same($checkpoint['requiredSourceSwitchReceipts'], $cursor['requiredSourceSwitchReceiptsSourceSwitchCheckpoint']);
    $t->same('held-until-source-switch-checkpoint-receipts-match', $cursor['nextExposureSourceSwitchCheckpoint']);
};

$tests['compound select window recursive limit current source source-switch-checkpoint accepts exact checkpoint receipts'] = static function (TestRunner $t) use ($summary257): void {
    $plan = $summary257();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
    $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
    $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
    $cursor['acknowledgedPromotionTicketsNext245'] = $plan['compoundNextSourcePromotionSnapshotNext245']['requiredPromotionTickets'];
    $cursor['acknowledgedPromotionEpochAcksRecursiveWindowPromotionEpoch'] = $plan['compoundRecursiveWindowPromotionEpochRecursiveWindowPromotionEpoch']['requiredPromotionEpochAcks'];
    $cursor['acknowledgedFinalPageYieldAcksFinalPageYieldWatermark'] = $plan['compoundFinalPageYieldWatermarkFinalPageYieldWatermark']['requiredFinalPageYieldAcks'];
    $cursor['acknowledgedSourceSwitchReceiptsSourceSwitchCheckpoint'] = $plan['compoundSourceSwitchCheckpointSourceSwitchCheckpoint']['requiredSourceSwitchReceipts'];
    $again = $summary257($cursor);
    $t->same($plan['compoundSourceSwitchCheckpointSourceSwitchCheckpoint']['sourceSwitchCheckpointToken'], $again['compoundSourceSwitchCheckpointSourceSwitchCheckpoint']['sourceSwitchCheckpointToken']);
    $t->same($plan['compoundSourceSwitchCheckpointSourceSwitchCheckpoint']['sourceSwitchDeltaToken'], $again['compoundSourceSwitchCheckpointSourceSwitchCheckpoint']['sourceSwitchDeltaToken']);
};

$tests['compound select window recursive limit current source source-switch-checkpoint rejects stale checkpoint token'] = static function (TestRunner $t) use ($summary257): void {
    $cursor = $summary257()['cursor'];
    $cursor['sourceSwitchCheckpointTokenSourceSwitchCheckpoint'] = str_repeat('e', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary257($cursor));
};

$tests['compound select window recursive limit current source source-switch-checkpoint rejects stale delta token'] = static function (TestRunner $t) use ($summary257): void {
    $cursor = $summary257()['cursor'];
    $cursor['sourceSwitchDeltaTokenSourceSwitchCheckpoint'] = str_repeat('f', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary257($cursor));
};

$tests['compound select window recursive limit current source source-switch-checkpoint rejects missing receipt'] = static function (TestRunner $t) use ($summary257): void {
    $cursor = $summary257()['cursor'];
    $cursor['acknowledgedSourceSwitchReceiptsSourceSwitchCheckpoint'] = array_slice($cursor['requiredSourceSwitchReceiptsSourceSwitchCheckpoint'], 0, 9);
    $t->throws(InvalidArgumentException::class, static fn () => $summary257($cursor));
};

$tests['compound select window recursive limit current source source-switch-checkpoint rejects unexpected receipt'] = static function (TestRunner $t) use ($summary257): void {
    $cursor = $summary257()['cursor'];
    $cursor['acknowledgedSourceSwitchReceiptsSourceSwitchCheckpoint'] = [...$cursor['requiredSourceSwitchReceiptsSourceSwitchCheckpoint'], str_repeat('1', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary257($cursor));
};

$tests['compound select window recursive limit current source source-switch-checkpoint executor parity'] = static function (TestRunner $t) use ($sql257, $currentTables257, $summary257): void {
    $t->same(SQLiteSelectSql::execute($sql257, $currentTables257), $summary257()['currentRows']);
};

$tests['compound select window recursive limit current source source-switch-checkpoint non overlap'] = static function (TestRunner $t) use ($summary257): void {
    $plan = $summary257();
    $t->contains('extends accepted final-page-yield-watermark', $plan['non_overlap']);
    $t->true(in_array('compound-window-recursive-source-switch-checkpoint-source-switch-checkpoint', $plan['replanReasons'], true));
    $t->true(in_array('next-source-held-until-ordered-current-window-checkpoint-source-switch-checkpoint', $plan['replanReasons'], true));
};

foreach (range(1, 72) as $case) {
    $tests['compound select window recursive limit current source source-switch-checkpoint generated checkpoint ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareSourceSwitchCheckpoint($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
        $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
        $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
        $cursor['acknowledgedPromotionTicketsNext245'] = $plan['compoundNextSourcePromotionSnapshotNext245']['requiredPromotionTickets'];
        $cursor['acknowledgedPromotionEpochAcksRecursiveWindowPromotionEpoch'] = $plan['compoundRecursiveWindowPromotionEpochRecursiveWindowPromotionEpoch']['requiredPromotionEpochAcks'];
        $cursor['acknowledgedFinalPageYieldAcksFinalPageYieldWatermark'] = $plan['compoundFinalPageYieldWatermarkFinalPageYieldWatermark']['requiredFinalPageYieldAcks'];
        $cursor['acknowledgedSourceSwitchReceiptsSourceSwitchCheckpoint'] = $plan['compoundSourceSwitchCheckpointSourceSwitchCheckpoint']['requiredSourceSwitchReceipts'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareSourceSwitchCheckpoint($sql, $tables, $nextTables, $cursor);
        $checkpoint = $plan['compoundSourceSwitchCheckpointSourceSwitchCheckpoint'];

        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3', 'home_' . $case, 'seed_' . $case . ':2:3:4'], array_column($checkpoint['orderedNextPage'], 'label'));
        $t->same(['plugin_' . $case], $checkpoint['nextOnlyLabels']);
        $t->same(['rewrite_' . $case], $checkpoint['currentOnlyLabels']);
        $t->same(10, $checkpoint['requiredSourceSwitchReceiptCount']);
        $t->same(2, $checkpoint['deltaLabelCount']);
        $t->same(64, strlen($checkpoint['sourceSwitchCheckpointToken']));
        $t->same($checkpoint['sourceSwitchCheckpointToken'], $again['compoundSourceSwitchCheckpointSourceSwitchCheckpoint']['sourceSwitchCheckpointToken']);
        $t->same('held-until-source-switch-checkpoint-receipts-match', $again['cursor']['nextExposureSourceSwitchCheckpoint']);
    };
}

return $tests;
