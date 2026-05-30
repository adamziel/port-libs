<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

$next245 = require __DIR__ . '/application-rowvalue-returning-window-current-source-next245.php';
$next246 = require __DIR__ . '/application-rowvalue-returning-filter-window-current-source-next246.php';
ob_start();
$next247 = require __DIR__ . '/application-rowvalue-returning-window-current-source-next247.php';
$next247Output = ob_get_clean();
$next247Decoded = json_decode((string) $next247Output, true, 512, JSON_THROW_ON_ERROR);
$next248 = require __DIR__ . '/application-rowvalue-returning-window-current-source-next248.php';

$statuses = [
    $next245['status'],
    $next246['status'],
    $next247Decoded['status'],
    $next248['status'],
];

assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next245',
    'rowvalue-update-delete-returning-window-current-source-next246',
    'rowvalue-update-delete-returning-window-current-source-next247',
    'rowvalue-update-delete-returning-window-current-source-next248',
]);
assert($next245['nextSourceExposed'] === true);
assert($next246['suppressedOnlyVisible'] === true);
assert($next247 === 1);
assert($next247Decoded['excludeGroupCount'] === 9);
assert($next248['publicationState'] === 'current-source-yield-complete-next-source-resumable-next248');

return [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next245-248-after-current',
    'candidateStatuses' => $statuses,
    'next245RetryIds' => $next245['retryIds'],
    'next246RetryUpdateIds' => $next246['retryUpdateIds'],
    'next247ReplayedIds' => $next247Decoded['replayed'],
    'next248RetryIds' => $next248['retryIds'],
    'applicationUse' => 'Copied wp_options imports can validate the prepared next245-248 current-source row-value UPDATE/DELETE RETURNING handoff as yield admission, filtered release receipts, peer-group exclusion, and resumable publication cursor coverage.',
];
