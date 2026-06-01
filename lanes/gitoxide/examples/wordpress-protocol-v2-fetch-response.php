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
$refInWantExchange = ProtocolV2FetchExchange::fromPacketLines($fixture['refInWantExchangeResponse']);
$sha256Response = FetchResponse::fromV2PacketLines($fixture['sha256Response']);
$cloneExchangeProgressMessages = [];
$cloneExchange = ProtocolV2FetchExchange::fromPacketLines(
    $fixture['cloneExchangeResponse'],
    false,
    null,
    static function (bool $isError, string $text) use (&$cloneExchangeProgressMessages): bool {
        $cloneExchangeProgressMessages[] = ['isError' => $isError, 'text' => $text];

        return true;
    }
);
$sidebandAllCapabilityExchangeMessages = [];
$sidebandAllCapabilityExchange = ProtocolV2FetchExchange::fromPacketLines(
    $fixture['sidebandAllCapabilityExchangeResponse'],
    null,
    null,
    static function (bool $isError, string $text) use (&$sidebandAllCapabilityExchangeMessages): bool {
        $sidebandAllCapabilityExchangeMessages[] = ['isError' => $isError, 'text' => $text];

        return true;
    }
);
$interleavedSidebandAllMessages = [];
$interleavedSidebandAllResponse = FetchResponse::fromV2PacketLines(
    $fixture['interleavedSidebandAllResponse'],
    true,
    static function (bool $isError, string $text) use (&$interleavedSidebandAllMessages): bool {
        $interleavedSidebandAllMessages[] = ['isError' => $isError, 'text' => $text];

        return true;
    }
);
$responseEndNoPackResponse = FetchResponse::fromV2PacketLines($fixture['responseEndNoPackResponse']);
$responseEndPackResponse = FetchResponse::fromV2PacketLines($fixture['responseEndPackResponse']);
$delimiterPackResponse = FetchResponse::fromV2PacketLines($fixture['delimiterPackResponse']);
$persistentFirstFetchResponse = FetchResponse::fromV2PacketLines($fixture['persistentFetchStream']);
$persistentFetchTail = substr($fixture['persistentFetchStream'], $persistentFirstFetchResponse->consumedBytes());
$persistentSecondFetchResponse = FetchResponse::fromV2PacketLines($persistentFetchTail);
$emptyErrorSidebandMessages = [];
$emptyErrorSidebandHandledResponse = FetchResponse::fromV2PacketLines(
    $fixture['emptyErrorSidebandResponse'],
    false,
    static function (bool $isError, string $text) use (&$emptyErrorSidebandMessages): bool {
        $emptyErrorSidebandMessages[] = ['isError' => $isError, 'text' => $text];

        return true;
    }
);
$sidebandAllResponseEndMessages = [];
$sidebandAllResponseEndResponse = FetchResponse::fromV2PacketLines(
    $fixture['sidebandAllResponseEndResponse'],
    true,
    static function (bool $isError, string $text) use (&$sidebandAllResponseEndMessages): bool {
        $sidebandAllResponseEndMessages[] = ['isError' => $isError, 'text' => $text];

        return true;
    }
);
$trailingWhitespaceResponse = FetchResponse::fromV2PacketLines($fixture['trailingWhitespaceResponse'], true);
$unicodeWhitespaceResponse = FetchResponse::fromV2PacketLines($fixture['unicodeWhitespaceResponse'], true);
$noNewlineSidebandAllResponse = FetchResponse::fromV2PacketLines($fixture['noNewlineSidebandAllResponse'], true);
$binarySidebandResponse = FetchResponse::fromV2PacketLines($fixture['binarySidebandResponse'], true);
$smartHttpUploadPackResponse = FetchResponse::fromSmartHttpUploadPackResult($fixture['smartHttpUploadPackResponse']);
$leadingStopMessages = [];
$leadingStopError = null;
try {
    FetchResponse::fromV2PacketLines(
        $fixture['leadingStopSidebandAllResponse'],
        true,
        static function (bool $isError, string $text) use (&$leadingStopMessages): bool {
            $leadingStopMessages[] = ['isError' => $isError, 'text' => $text];

            return true;
        }
    );
} catch (RuntimeException $error) {
    $leadingStopError = $error->getMessage();
}
$uploadPackError = null;
try {
    FetchResponse::fromV2PacketLines($fixture['rawUploadPackErrorResponse'], true);
} catch (RuntimeException $error) {
    $uploadPackError = rtrim($error->getMessage());
}
$sidebandAllDecodedErrLineError = null;
try {
    FetchResponse::fromV2PacketLines($fixture['sidebandAllDecodedErrLineResponse'], true);
} catch (InvalidArgumentException $error) {
    $sidebandAllDecodedErrLineError = $error->getMessage();
}
$truncatedPackError = null;
try {
    FetchResponse::fromV2PacketLines($fixture['truncatedPackResponse']);
} catch (RuntimeException $error) {
    $truncatedPackError = rtrim($error->getMessage());
}
$invalidUtf8ProtocolLineError = null;
try {
    FetchResponse::fromV2PacketLines($fixture['invalidUtf8ProtocolLineResponse'], true);
} catch (InvalidArgumentException $error) {
    $invalidUtf8ProtocolLineError = $error->getMessage();
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
    'sidebandAllDecodedErrLineError' => $sidebandAllDecodedErrLineError,
    'sidebandAllDecodedErrLineRejectedAsProtocolText' => $sidebandAllDecodedErrLineError === 'fetch response: unknown or unsupported section header ERR decoded WordPress fetch text',
    'truncatedPackRejected' => $truncatedPackError === 'fetch response: missing sideband flush packet',
    'truncatedPackError' => $truncatedPackError,
    'progressCancellationRejected' => $progressCancelError === 'fetch response: interrupted by user',
    'progressCancellationError' => $progressCancelError,
    'progressCancellationMessages' => $progressCancelMessages,
    'emptyErrorKeepaliveIgnored' => $emptyErrorSidebandResponse->errorMessages() === []
        && $emptyErrorSidebandResponse->packData() === $fixture['packData'],
    'emptyErrorKeepaliveHandled' => $emptyErrorSidebandHandledResponse->errorMessages() === []
        && $emptyErrorSidebandHandledResponse->packData() === $fixture['packData']
        && $emptyErrorSidebandMessages === [['isError' => true, 'text' => '']],
    'emptyErrorKeepaliveMessages' => $emptyErrorSidebandMessages,
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
    'refInWantExchangeParsed' => count($refInWantExchange->remoteRefs()) === 0
        && $refInWantExchange->lsRefsAdvertisementBytes() === ''
        && count($refInWantExchange->fetchResponse()->wantedRefs()) === 1
        && $refInWantExchange->fetchResponse()->wantedRefs()[0]->path === 'refs/heads/main'
        && $refInWantExchange->fetchResponse()->wantedRefs()[0]->object === $fixture['objects']['main']
        && $refInWantExchange->fetchResponse()->packData() === $fixture['packData'],
    'refInWantExchangeCapabilities' => $refInWantExchange->capabilities()->names(),
    'refInWantExchangePackTrailer' => bin2hex(substr($refInWantExchange->fetchResponse()->packData(), -20)),
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
    'cloneExchangeProgressMessages' => $cloneExchangeProgressMessages,
    'cloneExchangeProgressHandled' => $cloneExchangeProgressMessages === [
        ['isError' => false, 'text' => 'Enumerating objects: 1, done.'],
    ],
    'cloneExchangePackTrailer' => bin2hex(substr($cloneExchange->fetchResponse()->packData(), -20)),
    'sidebandAllCapabilityExchangeParsed' => in_array('sideband-all', $sidebandAllCapabilityExchange->capabilities()->capability('fetch')?->values() ?? [], true)
        && $sidebandAllCapabilityExchange->fetchResponse()->packData() === $fixture['packData']
        && $sidebandAllCapabilityExchange->fetchResponse()->progressMessages() === [
            'remote: preparing WordPress blobless pack',
            'Enumerating objects: 1, done.',
        ],
    'sidebandAllCapabilityExchangeMessages' => $sidebandAllCapabilityExchangeMessages,
    'sidebandAllCapabilityExchangePackTrailer' => bin2hex(substr($sidebandAllCapabilityExchange->fetchResponse()->packData(), -20)),
    'interleavedSidebandAllParsed' => $interleavedSidebandAllResponse->hasPack()
        && $interleavedSidebandAllResponse->packData() === $fixture['packData']
        && $interleavedSidebandAllResponse->acknowledgements()[0]->object === $fixture['objects']['installed']
        && $interleavedSidebandAllResponse->shallowUpdates()[0]->object === $fixture['objects']['main']
        && $interleavedSidebandAllResponse->wantedRefs()[0]->path === 'refs/heads/main',
    'interleavedSidebandAllProgress' => $interleavedSidebandAllResponse->progressMessages(),
    'interleavedSidebandAllErrors' => $interleavedSidebandAllResponse->errorMessages(),
    'interleavedSidebandAllEvents' => $interleavedSidebandAllResponse->sidebandEvents(),
    'interleavedSidebandAllMessages' => $interleavedSidebandAllMessages,
    'interleavedSidebandAllPackTrailer' => bin2hex(substr($interleavedSidebandAllResponse->packData(), -20)),
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
    'persistentFetchCursorParsed' => $persistentFirstFetchResponse->hasPack()
        && $persistentFirstFetchResponse->packData() === $fixture['packData']
        && $persistentFirstFetchResponse->consumedBytes() === strlen($fixture['persistentFirstFetchResponse'])
        && $persistentFetchTail === $fixture['persistentSecondFetchResponse']
        && $persistentSecondFetchResponse->hasPack() === false
        && $persistentSecondFetchResponse->acknowledgements()[0]->kind === 'nak'
        && $persistentSecondFetchResponse->consumedBytes() === strlen($fixture['persistentSecondFetchResponse']),
    'persistentFetchConsumedBytes' => $persistentFirstFetchResponse->consumedBytes(),
    'persistentFetchTailBytes' => strlen($persistentFetchTail),
    'persistentFetchFirstPackTrailer' => bin2hex(substr($persistentFirstFetchResponse->packData(), -20)),
    'sidebandAllResponseEndParsed' => $sidebandAllResponseEndResponse->hasPack()
        && $sidebandAllResponseEndResponse->packData() === $fixture['packData']
        && $sidebandAllResponseEndResponse->errorMessages() === ['remote: deployment warning before pack']
        && $sidebandAllResponseEndResponse->terminator() === 'response-end',
    'sidebandAllResponseEndMessages' => $sidebandAllResponseEndMessages,
    'trailingWhitespaceParsed' => $trailingWhitespaceResponse->hasPack()
        && $trailingWhitespaceResponse->acknowledgements()[0]->object === $fixture['objects']['installed']
        && $trailingWhitespaceResponse->acknowledgements()[1]->kind === 'ready'
        && $trailingWhitespaceResponse->shallowUpdates()[0]->object === $fixture['objects']['main']
        && $trailingWhitespaceResponse->wantedRefs()[0]->path === 'refs/heads/main'
        && $trailingWhitespaceResponse->wantedRefs()[0]->object === $fixture['objects']['main']
        && $trailingWhitespaceResponse->packData() === $fixture['packData']
        && $trailingWhitespaceResponse->remoteProgress()[0]->percent === 100,
    'trailingWhitespacePackTrailer' => bin2hex(substr($trailingWhitespaceResponse->packData(), -20)),
    'unicodeWhitespaceParsed' => $unicodeWhitespaceResponse->hasPack()
        && $unicodeWhitespaceResponse->acknowledgements()[0]->object === $fixture['objects']['installed']
        && $unicodeWhitespaceResponse->acknowledgements()[1]->kind === 'ready'
        && $unicodeWhitespaceResponse->shallowUpdates()[0]->object === $fixture['objects']['main']
        && $unicodeWhitespaceResponse->wantedRefs()[0]->path === 'refs/heads/main'
        && $unicodeWhitespaceResponse->wantedRefs()[0]->object === $fixture['objects']['main']
        && $unicodeWhitespaceResponse->packData() === $fixture['packData']
        && $unicodeWhitespaceResponse->remoteProgress()[0]->percent === 100,
    'unicodeWhitespacePackTrailer' => bin2hex(substr($unicodeWhitespaceResponse->packData(), -20)),
    'noNewlineSidebandAllParsed' => $noNewlineSidebandAllResponse->hasPack()
        && $noNewlineSidebandAllResponse->acknowledgements()[0]->object === $fixture['objects']['installed']
        && $noNewlineSidebandAllResponse->acknowledgements()[1]->kind === 'ready'
        && $noNewlineSidebandAllResponse->shallowUpdates()[0]->object === $fixture['objects']['main']
        && $noNewlineSidebandAllResponse->wantedRefs()[0]->path === 'refs/heads/main'
        && $noNewlineSidebandAllResponse->wantedRefs()[0]->object === $fixture['objects']['main']
        && $noNewlineSidebandAllResponse->packData() === $fixture['packData']
        && $noNewlineSidebandAllResponse->progressMessages() === ['Counting objects: 100% (1/1)']
        && $noNewlineSidebandAllResponse->remoteProgress()[0]->percent === 100,
    'noNewlineSidebandAllPackTrailer' => bin2hex(substr($noNewlineSidebandAllResponse->packData(), -20)),
    'invalidUtf8ProtocolLineRejected' => $invalidUtf8ProtocolLineError === 'fetch response: invalid UTF-8 protocol line',
    'invalidUtf8ProtocolLineError' => $invalidUtf8ProtocolLineError,
    'binarySidebandPayloadsPreserved' => $binarySidebandResponse->packData() === $fixture['binaryPackData']
        && $binarySidebandResponse->progressMessages() === ["remote: byte \xFF progress"]
        && $binarySidebandResponse->errorMessages() === ["remote: byte \xFE warning"],
    'binarySidebandPackSuffix' => bin2hex(substr($binarySidebandResponse->packData(), -15)),
    'leadingStopRejected' => $leadingStopError === 'fetch response: could not read message headline',
    'leadingStopError' => $leadingStopError,
    'leadingStopMessages' => $leadingStopMessages,
    'smartHttpUploadPackParsed' => $smartHttpUploadPackResponse->packData() === $fixture['packData']
        && count($smartHttpUploadPackResponse->acknowledgements()) === 3
        && $smartHttpUploadPackResponse->progressMessages() === ['Counting objects: 100% (1/1)' . "\r" . 'Counting objects: 100% (1/1), done.'],
    'smartHttpUploadPackTrailer' => bin2hex(substr($smartHttpUploadPackResponse->packData(), -20)),
];
