<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxUpstreamRunnerPlan;

$makeTempRoot = static function (): string {
    $root = sys_get_temp_dir() . '/pandoc-docx-runner-plan-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Unable to create temporary runner plan root');
    }

    return $root;
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    ) as $entry) {
        if ($entry->isDir()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }

    rmdir($path);
};

$writeFile = static function (string $root, string $relativePath, string $contents = "fixture\n"): void {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create runner plan fixture directory');
    }

    file_put_contents($path, $contents);
};

$hydrateRunnerPlanFixture = static function (string $upstreamRoot) use ($writeFile): void {
    $readerSource = <<<'HASKELL'
module Tests.Readers.Docx where

tests = testGroup "Docx"
  [ testCase "reader simple paragraph" $ pure ()
  , testCase "reader nested notes" $ pure ()
  ]
HASKELL;
    $writerSource = <<<'HASKELL'
module Tests.Writers.Docx where

tests = testGroup "Docx"
  [ golden "writer plain paragraph" "test/docx/golden/plain.docx"
  ]
HASKELL;

    foreach (DocxUpstreamRunnerPlan::requiredFiles() as $relativePath) {
        $contents = match ($relativePath) {
            'test/Tests/Readers/Docx.hs' => $readerSource,
            'test/Tests/Writers/Docx.hs' => $writerSource,
            default => $relativePath . "\n",
        };
        $writeFile($upstreamRoot, $relativePath, $contents);
    }

    $writeFile($upstreamRoot, 'test/docx/a.docx', 'docx-a');
    $writeFile($upstreamRoot, 'test/docx/a.native', 'native-a');
    $writeFile($upstreamRoot, 'test/docx/b.docx', 'docx-b');
    $writeFile($upstreamRoot, 'test/docx/golden/writer.docx', 'golden-writer');
};

$markSelectedInventoryPinned = static function (string $inventoryPath): void {
    $inventory = json_decode((string) file_get_contents($inventoryPath), true, 512, JSON_THROW_ON_ERROR);
    $inventory['upstream']['sourceCommit'] = DocxUpstreamRunnerPlan::PINNED_UPSTREAM_COMMIT;
    $inventory['upstream']['sourceCommitMatchesPinned'] = true;
    $inventory['upstream']['sourceCommitEvidenceStatus'] = DocxUpstreamRunnerPlan::PINNED_SOURCE_STATUS_MATCHED;
    file_put_contents(
        $inventoryPath,
        json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
    );
};

$writeValidRunnerTranscripts = static function (string $repoRoot, string $upstreamRoot, string $logRoot) use ($writeFile): array {
    $commands = (new DocxUpstreamRunnerPlan($repoRoot, $upstreamRoot))->report()['commands'];

    $writeFile(
        $repoRoot,
        $logRoot . '/runner-test-dependencies.txt',
        '$ ' . $commands['dependencyDryRun']['commandLine'] . "\nResolving dependencies...\nexitCode: 0\n"
    );
    $writeFile(
        $repoRoot,
        $logRoot . '/docx-targeted-list-tests.txt',
        '$ ' . $commands['listDocxTests']['commandLine'] . "\n"
            . "Readers.Docx.reader simple paragraph\n"
            . "Writers.Docx.writer plain paragraph\n"
            . "exitCode: 0\n"
    );
    $writeFile(
        $repoRoot,
        $logRoot . '/docx-targeted-run.txt',
        '$ ' . $commands['targetedDocxRun']['commandLine'] . "\n"
            . "Readers\n"
            . "  Docx\n"
            . "    reader simple paragraph: OK\n"
            . "Writers\n"
            . "  Docx\n"
            . "    writer plain paragraph: OK\n"
            . "All 2 tests passed\n"
            . "exitCode: 0\n"
    );

    return $commands;
};

