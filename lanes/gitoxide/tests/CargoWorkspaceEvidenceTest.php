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
        $t->same(10, count(array_filter(
            $evidence['missingDeclaredTargets'],
            static fn (array $target): bool => $target['blocksWorkspaceNoRun']
        )));

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
];
