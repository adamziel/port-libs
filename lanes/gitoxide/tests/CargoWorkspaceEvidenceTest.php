<?php

declare(strict_types=1);

use PortLibs\Gitoxide\CargoWorkspaceEvidence;

return [
    'records pinned upstream cargo workspace metadata inventory' => static function (TestRunner $t): void {
        $evidence = CargoWorkspaceEvidence::current();

        $t->same('87433ed33eee9ba974111d20b854f6acb07cd4a6', $evidence['upstreamCommit']);
        $t->same(70, $evidence['workspacePackages']);
        $t->same(70, $evidence['workspaceMembers']);
        $t->same(126, $evidence['declaredTargets']);
        $t->same(101, $evidence['testTargets']);
        $t->same(16, count($evidence['missingDeclaredTargets']));
        $t->same(16, $evidence['targetHydrationClosure']['totalHydratableTargets']);
        $t->same(true, $evidence['targetHydrationClosure']['allMissingTargetsHydratable']);
        $t->same(10, count(array_filter(
            $evidence['missingDeclaredTargets'],
            static fn (array $target): bool => $target['blocksWorkspaceNoRun']
        )));
        $t->same(10, $evidence['targetHydrationClosure']['blockingHydratableTargets']);
        $t->same(6, $evidence['targetHydrationClosure']['nonBlockingHydratableTargets']);

        $t->same([
            'gix',
            'gix-error',
            'gix-features',
            'gix-odb',
            'gix-tempfile',
        ], CargoWorkspaceEvidence::workspaceNoRunBlockingPackages());
    },
    'distinguishes full workspace blocker from default package cargo pass' => static function (TestRunner $t): void {
        $commands = CargoWorkspaceEvidence::current()['cargoCommands'];

        $t->same(0, $commands['metadataOffline']['exitCode']);
        $t->same('passed', $commands['metadataOffline']['status']);
        $t->same(70, $commands['metadataOffline']['workspaceMembers']);
        $t->same(126, $commands['metadataOffline']['targets']);

        $t->same(101, $commands['workspaceNoRunOffline']['exitCode']);
        $t->same('blocked', $commands['workspaceNoRunOffline']['status']);
        $t->same(10, $commands['workspaceNoRunOffline']['targetResolutionErrors']);
        $t->contains('target resolution', $commands['workspaceNoRunOffline']['blocker']);

        $t->same(0, $commands['defaultPackageNoRunOffline']['exitCode']);
        $t->same('passed', $commands['defaultPackageNoRunOffline']['status']);
        $t->same('unittests src/lib.rs', $commands['defaultPackageNoRunOffline']['builtExecutable']);
        $t->same(50.13, $commands['defaultPackageNoRunOffline']['durationSeconds']);

        $t->same(0, $commands['defaultPackageLibOffline']['exitCode']);
        $t->same('passed', $commands['defaultPackageLibOffline']['status']);
        $t->same(4, $commands['defaultPackageLibOffline']['passed']);
        $t->same(0, $commands['defaultPackageLibOffline']['failed']);
        $t->same(0, $commands['defaultPackageLibOffline']['filteredOut']);

        $t->contains('full offline Cargo workspace no-run fails', CargoWorkspaceEvidence::workspaceAdmissionStatus());
        $t->contains('target hydration closure is source-available', CargoWorkspaceEvidence::workspaceAdmissionStatus());
        $t->contains('default gitoxide package no-run and lib tests pass', CargoWorkspaceEvidence::workspaceAdmissionStatus());
    },
    'preserves exact sparse-cache missing cargo target paths' => static function (TestRunner $t): void {
        $missing = CargoWorkspaceEvidence::missingDeclaredTargets();
        $byPackageTarget = [];
        foreach ($missing as $target) {
            $byPackageTarget[$target['package'] . ':' . $target['target']] = $target;
        }

        $t->same('gix-error/tests/auto_chain_error.rs', $byPackageTarget['gix-error:auto-chain-error']['path']);
        $t->same(true, $byPackageTarget['gix-error:auto-chain-error']['blocksWorkspaceNoRun']);
        $t->same('gix-features/tests/parallel_shared_threaded.rs', $byPackageTarget['gix-features:multi-threaded']['path']);
        $t->same('gix-tempfile/examples/delete-tempfiles-on-sigterm.rs', $byPackageTarget['gix-tempfile:delete-tempfiles-on-sigterm']['path']);
        $t->same('gix-odb/tests/odb/main.rs', $byPackageTarget['gix-odb:odb']['path']);
        $t->same('gix/examples/clone.rs', $byPackageTarget['gix:clone']['path']);
        $t->same('gix-diff/benches/line_count.rs', $byPackageTarget['gix-diff:line-count']['path']);
        $t->same(false, $byPackageTarget['gix-diff:line-count']['blocksWorkspaceNoRun']);
        $t->same('gix-transport/tests/async-transport.rs', $byPackageTarget['gix-transport:async-transport']['path']);
        $t->same(false, $byPackageTarget['gix-transport:async-transport']['blocksWorkspaceNoRun']);
    },
    'closes missing target hydration inventory against pinned upstream blobs' => static function (TestRunner $t): void {
        $closure = CargoWorkspaceEvidence::targetHydrationClosure();

        $t->same('87433ed33eee9ba974111d20b854f6acb07cd4a6', $closure['upstreamCommit']);
        $t->same('closed-source-available', $closure['status']);
        $t->same(16, $closure['totalHydratableTargets']);
        $t->same(10, $closure['blockingHydratableTargets']);
        $t->same(6, $closure['nonBlockingHydratableTargets']);
        $t->same(true, $closure['allMissingTargetsHydratable']);
        $t->contains('git ls-tree -r', $closure['sourceTruth']);
        $t->contains('cargo test --workspace --locked --offline --no-run', $closure['nextProbe']);
        $t->contains('all 16 sparse-cache missing Cargo target files exist as blobs', CargoWorkspaceEvidence::targetHydrationStatus());

        $byPath = [];
        foreach ($closure['targets'] as $target) {
            $byPath[$target['path']] = $target;
            $t->same(40, strlen($target['blob']));
            $t->true($target['bytes'] > 0, 'target byte length should be positive for ' . $target['path']);
            $t->true($target['lines'] > 0, 'target line count should be positive for ' . $target['path']);
            $t->contains('/Cargo.toml', '/' . $target['manifestPath']);
        }

        $t->same('ab2c793721a3dce4d0fb6c92ef24818585ac660e', $byPath['gix-error/tests/auto_chain_error.rs']['blob']);
        $t->same(3059, $byPath['gix-error/tests/auto_chain_error.rs']['bytes']);
        $t->same(96, $byPath['gix-error/tests/auto_chain_error.rs']['lines']);
        $t->same('gix-error/Cargo.toml', $byPath['gix-error/tests/auto_chain_error.rs']['manifestPath']);
        $t->same(true, $byPath['gix-error/tests/auto_chain_error.rs']['blocksWorkspaceNoRun']);

        $t->same('055a899eadf092cdda35a6a60fe517450491cafc', $byPath['gix-features/tests/parallel_shared.rs']['blob']);
        $t->same('055a899eadf092cdda35a6a60fe517450491cafc', $byPath['gix-features/tests/parallel_shared_threaded.rs']['blob']);
        $t->same(1, $byPath['gix-features/tests/parallel_shared.rs']['lines']);
        $t->same(1, $byPath['gix-features/tests/parallel_shared_threaded.rs']['lines']);

        $t->same('9f1441ed90acbc58da304e2057f48975dbf93861', $byPath['gix/examples/clone.rs']['blob']);
        $t->same('example', $byPath['gix/examples/clone.rs']['kind']);
        $t->same(true, $byPath['gix/examples/clone.rs']['blocksWorkspaceNoRun']);

        $t->same('1f34f7fae3ea79828be8a848931b7e3455a41f9f', $byPath['gix-config/benches/large_config_file.rs']['blob']);
        $t->same(false, $byPath['gix-config/benches/large_config_file.rs']['blocksWorkspaceNoRun']);
        $t->same('af94be7e9f4b861f68961f5c9fe4398401c02222', $byPath['gix-transport/tests/blocking-transport-http.rs']['blob']);
        $t->same(false, $byPath['gix-transport/tests/blocking-transport-http.rs']['blocksWorkspaceNoRun']);
    },
];
