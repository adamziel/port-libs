<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

$selfTest = in_array('--self-test', $argv ?? [], true);
$outerArgv = $argv ?? [];

$argv = [];
$next249 = require __DIR__ . '/wordpress-rowvalue-chunked-yield-resume-window.php';
ob_start();
$argv = [];
$next250 = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next250.php';
$next250Output = ob_get_clean();
$next250Decoded = json_decode((string) $next250Output, true, 512, JSON_THROW_ON_ERROR);
$argv = [];
$next251 = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next251.php';
$argv = [];
$next252 = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next252.php';
$argv = $outerArgv;

$statuses = [
    $next249['status'],
    $next250Decoded['status'],
    $next251['status'],
    $next252['status'],
];

assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next249',
    'rowvalue-update-delete-returning-window-current-source-next250',
    'rowvalue-update-delete-returning-window-current-source-next251',
    'rowvalue-update-delete-returning-window-current-source-next252',
]);
assert($next249['yieldChunks'] === 2);
assert($next249['retryIds'] === [5, 6, 4, 3, 2]);
assert($next250 === 1);
assert($next250Decoded['excludeTiesCount'] === 9);
assert($next251['handoffState'] === 'current-source-drained-next-source-digest-ready-next251');
assert($next252['nextSourceFirstOrdinal'] === 4);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next249-252-after-current',
    'candidateStatuses' => $statuses,
    'next249RetryIds' => $next249['retryIds'],
    'next250ReplayedIds' => $next250Decoded['replayed'],
    'next251HandoffState' => $next251['handoffState'],
    'next252FirstRetryOrdinal' => $next252['nextSourceFirstOrdinal'],
    'wordpressUse' => 'Copied wp_options imports can validate the prepared next249-252 current-source row-value UPDATE/DELETE RETURNING handoff as chunked yield windows, EXCLUDE TIES frames, source digest handoff, and high-water window fence coverage.',
];

if ($selfTest) {
    echo "wordpress-rowvalue-returning-window-current-source-next249-252-after-current self-test passed\n";
}

return $summary;
