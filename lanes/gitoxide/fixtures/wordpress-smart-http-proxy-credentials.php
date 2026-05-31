<?php

declare(strict_types=1);

use PortLibs\Gitoxide\SmartHttpReceivePackTransport;

$packet = static fn (string $payload): string => sprintf('%04x', strlen($payload) + 4) . $payload;
$flush = '0000';

$requests = [];
$helperCalls = [];
$storedCredentials = [];
$erasedCredentials = [];
$advertisementBytes = $packet("58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a refs/heads/main\0report-status side-band-64k object-format=sha1\n")
    . $flush;

$transport = new SmartHttpReceivePackTransport(
    'https://git.example.test/wp-content.git',
    static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$requests, $packet, $flush, $advertisementBytes): array {
        $requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
            'timeout' => $timeout,
            'httpOptions' => $httpOptions,
        ];

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
        ];
    },
    [],
    5.0,
    ['User-Agent' => 'port-libs-wordpress-proxy/1'],
    [
        'proxy' => 'http://wp-proxy.example.test:8080',
        'proxyCredentialHelper' => static function (string $proxyUrl, string $requestHost) use (&$helperCalls): array {
            $helperCalls[] = [$proxyUrl, $requestHost];

            return ['username' => 'wp-proxy-user', 'password' => 'wp-proxy-pass'];
        },
        'proxyCredentialStore' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$storedCredentials): void {
            $storedCredentials[] = [$proxyUrl, $requestHost, $credentials];
        },
        'proxyCredentialErase' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$erasedCredentials): void {
            $erasedCredentials[] = [$proxyUrl, $requestHost, $credentials];
        },
    ],
);

$advertisement = $transport->readAdvertisement();

$usernameOnlyProxyRequests = [];
$usernameOnlyProxyHelperCalls = [];
$usernameOnlyProxyStores = [];
$usernameOnlyProxyTransport = new SmartHttpReceivePackTransport(
    'https://git.example.test/wp-content.git',
    static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$usernameOnlyProxyRequests, $packet, $flush, $advertisementBytes): array {
        $usernameOnlyProxyRequests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
            'timeout' => $timeout,
            'httpOptions' => $httpOptions,
        ];

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
        ];
    },
    [],
    5.0,
    ['User-Agent' => 'port-libs-wordpress-proxy/1'],
    [
        'proxy' => 'http://wp-proxy-user@wp-proxy.example.test:8080',
        'proxyCredentialHelper' => static function (string $proxyUrl, string $requestHost) use (&$usernameOnlyProxyHelperCalls): array {
            $usernameOnlyProxyHelperCalls[] = [$proxyUrl, $requestHost];

            return ['username' => 'wp-proxy-user', 'password' => 'helper-secret'];
        },
        'proxyCredentialStore' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$usernameOnlyProxyStores): void {
            $usernameOnlyProxyStores[] = [$proxyUrl, $requestHost, $credentials];
        },
    ],
);
$usernameOnlyProxyAdvertisement = $usernameOnlyProxyTransport->readAdvertisement();

$redirectRequests = [];
$redirectHelperCalls = [];
$redirectStoredCredentials = [];
$redirectErasedCredentials = [];
$redirectTransport = new SmartHttpReceivePackTransport(
    'https://git.example.test/wp-content.git',
    static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$redirectRequests, $packet, $flush, $advertisementBytes): array {
        $redirectRequests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
            'timeout' => $timeout,
            'httpOptions' => $httpOptions,
        ];

        if (count($redirectRequests) === 1) {
            return [
                'status' => 302,
                'headers' => ['Location' => 'https://git.example.test/redirected.git/info/refs?service=git-receive-pack'],
                'body' => '',
            ];
        }

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
        ];
    },
    [],
    5.0,
    ['User-Agent' => 'port-libs-wordpress-proxy/1'],
    [
        'proxy' => 'http://wp-proxy.example.test:8080',
        'proxyCredentialHelper' => static function (string $proxyUrl, string $requestHost) use (&$redirectHelperCalls): array {
            $redirectHelperCalls[] = [$proxyUrl, $requestHost];

            return ['username' => 'redirect-proxy-user', 'password' => 'redirect-proxy-pass'];
        },
        'proxyCredentialStore' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$redirectStoredCredentials): void {
            $redirectStoredCredentials[] = [$proxyUrl, $requestHost, $credentials];
        },
        'proxyCredentialErase' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$redirectErasedCredentials): void {
            $redirectErasedCredentials[] = [$proxyUrl, $requestHost, $credentials];
        },
    ],
);
$redirectAdvertisement = $redirectTransport->readAdvertisement();

