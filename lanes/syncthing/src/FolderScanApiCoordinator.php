<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderScanApiCoordinator
{
    public const HTTP_OK = 200;
    public const HTTP_MULTI_STATUS = 207;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_CONFLICT = 409;
    public const HTTP_INTERNAL_ERROR = 500;

    public function __construct(
        private readonly FolderScanScheduler $scheduler,
        private readonly ?IgnoreMatcher $ignoreMatcher = null,
        private readonly bool $defaultHashBlocks = false,
        private readonly ?int $defaultBlockSize = null,
    ) {
        if ($this->defaultBlockSize !== null && $this->defaultBlockSize <= 0) {
            throw new \InvalidArgumentException('Folder scan API default block size must be positive');
        }
    }

    public function scheduler(): FolderScanScheduler
    {
        return $this->scheduler;
    }

    /**
     * Handles a Syncthing-style POST /rest/db/scan request represented as a
     * WordPress REST payload.
     *
     * Supported payload forms:
     * - [] scans all registered folders.
     * - ["folder" => "id", "sub" => ["path", "other/path"]] scans one folder.
     * - ["folders" => ["id" => ["path"], "other" => []]] scans selected folders.
     *
     * @param array<string, mixed> $payload
     */
    public function postDbScan(array $payload = [], ?int $now = null): FolderScanApiResponse
    {
        try {
            $request = $this->canonicalDbScanRequest($payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse(
                self::HTTP_BAD_REQUEST,
                'invalid_request',
                $exception->getMessage(),
            );
        }

        $missing = $this->missingFolders(array_keys($request['folders']));
        if ($missing !== []) {
            return $this->errorResponse(
                self::HTTP_NOT_FOUND,
                'folder_missing',
                'folder missing',
                ['folders' => $missing],
            );
        }

        try {
            $result = $request['allFolders']
                ? $this->scheduler->scanFolders(
                    [],
                    $this->ignoreMatcher,
                    $request['hashBlocks'],
                    $request['blockSize'],
                    now: $now,
                )
                : $this->scanRequestedFolders($request['folders'], $request['hashBlocks'], $request['blockSize'], $now);
        } catch (\Throwable $throwable) {
            return $this->errorResponse(
                self::HTTP_INTERNAL_ERROR,
                'scan_failed',
                'folder scan failed',
                ['detail' => $throwable->getMessage()],
            );
        }

        $scheduledScans = $this->applyNextScanDelay($result, $request, $now);

        return $this->resultResponse($result, $request, $scheduledScans);
    }

    /**
     * @param array<string, list<string>> $subdirsByFolder
     */
    private function scanRequestedFolders(
        array $subdirsByFolder,
        bool $hashBlocks,
        ?int $blockSize,
        ?int $now,
    ): FolderScanSchedulerResult {
        $snapshots = [];
        $errors = [];

        foreach ($subdirsByFolder as $folderId => $subdirs) {
            try {
                $snapshots[$folderId] = $this->scheduler->scanFolderSubdirs(
                    $folderId,
                    $subdirs,
                    $this->ignoreMatcher,
                    $hashBlocks,
                    $blockSize,
                    now: $now,
                );
            } catch (\Throwable $throwable) {
                $errors[$folderId] = $throwable;
            }
        }

        return new FolderScanSchedulerResult($snapshots, $errors);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{allFolders:bool, folders:array<string, list<string>>, hashBlocks:bool, blockSize:?int, nextSeconds:?int}
     */
    public function canonicalDbScanRequest(array $payload): array
    {
        if (array_key_exists('folder', $payload) && array_key_exists('folders', $payload)) {
            throw new \InvalidArgumentException('Folder scan request must not mix folder and folders');
        }

        $hashBlocks = $this->parseBool($payload['hashBlocks'] ?? null, $this->defaultHashBlocks, 'hashBlocks');
        $blockSize = $this->parseBlockSize($payload['blockSize'] ?? null);
        $nextSeconds = $this->parseNextSeconds($this->nextPayload($payload));

        if (array_key_exists('folder', $payload)) {
            if (!is_string($payload['folder'])) {
                throw new \InvalidArgumentException('Folder scan request folder must be a string');
            }

            $folder = $payload['folder'];
            if ($folder === '') {
                if ($this->subdirPayloadPresent($payload)) {
                    throw new \InvalidArgumentException('Folder scan subdirs require a non-empty folder');
                }

                return [
                    'allFolders' => true,
                    'folders' => [],
                    'hashBlocks' => $hashBlocks,
                    'blockSize' => $blockSize,
                    'nextSeconds' => null,
                ];
            }

            return [
                'allFolders' => false,
                'folders' => [$folder => $this->parseSubdirs($this->subdirPayload($payload))],
                'hashBlocks' => $hashBlocks,
                'blockSize' => $blockSize,
                'nextSeconds' => $nextSeconds,
            ];
        }

        if (array_key_exists('folders', $payload)) {
            return [
                'allFolders' => false,
                'folders' => $this->parseFolderMap($payload['folders']),
                'hashBlocks' => $hashBlocks,
                'blockSize' => $blockSize,
                'nextSeconds' => $nextSeconds,
            ];
        }

        if ($this->subdirPayloadPresent($payload)) {
            throw new \InvalidArgumentException('Folder scan subdirs require a folder');
        }

        return [
            'allFolders' => true,
            'folders' => [],
            'hashBlocks' => $hashBlocks,
            'blockSize' => $blockSize,
            'nextSeconds' => null,
        ];
    }

    private function parseBool(mixed $value, bool $default, string $label): bool
    {
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }
        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        throw new \InvalidArgumentException('Folder scan request ' . $label . ' must be boolean');
    }

    private function parseBlockSize(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return $this->defaultBlockSize;
        }
        if (is_int($value)) {
            $blockSize = $value;
        } elseif (is_string($value) && ctype_digit($value)) {
            $blockSize = (int) $value;
        } else {
            throw new \InvalidArgumentException('Folder scan request blockSize must be a positive integer');
        }

        if ($blockSize <= 0) {
            throw new \InvalidArgumentException('Folder scan request blockSize must be positive');
        }

        return $blockSize;
    }

    private function parseNextSeconds(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[+-]?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function parseFolderMap(mixed $folders): array
    {
        if (!is_array($folders) || $folders === []) {
            throw new \InvalidArgumentException('Folder scan request folders must be a non-empty list or map');
        }

        $parsed = [];
        if (array_is_list($folders)) {
            foreach ($folders as $folderId) {
                if (!is_string($folderId) || $folderId === '') {
                    throw new \InvalidArgumentException('Folder scan request folder IDs must be non-empty strings');
                }
                $parsed[$folderId] = [];
            }

            return $parsed;
        }

        foreach ($folders as $folderId => $subdirs) {
            $folderId = (string) $folderId;
            if ($folderId === '') {
                throw new \InvalidArgumentException('Folder scan request folder IDs must be non-empty strings');
            }

            $parsed[$folderId] = $this->parseSubdirs($subdirs);
        }

        return $parsed;
    }

    private function subdirPayloadPresent(array $payload): bool
    {
        return array_key_exists('sub', $payload)
            || array_key_exists('subs', $payload)
            || array_key_exists('subdirs', $payload);
    }

    private function subdirPayload(array $payload): mixed
    {
        $keys = array_values(array_filter(
            ['sub', 'subs', 'subdirs'],
            static fn (string $key): bool => array_key_exists($key, $payload),
        ));
        if (count($keys) > 1) {
            throw new \InvalidArgumentException('Folder scan request must use only one subdir field');
        }

        return $keys === [] ? [] : $payload[$keys[0]];
    }

    private function nextPayload(array $payload): mixed
    {
        $keys = array_values(array_filter(
            ['next', 'delay', 'nextSeconds'],
            static fn (string $key): bool => array_key_exists($key, $payload),
        ));
        if (count($keys) > 1) {
            throw new \InvalidArgumentException('Folder scan request must use only one next scan delay field');
        }

        return $keys === [] ? null : $payload[$keys[0]];
    }

    /**
     * @return list<string>
     */
    private function parseSubdirs(mixed $subdirs): array
    {
        if ($subdirs === null || $subdirs === '') {
            return [];
        }

        if (is_string($subdirs)) {
            $subdirs = [$subdirs];
        }
        if (!is_array($subdirs) || !array_is_list($subdirs)) {
            throw new \InvalidArgumentException('Folder scan request subdirs must be a string or list of strings');
        }

        $normalized = [];
        $seen = [];
        foreach ($subdirs as $subdir) {
            if (!is_string($subdir)) {
                throw new \InvalidArgumentException('Folder scan request subdirs must be strings');
            }

            $subdir = $this->normalizeSubdir($subdir);
            if (!isset($seen[$subdir])) {
                $normalized[] = $subdir;
                $seen[$subdir] = true;
            }
        }

        return $normalized;
    }

    private function normalizeSubdir(string $subdir): string
    {
        if (str_contains($subdir, "\0")) {
            throw new \InvalidArgumentException('Folder scan request subdir must not contain NUL bytes');
        }

        $subdir = trim(str_replace('\\', '/', $subdir));
        if ($subdir === '' || $subdir === '.' || $subdir === '/') {
            return '';
        }

        $subdir = ltrim($subdir, '/');
        $parts = [];
        foreach (explode('/', $subdir) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new \InvalidArgumentException('Folder scan request subdir must not traverse above the root');
            }

            $parts[] = $part;
        }

        $normalized = implode('/', $parts);
        if ($normalized !== '') {
            $normalized = ProtocolValidation::normalizeWireName($normalized, '/');
            ProtocolValidation::checkFilename($normalized);
        }

        return $normalized;
    }

    /**
     * @param list<string> $folderIds
     * @return list<string>
     */
    private function missingFolders(array $folderIds): array
    {
        if ($folderIds === []) {
            return [];
        }

        $registered = array_fill_keys($this->scheduler->folderIds(), true);
        $missing = [];
        foreach ($folderIds as $folderId) {
            if (!isset($registered[$folderId])) {
                $missing[] = $folderId;
            }
        }

        sort($missing, SORT_STRING);
        return $missing;
    }

    /**
     * @param array{allFolders:bool, folders:array<string, list<string>>, hashBlocks:bool, blockSize:?int, nextSeconds:?int} $request
     * @return array<string, array<string, mixed>>
     */
    private function applyNextScanDelay(FolderScanSchedulerResult $result, array $request, ?int $now): array
    {
        if ($request['allFolders'] || $request['nextSeconds'] === null) {
            return [];
        }

        $delayNow = $now ?? time();
        $scheduled = [];
        foreach (array_keys($request['folders']) as $folderId) {
            if ($result->snapshot($folderId) === null) {
                continue;
            }

            if ($this->scheduler->delayScan($folderId, $request['nextSeconds'], $delayNow)) {
                $status = $this->scheduler->scheduledScanStatus($folderId, $delayNow);
                if ($status !== null) {
                    $scheduled[$folderId] = $status;
                }
            }
        }
        ksort($scheduled, SORT_STRING);

        return $scheduled;
    }

    /**
     * @param array{allFolders:bool, folders:array<string, list<string>>, hashBlocks:bool, blockSize:?int, nextSeconds:?int} $request
     * @param array<string, array<string, mixed>> $scheduledScans
     */
    private function resultResponse(FolderScanSchedulerResult $result, array $request, array $scheduledScans): FolderScanApiResponse
    {
        $statusCode = $this->statusCodeForResult($result);
        $status = $result->successful()
            ? 'ok'
            : ($result->snapshots() === [] ? 'error' : 'partial');

        return new FolderScanApiResponse($statusCode, self::redactAbsolutePaths([
            'ok' => $result->successful(),
            'status' => $status,
            'request' => [
                'allFolders' => $request['allFolders'],
                'folders' => $request['folders'],
                'hashBlocks' => $request['hashBlocks'],
                'blockSize' => $request['blockSize'],
                'nextSeconds' => $request['nextSeconds'],
            ],
            'result' => $result->toRestStatus(),
            'scheduledScans' => $scheduledScans,
        ]));
    }

    private function statusCodeForResult(FolderScanSchedulerResult $result): int
    {
        if ($result->successful()) {
            return self::HTTP_OK;
        }
        if ($result->snapshots() !== []) {
            return self::HTTP_MULTI_STATUS;
        }
        foreach ($result->errors() as $error) {
            $message = $error->getMessage();
            if (
                str_contains($message, FolderScanScheduler::ERR_FOLDER_PAUSED)
                || $error instanceof FolderScanCheckpointConflictException
            ) {
                return self::HTTP_CONFLICT;
            }
        }

        return self::HTTP_INTERNAL_ERROR;
    }

    /**
     * @param array<string, mixed> $details
     */
    private function errorResponse(int $statusCode, string $code, string $message, array $details = []): FolderScanApiResponse
    {
        $body = [
            'ok' => false,
            'status' => 'error',
            'error' => $code,
            'message' => $message,
        ];
        if ($details !== []) {
            $body['details'] = $details;
        }

        return new FolderScanApiResponse($statusCode, self::redactAbsolutePaths($body));
    }

    private static function redactAbsolutePaths(mixed $value): mixed
    {
        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $key => $entry) {
                $redacted[$key] = self::redactAbsolutePaths($entry);
            }

            return $redacted;
        }
        if (!is_string($value)) {
            return $value;
        }

        $value = preg_replace('~(?<![:/A-Za-z0-9._-])/(?:[^\\s\\\'"<>()[\\]{}]+)~', '[absolute-path]', $value) ?? $value;
        return preg_replace('~\\b[A-Za-z]:\\\\[^\\s\\\'"<>()[\\]{}]+~', '[absolute-path]', $value) ?? $value;
    }
}
