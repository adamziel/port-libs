<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\ReceivePackClient;
use PortLibs\Gitoxide\SmartHttpReceivePackTransport;
use PortLibs\Gitoxide\SmartHttpStatusException;

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

$defaultPortProxyRequests = [];
$defaultPortProxyHelperCalls = [];
$defaultPortProxyStores = [];
$defaultPortProxyBlob = new GitObject('blob', 'WordPress default-port proxy helper payload');
$defaultPortProxyResponseBytes = $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . $flush;
$defaultPortProxyClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test/wp-content.git',
        static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$defaultPortProxyRequests, $packet, $flush, $advertisementBytes, $defaultPortProxyResponseBytes): array {
            $defaultPortProxyRequests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
                'httpOptions' => $httpOptions,
            ];

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => [
                        'Content-Type' => 'application/x-git-receive-pack-advertisement',
                        'Set-Cookie' => 'wp_session=default-port; Path=/; Secure',
                    ],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                ];
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                'body' => $defaultPortProxyResponseBytes,
            ];
        },
        [],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-proxy/1'],
        [
            'proxy' => 'http://wp-default-proxy.example.test:80',
            'proxyCredentialHelper' => static function (string $proxyUrl, string $requestHost) use (&$defaultPortProxyHelperCalls): array {
                $defaultPortProxyHelperCalls[] = [$proxyUrl, $requestHost];

                return ['username' => 'default-port-proxy-user', 'password' => 'default-port-proxy-pass'];
            },
            'proxyCredentialStore' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$defaultPortProxyStores): void {
                $defaultPortProxyStores[] = [$proxyUrl, $requestHost, $credentials];
            },
        ],
    ),
    'port-libs/wordpress',
);
$defaultPortProxySession = $defaultPortProxyClient->handshake();
$defaultPortProxySession->createOrUpdate('refs/heads/main', $defaultPortProxyBlob->oid());
$defaultPortProxyResponse = $defaultPortProxyClient->send($defaultPortProxySession->buildRequest([$defaultPortProxyBlob]));

$nonDefaultPortProxyRequests = [];
$nonDefaultPortProxyHelperCalls = [];
$nonDefaultPortProxyStores = [];
$nonDefaultPortProxyBlob = new GitObject('blob', 'WordPress non-default-port proxy helper payload');
$nonDefaultPortProxyResponseBytes = $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . $flush;
$nonDefaultPortProxyClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test:8443/wp-content.git',
        static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$nonDefaultPortProxyRequests, $packet, $flush, $advertisementBytes, $nonDefaultPortProxyResponseBytes): array {
            $nonDefaultPortProxyRequests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
                'httpOptions' => $httpOptions,
            ];

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => [
                        'Content-Type' => 'application/x-git-receive-pack-advertisement',
                        'Set-Cookie' => 'wp_session=non-default-port; Path=/; Secure',
                    ],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                ];
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                'body' => $nonDefaultPortProxyResponseBytes,
            ];
        },
        [],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-proxy/1'],
        [
            'proxy' => 'http://wp-non-default-proxy.example.test:8080',
            'proxyCredentialHelper' => static function (string $proxyUrl, string $requestHost) use (&$nonDefaultPortProxyHelperCalls): array {
                $nonDefaultPortProxyHelperCalls[] = [$proxyUrl, $requestHost];

                return ['username' => 'non-default-port-proxy-user', 'password' => 'non-default-port-proxy-pass'];
            },
            'proxyCredentialStore' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$nonDefaultPortProxyStores): void {
                $nonDefaultPortProxyStores[] = [$proxyUrl, $requestHost, $credentials];
            },
        ],
    ),
    'port-libs/wordpress',
);
$nonDefaultPortProxySession = $nonDefaultPortProxyClient->handshake();
$nonDefaultPortProxySession->createOrUpdate('refs/heads/main', $nonDefaultPortProxyBlob->oid());
$nonDefaultPortProxyResponse = $nonDefaultPortProxyClient->send($nonDefaultPortProxySession->buildRequest([$nonDefaultPortProxyBlob]));

$pathNoSlashCookieRequests = [];
$pathNoSlashCookieBlob = new GitObject('blob', 'WordPress path-no-slash cookie proxy payload');
$pathNoSlashCookieResponseBytes = $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . $flush;
$pathNoSlashCookieClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test/wp-content.git',
        static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$pathNoSlashCookieRequests, $packet, $flush, $advertisementBytes, $pathNoSlashCookieResponseBytes): array {
            $pathNoSlashCookieRequests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
                'httpOptions' => $httpOptions,
            ];

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => [
                        'Content-Type' => 'application/x-git-receive-pack-advertisement',
                        'Set-Cookie' => [
                            'wp_root=wide; Path=wp-content.git; Secure',
                            'wp_empty=skip; Path=; Secure',
                        ],
                    ],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                ];
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                'body' => $pathNoSlashCookieResponseBytes,
            ];
        },
        [],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-proxy/1'],
        [
            'proxy' => 'http://wp-path-cookie-proxy.example.test:8080',
            'proxyCredentials' => ['username' => 'path-cookie-user', 'password' => 'path-cookie-pass'],
        ],
    ),
    'port-libs/wordpress',
);
$pathNoSlashCookieSession = $pathNoSlashCookieClient->handshake();
$pathNoSlashCookieSession->createOrUpdate('refs/heads/main', $pathNoSlashCookieBlob->oid());
$pathNoSlashCookieResponse = $pathNoSlashCookieClient->send($pathNoSlashCookieSession->buildRequest([$pathNoSlashCookieBlob]));

$cidrNoProxyRequests = [];
$cidrNoProxyHelperCalls = 0;
$cidrNoProxyBlob = new GitObject('blob', 'WordPress CIDR no-proxy payload');
$cidrNoProxyResponseBytes = $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . $flush;
$cidrNoProxyClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://192.168.12.34/wp-content.git',
        static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$cidrNoProxyRequests, $packet, $flush, $advertisementBytes, $cidrNoProxyResponseBytes): array {
            $cidrNoProxyRequests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'httpOptions' => $httpOptions,
            ];

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => [
                        'Content-Type' => 'application/x-git-receive-pack-advertisement',
                        'Set-Cookie' => 'wp_session=cidr; Path=/; Secure',
                    ],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                ];
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                'body' => $cidrNoProxyResponseBytes,
            ];
        },
        [],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-proxy/1'],
        [
            'proxy' => 'http://wp-proxy.example.test:8080',
            'noProxy' => '192.168.0.0/16',
            'proxyCredentialHelper' => static function () use (&$cidrNoProxyHelperCalls): array {
                $cidrNoProxyHelperCalls++;

                return ['username' => 'cidr-proxy-user', 'password' => 'cidr-proxy-pass'];
            },
        ],
    ),
    'port-libs/wordpress',
);
$cidrNoProxySession = $cidrNoProxyClient->handshake();
$cidrNoProxySession->createOrUpdate('refs/heads/main', $cidrNoProxyBlob->oid());
$cidrNoProxyResponse = $cidrNoProxyClient->send($cidrNoProxySession->buildRequest([$cidrNoProxyBlob]));

