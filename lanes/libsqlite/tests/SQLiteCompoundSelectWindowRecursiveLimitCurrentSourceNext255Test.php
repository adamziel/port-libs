<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext255Plan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions255 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$nextOptions255 = [
    ...$currentOptions255,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables255 = ['wp_options' => $currentOptions255];
$nextTables255 = ['wp_options' => $nextOptions255];

$sql255 = <<<'SQL'
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

$summary255 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext255Plan::compare($sql255, $currentTables255, $nextTables255, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next255 status dependencies'] = static function (TestRunner $t) use ($summary255): void {
    $plan = $summary255();
    $t->same('compound-select-window-recursive-limit-current-source-next255-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-recursive-window-continuation-resume-next255', $plan['dependencies'], true));
    $t->contains('next250 next-page admission tokens', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next255 resume tokens'] = static function (TestRunner $t) use ($summary255): void {
    $resume = $summary255()['compoundWindowRecursiveContinuationResumeNext255'];
    $t->same(64, strlen($resume['continuationResumeToken']));
    $t->same(64, strlen($resume['currentContinuationToken']));
    $t->same(64, strlen($resume['nextContinuationToken']));
};

$tests['compound select window recursive limit current source next255 acknowledgement shape'] = static function (TestRunner $t) use ($summary255): void {
    $resume = $summary255()['compoundWindowRecursiveContinuationResumeNext255'];
    $t->same(4, $resume['requiredContinuationAckCount']);
    $t->same('held-until-compound-window-recursive-continuation-resume-acks', $resume['nextExposure']);
    $t->same('compound-window-recursive-next255-continuation-resume', $resume['yieldBoundary']);
};

$tests['compound select window recursive limit current source next255 labels and metrics'] = static function (TestRunner $t) use ($summary255): void {
    $resume = $summary255()['compoundWindowRecursiveContinuationResumeNext255'];
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], $resume['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], $resume['nextLabels']);
    $t->same([2, 2, 3, 3], $resume['currentMetrics']);
    $t->same([2, 2, 3, 3], $resume['nextMetrics']);
    $t->same(true, $resume['labelsChanged']);
    $t->same(false, $resume['metricsChanged']);
};

$tests['compound select window recursive limit current source next255 spillover lineage'] = static function (TestRunner $t) use ($summary255): void {
    $resume = $summary255()['compoundWindowRecursiveContinuationResumeNext255'];
    $t->same(['blogname', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $resume['currentSpilloverLabels']);
    $t->same(['rewrite_rules', 'seed:2:3:4:5', 'blogname', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $resume['nextSpilloverLabels']);
    $t->same(true, $resume['spilloverChanged']);
    $t->same(true, $resume['recursiveLineage']['limitExhausted']);
    $t->same(['seed'], $resume['recursiveLineage']['skipped']);
};

$tests['compound select window recursive limit current source next255 cursor fields'] = static function (TestRunner $t) use ($summary255): void {
    $plan = $summary255();
    $resume = $plan['compoundWindowRecursiveContinuationResumeNext255'];
    $cursor = $plan['cursor'];
    $t->same($resume['continuationResumeToken'], $cursor['continuationResumeTokenNext255']);
    $t->same($resume['currentContinuationToken'], $cursor['currentContinuationTokenNext255']);
    $t->same($resume['nextContinuationToken'], $cursor['nextContinuationTokenNext255']);
    $t->same($resume['requiredContinuationAcks'], $cursor['requiredContinuationAcksNext255']);
};

$tests['compound select window recursive limit current source next255 accepts complete cursor'] = static function (TestRunner $t) use ($summary255): void {
    $plan = $summary255();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
    $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
    $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
    $cursor['acknowledgedSourceHandoffAcksNext246'] = $plan['compoundRecursiveLimitSourceHandoffNext246']['requiredSourceHandoffAcks'];
    $cursor['acknowledgedNextPageAdmissionAcksNext250'] = $plan['compoundCurrentSourceNextPageAdmissionNext250']['requiredNextPageAdmissionAcks'];
    $cursor['acknowledgedContinuationAcksNext255'] = $plan['compoundWindowRecursiveContinuationResumeNext255']['requiredContinuationAcks'];
    $again = $summary255($cursor);
    $t->same($plan['compoundWindowRecursiveContinuationResumeNext255']['continuationResumeToken'], $again['compoundWindowRecursiveContinuationResumeNext255']['continuationResumeToken']);
};

$tests['compound select window recursive limit current source next255 rejects stale resume token'] = static function (TestRunner $t) use ($summary255): void {
    $cursor = $summary255()['cursor'];
    $cursor['continuationResumeTokenNext255'] = str_repeat('c', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary255($cursor));
};

$tests['compound select window recursive limit current source next255 rejects stale current token'] = static function (TestRunner $t) use ($summary255): void {
    $cursor = $summary255()['cursor'];
    $cursor['currentContinuationTokenNext255'] = str_repeat('d', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary255($cursor));
};

$tests['compound select window recursive limit current source next255 rejects stale next token'] = static function (TestRunner $t) use ($summary255): void {
    $cursor = $summary255()['cursor'];
    $cursor['nextContinuationTokenNext255'] = str_repeat('e', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary255($cursor));
};

$tests['compound select window recursive limit current source next255 rejects incomplete acknowledgements'] = static function (TestRunner $t) use ($summary255): void {
    $plan = $summary255();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedContinuationAcksNext255'] = array_slice($plan['compoundWindowRecursiveContinuationResumeNext255']['requiredContinuationAcks'], 0, 3);
    $t->throws(InvalidArgumentException::class, static fn () => $summary255($cursor));
};

$tests['compound select window recursive limit current source next255 rejects unexpected acknowledgements'] = static function (TestRunner $t) use ($summary255): void {
    $plan = $summary255();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedContinuationAcksNext255'] = [...$plan['compoundWindowRecursiveContinuationResumeNext255']['requiredContinuationAcks'], 'unexpected'];
    $t->throws(InvalidArgumentException::class, static fn () => $summary255($cursor));
};

$tests['compound select window recursive limit current source next255 executor parity'] = static function (TestRunner $t) use ($sql255, $currentTables255, $summary255): void {
    $t->same(SQLiteSelectSql::execute($sql255, $currentTables255), $summary255()['currentRows']);
};

$tests['compound select window recursive limit current source next255 non overlap'] = static function (TestRunner $t) use ($summary255): void {
    $plan = $summary255();
    $t->contains('extends accepted next250', $plan['non_overlap']);
    $t->true(in_array('compound-recursive-window-continuation-resume-next255', $plan['replanReasons'], true));
    $t->true(in_array('compound-limit-held-until-current-and-next-continuation-acks-next255', $plan['replanReasons'], true));
};

foreach (range(1, 72) as $case) {
    $tests['compound select window recursive limit current source next255 generated continuation resume ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext255Plan::compare($sql, $tables, $nextTables);
        $resume = $plan['compoundWindowRecursiveContinuationResumeNext255'];
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
        $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
        $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
        $cursor['acknowledgedSourceHandoffAcksNext246'] = $plan['compoundRecursiveLimitSourceHandoffNext246']['requiredSourceHandoffAcks'];
        $cursor['acknowledgedNextPageAdmissionAcksNext250'] = $plan['compoundCurrentSourceNextPageAdmissionNext250']['requiredNextPageAdmissionAcks'];
        $cursor['acknowledgedContinuationAcksNext255'] = $resume['requiredContinuationAcks'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext255Plan::compare($sql, $tables, $nextTables, $cursor);

        $t->same(['home_' . $case, 'seed_' . $case . ':2:3', 'rewrite_' . $case, 'seed_' . $case . ':2:3:4'], $resume['currentLabels']);
        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3', 'home_' . $case, 'seed_' . $case . ':2:3:4'], $resume['nextLabels']);
        $t->same(4, $resume['requiredContinuationAckCount']);
        $t->same(true, $resume['labelsChanged']);
        $t->same(false, $resume['metricsChanged']);
        $t->same($resume['continuationResumeToken'], $again['compoundWindowRecursiveContinuationResumeNext255']['continuationResumeToken']);
    };
}

return $tests;
