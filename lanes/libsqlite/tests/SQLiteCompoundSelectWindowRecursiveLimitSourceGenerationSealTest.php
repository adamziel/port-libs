<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptionsSourceGenerationSeal = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 5, 'option_name' => 'transient_cache', 'autoload' => 'no', 'score' => 65],
];
$nextOptionsSourceGenerationSeal = [
    ...$currentOptionsSourceGenerationSeal,
    ['option_id' => 6, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTablesSourceGenerationSeal = ['wp_options' => $currentOptionsSourceGenerationSeal];
$nextTablesSourceGenerationSeal = ['wp_options' => $nextOptionsSourceGenerationSeal];

$sqlSourceGenerationSeal = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     LIMIT 5 OFFSET 2
)
SELECT id,
       label,
       dense_rank() OVER (ORDER BY score DESC) AS rn
  FROM q
UNION
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn
  FROM wp_options
 WHERE option_name IN ('siteurl')
 ORDER BY rn, label
 LIMIT 3 OFFSET 1
SQL;

$summarySourceGenerationSeal = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareSourceGenerationSeal($sqlSourceGenerationSeal, $currentTablesSourceGenerationSeal, $nextTablesSourceGenerationSeal, $cursor);
$tests = [];

$tests['compound select window recursive limit current source source-generation-seal status dependencies'] = static function (TestRunner $t) use ($summarySourceGenerationSeal): void {
    $plan = $summarySourceGenerationSeal();
    $t->same('compound-select-window-recursive-limit-current-source-generation-seal-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-recursive-window-current-source-generation-seal', $plan['dependencies'], true));
    $t->contains('source-generation seal over final compound LIMIT boundary', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source source-generation-seal seal labels'] = static function (TestRunner $t) use ($summarySourceGenerationSeal): void {
    $seal = $summarySourceGenerationSeal()['sourceGenerationSeal'];
    $t->same(['home', 'seed:2:3:4', 'rewrite_rules'], $seal['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3:4', 'home'], $seal['nextLabels']);
    $t->same(['plugin_prime'], $seal['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $seal['currentOnlyLabels']);
};

$tests['compound select window recursive limit current source source-generation-seal boundary labels'] = static function (TestRunner $t) use ($summarySourceGenerationSeal): void {
    $seal = $summarySourceGenerationSeal()['sourceGenerationSeal'];
    $t->same(['seed:2:3'], $seal['currentSkippedLabels']);
    $t->same(['seed:2:3'], $seal['nextSkippedLabels']);
    $t->same(['seed:2:3:4:5', 'blogname', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $seal['currentTruncatedLabels']);
    $t->same(['seed:2:3:4:5', 'rewrite_rules', 'seed:2:3:4:5:6', 'blogname', 'seed:2:3:4:5:6:7'], $seal['nextTruncatedLabels']);
};

$tests['compound select window recursive limit current source source-generation-seal token shape'] = static function (TestRunner $t) use ($summarySourceGenerationSeal): void {
    $seal = $summarySourceGenerationSeal()['sourceGenerationSeal'];
    $t->same(64, strlen($seal['sourceGenerationToken']));
    $t->same(64, strlen($seal['finalBoundaryToken']));
    $t->same(64, strlen($seal['promotionBarrierToken']));
    $t->same(2, $seal['requiredSourceGenerationAckCount']);
    $t->same('generation:', substr($seal['requiredSourceGenerationAcks'][0], 0, 11));
    $t->same('boundary:', substr($seal['requiredSourceGenerationAcks'][1], 0, 9));
};

$tests['compound select window recursive limit current source source-generation-seal generation changed flags'] = static function (TestRunner $t) use ($summarySourceGenerationSeal): void {
    $generation = $summarySourceGenerationSeal()['sourceGenerationSeal']['sourceGeneration'];
    $t->same(64, strlen($generation['currentPageHash']));
    $t->same(64, strlen($generation['nextPageHash']));
    $t->same(64, strlen($generation['currentBoundaryHash']));
    $t->same(64, strlen($generation['nextBoundaryHash']));
    $t->true($generation['pageChanged']);
    $t->true($generation['boundaryChanged']);
};

$tests['compound select window recursive limit current source source-generation-seal promotes after all acknowledgements'] = static function (TestRunner $t) use ($summarySourceGenerationSeal): void {
    $first = $summarySourceGenerationSeal();
    $cursor = $first['cursor'];
    $cursor['acknowledgedCurrentAcksCurrentPageHandoff'] = $cursor['requiredCurrentAcksCurrentPageHandoff'];
    $cursor['acknowledgedPromotionAcksRecursiveWindowPromotionBarrier'] = $cursor['requiredPromotionAcksRecursiveWindowPromotionBarrier'];
    $cursor['acknowledgedSourceGenerationAcksSourceGenerationSeal'] = $cursor['requiredSourceGenerationAcksSourceGenerationSeal'];
    $again = $summarySourceGenerationSeal($cursor);
    $t->same($first['sourceGenerationSeal']['sourceGenerationToken'], $again['sourceGenerationSeal']['sourceGenerationToken']);
    $t->same($first['sourceGenerationSeal']['nextSourceCursor'], $again['sourceGenerationSeal']['nextSourceCursor']);
};

$tests['compound select window recursive limit current source source-generation-seal rejects stale generation token'] = static function (TestRunner $t) use ($summarySourceGenerationSeal): void {
    $cursor = $summarySourceGenerationSeal()['cursor'];
    $cursor['sourceGenerationTokenSourceGenerationSeal'] = str_repeat('4', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summarySourceGenerationSeal($cursor));
};

$tests['compound select window recursive limit current source source-generation-seal rejects stale boundary token'] = static function (TestRunner $t) use ($summarySourceGenerationSeal): void {
    $cursor = $summarySourceGenerationSeal()['cursor'];
    $cursor['finalBoundaryTokenSourceGenerationSeal'] = str_repeat('5', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summarySourceGenerationSeal($cursor));
};

$tests['compound select window recursive limit current source source-generation-seal rejects missing generation ack'] = static function (TestRunner $t) use ($summarySourceGenerationSeal): void {
    $cursor = $summarySourceGenerationSeal()['cursor'];
    $cursor['acknowledgedSourceGenerationAcksSourceGenerationSeal'] = array_slice($cursor['requiredSourceGenerationAcksSourceGenerationSeal'], 0, 1);
    $t->throws(InvalidArgumentException::class, static fn () => $summarySourceGenerationSeal($cursor));
};

$tests['compound select window recursive limit current source source-generation-seal rejects unexpected generation ack'] = static function (TestRunner $t) use ($summarySourceGenerationSeal): void {
    $cursor = $summarySourceGenerationSeal()['cursor'];
    $cursor['acknowledgedSourceGenerationAcksSourceGenerationSeal'] = [...$cursor['requiredSourceGenerationAcksSourceGenerationSeal'], 'boundary:' . str_repeat('c', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summarySourceGenerationSeal($cursor));
};

$tests['compound select window recursive limit current source source-generation-seal executor parity'] = static function (TestRunner $t) use ($sqlSourceGenerationSeal, $currentTablesSourceGenerationSeal, $summarySourceGenerationSeal): void {
    $t->same(SQLiteSelectSql::execute($sqlSourceGenerationSeal, $currentTablesSourceGenerationSeal), $summarySourceGenerationSeal()['currentRows']);
};

$tests['compound select window recursive limit current source source-generation-seal non overlap'] = static function (TestRunner $t) use ($summarySourceGenerationSeal): void {
    $plan = $summarySourceGenerationSeal();
    $t->contains('extends accepted recursive-window-promotion-barrier', $plan['non_overlap']);
    $t->true(in_array('compound-recursive-window-source-generation-seal', $plan['replanReasons'], true));
    $t->true(in_array('final-compound-limit-boundary-source-generation-seal', $plan['replanReasons'], true));
};

foreach (range(1, 64) as $case) {
    $tests['compound select window recursive limit current source source-generation-seal generated source generation seal ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (140 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 8 LIMIT 5 OFFSET 2) SELECT id, label, dense_rank() OVER (ORDER BY score DESC) AS rn FROM q UNION SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn FROM wp_options WHERE option_name IN ('siteurl_{$case}') ORDER BY rn, label LIMIT 3 OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareSourceGenerationSeal($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentAcksCurrentPageHandoff'] = $cursor['requiredCurrentAcksCurrentPageHandoff'];
        $cursor['acknowledgedPromotionAcksRecursiveWindowPromotionBarrier'] = $cursor['requiredPromotionAcksRecursiveWindowPromotionBarrier'];
        $cursor['acknowledgedSourceGenerationAcksSourceGenerationSeal'] = $cursor['requiredSourceGenerationAcksSourceGenerationSeal'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareSourceGenerationSeal($sql, $tables, $nextTables, $cursor);
        $seal = $plan['sourceGenerationSeal'];

        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3:4', 'home_' . $case], $seal['nextLabels']);
        $t->same(['plugin_' . $case], $seal['nextOnlyLabels']);
        $t->same(['rewrite_' . $case], $seal['currentOnlyLabels']);
        $t->same(2, $seal['requiredSourceGenerationAckCount']);
        $t->same('held-until-source-generation-and-final-boundary-acks-match', $seal['admissionState']);
        $t->true($seal['sourceGeneration']['pageChanged']);
        $t->true($seal['sourceGeneration']['boundaryChanged']);
        $t->same($seal['sourceGenerationToken'], $again['sourceGenerationSeal']['sourceGenerationToken']);
    };
}

return $tests;
