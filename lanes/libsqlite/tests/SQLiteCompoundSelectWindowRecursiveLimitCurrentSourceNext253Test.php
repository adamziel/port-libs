<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext253Plan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions253 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
];
$nextOptions253 = [
    ...$currentOptions253,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables253 = ['wp_options' => $currentOptions253];
$nextTables253 = ['wp_options' => $nextOptions253];

$sql253 = <<<'SQL'
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

$summary253 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext253Plan::compare($sql253, $currentTables253, $nextTables253, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next253 status dependencies'] = static function (TestRunner $t) use ($summary253): void {
    $plan = $summary253();
    $t->same('compound-select-window-recursive-limit-current-source-next253-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-window-recursive-current-source-admission-next253', $plan['dependencies'], true));
    $t->contains('current-source admission fence', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next253 current labels metrics'] = static function (TestRunner $t) use ($summary253): void {
    $admission = $summary253()['compoundCurrentSourceAdmissionNext253'];
    $t->same(['home', 'seed:2:3', 'rewrite_rules', 'seed:2:3:4'], $admission['currentLabels']);
    $t->same([2, 2, 3, 3], $admission['currentWindowMetrics']);
    $t->same('home', $admission['currentFinalPage'][0]['label']);
    $t->same(2, $admission['currentFinalPage'][0]['metric']);
};

$tests['compound select window recursive limit current source next253 recursive limit lineage'] = static function (TestRunner $t) use ($summary253): void {
    $limit = $summary253()['compoundCurrentSourceAdmissionNext253']['recursiveLimit'];
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $limit['emittedLabels']);
    $t->same(['seed'], $limit['skippedLabels']);
    $t->same(['blogname', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $limit['truncatedLabels']);
};

$tests['compound select window recursive limit current source next253 token shape'] = static function (TestRunner $t) use ($summary253): void {
    $admission = $summary253()['compoundCurrentSourceAdmissionNext253'];
    $t->same(64, strlen($admission['currentSourceAdmissionToken']));
    $t->same(64, strlen($admission['currentRecursiveLimitToken']));
    $t->same(64, strlen($admission['currentWindowPageToken']));
    $t->same(3, $admission['requiredCurrentSourceAckCount']);
    $t->same('current-source:', substr($admission['requiredCurrentSourceAcks'][0], 0, 15));
    $t->same('recursive-limit:', substr($admission['requiredCurrentSourceAcks'][1], 0, 16));
    $t->same('window-page:', substr($admission['requiredCurrentSourceAcks'][2], 0, 12));
};

$tests['compound select window recursive limit current source next253 binds next249 tokens'] = static function (TestRunner $t) use ($summary253): void {
    $plan = $summary253();
    $admission = $plan['compoundCurrentSourceAdmissionNext253'];
    $epoch = $plan['compoundRecursiveWindowPromotionEpochNext249'];
    $t->same($epoch['promotionEpochToken'], $admission['promotionEpochToken']);
    $t->same($epoch['recursiveLineageToken'], $admission['recursiveLineageToken']);
    $t->same($epoch['windowMetricToken'], $admission['windowMetricToken']);
    $t->same(true, $admission['nextSourcePromotionBlocked']);
};

$tests['compound select window recursive limit current source next253 cursor carries fence'] = static function (TestRunner $t) use ($summary253): void {
    $plan = $summary253();
    $cursor = $plan['cursor'];
    $admission = $plan['compoundCurrentSourceAdmissionNext253'];
    $t->same($admission['currentSourceAdmissionToken'], $cursor['currentSourceAdmissionTokenNext253']);
    $t->same($admission['currentRecursiveLimitToken'], $cursor['currentRecursiveLimitTokenNext253']);
    $t->same($admission['currentWindowPageToken'], $cursor['currentWindowPageTokenNext253']);
    $t->same($admission['requiredCurrentSourceAcks'], $cursor['requiredCurrentSourceAcksNext253']);
    $t->same('held-until-current-recursive-limit-window-page-acks-match', $cursor['currentExposureNext253']);
};

$tests['compound select window recursive limit current source next253 accepts exact current-source acknowledgements'] = static function (TestRunner $t) use ($summary253): void {
    $plan = $summary253();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
    $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
    $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
    $cursor['acknowledgedPromotionTicketsNext245'] = $plan['compoundNextSourcePromotionSnapshotNext245']['requiredPromotionTickets'];
    $cursor['acknowledgedPromotionEpochAcksNext249'] = $plan['compoundRecursiveWindowPromotionEpochNext249']['requiredPromotionEpochAcks'];
    $cursor['acknowledgedCurrentSourceAcksNext253'] = $plan['compoundCurrentSourceAdmissionNext253']['requiredCurrentSourceAcks'];
    $again = $summary253($cursor);
    $t->same($plan['compoundCurrentSourceAdmissionNext253']['currentSourceAdmissionToken'], $again['compoundCurrentSourceAdmissionNext253']['currentSourceAdmissionToken']);
    $t->same($plan['compoundCurrentSourceAdmissionNext253']['currentWindowPageToken'], $again['compoundCurrentSourceAdmissionNext253']['currentWindowPageToken']);
};

$tests['compound select window recursive limit current source next253 rejects stale admission token'] = static function (TestRunner $t) use ($summary253): void {
    $cursor = $summary253()['cursor'];
    $cursor['currentSourceAdmissionTokenNext253'] = str_repeat('a', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary253($cursor));
};

$tests['compound select window recursive limit current source next253 rejects stale recursive limit token'] = static function (TestRunner $t) use ($summary253): void {
    $cursor = $summary253()['cursor'];
    $cursor['currentRecursiveLimitTokenNext253'] = str_repeat('b', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary253($cursor));
};

$tests['compound select window recursive limit current source next253 rejects stale window page token'] = static function (TestRunner $t) use ($summary253): void {
    $cursor = $summary253()['cursor'];
    $cursor['currentWindowPageTokenNext253'] = str_repeat('c', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary253($cursor));
};

$tests['compound select window recursive limit current source next253 rejects missing current-source ack'] = static function (TestRunner $t) use ($summary253): void {
    $cursor = $summary253()['cursor'];
    $cursor['acknowledgedCurrentSourceAcksNext253'] = array_slice($cursor['requiredCurrentSourceAcksNext253'], 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $summary253($cursor));
};

$tests['compound select window recursive limit current source next253 rejects unexpected current-source ack'] = static function (TestRunner $t) use ($summary253): void {
    $cursor = $summary253()['cursor'];
    $cursor['acknowledgedCurrentSourceAcksNext253'] = [...$cursor['requiredCurrentSourceAcksNext253'], 'window-page:' . str_repeat('d', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary253($cursor));
};

$tests['compound select window recursive limit current source next253 executor parity'] = static function (TestRunner $t) use ($sql253, $currentTables253, $summary253): void {
    $t->same(SQLiteSelectSql::execute($sql253, $currentTables253), $summary253()['currentRows']);
};

$tests['compound select window recursive limit current source next253 non overlap'] = static function (TestRunner $t) use ($summary253): void {
    $plan = $summary253();
    $t->contains('extends accepted next249', $plan['non_overlap']);
    $t->true(in_array('compound-window-recursive-current-source-admission-next253', $plan['replanReasons'], true));
    $t->true(in_array('current-source-held-until-recursive-limit-window-page-acks-next253', $plan['replanReasons'], true));
};

foreach (range(1, 54) as $case) {
    $tests['compound select window recursive limit current source next253 generated current-source admission ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext253Plan::compare($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentDequeueAcksNext237'] = $plan['currentSourceDequeueNext237']['requiredCurrentDequeueAcks'];
        $cursor['acknowledgedSpilloverAcksNext240'] = $plan['compoundFinalPageSpilloverDrainNext240']['requiredSpilloverAcks'];
        $cursor['acknowledgedReplayTicketsNext243'] = $plan['compoundWindowReplayFenceNext243']['requiredReplayTickets'];
        $cursor['acknowledgedPromotionTicketsNext245'] = $plan['compoundNextSourcePromotionSnapshotNext245']['requiredPromotionTickets'];
        $cursor['acknowledgedPromotionEpochAcksNext249'] = $plan['compoundRecursiveWindowPromotionEpochNext249']['requiredPromotionEpochAcks'];
        $cursor['acknowledgedCurrentSourceAcksNext253'] = $plan['compoundCurrentSourceAdmissionNext253']['requiredCurrentSourceAcks'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext253Plan::compare($sql, $tables, $nextTables, $cursor);
        $admission = $plan['compoundCurrentSourceAdmissionNext253'];

        $t->same(['home_' . $case, 'seed_' . $case . ':2:3', 'rewrite_' . $case, 'seed_' . $case . ':2:3:4'], $admission['currentLabels']);
        $t->same([2, 2, 3, 3], $admission['currentWindowMetrics']);
        $t->same(['seed_' . $case], $admission['recursiveLimit']['skippedLabels']);
        $t->same(3, $admission['requiredCurrentSourceAckCount']);
        $t->same(true, $admission['nextSourcePromotionBlocked']);
        $t->same($admission['currentSourceAdmissionToken'], $again['compoundCurrentSourceAdmissionNext253']['currentSourceAdmissionToken']);
        $t->same('held-until-current-recursive-limit-window-page-acks-match', $again['cursor']['currentExposureNext253']);
    };
}

return $tests;
