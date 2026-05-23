<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderScanApiRequestQueue
{
    public const HTTP_ACCEPTED = 202;
    public const HTTP_TOO_MANY_REQUESTS = 429;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $pending = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $running = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $completed = [];

    /**
     * @var array<string, int>
     */
    private array $activeKeys = [];

    private int $nextId = 1;

    public function __construct(
        private readonly FolderScanApiCoordinator $coordinator,
        private readonly int $maxPending = 32,
        private readonly int $maxCompleted = 32,
    ) {
        if ($this->maxPending < 1) {
            throw new \InvalidArgumentException('Folder scan request queue pending limit must be positive');
        }
        if ($this->maxCompleted < 0) {
            throw new \InvalidArgumentException('Folder scan request queue completed limit must not be negative');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function enqueue(array $payload, ?int $now = null): FolderScanApiResponse
    {
        $now = self::clock($now);

        try {
            $request = $this->coordinator->canonicalDbScanRequest($payload);
        } catch (\InvalidArgumentException $exception) {
            return new FolderScanApiResponse(FolderScanApiCoordinator::HTTP_BAD_REQUEST, [
                'ok' => false,
                'status' => 'rejected',
                'error' => 'invalid_request',
                'message' => $exception->getMessage(),
                'queue' => $this->toRestStatus(),
            ]);
        }

        $key = self::requestKey($request);
        if (isset($this->activeKeys[$key])) {
            $id = $this->activeKeys[$key];
            $record = $this->touchDuplicate($id, $now);

            return new FolderScanApiResponse(FolderScanApiCoordinator::HTTP_OK, [
                'ok' => true,
                'status' => 'coalesced',
                'requestId' => $id,
                'request' => self::recordStatus($record),
                'queue' => $this->toRestStatus(),
            ]);
        }

        if (count($this->pending) >= $this->maxPending) {
            return new FolderScanApiResponse(self::HTTP_TOO_MANY_REQUESTS, [
                'ok' => false,
                'status' => 'rejected',
                'error' => 'queue_full',
                'message' => 'folder scan request queue is full',
                'queue' => $this->toRestStatus(),
            ]);
        }

        $id = $this->nextId++;
        $record = [
            'id' => $id,
            'key' => $key,
            'state' => 'pending',
            'request' => $request,
            'payload' => self::payloadFromRequest($request),
            'enqueuedAt' => $now,
            'lastEnqueuedAt' => $now,
            'duplicateCount' => 0,
            'startedAt' => null,
            'completedAt' => null,
            'response' => null,
        ];

        $this->pending[$id] = $record;
        $this->activeKeys[$key] = $id;

        return new FolderScanApiResponse(self::HTTP_ACCEPTED, [
            'ok' => true,
            'status' => 'queued',
            'requestId' => $id,
            'request' => self::recordStatus($record),
            'queue' => $this->toRestStatus(),
        ]);
    }

    /**
     * Moves the oldest pending request into running state without invoking the
     * scanner yet, matching Syncthing's queued scan notification boundary.
     *
     * @return null|array<string, mixed>
     */
    public function startNext(?int $now = null): ?array
    {
        $now = self::clock($now);
        if ($this->pending === []) {
            return null;
        }

        $id = array_key_first($this->pending);
        $record = $this->pending[$id];
        unset($this->pending[$id]);

        $record['state'] = 'running';
        $record['startedAt'] = $now;
        $this->running[$id] = $record;

        return self::recordStatus($record);
    }

    public function finishRunning(int $id, ?int $now = null): FolderScanApiResponse
    {
        $now = self::clock($now);
        if (!isset($this->running[$id])) {
            throw new \InvalidArgumentException('Folder scan request is not running: ' . $id);
        }

        $record = $this->running[$id];
        unset($this->running[$id]);

        try {
            $scanResponse = $this->coordinator->postDbScan($record['payload'], $now);
        } catch (\Throwable $throwable) {
            $scanResponse = new FolderScanApiResponse(FolderScanApiCoordinator::HTTP_INTERNAL_ERROR, [
                'ok' => false,
                'status' => 'error',
                'error' => 'scan_failed',
                'message' => $throwable->getMessage(),
            ]);
        }

        $record['state'] = 'completed';
        $record['completedAt'] = $now;
        $record['response'] = $scanResponse->toArray();
        unset($this->activeKeys[$record['key']]);

        if ($this->maxCompleted > 0) {
            $this->completed[$id] = $record;
            while (count($this->completed) > $this->maxCompleted) {
                array_shift($this->completed);
            }
        }

        return new FolderScanApiResponse($scanResponse->statusCode, [
            'ok' => $scanResponse->successful(),
            'status' => 'completed',
            'requestId' => $id,
            'request' => self::recordStatus($record, includeResponse: true),
            'response' => $scanResponse->toArray(),
            'queue' => $this->toRestStatus(),
        ]);
    }

    public function runNext(?int $now = null): ?FolderScanApiResponse
    {
        $started = $this->startNext($now);
        if ($started === null) {
            return null;
        }

        return $this->finishRunning($started['id'], $now);
    }

    /**
     * @return list<FolderScanApiResponse>
     */
    public function runAll(?int $now = null): array
    {
        $responses = [];
        while (($response = $this->runNext($now)) !== null) {
            $responses[] = $response;
        }

        return $responses;
    }

    /**
     * @return array{pendingCount:int, runningCount:int, completedCount:int, pending:list<array<string, mixed>>, running:list<array<string, mixed>>, completed:list<array<string, mixed>>}
     */
    public function toRestStatus(): array
    {
        return [
            'pendingCount' => count($this->pending),
            'runningCount' => count($this->running),
            'completedCount' => count($this->completed),
            'pending' => array_values(array_map(self::recordStatus(...), $this->pending)),
            'running' => array_values(array_map(self::recordStatus(...), $this->running)),
            'completed' => array_values(array_map(
                static fn (array $record): array => self::recordStatus($record, includeResponse: true),
                $this->completed,
            )),
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private static function recordStatus(array $record, bool $includeResponse = false): array
    {
        $status = [
            'id' => $record['id'],
            'state' => $record['state'],
            'request' => [
                'allFolders' => $record['request']['allFolders'],
                'folders' => $record['request']['folders'],
                'hashBlocks' => $record['request']['hashBlocks'],
                'blockSize' => $record['request']['blockSize'],
                'nextSeconds' => $record['request']['nextSeconds'],
            ],
            'enqueuedAt' => $record['enqueuedAt'],
            'lastEnqueuedAt' => $record['lastEnqueuedAt'],
            'duplicateCount' => $record['duplicateCount'],
            'startedAt' => $record['startedAt'],
            'completedAt' => $record['completedAt'],
        ];

        if ($includeResponse && is_array($record['response'])) {
            $status['responseStatusCode'] = $record['response']['statusCode'];
        }

        return $status;
    }

    /**
     * @param array{allFolders:bool, folders:array<string, list<string>>, hashBlocks:bool, blockSize:?int, nextSeconds:?int} $request
     */
    private static function requestKey(array $request): string
    {
        $folders = $request['folders'];
        ksort($folders, SORT_STRING);
        foreach ($folders as $folderId => $subdirs) {
            $subdirs = array_values(array_unique($subdirs));
            sort($subdirs, SORT_STRING);
            $folders[$folderId] = $subdirs;
        }

        return hash('sha256', json_encode([
            'allFolders' => $request['allFolders'],
            'folders' => $folders,
            'hashBlocks' => $request['hashBlocks'],
            'blockSize' => $request['blockSize'],
            'nextSeconds' => $request['nextSeconds'],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * @param array{allFolders:bool, folders:array<string, list<string>>, hashBlocks:bool, blockSize:?int, nextSeconds:?int} $request
     * @return array<string, mixed>
     */
    private static function payloadFromRequest(array $request): array
    {
        $payload = [
            'hashBlocks' => $request['hashBlocks'],
            'blockSize' => $request['blockSize'],
        ];

        if (!$request['allFolders']) {
            $payload['folders'] = $request['folders'];
        }
        if ($request['nextSeconds'] !== null) {
            $payload['next'] = $request['nextSeconds'];
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function touchDuplicate(int $id, int $now): array
    {
        if (isset($this->pending[$id])) {
            $this->pending[$id]['lastEnqueuedAt'] = $now;
            ++$this->pending[$id]['duplicateCount'];

            return $this->pending[$id];
        }

        if (isset($this->running[$id])) {
            $this->running[$id]['lastEnqueuedAt'] = $now;
            ++$this->running[$id]['duplicateCount'];

            return $this->running[$id];
        }

        throw new \RuntimeException('Folder scan request queue active key points at a missing request');
    }

    private static function clock(?int $now): int
    {
        $now ??= time();
        if ($now < 0) {
            throw new \InvalidArgumentException('Folder scan request queue clock must not be negative');
        }

        return $now;
    }
}
