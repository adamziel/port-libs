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
            'status' => 308,
            'headers' => [
                'Location' => '/redirected.git/git-receive-pack',
                'Set-Cookie' => [
                    'stale_gate=closed; Path=/; Secure',
                    'stale_gate=; Max-Age=0; Path=/; Secure',
                    'legacy_gate=opened; Max-Age=60; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/; Secure',
                    'admin_gate=closed; Path=/wp-admin; Secure',
                    'foreign_gate=closed; Domain=example.org; Path=/; Secure',
                    'bad_path_gate=closed; Path=redirected.git; Secure',
                    "control_path_gate=closed; Path=/redirected.git\n; Secure",
                    'deploy_gate=opened; Path=/; Secure',
                    'deploy_gate=admin; Path=/wp-admin; Secure',
                    'deploy_gate=; Max-Age=0; Path=/wp-admin; Secure',
                    'replace_gate=stale; Path=/; Secure',
                    'replace_gate=fresh; Path=/; Secure',
                    'path_order=root; Path=/; Secure',
                    'path_order=redirect; Path=/redirected.git; Secure',
                    'path_order=receive; Path=/redirected.git/git-receive-pack; Secure',
                    'info_only=skip; Secure',
                ],
            ],
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
        [
            'User-Agent' => 'port-libs-wordpress-redirects/1',
            'Cookie' => 'wp_logged_in=editor; wp_nonce=abc',
        ],
        ['followRedirects' => true],
    ),
    'port-libs/wordpress',
);
$session = $client->handshake();
$session->createOrUpdate('refs/heads/main', $blob->oid());
$request = $session->buildRequest([$blob]);
$response = $client->send($request);
$redirectCookieHeader = $requests[2]['headers']['Cookie'] ?? '';
$pathOrderReceivePosition = strpos($redirectCookieHeader, 'path_order=receive');
$pathOrderRedirectPosition = strpos($redirectCookieHeader, 'path_order=redirect');
$pathOrderRootPosition = strpos($redirectCookieHeader, 'path_order=root');

$plainRedirectRequests = [];
$plainRedirectRequester = static function (string $method, string $url, array $headers, ?string $body) use (&$plainRedirectRequests, $packet, $flush, $advertisement, $responseBytes): array {
    $plainRedirectRequests[] = [
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

    if (count($plainRedirectRequests) === 2) {
        return [
            'status' => 307,
            'headers' => [
                'Location' => 'http://git.example.test/redirected.git/git-receive-pack',
                'Set-Cookie' => [
                    'plain_gate=opened; Path=/',
                    'secure_gate=closed; Path=/; Secure',
                ],
            ],
            'body' => '',
        ];
    }

    return [
        'status' => 200,
        'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
        'body' => $responseBytes,
    ];
};

$plainRedirectClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'http://git.example.test/wp-content.git',
        $plainRedirectRequester,
        ['version=1'],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-redirects/1'],
        ['followRedirects' => true],
    ),
    'port-libs/wordpress',
);
$plainRedirectSession = $plainRedirectClient->handshake();
$plainRedirectSession->createOrUpdate('refs/heads/main', $blob->oid());
$plainRedirectResponse = $plainRedirectClient->send($plainRedirectSession->buildRequest([$blob]));

$defaultPathRequests = [];
$defaultPathRequester = static function (string $method, string $url, array $headers, ?string $body) use (&$defaultPathRequests, $packet, $flush, $advertisement, $responseBytes): array {
    $defaultPathRequests[] = [
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

    if (count($defaultPathRequests) === 2) {
        return [
            'status' => 307,
            'headers' => [
                'Location' => 'https://git.example.test/redirected.git/git-receive-pack',
                'Set-Cookie' => [
                    'redirect_default_gate=closed; Secure',
                    'redirect_root_gate=opened; Path=/; Secure',
                ],
            ],
            'body' => '',
        ];
    }

    return [
        'status' => 200,
        'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
        'body' => $responseBytes,
    ];
};

$defaultPathClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test/wp-content.git',
        $defaultPathRequester,
        ['version=1'],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-redirects/1'],
        ['followRedirects' => true],
    ),
    'port-libs/wordpress',
);
$defaultPathSession = $defaultPathClient->handshake();
$defaultPathSession->createOrUpdate('refs/heads/main', $blob->oid());
$defaultPathResponse = $defaultPathClient->send($defaultPathSession->buildRequest([$blob]));

