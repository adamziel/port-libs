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

return [
    'reports blocked docx runner preflight without hydrated upstream source' => static function (TestRunner $t) use ($makeTempRoot, $removeTree): void {
        $repoRoot = $makeTempRoot();
        try {
            $report = (new DocxUpstreamRunnerPlan($repoRoot))->report();
            $text = DocxUpstreamRunnerPlan::formatTextReport($report);

            $t->same(DocxUpstreamRunnerPlan::STATUS_BLOCKED_MISSING_UPSTREAM_SOURCE, $report['status']);
            $t->same('targeted-docx-runner-preflight-plan-only', $report['evidenceKind']);
            $t->same(false, $report['runnerExecuted']);
            $t->same(false, $report['resultRecorded']);
            $t->same(false, $report['willExecute']);
            $t->same(DocxUpstreamRunnerPlan::requiredFiles(), $report['sourcePreflight']['missingFiles']);
            $t->same(DocxUpstreamRunnerPlan::requiredDirectories(), $report['sourcePreflight']['missingDirectories']);
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
            $t->same(DocxUpstreamRunnerPlan::LOCAL_READINESS_STATUS_BLOCKED, $readiness['status']);
            $t->same(false, $readiness['runnerExecutionAttemptedByThisTool']);
            $t->same(false, $readiness['resultRecordedByThisTool']);
            $t->same(false, $readiness['checks']['sourcePreflightReady']);
            $t->same(false, $readiness['checks']['upstreamRootPresent']);
            $t->contains('missing DOCX upstream source paths', implode("\n", $readiness['blockers']));
            $t->contains('Local execution readiness: blocked-targeted-docx-runner-local-prerequisites', $text);
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

            $report = (new DocxUpstreamRunnerPlan($repoRoot, 'cache/pandoc-current'))->report();

            $t->same(DocxUpstreamRunnerPlan::STATUS_READY, $report['status']);
            $t->same([], $report['sourcePreflight']['missingFiles']);
            $t->same([], $report['sourcePreflight']['missingDirectories']);
            $t->same(DocxUpstreamRunnerPlan::requiredFiles(), $report['sourcePreflight']['presentFiles']);
            $t->same(DocxUpstreamRunnerPlan::requiredDirectories(), $report['sourcePreflight']['presentDirectories']);
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
            $t->contains('exitCode', implode(',', $report['resultArtifactContract']['resultJsonRequiredFields']));
            $t->contains('record dependencyDryRun transcript first', implode("\n", $report['activationGate']));
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
            $t->same('static-docx-selected-test-inventory-only', $artifact['evidenceKind']);
            $t->same(['Tests.Readers.Docx.tests', 'Tests.Writers.Docx.tests'], $artifact['selection']['selectedGroups']);
            $t->same(1, $artifact['fixtures']['counts']['pairedRootDocxNativeStems']);
            $t->same(1, $artifact['fixtures']['counts']['goldenDocxPackageFiles']);
            $t->contains('not Tasty --list-tests output', $artifact['claim']);
        } finally {
            $removeTree($repoRoot);
        }
    },

    'cli validates targeted runner result artifacts without executing runner' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $hydrateRunnerPlanFixture, $writeFile): void {
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

            $writeFile($repoRoot, $logRoot . '/runner-test-dependencies.txt', "dependency dry-run transcript\n");
            $writeFile($repoRoot, $logRoot . '/docx-targeted-list-tests.txt', "Readers.Docx.reader simple paragraph\nWriters.Docx.writer plain paragraph\n");
            $writeFile($repoRoot, $logRoot . '/docx-targeted-run.txt', "targeted docx run transcript\n");

            $result = [
                'runnerExecuted' => true,
                'upstreamCommit' => DocxUpstreamRunnerPlan::PINNED_UPSTREAM_COMMIT,
                'commandLine' => 'cabal v2-run --offline --project-dir=. --builddir=.port-libs/pandoc-runner/cabal-build/docx-targeted-run test:test-pandoc -- --pattern ' . escapeshellarg(DocxUpstreamRunnerPlan::TASTY_PATTERN),
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
            $t->same([], $gate['problems']);
            $t->same(2, $gate['resultSummary']['selectedTestCount']);
            $t->same(hash_file('sha256', $repoRoot . '/' . $artifactRoot . '/result.json'), $gate['requiredArtifacts']['resultJson']['sha256']);
            $t->contains('does not execute Cabal/Tasty', $gate['claim']);
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
