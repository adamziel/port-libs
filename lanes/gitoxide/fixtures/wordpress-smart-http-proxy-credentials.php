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
    'unexpectedStatusRejected' => $unexpectedStatusRejected,
    'unexpectedStatusStores' => $unexpectedStatusStores,
    'unexpectedStatusErasures' => $unexpectedStatusErasures,
    'proxyAuthorizationSent' => $requests[0]['httpOptions']['proxyAuthorization'] ?? null,
    'originProxyHeaderLeaked' => isset($requests[0]['headers']['Proxy-Authorization']),
    'wordpressUse' => 'A WordPress deployment tool can retrieve proxy credentials from a callback, keep them out of origin headers, store them only after an HTTP 200 smart receive-pack request, and erase helper credentials after unexpected proxy/origin statuses.',
];