$chainedRedirectRequests = [];
$chainedRedirectRequester = static function (string $method, string $url, array $headers, ?string $body) use (&$chainedRedirectRequests, $packet, $flush, $advertisement, $responseBytes): array {
    $chainedRedirectRequests[] = [
        'method' => $method,
        'url' => $url,
        'headers' => $headers,
        'body' => $body,
    ];

    if ($method === 'GET') {
        return [
            'status' => 200,
            'headers' => [
                'Content-Type' => 'application/x-git-receive-pack-advertisement',
                'Set-Cookie' => 'repo_gate=repo; Path=/wp-content.git; Secure',
            ],
            'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisement,
        ];
    }

    if (count($chainedRedirectRequests) === 2) {
        return [
            'status' => 307,
            'headers' => [
                'Location' => 'https://git.example.test/gate-one.git/git-receive-pack',
                'Set-Cookie' => [
                    'gate_one=one; Path=/gate-one.git; Secure',
                    'stale_repo_gate=closed; Path=/wp-content.git; Secure',
                ],
            ],
            'body' => '',
        ];
    }

    if (count($chainedRedirectRequests) === 3) {
        return [
            'status' => 308,
            'headers' => [
                'Location' => 'https://git.example.test/gate-two.git/git-receive-pack',
                'Set-Cookie' => 'gate_two=two; Path=/gate-two.git; Secure',
            ],
            'body' => '',
        ];
    }

    return [
        'status' => 200,
        'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
        'body' => $responseBytes,
    ];
};

$chainedRedirectClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test/wp-content.git',
        $chainedRedirectRequester,
        ['version=1'],
        5.0,
        [
            'User-Agent' => 'port-libs-wordpress-redirects/1',
            'Cookie' => 'wp_logged_in=editor',
        ],
        ['followRedirects' => true],
    ),
    'port-libs/wordpress',
);
$chainedRedirectSession = $chainedRedirectClient->handshake();
$chainedRedirectSession->createOrUpdate('refs/heads/main', $blob->oid());
$chainedRedirectResponse = $chainedRedirectClient->send($chainedRedirectSession->buildRequest([$blob]));
$chainedFirstRetryCookieHeader = $chainedRedirectRequests[2]['headers']['Cookie'] ?? '';
$chainedFinalCookieHeader = $chainedRedirectRequests[3]['headers']['Cookie'] ?? '';

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

$permanentRequests = [];
$permanentRequester = static function (string $method, string $url, array $headers, ?string $body) use (&$permanentRequests, $packet, $flush, $advertisement): array {
    $permanentRequests[] = [
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
        'status' => 301,
        'headers' => ['Location' => 'https://git.example.test/redirected.git/git-receive-pack'],
        'body' => '',
    ];
};

$permanentRedirectRejected = false;
$permanentClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test/wp-content.git',
        $permanentRequester,
        ['version=1'],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-redirects/1'],
        ['followRedirects' => true],
    ),
    'port-libs/wordpress',
);
$permanentSession = $permanentClient->handshake();
$permanentSession->createOrUpdate('refs/heads/main', $blob->oid());
try {
    $permanentClient->send($permanentSession->buildRequest([$blob]));
} catch (RuntimeException) {
    $permanentRedirectRejected = true;
}

$seeOtherRequests = [];
$seeOtherRequester = static function (string $method, string $url, array $headers, ?string $body) use (&$seeOtherRequests, $packet, $flush, $advertisement): array {
    $seeOtherRequests[] = [
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
        'status' => 303,
        'headers' => ['Location' => 'https://git.example.test/redirected.git/git-receive-pack'],
        'body' => '',
    ];
};

$seeOtherRedirectRejected = false;
$seeOtherClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test/wp-content.git',
        $seeOtherRequester,
        ['version=1'],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-redirects/1'],
        ['followRedirects' => true],
    ),
    'port-libs/wordpress',
);
$seeOtherSession = $seeOtherClient->handshake();
$seeOtherSession->createOrUpdate('refs/heads/main', $blob->oid());
try {
    $seeOtherClient->send($seeOtherSession->buildRequest([$blob]));
} catch (RuntimeException) {
    $seeOtherRedirectRejected = true;
}

$wrongEndpointRequests = [];
$wrongEndpointRequester = static function (string $method, string $url, array $headers, ?string $body) use (&$wrongEndpointRequests, $packet, $flush, $advertisement): array {
    $wrongEndpointRequests[] = [
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
        'status' => 307,
        'headers' => ['Location' => 'https://git.example.test/redirected.git/git-upload-pack'],
        'body' => '',
    ];
};

