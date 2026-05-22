<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

$fixture = require __DIR__ . '/../fixtures/wordpress-smart-http-cleartext-credentials.php';

return [
    'error' => $fixture['error'],
    'requesterReached' => $fixture['requesterReached'],
    'wordpressUse' => 'Shared-hosting WordPress deployment code can fail before network I/O and not leak deployment credentials from cleartext smart HTTP URLs.',
];
