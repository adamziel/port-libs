<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class RequestServingResult
{
    public const SOURCE_TEMPORARY = 'temporary';
    public const SOURCE_FINAL = 'final';
    public const SOURCE_NONE = 'none';

    public function __construct(
        public readonly Response $response,
        public readonly string $source = self::SOURCE_NONE,
        public readonly string $reason = '',
    ) {
        if (!in_array($this->source, [
            self::SOURCE_TEMPORARY,
            self::SOURCE_FINAL,
            self::SOURCE_NONE,
        ], true)) {
            throw new \InvalidArgumentException('Unknown request serving source');
        }
    }

    public function successful(): bool
    {
        return $this->response->code === Response::CODE_NO_ERROR;
    }

    /**
     * @return array{code:int, bytes:int, source:string, reason:string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->response->code,
            'bytes' => strlen($this->response->data),
            'source' => $this->source,
            'reason' => $this->reason,
        ];
    }
}
