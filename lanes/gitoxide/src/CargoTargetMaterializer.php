<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class CargoTargetMaterializer
{
    /**
     * @param list<array<string, mixed>>|null $targets
     * @return array{
     *     upstreamCommit: string,
     *     status: string,
     *     targets: int,
     *     blockingTargets: int,
     *     nonBlockingTargets: int,
     *     totalBytes: int,
     *     totalLines: int,
     *     directories: list<string>,
     *     nextProbe: string
     * }
     */
    public static function plan(?array $targets = null): array
    {
        $targets = self::normalizeTargets($targets ?? CargoWorkspaceEvidence::hydratableTargetSources());
        $directories = [];
        $blockingTargets = 0;
        $totalBytes = 0;
        $totalLines = 0;

        foreach ($targets as $target) {
            $directory = dirname($target['path']);
            if ($directory !== '.') {
                $directories[$directory] = true;
            }
            if ($target['blocksWorkspaceNoRun']) {
                $blockingTargets++;
            }
            $totalBytes += $target['bytes'];
            $totalLines += $target['lines'];
        }

        $directoryList = array_keys($directories);
        sort($directoryList, SORT_STRING);

        return [
            'upstreamCommit' => CargoWorkspaceEvidence::UPSTREAM_COMMIT,
            'status' => 'ready-to-materialize',
            'targets' => count($targets),
            'blockingTargets' => $blockingTargets,
            'nonBlockingTargets' => count($targets) - $blockingTargets,
            'totalBytes' => $totalBytes,
            'totalLines' => $totalLines,
            'directories' => $directoryList,
            'nextProbe' => 'after materialization rerun CARGO_TARGET_DIR=/tmp/port-libs-gitoxide-cargo-workspace-target timeout 180 cargo test --workspace --locked --offline --no-run',
        ];
    }

    /**
     * @param list<array<string, mixed>>|null $targets
     * @return array{
     *     upstreamCommit: string,
     *     status: string,
     *     destinationRoot: string,
     *     requestedTargets: int,
     *     materializedTargets: int,
     *     writtenTargets: int,
     *     alreadyPresentTargets: int,
     *     blockingMaterializedTargets: int,
     *     nonBlockingMaterializedTargets: int,
     *     totalBytes: int,
     *     totalLines: int,
     *     targets: list<array{package:string,target:string,kind:string,path:string,blob:string,bytes:int,lines:int,blocksWorkspaceNoRun:bool,status:string}>,
     *     nextProbe: string
     * }
     */
    public static function materializeFromObjectDatabase(
        ObjectDatabase $database,
        string $destinationRoot,
        bool $overwrite = false,
        ?array $targets = null
    ): array {
        return self::materialize(
            $destinationRoot,
            static fn (array $target): string => self::readTargetBlobFromObjectDatabase($database, $target),
            $overwrite,
            $targets
        );
    }

    /**
     * @param callable(array<string, mixed>): string $readBlob
     * @param list<array<string, mixed>>|null $targets
     * @return array{
     *     upstreamCommit: string,
     *     status: string,
     *     destinationRoot: string,
     *     requestedTargets: int,
     *     materializedTargets: int,
     *     writtenTargets: int,
     *     alreadyPresentTargets: int,
     *     blockingMaterializedTargets: int,
     *     nonBlockingMaterializedTargets: int,
     *     totalBytes: int,
     *     totalLines: int,
     *     targets: list<array{package:string,target:string,kind:string,path:string,blob:string,bytes:int,lines:int,blocksWorkspaceNoRun:bool,status:string}>,
     *     nextProbe: string
     * }
     */
    public static function materialize(
        string $destinationRoot,
        callable $readBlob,
        bool $overwrite = false,
        ?array $targets = null
    ): array {
        $destinationRoot = self::prepareDestinationRoot($destinationRoot);
        $targets = self::normalizeTargets($targets ?? CargoWorkspaceEvidence::hydratableTargetSources());

        $rows = [];
        $writtenTargets = 0;
        $alreadyPresentTargets = 0;
        $blockingTargets = 0;
        $totalBytes = 0;
        $totalLines = 0;

        foreach ($targets as $target) {
            $bytes = $readBlob($target);
            self::assertBlobMatchesTarget($target, $bytes);

            $path = $destinationRoot . '/' . $target['path'];
            $status = self::writeTarget($path, $bytes, $overwrite);
            if ($status === 'written') {
                $writtenTargets++;
            } else {
                $alreadyPresentTargets++;
            }
            if ($target['blocksWorkspaceNoRun']) {
                $blockingTargets++;
            }
            $totalBytes += $target['bytes'];
            $totalLines += $target['lines'];

            $rows[] = [
                'package' => $target['package'],
                'target' => $target['target'],
                'kind' => $target['kind'],
                'path' => $target['path'],
                'blob' => $target['blob'],
                'bytes' => $target['bytes'],
                'lines' => $target['lines'],
                'blocksWorkspaceNoRun' => $target['blocksWorkspaceNoRun'],
                'status' => $status,
            ];
        }

        return [
            'upstreamCommit' => CargoWorkspaceEvidence::UPSTREAM_COMMIT,
            'status' => 'materialized',
            'destinationRoot' => $destinationRoot,
            'requestedTargets' => count($targets),
            'materializedTargets' => count($rows),
            'writtenTargets' => $writtenTargets,
            'alreadyPresentTargets' => $alreadyPresentTargets,
            'blockingMaterializedTargets' => $blockingTargets,
            'nonBlockingMaterializedTargets' => count($rows) - $blockingTargets,
            'totalBytes' => $totalBytes,
            'totalLines' => $totalLines,
            'targets' => $rows,
            'nextProbe' => 'CARGO_TARGET_DIR=/tmp/port-libs-gitoxide-cargo-workspace-target timeout 180 cargo test --workspace --locked --offline --no-run',
        ];
    }

    /**
     * @param array<string, mixed> $target
     */
    public static function readTargetBlobFromObjectDatabase(ObjectDatabase $database, array $target): string
    {
        $target = self::normalizeTarget($target);
        $object = $database->read($target['blob']);
        if ($object->type !== 'blob') {
            throw new \RuntimeException("Cargo target source object is not a blob: {$target['path']}");
        }

        return $object->body;
    }

    /**
     * @param list<array<string, mixed>> $targets
     * @return list<array{package:string,target:string,kind:string,path:string,manifestPath:string,blob:string,bytes:int,lines:int,blocksWorkspaceNoRun:bool}>
     */
    private static function normalizeTargets(array $targets): array
    {
        $normalized = [];
        foreach ($targets as $target) {
            $normalized[] = self::normalizeTarget($target);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $target
     * @return array{package:string,target:string,kind:string,path:string,manifestPath:string,blob:string,bytes:int,lines:int,blocksWorkspaceNoRun:bool}
     */
    private static function normalizeTarget(array $target): array
    {
        foreach (['package', 'target', 'kind', 'path', 'manifestPath', 'blob'] as $key) {
            if (!isset($target[$key]) || !is_string($target[$key]) || $target[$key] === '') {
                throw new \InvalidArgumentException("Cargo target {$key} must be a non-empty string");
            }
        }
        foreach (['bytes', 'lines'] as $key) {
            if (!isset($target[$key]) || !is_int($target[$key]) || $target[$key] < 0) {
                throw new \InvalidArgumentException("Cargo target {$key} must be a non-negative integer");
            }
        }
        if (!isset($target['blocksWorkspaceNoRun']) || !is_bool($target['blocksWorkspaceNoRun'])) {
            throw new \InvalidArgumentException('Cargo target blocksWorkspaceNoRun must be boolean');
        }
        if (!preg_match('/\A[0-9a-f]{40}\z/', $target['blob'])) {
            throw new \InvalidArgumentException("Cargo target blob must be a SHA-1 object id: {$target['path']}");
        }

        self::assertRelativePath($target['path']);
        self::assertRelativePath($target['manifestPath']);

        return [
            'package' => $target['package'],
            'target' => $target['target'],
            'kind' => $target['kind'],
            'path' => $target['path'],
            'manifestPath' => $target['manifestPath'],
            'blob' => $target['blob'],
            'bytes' => $target['bytes'],
            'lines' => $target['lines'],
            'blocksWorkspaceNoRun' => $target['blocksWorkspaceNoRun'],
        ];
    }

    private static function prepareDestinationRoot(string $destinationRoot): string
    {
        if ($destinationRoot === '' || str_contains($destinationRoot, "\0")) {
            throw new \InvalidArgumentException('Destination root must be a non-empty path without NUL bytes');
        }
        if (!str_starts_with($destinationRoot, '/')) {
            throw new \InvalidArgumentException('Destination root must be absolute for Cargo target materialization');
        }
        $destinationRoot = rtrim($destinationRoot, '/');
        if ($destinationRoot === '') {
            throw new \InvalidArgumentException('Destination root must not be filesystem root');
        }
        if (file_exists($destinationRoot) && !is_dir($destinationRoot)) {
            throw new \RuntimeException("Destination root exists and is not a directory: {$destinationRoot}");
        }
        if (!is_dir($destinationRoot) && !mkdir($destinationRoot, 0777, true) && !is_dir($destinationRoot)) {
            throw new \RuntimeException("Unable to create destination root: {$destinationRoot}");
        }

        return $destinationRoot;
    }

    private static function assertRelativePath(string $path): void
    {
        if (
            $path === ''
            || str_starts_with($path, '/')
            || str_contains($path, "\0")
            || str_contains($path, '\\')
        ) {
            throw new \InvalidArgumentException("Cargo target path must be a clean relative slash path: {$path}");
        }

        foreach (explode('/', $path) as $component) {
            if ($component === '' || $component === '.' || $component === '..') {
                throw new \InvalidArgumentException("Cargo target path must not contain empty, dot, or parent components: {$path}");
            }
        }
    }

    /**
     * @param array{path:string,blob:string,bytes:int,lines:int} $target
     */
    private static function assertBlobMatchesTarget(array $target, string $bytes): void
    {
        $actualBlob = hash('sha1', GitObject::looseHeader('blob', strlen($bytes)) . $bytes);
        if ($actualBlob !== $target['blob']) {
            throw new \RuntimeException("Cargo target source blob mismatch for {$target['path']}: expected {$target['blob']}, got {$actualBlob}");
        }
        if (strlen($bytes) !== $target['bytes']) {
            throw new \RuntimeException("Cargo target source byte length mismatch for {$target['path']}: expected {$target['bytes']}, got " . strlen($bytes));
        }
        $lines = substr_count($bytes, "\n");
        if ($lines !== $target['lines']) {
            throw new \RuntimeException("Cargo target source line count mismatch for {$target['path']}: expected {$target['lines']}, got {$lines}");
        }
    }

    private static function writeTarget(string $path, string $bytes, bool $overwrite): string
    {
        if (file_exists($path) || is_link($path)) {
            if (is_file($path) && !is_link($path)) {
                $existing = file_get_contents($path);
                if ($existing === $bytes) {
                    return 'already-present';
                }
                if (!$overwrite) {
                    throw new \RuntimeException("Cargo target already exists with different bytes: {$path}");
                }
            } else {
                throw new \RuntimeException("Cargo target path exists and is not a regular file: {$path}");
            }
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create Cargo target directory: {$directory}");
        }

        $temporary = tempnam($directory, '.cargo-target-');
        if ($temporary === false) {
            throw new \RuntimeException("Unable to create temporary Cargo target file in: {$directory}");
        }

        try {
            if (file_put_contents($temporary, $bytes) === false) {
                throw new \RuntimeException("Unable to write temporary Cargo target file: {$temporary}");
            }
            if (!rename($temporary, $path)) {
                throw new \RuntimeException("Unable to materialize Cargo target file: {$path}");
            }
            $temporary = null;
        } finally {
            if ($temporary !== null && file_exists($temporary)) {
                @unlink($temporary);
            }
        }

        return 'written';
    }
}
