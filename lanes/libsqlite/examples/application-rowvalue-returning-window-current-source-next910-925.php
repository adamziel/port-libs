<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield925', option_value || ':yield925', bytes + 609) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt925', option_value || ':attempt925', bytes + 509) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry925', option_value || ':retry925', bytes + 619) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 910; $next <= 925; $next++) {
    $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuation($next, ...$args);
    $plans[$next] = array_intersect_key($plan, array_flip([
        'status',
        'next' . $next . '_handoff',
        'next' . $next . '_source_audit',
        'next' . $next . '_preflight',
        'next' . $next . '_final',
        'next' . $next . '_ready',
    ]));
    unset($plan);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 910; $next <= 925; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[910]['next910_handoff']['next909_ready'] === true);
assert($plans[913]['next913_ready'] === true);
assert($plans[917]['next917_ready'] === true);
assert($plans[921]['next921_ready'] === true);
assert($plans[925]['next925_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next910-925',
    'candidateStatuses' => array_values($statuses),
    'next910Handoff' => $plans[910]['next910_handoff']['next910_handoff'],
    'next910AfterReadyRange' => $plans[910]['next910_handoff']['after_ready_range'],
    'next910ConsumesNext909Ready' => $plans[910]['next910_handoff']['next909_ready'],
    'next911SourceAudit' => $plans[911]['next911_source_audit']['next911_source_audit'],
    'next911PreservesCurrentSource' => $plans[911]['next911_source_audit']['retry_rows_preserve_current_source'],
    'next912Preflight' => $plans[912]['next912_preflight']['next912_preflight'],
    'next912KeepsThroughputHigh' => $plans[912]['next912_preflight']['keeps_libsqlite_throughput_high'],
    'next913Final' => $plans[913]['next913_final']['next913_final'],
    'next913Ready' => $plans[913]['next913_ready'],
    'next914Handoff' => $plans[914]['next914_handoff']['next914_handoff'],
    'next914AfterReadyRange' => $plans[914]['next914_handoff']['after_ready_range'],
    'next917Ready' => $plans[917]['next917_ready'],
    'next921Ready' => $plans[921]['next921_ready'],
    'next925Final' => $plans[925]['next925_final']['next925_final'],
    'next925Ready' => $plans[925]['next925_ready'],
    'applicationUse' => 'Copied wp_options imports validate the next910-925 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next909_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-current-source-next910-925 self-test passed\n";
    return;
}

return $summary;
