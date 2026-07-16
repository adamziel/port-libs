<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class BepSessionEvent
{
    /**
     * @param list<string> $outboundFrames
     * @param list<RequestExchangeResult> $closedResults
     */
    public function __construct(
        public readonly string $type,
        public readonly int $messageType,
        public readonly mixed $message = null,
        public readonly ?RequestExchangeResult $requestResult = null,
        public readonly ?Response $response = null,
        public readonly array $outboundFrames = [],
        public readonly array $closedResults = [],
        public readonly ?string $error = null,
        public readonly mixed $handlerResult = null,
    ) {
        if ($this->type === '') {
            throw new \InvalidArgumentException('Session event type must not be empty');
        }
        foreach ($this->outboundFrames as $frame) {
            if (!is_string($frame)) {
                throw new \InvalidArgumentException('Outbound frames must be strings');
            }
        }
        foreach ($this->closedResults as $result) {
            if (!$result instanceof RequestExchangeResult) {
                throw new \InvalidArgumentException('Closed results must be request exchange results');
            }
        }
    }

    public function closed(): bool
    {
        return $this->type === BepSession::EVENT_CLOSE
            || $this->type === BepSession::EVENT_PROTOCOL_ERROR
            || $this->type === BepSession::EVENT_HANDLER_ERROR
            || $this->type === BepSession::EVENT_CLOSED;
    }

    public function withHandlerResult(mixed $handlerResult): self
    {
        return new self(
            type: $this->type,
            messageType: $this->messageType,
            message: $this->message,
            requestResult: $this->requestResult,
            response: $this->response,
            outboundFrames: $this->outboundFrames,
            closedResults: $this->closedResults,
            error: $this->error,
            handlerResult: $handlerResult,
        );
    }

    /**
     * @return array{type:string, messageType:int, outboundFrames:int, closedResults:int, error:?string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'messageType' => $this->messageType,
            'outboundFrames' => count($this->outboundFrames),
            'closedResults' => count($this->closedResults),
            'error' => $this->error,
        ];
    }
}
