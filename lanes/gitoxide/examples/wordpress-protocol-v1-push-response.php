<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\PushRefStatus;
use PortLibs\Gitoxide\PushResponse;

$fixture = require __DIR__ . '/../fixtures/wordpress-protocol-v1-push-response.php';
$response = PushResponse::fromSidebandPacketLines($fixture['response']);
$rewrittenResponse = PushResponse::fromReportStatusPacketLines($fixture['rewrittenResponse']);
$fallThroughResponse = PushResponse::fromReportStatusPacketLines($fixture['fallThroughResponse']);
$compatibilityResponse = PushResponse::fromReportStatusPacketLines($fixture['compatibilityResponse']);
$emptyRejectionResponse = PushResponse::fromReportStatusPacketLines($fixture['emptyRejectionResponse']);
$emptyUnpackStatusResponse = PushResponse::fromReportStatusPacketLines($fixture['emptyUnpackStatusResponse']);
$valuelessOptionResponse = PushResponse::fromReportStatusPacketLines($fixture['valuelessOptionResponse'])
    ->forExpectedRefNames([$fixture['valuelessOptionRef']['requested']]);
$malformedObjectOptionResponse = PushResponse::fromReportStatusPacketLines($fixture['malformedObjectOptionResponse']);
$objectPrefixDiagnosticResponse = PushResponse::fromReportStatusPacketLines($fixture['objectPrefixDiagnosticResponse']);
$expectedFilteredResponse = PushResponse::fromReportStatusPacketLines($fixture['expectedFilterResponse'])
    ->forExpectedRefNames($fixture['expectedRefNames']);
$multiReportResponse = PushResponse::fromReportStatusPacketLines($fixture['multiReportResponse'])
    ->forExpectedRefNames([$fixture['multiReportRef']['requested']]);
$noRefnameMultiReportResponse = PushResponse::fromReportStatusPacketLines($fixture['noRefnameMultiReportResponse'])
    ->forExpectedRefNames([$fixture['noRefnameMultiReportRef']['requested']]);
$rejectedReportResponse = PushResponse::fromReportStatusPacketLines($fixture['rejectedReportResponse'])
    ->forExpectedRefNames([$fixture['rejectedReportRef']['requested']]);
$missingExpectedResponse = PushResponse::fromReportStatusPacketLines($fixture['missingExpectedResponse'])
    ->forExpectedRefNames(['refs/heads/main']);
$unpackOnlyExpectedResponse = PushResponse::fromReportStatusPacketLines($fixture['unpackOnlyResponse'])
    ->forExpectedRefNames($fixture['unpackOnlyExpectedRefs']);
