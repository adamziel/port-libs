<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions238 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 5, 'option_name' => 'transient_cache', 'autoload' => 'no', 'score' => 65],
];
$nextOptions238 = [
    ...$currentOptions238,
    ['option_id' => 6, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables238 = ['wp_options' => $currentOptions238];
$nextTables238 = ['wp_options' => $nextOptions238];

$sql238 = <<<'SQL'
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

$summary238 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareSourceGenerationSeal($sql238, $currentTables238, $nextTables238, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next238 status dependencies'] = static function (TestRunner $t) use ($summary238): void {
    $plan = $summary238();
    $t->same('compound-select-window-recursive-limit-current-source-next238-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-recursive-window-current-source-generation-next238', $plan['dependencies'], true));
    $t->contains('source-generation seal over final compound LIMIT boundary', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next238 seal labels'] = static function (TestRunner $t) use ($summary238): void {
    $seal = $summary238()['sourceGenerationSealNext238'];
    $t->same(['home', 'seed:2:3:4', 'rewrite_rules'], $seal['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3:4', 'home'], $seal['nextLabels']);
    $t->same(['plugin_prime'], $seal['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $seal['currentOnlyLabels']);
};

$tests['compound select window recursive limit current source next238 boundary labels'] = static function (TestRunner $t) use ($summary238): void {
    $seal = $summary238()['sourceGenerationSealNext238'];
    $t->same(['seed:2:3'], $seal['currentSkippedLabels']);
    $t->same(['seed:2:3'], $seal['nextSkippedLabels']);
    $t->same(['seed:2:3:4:5', 'blogname', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $seal['currentTruncatedLabels']);
    $t->same(['seed:2:3:4:5', 'rewrite_rules', 'seed:2:3:4:5:6', 'blogname', 'seed:2:3:4:5:6:7'], $seal['nextTruncatedLabels']);
};

$tests['compound select window recursive limit current source next238 token shape'] = static function (TestRunner $t) use ($summary238): void {
    $seal = $summary238()['sourceGenerationSealNext238'];
    $t->same(64, strlen($seal['sourceGenerationToken']));
    $t->same(64, strlen($seal['finalBoundaryToken']));
    $t->same(64, strlen($seal['promotionBarrierToken']));
    $t->same(2, $seal['requiredSourceGenerationAckCount']);
    $t->same('generation:', substr($seal['requiredSourceGenerationAcks'][0], 0, 11));
    $t->same('boundary:', substr($seal['requiredSourceGenerationAcks'][1], 0, 9));
};

$tests['compound select window recursive limit current source next238 generation changed flags'] = static function (TestRunner $t) use ($summary238): void {
    $generation = $summary238()['sourceGenerationSealNext238']['sourceGeneration'];
    $t->same(64, strlen($generation['currentPageHash']));
    $t->same(64, strlen($generation['nextPageHash']));
    $t->same(64, strlen($generation['currentBoundaryHash']));
    $t->same(64, strlen($generation['nextBoundaryHash']));
    $t->true($generation['pageChanged']);
    $t->true($generation['boundaryChanged']);
};

$tests['compound select window recursive limit current source next238 promotes after all acknowledgements'] = static function (TestRunner $t) use ($summary238): void {
    $first = $summary238();
    $cursor = $first['cursor'];
    $cursor['acknowledgedCurrentAcksNext232'] = $cursor['requiredCurrentAcksNext232'];
    $cursor['acknowledgedPromotionAcksNext235'] = $cursor['requiredPromotionAcksNext235'];
    $cursor['acknowledgedSourceGenerationAcksNext238'] = $cursor['requiredSourceGenerationAcksNext238'];
    $again = $summary238($cursor);
    $t->same($first['sourceGenerationSealNext238']['sourceGenerationToken'], $again['sourceGenerationSealNext238']['sourceGenerationToken']);
    $t->same($first['sourceGenerationSealNext238']['nextSourceCursor'], $again['sourceGenerationSealNext238']['nextSourceCursor']);
};

$tests['compound select window recursive limit current source next238 rejects stale generation token'] = static function (TestRunner $t) use ($summary238): void {
    $cursor = $summary238()['cursor'];
    $cursor['sourceGenerationTokenNext238'] = str_repeat('4', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary238($cursor));
};

$tests['compound select window recursive limit current source next238 rejects stale boundary token'] = static function (TestRunner $t) use ($summary238): void {
    $cursor = $summary238()['cursor'];
    $cursor['finalBoundaryTokenNext238'] = str_repeat('5', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary238($cursor));
};

$tests['compound select window recursive limit current source next238 rejects missing generation ack'] = static function (TestRunner $t) use ($summary238): void {
    $cursor = $summary238()['cursor'];
    $cursor['acknowledgedSourceGenerationAcksNext238'] = array_slice($cursor['requiredSourceGenerationAcksNext238'], 0, 1);
    $t->throws(InvalidArgumentException::class, static fn () => $summary238($cursor));
};

$tests['compound select window recursive limit current source next238 rejects unexpected generation ack'] = static function (TestRunner $t) use ($summary238): void {
    $cursor = $summary238()['cursor'];
    $cursor['acknowledgedSourceGenerationAcksNext238'] = [...$cursor['requiredSourceGenerationAcksNext238'], 'boundary:' . str_repeat('c', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary238($cursor));
};

$tests['compound select window recursive limit current source next238 executor parity'] = static function (TestRunner $t) use ($sql238, $currentTables238, $summary238): void {
    $t->same(SQLiteSelectSql::execute($sql238, $currentTables238), $summary238()['currentRows']);
};

$tests['compound select window recursive limit current source next238 non overlap'] = static function (TestRunner $t) use ($summary238): void {
    $plan = $summary238();
    $t->contains('extends accepted next235', $plan['non_overlap']);
    $t->true(in_array('compound-recursive-window-source-generation-seal-next238', $plan['replanReasons'], true));
    $t->true(in_array('final-compound-limit-boundary-next238', $plan['replanReasons'], true));
};

foreach (range(1, 64) as $case) {
    $tests['compound select window recursive limit current source next238 generated source generation seal ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $cursor['acknowledgedCurrentAcksNext232'] = $cursor['requiredCurrentAcksNext232'];
        $cursor['acknowledgedPromotionAcksNext235'] = $cursor['requiredPromotionAcksNext235'];
        $cursor['acknowledgedSourceGenerationAcksNext238'] = $cursor['requiredSourceGenerationAcksNext238'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareSourceGenerationSeal($sql, $tables, $nextTables, $cursor);
        $seal = $plan['sourceGenerationSealNext238'];

        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3:4', 'home_' . $case], $seal['nextLabels']);
        $t->same(['plugin_' . $case], $seal['nextOnlyLabels']);
        $t->same(['rewrite_' . $case], $seal['currentOnlyLabels']);
        $t->same(2, $seal['requiredSourceGenerationAckCount']);
        $t->same('held-until-source-generation-and-final-boundary-acks-match', $seal['admissionState']);
        $t->true($seal['sourceGeneration']['pageChanged']);
        $t->true($seal['sourceGeneration']['boundaryChanged']);
        $t->same($seal['sourceGenerationToken'], $again['sourceGenerationSealNext238']['sourceGenerationToken']);
    };
}

return $tests;
