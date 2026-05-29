<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions250 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$nextOptions250 = [
    ...$currentOptions250,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables250 = ['wp_options' => $currentOptions250];
$nextTables250 = ['wp_options' => $nextOptions250];

$sql250 = <<<'SQL'
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

$summary250 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext250($sql250, $currentTables250, $nextTables250, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next250 status dependencies'] = static function (TestRunner $t) use ($summary250): void {
    $plan = $summary250();
    $t->same('compound-select-window-recursive-limit-current-source-next250-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-recursive-limit-next-page-admission-next250', $plan['dependencies'], true));
    $t->contains('current-source handoff tokens', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next250 admission tokens'] = static function (TestRunner $t) use ($summary250): void {
    $admission = $summary250()['compoundCurrentSourceNextPageAdmissionNext250'];
    $t->same(64, strlen($admission['nextPageAdmissionToken']));
    $t->same(64, strlen($admission['currentSourceResumeToken']));
    $t->same(64, strlen($admission['nextPageCandidateToken']));
};

$tests['compound select window recursive limit current source next250 admission shape'] = static function (TestRunner $t) use ($summary250): void {
    $admission = $summary250()['compoundCurrentSourceNextPageAdmissionNext250'];
    $t->same(3, $admission['requiredNextPageAdmissionAckCount']);
    $t->same(3, $admission['requiredSourceHandoffAckCount']);
    $t->same('held-until-current-source-handoff-and-resume-page-acks', $admission['nextExposure']);
    $t->same('compound-window-recursive-next250-next-page-admission', $admission['yieldBoundary']);
};

$tests['compound select window recursive limit current source next250 current and next labels'] = static function (TestRunner $t) use ($summary250): void {
    $admission = $summary250()['compoundCurrentSourceNextPageAdmissionNext250'];
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], $admission['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], $admission['nextLabels']);
};

$tests['compound select window recursive limit current source next250 page frames'] = static function (TestRunner $t) use ($summary250): void {
    $admission = $summary250()['compoundCurrentSourceNextPageAdmissionNext250'];
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], array_column($admission['currentPageFrame'], 'label'));
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], array_column($admission['nextPageFrame'], 'label'));
    $t->same([2, 2, 3, 3], array_column($admission['currentPageFrame'], 'metric'));
    $t->same([2, 2, 3, 3], array_column($admission['nextPageFrame'], 'metric'));
};

