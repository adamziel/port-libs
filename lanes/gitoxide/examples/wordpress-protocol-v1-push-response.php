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
$expectedFilteredResponse = PushResponse::fromReportStatusPacketLines($fixture['expectedFilterResponse'])
    ->forExpectedRefNames($fixture['expectedRefNames']);
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
    'expectedFilteredRefs' => array_map(
        static fn (PushRefStatus $status): array => [
            'requestedRef' => $status->refName,
            'effectiveRef' => $status->effectiveRefName(),
            'status' => $status->status,
            'message' => $status->message,
        ],
        $expectedFilteredResponse->refStatuses()
    ),
    'progressMessages' => $response->progressMessages(),
    'errorMessages' => $response->errorMessages(),
    'oversizedReportStatusRejected' => $oversizedReportStatusRejected,
    'fatalSidebandRejected' => $fatalSidebandRejected,
    'fallThroughAccepted' => $fallThroughResponse->refStatuses()[0]->fallThrough,
    'compatibilityOptionExtensionsIgnored' => $compatibilityResponse->refStatuses()[0]->oldObject === $fixture['compatibilityRef']['oldObject'],
    'compatibilityBareRejectionDefaulted' => $compatibilityResponse->refStatuses()[1]->message === 'failed',
    'expectedUnknownStatusIgnored' => count($expectedFilteredResponse->refStatuses()) === 2,
    'expectedLastStatusWon' => $expectedFilteredResponse->refStatuses()[0]->message === 'post-update hook accepted',
    'carriageReturnStatusRejected' => $carriageReturnStatusRejected,
    'emptyPacketLineRejected' => $emptyPacketLineRejected,
    'unrequestedOptionRejected' => $unrequestedOptionRejected,
];
