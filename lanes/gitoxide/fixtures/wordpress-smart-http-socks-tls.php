<?php

declare(strict_types=1);

return [
    'repositoryUrl' => 'https://git.example.test/wp-content.git',
    'proxyUrl' => 'socks5h://wp-proxy.example.test:1080',
    'tlsPeerName' => 'git.example.test',
    'caOption' => 'sslCaInfo',
    'verifyOption' => 'sslVerify',
    'connectHost' => 'git.example.test',
    'connectPort' => 443,
    'requestTarget' => '/wp-content.git/info/refs?service=git-receive-pack',
    'originHeaders' => [
        'Host' => 'git.example.test',
        'User-Agent' => 'port-libs-socks-tls-test/1',
    ],
    'wordpressUse' => 'A shared-hosting WordPress deployment tool can discover receive-pack refs over HTTPS through a SOCKS proxy, verify a private Git server certificate with a configured CA file, and keep proxy credentials out of origin HTTP headers.',
];