$unexpectedStatusStores = [];
$unexpectedStatusErasures = [];
$unexpectedStatusRejected = false;
$unexpectedStatusTransport = new SmartHttpReceivePackTransport(
    'https://git.example.test/wp-content.git',
    static fn (): array => [
        'status' => 204,
        'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
        'body' => '',
    ],
    [],
    5.0,
    ['User-Agent' => 'port-libs-wordpress-proxy/1'],
    [
        'proxy' => 'http://wp-proxy.example.test:8080',
        'proxyCredentialHelper' => static fn (): array => ['username' => 'stale-proxy-user', 'password' => 'stale-proxy-pass'],
        'proxyCredentialStore' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$unexpectedStatusStores): void {
            $unexpectedStatusStores[] = [$proxyUrl, $requestHost, $credentials];
        },
        'proxyCredentialErase' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$unexpectedStatusErasures): void {
            $unexpectedStatusErasures[] = [$proxyUrl, $requestHost, $credentials];
        },
    ],
);
try {
    $unexpectedStatusTransport->readAdvertisement();
} catch (RuntimeException) {
    $unexpectedStatusRejected = true;
}

return [
    'advertisementBytes' => $advertisement,
    'helperCalls' => $helperCalls,
    'storedCredentials' => $storedCredentials,
    'erasedCredentials' => $erasedCredentials,
    'usernameOnlyProxyAdvertisementBytes' => $usernameOnlyProxyAdvertisement,
    'usernameOnlyProxyHelperCalls' => $usernameOnlyProxyHelperCalls,
    'usernameOnlyProxyStores' => $usernameOnlyProxyStores,
    'usernameOnlyProxyAuthorizationSent' => $usernameOnlyProxyRequests[0]['httpOptions']['proxyAuthorization'] ?? null,
    'usernameOnlyProxyUrl' => $usernameOnlyProxyRequests[0]['httpOptions']['proxyUrl'] ?? null,
    'usernameOnlyOriginProxyHeaderLeaked' => isset($usernameOnlyProxyRequests[0]['headers']['Proxy-Authorization']),
    'redirectAdvertisementBytes' => $redirectAdvertisement,
    'redirectRequestUrls' => array_map(static fn (array $request): string => $request['url'], $redirectRequests),
    'redirectHelperCalls' => $redirectHelperCalls,
    'redirectStoredCredentials' => $redirectStoredCredentials,
    'redirectErasedCredentials' => $redirectErasedCredentials,
    'redirectProxyAuthorizationReused' => ($redirectRequests[0]['httpOptions']['proxyAuthorization'] ?? null) !== null
        && ($redirectRequests[0]['httpOptions']['proxyAuthorization'] ?? null) === ($redirectRequests[1]['httpOptions']['proxyAuthorization'] ?? null),
    'unexpectedStatusRejected' => $unexpectedStatusRejected,
    'unexpectedStatusStores' => $unexpectedStatusStores,
    'unexpectedStatusErasures' => $unexpectedStatusErasures,
    'proxyAuthorizationSent' => $requests[0]['httpOptions']['proxyAuthorization'] ?? null,
    'originProxyHeaderLeaked' => isset($requests[0]['headers']['Proxy-Authorization']),
    'wordpressUse' => 'A WordPress deployment tool can retrieve proxy credentials from a callback, preserve proxy URL usernames as helper context, keep proxy credentials out of origin headers, reuse one helper credential action across a safe smart HTTP redirect, store it only after the final HTTP 200 request, and erase helper credentials after unexpected proxy/origin statuses.',
];
