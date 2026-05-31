<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

$fixture = require __DIR__ . '/../fixtures/wordpress-credential-context.php';

return [
    'credentialUrl' => $fixture['credentialUrl'],
    'encodedHost' => $fixture['encodedContext']['host'],
    'encodedPath' => $fixture['encodedContext']['path'],
    'requestBytes' => $fixture['requestBytes'],
    'emptyQuitFalse' => $fixture['emptyQuitFalse'],
    'overflowExpiryIgnored' => $fixture['overflowExpiryIgnored'],
    'overflowQuitIgnored' => $fixture['overflowQuitIgnored'],
    'rootHttpPathCleared' => $fixture['rootHttpPathCleared'],
    'fileUrlClearedHost' => $fixture['fileUrlClearedHost'],
    'fileUrlPath' => $fixture['fileUrlPath'],
    'redactedBytes' => $fixture['redactedBytes'],
    'secretsInCleartextLog' => str_contains($fixture['redactedBytes'], 'wp-deploy-token')
        || str_contains($fixture['redactedBytes'], 'wp-refresh-token'),
    'wordpressUse' => $fixture['wordpressUse'],
];
