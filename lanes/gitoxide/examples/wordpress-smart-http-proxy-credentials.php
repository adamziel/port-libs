<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

$fixture = require __DIR__ . '/../fixtures/wordpress-smart-http-proxy-credentials.php';

return [
    'proxyHelperCalls' => $fixture['helperCalls'],
    'storedProxyCredentials' => array_map(
        static fn (array $entry): array => [
            'proxyUrl' => $entry[0],
            'requestHost' => $entry[1],
            'username' => $entry[2]['username'],
        ],
        $fixture['storedCredentials']
    ),
    'proxyAuthorizationSent' => $fixture['proxyAuthorizationSent'],
    'originProxyHeaderLeaked' => $fixture['originProxyHeaderLeaked'],
    'wordpressUse' => $fixture['wordpressUse'],
];
