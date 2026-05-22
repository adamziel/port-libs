<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

$fixture = require __DIR__ . '/../fixtures/wordpress-receive-pack-transport.php';

return [
    'oldCommit' => $fixture['oldCommit'],
    'newCommit' => $fixture['newCommit'],
    'acceptedRefs' => $fixture['acceptedRefs'],
    'progressMessages' => $fixture['progressMessages'],
    'requestByteLength' => strlen($fixture['requestBytes']),
    'responseSuccessful' => $fixture['responseSuccessful'],
];
