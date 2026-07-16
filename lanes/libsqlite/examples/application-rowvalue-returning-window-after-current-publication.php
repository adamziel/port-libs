<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

$selfTest = in_array('--self-test', $argv ?? [], true);
$outerArgv = $argv ?? [];

$argv = [];
$next257 = require __DIR__ . '/application-rowvalue-returning-window-delete-retry-publication.php';
$argv = [];
$next258 = require __DIR__ . '/application-rowvalue-returning-window-publication-transition-admission.php';

ob_start();
$argv = [];
require __DIR__ . '/application-rowvalue-update-delete-returning-window-current-row-frame-admission.php';
$next259Output = trim((string) ob_get_clean());
$next259 = json_decode($next259Output, true, 512, JSON_THROW_ON_ERROR);

ob_start();
$argv = [];
require __DIR__ . '/application-rowvalue-returning-window-frame-boundary-admission.php';
$next260Output = trim((string) ob_get_clean());
$argv = $outerArgv;

$statuses = [
    $next257['status'],
    $next258['status'],
    $next259['status'],
    $next260Output,
];

assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next257',
    'rowvalue-update-delete-returning-window-current-source-next258',
    'rowvalue-update-delete-returning-window-current-source-next259',
    'application-rowvalue-returning-window-frame-boundary-admission self-test passed',
]);
assert($next257['tombstoneGate']['next_source_retry_tombstones_exposed'] === true);
assert($next257['publicationRowids'] === [1, 6, 2, 6, 5, 6, 4, 3, 2]);
assert($next258['heldRows'] === ['rewrite_rules', 'pending_theme', '_transient_feed']);
assert($next258['admittedRows'] === ['rewrite_rules', 'pending_theme', '_transient_feed', 'plugin_batch', 'home', 'rewrite_rules', 'pending_theme', '_transient_timeout_feed']);
assert($next259['ready_count'] === 8);
assert($next259['transition_count'] === 1);
assert($next259['ready_rowids'] === [7, 5, 3, 9, 10, 7, 5, 4]);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-after-current-publication',
    'candidateStatuses' => $statuses,
    'next257PublicationRowids' => $next257['publicationRowids'],
    'next257TombstoneGate' => $next257['tombstoneGate'],
    'next258HeldRows' => $next258['heldRows'],
    'next258AdmittedRows' => $next258['admittedRows'],
    'next259ReadyCount' => $next259['ready_count'],
    'next259TransitionCount' => $next259['transition_count'],
    'next259ReadyRowids' => $next259['ready_rowids'],
    'next260SelfTestOutput' => $next260Output,
    'applicationUse' => 'Copied wp_options imports can validate the prepared next257-260 current-source row-value UPDATE/DELETE RETURNING handoff as tombstone publication, transition-token admission, current-row frame acknowledgement, and mixed-boundary release coverage.',
];

if ($selfTest) {
    if ($statuses !== [
        'rowvalue-update-delete-returning-window-current-source-next257',
        'rowvalue-update-delete-returning-window-current-source-next258',
        'rowvalue-update-delete-returning-window-current-source-next259',
        'application-rowvalue-returning-window-frame-boundary-admission self-test passed',
    ]) {
        fwrite(STDERR, "application-rowvalue-returning-window-after-current-publication self-test failed\n");
        exit(1);
    }

    echo "application-rowvalue-returning-window-after-current-publication self-test passed\n";
    return;
}

return $summary;