$ipv6LiteralNoProxyRequests = [];
$ipv6LiteralNoProxyHelperCalls = 0;
$ipv6LiteralNoProxyBlob = new GitObject('blob', 'WordPress IPv6 literal no-proxy payload');
$ipv6LiteralNoProxyResponseBytes = $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . $flush;
$ipv6LiteralNoProxyClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://[2001:db8::10]/wp-content.git',
        static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$ipv6LiteralNoProxyRequests, $packet, $flush, $advertisementBytes, $ipv6LiteralNoProxyResponseBytes): array {
            $ipv6LiteralNoProxyRequests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'httpOptions' => $httpOptions,
            ];

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => [
                        'Content-Type' => 'application/x-git-receive-pack-advertisement',
                        'Set-Cookie' => 'wp_session=ipv6-literal; Path=/; Secure',
                    ],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                ];
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                'body' => $ipv6LiteralNoProxyResponseBytes,
            ];
        },
        [],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-proxy/1'],
        [
            'proxy' => 'http://wp-proxy.example.test:8080',
            'noProxy' => '[2001:db8::10]',
            'proxyCredentialHelper' => static function () use (&$ipv6LiteralNoProxyHelperCalls): array {
                $ipv6LiteralNoProxyHelperCalls++;

                return ['username' => 'ipv6-literal-user', 'password' => 'ipv6-literal-pass'];
            },
        ],
    ),
    'port-libs/wordpress',
);
$ipv6LiteralNoProxySession = $ipv6LiteralNoProxyClient->handshake();
$ipv6LiteralNoProxySession->createOrUpdate('refs/heads/main', $ipv6LiteralNoProxyBlob->oid());
$ipv6LiteralNoProxyResponse = $ipv6LiteralNoProxyClient->send($ipv6LiteralNoProxySession->buildRequest([$ipv6LiteralNoProxyBlob]));

$ipv6CidrNoProxyRequests = [];
$ipv6CidrNoProxyHelperCalls = 0;
$ipv6CidrNoProxyBlob = new GitObject('blob', 'WordPress IPv6 CIDR no-proxy payload');
$ipv6CidrNoProxyResponseBytes = $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . $flush;
$ipv6CidrNoProxyClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://[2001:db8::10]/wp-content.git',
        static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$ipv6CidrNoProxyRequests, $packet, $flush, $advertisementBytes, $ipv6CidrNoProxyResponseBytes): array {
            $ipv6CidrNoProxyRequests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'httpOptions' => $httpOptions,
            ];

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => [
                        'Content-Type' => 'application/x-git-receive-pack-advertisement',
                        'Set-Cookie' => 'wp_session=ipv6-cidr; Path=/; Secure',
                    ],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                ];
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                'body' => $ipv6CidrNoProxyResponseBytes,
            ];
        },
        [],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-proxy/1'],
        [
            'proxy' => 'http://wp-proxy.example.test:8080',
            'noProxy' => '[2001:db8::]/32',
            'proxyCredentialHelper' => static function () use (&$ipv6CidrNoProxyHelperCalls): array {
                $ipv6CidrNoProxyHelperCalls++;

                return ['username' => 'ipv6-cidr-user', 'password' => 'ipv6-cidr-pass'];
            },
        ],
    ),
    'port-libs/wordpress',
);
$ipv6CidrNoProxySession = $ipv6CidrNoProxyClient->handshake();
$ipv6CidrNoProxySession->createOrUpdate('refs/heads/main', $ipv6CidrNoProxyBlob->oid());
$ipv6CidrNoProxyResponse = $ipv6CidrNoProxyClient->send($ipv6CidrNoProxySession->buildRequest([$ipv6CidrNoProxyBlob]));

$wildcardLiteralNoProxyRequests = [];
$wildcardLiteralNoProxyHelperCalls = 0;
$wildcardLiteralNoProxyTransport = new SmartHttpReceivePackTransport(
    'https://git.bypass.test/wp-content.git',
    static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$wildcardLiteralNoProxyRequests, $packet, $flush, $advertisementBytes): array {
        $wildcardLiteralNoProxyRequests[] = [
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
        'noProxy' => '*.bypass.test,*bypass.test',
        'proxyCredentialHelper' => static function () use (&$wildcardLiteralNoProxyHelperCalls): array {
            $wildcardLiteralNoProxyHelperCalls++;

            return ['username' => 'literal-wildcard-proxy-user', 'password' => 'literal-wildcard-proxy-pass'];
        },
    ],
);
$wildcardLiteralNoProxyAdvertisement = $wildcardLiteralNoProxyTransport->readAdvertisement();

$starNoProxyRequests = [];
$starNoProxyHelperCalls = 0;
$starNoProxyBlob = new GitObject('blob', 'WordPress star no-proxy payload');
$starNoProxyResponseBytes = $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . $flush;
$starNoProxyClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.bypass.test/wp-content.git',
        static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$starNoProxyRequests, $packet, $flush, $advertisementBytes, $starNoProxyResponseBytes): array {
            $starNoProxyRequests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
                'httpOptions' => $httpOptions,
            ];

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => [
                        'Content-Type' => 'application/x-git-receive-pack-advertisement',
                        'Set-Cookie' => 'wp_session=star; Path=/; Secure',
                    ],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                ];
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                'body' => $starNoProxyResponseBytes,
            ];
        },
        [],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-proxy/1'],
        [
            'proxy' => 'http://wp-proxy.example.test:8080',
            'noProxy' => '*',
            'proxyCredentialHelper' => static function () use (&$starNoProxyHelperCalls): array {
                $starNoProxyHelperCalls++;

                return ['username' => 'star-proxy-user', 'password' => 'star-proxy-pass'];
            },
        ],
    ),
    'port-libs/wordpress',
);
$starNoProxySession = $starNoProxyClient->handshake();
$starNoProxySession->createOrUpdate('refs/heads/main', $starNoProxyBlob->oid());
$starNoProxyResponse = $starNoProxyClient->send($starNoProxySession->buildRequest([$starNoProxyBlob]));

