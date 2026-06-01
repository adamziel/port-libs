<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

$fixture = require __DIR__ . '/../fixtures/wordpress-credential-context.php';

return [
    'credentialUrl' => $fixture['credentialUrl'],
    'encodedHost' => $fixture['encodedContext']['host'],
    'encodedPath' => $fixture['encodedContext']['path'],
    'requestBytes' => $fixture['requestBytes'],
    'emptyQuitFalse' => $fixture['emptyQuitFalse'],
    'overflowExpiryIgnored' => $fixture['overflowExpiryIgnored'],
    'overflowQuitIgnored' => $fixture['overflowQuitIgnored'],
    'rootHttpPathCleared' => $fixture['rootHttpPathCleared'],
    'fileUrlClearedHost' => $fixture['fileUrlClearedHost'],
    'fileUrlPath' => $fixture['fileUrlPath'],
    'localPathClearedHost' => $fixture['localPathClearedHost'],
    'localPathPath' => $fixture['localPathPath'],
    'fileAuthorityContext' => $fixture['fileAuthorityContext'],
    'pathlessExtensionContext' => $fixture['pathlessExtensionContext'],
    'hostlessExtensionContext' => $fixture['hostlessExtensionContext'],
    'duplicateInvalidStringRejected' => $fixture['duplicateInvalidStringRejected'],
    'constructorInvalidStringRejected' => $fixture['constructorInvalidStringRejected'],
    'constructorByteFieldsPreserved' => $fixture['constructorByteFieldsPreserved'],
    'duplicateBytePath' => $fixture['duplicateBytePath'],
    'bareCarriageReturnPathPreserved' => $fixture['bareCarriageReturnPathPreserved'],
    'crlfPathTerminatorStripped' => $fixture['crlfPathTerminatorStripped'],
    'helperProgramProtocolHost' => $fixture['helperProgramProtocolHost'],
    'helperProgramMissingCredential' => $fixture['helperProgramMissingCredential'],
    'helperProgramUrlOnly' => $fixture['helperProgramUrlOnly'],
    'helperProgramOutput' => $fixture['helperProgramOutput'],
    'helperInvocationIdentity' => $fixture['helperInvocationIdentity'],
    'helperRequiredIdentity' => $fixture['helperRequiredIdentity'],
    'helperInvocationQuit' => $fixture['helperInvocationQuit'],
    'helperInvocationNextQuit' => $fixture['helperInvocationNextQuit'],
    'helperInvocationStorePayloadBytes' => strlen($fixture['helperInvocationStorePayload']),
    'helperInvocationErasePayloadBytes' => strlen($fixture['helperInvocationErasePayload']),
    'helperMissingIdentityRedacted' => $fixture['helperMissingIdentityRedacted'],
    'helperQuitMessage' => $fixture['helperQuitMessage'],
    'redactedBytes' => $fixture['redactedBytes'],
    'secretsInCleartextLog' => str_contains($fixture['redactedBytes'], 'wp-deploy-token')
        || str_contains($fixture['redactedBytes'], 'wp-refresh-token'),
    'wordpressUse' => $fixture['wordpressUse'],
];
