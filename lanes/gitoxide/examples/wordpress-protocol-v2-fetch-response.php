<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\FetchResponse;

$fixture = require __DIR__ . '/../fixtures/wordpress-protocol-v2-fetch-response.php';
$response = FetchResponse::fromV2PacketLines($fixture['response'], $fixture['sidebandAll'] ?? false);
$emptyErrorSidebandResponse = FetchResponse::fromV2PacketLines($fixture['emptyErrorSidebandResponse']);
$uploadPackError = null;
try {
    FetchResponse::fromV2PacketLines($fixture['rawUploadPackErrorResponse'], true);
} catch (RuntimeException $error) {
    $uploadPackError = rtrim($error->getMessage());
}

return [
    'acknowledgements' => array_map(
        static fn ($ack): array => ['kind' => $ack->kind, 'object' => $ack->object],
        $response->acknowledgements()
    ),
    'shallowUpdates' => array_map(
        static fn ($update): array => ['kind' => $update->kind, 'object' => $update->object],
        $response->shallowUpdates()
    ),
    'wantedRefs' => array_map(
        static fn ($wantedRef): array => ['path' => $wantedRef->path, 'object' => $wantedRef->object],
        $response->wantedRefs()
    ),
    'hasPack' => $response->hasPack(),
    'packetLineMaxBytes' => $fixture['packetLineMaxBytes'],
    'packPrefix' => substr($response->packData(), 0, 4),
    'packBytes' => strlen($response->packData()),
    'packTrailer' => bin2hex(substr($response->packData(), -20)),
    'progress' => $response->progressMessages(),
    'remoteProgress' => array_map(
        static fn ($progress): array => [
            'action' => $progress->action,
            'percent' => $progress->percent,
            'step' => $progress->step,
            'max' => $progress->max,
        ],
        $response->remoteProgress()
    ),
    'errors' => $response->errorMessages(),
    'uploadPackError' => $uploadPackError,
    'emptyErrorKeepaliveIgnored' => $emptyErrorSidebandResponse->errorMessages() === []
        && $emptyErrorSidebandResponse->packData() === $fixture['packData'],
];
