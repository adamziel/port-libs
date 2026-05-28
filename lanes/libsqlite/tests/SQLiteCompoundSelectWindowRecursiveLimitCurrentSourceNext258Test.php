<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext258Plan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions258 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$nextOptions258 = [
    ...$currentOptions258,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables258 = ['wp_options' => $currentOptions258];
$nextTables258 = ['wp_options' => $nextOptions258];

$sql258 = <<<'SQL'
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

$summary258 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext258Plan::compare($sql258, $currentTables258, $nextTables258, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next258 status dependencies'] = static function (TestRunner $t) use ($summary258): void {
    $plan = $summary258();
    $t->same('compound-select-window-recursive-limit-current-source-next258-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-window-recursive-current-source-handoff-next258', $plan['dependencies'], true));
    $t->contains('current-page high-water handoff', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next258 handoff token shape'] = static function (TestRunner $t) use ($summary258): void {
    $handoff = $summary258()['compoundWindowRecursiveSourceHandoffNext258'];
    $t->same(64, strlen($handoff['compoundSourceHandoffToken']));
    $t->same(64, strlen($handoff['currentPageHighWaterToken']));
    $t->same(64, strlen($handoff['recursiveQueueDigest']));
    $t->same(64, strlen($handoff['nextCandidateToken']));
    $t->same(4, $handoff['requiredSourceHandoffAckCount']);
};

$tests['compound select window recursive limit current source next258 current high water'] = static function (TestRunner $t) use ($summary258): void {
    $handoff = $summary258()['compoundWindowRecursiveSourceHandoffNext258'];
    $t->same('seed:2:3:4', $handoff['currentHighWaterLabel']);
    $t->same(3, $handoff['currentHighWaterMetric']);
    $t->same('plugin_prime', $handoff['nextCandidateLabel']);
    $t->same([4, 4], [$handoff['currentFrameCount'], $handoff['nextFrameCount']]);
};

$tests['compound select window recursive limit current source next258 labels'] = static function (TestRunner $t) use ($summary258): void {
    $handoff = $summary258()['compoundWindowRecursiveSourceHandoffNext258'];
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], $handoff['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], $handoff['nextLabels']);
    $t->true($handoff['labelBoundaryChanged']);
};

$tests['compound select window recursive limit current source next258 recursive digest counts'] = static function (TestRunner $t) use ($summary258): void {
    $handoff = $summary258()['compoundWindowRecursiveSourceHandoffNext258'];
    $t->same([6, 1], [$handoff['recursiveEmittedCount'], $handoff['recursiveSkippedCount']]);
    $t->same('held-until-current-page-high-water-and-recursive-digest-acks', $handoff['nextExposure']);
    $t->same('compound-window-recursive-next258-current-source-handoff', $handoff['yieldBoundary']);
};

$tests['compound select window recursive limit current source next258 cursor fields'] = static function (TestRunner $t) use ($summary258): void {
    $plan = $summary258();
    $handoff = $plan['compoundWindowRecursiveSourceHandoffNext258'];
    $t->same($handoff['compoundSourceHandoffToken'], $plan['cursor']['compoundSourceHandoffTokenNext258']);
    $t->same($handoff['currentPageHighWaterToken'], $plan['cursor']['currentPageHighWaterTokenNext258']);
    $t->same($handoff['recursiveQueueDigest'], $plan['cursor']['recursiveQueueDigestNext258']);
    $t->same($handoff['requiredSourceHandoffAcks'], $plan['cursor']['requiredSourceHandoffAcksNext258']);
};

$tests['compound select window recursive limit current source next258 accepts complete cursor'] = static function (TestRunner $t) use ($summary258): void {
    $plan = $summary258();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
    $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
    $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
    $cursor['acknowledgedSourceHandoffAcksNext246'] = $plan['compoundRecursiveLimitSourceHandoffNext246']['requiredSourceHandoffAcks'];
    $cursor['acknowledgedNextPageAdmissionAcksNext250'] = $plan['compoundCurrentSourceNextPageAdmissionNext250']['requiredNextPageAdmissionAcks'];
    $cursor['acknowledgedCompoundReceiptAcksNext254'] = $plan['compoundWindowRecursiveLimitReceiptNext254']['requiredCompoundReceiptAcks'];
    $cursor['acknowledgedSourceHandoffAcksNext258'] = $plan['compoundWindowRecursiveSourceHandoffNext258']['requiredSourceHandoffAcks'];
    $again = $summary258($cursor);
    $t->same($plan['compoundWindowRecursiveSourceHandoffNext258']['compoundSourceHandoffToken'], $again['compoundWindowRecursiveSourceHandoffNext258']['compoundSourceHandoffToken']);
};

$tests['compound select window recursive limit current source next258 rejects stale handoff token'] = static function (TestRunner $t) use ($summary258): void {
    $cursor = $summary258()['cursor'];
    $cursor['compoundSourceHandoffTokenNext258'] = str_repeat('c', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary258($cursor));
};

$tests['compound select window recursive limit current source next258 rejects stale high water'] = static function (TestRunner $t) use ($summary258): void {
    $cursor = $summary258()['cursor'];
    $cursor['currentPageHighWaterTokenNext258'] = str_repeat('d', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary258($cursor));
};

$tests['compound select window recursive limit current source next258 rejects stale recursive digest'] = static function (TestRunner $t) use ($summary258): void {
    $cursor = $summary258()['cursor'];
    $cursor['recursiveQueueDigestNext258'] = str_repeat('e', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary258($cursor));
};

$tests['compound select window recursive limit current source next258 rejects incomplete handoff acknowledgements'] = static function (TestRunner $t) use ($summary258): void {
    $plan = $summary258();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedSourceHandoffAcksNext258'] = array_slice($plan['compoundWindowRecursiveSourceHandoffNext258']['requiredSourceHandoffAcks'], 0, 3);
    $t->throws(InvalidArgumentException::class, static fn () => $summary258($cursor));
};

$tests['compound select window recursive limit current source next258 rejects unexpected handoff acknowledgement'] = static function (TestRunner $t) use ($summary258): void {
    $plan = $summary258();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedSourceHandoffAcksNext258'] = [...$plan['compoundWindowRecursiveSourceHandoffNext258']['requiredSourceHandoffAcks'], 'unexpected'];
    $t->throws(InvalidArgumentException::class, static fn () => $summary258($cursor));
};

$tests['compound select window recursive limit current source next258 executor parity'] = static function (TestRunner $t) use ($sql258, $currentTables258, $summary258): void {
    $t->same(SQLiteSelectSql::execute($sql258, $currentTables258), $summary258()['currentRows']);
};

$tests['compound select window recursive limit current source next258 non overlap'] = static function (TestRunner $t) use ($summary258): void {
    $plan = $summary258();
    $t->contains('extends accepted next254', $plan['non_overlap']);
    $t->true(in_array('compound-window-recursive-current-source-high-water-next258', $plan['replanReasons'], true));
    $t->true(in_array('next-source-held-until-current-page-high-water-next258', $plan['replanReasons'], true));
};

foreach (range(1, 78) as $case) {
    $tests['compound select window recursive limit current source next258 generated high-water handoff ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext258Plan::compare($sql, $tables, $nextTables);
        $handoff = $plan['compoundWindowRecursiveSourceHandoffNext258'];
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
        $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
        $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
        $cursor['acknowledgedSourceHandoffAcksNext246'] = $plan['compoundRecursiveLimitSourceHandoffNext246']['requiredSourceHandoffAcks'];
        $cursor['acknowledgedNextPageAdmissionAcksNext250'] = $plan['compoundCurrentSourceNextPageAdmissionNext250']['requiredNextPageAdmissionAcks'];
        $cursor['acknowledgedCompoundReceiptAcksNext254'] = $plan['compoundWindowRecursiveLimitReceiptNext254']['requiredCompoundReceiptAcks'];
        $cursor['acknowledgedSourceHandoffAcksNext258'] = $handoff['requiredSourceHandoffAcks'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext258Plan::compare($sql, $tables, $nextTables, $cursor);

        $t->same('seed_' . $case . ':2:3:4', $handoff['currentHighWaterLabel']);
        $t->same('plugin_' . $case, $handoff['nextCandidateLabel']);
        $t->same(['home_' . $case, 'seed_' . $case . ':2:3', 'rewrite_' . $case, 'seed_' . $case . ':2:3:4'], $handoff['currentLabels']);
        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3', 'home_' . $case, 'seed_' . $case . ':2:3:4'], $handoff['nextLabels']);
        $t->same(4, $handoff['requiredSourceHandoffAckCount']);
        $t->same($handoff['compoundSourceHandoffToken'], $again['compoundWindowRecursiveSourceHandoffNext258']['compoundSourceHandoffToken']);
    };
}

return $tests;