return [
    'reports blocked docx runner preflight without hydrated upstream source' => static function (TestRunner $t) use ($makeTempRoot, $removeTree): void {
        $repoRoot = $makeTempRoot();
        try {
            $report = (new DocxUpstreamRunnerPlan(
                $repoRoot,
                DocxUpstreamRunnerPlan::DEFAULT_RELATIVE_UPSTREAM_ROOT,
                static fn (string $name): ?string => null,
                static fn (string $path): int => DocxUpstreamRunnerPlan::MINIMUM_SUGGESTED_FREE_BYTES - 1
            ))->report();
            $text = DocxUpstreamRunnerPlan::formatTextReport($report);

            $t->same(DocxUpstreamRunnerPlan::STATUS_BLOCKED_MISSING_UPSTREAM_SOURCE, $report['status']);
            $t->same('targeted-docx-runner-preflight-plan-only', $report['evidenceKind']);
            $t->same(false, $report['runnerExecuted']);
            $t->same(false, $report['resultRecorded']);
            $t->same(false, $report['willExecute']);
            $t->same(DocxUpstreamRunnerPlan::requiredFiles(), $report['sourcePreflight']['missingFiles']);
            $t->same(DocxUpstreamRunnerPlan::requiredDirectories(), $report['sourcePreflight']['missingDirectories']);
            $t->same(DocxUpstreamRunnerPlan::PINNED_SOURCE_STATUS_ROOT_MISSING, $report['sourcePreflight']['pinnedSource']['status']);
            $t->same(false, $report['sourcePreflight']['pinnedSource']['matchesPinnedCommit']);
            $t->same(count(DocxUpstreamRunnerPlan::requiredFiles()) + count(DocxUpstreamRunnerPlan::requiredDirectories()), count($report['sourcePreflight']['sourceBlockers']));
            $t->same(0, $report['sourcePreflight']['artifactCounts']['rootDocxPackageFiles']);
            $t->same(0, $report['sourcePreflight']['artifactCounts']['rootNativeExpectedFiles']);
            $t->same(0, $report['sourcePreflight']['artifactCounts']['goldenDocxPackageFiles']);
            $t->same(DocxUpstreamRunnerPlan::SELECTED_TEST_INVENTORY_STATUS_BLOCKED, $report['selectedTestInventory']['status']);
            $t->same(DocxUpstreamRunnerPlan::SELECTED_TEST_INVENTORY_EVIDENCE_KIND, $report['selectedTestInventory']['evidenceKind']);
            $t->same(true, $report['selectedTestInventory']['skipped']);
            $t->same(false, $report['selectedTestInventory']['runnerExecuted']);
            $t->same(false, $report['selectedTestInventory']['cabalExecuted']);
            $t->same(false, $report['selectedTestInventory']['docxPackageBytesRead']);
            $readiness = $report['localExecutionReadiness'];
            $t->same(DocxUpstreamRunnerPlan::LOCAL_READINESS_EVIDENCE_KIND, $readiness['evidenceKind']);
            $t->same(DocxUpstreamRunnerPlan::LOCAL_READINESS_STATUS_BLOCKED, $readiness['status']);
            $t->same(false, $readiness['runnerExecutionAttemptedByThisTool']);
            $t->same(false, $readiness['resultRecordedByThisTool']);
            $t->same(false, $readiness['checks']['sourcePreflightReady']);
            $t->same(false, $readiness['checks']['upstreamRootPresent']);
            $t->same(false, $readiness['checks']['sourceCommitMatchesPinned']);
            $t->same(null, $readiness['checks']['cabalExecutable']);
            $t->same(null, $readiness['checks']['ghcExecutable']);
            $t->same(DocxUpstreamRunnerPlan::MINIMUM_SUGGESTED_FREE_BYTES - 1, $readiness['checks']['freeBytes']);
            $t->same(false, $readiness['checks']['sufficientDiskForTargetedWorkspace']);
            $t->same([
                DocxUpstreamRunnerPlan::LOCAL_READINESS_BLOCKER_MISSING_SOURCE,
                DocxUpstreamRunnerPlan::LOCAL_READINESS_BLOCKER_MISSING_CABAL,
                DocxUpstreamRunnerPlan::LOCAL_READINESS_BLOCKER_MISSING_GHC,
                DocxUpstreamRunnerPlan::LOCAL_READINESS_BLOCKER_INSUFFICIENT_DISK,
            ], $readiness['blockerCodes']);
            $t->same(DocxUpstreamRunnerPlan::LOCAL_READINESS_BLOCKER_MISSING_SOURCE, $readiness['blockerEvidence'][0]['code'] ?? null);
            $t->same(DocxUpstreamRunnerPlan::LOCAL_READINESS_BLOCKER_MISSING_CABAL, $readiness['blockerEvidence'][1]['code'] ?? null);
            $t->same(DocxUpstreamRunnerPlan::LOCAL_READINESS_BLOCKER_MISSING_GHC, $readiness['blockerEvidence'][2]['code'] ?? null);
            $t->same(DocxUpstreamRunnerPlan::LOCAL_READINESS_BLOCKER_INSUFFICIENT_DISK, $readiness['blockerEvidence'][3]['code'] ?? null);
            $t->contains('missing DOCX upstream source paths', implode("\n", $readiness['blockers']));
            $t->contains('cabal executable not found on PATH', implode("\n", $readiness['blockers']));
            $t->contains('ghc executable not found on PATH', implode("\n", $readiness['blockers']));
            $t->contains('available disk space is below the suggested targeted-runner workspace floor', implode("\n", $readiness['blockers']));
            $t->contains('Local execution readiness: blocked-targeted-docx-runner-local-prerequisites', $text);
            $t->contains('Local execution blocker codes: missing-docx-upstream-source, missing-cabal-executable, missing-ghc-executable, insufficient-disk-for-targeted-runner-workspace', $text);
            $t->contains('Local execution blocker: missing DOCX upstream source paths', $text);
            $t->contains('Pinned source: not-checked-upstream-root-missing', $text);
            $t->contains('--dry-run --only-dependencies', $report['commands']['dependencyDryRun']['commandLine']);
            $t->contains('--list-tests --pattern', $report['commands']['listDocxTests']['commandLine']);
            $t->contains('($2 == "Readers" || $2 == "Writers") && $3 == "Docx"', $report['commands']['targetedDocxRun']['commandLine']);
            $t->contains('not an upstream DOCX runner result', $report['claim']);
            $t->contains('Selected inventory: blocked-missing-docx-upstream-source', $text);
            $t->contains('No upstream DOCX runner result or parity claim is asserted.', $text);
        } finally {
            $removeTree($repoRoot);
        }
    },

    'marks docx runner preflight ready with source files fixtures and artifact contract only' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $hydrateRunnerPlanFixture): void {
        $repoRoot = $makeTempRoot();
        try {
            $upstreamRoot = $repoRoot . '/cache/pandoc-current';
            $hydrateRunnerPlanFixture($upstreamRoot);

            $report = (new DocxUpstreamRunnerPlan(
                $repoRoot,
                'cache/pandoc-current',
                static fn (string $name): ?string => match ($name) {
                    'cabal' => '/usr/bin/cabal-fixture',
                    'ghc' => '/usr/bin/ghc-fixture',
                    default => null,
                },
                static fn (string $path): int => DocxUpstreamRunnerPlan::MINIMUM_SUGGESTED_FREE_BYTES + 1
            ))->report();

            $t->same(DocxUpstreamRunnerPlan::STATUS_READY, $report['status']);
            $t->same([], $report['sourcePreflight']['missingFiles']);
            $t->same([], $report['sourcePreflight']['missingDirectories']);
            $t->same(DocxUpstreamRunnerPlan::requiredFiles(), $report['sourcePreflight']['presentFiles']);
            $t->same(DocxUpstreamRunnerPlan::requiredDirectories(), $report['sourcePreflight']['presentDirectories']);
            $t->same(null, $report['sourcePreflight']['pinnedSource']['observedCommit']);
            $t->same(false, $report['sourcePreflight']['pinnedSource']['matchesPinnedCommit']);
            $t->same(2, $report['sourcePreflight']['artifactCounts']['rootDocxPackageFiles']);
            $t->same(1, $report['sourcePreflight']['artifactCounts']['rootNativeExpectedFiles']);
            $t->same(1, $report['sourcePreflight']['artifactCounts']['goldenDocxPackageFiles']);
            $t->same('filesystem names/counts only; preflight does not read DOCX package bytes', $report['sourcePreflight']['packageBytePolicy']);
            $inventory = $report['selectedTestInventory'];
            $t->same(DocxUpstreamRunnerPlan::SELECTED_TEST_INVENTORY_STATUS_REPORTED, $inventory['status']);
            $t->same(DocxUpstreamRunnerPlan::SELECTED_TEST_INVENTORY_EVIDENCE_KIND, $inventory['evidenceKind']);
            $t->same(false, $inventory['skipped']);
            $t->same(false, $inventory['runnerExecuted']);
            $t->same(false, $inventory['cabalExecuted']);
            $t->same(false, $inventory['docxPackageBytesRead']);
            $t->same(null, $inventory['upstream']['sourceCommit']);
            $t->same(false, $inventory['upstream']['sourceCommitMatchesPinned']);
            $t->same('Tests.Readers.Docx.tests', $inventory['sourceGroups'][0]['entryPointSnippet']);
            $t->same('reader simple paragraph', $inventory['sourceGroups'][0]['candidateStaticLabels'][1]['label']);
            $t->same('writer plain paragraph', $inventory['sourceGroups'][1]['candidateStaticLabels'][1]['label']);
            $t->same(2, $inventory['fixtures']['counts']['rootDocxPackageFiles']);
            $t->same(1, $inventory['fixtures']['counts']['rootNativeExpectedFiles']);
            $t->same(1, $inventory['fixtures']['counts']['pairedRootDocxNativeStems']);
            $t->same(['a'], $inventory['fixtures']['pairedRootDocxNativeStems']);
            $t->same(['b'], $inventory['fixtures']['unpairedRootDocxPackageStems']);
            $t->same('test/docx/golden/writer.docx', $inventory['fixtures']['goldenDocxPackages'][0]['path']);
            $t->contains('not Tasty --list-tests output', $inventory['claim']);
            $readiness = $report['localExecutionReadiness'];
            $t->same(true, $readiness['checks']['sourcePreflightReady']);
            $t->same(true, $readiness['checks']['upstreamRootPresent']);
            $t->same(false, $readiness['checks']['sourceCommitMatchesPinned']);
            $t->same('/usr/bin/cabal-fixture', $readiness['checks']['cabalExecutable']);
            $t->same('/usr/bin/ghc-fixture', $readiness['checks']['ghcExecutable']);
            $t->same(DocxUpstreamRunnerPlan::MINIMUM_SUGGESTED_FREE_BYTES + 1, $readiness['checks']['freeBytes']);
            $t->same(true, $readiness['checks']['sufficientDiskForTargetedWorkspace']);
            $t->same([DocxUpstreamRunnerPlan::LOCAL_READINESS_BLOCKER_UNVERIFIED_PINNED_SOURCE], $readiness['blockerCodes']);
            $t->same(DocxUpstreamRunnerPlan::LOCAL_READINESS_BLOCKER_UNVERIFIED_PINNED_SOURCE, $readiness['blockerEvidence'][0]['code'] ?? null);
            $t->same(false, $readiness['runnerExecutionAttemptedByThisTool']);
            $t->same(false, $readiness['resultRecordedByThisTool']);
            $t->true(is_array($readiness['blockers']), 'Readiness blockers must be a list even when local tooling availability varies');
            $t->same('cache/pandoc-current', $report['commands']['targetedDocxRun']['workingDirectory']);
            $t->same('.port-libs/pandoc-runner/cabal-build/docx-targeted-run', $report['workspace']['directories']['targetedRunBuild']);
            $t->same('.port-libs/pandoc-runner/tmp', $report['workspace']['environmentVariables']['TMPDIR']);
            $t->same('test:test-pandoc', $report['runnerTarget']);
            $t->same('($2 == "Readers" || $2 == "Writers") && $3 == "Docx"', $report['tastyPattern']);
            $t->contains('.port-libs/pandoc-runner/logs/docx-targeted-run.txt', implode(',', $report['resultArtifactContract']['requiredBeforeResultRecorded']));
            $t->contains('result.json', implode(',', $report['resultArtifactContract']['requiredBeforeResultRecorded']));
            $t->contains('preflight-plan.json', implode(',', $report['resultArtifactContract']['ciEvidenceReports']));
            $t->contains('exitCode', implode(',', $report['resultArtifactContract']['resultJsonRequiredFields']));
            $t->contains('verify selectedTestInventory upstream.sourceCommitMatchesPinned=true', implode("\n", $report['activationGate']));
            $t->same(false, $report['runnerExecuted']);
            $t->same(false, $report['resultRecorded']);
        } finally {
            $removeTree($repoRoot);
        }
    },

    'cli writes selected docx test inventory artifact without executing runner' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $hydrateRunnerPlanFixture): void {
        $repoRoot = $makeTempRoot();
        try {
            $upstreamRoot = $repoRoot . '/cache/pandoc-current';
            $hydrateRunnerPlanFixture($upstreamRoot);
            $tool = dirname(__DIR__, 3) . '/tools/pandoc-docx-upstream-runner-plan.php';
            $artifactRelativePath = 'artifacts/docx-selected-test-inventory.json';
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg($tool)
                . ' --repo-root '
                . escapeshellarg($repoRoot)
                . ' --upstream-root cache/pandoc-current --write-selected-inventory '
                . escapeshellarg($artifactRelativePath)
                . ' --json 2>&1';

            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $t->same(0, $exitCode, implode("\n", $output));

            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
            $t->same(true, $decoded['selectedTestInventoryArtifact']['written']);
            $t->same($artifactRelativePath, $decoded['selectedTestInventoryArtifact']['path']);
            $t->same(DocxUpstreamRunnerPlan::SELECTED_TEST_INVENTORY_EVIDENCE_KIND, $decoded['selectedTestInventoryArtifact']['evidenceKind']);

            $artifactPath = $repoRoot . '/' . $artifactRelativePath;
            $t->true(is_file($artifactPath), 'Selected DOCX inventory artifact should be written');
            $artifact = json_decode((string) file_get_contents($artifactPath), true, 512, JSON_THROW_ON_ERROR);
            $t->same(DocxUpstreamRunnerPlan::SELECTED_TEST_INVENTORY_STATUS_REPORTED, $artifact['status']);
            $t->same(false, $artifact['runnerExecuted']);
            $t->same(false, $artifact['cabalExecuted']);
            $t->same(false, $artifact['docxPackageBytesRead']);
            $t->same(null, $artifact['upstream']['sourceCommit']);
            $t->same(false, $artifact['upstream']['sourceCommitMatchesPinned']);
            $t->same('static-docx-selected-test-inventory-only', $artifact['evidenceKind']);
            $t->same(['Tests.Readers.Docx.tests', 'Tests.Writers.Docx.tests'], $artifact['selection']['selectedGroups']);
            $t->same(1, $artifact['fixtures']['counts']['pairedRootDocxNativeStems']);
            $t->same(1, $artifact['fixtures']['counts']['goldenDocxPackageFiles']);
            $t->contains('not Tasty --list-tests output', $artifact['claim']);
        } finally {
            $removeTree($repoRoot);
        }
    },

    'cli validates targeted runner result artifacts without executing runner' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $hydrateRunnerPlanFixture, $writeFile, $markSelectedInventoryPinned, $writeValidRunnerTranscripts): void {
        $repoRoot = $makeTempRoot();
        try {
            $upstreamRoot = $repoRoot . '/cache/pandoc-current';
            $hydrateRunnerPlanFixture($upstreamRoot);
            $tool = dirname(__DIR__, 3) . '/tools/pandoc-docx-upstream-runner-plan.php';
            $artifactRoot = 'artifacts/docx-targeted-run';
            $logRoot = 'logs';
            $selectedInventoryPath = $artifactRoot . '/selected-test-inventory.json';

            $writeInventoryCommand = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg($tool)
                . ' --repo-root '
                . escapeshellarg($repoRoot)
                . ' --upstream-root cache/pandoc-current --write-selected-inventory '
                . escapeshellarg($selectedInventoryPath)
                . ' --json 2>&1';
            $writeOutput = [];
            $writeExitCode = 0;
            exec($writeInventoryCommand, $writeOutput, $writeExitCode);
            $t->same(0, $writeExitCode, implode("\n", $writeOutput));
            $markSelectedInventoryPinned($repoRoot . '/' . $selectedInventoryPath);

            $commands = $writeValidRunnerTranscripts($repoRoot, 'cache/pandoc-current', $logRoot);

            $result = [
                'runnerExecuted' => true,
                'upstreamCommit' => DocxUpstreamRunnerPlan::PINNED_UPSTREAM_COMMIT,
                'commandLine' => $commands['targetedDocxRun']['commandLine'],
                'exitCode' => 0,
                'runnerTarget' => DocxUpstreamRunnerPlan::RUNNER_TARGET,
                'tastyPattern' => DocxUpstreamRunnerPlan::TASTY_PATTERN,
                'selectedTestCount' => 2,
                'passedCount' => 2,
                'failedCount' => 0,
                'skippedCount' => 0,
                'startedAtUtc' => '2026-06-30T00:00:00Z',
                'finishedAtUtc' => '2026-06-30T00:00:01Z',
                'selectedTestInventorySha256' => hash_file('sha256', $repoRoot . '/' . $selectedInventoryPath),
                'dependencyDryRunTranscriptSha256' => hash_file('sha256', $repoRoot . '/' . $logRoot . '/runner-test-dependencies.txt'),
                'listTestsTranscriptSha256' => hash_file('sha256', $repoRoot . '/' . $logRoot . '/docx-targeted-list-tests.txt'),
                'targetedRunTranscriptSha256' => hash_file('sha256', $repoRoot . '/' . $logRoot . '/docx-targeted-run.txt'),
            ];
            $writeFile(
                $repoRoot,
                $artifactRoot . '/result.json',
                json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
            );

            $validateCommand = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg($tool)
                . ' --repo-root '
                . escapeshellarg($repoRoot)
                . ' --upstream-root cache/pandoc-current --validate-result-artifacts --artifact-root '
                . escapeshellarg($artifactRoot)
                . ' --log-root '
                . escapeshellarg($logRoot)
                . ' --json 2>&1';
            $validateOutput = [];
            $validateExitCode = 0;
            exec($validateCommand, $validateOutput, $validateExitCode);
            $t->same(0, $validateExitCode, implode("\n", $validateOutput));

            $decoded = json_decode(implode("\n", $validateOutput), true, 512, JSON_THROW_ON_ERROR);
            $gate = $decoded['resultArtifactGate'];
            $t->same(DocxUpstreamRunnerPlan::RESULT_ARTIFACT_GATE_STATUS_ADMISSIBLE, $gate['status']);
            $t->same(DocxUpstreamRunnerPlan::RESULT_ARTIFACT_GATE_EVIDENCE_KIND, $gate['evidenceKind']);
            $t->same(true, $gate['admissionReady']);
            $t->same(false, $gate['runnerExecutedByThisTool']);
            $t->same(false, $gate['resultRecordedByThisTool']);
            $t->same(true, $gate['runnerExecutedClaimFromResult']);
            $t->same(null, $gate['evidenceGap']);
            $t->same([], $gate['problems']);
            $t->same(2, $gate['resultSummary']['selectedTestCount']);
            $t->same(hash_file('sha256', $repoRoot . '/' . $artifactRoot . '/result.json'), $gate['requiredArtifacts']['resultJson']['sha256']);
            $t->contains('does not execute Cabal/Tasty', $gate['claim']);
            $t->contains('transcripts include the exact Cabal command line', implode("\n", $gate['claimBoundaries']['doesAssert']));
        } finally {
            $removeTree($repoRoot);
        }
    },

    'cli writes targeted runner result artifact from captured transcripts' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $hydrateRunnerPlanFixture, $markSelectedInventoryPinned, $writeValidRunnerTranscripts): void {
        $repoRoot = $makeTempRoot();
        try {
            $upstreamRoot = $repoRoot . '/cache/pandoc-current';
            $hydrateRunnerPlanFixture($upstreamRoot);
            $tool = dirname(__DIR__, 3) . '/tools/pandoc-docx-upstream-runner-plan.php';
            $artifactRoot = 'artifacts/docx-targeted-run';
            $logRoot = 'logs';
            $selectedInventoryPath = $artifactRoot . '/selected-test-inventory.json';
            $resultPath = $artifactRoot . '/result.json';

            $writeInventoryCommand = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg($tool)
                . ' --repo-root '
                . escapeshellarg($repoRoot)
                . ' --upstream-root cache/pandoc-current --write-selected-inventory '
                . escapeshellarg($selectedInventoryPath)
                . ' --json 2>&1';
            $writeOutput = [];
            $writeExitCode = 0;
            exec($writeInventoryCommand, $writeOutput, $writeExitCode);
            $t->same(0, $writeExitCode, implode("\n", $writeOutput));
            $markSelectedInventoryPinned($repoRoot . '/' . $selectedInventoryPath);
            $writeValidRunnerTranscripts($repoRoot, 'cache/pandoc-current', $logRoot);

            $writeResultCommand = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg($tool)
                . ' --repo-root '
                . escapeshellarg($repoRoot)
                . ' --upstream-root cache/pandoc-current --artifact-root '
                . escapeshellarg($artifactRoot)
                . ' --log-root '
                . escapeshellarg($logRoot)
                . ' --write-result-artifact '
                . escapeshellarg($resultPath)
                . ' --result-started-at-utc 2026-06-30T00:00:00Z'
                . ' --result-finished-at-utc 2026-06-30T00:00:01Z'
                . ' --validate-result-artifacts'
                . ' --json 2>&1';
            $resultOutput = [];
            $resultExitCode = 0;
            exec($writeResultCommand, $resultOutput, $resultExitCode);
            $t->same(0, $resultExitCode, implode("\n", $resultOutput));

            $decoded = json_decode(implode("\n", $resultOutput), true, 512, JSON_THROW_ON_ERROR);
            $t->same(true, $decoded['resultArtifact']['written']);
            $t->same($resultPath, $decoded['resultArtifact']['path']);
            $t->same(DocxUpstreamRunnerPlan::RESULT_ARTIFACT_EVIDENCE_KIND, $decoded['resultArtifact']['evidenceKind']);
            $t->same(2, $decoded['resultArtifact']['selectedTestCount']);
            $t->same(2, $decoded['resultArtifact']['passedCount']);
            $t->same(0, $decoded['resultArtifact']['failedCount']);
            $t->same(0, $decoded['resultArtifact']['skippedCount']);
            $t->same(DocxUpstreamRunnerPlan::RESULT_ARTIFACT_GATE_STATUS_ADMISSIBLE, $decoded['resultArtifactGate']['status']);
            $t->same(true, $decoded['resultArtifactGate']['admissionReady']);

            $result = json_decode((string) file_get_contents($repoRoot . '/' . $resultPath), true, 512, JSON_THROW_ON_ERROR);
            $t->same(true, $result['runnerExecuted']);
            $t->same(DocxUpstreamRunnerPlan::PINNED_UPSTREAM_COMMIT, $result['upstreamCommit']);
            $t->same(DocxUpstreamRunnerPlan::RUNNER_TARGET, $result['runnerTarget']);
            $t->same(DocxUpstreamRunnerPlan::TASTY_PATTERN, $result['tastyPattern']);
            $t->same(hash_file('sha256', $repoRoot . '/' . $selectedInventoryPath), $result['selectedTestInventorySha256']);
            $t->same(hash_file('sha256', $repoRoot . '/' . $logRoot . '/runner-test-dependencies.txt'), $result['dependencyDryRunTranscriptSha256']);
            $t->same(hash_file('sha256', $repoRoot . '/' . $logRoot . '/docx-targeted-list-tests.txt'), $result['listTestsTranscriptSha256']);
            $t->same(hash_file('sha256', $repoRoot . '/' . $logRoot . '/docx-targeted-run.txt'), $result['targetedRunTranscriptSha256']);
        } finally {
            $removeTree($repoRoot);
        }
    },

    'rejects self consistent runner result without real cabal tasty transcript evidence' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $hydrateRunnerPlanFixture, $writeFile, $markSelectedInventoryPinned): void {
        $repoRoot = $makeTempRoot();
        try {
            $upstreamRoot = $repoRoot . '/cache/pandoc-current';
            $hydrateRunnerPlanFixture($upstreamRoot);
            $artifactRoot = 'artifacts/docx-targeted-run';
            $logRoot = 'logs';
            $selectedInventoryPath = $artifactRoot . '/selected-test-inventory.json';
            $plan = new DocxUpstreamRunnerPlan($repoRoot, 'cache/pandoc-current');
            $report = $plan->report();

            $writeFile(
                $repoRoot,
                $selectedInventoryPath,
                json_encode($report['selectedTestInventory'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
            );
            $markSelectedInventoryPinned($repoRoot . '/' . $selectedInventoryPath);

            $writeFile($repoRoot, $logRoot . '/runner-test-dependencies.txt', "dependency dry-run transcript\n");
            $writeFile($repoRoot, $logRoot . '/docx-targeted-list-tests.txt', "Readers.Docx.reader simple paragraph\nWriters.Docx.writer plain paragraph\n");
            $writeFile($repoRoot, $logRoot . '/docx-targeted-run.txt', "targeted docx run transcript\n");

            $commands = $report['commands'];
            $result = [
                'runnerExecuted' => true,
                'upstreamCommit' => DocxUpstreamRunnerPlan::PINNED_UPSTREAM_COMMIT,
                'commandLine' => $commands['targetedDocxRun']['commandLine'],
                'exitCode' => 0,
                'runnerTarget' => DocxUpstreamRunnerPlan::RUNNER_TARGET,
                'tastyPattern' => DocxUpstreamRunnerPlan::TASTY_PATTERN,
                'selectedTestCount' => 2,
                'passedCount' => 2,
                'failedCount' => 0,
                'skippedCount' => 0,
                'startedAtUtc' => '2026-06-30T00:00:00Z',
                'finishedAtUtc' => '2026-06-30T00:00:01Z',
                'selectedTestInventorySha256' => hash_file('sha256', $repoRoot . '/' . $selectedInventoryPath),
                'dependencyDryRunTranscriptSha256' => hash_file('sha256', $repoRoot . '/' . $logRoot . '/runner-test-dependencies.txt'),
                'listTestsTranscriptSha256' => hash_file('sha256', $repoRoot . '/' . $logRoot . '/docx-targeted-list-tests.txt'),
                'targetedRunTranscriptSha256' => hash_file('sha256', $repoRoot . '/' . $logRoot . '/docx-targeted-run.txt'),
            ];
            $writeFile(
                $repoRoot,
                $artifactRoot . '/result.json',
                json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
            );

            $gate = $plan->resultArtifactGate($artifactRoot, $logRoot);
            $problems = implode("\n", $gate['problems']);
            $t->same(DocxUpstreamRunnerPlan::RESULT_ARTIFACT_GATE_STATUS_INVALID, $gate['status']);
            $t->same(false, $gate['admissionReady']);
            $t->contains('hard evidence gap', (string) $gate['evidenceGap']);
            $t->contains('dependency dry-run transcript must include the exact Cabal dry-run command line', $problems);
            $t->contains('list-tests transcript must include the exact Cabal/Tasty --list-tests command line', $problems);
            $t->contains('targeted-run transcript must include the exact targeted Cabal/Tasty command line', $problems);
            $t->contains('targeted-run transcript must contain Tasty result output for DOCX tests', $problems);
        } finally {
            $removeTree($repoRoot);
        }
    },

    'rejects non-targeted or empty runner result artifact admission' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $hydrateRunnerPlanFixture, $writeFile): void {
        $repoRoot = $makeTempRoot();
        try {
            $upstreamRoot = $repoRoot . '/cache/pandoc-current';
            $hydrateRunnerPlanFixture($upstreamRoot);
            $tool = dirname(__DIR__, 3) . '/tools/pandoc-docx-upstream-runner-plan.php';
            $artifactRoot = 'artifacts/docx-targeted-run';
            $logRoot = 'logs';
            $selectedInventoryPath = $artifactRoot . '/selected-test-inventory.json';

            $writeInventoryCommand = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg($tool)
                . ' --repo-root '
                . escapeshellarg($repoRoot)
                . ' --upstream-root cache/pandoc-current --write-selected-inventory '
                . escapeshellarg($selectedInventoryPath)
                . ' --json 2>&1';
            $writeOutput = [];
            $writeExitCode = 0;
            exec($writeInventoryCommand, $writeOutput, $writeExitCode);
            $t->same(0, $writeExitCode, implode("\n", $writeOutput));

            $inventoryPath = $repoRoot . '/' . $selectedInventoryPath;
            $inventory = json_decode((string) file_get_contents($inventoryPath), true, 512, JSON_THROW_ON_ERROR);
            $inventory['selection']['tastyPattern'] = 'Docx';
            $writeFile(
                $repoRoot,
                $selectedInventoryPath,
                json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
            );

            $writeFile($repoRoot, $logRoot . '/runner-test-dependencies.txt', "dependency dry-run transcript\n");
            $writeFile($repoRoot, $logRoot . '/docx-targeted-list-tests.txt', "Readers.Docx.reader simple paragraph\n");
            $writeFile($repoRoot, $logRoot . '/docx-targeted-run.txt', "targeted docx run transcript\n");

            $result = [
                'runnerExecuted' => true,
                'upstreamCommit' => DocxUpstreamRunnerPlan::PINNED_UPSTREAM_COMMIT,
                'commandLine' => 'cabal v2-run --offline --project-dir=. --builddir=.port-libs/pandoc-runner/cabal-build/docx-targeted-run test:test-pandoc',
                'exitCode' => 0,
                'runnerTarget' => DocxUpstreamRunnerPlan::RUNNER_TARGET,
                'tastyPattern' => DocxUpstreamRunnerPlan::TASTY_PATTERN,
                'selectedTestCount' => 0,
                'passedCount' => 0,
                'failedCount' => 0,
                'skippedCount' => 0,
                'startedAtUtc' => '2026-06-30T00:00:02Z',
                'finishedAtUtc' => '2026-06-30T00:00:01Z',
                'selectedTestInventorySha256' => hash_file('sha256', $inventoryPath),
                'dependencyDryRunTranscriptSha256' => hash_file('sha256', $repoRoot . '/' . $logRoot . '/runner-test-dependencies.txt'),
                'listTestsTranscriptSha256' => hash_file('sha256', $repoRoot . '/' . $logRoot . '/docx-targeted-list-tests.txt'),
                'targetedRunTranscriptSha256' => hash_file('sha256', $repoRoot . '/' . $logRoot . '/docx-targeted-run.txt'),
            ];
            $writeFile(
                $repoRoot,
                $artifactRoot . '/result.json',
                json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
            );

            $validateCommand = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg($tool)
                . ' --repo-root '
                . escapeshellarg($repoRoot)
                . ' --upstream-root cache/pandoc-current --validate-result-artifacts --artifact-root '
                . escapeshellarg($artifactRoot)
                . ' --log-root '
                . escapeshellarg($logRoot)
                . ' --json 2>&1';
            $validateOutput = [];
            $validateExitCode = 0;
            exec($validateCommand, $validateOutput, $validateExitCode);
            $t->same(0, $validateExitCode, implode("\n", $validateOutput));

            $decoded = json_decode(implode("\n", $validateOutput), true, 512, JSON_THROW_ON_ERROR);
            $gate = $decoded['resultArtifactGate'];
            $problems = implode("\n", $gate['problems']);
            $t->same(DocxUpstreamRunnerPlan::RESULT_ARTIFACT_GATE_STATUS_INVALID, $gate['status']);
            $t->same(false, $gate['admissionReady']);
            $t->contains('Selected test inventory artifact tastyPattern does not match the targeted DOCX pattern', $problems);
            $t->contains('Selected test inventory artifact sourceCommitMatchesPinned must be true before gating a runner result', $problems);
            $t->contains('result.json commandLine must exactly match the targeted DOCX runner command descriptor', $problems);
            $t->contains('result.json selectedTestCount must be greater than zero for a targeted DOCX runner result', $problems);
            $t->contains('result.json finishedAtUtc must not be earlier than startedAtUtc', $problems);
        } finally {
            $removeTree($repoRoot);
        }
    },

    'rejects empty roots before emitting runner commands' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn (): DocxUpstreamRunnerPlan => new DocxUpstreamRunnerPlan(''));
        $t->throws(InvalidArgumentException::class, static fn (): DocxUpstreamRunnerPlan => new DocxUpstreamRunnerPlan(__DIR__, ''));
    },
];