$trailingDotNoProxyRequests = [];
$trailingDotNoProxyHelperCalls = 0;
$trailingDotNoProxyBlob = new GitObject('blob', 'WordPress trailing-dot no-proxy payload');
$trailingDotNoProxyResponseBytes = $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . $flush;
$trailingDotNoProxyClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test./wp-content.git',
        static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$trailingDotNoProxyRequests, $packet, $flush, $advertisementBytes, $trailingDotNoProxyResponseBytes): array {
            $trailingDotNoProxyRequests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
                'httpOptions' => $httpOptions,
            ];

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => [
                        'Content-Type' => 'application/x-git-receive-pack-advertisement',
                        'Set-Cookie' => 'wp_session=trailing-dot; Path=/; Secure',
                    ],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                ];
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                'body' => $trailingDotNoProxyResponseBytes,
            ];
        },
        [],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-proxy/1'],
        [
            'proxy' => 'http://wp-proxy.example.test:8080',
            'noProxy' => 'example.test',
            'proxyCredentialHelper' => static function () use (&$trailingDotNoProxyHelperCalls): array {
                $trailingDotNoProxyHelperCalls++;

                return ['username' => 'trailing-dot-proxy-user', 'password' => 'trailing-dot-proxy-pass'];
            },
        ],
    ),
    'port-libs/wordpress',
);
$trailingDotNoProxySession = $trailingDotNoProxyClient->handshake();
$trailingDotNoProxySession->createOrUpdate('refs/heads/main', $trailingDotNoProxyBlob->oid());
$trailingDotNoProxyResponse = $trailingDotNoProxyClient->send($trailingDotNoProxySession->buildRequest([$trailingDotNoProxyBlob]));

$trailingDotDomainCookieRequests = [];
$trailingDotDomainCookieHelperCalls = 0;
$trailingDotDomainCookieBlob = new GitObject('blob', 'WordPress trailing-dot domain cookie no-proxy payload');
$trailingDotDomainCookieResponseBytes = $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . $flush;
$trailingDotDomainCookieClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test./wp-content.git',
        static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$trailingDotDomainCookieRequests, $packet, $flush, $advertisementBytes, $trailingDotDomainCookieResponseBytes): array {
            $trailingDotDomainCookieRequests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
                'httpOptions' => $httpOptions,
            ];

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => [
                        'Content-Type' => 'application/x-git-receive-pack-advertisement',
                        'Set-Cookie' => 'wp_domain=trail; Domain=example.test; Path=/; Secure',
                    ],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                ];
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                'body' => $trailingDotDomainCookieResponseBytes,
            ];
        },
        [],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-proxy/1'],
        [
            'proxy' => 'http://wp-proxy.example.test:8080',
            'noProxy' => 'example.test',
            'proxyCredentialHelper' => static function () use (&$trailingDotDomainCookieHelperCalls): array {
                $trailingDotDomainCookieHelperCalls++;

                return ['username' => 'trailing-dot-domain-user', 'password' => 'trailing-dot-domain-pass'];
            },
        ],
    ),
    'port-libs/wordpress',
);
$trailingDotDomainCookieSession = $trailingDotDomainCookieClient->handshake();
$trailingDotDomainCookieSession->createOrUpdate('refs/heads/main', $trailingDotDomainCookieBlob->oid());
$trailingDotDomainCookieResponse = $trailingDotDomainCookieClient->send($trailingDotDomainCookieSession->buildRequest([$trailingDotDomainCookieBlob]));

$trailingDotDomainAttributeCookieRequests = [];
$trailingDotDomainAttributeCookieHelperCalls = 0;
$trailingDotDomainAttributeCookieBlob = new GitObject('blob', 'WordPress trailing-dot Domain-attribute cookie no-proxy payload');
$trailingDotDomainAttributeCookieResponseBytes = $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . $flush;
$trailingDotDomainAttributeCookieClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test./wp-content.git',
        static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$trailingDotDomainAttributeCookieRequests, $packet, $flush, $advertisementBytes, $trailingDotDomainAttributeCookieResponseBytes): array {
            $trailingDotDomainAttributeCookieRequests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
                'httpOptions' => $httpOptions,
            ];

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => [
                        'Content-Type' => 'application/x-git-receive-pack-advertisement',
                        'Set-Cookie' => 'wp_domain_attr=trail; Domain=example.test.; Path=/; Secure',
                    ],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                ];
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                'body' => $trailingDotDomainAttributeCookieResponseBytes,
            ];
        },
        [],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-proxy/1'],
        [
            'proxy' => 'http://wp-proxy.example.test:8080',
            'noProxy' => 'example.test',
            'proxyCredentialHelper' => static function () use (&$trailingDotDomainAttributeCookieHelperCalls): array {
                $trailingDotDomainAttributeCookieHelperCalls++;

                return ['username' => 'trailing-dot-domain-attribute-user', 'password' => 'trailing-dot-domain-attribute-pass'];
            },
        ],
    ),
    'port-libs/wordpress',
);
$trailingDotDomainAttributeCookieSession = $trailingDotDomainAttributeCookieClient->handshake();
$trailingDotDomainAttributeCookieSession->createOrUpdate('refs/heads/main', $trailingDotDomainAttributeCookieBlob->oid());
$trailingDotDomainAttributeCookieResponse = $trailingDotDomainAttributeCookieClient->send($trailingDotDomainAttributeCookieSession->buildRequest([$trailingDotDomainAttributeCookieBlob]));

