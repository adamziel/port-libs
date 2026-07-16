<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

$fixture = require __DIR__ . '/../fixtures/wordpress-smart-http-socks-tls.php';

return [
    'repositoryUrl' => $fixture['repositoryUrl'],
    'proxyUrl' => $fixture['proxyUrl'],
    'tlsPeerName' => $fixture['tlsPeerName'],
    'caOption' => $fixture['caOption'],
    'verifyOption' => $fixture['verifyOption'],
    'connectHost' => $fixture['connectHost'],
    'connectPort' => $fixture['connectPort'],
    'requestTarget' => $fixture['requestTarget'],
    'proxyCredentialsLeakToOrigin' => false,
    'wordpressUse' => $fixture['wordpressUse'],
];
