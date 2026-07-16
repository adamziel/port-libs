<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield941', option_value || ':yield941', bytes + 625) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt941', option_value || ':attempt941', bytes + 525) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry941', option_value || ':retry941', bytes + 635) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 926; $next <= 941; $next++) {
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
for ($next = 926; $next <= 941; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[926]['next926_handoff']['next925_ready'] === true);
assert($plans[929]['next929_ready'] === true);
assert($plans[933]['next933_ready'] === true);
assert($plans[937]['next937_ready'] === true);
assert($plans[941]['next941_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next926-941',
    'candidateStatuses' => array_values($statuses),
    'next926Handoff' => $plans[926]['next926_handoff']['next926_handoff'],
    'next926AfterReadyRange' => $plans[926]['next926_handoff']['after_ready_range'],
    'next926ConsumesNext925Ready' => $plans[926]['next926_handoff']['next925_ready'],
    'next927SourceAudit' => $plans[927]['next927_source_audit']['next927_source_audit'],
    'next927PreservesCurrentSource' => $plans[927]['next927_source_audit']['retry_rows_preserve_current_source'],
    'next928Preflight' => $plans[928]['next928_preflight']['next928_preflight'],
    'next928KeepsThroughputHigh' => $plans[928]['next928_preflight']['keeps_libsqlite_throughput_high'],
    'next929Final' => $plans[929]['next929_final']['next929_final'],
    'next929Ready' => $plans[929]['next929_ready'],
    'next930Handoff' => $plans[930]['next930_handoff']['next930_handoff'],
    'next930AfterReadyRange' => $plans[930]['next930_handoff']['after_ready_range'],
    'next933Ready' => $plans[933]['next933_ready'],
    'next937Ready' => $plans[937]['next937_ready'],
    'next941Final' => $plans[941]['next941_final']['next941_final'],
    'next941Ready' => $plans[941]['next941_ready'],
    'applicationUse' => 'Copied wp_options imports validate the next926-941 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next925_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-current-source-next926-941 self-test passed\n";
    return;
}

return $summary;