$wrongEndpointRedirectRejected = false;
$wrongEndpointClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test/wp-content.git',
        $wrongEndpointRequester,
        ['version=1'],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-redirects/1'],
        ['followRedirects' => true],
    ),
    'port-libs/wordpress',
);
$wrongEndpointSession = $wrongEndpointClient->handshake();
$wrongEndpointSession->createOrUpdate('refs/heads/main', $blob->oid());
try {
    $wrongEndpointClient->send($wrongEndpointSession->buildRequest([$blob]));
} catch (RuntimeException) {
    $wrongEndpointRedirectRejected = true;
}

$credentialRedirectRequests = [];
$credentialRedirectRequester = static function (string $method, string $url, array $headers, ?string $body) use (&$credentialRedirectRequests, $packet, $flush, $advertisement): array {
    $credentialRedirectRequests[] = [
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
        'status' => 307,
        'headers' => ['Location' => 'https://redirect-user:redirect-pass@git.example.test/redirected.git/git-receive-pack'],
        'body' => '',
    ];
};

$credentialRedirectRejected = false;
$credentialClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test/wp-content.git',
        $credentialRedirectRequester,
        ['version=1'],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-redirects/1'],
        ['followRedirects' => true],
    ),
    'port-libs/wordpress',
);
$credentialSession = $credentialClient->handshake();
$credentialSession->createOrUpdate('refs/heads/main', $blob->oid());
try {
    $credentialClient->send($credentialSession->buildRequest([$blob]));
} catch (RuntimeException) {
    $credentialRedirectRejected = true;
}

$fragmentRedirectRequests = [];
$fragmentRedirectRequester = static function (string $method, string $url, array $headers, ?string $body) use (&$fragmentRedirectRequests, $packet, $flush, $advertisement): array {
    $fragmentRedirectRequests[] = [
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
        'status' => 307,
        'headers' => ['Location' => 'https://git.example.test/redirected.git/git-receive-pack#pack'],
        'body' => '',
    ];
};

$fragmentRedirectRejected = false;
$fragmentClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test/wp-content.git',
        $fragmentRedirectRequester,
        ['version=1'],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-redirects/1'],
        ['followRedirects' => true],
    ),
    'port-libs/wordpress',
);
$fragmentSession = $fragmentClient->handshake();
$fragmentSession->createOrUpdate('refs/heads/main', $blob->oid());
try {
    $fragmentClient->send($fragmentSession->buildRequest([$blob]));
} catch (RuntimeException) {
    $fragmentRedirectRejected = true;
}

$missingLocationRequests = [];
$missingLocationRequester = static function (string $method, string $url, array $headers, ?string $body) use (&$missingLocationRequests, $packet, $flush, $advertisement): array {
    $missingLocationRequests[] = [
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
        'status' => 307,
        'headers' => [],
        'body' => '',
    ];
};

$missingLocationRedirectRejected = false;
$missingLocationClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test/wp-content.git',
        $missingLocationRequester,
        ['version=1'],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-redirects/1'],
        ['followRedirects' => true],
    ),
    'port-libs/wordpress',
);
$missingLocationSession = $missingLocationClient->handshake();
$missingLocationSession->createOrUpdate('refs/heads/main', $blob->oid());
try {
    $missingLocationClient->send($missingLocationSession->buildRequest([$blob]));
} catch (RuntimeException) {
    $missingLocationRedirectRejected = true;
}

