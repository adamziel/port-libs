<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\FetchResponse;
use PortLibs\Gitoxide\ProtocolV2FetchExchange;

$fixture = require __DIR__ . '/../fixtures/wordpress-protocol-v2-fetch-response.php';
$response = FetchResponse::fromV2PacketLines($fixture['response'], $fixture['sidebandAll'] ?? false);
$emptyErrorSidebandResponse = FetchResponse::fromV2PacketLines($fixture['emptyErrorSidebandResponse']);
$overflowProgressResponse = FetchResponse::fromV2PacketLines($fixture['overflowProgressResponse']);
$suffixlessAckResponse = FetchResponse::fromV2PacketLines($fixture['suffixlessAckResponse']);
$refInWantResponse = FetchResponse::fromV2PacketLines($fixture['refInWantResponse']);
$sha256Response = FetchResponse::fromV2PacketLines($fixture['sha256Response']);
$cloneExchange = ProtocolV2FetchExchange::fromPacketLines($fixture['cloneExchangeResponse']);
$responseEndNoPackResponse = FetchResponse::fromV2PacketLines($fixture['responseEndNoPackResponse']);
$responseEndPackResponse = FetchResponse::fromV2PacketLines($fixture['responseEndPackResponse']);
$delimiterPackResponse = FetchResponse::fromV2PacketLines($fixture['delimiterPackResponse']);
$sidebandAllResponseEndMessages = [];
$sidebandAllResponseEndResponse = FetchResponse::fromV2PacketLines(
    $fixture['sidebandAllResponseEndResponse'],
    true,
    static function (bool $isError, string $text) use (&$sidebandAllResponseEndMessages): bool {
        $sidebandAllResponseEndMessages[] = ['isError' => $isError, 'text' => $text];

        return true;
    }
);
$smartHttpUploadPackResponse = FetchResponse::fromSmartHttpUploadPackResult($fixture['smartHttpUploadPackResponse']);
$uploadPackError = null;
try {
    FetchResponse::fromV2PacketLines($fixture['rawUploadPackErrorResponse'], true);
} catch (RuntimeException $error) {
    $uploadPackError = rtrim($error->getMessage());
}
$truncatedPackError = null;
try {
    FetchResponse::fromV2PacketLines($fixture['truncatedPackResponse']);
} catch (RuntimeException $error) {
    $truncatedPackError = rtrim($error->getMessage());
}
$progressCancelMessages = [];
$progressCancelError = null;
try {
    FetchResponse::fromV2PacketLines(
        $fixture['progressCancelResponse'],
        false,
        static function (bool $isError, string $text) use (&$progressCancelMessages): bool {
            $progressCancelMessages[] = ['isError' => $isError, 'text' => $text];

            return false;
        }
    );
} catch (RuntimeException $error) {
    $progressCancelError = rtrim($error->getMessage());
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
    'truncatedPackRejected' => $truncatedPackError === 'fetch response: missing sideband flush packet',
    'truncatedPackError' => $truncatedPackError,
    'progressCancellationRejected' => $progressCancelError === 'fetch response: interrupted by user',
    'progressCancellationError' => $progressCancelError,
    'progressCancellationMessages' => $progressCancelMessages,
    'emptyErrorKeepaliveIgnored' => $emptyErrorSidebandResponse->errorMessages() === []
        && $emptyErrorSidebandResponse->packData() === $fixture['packData'],
    'overflowProgress' => array_map(
        static fn ($progress): array => [
            'action' => $progress->action,
            'percent' => $progress->percent,
            'step' => $progress->step,
            'max' => $progress->max,
        ],
        $overflowProgressResponse->remoteProgress()
    ),
    'overflowPercentageBounded' => count($overflowProgressResponse->remoteProgress()) === 2
        && $overflowProgressResponse->remoteProgress()[0]->percent === 4294967295
        && $overflowProgressResponse->remoteProgress()[1]->percent === null
        && $overflowProgressResponse->remoteProgress()[1]->step === 5
        && $overflowProgressResponse->packData() === $fixture['packData'],
    'suffixlessAckParsed' => count($suffixlessAckResponse->acknowledgements()) === 3
        && $suffixlessAckResponse->acknowledgements()[0]->object === $fixture['objects']['installed']
        && $suffixlessAckResponse->acknowledgements()[1]->object === $fixture['objects']['main']
        && $suffixlessAckResponse->packData() === $fixture['packData'],
    'refInWantParsed' => count($refInWantResponse->wantedRefs()) === 1
        && $refInWantResponse->wantedRefs()[0]->path === 'refs/heads/main'
        && $refInWantResponse->wantedRefs()[0]->object === $fixture['objects']['main']
        && $refInWantResponse->packData() === $fixture['packData'],
    'refInWantPackTrailer' => bin2hex(substr($refInWantResponse->packData(), -20)),
    'sha256ObjectFormatParsed' => $sha256Response->acknowledgements()[0]->object === $fixture['objectsSha256']['installed']
        && $sha256Response->acknowledgements()[1]->object === $fixture['objectsSha256']['main']
        && $sha256Response->shallowUpdates()[0]->object === $fixture['objectsSha256']['shallow']
        && $sha256Response->wantedRefs()[0]->object === $fixture['objectsSha256']['main']
        && $sha256Response->packData() === $fixture['sha256PackData'],
    'sha256PackTrailer' => bin2hex(substr($sha256Response->packData(), -32)),
    'cloneExchangeCapabilities' => $cloneExchange->capabilities()->names(),
    'cloneExchangeRefs' => array_map(
        static fn ($ref): array => [
            'kind' => $ref->kind,
            'name' => $ref->name,
            'object' => $ref->object,
            'target' => $ref->target,
        ],
        $cloneExchange->remoteRefs()
    ),
    'cloneExchangeParsed' => $cloneExchange->remoteRefs()[0]->target === 'refs/heads/main'
        && $cloneExchange->fetchResponse()->packData() === $fixture['packData']
        && $cloneExchange->fetchResponse()->progressMessages() === ['Enumerating objects: 1, done.'],
    'cloneExchangePackTrailer' => bin2hex(substr($cloneExchange->fetchResponse()->packData(), -20)),
    'responseEndNoPackParsed' => $responseEndNoPackResponse->hasPack() === false
        && count($responseEndNoPackResponse->acknowledgements()) === 1
        && $responseEndNoPackResponse->acknowledgements()[0]->kind === 'nak'
        && $responseEndNoPackResponse->terminator() === 'response-end',
    'responseEndPackParsed' => $responseEndPackResponse->hasPack()
        && $responseEndPackResponse->packData() === $fixture['packData']
        && $responseEndPackResponse->progressMessages() === ['Counting objects: 100% (1/1)']
        && $responseEndPackResponse->terminator() === 'response-end',
    'responseEndPackTrailer' => bin2hex(substr($responseEndPackResponse->packData(), -20)),
    'delimiterPackParsed' => $delimiterPackResponse->hasPack()
        && $delimiterPackResponse->packData() === $fixture['packData']
        && $delimiterPackResponse->progressMessages() === ['Counting objects: 100% (1/1)']
        && $delimiterPackResponse->terminator() === 'delimiter',
    'delimiterPackTrailer' => bin2hex(substr($delimiterPackResponse->packData(), -20)),
    'sidebandAllResponseEndParsed' => $sidebandAllResponseEndResponse->hasPack()
        && $sidebandAllResponseEndResponse->packData() === $fixture['packData']
        && $sidebandAllResponseEndResponse->errorMessages() === ['remote: deployment warning before pack']
        && $sidebandAllResponseEndResponse->terminator() === 'response-end',
    'sidebandAllResponseEndMessages' => $sidebandAllResponseEndMessages,
    'smartHttpUploadPackParsed' => $smartHttpUploadPackResponse->packData() === $fixture['packData']
        && count($smartHttpUploadPackResponse->acknowledgements()) === 3
        && $smartHttpUploadPackResponse->progressMessages() === ['Counting objects: 100% (1/1)' . "\r" . 'Counting objects: 100% (1/1), done.'],
    'smartHttpUploadPackTrailer' => bin2hex(substr($smartHttpUploadPackResponse->packData(), -20)),
];
