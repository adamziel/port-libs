<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions247 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 110],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 85],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 75],
    ['option_id' => 5, 'option_name' => 'transient_cache', 'autoload' => 'no', 'score' => 65],
];
$nextOptions247 = [
    ...$currentOptions247,
    ['option_id' => 6, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 105],
];
$currentTables247 = ['wp_options' => $currentOptions247];
$nextTables247 = ['wp_options' => $nextOptions247];

$sql247 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 150)
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

$summary247 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveOffsetYieldSeal($sql247, $currentTables247, $nextTables247, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next247 status dependencies'] = static function (TestRunner $t) use ($summary247): void {
    $plan = $summary247();
    $t->same('compound-select-window-recursive-limit-current-source-next247-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-recursive-limit-offset-window-yield-seal-next247', $plan['dependencies'], true));
    $t->contains('skipped-row lineage seal', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next247 seals skipped offset labels'] = static function (TestRunner $t) use ($summary247): void {
    $seal = $summary247()['recursiveOffsetYieldSealNext247'];
    $t->same(['seed', 'seed:2'], $seal['currentSkippedLabels']);
    $t->same(['seed', 'seed:2'], $seal['nextSkippedLabels']);
    $t->same(['home', 'seed:2:3:4', 'rewrite_rules'], $seal['currentResultLabels']);
    $t->same(['plugin_prime', 'seed:2:3:4', 'home'], $seal['nextResultLabels']);
};

$tests['compound select window recursive limit current source next247 token shape'] = static function (TestRunner $t) use ($summary247): void {
    $seal = $summary247()['recursiveOffsetYieldSealNext247'];
    $t->same(64, strlen($seal['recursiveOffsetYieldSealToken']));
    $t->same(64, strlen($seal['currentSkippedWindowToken']));
    $t->same(64, strlen($seal['nextSourceCursorToken']));
    $t->same(3, $seal['requiredRecursiveOffsetYieldAckCount']);
    $t->same('offset-current-skipped:', substr($seal['requiredRecursiveOffsetYieldAcks'][0], 0, 23));
    $t->same('offset-next-cursor:', substr($seal['requiredRecursiveOffsetYieldAcks'][1], 0, 19));
    $t->same('offset-yield-seal:', substr($seal['requiredRecursiveOffsetYieldAcks'][2], 0, 18));
};

$tests['compound select window recursive limit current source next247 metrics and next cursor'] = static function (TestRunner $t) use ($summary247): void {
    $seal = $summary247()['recursiveOffsetYieldSealNext247'];
    $t->same([2, 2, 3], $seal['currentWindowMetrics']);
    $t->same([2, 2, 3], $seal['nextWindowMetrics']);
    $t->same(['plugin_prime'], $seal['nextOnlyLabels']);
    $t->same('held-until-recursive-offset-window-and-next-cursor-acks-match', $seal['yieldDecision']);
    $t->same('compound-window-recursive-limit-next247-offset-skip-before-next-source-cursor', $seal['yieldBoundary']);
};

$tests['compound select window recursive limit current source next247 cursor carries seal'] = static function (TestRunner $t) use ($summary247): void {
    $plan = $summary247();
    $cursor = $plan['cursor'];
    $seal = $plan['recursiveOffsetYieldSealNext247'];
    $t->same($seal['recursiveOffsetYieldSealToken'], $cursor['recursiveOffsetYieldSealTokenNext247']);
    $t->same($seal['currentSkippedWindowToken'], $cursor['currentSkippedWindowTokenNext247']);
    $t->same($seal['nextSourceCursorToken'], $cursor['nextSourceCursorTokenNext247']);
    $t->same($seal['requiredRecursiveOffsetYieldAcks'], $cursor['requiredRecursiveOffsetYieldAcksNext247']);
};

$tests['compound select window recursive limit current source next247 resumes after all acknowledgements'] = static function (TestRunner $t) use ($summary247): void {
    $first = $summary247();
    $cursor = $first['cursor'];
    if (isset($cursor['requiredCurrentAcksNext232'])) {
        $cursor['acknowledgedCurrentAcksNext232'] = $cursor['requiredCurrentAcksNext232'];
    }
    if (isset($cursor['requiredPromotionAcksNext235'])) {
        $cursor['acknowledgedPromotionAcksNext235'] = $cursor['requiredPromotionAcksNext235'];
    }
    $cursor['acknowledgedSourceGenerationAcksNext238'] = $cursor['requiredSourceGenerationAcksNext238'];
    $cursor['acknowledgedResumeAdmissionAcksNext241'] = $cursor['requiredResumeAdmissionAcksNext241'];
    $cursor['acknowledgedRecursiveLimitAcks'] = $cursor['requiredRecursiveLimitAcks'];
    $cursor['acknowledgedRecursiveOffsetYieldAcksNext247'] = $cursor['requiredRecursiveOffsetYieldAcksNext247'];
    $again = $summary247($cursor);
    $t->same($first['recursiveOffsetYieldSealNext247']['recursiveOffsetYieldSealToken'], $again['recursiveOffsetYieldSealNext247']['recursiveOffsetYieldSealToken']);
    $t->same($first['recursiveOffsetYieldSealNext247']['nextSourceCursorToken'], $again['recursiveOffsetYieldSealNext247']['nextSourceCursorToken']);
};

