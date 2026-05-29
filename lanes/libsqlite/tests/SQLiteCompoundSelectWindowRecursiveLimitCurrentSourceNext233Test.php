<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions233 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 130],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 112],
    ['option_id' => 3, 'option_name' => 'plugin_old', 'autoload' => 'yes', 'score' => 94],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 40],
];
$nextOptions233 = [
    ...$currentOptions233,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 124],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 105],
];
$currentTables233 = ['wp_options' => $currentOptions233];
$nextTables233 = ['wp_options' => $nextOptions233];

$sql233 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 144)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 8
      FROM q
     WHERE id < 8
     ORDER BY score DESC
     LIMIT 7 OFFSET 1
)
SELECT id,
       label,
       avg(score) OVER (
           ORDER BY score DESC
           ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING
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
SELECT id,
       label,
       metric
  FROM (
       SELECT id,
              label,
              avg(score) OVER (
                  ORDER BY score DESC
                  ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING
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
SELECT id,
       label,
       metric
  FROM (
       SELECT id,
              label,
              avg(score) OVER (
                  ORDER BY score DESC
                  ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING
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

$summary233 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext233($sql233, $currentTables233, $nextTables233, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next233 status dependencies'] = static function (TestRunner $t) use ($summary233): void {
    $plan = $summary233();
    $t->same('compound-select-window-recursive-limit-current-source-next233-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-window-current-source-resume-ordinal-next233', $plan['dependencies'], true));
    $t->contains('final-order ordinal acknowledgement', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next233 resume metadata'] = static function (TestRunner $t) use ($summary233): void {
    $resume = $summary233()['currentSourceResumeNext233'];
    $t->same(64, strlen($resume['currentResumeToken']));
    $t->same(6, $resume['requiredAckCount']);
    $t->same(['siteurl', 'seed:2:3', 'home', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $resume['currentFinalOrderLabels']);
    $t->same(['siteurl', 'seed:2:3', 'plugin_prime', 'home', 'seed:2:3:4:5', 'theme_mods_next'], $resume['nextFinalOrderLabels']);
    $t->same('held-until-current-final-order-ordinals-acked', $resume['nextExposure']);
};

$tests['compound select window recursive limit current source next233 next source ordinal boundary'] = static function (TestRunner $t) use ($summary233): void {
    $resume = $summary233()['currentSourceResumeNext233'];
    $t->same(6, $resume['currentLastOrdinal']);
    $t->same(1, $resume['nextFirstOrdinal']);
    $t->same(['plugin_prime', 'theme_mods_next'], $resume['nextOnlyLabels']);
    $t->same(['siteurl', 'seed:2:3', 'home', 'seed:2:3:4:5'], $resume['currentOnlyLabels']);
    $t->true($resume['tokensDiffer']);
    $t->same('compound-window-next233-current-final-order-resume-fences-next-source', $resume['yieldBoundary']);
};

$tests['compound select window recursive limit current source next233 cursor carries resume token'] = static function (TestRunner $t) use ($summary233): void {
    $plan = $summary233();
    $cursor = $plan['cursor'];
    $t->same($plan['currentSourceResumeNext233']['currentResumeToken'], $cursor['currentResumeToken']);
    $t->same($plan['currentSourceResumeNext233']['requiredCurrentOrdinalAcks'], $cursor['requiredCurrentOrdinalAcks']);
    $t->same('held-until-current-final-order-ordinals-acked', $cursor['nextExposure']);
};

$tests['compound select window recursive limit current source next233 accepts acknowledged ordinals'] = static function (TestRunner $t) use ($summary233): void {
    $plan = $summary233();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentOrdinalAcks'] = $plan['currentSourceResumeNext233']['requiredCurrentOrdinalAcks'];
    $again = $summary233($cursor);
    $t->same($plan['currentSourceResumeNext233']['currentResumeToken'], $again['currentSourceResumeNext233']['currentResumeToken']);
};

$tests['compound select window recursive limit current source next233 rejects stale resume token'] = static function (TestRunner $t) use ($summary233): void {
    $cursor = $summary233()['cursor'];
    $cursor['currentResumeToken'] = str_repeat('c', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary233($cursor));
};

$tests['compound select window recursive limit current source next233 rejects missing ordinal ack'] = static function (TestRunner $t) use ($summary233): void {
    $plan = $summary233();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentOrdinalAcks'] = array_slice($plan['currentSourceResumeNext233']['requiredCurrentOrdinalAcks'], 0, 5);
    $t->throws(InvalidArgumentException::class, static fn () => $summary233($cursor));
};

$tests['compound select window recursive limit current source next233 rejects unexpected ordinal ack'] = static function (TestRunner $t) use ($summary233): void {
    $plan = $summary233();
    $cursor = $plan['cursor'];
    $cursor['acknowledgedCurrentOrdinalAcks'] = [...$plan['currentSourceResumeNext233']['requiredCurrentOrdinalAcks'], str_repeat('d', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary233($cursor));
};

$tests['compound select window recursive limit current source next233 executor parity'] = static function (TestRunner $t) use ($sql233, $currentTables233, $summary233): void {
    $t->same(SQLiteSelectSql::execute($sql233, $currentTables233), $summary233()['currentRows']);
};

$tests['compound select window recursive limit current source next233 non overlap'] = static function (TestRunner $t) use ($summary233): void {
    $plan = $summary233();
    $t->contains('extends accepted next230', $plan['non_overlap']);
    $t->true(in_array('compound-window-current-final-order-ordinal-resume-next233', $plan['replanReasons'], true));
    $t->true(in_array('next-source-compound-page-held-until-current-ordinal-acks-next233', $plan['replanReasons'], true));
};

foreach (range(1, 60) as $case) {
    $tests['compound select window recursive limit current source next233 generated ordinal resume ' . $case] = static function (TestRunner $t) use ($case): void {
        $finalLimit = 4 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 130 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 112 + $case],
                ['option_id' => 3, 'option_name' => 'plugin_old_' . $case, 'autoload' => 'yes', 'score' => 94 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 4, 'option_name' => 'plugin_prime_' . $case, 'autoload' => 'yes', 'score' => 124 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (144 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 8 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, avg(score) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS metric FROM q UNION SELECT option_id AS id, option_name AS label, first_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes' INTERSECT SELECT id, label, metric FROM (SELECT id, label, avg(score) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS metric FROM q UNION SELECT option_id AS id, option_name AS label, first_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes') EXCEPT SELECT id, label, metric FROM (SELECT id, label, avg(score) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS metric FROM q WHERE id = 4 UNION SELECT option_id AS id, option_name AS label, first_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS metric FROM wp_options WHERE option_name = 'plugin_old_{$case}') ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext233($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentOrdinalAcks'] = $plan['currentSourceResumeNext233']['requiredCurrentOrdinalAcks'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext233($sql, $tables, $nextTables, $cursor);

        $t->same($finalLimit, $plan['currentSourceResumeNext233']['requiredAckCount']);
        $t->same($finalLimit, $plan['currentSourceResumeNext233']['currentLastOrdinal']);
        $t->same(1, $plan['currentSourceResumeNext233']['nextFirstOrdinal']);
        $t->same(['plugin_prime_' . $case], $plan['currentSourceResumeNext233']['nextOnlyLabels']);
        $t->same($plan['currentSourceResumeNext233']['currentResumeToken'], $again['currentSourceResumeNext233']['currentResumeToken']);
        $t->same('held-until-current-final-order-ordinals-acked', $again['cursor']['nextExposure']);
    };
}

return $tests;