$tests['compound select window recursive limit current source next250 recursive queue'] = static function (TestRunner $t) use ($summary250): void {
    $admission = $summary250()['compoundCurrentSourceNextPageAdmissionNext250'];
    $t->same(['seed'], $admission['recursiveSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $admission['recursiveEmittedLabels']);
    $t->same(true, $admission['recursiveLimitExhausted']);
};

$tests['compound select window recursive limit current source next250 limit counts'] = static function (TestRunner $t) use ($summary250): void {
    $admission = $summary250()['compoundCurrentSourceNextPageAdmissionNext250'];
    $t->same(0, $admission['currentPreLimitCount']);
    $t->same(0, $admission['nextPreLimitCount']);
};

$tests['compound select window recursive limit current source next250 cursor fields'] = static function (TestRunner $t) use ($summary250): void {
    $plan = $summary250();
    $admission = $plan['compoundCurrentSourceNextPageAdmissionNext250'];
    $cursor = $plan['cursor'];
    $t->same($admission['nextPageAdmissionToken'], $cursor['nextPageAdmissionTokenNext250']);
    $t->same($admission['currentSourceResumeToken'], $cursor['currentSourceResumeTokenNext250']);
    $t->same($admission['requiredNextPageAdmissionAcks'], $cursor['requiredNextPageAdmissionAcksNext250']);
};

$tests['compound select window recursive limit current source next250 accepts complete cursor'] = static function (TestRunner $t) use ($summary250): void {
    $plan = $summary250();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
    $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
    $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
    $cursor['acknowledgedSourceHandoffAcksNext246'] = $plan['compoundRecursiveLimitSourceHandoffNext246']['requiredSourceHandoffAcks'];
    $cursor['acknowledgedNextPageAdmissionAcksNext250'] = $plan['compoundCurrentSourceNextPageAdmissionNext250']['requiredNextPageAdmissionAcks'];
    $again = $summary250($cursor);
    $t->same($plan['compoundCurrentSourceNextPageAdmissionNext250']['nextPageAdmissionToken'], $again['compoundCurrentSourceNextPageAdmissionNext250']['nextPageAdmissionToken']);
};

$tests['compound select window recursive limit current source next250 rejects stale admission token'] = static function (TestRunner $t) use ($summary250): void {
    $cursor = $summary250()['cursor'];
    $cursor['nextPageAdmissionTokenNext250'] = str_repeat('a', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary250($cursor));
};

$tests['compound select window recursive limit current source next250 rejects stale resume token'] = static function (TestRunner $t) use ($summary250): void {
    $cursor = $summary250()['cursor'];
    $cursor['currentSourceResumeTokenNext250'] = str_repeat('b', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary250($cursor));
};

$tests['compound select window recursive limit current source next250 rejects incomplete admission acknowledgements'] = static function (TestRunner $t) use ($summary250): void {
    $plan = $summary250();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedNextPageAdmissionAcksNext250'] = array_slice($plan['compoundCurrentSourceNextPageAdmissionNext250']['requiredNextPageAdmissionAcks'], 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $summary250($cursor));
};

$tests['compound select window recursive limit current source next250 rejects unexpected admission acknowledgement'] = static function (TestRunner $t) use ($summary250): void {
    $plan = $summary250();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedNextPageAdmissionAcksNext250'] = [...$plan['compoundCurrentSourceNextPageAdmissionNext250']['requiredNextPageAdmissionAcks'], 'unexpected'];
    $t->throws(InvalidArgumentException::class, static fn () => $summary250($cursor));
};

$tests['compound select window recursive limit current source next250 executor parity'] = static function (TestRunner $t) use ($sql250, $currentTables250, $summary250): void {
    $t->same(SQLiteSelectSql::execute($sql250, $currentTables250), $summary250()['currentRows']);
};

$tests['compound select window recursive limit current source next250 non overlap'] = static function (TestRunner $t) use ($summary250): void {
    $plan = $summary250();
    $t->contains('extends accepted next246', $plan['non_overlap']);
    $t->true(in_array('compound-recursive-window-current-source-next-page-admission-next250', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-handoff-acks-before-next-page-next250', $plan['replanReasons'], true));
};

foreach (range(1, 64) as $case) {
    $tests['compound select window recursive limit current source next250 generated next-page admission ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext250($sql, $tables, $nextTables);
        $admission = $plan['compoundCurrentSourceNextPageAdmissionNext250'];
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
        $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
        $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
        $cursor['acknowledgedSourceHandoffAcksNext246'] = $plan['compoundRecursiveLimitSourceHandoffNext246']['requiredSourceHandoffAcks'];
        $cursor['acknowledgedNextPageAdmissionAcksNext250'] = $admission['requiredNextPageAdmissionAcks'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext250($sql, $tables, $nextTables, $cursor);

        $t->same(['home_' . $case, 'seed_' . $case . ':2:3', 'rewrite_' . $case, 'seed_' . $case . ':2:3:4'], $admission['currentLabels']);
        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3', 'home_' . $case, 'seed_' . $case . ':2:3:4'], $admission['nextLabels']);
        $t->same(3, $admission['requiredNextPageAdmissionAckCount']);
        $t->same(3, $admission['requiredSourceHandoffAckCount']);
        $t->same(true, $admission['recursiveLimitExhausted']);
        $t->same($admission['nextPageAdmissionToken'], $again['compoundCurrentSourceNextPageAdmissionNext250']['nextPageAdmissionToken']);
    };
}

return $tests;
