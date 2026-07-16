<?php

declare(strict_types=1);

use PortLibs\Pandoc\ManUpstreamReaderEvidence;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-man-reader-evidence-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary man evidence directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary man evidence directory {$base}");
    }

    return $base;
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child)) {
            $removeTree($child);
        } else {
            @unlink($child);
        }
    }
    @rmdir($path);
};

$writeFile = static function (string $root, string $relativePath, string $contents): void {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to create fixture directory {$directory}");
    }
    file_put_contents($path, $contents);
};

$writeManEvidenceTree = static function (string $root) use ($writeFile): void {
    $writeFile($root, 'test/Tests/Readers/Man.hs', <<<'HS'
tests :: [TestTree]
tests = [
  testGroup "Macros" [
      "Bold" =:
      ".B foo"
      =?> para (strong "foo")
    , "comment  with \\\"" =:
      "Foo \\\" bar\n" =?> para (text "Foo")
    ]
  ]
HS);
    $writeFile($root, 'src/Text/Pandoc/Readers/Man.hs', "module Text.Pandoc.Readers.Man where\n");
};

$writeRunnerTranscripts = static function (string $root, array $paths) use ($writeFile): array {
    $records = [];
    foreach (array_values($paths) as $index => $path) {
        $contents = 'man runner transcript ' . (string) ($index + 1) . "\n" . $path . "\n";
        $writeFile($root, $path, $contents);
        $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $records[] = [
            'path' => $path,
            'sha256' => hash_file('sha256', $absolutePath),
            'bytes' => filesize($absolutePath),
        ];
    }

    return $records;
};

