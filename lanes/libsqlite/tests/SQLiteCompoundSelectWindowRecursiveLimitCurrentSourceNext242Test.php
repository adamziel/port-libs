<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions242 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 5, 'option_name' => 'cache_bucket', 'autoload' => 'no', 'score' => 67],
];
$nextOptions242 = [
    ...$currentOptions242,
    ['option_id' => 6, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables242 = ['wp_options' => $currentOptions242];
$nextTables242 = ['wp_options' => $nextOptions242];

$sql242 = <<<'SQL'
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

$summary242 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveLimitWindowCommitFence($sql242, $currentTables242, $nextTables242, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next242 status dependencies'] = static function (TestRunner $t) use ($summary242): void {
    $plan = $summary242();
    $t->same('compound-select-window-recursive-limit-current-source-next242-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-recursive-window-commit-fence-current-source-next242', $plan['dependencies'], true));
    $t->contains('next242 reuses native SELECT SQL compound', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next242 commit fence labels'] = static function (TestRunner $t) use ($summary242): void {
    $fence = $summary242()['recursiveLimitWindowCommitFenceNext242'];
    $t->same(['home', 'seed:2:3:4', 'rewrite_rules'], $fence['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3:4', 'home'], $fence['nextLabels']);
    $t->same(['plugin_prime'], $fence['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $fence['currentOnlyLabels']);
};

$tests['compound select window recursive limit current source next242 token shape'] = static function (TestRunner $t) use ($summary242): void {
    $fence = $summary242()['recursiveLimitWindowCommitFenceNext242'];
    $t->same(64, strlen($fence['commitFenceToken']));
    $t->same(64, strlen($fence['recursiveQueueToken']));
    $t->same(64, strlen($fence['windowOutputToken']));
    $t->same(64, strlen($fence['finalPageToken']));
    $t->same(3, $fence['requiredCommitFenceAckCount']);
    $t->same('recursive:', substr($fence['requiredCommitFenceAcks'][0], 0, 10));
    $t->same('window:', substr($fence['requiredCommitFenceAcks'][1], 0, 7));
    $t->same('final-page:', substr($fence['requiredCommitFenceAcks'][2], 0, 11));
};

$tests['compound select window recursive limit current source next242 carries recursive and window details'] = static function (TestRunner $t) use ($summary242): void {
    $fence = $summary242()['recursiveLimitWindowCommitFenceNext242'];
    $t->same(['seed', 'seed:2'], $fence['recursiveSkippedLabels']);
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $fence['recursiveEmittedLabels']);
    $t->same(['dense_rank'], $fence['windowFunctions']);
    $t->same('held-until-recursive-window-and-final-page-acks-match', $fence['admissionState']);
};

$tests['compound select window recursive limit current source next242 promotes after all acknowledgements'] = static function (TestRunner $t) use ($summary242): void {
    $first = $summary242();
    $cursor = $first['cursor'];
    $cursor['acknowledgedCurrentAcksNext232'] = $cursor['requiredCurrentAcksNext232'];
    $cursor['acknowledgedPromotionAcksNext235'] = $cursor['requiredPromotionAcksNext235'];
    $cursor['acknowledgedSourceGenerationAcksNext238'] = $cursor['requiredSourceGenerationAcksNext238'];
    $cursor['acknowledgedCommitFenceAcksNext242'] = $cursor['requiredCommitFenceAcksNext242'];
    $again = $summary242($cursor);
    $t->same($first['recursiveLimitWindowCommitFenceNext242']['commitFenceToken'], $again['recursiveLimitWindowCommitFenceNext242']['commitFenceToken']);
    $t->same($first['recursiveLimitWindowCommitFenceNext242']['nextSourceCursor'], $again['recursiveLimitWindowCommitFenceNext242']['nextSourceCursor']);
};

$tests['compound select window recursive limit current source next242 rejects stale commit token'] = static function (TestRunner $t) use ($summary242): void {
    $cursor = $summary242()['cursor'];
    $cursor['commitFenceTokenNext242'] = str_repeat('8', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary242($cursor));
};

$tests['compound select window recursive limit current source next242 rejects stale recursive token'] = static function (TestRunner $t) use ($summary242): void {
    $cursor = $summary242()['cursor'];
    $cursor['recursiveQueueTokenNext242'] = str_repeat('9', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary242($cursor));
};

$tests['compound select window recursive limit current source next242 rejects stale window token'] = static function (TestRunner $t) use ($summary242): void {
    $cursor = $summary242()['cursor'];
    $cursor['windowOutputTokenNext242'] = str_repeat('a', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary242($cursor));
};

$tests['compound select window recursive limit current source next242 rejects stale final page token'] = static function (TestRunner $t) use ($summary242): void {
    $cursor = $summary242()['cursor'];
    $cursor['finalPageTokenNext242'] = str_repeat('b', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary242($cursor));
};

$tests['compound select window recursive limit current source next242 rejects missing commit ack'] = static function (TestRunner $t) use ($summary242): void {
    $cursor = $summary242()['cursor'];
    $cursor['acknowledgedCommitFenceAcksNext242'] = array_slice($cursor['requiredCommitFenceAcksNext242'], 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $summary242($cursor));
};

$tests['compound select window recursive limit current source next242 rejects unexpected commit ack'] = static function (TestRunner $t) use ($summary242): void {
    $cursor = $summary242()['cursor'];
    $cursor['acknowledgedCommitFenceAcksNext242'] = [...$cursor['requiredCommitFenceAcksNext242'], 'window:' . str_repeat('d', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary242($cursor));
};

$tests['compound select window recursive limit current source next242 executor parity'] = static function (TestRunner $t) use ($sql242, $currentTables242, $summary242): void {
    $t->same(SQLiteSelectSql::execute($sql242, $currentTables242), $summary242()['currentRows']);
};

$tests['compound select window recursive limit current source next242 non overlap'] = static function (TestRunner $t) use ($summary242): void {
    $plan = $summary242();
    $t->contains('extends accepted next238', $plan['non_overlap']);
    $t->true(in_array('compound-recursive-limit-window-commit-fence-next242', $plan['replanReasons'], true));
    $t->true(in_array('recursive-queue-window-final-page-acks-next242', $plan['replanReasons'], true));
};

foreach (range(1, 72) as $case) {
    $tests['compound select window recursive limit current source next242 generated commit fence ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveLimitWindowCommitFence($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentAcksNext232'] = $cursor['requiredCurrentAcksNext232'];
        $cursor['acknowledgedPromotionAcksNext235'] = $cursor['requiredPromotionAcksNext235'];
        $cursor['acknowledgedSourceGenerationAcksNext238'] = $cursor['requiredSourceGenerationAcksNext238'];
        $cursor['acknowledgedCommitFenceAcksNext242'] = $cursor['requiredCommitFenceAcksNext242'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveLimitWindowCommitFence($sql, $tables, $nextTables, $cursor);
        $fence = $plan['recursiveLimitWindowCommitFenceNext242'];

        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3:4', 'home_' . $case], $fence['nextLabels']);
        $t->same(['plugin_' . $case], $fence['nextOnlyLabels']);
        $t->same(['rewrite_' . $case], $fence['currentOnlyLabels']);
        $t->same(3, $fence['requiredCommitFenceAckCount']);
        $t->same('held-until-recursive-window-and-final-page-acks-match', $fence['admissionState']);
        $t->same(['dense_rank'], $fence['windowFunctions']);
        $t->same($fence['commitFenceToken'], $again['recursiveLimitWindowCommitFenceNext242']['commitFenceToken']);
    };
}

return $tests;