$portQualifiedNoProxyRequests = [];
$portQualifiedNoProxyHelperCalls = 0;
$portQualifiedNoProxyBlob = new GitObject('blob', 'WordPress port-qualified no-proxy payload');
$portQualifiedNoProxyResponseBytes = $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . $flush;
$portQualifiedNoProxyClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test/wp-content.git',
        static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$portQualifiedNoProxyRequests, $packet, $flush, $advertisementBytes, $portQualifiedNoProxyResponseBytes): array {
            $portQualifiedNoProxyRequests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
                'httpOptions' => $httpOptions,
            ];

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => [
                        'Content-Type' => 'application/x-git-receive-pack-advertisement',
                        'Set-Cookie' => 'wp_session=port-literal; Path=/; Secure',
                    ],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                ];
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                'body' => $portQualifiedNoProxyResponseBytes,
            ];
        },
        [],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-proxy/1'],
        [
            'proxy' => 'http://wp-proxy.example.test:8080',
            'noProxy' => 'git.example.test:443,.example.test:443',
            'proxyCredentialHelper' => static function () use (&$portQualifiedNoProxyHelperCalls): array {
                $portQualifiedNoProxyHelperCalls++;

                return ['username' => 'port-literal-proxy-user', 'password' => 'port-literal-proxy-pass'];
            },
        ],
    ),
    'port-libs/wordpress',
);
$portQualifiedNoProxySession = $portQualifiedNoProxyClient->handshake();
$portQualifiedNoProxySession->createOrUpdate('refs/heads/main', $portQualifiedNoProxyBlob->oid());
$portQualifiedNoProxyResponse = $portQualifiedNoProxyClient->send($portQualifiedNoProxySession->buildRequest([$portQualifiedNoProxyBlob]));

$httpsProxyFallbackRequests = [];
$httpsProxyFallbackHelperCalls = 0;
$httpsProxyFallbackBlob = new GitObject('blob', 'WordPress HTTPS proxy fallback payload');
$httpsProxyFallbackResponseBytes = $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . $flush;
$httpsProxyFallbackClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test/wp-content.git',
        static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$httpsProxyFallbackRequests, $packet, $flush, $advertisementBytes, $httpsProxyFallbackResponseBytes): array {
            $httpsProxyFallbackRequests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
                'httpOptions' => $httpOptions,
            ];

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => [
                        'Content-Type' => 'application/x-git-receive-pack-advertisement',
                        'Set-Cookie' => 'wp_session=https-fallback; Path=/; Secure',
                    ],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                ];
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                'body' => $httpsProxyFallbackResponseBytes,
            ];
        },
        [],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-proxy/1'],
        [
            'httpsProxy' => 'http://https-proxy.example.test:9443',
            'proxyCredentialHelper' => static function () use (&$httpsProxyFallbackHelperCalls): array {
                $httpsProxyFallbackHelperCalls++;

                return ['username' => 'https-fallback-user', 'password' => 'https-fallback-pass'];
            },
        ],
    ),
    'port-libs/wordpress',
);
$httpsProxyFallbackSession = $httpsProxyFallbackClient->handshake();
$httpsProxyFallbackSession->createOrUpdate('refs/heads/main', $httpsProxyFallbackBlob->oid());
$httpsProxyFallbackResponse = $httpsProxyFallbackClient->send($httpsProxyFallbackSession->buildRequest([$httpsProxyFallbackBlob]));

$httpsAllProxyRequests = [];
$httpsAllProxyHelperCalls = 0;
$httpsAllProxyBlob = new GitObject('blob', 'WordPress HTTPS all-proxy fallback payload');
$httpsAllProxyResponseBytes = $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . $flush;
$httpsAllProxyClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test/wp-content.git',
        static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$httpsAllProxyRequests, $packet, $flush, $advertisementBytes, $httpsAllProxyResponseBytes): array {
            $httpsAllProxyRequests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
                'httpOptions' => $httpOptions,
            ];

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => [
                        'Content-Type' => 'application/x-git-receive-pack-advertisement',
                        'Set-Cookie' => 'wp_session=https-all-proxy; Path=/; Secure',
                    ],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                ];
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                'body' => $httpsAllProxyResponseBytes,
            ];
        },
        [],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-proxy/1'],
        [
            'allProxy' => 'http://all-proxy.example.test:8081',
            'proxyCredentialHelper' => static function () use (&$httpsAllProxyHelperCalls): array {
                $httpsAllProxyHelperCalls++;

                return ['username' => 'https-all-proxy-user', 'password' => 'https-all-proxy-pass'];
            },
        ],
    ),
    'port-libs/wordpress',
);
$httpsAllProxySession = $httpsAllProxyClient->handshake();
$httpsAllProxySession->createOrUpdate('refs/heads/main', $httpsAllProxyBlob->oid());
$httpsAllProxyResponse = $httpsAllProxyClient->send($httpsAllProxySession->buildRequest([$httpsAllProxyBlob]));

$httpsProxyEmptyAllProxyRequests = [];
$httpsProxyEmptyAllProxyHelperCalls = 0;
$httpsProxyEmptyAllProxyBlob = new GitObject('blob', 'WordPress empty HTTPS proxy disables all-proxy payload');
$httpsProxyEmptyAllProxyResponseBytes = $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . $flush;
$httpsProxyEmptyAllProxyClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test/wp-content.git',
        static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$httpsProxyEmptyAllProxyRequests, $packet, $flush, $advertisementBytes, $httpsProxyEmptyAllProxyResponseBytes): array {
            $httpsProxyEmptyAllProxyRequests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
                'httpOptions' => $httpOptions,
            ];

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => [
                        'Content-Type' => 'application/x-git-receive-pack-advertisement',
                        'Set-Cookie' => 'wp_session=https-proxy-empty; Path=/; Secure',
                    ],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                ];
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                'body' => $httpsProxyEmptyAllProxyResponseBytes,
            ];
        },
        [],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-proxy/1'],
        [
            'httpsProxy' => '',
            'allProxy' => 'http://all-proxy.example.test:8081',
            'proxyCredentialHelper' => static function () use (&$httpsProxyEmptyAllProxyHelperCalls): array {
                $httpsProxyEmptyAllProxyHelperCalls++;

                return ['username' => 'empty-https-proxy-user', 'password' => 'empty-https-proxy-pass'];
            },
        ],
    ),
    'port-libs/wordpress',
);
$httpsProxyEmptyAllProxySession = $httpsProxyEmptyAllProxyClient->handshake();
$httpsProxyEmptyAllProxySession->createOrUpdate('refs/heads/main', $httpsProxyEmptyAllProxyBlob->oid());
$httpsProxyEmptyAllProxyResponse = $httpsProxyEmptyAllProxyClient->send($httpsProxyEmptyAllProxySession->buildRequest([$httpsProxyEmptyAllProxyBlob]));

