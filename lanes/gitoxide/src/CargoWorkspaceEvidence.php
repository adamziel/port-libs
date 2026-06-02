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
     *     targetHydrationClosure: array<string, mixed>,
     *     targetMaterializationPlan: array<string, mixed>,
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
            'targetHydrationClosure' => self::targetHydrationClosure(),
            'targetMaterializationPlan' => CargoTargetMaterializer::plan(),
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
        return 'blocked: full offline Cargo workspace no-run fails during target resolution in the sparse checkout; target hydration closure is source-available for all missing declared targets; default gitoxide package no-run and lib tests pass offline';
    }

    /**
     * @return array{
     *     upstreamCommit: string,
     *     status: string,
     *     sourceTruth: string,
     *     totalHydratableTargets: int,
     *     blockingHydratableTargets: int,
     *     nonBlockingHydratableTargets: int,
     *     allMissingTargetsHydratable: bool,
     *     nextProbe: string,
     *     targets: list<array{package: string, target: string, kind: string, path: string, manifestPath: string, blob: string, bytes: int, lines: int, blocksWorkspaceNoRun: bool}>
     * }
     */
    public static function targetHydrationClosure(): array
    {
        $targets = self::hydratableTargetSources();
        $missingPaths = array_map(
            static fn (array $target): string => $target['path'],
            self::missingDeclaredTargets()
        );
        $hydratedPaths = array_map(
            static fn (array $target): string => $target['path'],
            $targets
        );

        sort($missingPaths);
        sort($hydratedPaths);

        $blockingTargets = array_values(array_filter(
            $targets,
            static fn (array $target): bool => $target['blocksWorkspaceNoRun']
        ));

        return [
            'upstreamCommit' => self::UPSTREAM_COMMIT,
            'status' => 'closed-source-available',
            'sourceTruth' => 'git ls-tree -r plus git cat-file -s/-p against the pinned upstream commit',
            'totalHydratableTargets' => count($targets),
            'blockingHydratableTargets' => count($blockingTargets),
            'nonBlockingHydratableTargets' => count($targets) - count($blockingTargets),
            'allMissingTargetsHydratable' => $missingPaths === $hydratedPaths,
            'nextProbe' => 'materialize these target blobs into the sparse checkout or use a complete checkout, then rerun CARGO_TARGET_DIR=/tmp/port-libs-gitoxide-cargo-workspace-target timeout 180 cargo test --workspace --locked --offline --no-run',
            'targets' => $targets,
        ];
    }

    public static function targetHydrationStatus(): string
    {
        return 'closed-source-available: all 16 sparse-cache missing Cargo target files exist as blobs at the pinned upstream commit; the remaining runner gate is materialization plus full workspace no-run compilation';
    }

    /**
     * @return list<array{package: string, target: string, kind: string, path: string, manifestPath: string, blob: string, bytes: int, lines: int, blocksWorkspaceNoRun: bool}>
     */
    public static function hydratableTargetSources(): array
    {
        return [
            self::hydration('gix-error', 'auto-chain-error', 'test', 'gix-error/tests/auto_chain_error.rs', 'gix-error/Cargo.toml', 'ab2c793721a3dce4d0fb6c92ef24818585ac660e', 3059, 96, true),
            self::hydration('gix-features', 'multi-threaded', 'test', 'gix-features/tests/parallel_shared_threaded.rs', 'gix-features/Cargo.toml', '055a899eadf092cdda35a6a60fe517450491cafc', 14, 1, true),
            self::hydration('gix-features', 'parallel', 'test', 'gix-features/tests/parallel_threaded.rs', 'gix-features/Cargo.toml', '05f49d19550ce520de9c58eb9044966d3ffad7ca', 3871, 122, true),
            self::hydration('gix-features', 'pipe', 'test', 'gix-features/tests/pipe.rs', 'gix-features/Cargo.toml', '8027a234c910810b876c02d6ddd25cb90104deda', 3645, 117, true),
            self::hydration('gix-features', 'single-threaded', 'test', 'gix-features/tests/parallel_shared.rs', 'gix-features/Cargo.toml', '055a899eadf092cdda35a6a60fe517450491cafc', 14, 1, true),
            self::hydration('gix-tempfile', 'delete-tempfiles-on-sigterm', 'example', 'gix-tempfile/examples/delete-tempfiles-on-sigterm.rs', 'gix-tempfile/Cargo.toml', '8ed558ce05e39a0c4486d8960d9f79da9df43117', 886, 27, true),
            self::hydration('gix-tempfile', 'delete-tempfiles-on-sigterm-interactive', 'example', 'gix-tempfile/examples/delete-tempfiles-on-sigterm-interactive.rs', 'gix-tempfile/Cargo.toml', 'e55929687959bb2d9995b1fa79590f77b7d58d15', 935, 24, true),
            self::hydration('gix-tempfile', 'try-deadlock-on-cleanup', 'example', 'gix-tempfile/examples/try-deadlock-on-cleanup.rs', 'gix-tempfile/Cargo.toml', 'eb4def5a0bc456a7ed8ecb04cba1d93980ac265b', 4242, 103, true),
            self::hydration('gix-odb', 'odb', 'test', 'gix-odb/tests/odb/main.rs', 'gix-odb/Cargo.toml', '7371e2a88dde5a50316eedafb9f804370f284db5', 708, 26, true),
            self::hydration('gix-diff', 'line-count', 'bench', 'gix-diff/benches/line_count.rs', 'gix-diff/Cargo.toml', 'efdb888c40a0e9661e538bfa3227e67f7a24b2cd', 4694, 162, false),
            self::hydration('gix-glob', 'wildmatch', 'bench', 'gix-glob/benches/wildmatch.rs', 'gix-glob/Cargo.toml', '5a3c3a889c9585182f6d6eb59e3cce743d359f6a', 1971, 59, false),
            self::hydration('gix-index', 'from-tree', 'bench', 'gix-index/benches/from_tree.rs', 'gix-index/Cargo.toml', '66eb1ad4901b8abcf6d2ede4eb03b1a74128793d', 4879, 142, false),
            self::hydration('gix-config', 'large_config_file', 'bench', 'gix-config/benches/large_config_file.rs', 'gix-config/Cargo.toml', '1f34f7fae3ea79828be8a848931b7e3455a41f9f', 7394, 292, false),
            self::hydration('gix-transport', 'async-transport', 'test', 'gix-transport/tests/async-transport.rs', 'gix-transport/Cargo.toml', '19a22801885d18067c3dab22c0017fff935bb1b1', 458, 14, false),
            self::hydration('gix-transport', 'blocking-transport-http-only', 'test', 'gix-transport/tests/blocking-transport-http.rs', 'gix-transport/Cargo.toml', 'af94be7e9f4b861f68961f5c9fe4398401c02222', 402, 15, false),
            self::hydration('gix', 'clone', 'example', 'gix/examples/clone.rs', 'gix/Cargo.toml', '9f1441ed90acbc58da304e2057f48975dbf93861', 1755, 53, true),
        ];
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

    /**
     * @return array{package: string, target: string, kind: string, path: string, manifestPath: string, blob: string, bytes: int, lines: int, blocksWorkspaceNoRun: bool}
     */
    private static function hydration(
        string $package,
        string $target,
        string $kind,
        string $path,
        string $manifestPath,
        string $blob,
        int $bytes,
        int $lines,
        bool $blocksWorkspaceNoRun
    ): array {
        return [
            'package' => $package,
            'target' => $target,
            'kind' => $kind,
            'path' => $path,
            'manifestPath' => $manifestPath,
            'blob' => $blob,
            'bytes' => $bytes,
            'lines' => $lines,
            'blocksWorkspaceNoRun' => $blocksWorkspaceNoRun,
        ];
    }
}