$tests['compound select window recursive limit current source next247 rejects stale seal token'] = static function (TestRunner $t) use ($summary247): void {
    $cursor = $summary247()['cursor'];
    $cursor['recursiveOffsetYieldSealTokenNext247'] = str_repeat('a', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary247($cursor));
};

$tests['compound select window recursive limit current source next247 rejects stale skipped token'] = static function (TestRunner $t) use ($summary247): void {
    $cursor = $summary247()['cursor'];
    $cursor['currentSkippedWindowTokenNext247'] = str_repeat('b', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary247($cursor));
};

$tests['compound select window recursive limit current source next247 rejects stale next cursor token'] = static function (TestRunner $t) use ($summary247): void {
    $cursor = $summary247()['cursor'];
    $cursor['nextSourceCursorTokenNext247'] = str_repeat('c', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary247($cursor));
};

$tests['compound select window recursive limit current source next247 rejects missing offset ack'] = static function (TestRunner $t) use ($summary247): void {
    $cursor = $summary247()['cursor'];
    $cursor['acknowledgedRecursiveOffsetYieldAcksNext247'] = array_slice($cursor['requiredRecursiveOffsetYieldAcksNext247'], 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $summary247($cursor));
};

$tests['compound select window recursive limit current source next247 rejects unexpected offset ack'] = static function (TestRunner $t) use ($summary247): void {
    $cursor = $summary247()['cursor'];
    $cursor['acknowledgedRecursiveOffsetYieldAcksNext247'] = [...$cursor['requiredRecursiveOffsetYieldAcksNext247'], 'offset-yield-seal:' . str_repeat('d', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary247($cursor));
};

$tests['compound select window recursive limit current source next247 executor parity'] = static function (TestRunner $t) use ($sql247, $currentTables247, $summary247): void {
    $t->same(SQLiteSelectSql::execute($sql247, $currentTables247), $summary247()['currentRows']);
};

$tests['compound select window recursive limit current source next247 non overlap'] = static function (TestRunner $t) use ($summary247): void {
    $plan = $summary247();
    $t->contains('extends accepted recursive LIMIT exhaustion', $plan['non_overlap']);
    $t->true(in_array('compound-recursive-limit-offset-yield-seal-next247', $plan['replanReasons'], true));
    $t->true(in_array('compound-window-next-source-cursor-held-after-offset-skip-next247', $plan['replanReasons'], true));
};

foreach (range(1, 64) as $case) {
    $tests['compound select window recursive limit current source next247 generated offset yield seal ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 110 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 100 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_' . $case, 'autoload' => 'yes', 'score' => 85 + $case],
                ['option_id' => 4, 'option_name' => 'blog_' . $case, 'autoload' => 'yes', 'score' => 75 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 105 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (150 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 8 LIMIT 5 OFFSET 2) SELECT id, label, dense_rank() OVER (ORDER BY score DESC) AS rn FROM q UNION SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC) AS rn FROM wp_options WHERE option_name IN ('siteurl_{$case}') ORDER BY rn, label LIMIT 3 OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveOffsetYieldSeal($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        if (isset($cursor['requiredCurrentAcksNext232'])) {
            $cursor['acknowledgedCurrentAcksNext232'] = $cursor['requiredCurrentAcksNext232'];
        }
        if (isset($cursor['requiredPromotionAcksNext235'])) {
            $cursor['acknowledgedPromotionAcksNext235'] = $cursor['requiredPromotionAcksNext235'];
        }
        $cursor['acknowledgedSourceGenerationAcksNext238'] = $cursor['requiredSourceGenerationAcksNext238'];
        $cursor['acknowledgedResumeAdmissionAcksNext241'] = $cursor['requiredResumeAdmissionAcksNext241'];
        $cursor['acknowledgedRecursiveLimitAcks'] = $cursor['requiredRecursiveLimitAcks'];
        $cursor['acknowledgedRecursiveOffsetYieldAcksNext247'] = $cursor['requiredRecursiveOffsetYieldAcksNext247'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveOffsetYieldSeal($sql, $tables, $nextTables, $cursor);
        $seal = $plan['recursiveOffsetYieldSealNext247'];

        $t->same(['seed_' . $case, 'seed_' . $case . ':2'], $seal['currentSkippedLabels']);
        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3:4', 'home_' . $case], $seal['nextResultLabels']);
        $t->same([2, 2, 3], $seal['currentWindowMetrics']);
        $t->same(3, $seal['requiredRecursiveOffsetYieldAckCount']);
        $t->same('held-until-recursive-offset-window-and-next-cursor-acks-match', $seal['yieldDecision']);
        $t->same($seal['recursiveOffsetYieldSealToken'], $again['recursiveOffsetYieldSealNext247']['recursiveOffsetYieldSealToken']);
    };
}

return $tests;