$upgradeRedirectRequests = [];
$upgradeRedirectHelperCalls = 0;
$upgradeRedirectBlob = new GitObject('blob', 'WordPress HTTP to HTTPS upgrade proxy-cookie payload');
$upgradeRedirectResponseBytes = $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . $flush;
$upgradeRedirectClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'http://git.example.test/wp-content.git',
        static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$upgradeRedirectRequests, $packet, $flush, $advertisementBytes, $upgradeRedirectResponseBytes): array {
            $upgradeRedirectRequests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
                'httpOptions' => $httpOptions,
            ];

            if (count($upgradeRedirectRequests) === 1) {
                return [
                    'status' => 302,
                    'headers' => [
                        'Location' => 'https://git.example.test/wp-content.git/info/refs?service=git-receive-pack',
                        'Set-Cookie' => 'upgrade_gate=opened; Path=/',
                    ],
                    'body' => '',
                ];
            }

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => ['Content-Type' => 'application/x-git-receive-pack-advertisement'],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                ];
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                'body' => $upgradeRedirectResponseBytes,
            ];
        },
        [],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-proxy/1'],
        [
            'httpsProxy' => 'http://https-proxy.example.test:9443',
            'proxyCredentialHelper' => static function () use (&$upgradeRedirectHelperCalls): array {
                $upgradeRedirectHelperCalls++;

                return ['username' => 'upgrade-proxy-user', 'password' => 'upgrade-proxy-pass'];
            },
        ],
    ),
    'port-libs/wordpress',
);
$upgradeRedirectSession = $upgradeRedirectClient->handshake();
$upgradeRedirectSession->createOrUpdate('refs/heads/main', $upgradeRedirectBlob->oid());
$upgradeRedirectResponse = $upgradeRedirectClient->send($upgradeRedirectSession->buildRequest([$upgradeRedirectBlob]));

$protocolRelativeRedirectRequests = [];
$protocolRelativeRedirectHelperCalls = 0;
$protocolRelativeRedirectStores = [];
$protocolRelativeRedirectBlob = new GitObject('blob', 'WordPress protocol-relative proxy redirect payload');
$protocolRelativeRedirectResponseBytes = $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . $flush;
$protocolRelativeRedirectClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test/wp-content.git',
        static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$protocolRelativeRedirectRequests, $packet, $flush, $advertisementBytes, $protocolRelativeRedirectResponseBytes): array {
            $protocolRelativeRedirectRequests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
                'httpOptions' => $httpOptions,
            ];

            if (count($protocolRelativeRedirectRequests) === 1) {
                return [
                    'status' => 302,
                    'headers' => [
                        'Location' => '//git.example.test/redirected.git/info/refs?service=git-receive-pack',
                        'Set-Cookie' => 'protocol_gate=opened; Path=/; Secure',
                    ],
                    'body' => '',
                ];
            }

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => [
                        'Content-Type' => 'application/x-git-receive-pack-advertisement',
                        'Set-Cookie' => 'protocol_repo=ready; Path=/redirected.git; Secure',
                    ],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                ];
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                'body' => $protocolRelativeRedirectResponseBytes,
            ];
        },
        [],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-proxy/1'],
        [
            'proxy' => 'http://wp-protocol-proxy.example.test:8080',
            'proxyCredentialHelper' => static function () use (&$protocolRelativeRedirectHelperCalls): array {
                $protocolRelativeRedirectHelperCalls++;

                return ['username' => 'protocol-proxy-user', 'password' => 'protocol-proxy-pass'];
            },
            'proxyCredentialStore' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$protocolRelativeRedirectStores): void {
                $protocolRelativeRedirectStores[] = [$proxyUrl, $requestHost, $credentials];
            },
        ],
    ),
    'port-libs/wordpress',
);
$protocolRelativeRedirectSession = $protocolRelativeRedirectClient->handshake();
$protocolRelativeRedirectSession->createOrUpdate('refs/heads/main', $protocolRelativeRedirectBlob->oid());
$protocolRelativeRedirectResponse = $protocolRelativeRedirectClient->send($protocolRelativeRedirectSession->buildRequest([$protocolRelativeRedirectBlob]));

$urlCredentialProxyRequests = [];
$urlCredentialProxyHelperCalls = [];
$urlCredentialProxyStores = [];
$urlCredentialProxyTransport = new SmartHttpReceivePackTransport(
    'https://git.example.test/wp-content.git',
    static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$urlCredentialProxyRequests, $packet, $flush, $advertisementBytes): array {
        $urlCredentialProxyRequests[] = [
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
        'proxy' => 'http://stale-user:stale-pass@wp-proxy.example.test:8080',
        'proxyCredentialHelper' => static function (string $proxyUrl, string $requestHost) use (&$urlCredentialProxyHelperCalls): array {
            $urlCredentialProxyHelperCalls[] = [$proxyUrl, $requestHost];

            return ['username' => 'helper-proxy-user', 'password' => 'helper-proxy-pass'];
        },
        'proxyCredentialStore' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$urlCredentialProxyStores): void {
            $urlCredentialProxyStores[] = [$proxyUrl, $requestHost, $credentials];
        },
    ],
);
$urlCredentialProxyAdvertisement = $urlCredentialProxyTransport->readAdvertisement();

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

$notModifiedProxyRequests = [];
$notModifiedProxyHelperCalls = [];
$notModifiedProxyStores = [];
$notModifiedProxyErasures = [];
$notModifiedProxyBlob = new GitObject('blob', 'WordPress smart HTTP 304 proxy cookie payload');
$notModifiedProxyResponseBytes = $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . $flush;
$notModifiedProxyClient = new ReceivePackClient(
    new SmartHttpReceivePackTransport(
        'https://git.example.test/wp-content.git',
        static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$notModifiedProxyRequests, $packet, $flush, $advertisementBytes, $notModifiedProxyResponseBytes): array {
            $notModifiedProxyRequests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
                'httpOptions' => $httpOptions,
            ];

            if ($method === 'GET') {
                return [
                    'status' => 304,
                    'headers' => [
                        'Content-Type' => 'application/x-git-receive-pack-advertisement',
                        'Set-Cookie' => 'not_modified_gate=opened; Path=/; Secure',
                    ],
                    'body' => $packet("# service=git-receive-pack\n") . $flush . $advertisementBytes,
                ];
            }

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/x-git-receive-pack-result'],
                'body' => $notModifiedProxyResponseBytes,
            ];
        },
        [],
        5.0,
        ['User-Agent' => 'port-libs-wordpress-proxy/1'],
        [
            'proxy' => 'http://wp-proxy.example.test:8080',
            'proxyCredentialHelper' => static function (string $proxyUrl, string $requestHost) use (&$notModifiedProxyHelperCalls): array {
                $notModifiedProxyHelperCalls[] = [$proxyUrl, $requestHost];

                return ['username' => 'not-modified-proxy-user', 'password' => 'not-modified-proxy-pass'];
            },
            'proxyCredentialStore' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$notModifiedProxyStores): void {
                $notModifiedProxyStores[] = [$proxyUrl, $requestHost, $credentials];
            },
            'proxyCredentialErase' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$notModifiedProxyErasures): void {
                $notModifiedProxyErasures[] = [$proxyUrl, $requestHost, $credentials];
            },
        ],
    ),
    'port-libs/wordpress',
);
$notModifiedProxySession = $notModifiedProxyClient->handshake();
$notModifiedProxySession->createOrUpdate('refs/heads/main', $notModifiedProxyBlob->oid());
$notModifiedProxyResponse = $notModifiedProxyClient->send($notModifiedProxySession->buildRequest([$notModifiedProxyBlob]));

