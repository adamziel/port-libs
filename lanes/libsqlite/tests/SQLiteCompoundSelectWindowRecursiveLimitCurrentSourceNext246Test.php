<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext246Plan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions246 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$nextOptions246 = [
    ...$currentOptions246,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables246 = ['wp_options' => $currentOptions246];
$nextTables246 = ['wp_options' => $nextOptions246];

$sql246 = <<<'SQL'
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

$summary246 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext246Plan::compare($sql246, $currentTables246, $nextTables246, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next246 status dependencies'] = static function (TestRunner $t) use ($summary246): void {
    $plan = $summary246();
    $t->same('compound-select-window-recursive-limit-current-source-next246-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-recursive-limit-current-source-handoff-next246', $plan['dependencies'], true));
    $t->contains('current-source handoff fence', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next246 keeps next243 replay fields'] = static function (TestRunner $t) use ($summary246): void {
    $plan = $summary246();
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], array_column($plan['currentRows'], 'label'));
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], array_column($plan['nextRows'], 'label'));
    $t->same(4, $plan['compoundWindowReplayFenceNext243']['requiredReplayTicketCount']);
    $t->same(5, $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAckCount']);
};

$tests['compound select window recursive limit current source next246 handoff tokens'] = static function (TestRunner $t) use ($summary246): void {
    $handoff = $summary246()['compoundRecursiveLimitSourceHandoffNext246'];
    $t->same(64, strlen($handoff['sourceHandoffToken']));
    $t->same(64, strlen($handoff['recursiveLimitCursorToken']));
    $t->same(64, strlen($handoff['currentSourceSignature']));
    $t->same(64, strlen($handoff['nextSourceCandidateToken']));
};

$tests['compound select window recursive limit current source next246 handoff shape'] = static function (TestRunner $t) use ($summary246): void {
    $handoff = $summary246()['compoundRecursiveLimitSourceHandoffNext246'];
    $t->same(3, $handoff['requiredSourceHandoffAckCount']);
    $t->same('held-until-current-source-recursive-limit-window-handoff-acks', $handoff['nextExposure']);
    $t->same('compound-window-recursive-next246-current-source-handoff', $handoff['yieldBoundary']);
    $t->same(true, $handoff['currentSourceComplete']);
};

