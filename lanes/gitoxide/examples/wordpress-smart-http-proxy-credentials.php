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
    'usernameOnlyProxyHelperCalls' => $fixture['usernameOnlyProxyHelperCalls'],
    'usernameOnlyProxyCredentialUrl' => $fixture['usernameOnlyProxyUrl'],
    'usernameOnlyProxyAuthorizationSent' => $fixture['usernameOnlyProxyAuthorizationSent'],
    'usernameOnlyProxyCredentialsStored' => array_map(
        static fn (array $entry): array => [
            'proxyUrl' => $entry[0],
            'requestHost' => $entry[1],
            'username' => $entry[2]['username'],
        ],
        $fixture['usernameOnlyProxyStores']
    ),
    'usernameOnlyOriginProxyHeaderLeaked' => $fixture['usernameOnlyOriginProxyHeaderLeaked'],
    'cidrNoProxyBypassedProxy' => $fixture['cidrNoProxyBypassedProxy'],
    'cidrNoProxyHelperCalls' => $fixture['cidrNoProxyHelperCalls'],
    'cidrNoProxyPostCookieHeader' => $fixture['cidrNoProxyPostCookieHeader'],
    'urlCredentialProxyHelperCalls' => $fixture['urlCredentialProxyHelperCalls'],
    'urlCredentialProxyUrl' => $fixture['urlCredentialProxyUrl'],
    'urlCredentialProxyAuthorizationSent' => $fixture['urlCredentialProxyAuthorizationSent'],
    'urlCredentialProxyCredentialsStored' => array_map(
        static fn (array $entry): array => [
            'proxyUrl' => $entry[0],
            'requestHost' => $entry[1],
            'username' => $entry[2]['username'],
        ],
        $fixture['urlCredentialProxyStores']
    ),
    'urlCredentialOriginProxyHeaderLeaked' => $fixture['urlCredentialOriginProxyHeaderLeaked'],
    'proxyAuthorizationSent' => $fixture['proxyAuthorizationSent'],
    'originProxyHeaderLeaked' => $fixture['originProxyHeaderLeaked'],
    'redirectRequestUrls' => $fixture['redirectRequestUrls'],
    'redirectProxyHelperCalls' => $fixture['redirectHelperCalls'],
    'redirectProxyAuthorizationReused' => $fixture['redirectProxyAuthorizationReused'],
    'redirectStoredProxyCredentials' => array_map(
        static fn (array $entry): array => [
            'proxyUrl' => $entry[0],
            'requestHost' => $entry[1],
            'username' => $entry[2]['username'],
        ],
        $fixture['redirectStoredCredentials']
    ),
    'redirectErasedProxyCredentials' => $fixture['redirectErasedCredentials'],
    'unexpectedStatusRejected' => $fixture['unexpectedStatusRejected'],
    'unexpectedStatusStores' => $fixture['unexpectedStatusStores'],
    'unexpectedStatusErasures' => array_map(
        static fn (array $entry): array => [
            'proxyUrl' => $entry[0],
            'requestHost' => $entry[1],
            'username' => $entry[2]['username'],
        ],
        $fixture['unexpectedStatusErasures']
    ),
    'wordpressUse' => $fixture['wordpressUse'],
];
