<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\PatchFunctionArgument;
use PortLibs\Dolt\PatchFunctionCall;

$fixture = require dirname(__DIR__) . '/fixtures/wp-patch-review.php';
$call = new PatchFunctionCall();
$knownTables = ['wp_posts', 'wp_import_log', 'wp_options'];
$revisionGraph = [
    ['commit_hash' => 'review-base-hash', 'parents' => []],
    [
        'commit_hash' => 'review-head-hash',
        'parents' => ['review-base-hash'],
        'refs' => ['refs/heads/main', 'refs/tags/review-head'],
    ],
    [
        'commit_hash' => 'review-working-hash',
        'parents' => ['review-head-hash'],
        'refs' => ['refs/heads/review-working'],
    ],
];

try {
    $call->rows($fixture['tables'], ['review-base..review-working', 'wp_missing_queue'], [
        'knownTables' => $knownTables,
    ]);
    $missingTableError = null;
} catch (RuntimeException $exception) {
    $missingTableError = $exception->getMessage();
}

try {
    $call->rows($fixture['tables'], ['review-head', 'missing-review-branch', 'wp_posts'], [
        'knownTables' => $knownTables,
        'revisionGraph' => $revisionGraph,
        'headHash' => 'review-head-hash',
    ]);
    $missingBranchError = null;
} catch (RuntimeException $exception) {
    $missingBranchError = $exception->getMessage();
}

try {
    $call->rows($fixture['tables'], [
        'review-base',
        'review-working',
        PatchFunctionArgument::expression("LOWER('wp_posts')"),
    ], [
        'knownTables' => $knownTables,
    ]);
    $nonLiteralError = null;
} catch (InvalidArgumentException $exception) {
    $nonLiteralError = $exception->getMessage();
}

return [
    'postDataPatch' => $call->rows($fixture['tables'], ['review-base..review-working', 'WP_POSTS'], [
        'filter' => 'data',
        'knownTables' => $knownTables,
    ]),
    'allPatchFromThreeDot' => $call->rows($fixture['tables'], ['main...review-working'], [
        'knownTables' => $knownTables,
        'mergeBases' => ['main...review-working' => 'review-base'],
    ]),
    'resolvedBranchPatch' => $call->rows($fixture['tables'], ['review-head', 'review-working', 'wp_posts'], [
        'knownTables' => $knownTables,
        'revisionGraph' => $revisionGraph,
        'headHash' => 'review-head-hash',
    ]),
    'resolvedWorkingPatch' => $call->rows($fixture['tables'], ['review-head', 'WORKING', 'wp_posts'], [
        'knownTables' => $knownTables,
        'revisionGraph' => $revisionGraph,
        'headHash' => 'review-head-hash',
    ]),
    'unchangedKnownTable' => $call->rows($fixture['tables'], ['review-base', 'review-working', 'wp_options'], [
        'knownTables' => $knownTables,
    ]),
    'missingTableError' => $missingTableError,
    'missingBranchError' => $missingBranchError,
    'nonLiteralError' => $nonLiteralError,
];