$tests['compound select window recursive limit current source next246 labels'] = static function (TestRunner $t) use ($summary246): void {
    $handoff = $summary246()['compoundRecursiveLimitSourceHandoffNext246'];
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], $handoff['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], $handoff['nextLabels']);
    $t->same(['plugin_prime'], $handoff['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $handoff['currentOnlyLabels']);
};

$tests['compound select window recursive limit current source next246 recursive cursor'] = static function (TestRunner $t) use ($summary246): void {
    $handoff = $summary246()['compoundRecursiveLimitSourceHandoffNext246'];
    $t->same(['seed'], $handoff['recursiveSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $handoff['recursiveEmittedLabels']);
    $t->same(0, $handoff['recursiveLimitRemaining']);
    $t->same(0, $handoff['recursiveOffsetRemaining']);
    $t->same(true, $handoff['recursiveLimitExhausted']);
};

$tests['compound select window recursive limit current source next246 replay spillover counts'] = static function (TestRunner $t) use ($summary246): void {
    $handoff = $summary246()['compoundRecursiveLimitSourceHandoffNext246'];
    $t->same(['seed:2', 'blogname', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $handoff['spilloverLabels']);
    $t->same(5, $handoff['spilloverAckCount']);
    $t->same(4, $handoff['replayTicketCount']);
};

$tests['compound select window recursive limit current source next246 cursor carries handoff fence'] = static function (TestRunner $t) use ($summary246): void {
    $plan = $summary246();
    $handoff = $plan['compoundRecursiveLimitSourceHandoffNext246'];
    $cursor = $plan['cursor'];
    $t->same($handoff['sourceHandoffToken'], $cursor['sourceHandoffTokenNext246']);
    $t->same($handoff['recursiveLimitCursorToken'], $cursor['recursiveLimitCursorTokenNext246']);
    $t->same($handoff['currentSourceSignature'], $cursor['currentSourceSignatureNext246']);
    $t->same($handoff['requiredSourceHandoffAcks'], $cursor['requiredSourceHandoffAcksNext246']);
};

$tests['compound select window recursive limit current source next246 accepts complete cursor'] = static function (TestRunner $t) use ($summary246): void {
    $plan = $summary246();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
    $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
    $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
    $cursor['acknowledgedSourceHandoffAcksNext246'] = $plan['compoundRecursiveLimitSourceHandoffNext246']['requiredSourceHandoffAcks'];
    $again = $summary246($cursor);
    $t->same($plan['compoundRecursiveLimitSourceHandoffNext246']['sourceHandoffToken'], $again['compoundRecursiveLimitSourceHandoffNext246']['sourceHandoffToken']);
};

$tests['compound select window recursive limit current source next246 rejects stale source handoff token'] = static function (TestRunner $t) use ($summary246): void {
    $cursor = $summary246()['cursor'];
    $cursor['sourceHandoffTokenNext246'] = str_repeat('6', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary246($cursor));
};

$tests['compound select window recursive limit current source next246 rejects stale recursive cursor token'] = static function (TestRunner $t) use ($summary246): void {
    $cursor = $summary246()['cursor'];
    $cursor['recursiveLimitCursorTokenNext246'] = str_repeat('7', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary246($cursor));
};

$tests['compound select window recursive limit current source next246 rejects stale current source signature'] = static function (TestRunner $t) use ($summary246): void {
    $cursor = $summary246()['cursor'];
    $cursor['currentSourceSignatureNext246'] = str_repeat('8', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary246($cursor));
};

$tests['compound select window recursive limit current source next246 rejects incomplete handoff acknowledgements'] = static function (TestRunner $t) use ($summary246): void {
    $plan = $summary246();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedSourceHandoffAcksNext246'] = array_slice($plan['compoundRecursiveLimitSourceHandoffNext246']['requiredSourceHandoffAcks'], 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $summary246($cursor));
};

$tests['compound select window recursive limit current source next246 rejects unexpected handoff acknowledgement'] = static function (TestRunner $t) use ($summary246): void {
    $plan = $summary246();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedSourceHandoffAcksNext246'] = [...$plan['compoundRecursiveLimitSourceHandoffNext246']['requiredSourceHandoffAcks'], 'unexpected'];
    $t->throws(InvalidArgumentException::class, static fn () => $summary246($cursor));
};

$tests['compound select window recursive limit current source next246 executor parity'] = static function (TestRunner $t) use ($sql246, $currentTables246, $summary246): void {
    $t->same(SQLiteSelectSql::execute($sql246, $currentTables246), $summary246()['currentRows']);
};

$tests['compound select window recursive limit current source next246 non overlap'] = static function (TestRunner $t) use ($summary246): void {
    $plan = $summary246();
    $t->contains('extends accepted next243', $plan['non_overlap']);
    $t->true(in_array('compound-recursive-limit-current-source-handoff-next246', $plan['replanReasons'], true));
    $t->true(in_array('window-replay-and-spillover-acks-before-next-source-next246', $plan['replanReasons'], true));
};

foreach (range(1, 64) as $case) {
    $tests['compound select window recursive limit current source next246 generated handoff ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext246Plan::compare($sql, $tables, $nextTables);
        $handoff = $plan['compoundRecursiveLimitSourceHandoffNext246'];
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
        $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
        $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
        $cursor['acknowledgedSourceHandoffAcksNext246'] = $handoff['requiredSourceHandoffAcks'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext246Plan::compare($sql, $tables, $nextTables, $cursor);

        $t->same(['home_' . $case, 'seed_' . $case . ':2:3', 'rewrite_' . $case, 'seed_' . $case . ':2:3:4'], $handoff['currentLabels']);
        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3', 'home_' . $case, 'seed_' . $case . ':2:3:4'], $handoff['nextLabels']);
        $t->same(['plugin_' . $case], $handoff['nextOnlyLabels']);
        $t->same(['rewrite_' . $case], $handoff['currentOnlyLabels']);
        $t->same(3, $handoff['requiredSourceHandoffAckCount']);
        $t->same(5, $handoff['spilloverAckCount']);
        $t->same(4, $handoff['replayTicketCount']);
        $t->same(true, $handoff['recursiveLimitExhausted']);
        $t->same($handoff['sourceHandoffToken'], $again['compoundRecursiveLimitSourceHandoffNext246']['sourceHandoffToken']);
    };
}

return $tests;