$notModifiedNoRedirectRequests = [];
$notModifiedNoRedirectHelperCalls = [];
$notModifiedNoRedirectStores = [];
$notModifiedNoRedirectErasures = [];
$notModifiedNoRedirectStatusCode = null;
$notModifiedNoRedirectKind = null;
$notModifiedNoRedirectTransport = new SmartHttpReceivePackTransport(
    'https://git.example.test/wp-content.git',
    static function (string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions) use (&$notModifiedNoRedirectRequests): array {
        $notModifiedNoRedirectRequests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
            'timeout' => $timeout,
            'httpOptions' => $httpOptions,
        ];

        return [
            'status' => 304,
            'headers' => [
                'Content-Type' => 'application/x-git-receive-pack-advertisement',
                'Set-Cookie' => 'no_redirect_gate=opened; Path=/; Secure',
            ],
            'body' => '',
        ];
    },
    [],
    5.0,
    ['User-Agent' => 'port-libs-wordpress-proxy/1'],
    [
        'proxy' => 'http://wp-proxy.example.test:8080',
        'followRedirects' => false,
        'proxyCredentialHelper' => static function (string $proxyUrl, string $requestHost) use (&$notModifiedNoRedirectHelperCalls): array {
            $notModifiedNoRedirectHelperCalls[] = [$proxyUrl, $requestHost];

            return ['username' => 'not-modified-no-redirect-user', 'password' => 'not-modified-no-redirect-pass'];
        },
        'proxyCredentialStore' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$notModifiedNoRedirectStores): void {
            $notModifiedNoRedirectStores[] = [$proxyUrl, $requestHost, $credentials];
        },
        'proxyCredentialErase' => static function (string $proxyUrl, string $requestHost, array $credentials) use (&$notModifiedNoRedirectErasures): void {
            $notModifiedNoRedirectErasures[] = [$proxyUrl, $requestHost, $credentials];
        },
    ],
);
try {
    $notModifiedNoRedirectTransport->readAdvertisement();
} catch (SmartHttpStatusException $exception) {
    $notModifiedNoRedirectStatusCode = $exception->statusCode();
    $notModifiedNoRedirectKind = $exception->kind();
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
    'defaultPortProxyResponseSuccessful' => $defaultPortProxyResponse->isSuccessful(),
    'defaultPortProxyHelperCalls' => $defaultPortProxyHelperCalls,
    'defaultPortProxyStores' => $defaultPortProxyStores,
    'defaultPortProxyRequestProxyUrl' => $defaultPortProxyRequests[0]['httpOptions']['proxyUrl'] ?? null,
    'defaultPortProxyRequestProxyStream' => $defaultPortProxyRequests[0]['httpOptions']['proxy'] ?? null,
    'defaultPortProxyAuthorizationSent' => $defaultPortProxyRequests[0]['httpOptions']['proxyAuthorization'] ?? null,
    'defaultPortProxyOriginProxyHeaderLeaked' => isset($defaultPortProxyRequests[0]['headers']['Proxy-Authorization'])
        || isset($defaultPortProxyRequests[1]['headers']['Proxy-Authorization']),
    'defaultPortProxyPostCookieHeader' => $defaultPortProxyRequests[1]['headers']['Cookie'] ?? null,
    'nonDefaultPortProxyResponseSuccessful' => $nonDefaultPortProxyResponse->isSuccessful(),
    'nonDefaultPortProxyHelperCalls' => $nonDefaultPortProxyHelperCalls,
    'nonDefaultPortProxyStores' => $nonDefaultPortProxyStores,
    'nonDefaultPortProxyRequestUrls' => array_map(static fn (array $request): string => $request['url'], $nonDefaultPortProxyRequests),
    'nonDefaultPortProxyRequestProxyUrl' => $nonDefaultPortProxyRequests[0]['httpOptions']['proxyUrl'] ?? null,
    'nonDefaultPortProxyRequestProxyStream' => $nonDefaultPortProxyRequests[0]['httpOptions']['proxy'] ?? null,
    'nonDefaultPortProxyAuthorizationSent' => $nonDefaultPortProxyRequests[0]['httpOptions']['proxyAuthorization'] ?? null,
    'nonDefaultPortProxyOriginProxyHeaderLeaked' => isset($nonDefaultPortProxyRequests[0]['headers']['Proxy-Authorization'])
        || isset($nonDefaultPortProxyRequests[1]['headers']['Proxy-Authorization']),
    'nonDefaultPortProxyPostCookieHeader' => $nonDefaultPortProxyRequests[1]['headers']['Cookie'] ?? null,
    'pathNoSlashCookieResponseSuccessful' => $pathNoSlashCookieResponse->isSuccessful(),
    'pathNoSlashCookieUsedProxy' => ($pathNoSlashCookieRequests[0]['httpOptions']['proxy'] ?? null) === 'tcp://wp-path-cookie-proxy.example.test:8080'
        && ($pathNoSlashCookieRequests[1]['httpOptions']['proxy'] ?? null) === 'tcp://wp-path-cookie-proxy.example.test:8080',
    'pathNoSlashCookieAuthorizationSent' => $pathNoSlashCookieRequests[0]['httpOptions']['proxyAuthorization'] ?? null,
    'pathNoSlashCookieOriginProxyHeaderLeaked' => isset($pathNoSlashCookieRequests[0]['headers']['Proxy-Authorization'])
        || isset($pathNoSlashCookieRequests[1]['headers']['Proxy-Authorization']),
    'pathNoSlashCookiePostCookieHeader' => $pathNoSlashCookieRequests[1]['headers']['Cookie'] ?? null,
    'pathNoSlashCookieEmptyPathOmitted' => !str_contains($pathNoSlashCookieRequests[1]['headers']['Cookie'] ?? '', 'wp_empty='),
    'cidrNoProxyBypassedProxy' => $cidrNoProxyResponse->isSuccessful()
        && ($cidrNoProxyRequests[0]['httpOptions'] ?? null) === []
        && ($cidrNoProxyRequests[1]['httpOptions'] ?? null) === [],
    'cidrNoProxyHelperCalls' => $cidrNoProxyHelperCalls,
    'cidrNoProxyPostCookieHeader' => $cidrNoProxyRequests[1]['headers']['Cookie'] ?? null,
    'ipv6LiteralNoProxyBypassedProxy' => $ipv6LiteralNoProxyResponse->isSuccessful()
        && ($ipv6LiteralNoProxyRequests[0]['httpOptions'] ?? null) === []
        && ($ipv6LiteralNoProxyRequests[1]['httpOptions'] ?? null) === [],
    'ipv6LiteralNoProxyHelperCalls' => $ipv6LiteralNoProxyHelperCalls,
    'ipv6LiteralNoProxyPostCookieHeader' => $ipv6LiteralNoProxyRequests[1]['headers']['Cookie'] ?? null,
    'ipv6CidrNoProxyBypassedProxy' => $ipv6CidrNoProxyResponse->isSuccessful()
        && ($ipv6CidrNoProxyRequests[0]['httpOptions'] ?? null) === []
        && ($ipv6CidrNoProxyRequests[1]['httpOptions'] ?? null) === [],
    'ipv6CidrNoProxyHelperCalls' => $ipv6CidrNoProxyHelperCalls,
    'ipv6CidrNoProxyPostCookieHeader' => $ipv6CidrNoProxyRequests[1]['headers']['Cookie'] ?? null,
    'wildcardLiteralNoProxyAdvertisementBytes' => $wildcardLiteralNoProxyAdvertisement,
    'wildcardLiteralNoProxyHelperCalls' => $wildcardLiteralNoProxyHelperCalls,
    'wildcardLiteralNoProxyUsedProxy' => isset($wildcardLiteralNoProxyRequests[0]['httpOptions']['proxy']),
    'wildcardLiteralNoProxyAuthorizationSent' => $wildcardLiteralNoProxyRequests[0]['httpOptions']['proxyAuthorization'] ?? null,
    'starNoProxyBypassedProxy' => $starNoProxyResponse->isSuccessful()
        && ($starNoProxyRequests[0]['httpOptions'] ?? null) === []
        && ($starNoProxyRequests[1]['httpOptions'] ?? null) === [],
    'starNoProxyHelperCalls' => $starNoProxyHelperCalls,
    'starNoProxyPostCookieHeader' => $starNoProxyRequests[1]['headers']['Cookie'] ?? null,
    'trailingDotNoProxyBypassedProxy' => $trailingDotNoProxyResponse->isSuccessful()
        && ($trailingDotNoProxyRequests[0]['httpOptions'] ?? null) === []
        && ($trailingDotNoProxyRequests[1]['httpOptions'] ?? null) === [],
    'trailingDotNoProxyHelperCalls' => $trailingDotNoProxyHelperCalls,
    'trailingDotNoProxyPostCookieHeader' => $trailingDotNoProxyRequests[1]['headers']['Cookie'] ?? null,
    'trailingDotDomainCookieBypassedProxy' => $trailingDotDomainCookieResponse->isSuccessful()
        && ($trailingDotDomainCookieRequests[0]['httpOptions'] ?? null) === []
        && ($trailingDotDomainCookieRequests[1]['httpOptions'] ?? null) === [],
    'trailingDotDomainCookieHelperCalls' => $trailingDotDomainCookieHelperCalls,
    'trailingDotDomainCookiePostCookieHeader' => $trailingDotDomainCookieRequests[1]['headers']['Cookie'] ?? null,
    'trailingDotDomainAttributeCookieBypassedProxy' => $trailingDotDomainAttributeCookieResponse->isSuccessful()
        && ($trailingDotDomainAttributeCookieRequests[0]['httpOptions'] ?? null) === []
        && ($trailingDotDomainAttributeCookieRequests[1]['httpOptions'] ?? null) === [],
    'trailingDotDomainAttributeCookieHelperCalls' => $trailingDotDomainAttributeCookieHelperCalls,
    'trailingDotDomainAttributeCookiePostCookieHeader' => $trailingDotDomainAttributeCookieRequests[1]['headers']['Cookie'] ?? null,
    'portQualifiedNoProxyUsedProxy' => $portQualifiedNoProxyResponse->isSuccessful()
        && isset($portQualifiedNoProxyRequests[0]['httpOptions']['proxy'])
        && isset($portQualifiedNoProxyRequests[1]['httpOptions']['proxy']),
    'portQualifiedNoProxyHelperCalls' => $portQualifiedNoProxyHelperCalls,
    'portQualifiedNoProxyAuthorizationSent' => $portQualifiedNoProxyRequests[0]['httpOptions']['proxyAuthorization'] ?? null,
    'portQualifiedNoProxyPostCookieHeader' => $portQualifiedNoProxyRequests[1]['headers']['Cookie'] ?? null,
    'httpsProxyFallbackUsedProxy' => $httpsProxyFallbackResponse->isSuccessful()
        && ($httpsProxyFallbackRequests[0]['httpOptions']['proxy'] ?? null) === 'tcp://https-proxy.example.test:9443'
        && ($httpsProxyFallbackRequests[1]['httpOptions']['proxy'] ?? null) === 'tcp://https-proxy.example.test:9443',
    'httpsProxyFallbackHelperCalls' => $httpsProxyFallbackHelperCalls,
    'httpsProxyFallbackAuthorizationSent' => $httpsProxyFallbackRequests[0]['httpOptions']['proxyAuthorization'] ?? null,
    'httpsProxyFallbackPostCookieHeader' => $httpsProxyFallbackRequests[1]['headers']['Cookie'] ?? null,
    'httpsAllProxyUsedProxy' => $httpsAllProxyResponse->isSuccessful()
        && ($httpsAllProxyRequests[0]['httpOptions']['proxy'] ?? null) === 'tcp://all-proxy.example.test:8081'
        && ($httpsAllProxyRequests[1]['httpOptions']['proxy'] ?? null) === 'tcp://all-proxy.example.test:8081',
    'httpsAllProxyHelperCalls' => $httpsAllProxyHelperCalls,
    'httpsAllProxyAuthorizationSent' => $httpsAllProxyRequests[0]['httpOptions']['proxyAuthorization'] ?? null,
    'httpsAllProxyPostCookieHeader' => $httpsAllProxyRequests[1]['headers']['Cookie'] ?? null,
    'httpsProxyEmptyAllProxyBypassedProxy' => $httpsProxyEmptyAllProxyResponse->isSuccessful()
        && ($httpsProxyEmptyAllProxyRequests[0]['httpOptions'] ?? null) === []
        && ($httpsProxyEmptyAllProxyRequests[1]['httpOptions'] ?? null) === [],
    'httpsProxyEmptyAllProxyHelperCalls' => $httpsProxyEmptyAllProxyHelperCalls,
    'httpsProxyEmptyAllProxyPostCookieHeader' => $httpsProxyEmptyAllProxyRequests[1]['headers']['Cookie'] ?? null,
    'upgradeRedirectUsedHttpsProxy' => isset($upgradeRedirectRequests[1]['httpOptions']['proxy'])
        || isset($upgradeRedirectRequests[2]['httpOptions']['proxy']),
    'upgradeRedirectHelperCalls' => $upgradeRedirectHelperCalls,
    'upgradeRedirectRequestUrls' => array_map(static fn (array $request): string => $request['url'], $upgradeRedirectRequests),
    'upgradeRedirectPostCookieHeader' => $upgradeRedirectRequests[2]['headers']['Cookie'] ?? null,
    'upgradeRedirectResponseSuccessful' => $upgradeRedirectResponse->isSuccessful(),
    'protocolRelativeRedirectResponseSuccessful' => $protocolRelativeRedirectResponse->isSuccessful(),
    'protocolRelativeRedirectHelperCalls' => $protocolRelativeRedirectHelperCalls,
    'protocolRelativeRedirectStores' => $protocolRelativeRedirectStores,
    'protocolRelativeRedirectRequestUrls' => array_map(static fn (array $request): string => $request['url'], $protocolRelativeRedirectRequests),
    'protocolRelativeRedirectMethods' => array_map(static fn (array $request): string => $request['method'], $protocolRelativeRedirectRequests),
    'protocolRelativeRedirectUsedProxy' => isset($protocolRelativeRedirectRequests[0]['httpOptions']['proxy'])
        && isset($protocolRelativeRedirectRequests[1]['httpOptions']['proxy'])
        && isset($protocolRelativeRedirectRequests[2]['httpOptions']['proxy']),
    'protocolRelativeRedirectPostCookieHeader' => $protocolRelativeRedirectRequests[2]['headers']['Cookie'] ?? null,
    'protocolRelativeRedirectOriginProxyHeaderLeaked' => isset($protocolRelativeRedirectRequests[0]['headers']['Proxy-Authorization'])
        || isset($protocolRelativeRedirectRequests[1]['headers']['Proxy-Authorization'])
        || isset($protocolRelativeRedirectRequests[2]['headers']['Proxy-Authorization']),
    'urlCredentialProxyAdvertisementBytes' => $urlCredentialProxyAdvertisement,
    'urlCredentialProxyHelperCalls' => $urlCredentialProxyHelperCalls,
    'urlCredentialProxyStores' => $urlCredentialProxyStores,
    'urlCredentialProxyAuthorizationSent' => $urlCredentialProxyRequests[0]['httpOptions']['proxyAuthorization'] ?? null,
    'urlCredentialProxyUrl' => $urlCredentialProxyRequests[0]['httpOptions']['proxyUrl'] ?? null,
    'urlCredentialOriginProxyHeaderLeaked' => isset($urlCredentialProxyRequests[0]['headers']['Proxy-Authorization']),
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
    'notModifiedProxyResponseSuccessful' => $notModifiedProxyResponse->isSuccessful(),
    'notModifiedProxyHelperCalls' => $notModifiedProxyHelperCalls,
    'notModifiedProxyStores' => $notModifiedProxyStores,
    'notModifiedProxyErasures' => $notModifiedProxyErasures,
    'notModifiedProxyPostCookieHeader' => $notModifiedProxyRequests[1]['headers']['Cookie'] ?? null,
    'notModifiedNoRedirectRejected' => $notModifiedNoRedirectStatusCode === 304,
    'notModifiedNoRedirectStatusCode' => $notModifiedNoRedirectStatusCode,
    'notModifiedNoRedirectKind' => $notModifiedNoRedirectKind,
    'notModifiedNoRedirectHelperCalls' => $notModifiedNoRedirectHelperCalls,
    'notModifiedNoRedirectStores' => $notModifiedNoRedirectStores,
    'notModifiedNoRedirectErasures' => $notModifiedNoRedirectErasures,
    'notModifiedNoRedirectRequestCount' => count($notModifiedNoRedirectRequests),
    'proxyAuthorizationSent' => $requests[0]['httpOptions']['proxyAuthorization'] ?? null,
    'originProxyHeaderLeaked' => isset($requests[0]['headers']['Proxy-Authorization']),
    'wordpressUse' => 'A WordPress deployment tool can retrieve proxy credentials from a callback, canonicalize default proxy ports out of credential-helper context while preserving concrete proxy streams, preserve non-default repository ports in proxy helper and store context, preserve proxy URL usernames and embedded proxy credential URLs as helper context, prefer helper-returned proxy credentials over stale URL credentials, keep proxy credentials out of origin headers, distinguish curl-style bare-star noProxy bypasses from literal asterisk-bearing host patterns, bypass bracketed IPv6 literal repository hosts without consulting proxy helpers, bypass bracketed IPv6 CIDR noProxy ranges while preserving receive-pack cookies, preserve proxy use for curl-style port-qualified noProxy literal tokens, carry curl-compatible root-scoped non-slash Path cookies through authenticated receive-pack proxies, use HTTPS-specific and all-proxy fallbacks with receive-pack cookies intact, treat an explicitly empty HTTPS proxy as disabling lower all-proxy fallback, keep an HTTP-origin request direct when a safe redirect upgrades it to HTTPS, resolve protocol-relative redirects through the same smart HTTP proxy while preserving scoped cookies, bypass proxies for DNS-equivalent trailing-dot repository hosts, preserve domain-scoped cookies and curl-accepted trailing-dot Domain attributes from those hosts into receive-pack POSTs, reuse one helper credential action across a safe smart HTTP redirect, store helper credentials after accepted 200 or default redirect-mode 304 smart HTTP responses, preserve accepted 304 discovery cookies into receive-pack POSTs, and erase helper credentials when no-redirect mode rejects a 304 or when unexpected proxy/origin statuses arrive.',
];
