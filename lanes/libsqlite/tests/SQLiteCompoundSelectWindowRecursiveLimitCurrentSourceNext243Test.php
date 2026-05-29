<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions243 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$nextOptions243 = [
    ...$currentOptions243,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables243 = ['wp_options' => $currentOptions243];
$nextTables243 = ['wp_options' => $nextOptions243];

$sql243 = <<<'SQL'
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

$summary243 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext243($sql243, $currentTables243, $nextTables243, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next243 status dependencies'] = static function (TestRunner $t) use ($summary243): void {
    $plan = $summary243();
    $t->same('compound-select-window-recursive-limit-current-source-next243-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-window-recursive-replay-ticket-next243', $plan['dependencies'], true));
    $t->contains('current-row replay-ticket fence', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next243 preserves base final rows'] = static function (TestRunner $t) use ($summary243): void {
    $plan = $summary243();
    $t->same(['UNION ALL', 'INTERSECT', 'EXCEPT'], $plan['compound']['operators']);
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], array_column($plan['currentRows'], 'label'));
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], array_column($plan['nextRows'], 'label'));
    $t->same(['seed:2', 'blogname', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $plan['compoundFinalPageSpilloverDrainNext240']['currentSpilloverLabels']);
};

$tests['compound select window recursive limit current source next243 replay token shape'] = static function (TestRunner $t) use ($summary243): void {
    $replay = $summary243()['compoundWindowReplayFenceNext243'];
    $t->same(64, strlen($replay['windowReplayToken']));
    $t->same(64, strlen($replay['currentReplaySignature']));
    $t->same(4, $replay['requiredReplayTicketCount']);
    $t->same(4, count($replay['requiredReplayTickets']));
};

$tests['compound select window recursive limit current source next243 replay rows include metrics'] = static function (TestRunner $t) use ($summary243): void {
    $rows = $summary243()['compoundWindowReplayFenceNext243']['currentReplayRows'];
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], array_column($rows, 'label'));
    $t->same([2, 2, 3, 3], array_column($rows, 'metric'));
    $t->same([1, 2, 3, 4], array_column($rows, 'ordinal'));
    $t->same([2, 3, 3, 4], array_column($rows, 'id'));
};

$tests['compound select window recursive limit current source next243 replay metadata'] = static function (TestRunner $t) use ($summary243): void {
    $replay = $summary243()['compoundWindowReplayFenceNext243'];
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $replay['recursiveEmittedLabels']);
    $t->same(['seed'], $replay['recursiveSkippedLabels']);
    $t->same(['plugin_prime'], $replay['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $replay['currentOnlyLabels']);
};

$tests['compound select window recursive limit current source next243 cursor carries replay fence'] = static function (TestRunner $t) use ($summary243): void {
    $plan = $summary243();
    $cursor = $plan['cursor'];
    $t->same($plan['compoundWindowReplayFenceNext243']['windowReplayToken'], $cursor['windowReplayTokenNext243']);
    $t->same($plan['compoundWindowReplayFenceNext243']['currentReplaySignature'], $cursor['currentReplaySignatureNext243']);
    $t->same($plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'], $cursor['requiredReplayTicketsNext243']);
    $t->same('held-until-current-window-replay-tickets-match', $cursor['nextExposureNext243']);
};

$tests['compound select window recursive limit current source next243 accepts exact replay tickets'] = static function (TestRunner $t) use ($summary243): void {
    $plan = $summary243();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
    $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
    $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
    $again = $summary243($cursor);
    $t->same($plan['compoundWindowReplayFenceNext243']['windowReplayToken'], $again['compoundWindowReplayFenceNext243']['windowReplayToken']);
    $t->same($plan['compoundWindowReplayFenceNext243']['currentReplaySignature'], $again['compoundWindowReplayFenceNext243']['currentReplaySignature']);
};

$tests['compound select window recursive limit current source next243 rejects stale replay token'] = static function (TestRunner $t) use ($summary243): void {
    $cursor = $summary243()['cursor'];
    $cursor['windowReplayTokenNext243'] = str_repeat('a', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary243($cursor));
};

$tests['compound select window recursive limit current source next243 rejects stale replay signature'] = static function (TestRunner $t) use ($summary243): void {
    $cursor = $summary243()['cursor'];
    $cursor['currentReplaySignatureNext243'] = str_repeat('b', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary243($cursor));
};

$tests['compound select window recursive limit current source next243 rejects missing ticket'] = static function (TestRunner $t) use ($summary243): void {
    $plan = $summary243();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedReplayTicketsNext243'] = array_slice($plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'], 0, 3);
    $t->throws(InvalidArgumentException::class, static fn () => $summary243($cursor));
};

$tests['compound select window recursive limit current source next243 rejects unexpected ticket'] = static function (TestRunner $t) use ($summary243): void {
    $plan = $summary243();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedReplayTicketsNext243'] = [...$plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'], str_repeat('c', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary243($cursor));
};

$tests['compound select window recursive limit current source next243 executor parity'] = static function (TestRunner $t) use ($sql243, $currentTables243, $summary243): void {
    $t->same(SQLiteSelectSql::execute($sql243, $currentTables243), $summary243()['currentRows']);
};

$tests['compound select window recursive limit current source next243 non overlap'] = static function (TestRunner $t) use ($summary243): void {
    $plan = $summary243();
    $t->contains('extends accepted next240', $plan['non_overlap']);
    $t->true(in_array('compound-window-recursive-current-replay-ticket-next243', $plan['replanReasons'], true));
    $t->true(in_array('next-source-held-until-window-metric-lineage-replayed-next243', $plan['replanReasons'], true));
};

foreach (range(1, 70) as $case) {
    $tests['compound select window recursive limit current source next243 generated replay ticket ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext243($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
        $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
        $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext243($sql, $tables, $nextTables, $cursor);

        $t->same(['home_' . $case, 'seed_' . $case . ':2:3', 'rewrite_' . $case, 'seed_' . $case . ':2:3:4'], array_column($plan['currentRows'], 'label'));
        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3', 'home_' . $case, 'seed_' . $case . ':2:3:4'], array_column($plan['nextRows'], 'label'));
        $t->same([2, 2, 3, 3], array_column($plan['compoundWindowReplayFenceNext243']['currentReplayRows'], 'metric'));
        $t->same(4, $plan['compoundWindowReplayFenceNext243']['requiredReplayTicketCount']);
        $t->same($plan['compoundWindowReplayFenceNext243']['windowReplayToken'], $again['compoundWindowReplayFenceNext243']['windowReplayToken']);
        $t->same('held-until-current-window-replay-tickets-match', $again['cursor']['nextExposureNext243']);
    };
}

return $tests;
