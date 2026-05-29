<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions245 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$nextOptions245 = [
    ...$currentOptions245,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables245 = ['wp_options' => $currentOptions245];
$nextTables245 = ['wp_options' => $nextOptions245];

$sql245 = <<<'SQL'
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

$summary245 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNextSourcePromotionSnapshot($sql245, $currentTables245, $nextTables245, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next245 status dependencies'] = static function (TestRunner $t) use ($summary245): void {
    $plan = $summary245();
    $t->same('compound-select-window-recursive-limit-next-source-promotion-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-window-recursive-next-source-promotion-snapshot', $plan['dependencies'], true));
    $t->contains('next-source promotion snapshot', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next245 keeps next243 replay data'] = static function (TestRunner $t) use ($summary245): void {
    $plan = $summary245();
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], array_column($plan['currentRows'], 'label'));
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], array_column($plan['nextRows'], 'label'));
    $t->same(4, $plan['compoundWindowReplayFenceNext243']['requiredReplayTicketCount']);
};

$tests['compound select window recursive limit current source next245 promotion token shape'] = static function (TestRunner $t) use ($summary245): void {
    $promotion = $summary245()['compoundNextSourcePromotionSnapshot'];
    $t->same(64, strlen($promotion['promotionSnapshotToken']));
    $t->same(64, strlen($promotion['nextSourceDeltaToken']));
    $t->same(2, $promotion['requiredPromotionTicketCount']);
    $t->same('next:', substr($promotion['requiredPromotionTickets'][0], 0, 5));
    $t->same('current:', substr($promotion['requiredPromotionTickets'][1], 0, 8));
};

$tests['compound select window recursive limit current source next245 promotion labels'] = static function (TestRunner $t) use ($summary245): void {
    $promotion = $summary245()['compoundNextSourcePromotionSnapshot'];
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], $promotion['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3', 'home', 'seed:2:3:4'], $promotion['nextLabels']);
    $t->same(['plugin_prime'], $promotion['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $promotion['currentOnlyLabels']);
    $t->same(2, $promotion['changedRowCount']);
};

$tests['compound select window recursive limit current source next245 binds replay and spillover tokens'] = static function (TestRunner $t) use ($summary245): void {
    $plan = $summary245();
    $promotion = $plan['compoundNextSourcePromotionSnapshot'];
    $t->same($plan['compoundWindowReplayFenceNext243']['windowReplayToken'], $promotion['windowReplayToken']);
    $t->same($plan['compoundWindowReplayFenceNext243']['currentReplaySignature'], $promotion['currentReplaySignature']);
    $t->same($plan['compoundFinalPageSpilloverDrainNext240']['spilloverDrainToken'], $promotion['spilloverDrainToken']);
    $t->same('compound-window-recursive-next-source-promotion-snapshot', $promotion['yieldBoundary']);
};

$tests['compound select window recursive limit current source next245 cursor carries promotion snapshot'] = static function (TestRunner $t) use ($summary245): void {
    $plan = $summary245();
    $cursor = $plan['cursor'];
    $promotion = $plan['compoundNextSourcePromotionSnapshot'];
    $t->same($promotion['promotionSnapshotToken'], $cursor['promotionSnapshotToken']);
    $t->same($promotion['nextSourceDeltaToken'], $cursor['nextSourceDeltaToken']);
    $t->same($promotion['requiredPromotionTickets'], $cursor['requiredPromotionTickets']);
    $t->same('held-until-current-replay-and-next-delta-snapshot-match', $cursor['nextExposure']);
};

$tests['compound select window recursive limit current source next245 accepts exact promotion tickets'] = static function (TestRunner $t) use ($summary245): void {
    $plan = $summary245();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
    $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
    $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
    $cursor['acknowledgedPromotionTickets'] = $plan['compoundNextSourcePromotionSnapshot']['requiredPromotionTickets'];
    $again = $summary245($cursor);
    $t->same($plan['compoundNextSourcePromotionSnapshot']['promotionSnapshotToken'], $again['compoundNextSourcePromotionSnapshot']['promotionSnapshotToken']);
    $t->same($plan['compoundNextSourcePromotionSnapshot']['nextSourceDeltaToken'], $again['compoundNextSourcePromotionSnapshot']['nextSourceDeltaToken']);
};

$tests['compound select window recursive limit current source next245 rejects stale promotion token'] = static function (TestRunner $t) use ($summary245): void {
    $cursor = $summary245()['cursor'];
    $cursor['promotionSnapshotToken'] = str_repeat('d', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary245($cursor));
};

$tests['compound select window recursive limit current source next245 rejects stale delta token'] = static function (TestRunner $t) use ($summary245): void {
    $cursor = $summary245()['cursor'];
    $cursor['nextSourceDeltaToken'] = str_repeat('e', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary245($cursor));
};

$tests['compound select window recursive limit current source next245 rejects missing promotion ticket'] = static function (TestRunner $t) use ($summary245): void {
    $plan = $summary245();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedPromotionTickets'] = array_slice($plan['compoundNextSourcePromotionSnapshot']['requiredPromotionTickets'], 0, 1);
    $t->throws(InvalidArgumentException::class, static fn () => $summary245($cursor));
};

$tests['compound select window recursive limit current source next245 rejects unexpected promotion ticket'] = static function (TestRunner $t) use ($summary245): void {
    $plan = $summary245();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedPromotionTickets'] = [...$plan['compoundNextSourcePromotionSnapshot']['requiredPromotionTickets'], 'next:' . str_repeat('f', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary245($cursor));
};

$tests['compound select window recursive limit current source next245 executor parity'] = static function (TestRunner $t) use ($sql245, $currentTables245, $summary245): void {
    $t->same(SQLiteSelectSql::execute($sql245, $currentTables245), $summary245()['currentRows']);
};

$tests['compound select window recursive limit current source next245 non overlap'] = static function (TestRunner $t) use ($summary245): void {
    $plan = $summary245();
    $t->contains('extends accepted next243', $plan['non_overlap']);
    $t->true(in_array('compound-window-recursive-next-source-promotion-snapshot', $plan['replanReasons'], true));
    $t->true(in_array('next-source-held-until-current-replay-and-delta-snapshot', $plan['replanReasons'], true));
};

foreach (range(1, 72) as $case) {
    $tests['compound select window recursive limit current source next245 generated promotion snapshot ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNextSourcePromotionSnapshot($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
        $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
        $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
        $cursor['acknowledgedPromotionTickets'] = $plan['compoundNextSourcePromotionSnapshot']['requiredPromotionTickets'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNextSourcePromotionSnapshot($sql, $tables, $nextTables, $cursor);
        $promotion = $plan['compoundNextSourcePromotionSnapshot'];

        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3', 'home_' . $case, 'seed_' . $case . ':2:3:4'], $promotion['nextLabels']);
        $t->same(['plugin_' . $case], $promotion['nextOnlyLabels']);
        $t->same(['rewrite_' . $case], $promotion['currentOnlyLabels']);
        $t->same(2, $promotion['requiredPromotionTicketCount']);
        $t->same(2, $promotion['changedRowCount']);
        $t->same($promotion['promotionSnapshotToken'], $again['compoundNextSourcePromotionSnapshot']['promotionSnapshotToken']);
        $t->same('held-until-current-replay-and-next-delta-snapshot-match', $again['cursor']['nextExposure']);
    };
}

return $tests;
