<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield_final', option_value || ':yield_final', bytes + 853) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt_final', option_value || ':attempt_final', bytes + 733) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry_final', option_value || ':retry_final', bytes + 857) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$rangeStarts = [1006, 1022, 1038, 1054, 1070, 1102, 1118, 1134];
$rangeEnds = [1021, 1037, 1053, 1069, 1085, 1117, 1133, 1149];
$statuses = [];
$handoffs = [];
$ready = [];
$finalSeal = null;
$finalReady = false;
foreach ($rangeStarts as $rangeIndex => $firstStep) {
    $lastStep = $rangeEnds[$rangeIndex];
    $rangePlans = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationRange(
        $firstStep,
        $lastStep,
        ['wp_options' => $rows],
        $yieldStatements,
        $attemptStatements,
        $retryStatements,
        [['blog_id', 'option_name']],
        'wp_options_rowvalue_window_ready_publication_final_' . $firstStep,
    );

    foreach ($rangePlans as $step => $plan) {
        $statuses[$step] = $plan['status'];
    }
    $handoffs[$firstStep] = [
        'hash' => $rangePlans[$firstStep]['next' . $firstStep . '_handoff']['next' . $firstStep . '_handoff'],
        'afterReadyRange' => $rangePlans[$firstStep]['next' . $firstStep . '_handoff']['after_ready_range'],
        'previousReady' => $rangePlans[$firstStep]['next' . $firstStep . '_handoff']['next' . ($firstStep - 1) . '_ready'],
    ];

    foreach ([$firstStep + 3, $lastStep] as $readyStep) {
        $ready[$readyStep] = $rangePlans[$readyStep]['next' . $readyStep . '_ready'];
    }
    if ($lastStep === 1149) {
        $finalSeal = $rangePlans[1149]['next1149_final']['next1149_final'];
        $finalReady = $rangePlans[1149]['next1149_ready'];
    }
    unset($rangePlans);
}

$expectedStatuses = [];
foreach ($rangeStarts as $rangeIndex => $firstStep) {
    for ($step = $firstStep; $step <= $rangeEnds[$rangeIndex]; $step++) {
        $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $step;
    }
}
assert(array_values($statuses) === $expectedStatuses);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-ready-publication-final',
    'candidateStatuses' => array_values($statuses),
    'candidateCount' => count($statuses),
    'firstStatus' => reset($statuses),
    'lastStatus' => end($statuses),
    'rangeStarts' => $rangeStarts,
    'handoffs' => $handoffs,
    'ready' => $ready,
    'finalSeal' => $finalSeal,
    'finalReady' => $finalReady,
    'wordpressUse' => 'Copied wp_options imports validate the final ready-publication row-value UPDATE/DELETE RETURNING window current-source continuation without keeping numbered caller files.',
    'dependencyClosure' => 'no new support component needed; reuses canonical row-value RETURNING window continuation and range execution helpers.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-ready-publication-final self-test passed\n";
    return;
}

return $summary;