return [
    'requestMethods' => array_map(static fn (array $request): string => $request['method'], $requests),
    'requestUrls' => array_map(static fn (array $request): string => $request['url'], $requests),
    'redirectCookieHeader' => $redirectCookieHeader,
    'expiredRedirectCookieOmitted' => !str_contains($redirectCookieHeader, 'stale_gate='),
    'maxAgeRedirectCookieRetained' => str_contains($redirectCookieHeader, 'legacy_gate=opened'),
    'pathScopedRedirectCookieOmitted' => !str_contains($redirectCookieHeader, 'admin_gate='),
    'foreignDomainRedirectCookieOmitted' => !str_contains($redirectCookieHeader, 'foreign_gate='),
    'malformedPathRedirectCookiesOmitted' => !str_contains($redirectCookieHeader, 'bad_path_gate=')
        && !str_contains($redirectCookieHeader, 'control_path_gate='),
    'secureCookiePlainRedirectOmitted' => $plainRedirectResponse->isSuccessful()
        && str_contains($plainRedirectRequests[2]['headers']['Cookie'] ?? '', 'plain_gate=opened')
        && !str_contains($plainRedirectRequests[2]['headers']['Cookie'] ?? '', 'secure_gate='),
    'defaultPathRedirectCookieOmitted' => $defaultPathResponse->isSuccessful()
        && str_contains($defaultPathRequests[2]['headers']['Cookie'] ?? '', 'redirect_root_gate=opened')
        && !str_contains($defaultPathRequests[2]['headers']['Cookie'] ?? '', 'redirect_default_gate='),
    'postRedirectDefaultPathCookieOmitted' => !str_contains($redirectCookieHeader, 'info_only='),
    'redirectChainCookiesRecomputed' => $chainedRedirectResponse->isSuccessful()
        && ($chainedRedirectRequests[1]['headers']['Cookie'] ?? '') === 'wp_logged_in=editor; repo_gate=repo'
        && $chainedFirstRetryCookieHeader === 'wp_logged_in=editor; gate_one=one'
        && $chainedFinalCookieHeader === 'wp_logged_in=editor; gate_two=two',
    'redirectChainRequestMethods' => array_map(static fn (array $request): string => $request['method'], $chainedRedirectRequests),
    'redirectChainFirstRetryCookieHeader' => $chainedFirstRetryCookieHeader,
    'redirectChainFinalCookieHeader' => $chainedFinalCookieHeader,
    'sameNameScopedRedirectCookieRetained' => str_contains($redirectCookieHeader, 'deploy_gate=opened')
        && !str_contains($redirectCookieHeader, 'deploy_gate=admin'),
    'sameScopeRedirectCookieReplaced' => str_contains($redirectCookieHeader, 'replace_gate=fresh')
        && !str_contains($redirectCookieHeader, 'replace_gate=stale'),
    'callerCookieHeaderPreserved' => str_starts_with($redirectCookieHeader, 'wp_logged_in=editor; wp_nonce=abc; ')
        && str_contains($redirectCookieHeader, 'deploy_gate=opened'),
    'pathSpecificRedirectCookiesFirst' => $pathOrderReceivePosition !== false
        && $pathOrderRedirectPosition !== false
        && $pathOrderRootPosition !== false
        && $pathOrderReceivePosition < $pathOrderRedirectPosition
        && $pathOrderRedirectPosition < $pathOrderRootPosition,
    'postBodyPreserved' => ($requests[2]['body'] ?? null) === $request->requestBytes(),
    'rewritingPostRedirectRejected' => $rewritingRedirectRejected,
    'rewritingRequestMethods' => array_map(static fn (array $request): string => $request['method'], $rewritingRequests),
    'permanentPostRedirectRejected' => $permanentRedirectRejected,
    'permanentRequestMethods' => array_map(static fn (array $request): string => $request['method'], $permanentRequests),
    'seeOtherPostRedirectRejected' => $seeOtherRedirectRejected,
    'seeOtherRequestMethods' => array_map(static fn (array $request): string => $request['method'], $seeOtherRequests),
    'wrongEndpointPostRedirectRejected' => $wrongEndpointRedirectRejected,
    'wrongEndpointRequestMethods' => array_map(static fn (array $request): string => $request['method'], $wrongEndpointRequests),
    'credentialPostRedirectRejected' => $credentialRedirectRejected,
    'credentialRequestMethods' => array_map(static fn (array $request): string => $request['method'], $credentialRedirectRequests),
    'fragmentPostRedirectRejected' => $fragmentRedirectRejected,
    'fragmentRequestMethods' => array_map(static fn (array $request): string => $request['method'], $fragmentRedirectRequests),
    'missingLocationPostRedirectRejected' => $missingLocationRedirectRejected,
    'missingLocationRequestMethods' => array_map(static fn (array $request): string => $request['method'], $missingLocationRequests),
    'responseSuccessful' => $response->isSuccessful(),
    'wordpressUse' => 'A WordPress deployment tool can opt into following safe same-host receive-pack POST redirects while preserving the generated pack request body and caller-supplied WordPress cookies, recomputing managed cookies for each redirected retry, honoring redirect-issued cookie expiration, default Path on discovery and redirected POST responses, explicit Domain/Path/Secure scope including same-name scoped cookies, same-scope replacement, malformed Path quarantine, and Max-Age precedence, and rejecting rewriting 301/302/303, wrong-endpoint, credential-bearing, fragment-bearing, or missing-Location POST redirects before replaying a generated pack.',
];
