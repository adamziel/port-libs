<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class BepSession
{
    public const EVENT_CLUSTER_CONFIG = 'cluster-config';
    public const EVENT_INDEX = 'index';
    public const EVENT_INDEX_UPDATE = 'index-update';
    public const EVENT_REQUEST = 'request';
    public const EVENT_RESPONSE = 'response';
    public const EVENT_DOWNLOAD_PROGRESS = 'download-progress';
    public const EVENT_PING = 'ping';
    public const EVENT_CLOSE = 'close';
    public const EVENT_IGNORED_UNKNOWN = 'ignored-unknown';
    public const EVENT_PROTOCOL_ERROR = 'protocol-error';
    public const EVENT_HANDLER_ERROR = 'handler-error';
    public const EVENT_CLOSED = 'closed';

    private bool $sentClusterConfig = false;
    private bool $receivedClusterConfig = false;
    private bool $closed = false;
    private ?string $closedError = null;

    private RequestExchange $exchange;
    private BepSessionHandlers $handlers;

    public function __construct(
        private readonly int $compressionMode = Device::COMPRESSION_NEVER,
        ?RequestExchange $exchange = null,
        ?BepSessionHandlers $handlers = null,
    ) {
        if (!in_array($this->compressionMode, [
            Device::COMPRESSION_METADATA,
            Device::COMPRESSION_NEVER,
            Device::COMPRESSION_ALWAYS,
        ], true)) {
            throw new \InvalidArgumentException('Unknown Syncthing compression mode');
        }

        $this->exchange = $exchange ?? new RequestExchange();
        $this->handlers = $handlers ?? new BepSessionHandlers();
    }

    public function hasSentClusterConfig(): bool
    {
        return $this->sentClusterConfig;
    }

    public function hasReceivedClusterConfig(): bool
    {
        return $this->receivedClusterConfig;
    }

    public function canSendPostClusterMessage(): bool
    {
        return $this->sentClusterConfig && !$this->closed;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function closedError(): ?string
    {
        return $this->closedError;
    }

    /**
     * @return list<int>
     */
    public function pendingRequestIds(): array
    {
        return $this->exchange->pendingIds();
    }

    /**
     * @param callable(Request): (RequestServingResult|Response|string|null) $handler
     */
    public function onRequest(callable $handler): self
    {
        $this->handlers = $this->handlers->withRequestHandler($handler);

        return $this;
    }

    /**
     * @param callable(Index): mixed $handler
     */
    public function onIndex(callable $handler): self
    {
        $this->handlers = $this->handlers->withIndexHandler($handler);

        return $this;
    }

    /**
     * @param callable(IndexUpdate): mixed $handler
     */
    public function onIndexUpdate(callable $handler): self
    {
        $this->handlers = $this->handlers->withIndexUpdateHandler($handler);

        return $this;
    }

    /**
     * @param callable(DownloadProgress): mixed $handler
     */
    public function onDownloadProgress(callable $handler): self
    {
        $this->handlers = $this->handlers->withDownloadProgressHandler($handler);

        return $this;
    }

    public function sendClusterConfig(ClusterConfig $config): ?string
    {
        if ($this->closed) {
            return null;
        }

        $this->sentClusterConfig = true;

        return BepWire::encodeClusterConfigMessage($config, $this->compressionMode);
    }

    public function sendPing(): ?string
    {
        return $this->sendPostClusterFrame(
            fn (): string => BepWire::encodePingMessage($this->compressionMode),
        );
    }

    public function sendIndex(Index $index): ?string
    {
        return $this->sendPostClusterFrame(
            fn (): string => BepWire::encodeIndexMessage($index->normalizedForWire(), $this->compressionMode),
        );
    }

    public function sendIndexUpdate(IndexUpdate $indexUpdate): ?string
    {
        return $this->sendPostClusterFrame(
            fn (): string => BepWire::encodeIndexUpdateMessage($indexUpdate->normalizedForWire(), $this->compressionMode),
        );
    }

    public function sendDownloadProgress(DownloadProgress $progress): ?string
    {
        return $this->sendPostClusterFrame(
            fn (): string => BepWire::encodeDownloadProgressMessage($progress, $this->compressionMode),
        );
    }

    public function sendRequest(Request $request): ?string
    {
        return $this->sendPostClusterFrame(
            fn (): string => $this->exchange->encodeQueuedRequest(
                $request->normalizedForWire(),
                $this->compressionMode,
            ),
        );
    }

    public function sendClose(string $reason): ?string
    {
        if ($this->closed) {
            return null;
        }

        $frame = BepWire::encodeCloseMessage(new Close($reason), $this->compressionMode);
        $this->markClosed($reason);

        return $frame;
    }

    /**
     * @param null|BepSessionHandlers|callable(Request): (RequestServingResult|Response|string|null) $handlers
     */
    public function receiveFrame(string $frame, null|BepSessionHandlers|callable $handlers = null): BepSessionEvent
    {
        if ($this->closed) {
            return new BepSessionEvent(
                type: self::EVENT_CLOSED,
                messageType: -1,
                error: $this->closedError,
            );
        }

        $activeHandlers = $this->resolveHandlers($handlers);
        $decoded = BepWire::decodeMessageFrame($frame);
        $messageType = $decoded['type'];
        if ($messageType < BepWire::MESSAGE_TYPE_CLUSTER_CONFIG || $messageType > BepWire::MESSAGE_TYPE_CLOSE) {
            return new BepSessionEvent(
                type: self::EVENT_IGNORED_UNKNOWN,
                messageType: $messageType,
            );
        }

        return match ($messageType) {
            BepWire::MESSAGE_TYPE_CLUSTER_CONFIG => $this->receiveClusterConfig($decoded['payload']),
            BepWire::MESSAGE_TYPE_INDEX => $this->receiveIndex($decoded['payload'], $activeHandlers),
            BepWire::MESSAGE_TYPE_INDEX_UPDATE => $this->receiveIndexUpdate($decoded['payload'], $activeHandlers),
            BepWire::MESSAGE_TYPE_REQUEST => $this->receiveRequest($decoded['payload'], $activeHandlers->requestHandler()),
            BepWire::MESSAGE_TYPE_RESPONSE => $this->receiveResponse($decoded['payload']),
            BepWire::MESSAGE_TYPE_DOWNLOAD_PROGRESS => $this->receiveDownloadProgress($decoded['payload'], $activeHandlers),
            BepWire::MESSAGE_TYPE_PING => $this->receivePing(),
            BepWire::MESSAGE_TYPE_CLOSE => $this->receiveClose($decoded['payload']),
        };
    }

    private function receiveClusterConfig(string $payload): BepSessionEvent
    {
        $config = BepWire::decodeClusterConfigPayload($payload);
        $this->receivedClusterConfig = true;

        return new BepSessionEvent(
            type: self::EVENT_CLUSTER_CONFIG,
            messageType: BepWire::MESSAGE_TYPE_CLUSTER_CONFIG,
            message: $config,
        );
    }

    private function receiveIndex(string $payload, BepSessionHandlers $handlers): BepSessionEvent
    {
        $index = BepWire::decodeIndexPayload($payload);
        $context = self::messageContext(BepWire::MESSAGE_TYPE_INDEX, $index);
        if (!$this->receivedClusterConfig) {
            return $this->protocolError(BepWire::MESSAGE_TYPE_INDEX, 'invalid state 0 for ' . $context);
        }

        try {
            $index->checkConsistency();
        } catch (\Throwable $throwable) {
            return $this->protocolError(BepWire::MESSAGE_TYPE_INDEX, $throwable->getMessage() . ' in ' . $context);
        }

        return $this->dispatchModelHandler(new BepSessionEvent(
            type: self::EVENT_INDEX,
            messageType: BepWire::MESSAGE_TYPE_INDEX,
            message: $index,
        ), $handlers->indexHandler(), $context);
    }

    private function receiveIndexUpdate(string $payload, BepSessionHandlers $handlers): BepSessionEvent
    {
        $indexUpdate = BepWire::decodeIndexUpdatePayload($payload);
        $context = self::messageContext(BepWire::MESSAGE_TYPE_INDEX_UPDATE, $indexUpdate);
        if (!$this->receivedClusterConfig) {
            return $this->protocolError(BepWire::MESSAGE_TYPE_INDEX_UPDATE, 'invalid state 0 for ' . $context);
        }

        try {
            $indexUpdate->checkConsistency();
        } catch (\Throwable $throwable) {
            return $this->protocolError(BepWire::MESSAGE_TYPE_INDEX_UPDATE, $throwable->getMessage() . ' in ' . $context);
        }

        return $this->dispatchModelHandler(new BepSessionEvent(
            type: self::EVENT_INDEX_UPDATE,
            messageType: BepWire::MESSAGE_TYPE_INDEX_UPDATE,
            message: $indexUpdate,
        ), $handlers->indexUpdateHandler(), $context);
    }

    /**
     * @param null|callable(Request): (RequestServingResult|Response|string|null) $requestHandler
     */
    private function receiveRequest(string $payload, ?callable $requestHandler): BepSessionEvent
    {
        $request = BepWire::decodeRequestPayload($payload);
        $context = self::messageContext(BepWire::MESSAGE_TYPE_REQUEST, $request);
        if (!$this->receivedClusterConfig) {
            return $this->protocolError(BepWire::MESSAGE_TYPE_REQUEST, 'invalid state 0 for ' . $context);
        }

        try {
            ProtocolValidation::checkRequest($request);
        } catch (\Throwable $throwable) {
            return $this->protocolError(BepWire::MESSAGE_TYPE_REQUEST, $throwable->getMessage() . ' in ' . $context);
        }

        $response = null;
        $frames = [];
        if ($requestHandler !== null) {
            $response = $this->responseFromHandler($request, $requestHandler);
            $frames[] = BepWire::encodeResponseMessage($response, $this->compressionMode);
        }

        return new BepSessionEvent(
            type: self::EVENT_REQUEST,
            messageType: BepWire::MESSAGE_TYPE_REQUEST,
            message: $request,
            response: $response,
            outboundFrames: $frames,
        );
    }

    private function receiveResponse(string $payload): BepSessionEvent
    {
        $response = BepWire::decodeResponsePayload($payload);
        $context = self::messageContext(BepWire::MESSAGE_TYPE_RESPONSE, $response);
        if (!$this->receivedClusterConfig) {
            return $this->protocolError(BepWire::MESSAGE_TYPE_RESPONSE, 'invalid state 0 for ' . $context);
        }

        return new BepSessionEvent(
            type: self::EVENT_RESPONSE,
            messageType: BepWire::MESSAGE_TYPE_RESPONSE,
            message: $response,
            requestResult: $this->exchange->complete($response),
        );
    }

    private function receiveDownloadProgress(string $payload, BepSessionHandlers $handlers): BepSessionEvent
    {
        $progress = BepWire::decodeDownloadProgressPayload($payload);
        $context = self::messageContext(BepWire::MESSAGE_TYPE_DOWNLOAD_PROGRESS, $progress);
        if (!$this->receivedClusterConfig) {
            return $this->protocolError(BepWire::MESSAGE_TYPE_DOWNLOAD_PROGRESS, 'invalid state 0 for ' . $context);
        }

        return $this->dispatchModelHandler(new BepSessionEvent(
            type: self::EVENT_DOWNLOAD_PROGRESS,
            messageType: BepWire::MESSAGE_TYPE_DOWNLOAD_PROGRESS,
            message: $progress,
        ), $handlers->downloadProgressHandler(), $context);
    }

    private function receivePing(): BepSessionEvent
    {
        if (!$this->receivedClusterConfig) {
            return $this->protocolError(BepWire::MESSAGE_TYPE_PING, 'invalid state 0 for ping');
        }

        return new BepSessionEvent(
            type: self::EVENT_PING,
            messageType: BepWire::MESSAGE_TYPE_PING,
        );
    }

    private function receiveClose(string $payload): BepSessionEvent
    {
        $close = BepWire::decodeClosePayload($payload);
        $error = 'closed by remote: ' . $close->reason;

        return new BepSessionEvent(
            type: self::EVENT_CLOSE,
            messageType: BepWire::MESSAGE_TYPE_CLOSE,
            message: $close,
            closedResults: $this->markClosed($error),
            error: $error,
        );
    }

    private function sendPostClusterFrame(callable $encoder): ?string
    {
        if (!$this->canSendPostClusterMessage()) {
            return null;
        }

        return $encoder();
    }

    private function resolveHandlers(null|BepSessionHandlers|callable $handlers): BepSessionHandlers
    {
        if ($handlers === null) {
            return $this->handlers;
        }
        if ($handlers instanceof BepSessionHandlers) {
            return $this->handlers->mergedWith($handlers);
        }

        return $this->handlers->withRequestHandler($handlers);
    }

    private function dispatchModelHandler(BepSessionEvent $event, ?\Closure $handler, string $context): BepSessionEvent
    {
        if ($handler === null) {
            return $event;
        }

        try {
            return $event->withHandlerResult($handler($event->message));
        } catch (\Throwable $throwable) {
            return $this->handlerError($event->messageType, $event->message, $context, $throwable);
        }
    }

    /**
     * @param callable(Request): (RequestServingResult|Response|string|null) $requestHandler
     */
    private function responseFromHandler(Request $request, callable $requestHandler): Response
    {
        try {
            $served = $requestHandler($request);
            if ($served instanceof RequestServingResult) {
                return $this->withRequestId($request, $served->response);
            }
            if ($served instanceof Response) {
                return $this->withRequestId($request, $served);
            }
            if (is_string($served)) {
                return new Response($request->id, $served);
            }
            if ($served === null) {
                return new Response($request->id);
            }

            throw new \UnexpectedValueException('Unsupported request handler result');
        } catch (\Throwable $throwable) {
            return Response::errorResponse($request->id, $throwable);
        }
    }

    private function withRequestId(Request $request, Response $response): Response
    {
        if ($response->id === $request->id) {
            return $response;
        }

        return new Response($request->id, $response->data, $response->code);
    }

    private function handlerError(int $messageType, mixed $message, string $context, \Throwable $throwable): BepSessionEvent
    {
        $reason = $throwable->getMessage() === '' ? $throwable::class : $throwable->getMessage();
        $error = 'handling ' . $context . ': ' . $reason;

        return new BepSessionEvent(
            type: self::EVENT_HANDLER_ERROR,
            messageType: $messageType,
            message: $message,
            closedResults: $this->markClosed($error),
            error: $error,
        );
    }

    private function protocolError(int $messageType, string $reason): BepSessionEvent
    {
        $error = 'protocol error: ' . $reason;

        return new BepSessionEvent(
            type: self::EVENT_PROTOCOL_ERROR,
            messageType: $messageType,
            closedResults: $this->markClosed($error),
            error: $error,
        );
    }

    /**
     * @return list<RequestExchangeResult>
     */
    private function markClosed(string $error): array
    {
        if ($this->closed) {
            return [];
        }

        $this->closed = true;
        $this->closedError = $error;

        return $this->exchange->close();
    }

    private static function messageContext(int $messageType, mixed $message): string
    {
        return match ($messageType) {
            BepWire::MESSAGE_TYPE_CLUSTER_CONFIG => 'cluster-config',
            BepWire::MESSAGE_TYPE_INDEX => 'index for ' . ($message instanceof Index ? $message->folder : ''),
            BepWire::MESSAGE_TYPE_INDEX_UPDATE => 'index-update for ' . ($message instanceof IndexUpdate ? $message->folder : ''),
            BepWire::MESSAGE_TYPE_REQUEST => $message instanceof Request
                ? sprintf('request for "%s" in %s', $message->name, $message->folder)
                : 'request',
            BepWire::MESSAGE_TYPE_RESPONSE => 'response',
            BepWire::MESSAGE_TYPE_DOWNLOAD_PROGRESS => $message instanceof DownloadProgress
                ? 'download-progress for ' . $message->folder
                : 'download-progress',
            BepWire::MESSAGE_TYPE_PING => 'ping',
            BepWire::MESSAGE_TYPE_CLOSE => 'close',
            default => 'unknown',
        };
    }
}
