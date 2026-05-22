<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class RequestExchange
{
    /**
     * @var array<int, Request>
     */
    private array $awaiting = [];

    private bool $closed = false;

    public function __construct(private int $nextRequestId = 0)
    {
        if ($this->nextRequestId < 0) {
            throw new \InvalidArgumentException('Initial request ID must not be negative');
        }
    }

    public function queue(Request $request): Request
    {
        $this->assertOpen();

        $id = $this->nextRequestId;
        if (isset($this->awaiting[$id])) {
            throw new \LogicException('request id already awaiting');
        }

        $queued = $this->copyWithId($request, $id);
        $this->awaiting[$id] = $queued;
        $this->nextRequestId++;

        return $queued;
    }

    public function encodeQueuedRequest(Request $request, int $compressionMode = Device::COMPRESSION_NEVER): string
    {
        return BepWire::encodeRequestMessage($this->queue($request), $compressionMode);
    }

    public function complete(Response $response): ?RequestExchangeResult
    {
        if (!isset($this->awaiting[$response->id])) {
            return null;
        }

        unset($this->awaiting[$response->id]);

        return RequestExchangeResult::fromResponse($response);
    }

    /**
     * @return list<RequestExchangeResult>
     */
    public function close(): array
    {
        if ($this->closed) {
            return [];
        }

        $this->closed = true;
        $ids = array_keys($this->awaiting);
        sort($ids, SORT_NUMERIC);
        $this->awaiting = [];

        $results = [];
        foreach ($ids as $id) {
            $results[] = RequestExchangeResult::closed((int) $id);
        }

        return $results;
    }

    /**
     * @return list<int>
     */
    public function pendingIds(): array
    {
        $ids = array_keys($this->awaiting);
        sort($ids, SORT_NUMERIC);

        return array_map('intval', $ids);
    }

    public function nextRequestId(): int
    {
        return $this->nextRequestId;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    private function assertOpen(): void
    {
        if ($this->closed) {
            throw new \RuntimeException(Response::ERROR_CLOSED);
        }
    }

    private function copyWithId(Request $request, int $id): Request
    {
        return new Request(
            id: $id,
            folder: $request->folder,
            name: $request->name,
            offset: $request->offset,
            size: $request->size,
            hashHex: $request->hashHex,
            fromTemporary: $request->fromTemporary,
            blockNo: $request->blockNo,
        );
    }
}
