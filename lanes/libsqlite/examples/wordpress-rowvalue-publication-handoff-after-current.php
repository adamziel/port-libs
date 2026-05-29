<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

$selfTest = in_array('--self-test', $argv ?? [], true);
$outerArgv = $argv ?? [];

$argv = [];
$next265 = require __DIR__ . '/wordpress-rowvalue-receipt-ledger-handoff.php';
$argv = [];
$next266 = require __DIR__ . '/wordpress-rowvalue-source-epoch-watermark.php';
$argv = [];
$next267 = require __DIR__ . '/wordpress-rowvalue-handoff-batch-admission.php';
$argv = [];
$next268 = require __DIR__ . '/wordpress-rowvalue-handoff-manifest.php';
$argv = $outerArgv;

$statuses = [$next265['status'], $next266['status'], $next267['status'], $next268['status']];

assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next265',
    'rowvalue-update-delete-returning-window-current-source-next266',
    'rowvalue-update-delete-returning-window-current-source-next267',
    'rowvalue-update-delete-returning-window-current-source-next268',
]);
assert($next265['ledgerCount'] === 8);
assert($next266['currentSourceClosed'] === true);
assert($next267['batchCount'] === 3);
assert($next268['handoffComplete'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next265-268-after-current',
    'candidateStatuses' => $statuses,
    'next265LedgerCount' => $next265['ledgerCount'],
    'next266CurrentSourceClosed' => $next266['currentSourceClosed'],
    'next267BatchCount' => $next267['batchCount'],
    'next268HandoffComplete' => $next268['handoffComplete'],
    'wordpressUse' => 'Copied wp_options imports can validate the prepared next265-268 current-source row-value UPDATE/DELETE RETURNING handoff as final receipt ledgering, audit watermarking, deterministic batch partitioning, and manifest completion.',
];

if ($selfTest) {
    echo "wordpress-rowvalue-publication-handoff-after-current self-test passed\n";
    return;
}

return $summary;
