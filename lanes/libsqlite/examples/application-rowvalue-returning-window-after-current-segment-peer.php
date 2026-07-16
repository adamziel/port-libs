<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

$selfTest = in_array('--self-test', $argv ?? [], true);
$outerArgv = $argv ?? [];

$argv = [];
$next261 = require __DIR__ . '/application-rowvalue-returning-window-source-segment-watermark.php';

ob_start();
$argv = [];
require __DIR__ . '/application-rowvalue-returning-window-peer-group-admission.php';
$next262Output = trim((string) ob_get_clean());

$argv = [];
$next263 = require __DIR__ . '/application-rowvalue-peer-checkpoint-admission.php';
$argv = [];
$next264 = require __DIR__ . '/application-rowvalue-final-receipt-admission.php';
$argv = $outerArgv;

$statuses = [
    $next261['status'],
    $next262Output,
    $next263['status'],
    $next264['status'],
];

assert($statuses[0] === 'rowvalue-update-delete-returning-window-current-source-next261');
assert($statuses[1] === 'application-rowvalue-returning-window-peer-group-admission self-test passed');
assert($statuses[2] === 'rowvalue-update-delete-returning-window-current-source-next263');
assert($statuses[3] === 'rowvalue-update-delete-returning-window-current-source-next264');
assert($next263['checkpointCount'] === 4);
assert($next264['finalReceiptCount'] === 8);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-after-current-segment-peer',
    'candidateStatuses' => $statuses,
    'next261PublishedTickets' => $next261['publishedTickets'],
    'next262SelfTestOutput' => $next262Output,
    'next263CheckpointCount' => $next263['checkpointCount'],
    'next264FinalReceiptCount' => $next264['finalReceiptCount'],
    'applicationUse' => 'Copied wp_options imports can validate the prepared next261-264 current-source row-value UPDATE/DELETE RETURNING handoff as source watermarks, peer-group admission, peer checkpoints, and final receipt completion.',
];

if ($selfTest) {
    if ($statuses !== [
        'rowvalue-update-delete-returning-window-current-source-next261',
        'application-rowvalue-returning-window-peer-group-admission self-test passed',
        'rowvalue-update-delete-returning-window-current-source-next263',
        'rowvalue-update-delete-returning-window-current-source-next264',
    ]) {
        fwrite(STDERR, "application-rowvalue-returning-window-after-current-segment-peer self-test failed\n");
        exit(1);
    }

    echo "application-rowvalue-returning-window-after-current-segment-peer self-test passed\n";
    return;
}

return $summary;
