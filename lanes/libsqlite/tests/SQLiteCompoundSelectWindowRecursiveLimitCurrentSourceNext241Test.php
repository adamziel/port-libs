<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions241 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 5, 'option_name' => 'transient_cache', 'autoload' => 'no', 'score' => 65],
];
$nextOptions241 = [
    ...$currentOptions241,
    ['option_id' => 6, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables241 = ['wp_options' => $currentOptions241];
$nextTables241 = ['wp_options' => $nextOptions241];

$sql241 = <<<'SQL'
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

$summary241 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareResumeAdmissionReceipt($sql241, $currentTables241, $nextTables241, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next241 status dependencies'] = static function (TestRunner $t) use ($summary241): void {
    $plan = $summary241();
    $t->same('compound-select-window-recursive-limit-current-source-next241-ready', $plan['status']);
    $t->true(in_array('sqlite-compound-recursive-window-resume-admission-next241', $plan['dependencies'], true));
    $t->contains('final current/next result-row plus recursive/window/LIMIT receipt tokens', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next241 receipt labels'] = static function (TestRunner $t) use ($summary241): void {
    $receipt = $summary241()['resumeAdmissionReceiptNext241'];
    $t->same(['home', 'seed:2:3:4', 'rewrite_rules'], $receipt['currentLabels']);
    $t->same(['plugin_prime', 'seed:2:3:4', 'home'], $receipt['nextLabels']);
    $t->same(['plugin_prime'], $receipt['nextOnlyLabels']);
    $t->same(['rewrite_rules'], $receipt['currentOnlyLabels']);
};

$tests['compound select window recursive limit current source next241 receipt token shape'] = static function (TestRunner $t) use ($summary241): void {
    $receipt = $summary241()['resumeAdmissionReceiptNext241'];
    $t->same(64, strlen($receipt['resumeAdmissionToken']));
    $t->same(64, strlen($receipt['currentResultToken']));
    $t->same(64, strlen($receipt['nextResultToken']));
    $t->same(64, strlen($receipt['windowLimitReceiptToken']));
    $t->same(3, $receipt['requiredResumeAdmissionAckCount']);
    $t->same('current-result:', substr($receipt['requiredResumeAdmissionAcks'][0], 0, 15));
    $t->same('next-result:', substr($receipt['requiredResumeAdmissionAcks'][1], 0, 12));
    $t->same('window-limit:', substr($receipt['requiredResumeAdmissionAcks'][2], 0, 13));
};

$tests['compound select window recursive limit current source next241 row counts and source seal'] = static function (TestRunner $t) use ($summary241): void {
    $plan = $summary241();
    $receipt = $plan['resumeAdmissionReceiptNext241'];
    $t->same(3, $receipt['currentRowCount']);
    $t->same(3, $receipt['nextRowCount']);
    $t->same($plan['sourceGenerationSealNext238']['sourceGenerationToken'], $receipt['sourceGenerationToken']);
    $t->same($plan['sourceGenerationSealNext238']['finalBoundaryToken'], $receipt['finalBoundaryToken']);
    $t->same($plan['sourceGenerationSealNext238']['nextSourceCursor'], $receipt['nextSourceCursor']);
};

$tests['compound select window recursive limit current source next241 resume after all acknowledgements'] = static function (TestRunner $t) use ($summary241): void {
    $first = $summary241();
    $cursor = $first['cursor'];
    $cursor['acknowledgedCurrentAcksNext232'] = $cursor['requiredCurrentAcksNext232'];
    $cursor['acknowledgedPromotionAcksNext235'] = $cursor['requiredPromotionAcksNext235'];
    $cursor['acknowledgedSourceGenerationAcksNext238'] = $cursor['requiredSourceGenerationAcksNext238'];
    $cursor['acknowledgedResumeAdmissionAcksNext241'] = $cursor['requiredResumeAdmissionAcksNext241'];
    $again = $summary241($cursor);
    $t->same($first['resumeAdmissionReceiptNext241']['resumeAdmissionToken'], $again['resumeAdmissionReceiptNext241']['resumeAdmissionToken']);
    $t->same($first['resumeAdmissionReceiptNext241']['nextSourceCursor'], $again['resumeAdmissionReceiptNext241']['nextSourceCursor']);
};

$tests['compound select window recursive limit current source next241 rejects stale resume token'] = static function (TestRunner $t) use ($summary241): void {
    $cursor = $summary241()['cursor'];
    $cursor['resumeAdmissionTokenNext241'] = str_repeat('6', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary241($cursor));
};

$tests['compound select window recursive limit current source next241 rejects stale current result token'] = static function (TestRunner $t) use ($summary241): void {
    $cursor = $summary241()['cursor'];
    $cursor['currentResultTokenNext241'] = str_repeat('7', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary241($cursor));
};

$tests['compound select window recursive limit current source next241 rejects stale next result token'] = static function (TestRunner $t) use ($summary241): void {
    $cursor = $summary241()['cursor'];
    $cursor['nextResultTokenNext241'] = str_repeat('8', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary241($cursor));
};

$tests['compound select window recursive limit current source next241 rejects stale window limit receipt'] = static function (TestRunner $t) use ($summary241): void {
    $cursor = $summary241()['cursor'];
    $cursor['windowLimitReceiptTokenNext241'] = str_repeat('9', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary241($cursor));
};

$tests['compound select window recursive limit current source next241 rejects missing resume ack'] = static function (TestRunner $t) use ($summary241): void {
    $cursor = $summary241()['cursor'];
    $cursor['acknowledgedResumeAdmissionAcksNext241'] = array_slice($cursor['requiredResumeAdmissionAcksNext241'], 0, 2);
    $t->throws(InvalidArgumentException::class, static fn () => $summary241($cursor));
};

$tests['compound select window recursive limit current source next241 rejects unexpected resume ack'] = static function (TestRunner $t) use ($summary241): void {
    $cursor = $summary241()['cursor'];
    $cursor['acknowledgedResumeAdmissionAcksNext241'] = [...$cursor['requiredResumeAdmissionAcksNext241'], 'window-limit:' . str_repeat('d', 64)];
    $t->throws(InvalidArgumentException::class, static fn () => $summary241($cursor));
};

$tests['compound select window recursive limit current source next241 executor parity'] = static function (TestRunner $t) use ($sql241, $currentTables241, $summary241): void {
    $t->same(SQLiteSelectSql::execute($sql241, $currentTables241), $summary241()['currentRows']);
};

$tests['compound select window recursive limit current source next241 non overlap'] = static function (TestRunner $t) use ($summary241): void {
    $plan = $summary241();
    $t->contains('extends accepted next238', $plan['non_overlap']);
    $t->true(in_array('compound-recursive-window-resume-admission-next241', $plan['replanReasons'], true));
    $t->true(in_array('compound-final-row-token-next241', $plan['replanReasons'], true));
};

foreach (range(1, 64) as $case) {
    $tests['compound select window recursive limit current source next241 generated resume admission receipt ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareResumeAdmissionReceipt($sql, $tables, $nextTables);
        $cursor = $plan['cursor'];
        $cursor['acknowledgedCurrentAcksNext232'] = $cursor['requiredCurrentAcksNext232'];
        $cursor['acknowledgedPromotionAcksNext235'] = $cursor['requiredPromotionAcksNext235'];
        $cursor['acknowledgedSourceGenerationAcksNext238'] = $cursor['requiredSourceGenerationAcksNext238'];
        $cursor['acknowledgedResumeAdmissionAcksNext241'] = $cursor['requiredResumeAdmissionAcksNext241'];
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareResumeAdmissionReceipt($sql, $tables, $nextTables, $cursor);
        $receipt = $plan['resumeAdmissionReceiptNext241'];

        $t->same(['plugin_' . $case, 'seed_' . $case . ':2:3:4', 'home_' . $case], $receipt['nextLabels']);
        $t->same(['plugin_' . $case], $receipt['nextOnlyLabels']);
        $t->same(['rewrite_' . $case], $receipt['currentOnlyLabels']);
        $t->same(3, $receipt['requiredResumeAdmissionAckCount']);
        $t->same('held-until-current-next-results-and-window-limit-receipt-acks-match', $receipt['resumeState']);
        $t->same(3, $receipt['currentRowCount']);
        $t->same(3, $receipt['nextRowCount']);
        $t->same($receipt['resumeAdmissionToken'], $again['resumeAdmissionReceiptNext241']['resumeAdmissionToken']);
    };
}

return $tests;
