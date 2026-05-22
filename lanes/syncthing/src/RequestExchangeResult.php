<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class RequestExchangeResult
{
    public function __construct(
        public readonly int $id,
        public readonly string $data = '',
        public readonly int $code = Response::CODE_NO_ERROR,
        public readonly ?string $error = null,
    ) {
        if ($this->id < 0) {
            throw new \InvalidArgumentException('Request result ID must not be negative');
        }
        if (!in_array($this->code, [
            Response::CODE_NO_ERROR,
            Response::CODE_GENERIC,
            Response::CODE_NO_SUCH_FILE,
            Response::CODE_INVALID_FILE,
        ], true)) {
            throw new \InvalidArgumentException('Unknown response error code');
        }
    }

    public static function fromResponse(Response $response): self
    {
        return new self(
            id: $response->id,
            data: $response->data,
            code: $response->code,
            error: $response->error(),
        );
    }

    public static function closed(int $id): self
    {
        return new self(
            id: $id,
            code: Response::CODE_GENERIC,
            error: Response::ERROR_CLOSED,
        );
    }

    public function successful(): bool
    {
        return $this->error === null;
    }

    /**
     * @return array{id:int, code:int, dataBytes:int, error:?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'dataBytes' => strlen($this->data),
            'error' => $this->error,
        ];
    }
}