$unpackOnlyExpectedRefsRejected = !$unpackOnlyExpectedResponse->isSuccessful();
foreach ($unpackOnlyExpectedResponse->refStatuses() as $status) {
    if (!$status->isRejected() || $status->message !== 'remote failed to report status') {
        $unpackOnlyExpectedRefsRejected = false;
        break;
    }
}
$oversizedReportStatusRejected = false;
try {
    PushResponse::fromReportStatusPacketLines($fixture['oversizedReportStatus']);
} catch (InvalidArgumentException $error) {
    $oversizedReportStatusRejected = str_contains($error->getMessage(), 'packet line exceeds maximum length');
}
$fatalSidebandRejected = false;
try {
    PushResponse::fromSidebandPacketLines($fixture['fatalSidebandResponse']);
} catch (RuntimeException $error) {
    $fatalSidebandRejected = str_contains($error->getMessage(), 'sideband error pre-receive hook declined deployment');
}
$fatalAfterStatusRejected = false;
try {
    PushResponse::fromSidebandPacketLines($fixture['fatalAfterStatusResponse']);
} catch (RuntimeException $error) {
    $fatalAfterStatusRejected = str_contains($error->getMessage(), 'sideband error pre-receive hook declined after deployment status');
}
$emptyErrorSidebandResponse = PushResponse::fromSidebandPacketLines($fixture['emptyErrorSidebandResponse']);
$emptyProgressSidebandResponse = PushResponse::fromSidebandPacketLines($fixture['emptyProgressSidebandResponse']);
$responseEndTerminatedResponse = PushResponse::fromReportStatusPacketLines($fixture['responseEndTerminatedResponse']);
$delimiterTerminatedResponse = PushResponse::fromReportStatusPacketLines($fixture['delimiterTerminatedResponse']);
$carriageReturnStatusRejected = false;
try {
    PushResponse::fromReportStatusPacketLines($fixture['carriageReturnStatusResponse']);
} catch (InvalidArgumentException $error) {
    $carriageReturnStatusRejected = str_contains($error->getMessage(), 'Reference name contains an invalid byte');
}
$emptyPacketLineRejected = false;
try {
    PushResponse::fromReportStatusPacketLines($fixture['emptyPacketLineResponse']);
} catch (InvalidArgumentException $error) {
    $emptyPacketLineRejected = str_contains($error->getMessage(), 'invalid empty packet line');
}
$unrequestedOptionRejected = false;
try {
    PushResponse::fromReportStatusPacketLines($fixture['unrequestedOptionResponse'])
        ->forExpectedRefNames(['refs/heads/main']);
} catch (InvalidArgumentException $error) {
    $unrequestedOptionRejected = str_contains($error->getMessage(), 'option followed unrequested ref');
}

