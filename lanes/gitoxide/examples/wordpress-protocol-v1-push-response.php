<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\PushRefStatus;
use PortLibs\Gitoxide\PushResponse;

$fixture = require __DIR__ . '/../fixtures/wordpress-protocol-v1-push-response.php';
$response = PushResponse::fromSidebandPacketLines($fixture['response']);
$rewrittenResponse = PushResponse::fromReportStatusPacketLines($fixture['rewrittenResponse']);

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
        ],
        $rewrittenResponse->refStatuses()
    ),
    'progressMessages' => $response->progressMessages(),
    'errorMessages' => $response->errorMessages(),
];
