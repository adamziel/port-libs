<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\PatchFunctionCall;

$fixture = require dirname(__DIR__) . '/fixtures/wp-patch-review.php';
$call = new PatchFunctionCall();

$databaseTables = ['wp_posts', 'wp_import_log', 'wp_options'];
$baseOptions = [
    'databaseName' => 'wp_review/review-working',
    'knownTables' => $databaseTables,
    'databaseTables' => $databaseTables,
];

try {
    $call->rows($fixture['tables'], ['review-base', 'review-working', 'wp_posts'], $baseOptions + [
        'selectPrivileges' => [],
    ]);
    $noPrivilegeError = null;
} catch (RuntimeException $exception) {
    $noPrivilegeError = $exception->getMessage();
}

try {
    $call->rows($fixture['tables'], ['review-base', 'review-working'], $baseOptions + [
        'selectPrivileges' => ['wp_review.wp_posts'],
    ]);
    $allTablesDenied = null;
} catch (RuntimeException $exception) {
    $allTablesDenied = $exception->getMessage();
}

return [
    'limitedReviewerRows' => $call->rows($fixture['tables'], ['review-base', 'review-working', 'wp_posts'], $baseOptions + [
        'filter' => 'data',
        'selectPrivileges' => ['wp_review.wp_posts'],
    ]),
    'databaseWideRows' => $call->rows($fixture['tables'], ['review-base..review-working'], $baseOptions + [
        'selectPrivileges' => ['wp_review.*'],
    ]),
    'noPrivilegeError' => $noPrivilegeError,
    'allTablesDenied' => $allTablesDenied,
];
