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
    'sshTarget' => $fixture['sshTarget'],
    'sshCommand' => $fixture['sshCommand'],
    'gitDaemonServiceRequestPayload' => substr($fixture['gitDaemonServiceRequest'], 4),
    'gitDaemonIpv6ServiceRequestPayload' => substr($fixture['gitDaemonIpv6ServiceRequest'], 4),
    'unsafeGitDaemonPathRejected' => $fixture['unsafeGitDaemonPathRejected'],
    'unsafeSshTargetRejected' => $fixture['unsafeSshTargetRejected'],
];