return [
    'reports skipped man reader evidence when upstream root is absent' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $report = (new ManUpstreamReaderEvidence($root, 'missing'))->report();
            $text = ManUpstreamReaderEvidence::formatTextReport($report);

            $t->same(1, $report['schemaVersion']);
            $t->same(ManUpstreamReaderEvidence::TOOL_NAME, $report['tool']);
            $t->same(ManUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
            $t->same(0, $report['denominator']['readerUnitCaseCount']);
            $t->same('not-evaluated-missing-upstream-root', $report['validation']['status']);
            $t->same(false, ManUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->same(true, ManUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same(true, ManUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
            $t->contains('Pandoc man reader evidence', $text);
            $t->contains('Runner plan: planned-not-run', $text);
        } finally {
            $removeTree($root);
        }
    },

    'parses upstream man reader unit denominator' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeManEvidenceTree): void {
        $root = $makeTempDir();
        try {
            $writeManEvidenceTree($root);
            $report = (new ManUpstreamReaderEvidence($root, '.'))->report();

            $t->same(ManUpstreamReaderEvidence::STATUS_COMPLETED, $report['status']);
            $t->same('valid-upstream-man-reader-denominator', $report['validation']['status']);
            $t->same([], $report['validation']['issues']);
            $t->same(2, $report['denominator']['readerUnitCaseCount']);
            $t->same('Bold', $report['denominator']['readerCases'][0]['name']);
            $t->contains('comment', $report['denominator']['readerCases'][1]['name']);
            $t->same(2, $report['sourceInventory']['presentFileCount']);
            $t->same(0, $report['sourceInventory']['missingFileCount']);
            $t->same(true, ManUpstreamReaderEvidence::hasRequiredReaderUnitCaseCount($report, 2));
            $t->same(true, ManUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->same(true, ManUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same(true, ManUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
            $t->same('$2 == "Readers" && $3 == "Man"', $report['runnerEvidence']['target']['tastyPattern']);
            $t->true(in_array('.port-libs/pandoc-runner/logs/man-targeted-run.txt', $report['runnerEvidence']['requiredTranscripts'], true));
            $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $report['claimBoundaries']['doesNotAssert'], true));
            $t->true(in_array('that upstream Haskell runner evidence is either explicitly not-run or supplied as a validated result artifact', $report['claimBoundaries']['doesAssert'], true));
        } finally {
            $removeTree($root);
        }
    },

    'validates supplied man reader upstream runner result artifact' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writeManEvidenceTree, $writeRunnerTranscripts): void {
        $root = $makeTempDir();
        try {
            $writeManEvidenceTree($root);
            $baseReport = (new ManUpstreamReaderEvidence($root, '.'))->report();
            $runnerPlan = $baseReport['runnerEvidence'];
            $transcripts = $writeRunnerTranscripts($root, $runnerPlan['requiredTranscripts']);
            $testNames = array_map(
                static fn (array $case): string => $case['name'],
                $baseReport['denominator']['readerCases']
            );
            $payload = [
                'schemaVersion' => 2,
                'runner' => 'Cabal/Tasty Pandoc man reader suite',
                'runnerExecuted' => true,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'commit' => ManUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT,
                ],
                'target' => $runnerPlan['target'],
                'command' => $runnerPlan['futureCommands'][2],
                'exitCode' => 0,
                'testCount' => count($testNames),
                'passedCount' => count($testNames),
                'failedCount' => 0,
                'skippedCount' => 0,
                'testNames' => $testNames,
                'transcriptPaths' => $runnerPlan['requiredTranscripts'],
                'transcripts' => $transcripts,
            ];
            $validPayload = $payload;
            $writeFile($root, 'result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $artifactPath = $root . '/result.json';
            $report = (new ManUpstreamReaderEvidence($root, '.', $artifactPath))->report();
            $text = ManUpstreamReaderEvidence::formatTextReport($report);

            $t->same('completed', $report['runnerEvidence']['status']);
            $t->same(true, $report['runnerEvidence']['executed']);
            $t->same('runner-result-artifact-validated', $report['runnerEvidence']['commandPlanStatus']);
            $t->same('valid-upstream-man-reader-runner-result-artifact', $report['runnerEvidence']['validation']['status']);
            $t->same([], $report['runnerEvidence']['validation']['issues']);
            $t->same('upstream-man-reader-runner-result-artifact', $report['runnerEvidence']['resultArtifact']['kind']);
            $t->same(true, $report['runnerEvidence']['resultArtifact']['present']);
            $t->same(hash_file('sha256', $artifactPath), $report['runnerEvidence']['resultArtifact']['sha256']);
            $t->same(filesize($artifactPath), $report['runnerEvidence']['resultArtifact']['bytes']);
            $t->same(ManUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $report['runnerEvidence']['upstreamBinding']['observedCommit']);
            $t->same($runnerPlan['target'], $report['runnerEvidence']['target']);
            $t->same($runnerPlan['futureCommands'][2], $report['runnerEvidence']['command']);
            $t->same($testNames, $report['runnerEvidence']['observed']['testNames']);
            $t->same($runnerPlan['requiredTranscripts'], $report['runnerEvidence']['observed']['transcriptPaths']);
            $t->same($transcripts, $report['runnerEvidence']['observed']['transcripts']);
            $t->same($transcripts, $report['runnerEvidence']['expected']['transcripts']);
            $t->same('upstream-man-reader-runner-transcript', $report['runnerEvidence']['transcripts'][0]['kind']);
            $t->same(true, $report['runnerEvidence']['transcripts'][0]['present']);
            $t->same(true, ManUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($report));
            $t->same(false, ManUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same(false, ManUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
            $t->contains('Runner status: completed', $text);
            $t->contains('Runner plan: runner-result-artifact-validated', $text);
            $t->contains('Runner result artifact: valid-upstream-man-reader-runner-result-artifact', $text);
            $t->contains('Supplied upstream Haskell/Cabal runner result artifact is validated', $text);

            $payload = $validPayload;
            $payload['failedCount'] = 1;
            $payload['exitCode'] = 1;
            $writeFile($root, 'bad-result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $badReport = (new ManUpstreamReaderEvidence($root, '.', $root . '/bad-result.json'))->report();

            $t->same('invalid', $badReport['runnerEvidence']['status']);
            $t->same('invalid-upstream-man-reader-runner-result-artifact', $badReport['runnerEvidence']['validation']['status']);
            $t->true(in_array('runner-result-exit-code-nonzero', $badReport['runnerEvidence']['validation']['issues'], true));
            $t->true(in_array('runner-result-counts-mismatch', $badReport['runnerEvidence']['validation']['issues'], true));
            $t->same(false, ManUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($badReport));

            $badTranscriptPayload = $validPayload;
            $badTranscriptPayload['transcripts'][0]['bytes'] = 0;
            $writeFile($root, 'bad-transcript-result.json', json_encode($badTranscriptPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $badTranscriptReport = (new ManUpstreamReaderEvidence($root, '.', $root . '/bad-transcript-result.json'))->report();

            $t->same('invalid', $badTranscriptReport['runnerEvidence']['status']);
            $t->true(in_array('runner-result-transcript-bytes-mismatch', $badTranscriptReport['runnerEvidence']['validation']['issues'], true));
            $t->same(false, ManUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($badTranscriptReport));
        } finally {
            $removeTree($root);
        }
    },

    'reports invalid man reader evidence when source files are missing' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile): void {
        $root = $makeTempDir();
        try {
            $writeFile($root, 'test/Tests/Readers/Man.hs', 'tests = []');

            $report = (new ManUpstreamReaderEvidence($root, '.'))->report();

            $t->same('invalid-upstream-man-reader-denominator', $report['validation']['status']);
            $t->true(in_array('missing-reader-source', $report['validation']['issues'], true));
            $t->true(in_array('no-man-reader-unit-cases', $report['validation']['issues'], true));
            $t->same(false, ManUpstreamReaderEvidence::hasNoValidationIssues($report));
        } finally {
            $removeTree($root);
        }
    },

    'cli gates man reader evidence counts and validation issues' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeManEvidenceTree): void {
        $root = $makeTempDir();
        try {
            $writeManEvidenceTree($root);
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-man-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg(dirname($root))
                . ' --upstream-root=' . escapeshellarg($root)
                . ' --json'
                . ' --require-test-count=2'
                . ' --require-runner-not-run'
                . ' --require-runner-plan'
                . ' --require-no-validation-issues';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same(2, $decoded['denominator']['readerUnitCaseCount']);
            $t->same('valid-upstream-man-reader-denominator', $decoded['validation']['status']);
            $t->same('not-run', $decoded['runnerEvidence']['status']);
            $t->same('planned-not-run', $decoded['runnerEvidence']['commandPlanStatus']);

            $failingCommand = str_replace('--require-test-count=2', '--require-test-count=3', $command) . ' 2>/dev/null';
            $failingOutput = [];
            $failingExitCode = 0;
            exec($failingCommand, $failingOutput, $failingExitCode);

            $t->same(1, $failingExitCode);
        } finally {
            $removeTree($root);
        }
    },

    'cli gates supplied man reader upstream runner result artifact' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writeManEvidenceTree, $writeRunnerTranscripts): void {
        $root = $makeTempDir();
        try {
            $writeManEvidenceTree($root);
            $baseReport = (new ManUpstreamReaderEvidence($root, '.'))->report();
            $runnerPlan = $baseReport['runnerEvidence'];
            $transcripts = $writeRunnerTranscripts($root, $runnerPlan['requiredTranscripts']);
            $testNames = array_map(
                static fn (array $case): string => $case['name'],
                $baseReport['denominator']['readerCases']
            );
            $payload = [
                'schemaVersion' => 2,
                'runner' => 'Cabal/Tasty Pandoc man reader suite',
                'runnerExecuted' => true,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'commit' => ManUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT,
                ],
                'target' => $runnerPlan['target'],
                'command' => $runnerPlan['futureCommands'][2],
                'exitCode' => 0,
                'testCount' => count($testNames),
                'passedCount' => count($testNames),
                'failedCount' => 0,
                'skippedCount' => 0,
                'testNames' => $testNames,
                'transcriptPaths' => $runnerPlan['requiredTranscripts'],
                'transcripts' => $transcripts,
            ];
            $validPayload = $payload;
            $writeFile($root, 'result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-man-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg($root)
                . ' --upstream-root=' . escapeshellarg($root)
                . ' --runner-result-artifact=' . escapeshellarg($root . '/result.json')
                . ' --json'
                . ' --require-runner-result-artifact';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same('completed', $decoded['runnerEvidence']['status']);
            $t->same('valid-upstream-man-reader-runner-result-artifact', $decoded['runnerEvidence']['validation']['status']);
            $t->same(true, ManUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($decoded));

            $payload = $validPayload;
            $payload['target']['tastyPattern'] = '$2 == "Readers" && $3 == "Markdown"';
            $writeFile($root, 'bad-result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $failingCommand = str_replace('result.json', 'bad-result.json', $command) . ' 2>/dev/null';
            $failingOutput = [];
            $failingExitCode = 0;
            exec($failingCommand, $failingOutput, $failingExitCode);

            $t->same(1, $failingExitCode);
        } finally {
            $removeTree($root);
        }
    },
];
