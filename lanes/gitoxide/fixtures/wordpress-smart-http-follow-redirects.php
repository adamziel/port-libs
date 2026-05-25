<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\ReceivePackClient;
use PortLibs\Gitoxide\SmartHttpReceivePackTransport;

$packet = static fn (string $payload): string => sprintf('%04x', strlen($payload) + 4) . $payload;
$flush = '0000';

$oldCommit = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
$blob = new GitObject('blob', 'WordPress redirected receive-pack payload');
$advertisement = $packet("{$oldCommit} refs/heads/main\0report-status side-band-64k object-format=sha1\n") . $flush;
$responseBytes = $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . $flush;

$requests = [];
$requester = static function (string $method, string $url, array $headers, ?string $body) use (&$requests, $packet, $flush, $advertisement, $responseBytes): array {
    $requests[] = [
        'method' => $method,
        'url' => $url,
        'headers' => $headers,
        'body' => $body,
    ];

    if ($method === 'GET') {
        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
        ];
    }

    if (count($requests) === 2) {
        return [
            'status' => 307,
            'headers' => ['Location' => 'https://git.example.test/redirected.git/git-receive-pack'],
            'body' => '',
        ];
    }

    return [
        'status' => 200,
        'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
        'body' => $responseBytes,
    ];
};

$client = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test/wp-content.git',
        $requester,
        ['version=1'],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-redirects/1'],
        ['followRedirects' => true],
    ),
    'port-libs/wordpress',
);
$session = $client->handshake();
$session->createOrUpdate('refs/heads/main', $blob->oid());
$request = $session->buildRequest([$blob]);
$response = $client->send($request);

$rewritingRequests = [];
$rewritingRequester = static function (string $method, string $url, array $headers, ?string $body) use (&$rewritingRequests, $packet, $flush, $advertisement): array {
    $rewritingRequests[] = [
        'method' => $method,
        'url' => $url,
        'headers' => $headers,
        'body' => $body,
    ];

    if ($method === 'GET') {
        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
        ];
    }

    return [
        'status' => 302,
        'headers' => ['Location' => 'https://git.example.test/redirected.git/git-receive-pack'],
        'body' => '',
    ];
};

$rewritingRedirectRejected = false;
$rewritingClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test/wp-content.git',
        $rewritingRequester,
        ['version=1'],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-redirects/1'],
        ['followRedirects' => true],
    ),
    'port-libs/wordpress',
);
$rewritingSession = $rewritingClient->handshake();
$rewritingSession->createOrUpdate('refs/heads/main', $blob->oid());
try {
    $rewritingClient->send($rewritingSession->buildRequest([$blob]));
} catch (RuntimeException) {
    $rewritingRedirectRejected = true;
}

return [
    'requestMethods' => array_map(static fn (array $request): string => $request['method'], $requests),
    'requestUrls' => array_map(static fn (array $request): string => $request['url'], $requests),
    'postBodyPreserved' => ($requests[2]['body'] ?? null) === $request->requestBytes(),
    'rewritingPostRedirectRejected' => $rewritingRedirectRejected,
    'rewritingRequestMethods' => array_map(static fn (array $request): string => $request['method'], $rewritingRequests),
    'responseSuccessful' => $response->isSuccessful(),
    'wordpressUse' => 'A WordPress deployment tool can opt into following a safe same-host receive-pack POST redirect while preserving the generated pack request body, and rejects rewriting POST redirects before replaying a generated pack.',
];