return [
    'unpackOk' => $response->unpackOk(),
    'successful' => $response->isSuccessful(),
    'refs' => array_map(
        static fn (PushRefStatus $status): array => [
            'ref' => $status->refName,
            'effectiveRef' => $status->effectiveRefName(),
            'status' => $status->status,
            'message' => $status->message,
        ],
        $response->refStatuses()
    ),
    'rewrittenRefs' => array_map(
        static fn (PushRefStatus $status): array => [
            'requestedRef' => $status->refName,
            'effectiveRef' => $status->effectiveRefName(),
            'oldObject' => $status->oldObject,
            'newObject' => $status->newObject,
            'forcedUpdate' => $status->forcedUpdate,
            'fallThrough' => $status->fallThrough,
        ],
        $rewrittenResponse->refStatuses()
    ),
    'fallThroughRefs' => array_map(
        static fn (PushRefStatus $status): array => [
            'requestedRef' => $status->refName,
            'effectiveRef' => $status->effectiveRefName(),
            'fallThrough' => $status->fallThrough,
        ],
        $fallThroughResponse->refStatuses()
    ),
    'compatibilityRefs' => array_map(
        static fn (PushRefStatus $status): array => [
            'requestedRef' => $status->refName,
            'effectiveRef' => $status->effectiveRefName(),
            'status' => $status->status,
            'message' => $status->message,
            'oldObject' => $status->oldObject,
            'newObject' => $status->newObject,
            'forcedUpdate' => $status->forcedUpdate,
        ],
        $compatibilityResponse->refStatuses()
    ),
    'emptyRejectionRefs' => array_map(
        static fn (PushRefStatus $status): array => [
            'requestedRef' => $status->refName,
            'status' => $status->status,
            'message' => $status->message,
        ],
        $emptyRejectionResponse->refStatuses()
    ),
    'valuelessOptionRefs' => array_map(
        static fn (PushRefStatus $status): array => [
            'requestedRef' => $status->refName,
            'effectiveRef' => $status->effectiveRefName(),
            'status' => $status->status,
            'message' => $status->message,
            'oldObject' => $status->oldObject,
            'newObject' => $status->newObject,
            'hasReportOption' => $status->hasReportOption(),
        ],
        $valuelessOptionResponse->refStatuses()
    ),
    'malformedObjectOptionRefs' => array_map(
        static fn (PushRefStatus $status): array => [
            'requestedRef' => $status->refName,
            'status' => $status->status,
            'oldObject' => $status->oldObject,
            'newObject' => $status->newObject,
            'hasReportOption' => $status->hasReportOption(),
        ],
        $malformedObjectOptionResponse->refStatuses()
    ),
    'objectPrefixDiagnosticRefs' => array_map(
        static fn (PushRefStatus $status): array => [
            'requestedRef' => $status->refName,
            'status' => $status->status,
            'oldObject' => $status->oldObject,
            'newObject' => $status->newObject,
            'hasReportOption' => $status->hasReportOption(),
        ],
        $objectPrefixDiagnosticResponse->refStatuses()
    ),
    'expectedFilteredRefs' => array_map(
        static fn (PushRefStatus $status): array => [
            'requestedRef' => $status->refName,
            'effectiveRef' => $status->effectiveRefName(),
            'status' => $status->status,
            'message' => $status->message,
        ],
        $expectedFilteredResponse->refStatuses()
    ),
    'multiReportRefs' => array_map(
        static fn (PushRefStatus $status): array => [
            'requestedRef' => $status->refName,
            'effectiveRef' => $status->effectiveRefName(),
            'oldObject' => $status->oldObject,
            'newObject' => $status->newObject,
        ],
        $multiReportResponse->refStatuses()
    ),
    'noRefnameMultiReportRefs' => array_map(
        static fn (PushRefStatus $status): array => [
            'requestedRef' => $status->refName,
            'effectiveRef' => $status->effectiveRefName(),
            'oldObject' => $status->oldObject,
            'newObject' => $status->newObject,
            'forcedUpdate' => $status->forcedUpdate,
        ],
        $noRefnameMultiReportResponse->refStatuses()
    ),
    'rejectedReportRefs' => array_map(
        static fn (PushRefStatus $status): array => [
            'requestedRef' => $status->refName,
            'effectiveRef' => $status->effectiveRefName(),
            'status' => $status->status,
            'message' => $status->message,
            'oldObject' => $status->oldObject,
            'newObject' => $status->newObject,
        ],
        $rejectedReportResponse->refStatuses()
    ),
    'missingExpectedRefs' => array_map(
        static fn (PushRefStatus $status): array => [
            'requestedRef' => $status->refName,
            'status' => $status->status,
            'message' => $status->message,
        ],
        $missingExpectedResponse->refStatuses()
    ),
    'unpackOnlyExpectedRefs' => array_map(
        static fn (PushRefStatus $status): array => [
            'requestedRef' => $status->refName,
            'status' => $status->status,
            'message' => $status->message,
        ],
        $unpackOnlyExpectedResponse->refStatuses()
    ),
    'progressMessages' => $response->progressMessages(),
    'errorMessages' => $response->errorMessages(),
    'oversizedReportStatusRejected' => $oversizedReportStatusRejected,
    'fatalSidebandRejected' => $fatalSidebandRejected,
    'fatalAfterStatusRejected' => $fatalAfterStatusRejected,
    'emptyErrorSidebandAccepted' => $emptyErrorSidebandResponse->isSuccessful()
        && $emptyErrorSidebandResponse->errorMessages() === [],
    'emptyProgressSidebandIgnored' => $emptyProgressSidebandResponse->isSuccessful()
        && $emptyProgressSidebandResponse->progressMessages() === ['remote: WordPress deployment accepted'],
    'responseEndTerminatedAccepted' => $responseEndTerminatedResponse->isSuccessful()
        && $responseEndTerminatedResponse->refStatuses()[0]->refName === 'refs/heads/wp-release',
    'delimiterTerminatedAccepted' => $delimiterTerminatedResponse->isSuccessful()
        && $delimiterTerminatedResponse->refStatuses()[0]->refName === 'refs/heads/wp-preview',
    'fallThroughAccepted' => $fallThroughResponse->refStatuses()[0]->fallThrough,
    'compatibilityOptionExtensionsIgnored' => $compatibilityResponse->refStatuses()[0]->oldObject === $fixture['compatibilityRef']['oldObject'],
    'compatibilityTrailingObjectDiagnosticsIgnored' => $compatibilityResponse->refStatuses()[0]->oldObject === $fixture['compatibilityRef']['oldObject']
        && $compatibilityResponse->refStatuses()[0]->newObject === $fixture['compatibilityRef']['newObject'],
    'compatibilityBareRejectionDefaulted' => $compatibilityResponse->refStatuses()[1]->message === 'failed',
    'emptyRejectionMessagePreserved' => $emptyRejectionResponse->rejectedRefs()[0]->message === '',
    'emptyUnpackStatusRejected' => !$emptyUnpackStatusResponse->unpackOk()
        && $emptyUnpackStatusResponse->unpackStatus() === ''
        && $emptyUnpackStatusResponse->rejectedRefs()[0]->message === $fixture['emptyUnpackStatusRef']['message'],
    'valuelessReportStatusOptionsAccepted' => $valuelessOptionResponse->isSuccessful()
        && $valuelessOptionResponse->refStatuses()[0]->effectiveRefName() === $fixture['valuelessOptionRef']['requested']
        && $valuelessOptionResponse->refStatuses()[0]->oldObject === null
        && $valuelessOptionResponse->refStatuses()[0]->newObject === null
        && $valuelessOptionResponse->refStatuses()[0]->hasReportOption(),
    'malformedObjectOptionsIgnored' => $malformedObjectOptionResponse->isSuccessful()
        && $malformedObjectOptionResponse->refStatuses()[0]->oldObject === $fixture['malformedObjectOptionRef']['oldObject']
        && $malformedObjectOptionResponse->refStatuses()[0]->newObject === $fixture['malformedObjectOptionRef']['newObject']
        && $malformedObjectOptionResponse->refStatuses()[0]->hasReportOption(),
    'objectPrefixDiagnosticSuffixesParsed' => $objectPrefixDiagnosticResponse->isSuccessful()
        && $objectPrefixDiagnosticResponse->refStatuses()[0]->oldObject === $fixture['objectPrefixDiagnosticRef']['oldObject']
        && $objectPrefixDiagnosticResponse->refStatuses()[0]->newObject === $fixture['objectPrefixDiagnosticRef']['newObject']
        && $objectPrefixDiagnosticResponse->refStatuses()[0]->hasReportOption(),
    'expectedUnknownStatusIgnored' => count($expectedFilteredResponse->refStatuses()) === 2,
    'expectedLastStatusWon' => $expectedFilteredResponse->refStatuses()[0]->message === 'post-update hook accepted',
    'multiReportStatusPreserved' => array_map(
        static fn (PushRefStatus $status): string => $status->effectiveRefName(),
        $multiReportResponse->refStatuses()
    ) === $fixture['multiReportRef']['actual'],
    'noRefnameMultiReportStatusPreserved' => array_map(
        static fn (PushRefStatus $status): string => $status->effectiveRefName(),
        $noRefnameMultiReportResponse->refStatuses()
    ) === $fixture['noRefnameMultiReportRef']['actual'],
    'rejectedReportStatusPreserved' => !$rejectedReportResponse->isSuccessful()
        && array_map(
            static fn (PushRefStatus $status): string => $status->effectiveRefName(),
            $rejectedReportResponse->rejectedRefs()
        ) === $fixture['rejectedReportRef']['actual']
        && $rejectedReportResponse->rejectedRefs()[0]->message === $fixture['rejectedReportRef']['message'],
    'missingExpectedStatusRejected' => !$missingExpectedResponse->isSuccessful()
        && $missingExpectedResponse->rejectedRefs()[0]->message === 'remote failed to report status',
    'unpackOnlyExpectedRefsRejected' => $unpackOnlyExpectedRefsRejected,
    'carriageReturnStatusRejected' => $carriageReturnStatusRejected,
    'emptyPacketLineRejected' => $emptyPacketLineRejected,
    'unrequestedOptionRejected' => $unrequestedOptionRejected,
];
