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
    'sshIpv6Target' => $fixture['sshIpv6Target'],
    'sshCommand' => $fixture['sshCommand'],
    'sshProtocolV2Environment' => $fixture['sshProtocolV2Context']['environment'],
    'sshProtocolV2Arguments' => $fixture['sshProtocolV2Context']['sshArguments'],
    'sshCredentialContext' => $fixture['sshProtocolV2Context']['redactedCredentialContext'],
    'sshAuthenticationBoundary' => $fixture['sshProtocolV2Context']['authenticationBoundary'],
    'gitDaemonServiceRequestPayload' => substr($fixture['gitDaemonServiceRequest'], 4),
    'gitDaemonUrlServiceRequestPayload' => substr($fixture['gitDaemonUrlServiceRequest'], 4),
    'gitDaemonEncodedUrlServiceRequestPayload' => substr($fixture['gitDaemonEncodedUrlServiceRequest'], 4),
    'gitDaemonIpv6ServiceRequestPayload' => substr($fixture['gitDaemonIpv6ServiceRequest'], 4),
    'unsafeGitDaemonPathRejected' => $fixture['unsafeGitDaemonPathRejected'],
    'unsafeGitDaemonControlByteRejected' => $fixture['unsafeGitDaemonControlByteRejected'],
    'unsafeGitDaemonUrlRejected' => $fixture['unsafeGitDaemonUrlRejected'],
    'unsafeGitDaemonEncodedControlByteRejected' => $fixture['unsafeGitDaemonEncodedControlByteRejected'],
    'unsafeGitDaemonEncodedHostDelimiterRejected' => $fixture['unsafeGitDaemonEncodedHostDelimiterRejected'],
    'unsafeGitDaemonExtraParameterRejected' => $fixture['unsafeGitDaemonExtraParameterRejected'],
    'unsafeSmartHttpCredentialControlByteRejected' => $fixture['unsafeSmartHttpCredentialControlByteRejected'],
    'unsafeSmartHttpCredentialTabRejected' => $fixture['unsafeSmartHttpCredentialTabRejected'],
    'unsafeSmartHttpHostDelimiterRejected' => $fixture['unsafeSmartHttpHostDelimiterRejected'],
    'unsafeSmartHttpProxyHostDelimiterRejected' => $fixture['unsafeSmartHttpProxyHostDelimiterRejected'],
    'unsafeSmartHttpEncodedPathControlByteRejected' => $fixture['unsafeSmartHttpEncodedPathControlByteRejected'],
    'unsafeSmartHttpExtraParameterTabRejected' => $fixture['unsafeSmartHttpExtraParameterTabRejected'],
    'unsafeSmartHttpHeaderTabRejected' => $fixture['unsafeSmartHttpHeaderTabRejected'],
    'unsafeSmartHttpNoProxyDelimiterRejected' => $fixture['unsafeSmartHttpNoProxyDelimiterRejected'],
    'unsafeSmartHttpRawUrlControlByteRejected' => $fixture['unsafeSmartHttpRawUrlControlByteRejected'],
    'unsafeSmartHttpRawProxyControlByteRejected' => $fixture['unsafeSmartHttpRawProxyControlByteRejected'],
    'smartHttpAdvertisementWithoutServiceHeaderAccepted' => $fixture['smartHttpAdvertisementWithoutServiceHeaderAccepted'],
    'streamWatchdogTimeoutReported' => $fixture['streamWatchdogTimeoutReported'],
    'advertisementErrorReported' => $fixture['advertisementErrorReported'],
    'unsafeSshTargetRejected' => $fixture['unsafeSshTargetRejected'],
    'unsafeSshHostDelimiterRejected' => $fixture['unsafeSshHostDelimiterRejected'],
    'unsafeSshUserDelimiterRejected' => $fixture['unsafeSshUserDelimiterRejected'],
    'unsafeSshEncodedUserDelimiterRejected' => $fixture['unsafeSshEncodedUserDelimiterRejected'],
    'unsafeSshPasswordRejected' => $fixture['unsafeSshPasswordRejected'],
];
