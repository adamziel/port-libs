<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class CargoWorkspaceEvidence
{
    public const UPSTREAM_COMMIT = '87433ed33eee9ba974111d20b854f6acb07cd4a6';

    /**
     * @return array{
     *     upstreamCommit: string,
     *     workspacePackages: int,
     *     workspaceMembers: int,
     *     declaredTargets: int,
     *     testTargets: int,
     *     missingDeclaredTargets: list<array{package: string, target: string, kind: string, path: string, blocksWorkspaceNoRun: bool}>,
     *     cargoCommands: array<string, array<string, mixed>>
     * }
     */
    public static function current(): array
    {
        return [
            'upstreamCommit' => self::UPSTREAM_COMMIT,
            'workspacePackages' => 70,
            'workspaceMembers' => 70,
            'declaredTargets' => 126,
            'testTargets' => 101,
            'missingDeclaredTargets' => self::missingDeclaredTargets(),
            'cargoCommands' => [
                'metadataOffline' => [
                    'command' => 'timeout 60 cargo metadata --locked --offline --format-version 1 --no-deps',
                    'exitCode' => 0,
                    'status' => 'passed',
                    'packages' => 70,
                    'workspaceMembers' => 70,
                    'targets' => 126,
                    'testTargets' => 101,
                ],
                'workspaceNoRunOffline' => [
                    'command' => 'CARGO_TARGET_DIR=/tmp/port-libs-gitoxide-cargo-workspace-target timeout 180 cargo test --workspace --locked --offline --no-run',
                    'exitCode' => 101,
                    'status' => 'blocked',
                    'blocker' => 'sparse upstream cache is missing declared Cargo target source files during target resolution before compilation starts',
                    'targetResolutionErrors' => 10,
                    'blockingPackages' => self::workspaceNoRunBlockingPackages(),
                ],
                'defaultPackageNoRunOffline' => [
                    'command' => 'CARGO_TARGET_DIR=/tmp/port-libs-gitoxide-default-target timeout 180 cargo test --locked --offline --no-run',
                    'exitCode' => 0,
                    'status' => 'passed',
                    'durationSeconds' => 50.13,
                    'builtExecutable' => 'unittests src/lib.rs',
                ],
                'defaultPackageLibOffline' => [
                    'command' => 'CARGO_TARGET_DIR=/tmp/port-libs-gitoxide-default-target timeout 120 cargo test --locked --offline --lib',
                    'exitCode' => 0,
                    'status' => 'passed',
                    'passed' => 4,
                    'failed' => 0,
                    'ignored' => 0,
                    'measured' => 0,
                    'filteredOut' => 0,
                ],
            ],
        ];
    }

    /**
     * @return list<array{package: string, target: string, kind: string, path: string, blocksWorkspaceNoRun: bool}>
     */
    public static function missingDeclaredTargets(): array
    {
        return [
            self::target('gix-error', 'auto-chain-error', 'test', 'gix-error/tests/auto_chain_error.rs', true),
            self::target('gix-features', 'multi-threaded', 'test', 'gix-features/tests/parallel_shared_threaded.rs', true),
            self::target('gix-features', 'parallel', 'test', 'gix-features/tests/parallel_threaded.rs', true),
            self::target('gix-features', 'pipe', 'test', 'gix-features/tests/pipe.rs', true),
            self::target('gix-features', 'single-threaded', 'test', 'gix-features/tests/parallel_shared.rs', true),
            self::target('gix-tempfile', 'delete-tempfiles-on-sigterm', 'example', 'gix-tempfile/examples/delete-tempfiles-on-sigterm.rs', true),
            self::target('gix-tempfile', 'delete-tempfiles-on-sigterm-interactive', 'example', 'gix-tempfile/examples/delete-tempfiles-on-sigterm-interactive.rs', true),
            self::target('gix-tempfile', 'try-deadlock-on-cleanup', 'example', 'gix-tempfile/examples/try-deadlock-on-cleanup.rs', true),
            self::target('gix-odb', 'odb', 'test', 'gix-odb/tests/odb/main.rs', true),
            self::target('gix-diff', 'line-count', 'bench', 'gix-diff/benches/line_count.rs', false),
            self::target('gix-glob', 'wildmatch', 'bench', 'gix-glob/benches/wildmatch.rs', false),
            self::target('gix-index', 'from-tree', 'bench', 'gix-index/benches/from_tree.rs', false),
            self::target('gix-config', 'large_config_file', 'bench', 'gix-config/benches/large_config_file.rs', false),
            self::target('gix-transport', 'async-transport', 'test', 'gix-transport/tests/async-transport.rs', false),
            self::target('gix-transport', 'blocking-transport-http-only', 'test', 'gix-transport/tests/blocking-transport-http.rs', false),
            self::target('gix', 'clone', 'example', 'gix/examples/clone.rs', true),
        ];
    }

    /**
     * @return list<string>
     */
    public static function workspaceNoRunBlockingPackages(): array
    {
        return [
            'gix',
            'gix-error',
            'gix-features',
            'gix-odb',
            'gix-tempfile',
        ];
    }

    public static function workspaceAdmissionStatus(): string
    {
        return 'blocked: full offline Cargo workspace no-run fails during target resolution; default gitoxide package no-run and lib tests pass offline';
    }

    /**
     * @return array{package: string, target: string, kind: string, path: string, blocksWorkspaceNoRun: bool}
     */
    private static function target(string $package, string $target, string $kind, string $path, bool $blocksWorkspaceNoRun): array
    {
        return [
            'package' => $package,
            'target' => $target,
            'kind' => $kind,
            'path' => $path,
            'blocksWorkspaceNoRun' => $blocksWorkspaceNoRun,
        ];
    }
}
