<?php

declare(strict_types=1);

use PortLibs\Pandoc\PptxUpstreamReaderEvidence;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-pptx-reader-evidence-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary PPTX evidence directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary PPTX evidence directory {$base}");
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

$writeGitHead = static function (string $root, string $commit): void {
    $gitDir = $root . DIRECTORY_SEPARATOR . '.git';
    $refDir = $gitDir . DIRECTORY_SEPARATOR . 'refs' . DIRECTORY_SEPARATOR . 'heads';
    if (!is_dir($refDir) && !mkdir($refDir, 0777, true) && !is_dir($refDir)) {
        throw new RuntimeException("Unable to create fixture git directory {$refDir}");
    }
    file_put_contents($gitDir . DIRECTORY_SEPARATOR . 'HEAD', "ref: refs/heads/main\n");
    file_put_contents($refDir . DIRECTORY_SEPARATOR . 'main', $commit . "\n");
};

$writePptxEvidenceTree = static function (string $root) use ($writeFile, $writeGitHead): void {
    $writeGitHead($root, PptxUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT);
    $writeFile($root, 'test/Tests/Readers/Pptx.hs', <<<'HS'
module Tests.Readers.Pptx (tests) where

tests = [ testGroup "basic"
          [ testCompare
            "text extraction"
            "pptx-reader/basic.pptx"
            "pptx-reader/basic.native"
          ]
        ]
HS);
    $writeFile($root, 'test/pptx-reader/basic.pptx', 'pptx bytes');
    $writeFile($root, 'test/pptx-reader/basic.native', '[Para [Str "pptx"]]');
    foreach ([
        'src/Text/Pandoc/Readers/Pptx.hs',
        'src/Text/Pandoc/Readers/Pptx/Parse.hs',
        'src/Text/Pandoc/Readers/Pptx/Shapes.hs',
        'src/Text/Pandoc/Readers/Pptx/Slides.hs',
        'src/Text/Pandoc/Readers/Pptx/SmartArt.hs',
    ] as $path) {
        $writeFile($root, $path, "module Stub where\n");
    }
};

$writeRunnerTranscripts = static function (string $root, array $paths, string $label = 'pptx', array $testNames = []) use ($writeFile): array {
    $records = [];
    foreach (array_values($paths) as $index => $path) {
        $contents = $label . " runner transcript " . (string) ($index + 1) . "\n" . $path . "\n";
        if (str_ends_with($path, '-targeted-list-tests.txt')) {
            $contents .= implode("\n", $testNames) . "\n";
        }
        if (str_ends_with($path, '-targeted-run.txt')) {
            $contents .= implode("\n", array_map(static fn (string $name): string => $name . ': OK', $testNames)) . "\n";
        }
        $contents .= "exitCode: 0\n";
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
    'reports skipped pptx reader evidence when upstream root is absent' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $report = (new PptxUpstreamReaderEvidence($root, 'missing'))->report();
            $text = PptxUpstreamReaderEvidence::formatTextReport($report);

            $t->same(1, $report['schemaVersion']);
            $t->same(PptxUpstreamReaderEvidence::TOOL_NAME, $report['tool']);
            $t->same(PptxUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
            $t->same(0, $report['denominator']['readerTestCompareCount']);
            $t->same(0, $report['denominator']['fixturePairCount']);
            $t->same('not-evaluated-missing-upstream-root', $report['validation']['status']);
            $t->same(false, PptxUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->same(true, PptxUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same(true, PptxUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
            $t->same('not-run', $report['runnerEvidence']['status']);
            $t->same(false, $report['runnerEvidence']['executed']);
            $t->same(null, $report['runnerEvidence']['command']);
            $t->same(null, $report['runnerEvidence']['resultArtifact']);
            $t->same('planned-not-run', $report['runnerEvidence']['commandPlanStatus']);
            $t->same(PptxUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $report['runnerEvidence']['upstreamBinding']['expectedCommit']);
            $t->same('$2 == "Readers" && $3 == "Pptx"', $report['runnerEvidence']['target']['tastyPattern']);
            $t->contains('Pandoc PPTX reader evidence', $text);
            $t->contains('Runner status: not-run', $text);
            $t->contains('Runner plan: planned-not-run', $text);
        } finally {
            $removeTree($root);
        }
    },
    'parses upstream pptx reader test denominator and fixture pairs' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writePptxEvidenceTree): void {
        $root = $makeTempDir();
        try {
            $writePptxEvidenceTree($root);
            $report = (new PptxUpstreamReaderEvidence($root, '.'))->report();

            $t->same(PptxUpstreamReaderEvidence::STATUS_COMPLETED, $report['status']);
            $t->same('valid-upstream-pptx-reader-denominator', $report['validation']['status']);
            $t->same([], $report['validation']['issues']);
            $t->same(1, $report['denominator']['readerTestCompareCount']);
            $t->same(1, $report['denominator']['fixturePairCount']);
            $t->same('text extraction', $report['denominator']['readerCases'][0]['name']);
            $t->same('pptx-reader/basic.pptx', $report['denominator']['readerCases'][0]['pptx']);
            $t->same('pptx-reader/basic.native', $report['denominator']['readerCases'][0]['native']);
            $t->same(0, $report['denominator']['unpairedPptxFixtureCount']);
            $t->same(0, $report['denominator']['unpairedNativeFixtureCount']);
            $t->same([], $report['denominator']['unpairedPptxFixtures']);
            $t->same([], $report['denominator']['unpairedNativeFixtures']);
            $t->same([], $report['denominator']['missingReferencedFiles']);
            $t->same([], $report['denominator']['unreferencedFixturePairs']);
            $t->same(6, $report['sourceInventory']['presentFileCount']);
            $t->same(0, $report['sourceInventory']['missingFileCount']);
            $t->same(true, PptxUpstreamReaderEvidence::hasRequiredReaderTestCount($report, 1));
            $t->same(true, PptxUpstreamReaderEvidence::hasRequiredFixturePairCount($report, 1));
            $t->same(true, PptxUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->same(true, PptxUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same(true, PptxUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
            $t->same('upstream-haskell-runner', $report['runnerEvidence']['scope']);
            $t->same('planned-not-run', $report['runnerEvidence']['commandPlanStatus']);
            $t->same(PptxUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $report['runnerEvidence']['upstreamBinding']['expectedCommit']);
            $t->same('test:test-pandoc', $report['runnerEvidence']['target']['testSuite']);
            $t->same(['Readers', 'Pptx'], $report['runnerEvidence']['target']['tastyGroupPath']);
            $t->same('$2 == "Readers" && $3 == "Pptx"', $report['runnerEvidence']['target']['tastyPattern']);
            $t->same('$2 == "Readers" && $3 == "Pptx"', $report['runnerEvidence']['futureCommands'][1]['arguments'][8]);
            $t->same('$2 == "Readers" && $3 == "Pptx"', $report['runnerEvidence']['futureCommands'][2]['arguments'][7]);
            $t->true(in_array('.port-libs/pandoc-runner/logs/pptx-targeted-run.txt', $report['runnerEvidence']['requiredTranscripts'], true));
            $t->true(in_array('.port-libs/pandoc-runner/artifacts/pptx-targeted-run/result.json', $report['runnerEvidence']['requiredArtifacts'], true));
            $mutatedReport = $report;
            $mutatedReport['runnerEvidence']['target']['tastyPattern'] = '$2 == "Readers" && $3 == "HTML"';
            $t->same(false, PptxUpstreamReaderEvidence::hasRunnerPlanEvidence($mutatedReport));
            $t->true(in_array('that this PHP evidence command executed upstream Haskell/Cabal/Tasty tests', $report['claimBoundaries']['doesNotAssert'], true));
            $t->true(in_array('that upstream Haskell runner evidence is explicitly not-run', $report['claimBoundaries']['doesAssert'], true));
            $t->true(in_array('the future upstream runner command plan targets test:test-pandoc Readers/Pptx at the pinned upstream commit without execution', $report['claimBoundaries']['doesAssert'], true));
        } finally {
            $removeTree($root);
        }
    },
    'requires pinned upstream git head for pptx reader denominator evidence' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writePptxEvidenceTree, $writeGitHead): void {
        $missingHeadRoot = $makeTempDir();
        try {
            $writePptxEvidenceTree($missingHeadRoot);
            $removeTree($missingHeadRoot . '/.git');
            $missingHeadReport = (new PptxUpstreamReaderEvidence($missingHeadRoot, '.'))->report();

            $t->same(PptxUpstreamReaderEvidence::STATUS_COMPLETED, $missingHeadReport['status']);
            $t->same(null, $missingHeadReport['upstream']['commit']);
            $t->same('invalid-upstream-pptx-reader-denominator', $missingHeadReport['validation']['status']);
            $t->true(in_array('upstream-git-head-unavailable', $missingHeadReport['validation']['issues'], true));
            $t->same(false, PptxUpstreamReaderEvidence::hasNoValidationIssues($missingHeadReport));
        } finally {
            $removeTree($missingHeadRoot);
        }

        $mismatchRoot = $makeTempDir();
        try {
            $writePptxEvidenceTree($mismatchRoot);
            $wrongCommit = str_repeat('1', 40);
            $writeGitHead($mismatchRoot, $wrongCommit);
            $mismatchReport = (new PptxUpstreamReaderEvidence($mismatchRoot, '.'))->report();

            $t->same($wrongCommit, $mismatchReport['upstream']['commit']);
            $t->same('invalid-upstream-pptx-reader-denominator', $mismatchReport['validation']['status']);
            $t->true(in_array('upstream-commit-mismatch', $mismatchReport['validation']['issues'], true));
            $t->same(false, PptxUpstreamReaderEvidence::hasNoValidationIssues($mismatchReport));
        } finally {
            $removeTree($mismatchRoot);
        }
    },
    'validates supplied pptx reader upstream runner result artifact' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writePptxEvidenceTree, $writeRunnerTranscripts): void {
        $root = $makeTempDir();
        try {
            $writePptxEvidenceTree($root);
            $baseReport = (new PptxUpstreamReaderEvidence($root, '.'))->report();
            $runnerPlan = $baseReport['runnerEvidence'];
            $transcripts = $writeRunnerTranscripts($root, $runnerPlan['requiredTranscripts']);
            $testNames = array_map(
                static fn (array $case): string => $case['name'],
                $baseReport['denominator']['readerCases']
            );
            $payload = [
                'schemaVersion' => 2,
                'runner' => 'Cabal/Tasty Pandoc PPTX reader suite',
                'runnerExecuted' => true,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'commit' => PptxUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT,
                ],
                'target' => $runnerPlan['target'],
                'command' => $runnerPlan['futureCommands'][2],
                'exitCode' => 0,
                'testCount' => 1,
                'passedCount' => 1,
                'failedCount' => 0,
                'skippedCount' => 0,
                'testNames' => $testNames,
                'transcriptPaths' => $runnerPlan['requiredTranscripts'],
                'transcripts' => $transcripts,
            ];
            $validPayload = $payload;
            $writeFile($root, 'result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $artifactPath = $root . '/result.json';
            $report = (new PptxUpstreamReaderEvidence($root, '.', $artifactPath))->report();
            $text = PptxUpstreamReaderEvidence::formatTextReport($report);

            $t->same('completed', $report['runnerEvidence']['status']);
            $t->same(true, $report['runnerEvidence']['executed']);
            $t->same('runner-result-artifact-validated', $report['runnerEvidence']['commandPlanStatus']);
            $t->same('valid-upstream-pptx-reader-runner-result-artifact', $report['runnerEvidence']['validation']['status']);
            $t->same([], $report['runnerEvidence']['validation']['issues']);
            $t->same('upstream-pptx-reader-runner-result-artifact', $report['runnerEvidence']['resultArtifact']['kind']);
            $t->same(true, $report['runnerEvidence']['resultArtifact']['present']);
            $t->same(hash_file('sha256', $artifactPath), $report['runnerEvidence']['resultArtifact']['sha256']);
            $t->same(filesize($artifactPath), $report['runnerEvidence']['resultArtifact']['bytes']);
            $t->same(PptxUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $report['runnerEvidence']['upstreamBinding']['observedCommit']);
            $t->same($runnerPlan['target'], $report['runnerEvidence']['target']);
            $t->same($runnerPlan['futureCommands'][2], $report['runnerEvidence']['command']);
            $t->same($testNames, $report['runnerEvidence']['observed']['testNames']);
            $t->same($runnerPlan['requiredTranscripts'], $report['runnerEvidence']['observed']['transcriptPaths']);
            $t->same($transcripts, $report['runnerEvidence']['observed']['transcripts']);
            $t->same($transcripts, $report['runnerEvidence']['expected']['transcripts']);
            $t->same('upstream-pptx-reader-runner-transcript', $report['runnerEvidence']['transcripts'][0]['kind']);
            $t->same(true, $report['runnerEvidence']['transcripts'][0]['present']);
            $t->same(true, PptxUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($report));
            $t->same(false, PptxUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same(false, PptxUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
            $t->contains('Runner status: completed', $text);
            $t->contains('Runner plan: runner-result-artifact-validated', $text);
            $t->contains('Runner result artifact: valid-upstream-pptx-reader-runner-result-artifact', $text);
            $t->contains('Supplied upstream Haskell/Cabal runner result artifact is validated', $text);

            $payload = $validPayload;
            $payload['target']['tastyPattern'] = '$2 == "Readers" && $3 == "EPUB"';
            $payload['testNames'] = ['wrong test'];
            $writeFile($root, 'bad-result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $badReport = (new PptxUpstreamReaderEvidence($root, '.', $root . '/bad-result.json'))->report();

            $t->same('invalid', $badReport['runnerEvidence']['status']);
            $t->same('invalid-upstream-pptx-reader-runner-result-artifact', $badReport['runnerEvidence']['validation']['status']);
            $t->true(in_array('runner-result-target-mismatch', $badReport['runnerEvidence']['validation']['issues'], true));
            $t->true(in_array('runner-result-test-names-mismatch', $badReport['runnerEvidence']['validation']['issues'], true));
            $t->same(false, PptxUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($badReport));

            $badTranscriptPayload = $validPayload;
            $badTranscriptPayload['transcripts'][0]['sha256'] = str_repeat('0', 64);
            $writeFile($root, 'bad-transcript-result.json', json_encode($badTranscriptPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $badTranscriptReport = (new PptxUpstreamReaderEvidence($root, '.', $root . '/bad-transcript-result.json'))->report();

            $t->same('invalid', $badTranscriptReport['runnerEvidence']['status']);
            $t->true(in_array('runner-result-transcript-sha256-mismatch', $badTranscriptReport['runnerEvidence']['validation']['issues'], true));
            $t->same(false, PptxUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($badTranscriptReport));
        } finally {
            $removeTree($root);
        }
    },
    'rejects pptx runner result artifact when upstream denominator is missing' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writeRunnerTranscripts): void {
        $root = $makeTempDir();
        try {
            $baseReport = (new PptxUpstreamReaderEvidence($root, 'missing-upstream'))->report();
            $runnerPlan = $baseReport['runnerEvidence'];
            $transcripts = $writeRunnerTranscripts($root, $runnerPlan['requiredTranscripts']);
            $payload = [
                'schemaVersion' => 2,
                'runner' => 'Cabal/Tasty Pandoc PPTX reader suite',
                'runnerExecuted' => true,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'commit' => PptxUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT,
                ],
                'target' => $runnerPlan['target'],
                'command' => $runnerPlan['futureCommands'][2],
                'exitCode' => 0,
                'testCount' => 0,
                'passedCount' => 0,
                'failedCount' => 0,
                'skippedCount' => 0,
                'testNames' => [],
                'transcriptPaths' => $runnerPlan['requiredTranscripts'],
                'transcripts' => $transcripts,
            ];
            $writeFile($root, 'result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            $report = (new PptxUpstreamReaderEvidence($root, 'missing-upstream', $root . '/result.json'))->report();
            $issues = $report['runnerEvidence']['validation']['issues'];

            $t->same(PptxUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
            $t->same('not-evaluated-missing-upstream-root', $report['validation']['status']);
            $t->same('invalid', $report['runnerEvidence']['status']);
            $t->same('runner-result-artifact-invalid', $report['runnerEvidence']['commandPlanStatus']);
            $t->same('invalid-upstream-pptx-reader-runner-result-artifact', $report['runnerEvidence']['validation']['status']);
            $t->true(in_array('runner-result-denominator-invalid', $issues, true));
            $t->true(in_array('runner-result-denominator-empty', $issues, true));
            $t->same(false, PptxUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($report));
        } finally {
            $removeTree($root);
        }
    },
    'reports checked-in current upstream pptx static evidence gate' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $report = (new PptxUpstreamReaderEvidence($repoRoot, 'missing-upstream-root-for-static-gate'))->report();
        $text = PptxUpstreamReaderEvidence::formatTextReport($report);
        $static = $report['staticCurrentEvidence'];
        $nativeParity = $static['nativeAstMappedParity'];
        $executableParity = $static['executableNativeAstMappedParity'];
        $reviewMetadata = $static['checkedInReviewMetadata'];
        $reviewFixturesByStem = [];
        foreach ($reviewMetadata['fixtures'] as $fixture) {
            $reviewFixturesByStem[$fixture['stem']] = $fixture;
        }
        $reviewFixture = $reviewFixturesByStem['chart-placeholder'];
        $reviewChart = $reviewFixture['charts'][0];
        $expectedChartPlaceholderReview = [
            'graphicUri' => 'http://schemas.openxmlformats.org/drawingml/2006/chart',
            'relationshipId' => 'rIdChart1',
            'relationshipType' => '',
            'target' => '',
            'partName' => '',
            'external' => false,
            'title' => '',
            'chartType' => 'unknown',
            'series' => [],
            'externalDataRelationshipIds' => [],
            'externalDataRelationships' => [],
            'issues' => ['unknown-chart-relationship'],
            'byteExposurePolicy' => 'chart-part-bytes-blocked',
            'reviewPolicy' => 'chart-metadata-and-cache-values-only',
        ];
        $expectedChartEmbeddedWorkbookReview = [
            'graphicUri' => 'http://schemas.openxmlformats.org/drawingml/2006/chart',
            'relationshipId' => 'rIdChart1',
            'relationshipType' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart',
            'target' => '../charts/chart1.xml',
            'partName' => 'ppt/charts/chart1.xml',
            'external' => false,
            'title' => 'Embedded Workbook Chart',
            'chartType' => 'unknown',
            'series' => [],
            'externalDataRelationshipIds' => ['rIdWorkbook'],
            'externalDataRelationships' => [
                [
                    'relationshipId' => 'rIdWorkbook',
                    'relationshipType' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/package',
                    'target' => '../embeddings/Microsoft_Excel_Worksheet1.xlsx',
                    'external' => false,
                    'partName' => 'ppt/embeddings/Microsoft_Excel_Worksheet1.xlsx',
                    'exists' => true,
                    'zipEntry' => 'ppt/embeddings/Microsoft_Excel_Worksheet1.xlsx',
                    'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'packageRelationshipRole' => 'embedded-workbook',
                    'embeddedWorkbook' => true,
                    'byteLength' => 35,
                    'compressedByteLength' => 35,
                    'compressionMethod' => 0,
                    'sha256' => '88240b7ef08d8ae0d2d98545f46f46a7fc38d4aa83749fb4b273c45d09393c3d',
                    'byteExposurePolicy' => 'package-part-bytes-hashed-not-exposed',
                ],
            ],
            'issues' => [],
            'byteExposurePolicy' => 'chart-part-bytes-blocked',
            'reviewPolicy' => 'chart-metadata-and-cache-values-only',
        ];
        $expectedSpeakerNoteReview = [
            'relationshipId' => 'rIdNotes',
            'relationshipType' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/notesSlide',
            'target' => '../notesSlides/notesSlide1.xml',
            'partName' => 'ppt/notesSlides/notesSlide1.xml',
            'text' => "Remember the launch date.\nAsk about migration risks.",
            'blockCount' => 2,
        ];
        $expectedCommentAuthorReview = [
            'name' => 'Reviewer',
            'initials' => 'RV',
            'lastIdx' => '1',
        ];
        $expectedCommentReview = [
            'id' => '1',
            'authorId' => '0',
            'author' => 'Reviewer',
            'initials' => 'RV',
            'date' => '2026-07-03T00:00:00Z',
            'text' => 'Reviewer-only note',
            'partName' => 'ppt/comments/comment1.xml',
            'x' => 120,
            'y' => 240,
        ];
        $pairsByStem = [];
        foreach ($static['checkedInFixturePairs'] as $fixturePair) {
            $pairsByStem[$fixturePair['stem']] = $fixturePair;
        }
        $pair = $pairsByStem['basic'];
        $alternateContentSkipPair = $pairsByStem['alternate-content-skip'];
        $backgroundImageSkipPair = $pairsByStem['background-image-skip'];
        $bodyBeforeTitlePair = $pairsByStem['body-before-title'];
        $minimalPair = $pairsByStem['minimal'];
        $missingRelationshipSkipPair = $pairsByStem['missing-relationship-skip'];
        $missingSlideRelationshipTypePair = $pairsByStem['missing-slide-relationship-type'];
        $multiParagraphTableCellPair = $pairsByStem['multi-paragraph-table-cell'];
        $multiParagraphTextboxPair = $pairsByStem['multi-paragraph-textbox'];
        $multipleParagraphPropertiesPair = $pairsByStem['multiple-paragraph-properties'];
        $namespaceAgnosticDrawingTextPair = $pairsByStem['namespace-agnostic-drawing-text'];
        $paragraphPropertyDescendantTextPair = $pairsByStem['paragraph-property-descendant-text'];
        $namespaceScopedTablePair = $pairsByStem['namespace-scoped-table'];
        $nestedListPair = $pairsByStem['nested-list'];
        $noSlidesPair = $pairsByStem['no-slides'];
        $emptyParagraphTextboxPair = $pairsByStem['empty-paragraph-textbox'];
        $firstTextBodyPair = $pairsByStem['first-text-body'];
        $firstTitlePlaceholderPair = $pairsByStem['first-title-placeholder'];
        $firstRunPropertySymbolPair = $pairsByStem['first-run-property-symbol'];
        $breakTabFieldPair = $pairsByStem['break-tab-field'];
        $whitespaceDrawingTextPair = $pairsByStem['whitespace-drawing-text'];
        $bulletsPair = $pairsByStem['bullets'];
        $bunoneWingdingsPair = $pairsByStem['bunone-wingdings'];
        $caseSensitivePlaceholderTypePair = $pairsByStem['case-sensitive-placeholder-type'];
        $wingdingsTypefaceCasePair = $pairsByStem['wingdings-typeface-case'];
        $centerTitlePlaceholderPair = $pairsByStem['center-title-placeholder'];
        $cdataEntityTextPair = $pairsByStem['cdata-entity-text'];
        $chartPlaceholderPair = $pairsByStem['chart-placeholder'];
        $chartEmbeddedWorkbookPair = $pairsByStem['chart-embedded-workbook'];
        $commentsIgnoredPair = $pairsByStem['comments-ignored'];
        $contentPartSkipPair = $pairsByStem['content-part-skip'];
        $diagramMissingRelsPair = $pairsByStem['diagram-missing-rels'];
        $diagramNoRelidsPair = $pairsByStem['diagram-no-relids'];
        $directDrawingParagraphsPair = $pairsByStem['direct-drawing-paragraphs'];
        $documentPropertiesPair = $pairsByStem['document-properties'];
        $dotPresentationTargetPair = $pairsByStem['dot-presentation-target'];
        $dotSlideTargetPair = $pairsByStem['dot-slide-target'];
        $duplicateRelationshipIdPair = $pairsByStem['duplicate-relationship-id'];
        $duplicateSlideReferencePair = $pairsByStem['duplicate-slide-reference'];
        $embedAndLinkImagePair = $pairsByStem['embed-and-link-image'];
        $connectorSkipPair = $pairsByStem['connector-skip'];
        $connectorTextSkipPair = $pairsByStem['connector-text-skip'];
        $embeddedImagePair = $pairsByStem['embedded-image'];
        $emptyBulletParagraphPair = $pairsByStem['empty-bullet-paragraph'];
        $emptyHeaderTablePair = $pairsByStem['empty-header-table'];
        $emptyTitlePlaceholderPair = $pairsByStem['empty-title-placeholder'];
        $generatedTablePair = $pairsByStem['generated-table'];
        $graphicNoUriPair = $pairsByStem['graphic-no-uri'];
        $tableSpanReviewPair = $pairsByStem['table-span-review'];
        $tableStylesRelationshipPair = $pairsByStem['table-styles-relationship'];
        $textCommentBoundaryPair = $pairsByStem['text-comment-boundary'];
        $textboxWithoutNonVisualPropertiesPair = $pairsByStem['textbox-without-nonvisual-properties'];
        $groupedShapeMediaReviewPair = $pairsByStem['grouped-shape-media-review'];
        $groupedShapesPair = $pairsByStem['grouped-shapes'];
        $hexListLevelPair = $pairsByStem['hex-list-level'];
        $signedBulletLevelPair = $pairsByStem['signed-bullet-level'];
        $hiddenShapeMetadataPair = $pairsByStem['hidden-shape-metadata'];
        $hiddenSlidePair = $pairsByStem['hidden-slide'];
        $ignoredSlideIdAttributesPair = $pairsByStem['ignored-slide-id-attributes'];
        $hyperlinkTextPair = $pairsByStem['hyperlink-text'];
        $inlineFormattingPair = $pairsByStem['inline-formatting'];
        $listContinuationPair = $pairsByStem['list-continuation'];
        $linkedImageSkipPair = $pairsByStem['linked-image-skip'];
        $literalImageTargetsPair = $pairsByStem['literal-image-targets'];
        $pictureShapeHyperlinkPair = $pairsByStem['picture-shape-hyperlink'];
        $mediaRelativeImageTargetPair = $pairsByStem['media-relative-image-target'];
        $twoSlidesPair = $pairsByStem['two-slides'];
        $unicodeDrawingTextPair = $pairsByStem['unicode-drawing-text'];
        $speakerNotesPair = $pairsByStem['speaker-notes'];
        $subtitlePlaceholderPair = $pairsByStem['subtitle-placeholder'];
        $numberedListPair = $pairsByStem['numbered-list'];
        $octalListLevelPair = $pairsByStem['octal-list-level'];
        $overflowBulletLevelPair = $pairsByStem['overflow-bullet-level'];
        $parenthesizedBulletLevelPair = $pairsByStem['parenthesized-bullet-level'];
        $pandocGeneratedImageAltTitlePair = $pairsByStem['pandoc-generated-image-alt-title'];
        $shapeOrderPair = $pairsByStem['shape-order'];
        $slideLayoutPlaceholderNoInheritPair = $pairsByStem['slide-layout-placeholder-no-inherit'];
        $slidePlaceholdersPair = $pairsByStem['slide-placeholders'];
        $smartartHierarchyPair = $pairsByStem['smartart-hierarchy'];
        $smartartTitleFallbackPair = $pairsByStem['smartart-title-fallback'];
        $noTitleFallbackPair = $pairsByStem['no-title-fallback'];
        $nonRelationshipChildRelationshipsPair = $pairsByStem['non-relationship-child-relationships'];
        $paragraphlessTextboxPair = $pairsByStem['paragraphless-textbox'];
        $endParagraphSymbolPair = $pairsByStem['end-paragraph-symbol'];
        $externalModeSlideTargetPair = $pairsByStem['external-mode-slide-target'];
        $externalRichMediaSkipPair = $pairsByStem['external-rich-media-skip'];
        $rootTargetmodeExternalPair = $pairsByStem['root-targetmode-external'];
        $percentEncodedTargetPair = $pairsByStem['percent-encoded-target'];
        $qualifiedBulletLevelPair = $pairsByStem['qualified-bullet-level'];
        $qualifiedPictureMetadataPair = $pairsByStem['qualified-picture-metadata'];
        $queryFragmentPresentationTargetPair = $pairsByStem['query-fragment-presentation-target'];
        $relPrefixImageSkipPair = $pairsByStem['rel-prefix-image-skip'];
        $repeatedSlashPresentationTargetPair = $pairsByStem['repeated-slash-presentation-target'];
        $repeatedSlashSlideTargetPair = $pairsByStem['repeated-slash-slide-target'];
        $richMediaSkipPair = $pairsByStem['rich-media-skip'];
        $rootedSlideTargetPair = $pairsByStem['rooted-slide-target'];
        $unknownGraphicUriPair = $pairsByStem['unknown-graphic-uri'];
        $wrongTypedSlideRelationshipPair = $pairsByStem['wrong-typed-slide-relationship'];

        $t->same(PptxUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
        $t->same('valid-checked-in-current-pptx-reader-evidence', $static['validation']['status']);
        $t->same([], $static['validation']['issues']);
        $t->same(1, $static['readerDenominator']['expectedCompareCount']);
        $t->same('text extraction', $static['readerDenominator']['expectedReaderCases'][0]['name']);
        $t->same('pptx-reader/basic.pptx', $static['readerDenominator']['expectedReaderCases'][0]['pptx']);
        $t->same('pptx-reader/basic.native', $static['readerDenominator']['expectedReaderCases'][0]['native']);
        $t->same(101, $static['checkedInFixturePairCount']);
        $t->same(0, $static['checkedInUnpairedPptxFixtureCount']);
        $t->same(0, $static['checkedInUnpairedNativeFixtureCount']);
        $t->same([], $static['checkedInUnpairedPptxFixtures']);
        $t->same([], $static['checkedInUnpairedNativeFixtures']);
        $t->same('checked-in-current-pptx-native-normalized-ast-parity', $nativeParity['kind']);
        $t->same('completed', $nativeParity['status']);
        $t->same(false, $nativeParity['skipped']);
        $t->same(101, $nativeParity['requiredPairCount']);
        $t->same(101, $nativeParity['totalPairCount']);
        $t->same(101, $nativeParity['comparedPairCount']);
        $t->same(101, $nativeParity['pptxParsedCount']);
        $t->same(101, $nativeParity['nativeParsedCount']);
        $t->same(101, $nativeParity['bothParsedCount']);
        $t->same(0, $nativeParity['unpairedPptxCount']);
        $t->same(0, $nativeParity['unpairedNativeCount']);
        $t->same([], $nativeParity['unpairedPptxFixtures']);
        $t->same([], $nativeParity['unpairedNativeFixtures']);
        $t->same(0, $nativeParity['parseFailureCount']);
        $t->same(101, $nativeParity['normalizedAstMatchCount']);
        $t->same(0, $nativeParity['normalizedAstMismatchCount']);
        $t->same(101, count($nativeParity['fixtureComparisons']));
        $t->same([], array_values(array_filter(
            $nativeParity['fixtureComparisons'],
            static fn (array $row): bool => ($row['status'] ?? null) !== 'matched'
        )));
        $t->same('normalized-ast-equality-observed-not-runner-parity', $nativeParity['astParityStatus']);
        $t->same(true, $nativeParity['hasRequiredMappedParity']);
        $t->same('selected-pptx-native-fixture-corpus-coverage', $nativeParity['orderedRemainingGaps'][2]['id']);
        $t->same('covered-by-current-selected-corpus-evidence', $nativeParity['orderedRemainingGaps'][2]['status']);
        $t->same('checked-in-current-pptx-executable-native-normalized-ast-parity', $executableParity['kind']);
        $t->same('completed', $executableParity['status']);
        $t->same(false, $executableParity['skipped']);
        $t->same(101, $executableParity['requiredPptxCount']);
        $t->same('valid-checked-in-current-pptx-executable-native-ast-parity', $executableParity['validation']['status']);
        $t->same([], $executableParity['validation']['issues']);
        $t->same('lanes/pandoc/fixtures/upstream-current-pptx-reader/checked-in.executable-native-ast.json', $executableParity['snapshotFile']['path']);
        $t->same(true, $executableParity['snapshotFile']['present']);
        $t->same('2c1b67e9ff1e2635b4f555a51e7eee1f7d50b20a3b643929537905eda9550e01', $executableParity['snapshotFile']['sha256']);
        $t->same(43795, $executableParity['snapshotFile']['bytes']);
        $t->same('2026-07-05', $executableParity['capturedDate']);
        $t->same('pandoc 3.10', $executableParity['requiredPandocVersion']);
        $t->same('pandoc 3.10', $executableParity['pandocVersion']);
        $t->same(101, $executableParity['totalPptxCount']);
        $t->same(101, $executableParity['comparedPptxCount']);
        $t->same(101, $executableParity['localParsedCount']);
        $t->same(101, $executableParity['pandocParsedCount']);
        $t->same(101, $executableParity['nativeFixtureParsedCount']);
        $t->same(101, $executableParity['bothParsedCount']);
        $t->same(0, $executableParity['parseFailureCount']);
        $t->same(101, $executableParity['normalizedAstMatchCount']);
        $t->same(0, $executableParity['normalizedAstMismatchCount']);
        $t->same(101, $executableParity['pandocNativeFixtureComparedCount']);
        $t->same(101, $executableParity['pandocNativeFixtureMatchCount']);
        $t->same(0, $executableParity['pandocNativeFixtureMismatchCount']);
        $t->same('normalized-ast-equality-observed-against-pandoc-executable', $executableParity['astParityStatus']);
        $t->same(true, $executableParity['hasRequiredExecutableParity']);
        $t->same(true, $executableParity['hasRequiredPandocVersion']);
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticExecutableNativeAstParity($report));
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticReviewMetadata($report));
        $t->same('checked-in-current-pptx-review-metadata', $reviewMetadata['kind']);
        $t->same('valid-checked-in-current-pptx-review-metadata', $reviewMetadata['validation']['status']);
        $t->same([], $reviewMetadata['validation']['issues']);
        $t->same(4, $reviewMetadata['fixtureCount']);
        $t->same(2, $reviewMetadata['chartReviewFixtureCount']);
        $t->same(2, $reviewMetadata['chartReviewCount']);
        $t->same(1, $reviewMetadata['speakerNotesFixtureCount']);
        $t->same(1, $reviewMetadata['speakerNoteCount']);
        $t->same(1, $reviewMetadata['commentFixtureCount']);
        $t->same(1, $reviewMetadata['commentAuthorCount']);
        $t->same(1, $reviewMetadata['commentCount']);
        $t->same('chart-placeholder', $reviewFixture['stem']);
        $t->same(1, $reviewFixture['chartCount']);
        $t->same($expectedChartPlaceholderReview, $reviewChart);
        $t->same('chart-embedded-workbook', $reviewFixturesByStem['chart-embedded-workbook']['stem']);
        $t->same(1, $reviewFixturesByStem['chart-embedded-workbook']['chartCount']);
        $t->same($expectedChartEmbeddedWorkbookReview, $reviewFixturesByStem['chart-embedded-workbook']['charts'][0]);
        $t->same('speaker-notes', $reviewFixturesByStem['speaker-notes']['stem']);
        $t->same(0, $reviewFixturesByStem['speaker-notes']['chartCount']);
        $t->same(1, $reviewFixturesByStem['speaker-notes']['speakerNoteCount']);
        $t->same($expectedSpeakerNoteReview, $reviewFixturesByStem['speaker-notes']['speakerNotes'][0]);
        $t->same('comments-ignored', $reviewFixturesByStem['comments-ignored']['stem']);
        $t->same(0, $reviewFixturesByStem['comments-ignored']['chartCount']);
        $t->same(1, $reviewFixturesByStem['comments-ignored']['commentAuthorCount']);
        $t->same(1, $reviewFixturesByStem['comments-ignored']['commentCount']);
        $t->same($expectedCommentAuthorReview, $reviewFixturesByStem['comments-ignored']['commentAuthors'][0]);
        $t->same($expectedCommentReview, $reviewFixturesByStem['comments-ignored']['comments'][0]);
        $t->same('basic', $pair['stem']);
        $t->same('pptx-reader/basic.pptx|pptx-reader/basic.native', $pair['pairKey']);
        $t->same('e48fd9c2f8369d1792197e301d5fea676bf6e51097a24af7d85831a6f96dc2dc', $pair['checkedInPptx']['sha256']);
        $t->same('42804b9b1954094a4b0ff0be20084e2e6d9bc0a84272f34f7f219f82505da6b4', $pair['checkedInNative']['sha256']);
        $t->same(111674, $pair['checkedInPptx']['bytes']);
        $t->same(3966, $pair['checkedInNative']['bytes']);
        $t->same('alternate-content-skip', $alternateContentSkipPair['stem']);
        $t->same('f84d950060c44f8f0b85ed15f29c5760c49aaf445b101806d70711507c93a194', $alternateContentSkipPair['checkedInPptx']['sha256']);
        $t->same('d067f8fa32d162f9bc33280c7bc4b725fb1543b454861b7f61e31ef2a18acea1', $alternateContentSkipPair['checkedInNative']['sha256']);
        $t->same(1534, $alternateContentSkipPair['checkedInPptx']['bytes']);
        $t->same(185, $alternateContentSkipPair['checkedInNative']['bytes']);
        $t->same('background-image-skip', $backgroundImageSkipPair['stem']);
        $t->same('generated background image relationship skip parity', $backgroundImageSkipPair['name']);
        $t->same('pptx-reader/background-image-skip.pptx|pptx-reader/background-image-skip.native', $backgroundImageSkipPair['pairKey']);
        $t->same('e9483d1883b3ea011c81ddf140e6400334f3aa3d52acd3b5f68cf81f0ead1769', $backgroundImageSkipPair['checkedInPptx']['sha256']);
        $t->same('5c14cd84da6db538df5569de41e3f5a29ac75b4030de9e3e0b4e28caf14e7b5d', $backgroundImageSkipPair['checkedInNative']['sha256']);
        $t->same(2328, $backgroundImageSkipPair['checkedInPptx']['bytes']);
        $t->same(127, $backgroundImageSkipPair['checkedInNative']['bytes']);
        $t->same('body-before-title', $bodyBeforeTitlePair['stem']);
        $t->same('generated body-before-title placeholder ordering parity', $bodyBeforeTitlePair['name']);
        $t->same('pptx-reader/body-before-title.pptx|pptx-reader/body-before-title.native', $bodyBeforeTitlePair['pairKey']);
        $t->same('0211c524f44cac1d910cb51f9540bf2fa6dd6b497d3c018ff4f06517be6564c1', $bodyBeforeTitlePair['checkedInPptx']['sha256']);
        $t->same('e0b1dacb8bd85677d2556009e0a79e4443680cf98e45e26c4a3a0747800d7453', $bodyBeforeTitlePair['checkedInNative']['sha256']);
        $t->same(1519, $bodyBeforeTitlePair['checkedInPptx']['bytes']);
        $t->same(132, $bodyBeforeTitlePair['checkedInNative']['bytes']);
        $t->same('minimal', $minimalPair['stem']);
        $t->same('generated minimal text extraction parity', $minimalPair['name']);
        $t->same('pptx-reader/minimal.pptx|pptx-reader/minimal.native', $minimalPair['pairKey']);
        $t->same('f4852d7b0455ae99a8ef2b3d419cb2aa9ab2f8b5c4167e3770a38483ab36f202', $minimalPair['checkedInPptx']['sha256']);
        $t->same('6ec8b821c9a28c12ca65c771d7dcb6df0ec7f9f91b139e318d4cdbbd4fde4c76', $minimalPair['checkedInNative']['sha256']);
        $t->same(1502, $minimalPair['checkedInPptx']['bytes']);
        $t->same(119, $minimalPair['checkedInNative']['bytes']);
        $t->same('missing-relationship-skip', $missingRelationshipSkipPair['stem']);
        $t->same('generated missing relationship skip parity', $missingRelationshipSkipPair['name']);
        $t->same('pptx-reader/missing-relationship-skip.pptx|pptx-reader/missing-relationship-skip.native', $missingRelationshipSkipPair['pairKey']);
        $t->same('0a9ed423c8987719d2b5ac4ed3367db507ece227737e141b371812b57c18e77a', $missingRelationshipSkipPair['checkedInPptx']['sha256']);
        $t->same('e751a414543010757345bac58bc4fb6157c1c99cbbd0e958f39753c18db3e5cd', $missingRelationshipSkipPair['checkedInNative']['sha256']);
        $t->same(1795, $missingRelationshipSkipPair['checkedInPptx']['bytes']);
        $t->same(144, $missingRelationshipSkipPair['checkedInNative']['bytes']);
        $t->same('missing-slide-relationship-type', $missingSlideRelationshipTypePair['stem']);
        $t->same('generated missing slide relationship Type fallback parity', $missingSlideRelationshipTypePair['name']);
        $t->same('pptx-reader/missing-slide-relationship-type.pptx|pptx-reader/missing-slide-relationship-type.native', $missingSlideRelationshipTypePair['pairKey']);
        $t->same('ba37020b6c4a1118758c3dc53292e7137e52cd2188ac6c46ce236f9000b442b4', $missingSlideRelationshipTypePair['checkedInPptx']['sha256']);
        $t->same('10d3dad7c71db5cf453d3602d085f9c9cf63a6d63d4ae0558b8e75b7eabde390', $missingSlideRelationshipTypePair['checkedInNative']['sha256']);
        $t->same(1707, $missingSlideRelationshipTypePair['checkedInPptx']['bytes']);
        $t->same(157, $missingSlideRelationshipTypePair['checkedInNative']['bytes']);
        $t->same('multi-paragraph-table-cell', $multiParagraphTableCellPair['stem']);
        $t->same('generated multi-paragraph table cell flattening parity', $multiParagraphTableCellPair['name']);
        $t->same('pptx-reader/multi-paragraph-table-cell.pptx|pptx-reader/multi-paragraph-table-cell.native', $multiParagraphTableCellPair['pairKey']);
        $t->same('ea8c0da62e75f272bd9f3ae72e9c646086e698ee518e8b7c66ff59ed8eafdd19', $multiParagraphTableCellPair['checkedInPptx']['sha256']);
        $t->same('779fba71bf9c3b12489fe696e19362fc4c435f9cc00c41958887a5d16fa1cff6', $multiParagraphTableCellPair['checkedInNative']['sha256']);
        $t->same(1622, $multiParagraphTableCellPair['checkedInPptx']['bytes']);
        $t->same(1224, $multiParagraphTableCellPair['checkedInNative']['bytes']);
        $t->same('multi-paragraph-textbox', $multiParagraphTextboxPair['stem']);
        $t->same('generated multi-paragraph text box parity', $multiParagraphTextboxPair['name']);
        $t->same('pptx-reader/multi-paragraph-textbox.pptx|pptx-reader/multi-paragraph-textbox.native', $multiParagraphTextboxPair['pairKey']);
        $t->same('f586b777919bb9266acec04640e2992be888ab987009e9d6866dc440d5f3060e', $multiParagraphTextboxPair['checkedInPptx']['sha256']);
        $t->same('1201499244544e7be60096ac6d0a434ed10036429d0bf18b6dcf2807eb8ad8fd', $multiParagraphTextboxPair['checkedInNative']['sha256']);
        $t->same(1519, $multiParagraphTextboxPair['checkedInPptx']['bytes']);
        $t->same(177, $multiParagraphTextboxPair['checkedInNative']['bytes']);
        $t->same('multiple-paragraph-properties', $multipleParagraphPropertiesPair['stem']);
        $t->same('generated first paragraph properties child parity', $multipleParagraphPropertiesPair['name']);
        $t->same('pptx-reader/multiple-paragraph-properties.pptx|pptx-reader/multiple-paragraph-properties.native', $multipleParagraphPropertiesPair['pairKey']);
        $t->same('c2cf31c18f58461b4f016edc1e005124c9a7f5c5405f52a8d7c4e3ac3a267818', $multipleParagraphPropertiesPair['checkedInPptx']['sha256']);
        $t->same('dd233a289b57a8fd950c49a5cb4d60835cd9a39905c41701278132a596a413e8', $multipleParagraphPropertiesPair['checkedInNative']['sha256']);
        $t->same(1473, $multipleParagraphPropertiesPair['checkedInPptx']['bytes']);
        $t->same(297, $multipleParagraphPropertiesPair['checkedInNative']['bytes']);
        $t->same('namespace-agnostic-drawing-text', $namespaceAgnosticDrawingTextPair['stem']);
        $t->same('generated terminal nested drawing text parity', $namespaceAgnosticDrawingTextPair['name']);
        $t->same('pptx-reader/namespace-agnostic-drawing-text.pptx|pptx-reader/namespace-agnostic-drawing-text.native', $namespaceAgnosticDrawingTextPair['pairKey']);
        $t->same('6203769629208902d006b9a481e8a3a75e31d56b4d470da081dfa78049df4a95', $namespaceAgnosticDrawingTextPair['checkedInPptx']['sha256']);
        $t->same('7e162f0d57dcd35ee21a78ef454f31fa4862dae95294437a6e71ce7dd85a02c3', $namespaceAgnosticDrawingTextPair['checkedInNative']['sha256']);
        $t->same(1411, $namespaceAgnosticDrawingTextPair['checkedInPptx']['bytes']);
        $t->same(166, $namespaceAgnosticDrawingTextPair['checkedInNative']['bytes']);
        $t->same('paragraph-property-descendant-text', $paragraphPropertyDescendantTextPair['stem']);
        $t->same('generated paragraph-property descendant text parity', $paragraphPropertyDescendantTextPair['name']);
        $t->same('pptx-reader/paragraph-property-descendant-text.pptx|pptx-reader/paragraph-property-descendant-text.native', $paragraphPropertyDescendantTextPair['pairKey']);
        $t->same('7e1190e41ff541fdd9f09dd3c67110cfda46f4aed4fd2b3c9fa60459e73e4ccf', $paragraphPropertyDescendantTextPair['checkedInPptx']['sha256']);
        $t->same('758e867887f88b35ec6bdc27ed1fe04baa4b6bbc5d535c1576f40f8c81aa3bf3', $paragraphPropertyDescendantTextPair['checkedInNative']['sha256']);
        $t->same(1413, $paragraphPropertyDescendantTextPair['checkedInPptx']['bytes']);
        $t->same(185, $paragraphPropertyDescendantTextPair['checkedInNative']['bytes']);
        $t->same('namespace-scoped-table', $namespaceScopedTablePair['stem']);
        $t->same('generated namespace-scoped table boundary parity', $namespaceScopedTablePair['name']);
        $t->same('pptx-reader/namespace-scoped-table.pptx|pptx-reader/namespace-scoped-table.native', $namespaceScopedTablePair['pairKey']);
        $t->same('81c2a77fe8ebd2965b39506d4ea52400617133ab74b8c29d39cae18df93ae83d', $namespaceScopedTablePair['checkedInPptx']['sha256']);
        $t->same('7f85d7011628e5ca386fc75a5bf138674f47a9b3d2004a2f58df144bd920336e', $namespaceScopedTablePair['checkedInNative']['sha256']);
        $t->same(1609, $namespaceScopedTablePair['checkedInPptx']['bytes']);
        $t->same(1039, $namespaceScopedTablePair['checkedInNative']['bytes']);
        $t->same('nested-list', $nestedListPair['stem']);
        $t->same('generated adjacent list-level split parity', $nestedListPair['name']);
        $t->same('pptx-reader/nested-list.pptx|pptx-reader/nested-list.native', $nestedListPair['pairKey']);
        $t->same('c85b56c09a3568286e4c0d7b1979d88b700d5f609e121955c691a58f2bb97ff0', $nestedListPair['checkedInPptx']['sha256']);
        $t->same('395c237357a332023f6bb3c991f2f84d54be6fb277ce964cdaad6d9ffe2336a6', $nestedListPair['checkedInNative']['sha256']);
        $t->same(1703, $nestedListPair['checkedInPptx']['bytes']);
        $t->same(253, $nestedListPair['checkedInNative']['bytes']);
        $t->same('no-slides', $noSlidesPair['stem']);
        $t->same('generated no-slide presentation parity', $noSlidesPair['name']);
        $t->same('pptx-reader/no-slides.pptx|pptx-reader/no-slides.native', $noSlidesPair['pairKey']);
        $t->same('06ee4f11b616153b569aba25917a0c77fd963ad825e65f3a61baf6d83988aead', $noSlidesPair['checkedInPptx']['sha256']);
        $t->same('37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570', $noSlidesPair['checkedInNative']['sha256']);
        $t->same(781, $noSlidesPair['checkedInPptx']['bytes']);
        $t->same(3, $noSlidesPair['checkedInNative']['bytes']);
        $t->same('no-title-fallback', $noTitleFallbackPair['stem']);
        $t->same('generated no-title slide fallback parity', $noTitleFallbackPair['name']);
        $t->same('pptx-reader/no-title-fallback.pptx|pptx-reader/no-title-fallback.native', $noTitleFallbackPair['pairKey']);
        $t->same('de4ad6bc1bf66072bdcb96e31390955f4a283e3be94b0f691dc96ba36765f557', $noTitleFallbackPair['checkedInPptx']['sha256']);
        $t->same('fcd4183bbfebc6ecd4118786cf7bbc1fb760f2e385d6bbb9bab6031851557763', $noTitleFallbackPair['checkedInNative']['sha256']);
        $t->same(1533, $noTitleFallbackPair['checkedInPptx']['bytes']);
        $t->same(103, $noTitleFallbackPair['checkedInNative']['bytes']);
        $t->same('non-relationship-child-relationships', $nonRelationshipChildRelationshipsPair['stem']);
        $t->same('generated non-Relationship child relationship parity', $nonRelationshipChildRelationshipsPair['name']);
        $t->same('pptx-reader/non-relationship-child-relationships.pptx|pptx-reader/non-relationship-child-relationships.native', $nonRelationshipChildRelationshipsPair['pairKey']);
        $t->same('f528a1f1d7a380770ac34c583c0a9eb3279e895871d01bf1d707f3255c88addc', $nonRelationshipChildRelationshipsPair['checkedInPptx']['sha256']);
        $t->same('2f44281be9a23ee4bcac11b033ee74320071e1ac1733d479051beffd19d49124', $nonRelationshipChildRelationshipsPair['checkedInNative']['sha256']);
        $t->same(1867, $nonRelationshipChildRelationshipsPair['checkedInPptx']['bytes']);
        $t->same(247, $nonRelationshipChildRelationshipsPair['checkedInNative']['bytes']);
        $t->same('paragraphless-textbox', $paragraphlessTextboxPair['stem']);
        $t->same('generated paragraphless text box skip parity', $paragraphlessTextboxPair['name']);
        $t->same('pptx-reader/paragraphless-textbox.pptx|pptx-reader/paragraphless-textbox.native', $paragraphlessTextboxPair['pairKey']);
        $t->same('5ecbb58a28c01bba60dab87081eb69b475fd87817410197f87d003443e38a49b', $paragraphlessTextboxPair['checkedInPptx']['sha256']);
        $t->same('1b8599dd7c13c0c93a592eff7fae16bc53bb07d3ae53b788ecd7874b7e8106e8', $paragraphlessTextboxPair['checkedInNative']['sha256']);
        $t->same(1544, $paragraphlessTextboxPair['checkedInPptx']['bytes']);
        $t->same(113, $paragraphlessTextboxPair['checkedInNative']['bytes']);
        $t->same('empty-paragraph-textbox', $emptyParagraphTextboxPair['stem']);
        $t->same('generated explicit empty paragraph text box parity', $emptyParagraphTextboxPair['name']);
        $t->same('pptx-reader/empty-paragraph-textbox.pptx|pptx-reader/empty-paragraph-textbox.native', $emptyParagraphTextboxPair['pairKey']);
        $t->same('3c2746d48004a382c77a6b0780c31dae0246c9f9063251db2f93bcc16e688655', $emptyParagraphTextboxPair['checkedInPptx']['sha256']);
        $t->same('9a1dd6f8ddf28f555cd1f128f5e24864284f1a721d2ae3c1e4598ebdcbe9b21b', $emptyParagraphTextboxPair['checkedInNative']['sha256']);
        $t->same(1519, $emptyParagraphTextboxPair['checkedInPptx']['bytes']);
        $t->same(169, $emptyParagraphTextboxPair['checkedInNative']['bytes']);
        $t->same('empty-title-placeholder', $emptyTitlePlaceholderPair['stem']);
        $t->same('generated empty title placeholder fallback parity', $emptyTitlePlaceholderPair['name']);
        $t->same('pptx-reader/empty-title-placeholder.pptx|pptx-reader/empty-title-placeholder.native', $emptyTitlePlaceholderPair['pairKey']);
        $t->same('4a15fd6e8508407c05e09b9c1fcd3481df624d810b6a4443ec2a99271bd83d12', $emptyTitlePlaceholderPair['checkedInPptx']['sha256']);
        $t->same('aa2979e514f11a5ef811ef8a1a9c2b7a5e61fbf6edc3323dbb8f80d11de1fb3f', $emptyTitlePlaceholderPair['checkedInNative']['sha256']);
        $t->same(1495, $emptyTitlePlaceholderPair['checkedInPptx']['bytes']);
        $t->same(98, $emptyTitlePlaceholderPair['checkedInNative']['bytes']);
        $t->same('end-paragraph-symbol', $endParagraphSymbolPair['stem']);
        $t->same('generated end-paragraph Wingdings symbol locality parity', $endParagraphSymbolPair['name']);
        $t->same('pptx-reader/end-paragraph-symbol.pptx|pptx-reader/end-paragraph-symbol.native', $endParagraphSymbolPair['pairKey']);
        $t->same('5bf9f05d16bbd53092f9e7c4d7cc68be91370d4c2027d59aa4b31f7649569f0a', $endParagraphSymbolPair['checkedInPptx']['sha256']);
        $t->same('bbcbc22ada6d869940c3b7512ea52562df6a54e6b872a23fb7d536857dbb0466', $endParagraphSymbolPair['checkedInNative']['sha256']);
        $t->same(1654, $endParagraphSymbolPair['checkedInPptx']['bytes']);
        $t->same(100, $endParagraphSymbolPair['checkedInNative']['bytes']);
        $t->same('external-mode-slide-target', $externalModeSlideTargetPair['stem']);
        $t->same('generated slide TargetMode ignored parity', $externalModeSlideTargetPair['name']);
        $t->same('pptx-reader/external-mode-slide-target.pptx|pptx-reader/external-mode-slide-target.native', $externalModeSlideTargetPair['pairKey']);
        $t->same('b0bb86a568a2020e07bebe50775568a3dc2fbd27a10d4ab8144258ae9f7f3eef', $externalModeSlideTargetPair['checkedInPptx']['sha256']);
        $t->same('7f8c73a728f91c0142c503e40e06a3fa5bf76c1d931d63ffbcf3ffacf205c918', $externalModeSlideTargetPair['checkedInNative']['sha256']);
        $t->same(1707, $externalModeSlideTargetPair['checkedInPptx']['bytes']);
        $t->same(110, $externalModeSlideTargetPair['checkedInNative']['bytes']);
        $t->same('root-targetmode-external', $rootTargetmodeExternalPair['stem']);
        $t->same('generated root officeDocument TargetMode ignored parity', $rootTargetmodeExternalPair['name']);
        $t->same('pptx-reader/root-targetmode-external.pptx|pptx-reader/root-targetmode-external.native', $rootTargetmodeExternalPair['pairKey']);
        $t->same('4e3501e9cfe8c0e23e5c977c3ba0b56ea955a96382402144fff007c8ff323587', $rootTargetmodeExternalPair['checkedInPptx']['sha256']);
        $t->same('f14cb1439bde326803f0428b5ef451c49d557a66e9d3594a1c3b6e1b3ab6905f', $rootTargetmodeExternalPair['checkedInNative']['sha256']);
        $t->same(1679, $rootTargetmodeExternalPair['checkedInPptx']['bytes']);
        $t->same(75, $rootTargetmodeExternalPair['checkedInNative']['bytes']);
        $t->same('external-rich-media-skip', $externalRichMediaSkipPair['stem']);
        $t->same('generated external rich media placeholder skip parity', $externalRichMediaSkipPair['name']);
        $t->same('pptx-reader/external-rich-media-skip.pptx|pptx-reader/external-rich-media-skip.native', $externalRichMediaSkipPair['pairKey']);
        $t->same('26b4f782fcb6aa221cc33495d5635b52c536ada9f4a6a116f4e06c927f38d86b', $externalRichMediaSkipPair['checkedInPptx']['sha256']);
        $t->same('600f69ab626db820ccdbdbd28f0d8e3f43dde299bf35d82eb77dab031f229b20', $externalRichMediaSkipPair['checkedInNative']['sha256']);
        $t->same(2340, $externalRichMediaSkipPair['checkedInPptx']['bytes']);
        $t->same(135, $externalRichMediaSkipPair['checkedInNative']['bytes']);
        $t->same('first-text-body', $firstTextBodyPair['stem']);
        $t->same('generated first text body child parity', $firstTextBodyPair['name']);
        $t->same('pptx-reader/first-text-body.pptx|pptx-reader/first-text-body.native', $firstTextBodyPair['pairKey']);
        $t->same('9632d9605fcc1ee78db83843121f12c297eafeab15d6098932e8738d6dd74624', $firstTextBodyPair['checkedInPptx']['sha256']);
        $t->same('98aabf841a37c3c677ef20c7ac0a3987ec55bcc38bde09efcaac83bfc39619e7', $firstTextBodyPair['checkedInNative']['sha256']);
        $t->same(1552, $firstTextBodyPair['checkedInPptx']['bytes']);
        $t->same(63, $firstTextBodyPair['checkedInNative']['bytes']);
        $t->same('first-title-placeholder', $firstTitlePlaceholderPair['stem']);
        $t->same('generated first duplicate title placeholder parity', $firstTitlePlaceholderPair['name']);
        $t->same('pptx-reader/first-title-placeholder.pptx|pptx-reader/first-title-placeholder.native', $firstTitlePlaceholderPair['pairKey']);
        $t->same('82b4a59db7cc37a0f31602bc10c60cfb0d9bcb191f6675cd54215e637618a4c6', $firstTitlePlaceholderPair['checkedInPptx']['sha256']);
        $t->same('acad0d5021e8a75d5793e332692bd48b0e7ebe51d69f63cf179e094940f4167b', $firstTitlePlaceholderPair['checkedInNative']['sha256']);
        $t->same(1344, $firstTitlePlaceholderPair['checkedInPptx']['bytes']);
        $t->same(103, $firstTitlePlaceholderPair['checkedInNative']['bytes']);
        $t->same('first-run-property-symbol', $firstRunPropertySymbolPair['stem']);
        $t->same('generated first run-property Wingdings symbol parity', $firstRunPropertySymbolPair['name']);
        $t->same('pptx-reader/first-run-property-symbol.pptx|pptx-reader/first-run-property-symbol.native', $firstRunPropertySymbolPair['pairKey']);
        $t->same('ba8e59a0ffaf54ca3bf1966bc508c4237b556105fd8437b1b74d3a1e9ba4aa0f', $firstRunPropertySymbolPair['checkedInPptx']['sha256']);
        $t->same('4aba0d35d612be97b9dcdc36293d4bd244c9c2fa43d3da270fc873c9930c831d', $firstRunPropertySymbolPair['checkedInNative']['sha256']);
        $t->same(1417, $firstRunPropertySymbolPair['checkedInPptx']['bytes']);
        $t->same(251, $firstRunPropertySymbolPair['checkedInNative']['bytes']);
        $t->same('break-tab-field', $breakTabFieldPair['stem']);
        $t->same('generated break, tab, and field text boundary parity', $breakTabFieldPair['name']);
        $t->same('pptx-reader/break-tab-field.pptx|pptx-reader/break-tab-field.native', $breakTabFieldPair['pairKey']);
        $t->same('eab556ea99844fb5f815f977d46d5a1923d59f71682c7cceae5e23b5937f113c', $breakTabFieldPair['checkedInPptx']['sha256']);
        $t->same('e619a9e7b375700d5fd8c2c74cd9bb5c424098d39b972212a86f58764affadf4', $breakTabFieldPair['checkedInNative']['sha256']);
        $t->same(1435, $breakTabFieldPair['checkedInPptx']['bytes']);
        $t->same(113, $breakTabFieldPair['checkedInNative']['bytes']);
        $t->same('whitespace-drawing-text', $whitespaceDrawingTextPair['stem']);
        $t->same('generated whitespace-only drawing text parity', $whitespaceDrawingTextPair['name']);
        $t->same('pptx-reader/whitespace-drawing-text.pptx|pptx-reader/whitespace-drawing-text.native', $whitespaceDrawingTextPair['pairKey']);
        $t->same('5ae9d3ad48991a588151ed1be4b24c049cdcba38c934832dad3b3e2e583aca1c', $whitespaceDrawingTextPair['checkedInPptx']['sha256']);
        $t->same('fca7800593fc3281941905b032d7256d447402d49b3b6886df979d264d5ce7c5', $whitespaceDrawingTextPair['checkedInNative']['sha256']);
        $t->same(1759, $whitespaceDrawingTextPair['checkedInPptx']['bytes']);
        $t->same(614, $whitespaceDrawingTextPair['checkedInNative']['bytes']);
        $t->same('bullets', $bulletsPair['stem']);
        $t->same('generated minimal bullet list parity', $bulletsPair['name']);
        $t->same('pptx-reader/bullets.pptx|pptx-reader/bullets.native', $bulletsPair['pairKey']);
        $t->same('912915e6c9a56eda1e2cb657b23cd007cd0c49da8d8d96a199e9cb8c1e310760', $bulletsPair['checkedInPptx']['sha256']);
        $t->same('f53f49de194917ae945eaaff66720120bf8a0df95c6075b31a08ea41f633507c', $bulletsPair['checkedInNative']['sha256']);
        $t->same(1543, $bulletsPair['checkedInPptx']['bytes']);
        $t->same(157, $bulletsPair['checkedInNative']['bytes']);
        $t->same('bunone-wingdings', $bunoneWingdingsPair['stem']);
        $t->same('generated buNone Wingdings bullet override parity', $bunoneWingdingsPair['name']);
        $t->same('pptx-reader/bunone-wingdings.pptx|pptx-reader/bunone-wingdings.native', $bunoneWingdingsPair['pairKey']);
        $t->same('8ccf4cfc9c7aeda294d99f34f0364ebfdb42b75d013d96bb9b9fc7776d9b0467', $bunoneWingdingsPair['checkedInPptx']['sha256']);
        $t->same('f89bc42f76c23972fef13fac39bdbb5fafa0f690f488a45eb97f2469d58d4771', $bunoneWingdingsPair['checkedInNative']['sha256']);
        $t->same(1697, $bunoneWingdingsPair['checkedInPptx']['bytes']);
        $t->same(232, $bunoneWingdingsPair['checkedInNative']['bytes']);
        $t->same('case-sensitive-placeholder-type', $caseSensitivePlaceholderTypePair['stem']);
        $t->same('generated case-sensitive placeholder type fallback parity', $caseSensitivePlaceholderTypePair['name']);
        $t->same('pptx-reader/case-sensitive-placeholder-type.pptx|pptx-reader/case-sensitive-placeholder-type.native', $caseSensitivePlaceholderTypePair['pairKey']);
        $t->same('266be65ed22ffcd004f9aa15b02a03b694046753195758bd4d90c088173fe235', $caseSensitivePlaceholderTypePair['checkedInPptx']['sha256']);
        $t->same('338fda8cc76cf5ce483903d350023f942a9ac3ce391fe2172e51f224d62de47f', $caseSensitivePlaceholderTypePair['checkedInNative']['sha256']);
        $t->same(1486, $caseSensitivePlaceholderTypePair['checkedInPptx']['bytes']);
        $t->same(116, $caseSensitivePlaceholderTypePair['checkedInNative']['bytes']);
        $t->same('wingdings-typeface-case', $wingdingsTypefaceCasePair['stem']);
        $t->same('generated Wingdings typeface case matching parity', $wingdingsTypefaceCasePair['name']);
        $t->same('pptx-reader/wingdings-typeface-case.pptx|pptx-reader/wingdings-typeface-case.native', $wingdingsTypefaceCasePair['pairKey']);
        $t->same('2a37ab63e4052cacdfaa24aca6e8dbb11ea16ac41aa42996e1f82c358197582d', $wingdingsTypefaceCasePair['checkedInPptx']['sha256']);
        $t->same('6410058cd9a16830e37c5039097a69c2308a43b2e1d3149af760c8cd6356b755', $wingdingsTypefaceCasePair['checkedInNative']['sha256']);
        $t->same(1470, $wingdingsTypefaceCasePair['checkedInPptx']['bytes']);
        $t->same(357, $wingdingsTypefaceCasePair['checkedInNative']['bytes']);
        $t->same('center-title-placeholder', $centerTitlePlaceholderPair['stem']);
        $t->same('generated centered title placeholder parity', $centerTitlePlaceholderPair['name']);
        $t->same('pptx-reader/center-title-placeholder.pptx|pptx-reader/center-title-placeholder.native', $centerTitlePlaceholderPair['pairKey']);
        $t->same('e22c661df3cdb54049aed387ad518fd9ecbaa69f28adb176c08dfcac456c3c7b', $centerTitlePlaceholderPair['checkedInPptx']['sha256']);
        $t->same('9589ff6f42a0238f3446f02e7e97e9e52f8b2e3817597d91f4e2ec3788fb1356', $centerTitlePlaceholderPair['checkedInNative']['sha256']);
        $t->same(1503, $centerTitlePlaceholderPair['checkedInPptx']['bytes']);
        $t->same(114, $centerTitlePlaceholderPair['checkedInNative']['bytes']);
        $t->same('cdata-entity-text', $cdataEntityTextPair['stem']);
        $t->same('generated CDATA and entity text parity', $cdataEntityTextPair['name']);
        $t->same('pptx-reader/cdata-entity-text.pptx|pptx-reader/cdata-entity-text.native', $cdataEntityTextPair['pairKey']);
        $t->same('369898eb68f295a8e448a0a170a9cecbd39ffb2faf22d70d0d75748c4f7d2d35', $cdataEntityTextPair['checkedInPptx']['sha256']);
        $t->same('ebffd044d94f5761cde8e231b149d3fe2eb0c686c5474ff4737c9b060eb7ad46', $cdataEntityTextPair['checkedInNative']['sha256']);
        $t->same(1663, $cdataEntityTextPair['checkedInPptx']['bytes']);
        $t->same(129, $cdataEntityTextPair['checkedInNative']['bytes']);
        $t->same('chart-placeholder', $chartPlaceholderPair['stem']);
        $t->same('generated chart graphic placeholder parity', $chartPlaceholderPair['name']);
        $t->same('pptx-reader/chart-placeholder.pptx|pptx-reader/chart-placeholder.native', $chartPlaceholderPair['pairKey']);
        $t->same('43da4b9bc501e22c665706a9cf93597e445ee123e06ea1edaed49858e862f2ed', $chartPlaceholderPair['checkedInPptx']['sha256']);
        $t->same('c583540a28768d66ecd7aca44a211ae5ebff6cdec77eeb38b19ac10e5ad11f27', $chartPlaceholderPair['checkedInNative']['sha256']);
        $t->same(1659, $chartPlaceholderPair['checkedInPptx']['bytes']);
        $t->same(180, $chartPlaceholderPair['checkedInNative']['bytes']);
        $t->same('chart-embedded-workbook', $chartEmbeddedWorkbookPair['stem']);
        $t->same('generated chart embedded workbook provenance parity', $chartEmbeddedWorkbookPair['name']);
        $t->same('pptx-reader/chart-embedded-workbook.pptx|pptx-reader/chart-embedded-workbook.native', $chartEmbeddedWorkbookPair['pairKey']);
        $t->same('29f93cbe2fe5c2021a391acd9ae60e1a7afd68e281e6fa0304c379f527382142', $chartEmbeddedWorkbookPair['checkedInPptx']['sha256']);
        $t->same('2693cb5ba98115bdb045788a746ee26339d6253034772feaa9beef190fc7ebf9', $chartEmbeddedWorkbookPair['checkedInNative']['sha256']);
        $t->same(3021, $chartEmbeddedWorkbookPair['checkedInPptx']['bytes']);
        $t->same(250, $chartEmbeddedWorkbookPair['checkedInNative']['bytes']);
        $t->same('comments-ignored', $commentsIgnoredPair['stem']);
        $t->same('generated comments ignored parity', $commentsIgnoredPair['name']);
        $t->same('pptx-reader/comments-ignored.pptx|pptx-reader/comments-ignored.native', $commentsIgnoredPair['pairKey']);
        $t->same('c4677dabb5ef3ac8765c1b818ca007f85cfa16b36a47e3b409bba90fe5c5485a', $commentsIgnoredPair['checkedInPptx']['sha256']);
        $t->same('0adde5d0b2b9a90a0ce7864730f945f448d9d4f204c54db62de3de2294879d2a', $commentsIgnoredPair['checkedInNative']['sha256']);
        $t->same(2368, $commentsIgnoredPair['checkedInPptx']['bytes']);
        $t->same(122, $commentsIgnoredPair['checkedInNative']['bytes']);
        $t->same('content-part-skip', $contentPartSkipPair['stem']);
        $t->same('generated contentPart skip parity', $contentPartSkipPair['name']);
        $t->same('pptx-reader/content-part-skip.pptx|pptx-reader/content-part-skip.native', $contentPartSkipPair['pairKey']);
        $t->same('61244c0cca6dff5a64caa8318b8e81755b2853c221bf57d8cfafb9475deb2b0b', $contentPartSkipPair['checkedInPptx']['sha256']);
        $t->same('9e223d1d5dad199772749979c4331208ea6ee428b373d213f02c62ad108989f7', $contentPartSkipPair['checkedInNative']['sha256']);
        $t->same(1817, $contentPartSkipPair['checkedInPptx']['bytes']);
        $t->same(125, $contentPartSkipPair['checkedInNative']['bytes']);
        $t->same('diagram-missing-rels', $diagramMissingRelsPair['stem']);
        $t->same('generated diagram relIds missing relationship placeholder parity', $diagramMissingRelsPair['name']);
        $t->same('pptx-reader/diagram-missing-rels.pptx|pptx-reader/diagram-missing-rels.native', $diagramMissingRelsPair['pairKey']);
        $t->same('f0c37fc30ddd29f7b35d55002005acdf1ae98be1c32112113d95de3ea54e370b', $diagramMissingRelsPair['checkedInPptx']['sha256']);
        $t->same('ddf13978208bba40d57db2aceaed9ae3b49bfcf92c11c514719fe520a9066b18', $diagramMissingRelsPair['checkedInNative']['sha256']);
        $t->same(1461, $diagramMissingRelsPair['checkedInPptx']['bytes']);
        $t->same(141, $diagramMissingRelsPair['checkedInNative']['bytes']);
        $t->same('diagram-no-relids', $diagramNoRelidsPair['stem']);
        $t->same('generated diagram graphic without relIds placeholder parity', $diagramNoRelidsPair['name']);
        $t->same('pptx-reader/diagram-no-relids.pptx|pptx-reader/diagram-no-relids.native', $diagramNoRelidsPair['pairKey']);
        $t->same('d34b13655f60496d827b983c436780deaad410cb870446086608920292bdbed0', $diagramNoRelidsPair['checkedInPptx']['sha256']);
        $t->same('1e30c1c1df173905b38cd1526f9ae1a95b0f7a63253dee072207ac0925419354', $diagramNoRelidsPair['checkedInNative']['sha256']);
        $t->same(1418, $diagramNoRelidsPair['checkedInPptx']['bytes']);
        $t->same(122, $diagramNoRelidsPair['checkedInNative']['bytes']);
        $t->same('direct-drawing-paragraphs', $directDrawingParagraphsPair['stem']);
        $t->same('generated direct drawing paragraph boundary parity', $directDrawingParagraphsPair['name']);
        $t->same('pptx-reader/direct-drawing-paragraphs.pptx|pptx-reader/direct-drawing-paragraphs.native', $directDrawingParagraphsPair['pairKey']);
        $t->same('98867e5898bd31ff7f82498fe13d464b237b784ce149c6591c489c00b9bc0979', $directDrawingParagraphsPair['checkedInPptx']['sha256']);
        $t->same('cc7a90f26bdb968391ccaecfd3172a4666636d309f3024f67948022e88460b22', $directDrawingParagraphsPair['checkedInNative']['sha256']);
        $t->same(1405, $directDrawingParagraphsPair['checkedInPptx']['bytes']);
        $t->same(124, $directDrawingParagraphsPair['checkedInNative']['bytes']);
        $t->same('document-properties', $documentPropertiesPair['stem']);
        $t->same('generated document property sidecar metadata parity', $documentPropertiesPair['name']);
        $t->same('pptx-reader/document-properties.pptx|pptx-reader/document-properties.native', $documentPropertiesPair['pairKey']);
        $t->same('d059bf3fe2086ca7012e76a47a8cdd44c0e0235a6786444fe6ca628f25fba23c', $documentPropertiesPair['checkedInPptx']['sha256']);
        $t->same('8be0433cdacbaa8af79c12f3eb61f95d789ee60ccae8e2c26e39a35dddbd3648', $documentPropertiesPair['checkedInNative']['sha256']);
        $t->same(3187, $documentPropertiesPair['checkedInPptx']['bytes']);
        $t->same(126, $documentPropertiesPair['checkedInNative']['bytes']);
        $t->same('dot-presentation-target', $dotPresentationTargetPair['stem']);
        $t->same('generated dot-segment presentation target parity', $dotPresentationTargetPair['name']);
        $t->same('pptx-reader/dot-presentation-target.pptx|pptx-reader/dot-presentation-target.native', $dotPresentationTargetPair['pairKey']);
        $t->same('9783b8a44828e294087a1de24045e4ad9e268479b57f5850f0b4d3c82ef9a5ae', $dotPresentationTargetPair['checkedInPptx']['sha256']);
        $t->same('d86a49808a54e15a27287e0fb9fbcd4838f55b68eca8d3c0ca68547667f7462f', $dotPresentationTargetPair['checkedInNative']['sha256']);
        $t->same(1310, $dotPresentationTargetPair['checkedInPptx']['bytes']);
        $t->same(90, $dotPresentationTargetPair['checkedInNative']['bytes']);
        $t->same('dot-slide-target', $dotSlideTargetPair['stem']);
        $t->same('generated dot-segment slide target parity', $dotSlideTargetPair['name']);
        $t->same('pptx-reader/dot-slide-target.pptx|pptx-reader/dot-slide-target.native', $dotSlideTargetPair['pairKey']);
        $t->same('89b8ceba7657e20909a0406ac371c75b81929b72555b4a698e77ed0bcf944373', $dotSlideTargetPair['checkedInPptx']['sha256']);
        $t->same('835dfba7de0cdcc016d24d7eba54ff6eee05d0434da2154bd49f51fe25a66bb4', $dotSlideTargetPair['checkedInNative']['sha256']);
        $t->same(1280, $dotSlideTargetPair['checkedInPptx']['bytes']);
        $t->same(101, $dotSlideTargetPair['checkedInNative']['bytes']);
        $t->same('duplicate-relationship-id', $duplicateRelationshipIdPair['stem']);
        $t->same('generated duplicate relationship id first-target parity', $duplicateRelationshipIdPair['name']);
        $t->same('pptx-reader/duplicate-relationship-id.pptx|pptx-reader/duplicate-relationship-id.native', $duplicateRelationshipIdPair['pairKey']);
        $t->same('da3cc0a2e97ec681bdea132762fe438ac20beee5c4eda19c89e721d82888ab55', $duplicateRelationshipIdPair['checkedInPptx']['sha256']);
        $t->same('c782680dd8586098eacc123dcbd3c608f621ae3e83853c8c1e7bb58f9f8781f8', $duplicateRelationshipIdPair['checkedInNative']['sha256']);
        $t->same(1923, $duplicateRelationshipIdPair['checkedInPptx']['bytes']);
        $t->same(115, $duplicateRelationshipIdPair['checkedInNative']['bytes']);
        $t->same('duplicate-slide-reference', $duplicateSlideReferencePair['stem']);
        $t->same('generated duplicate slide reference parity', $duplicateSlideReferencePair['name']);
        $t->same('pptx-reader/duplicate-slide-reference.pptx|pptx-reader/duplicate-slide-reference.native', $duplicateSlideReferencePair['pairKey']);
        $t->same('f598167fa0995e6069126c2a00f9ed92c7732df26210b9952c8c0a54022d30c6', $duplicateSlideReferencePair['checkedInPptx']['sha256']);
        $t->same('a13be46a0ba2e04ae56ee4da86015c4f30401c654f942f574c2d3516a0eb2a3d', $duplicateSlideReferencePair['checkedInNative']['sha256']);
        $t->same(1895, $duplicateSlideReferencePair['checkedInPptx']['bytes']);
        $t->same(176, $duplicateSlideReferencePair['checkedInNative']['bytes']);
        $t->same('embed-and-link-image', $embedAndLinkImagePair['stem']);
        $t->same('generated embed-over-link image relationship parity', $embedAndLinkImagePair['name']);
        $t->same('pptx-reader/embed-and-link-image.pptx|pptx-reader/embed-and-link-image.native', $embedAndLinkImagePair['pairKey']);
        $t->same('0675b7e479fbd55a76fb798357dbe2266e509023be3a7a2d2d34dff8ddf7322b', $embedAndLinkImagePair['checkedInPptx']['sha256']);
        $t->same('2088c8c09db8bad7bbc09ddcabc8d54500491b2ac13bb75970c5e6ed0969c507', $embedAndLinkImagePair['checkedInNative']['sha256']);
        $t->same(2607, $embedAndLinkImagePair['checkedInPptx']['bytes']);
        $t->same(218, $embedAndLinkImagePair['checkedInNative']['bytes']);
        $t->same('connector-skip', $connectorSkipPair['stem']);
        $t->same('generated connector shape skip parity', $connectorSkipPair['name']);
        $t->same('pptx-reader/connector-skip.pptx|pptx-reader/connector-skip.native', $connectorSkipPair['pairKey']);
        $t->same('ea84954b53c9ff9b53419df4828b32e191261c8e00375f20bd03ea160326a25b', $connectorSkipPair['checkedInPptx']['sha256']);
        $t->same('df89712378d3c5d4994094744ecd4e20f482e0231acd053619ebf92eff5b1254', $connectorSkipPair['checkedInNative']['sha256']);
        $t->same(1493, $connectorSkipPair['checkedInPptx']['bytes']);
        $t->same(139, $connectorSkipPair['checkedInNative']['bytes']);
        $t->same('connector-text-skip', $connectorTextSkipPair['stem']);
        $t->same('generated connector text skip parity', $connectorTextSkipPair['name']);
        $t->same('pptx-reader/connector-text-skip.pptx|pptx-reader/connector-text-skip.native', $connectorTextSkipPair['pairKey']);
        $t->same('4112630a090c011c06adb2f1607b6d240416ae5fc4c2be8c7f93bd3356de0015', $connectorTextSkipPair['checkedInPptx']['sha256']);
        $t->same('4c0a2dc57adbbe7429f6fcb6ed27c412c6166a2dbe98fa725b4c11739f69aca7', $connectorTextSkipPair['checkedInNative']['sha256']);
        $t->same(1470, $connectorTextSkipPair['checkedInPptx']['bytes']);
        $t->same(112, $connectorTextSkipPair['checkedInNative']['bytes']);
        $t->same('embedded-image', $embeddedImagePair['stem']);
        $t->same('generated embedded image native parity', $embeddedImagePair['name']);
        $t->same('pptx-reader/embedded-image.pptx|pptx-reader/embedded-image.native', $embeddedImagePair['pairKey']);
        $t->same('de45bd6af2dcf74e29dd7d961e5459c3a5d2b420992b1bbf280b10ee6df7256a', $embeddedImagePair['checkedInPptx']['sha256']);
        $t->same('1aea7cedcb9155ee19a55db0d2825b1427dab1f51bbb460d140cd637e2bec266', $embeddedImagePair['checkedInNative']['sha256']);
        $t->same(2363, $embeddedImagePair['checkedInPptx']['bytes']);
        $t->same(195, $embeddedImagePair['checkedInNative']['bytes']);
        $t->same('empty-bullet-paragraph', $emptyBulletParagraphPair['stem']);
        $t->same('generated empty bullet paragraph parity', $emptyBulletParagraphPair['name']);
        $t->same('pptx-reader/empty-bullet-paragraph.pptx|pptx-reader/empty-bullet-paragraph.native', $emptyBulletParagraphPair['pairKey']);
        $t->same('e7660917ee56111797224aa96d1a70783169c574117eaf9dbd36299c4efbfaff', $emptyBulletParagraphPair['checkedInPptx']['sha256']);
        $t->same('a7420eaafce9765543a82c54d9b0ecdc185ff5557ad60ee77ec3cd6cfc154e10', $emptyBulletParagraphPair['checkedInNative']['sha256']);
        $t->same(1526, $emptyBulletParagraphPair['checkedInPptx']['bytes']);
        $t->same(162, $emptyBulletParagraphPair['checkedInNative']['bytes']);
        $t->same('empty-header-table', $emptyHeaderTablePair['stem']);
        $t->same('generated empty header table parity', $emptyHeaderTablePair['name']);
        $t->same('pptx-reader/empty-header-table.pptx|pptx-reader/empty-header-table.native', $emptyHeaderTablePair['pairKey']);
        $t->same('adfa227750b01446fb7423b75ebed5ec49d5e8b47b56aee6d2cee3af95e355ad', $emptyHeaderTablePair['checkedInPptx']['sha256']);
        $t->same('313eec07897c789b4bdc2835abc54bae10f67ce37a1795c7c37babb7ac898dae', $emptyHeaderTablePair['checkedInNative']['sha256']);
        $t->same(1441, $emptyHeaderTablePair['checkedInPptx']['bytes']);
        $t->same(740, $emptyHeaderTablePair['checkedInNative']['bytes']);
        $t->same('generated-table', $generatedTablePair['stem']);
        $t->same('generated table extraction parity', $generatedTablePair['name']);
        $t->same('pptx-reader/generated-table.pptx|pptx-reader/generated-table.native', $generatedTablePair['pairKey']);
        $t->same('85fec7638ef6f82c43cd805e9064146c4602cf5e7384ccdfa60a55048ec67b78', $generatedTablePair['checkedInPptx']['sha256']);
        $t->same('17b1efbb9d7b21ddf994fffd6c9d34110c48668ab144fd5b027d40034ec2e832', $generatedTablePair['checkedInNative']['sha256']);
        $t->same(1702, $generatedTablePair['checkedInPptx']['bytes']);
        $t->same(1192, $generatedTablePair['checkedInNative']['bytes']);
        $t->same('graphic-no-uri', $graphicNoUriPair['stem']);
        $t->same('generated graphicData without URI placeholder parity', $graphicNoUriPair['name']);
        $t->same('pptx-reader/graphic-no-uri.pptx|pptx-reader/graphic-no-uri.native', $graphicNoUriPair['pairKey']);
        $t->same('83f08ec41905374579aa7d1f3a4298fd839b99fef5fb1f971d170a804bc18a94', $graphicNoUriPair['checkedInPptx']['sha256']);
        $t->same('3f88fef1c759398017753ef141d6fb81c0c6e2fe3b93d2042f94027faeac72e4', $graphicNoUriPair['checkedInNative']['sha256']);
        $t->same(1606, $graphicNoUriPair['checkedInPptx']['bytes']);
        $t->same(106, $graphicNoUriPair['checkedInNative']['bytes']);
        $t->same('table-span-review', $tableSpanReviewPair['stem']);
        $t->same('generated table span review-only parity', $tableSpanReviewPair['name']);
        $t->same('pptx-reader/table-span-review.pptx|pptx-reader/table-span-review.native', $tableSpanReviewPair['pairKey']);
        $t->same('6d39a50f3215706922877dd2148afb0e55208a7600d2ebb48e60830d7d160b0c', $tableSpanReviewPair['checkedInPptx']['sha256']);
        $t->same('8df034dad767bbd20cc5f1f9fb875eecf84b8636dc74100677433cda03b304ce', $tableSpanReviewPair['checkedInNative']['sha256']);
        $t->same(1739, $tableSpanReviewPair['checkedInPptx']['bytes']);
        $t->same(1598, $tableSpanReviewPair['checkedInNative']['bytes']);
        $t->same('table-styles-relationship', $tableStylesRelationshipPair['stem']);
        $t->same('generated table styles relationship parity', $tableStylesRelationshipPair['name']);
        $t->same('pptx-reader/table-styles-relationship.pptx|pptx-reader/table-styles-relationship.native', $tableStylesRelationshipPair['pairKey']);
        $t->same('5031c2ca5d8ea2bcd7ae08cd904655d151312a9bb83646c126e643a4acc4f3bc', $tableStylesRelationshipPair['checkedInPptx']['sha256']);
        $t->same('a799a5d732ca0a11d94f239d782b61bffe81325c09ed721705fabc2fe079feba', $tableStylesRelationshipPair['checkedInNative']['sha256']);
        $t->same(2155, $tableStylesRelationshipPair['checkedInPptx']['bytes']);
        $t->same(1228, $tableStylesRelationshipPair['checkedInNative']['bytes']);
        $t->same('text-comment-boundary', $textCommentBoundaryPair['stem']);
        $t->same('generated XML comment text-node boundary parity', $textCommentBoundaryPair['name']);
        $t->same('pptx-reader/text-comment-boundary.pptx|pptx-reader/text-comment-boundary.native', $textCommentBoundaryPair['pairKey']);
        $t->same('6f0b2e376015d61f06e8320c27eeba91a96b364d766e9dac0b75af66326d7e7c', $textCommentBoundaryPair['checkedInPptx']['sha256']);
        $t->same('088750aa75a347feec838c6d3f7bed51842fe70846b64d322b339a5335d5b8e9', $textCommentBoundaryPair['checkedInNative']['sha256']);
        $t->same(1536, $textCommentBoundaryPair['checkedInPptx']['bytes']);
        $t->same(105, $textCommentBoundaryPair['checkedInNative']['bytes']);
        $t->same('textbox-without-nonvisual-properties', $textboxWithoutNonVisualPropertiesPair['stem']);
        $t->same('generated text box without nonvisual properties parity', $textboxWithoutNonVisualPropertiesPair['name']);
        $t->same('pptx-reader/textbox-without-nonvisual-properties.pptx|pptx-reader/textbox-without-nonvisual-properties.native', $textboxWithoutNonVisualPropertiesPair['pairKey']);
        $t->same('c52b2158d87f686fe7f8772ed2981046e565b1248116f4790b6ac8e137db8dbf', $textboxWithoutNonVisualPropertiesPair['checkedInPptx']['sha256']);
        $t->same('4166c3c576b0b9c56b1660166572f88a2d24130db590bb76b6cc211f8201e743', $textboxWithoutNonVisualPropertiesPair['checkedInNative']['sha256']);
        $t->same(1315, $textboxWithoutNonVisualPropertiesPair['checkedInPptx']['bytes']);
        $t->same(110, $textboxWithoutNonVisualPropertiesPair['checkedInNative']['bytes']);
        $t->same('grouped-shape-media-review', $groupedShapeMediaReviewPair['stem']);
        $t->same('generated grouped shape media review parity', $groupedShapeMediaReviewPair['name']);
        $t->same('pptx-reader/grouped-shape-media-review.pptx|pptx-reader/grouped-shape-media-review.native', $groupedShapeMediaReviewPair['pairKey']);
        $t->same('8125b15cd633f7e88428893df558f6abf7a927b1ba2a13bf36a97497be789de0', $groupedShapeMediaReviewPair['checkedInPptx']['sha256']);
        $t->same('d782ab60b3a2c37fbcaa60cd2a658d2d9457bf4674d1e4d920be5ee7daa75301', $groupedShapeMediaReviewPair['checkedInNative']['sha256']);
        $t->same(2289, $groupedShapeMediaReviewPair['checkedInPptx']['bytes']);
        $t->same(119, $groupedShapeMediaReviewPair['checkedInNative']['bytes']);
        $t->same('grouped-shapes', $groupedShapesPair['stem']);
        $t->same('generated grouped shape skip parity', $groupedShapesPair['name']);
        $t->same('pptx-reader/grouped-shapes.pptx|pptx-reader/grouped-shapes.native', $groupedShapesPair['pairKey']);
        $t->same('906420300b4dd404e516ea84b72afa1ae74ea5ed729097e1cbaa6e1226fb2d09', $groupedShapesPair['checkedInPptx']['sha256']);
        $t->same('4e1caa42c42964a8ca9dab0dfb092ad4303009f46c3b406d491307e951447176', $groupedShapesPair['checkedInNative']['sha256']);
        $t->same(1975, $groupedShapesPair['checkedInPptx']['bytes']);
        $t->same(61, $groupedShapesPair['checkedInNative']['bytes']);
        $t->same('hex-list-level', $hexListLevelPair['stem']);
        $t->same('generated hexadecimal list level parity', $hexListLevelPair['name']);
        $t->same('pptx-reader/hex-list-level.pptx|pptx-reader/hex-list-level.native', $hexListLevelPair['pairKey']);
        $t->same('32f695d75454c0fb94694cd02f655c248419b27158eff788fd06f910d91190bf', $hexListLevelPair['checkedInPptx']['sha256']);
        $t->same('9a880e7716e4fb9301d13de65664811376cfbb7fdbc7e78772432187be00fd64', $hexListLevelPair['checkedInNative']['sha256']);
        $t->same(1548, $hexListLevelPair['checkedInPptx']['bytes']);
        $t->same(161, $hexListLevelPair['checkedInNative']['bytes']);
        $t->same('signed-bullet-level', $signedBulletLevelPair['stem']);
        $t->same('generated signed bullet level parity', $signedBulletLevelPair['name']);
        $t->same('pptx-reader/signed-bullet-level.pptx|pptx-reader/signed-bullet-level.native', $signedBulletLevelPair['pairKey']);
        $t->same('96eabf5aee2a41ac7f18672924541ea658a775931d88c9eb81d4807b3cac8152', $signedBulletLevelPair['checkedInPptx']['sha256']);
        $t->same('e683a48f7c2966aec3033ea3b0e8e28beb48de02fee0c2fa09651cc55f25cdaf', $signedBulletLevelPair['checkedInNative']['sha256']);
        $t->same(1420, $signedBulletLevelPair['checkedInPptx']['bytes']);
        $t->same(236, $signedBulletLevelPair['checkedInNative']['bytes']);
        $t->same('hidden-shape-metadata', $hiddenShapeMetadataPair['stem']);
        $t->same('generated hidden shape metadata parity', $hiddenShapeMetadataPair['name']);
        $t->same('pptx-reader/hidden-shape-metadata.pptx|pptx-reader/hidden-shape-metadata.native', $hiddenShapeMetadataPair['pairKey']);
        $t->same('8ef23fb882dd6f0acd914e1da20fcf14295f8bbd413ddc5fb41da6e4d7e8caea', $hiddenShapeMetadataPair['checkedInPptx']['sha256']);
        $t->same('430227468460a2c9d03fa45b39efdb0ea659e49ad44d9b7a374128688a0f2f4c', $hiddenShapeMetadataPair['checkedInNative']['sha256']);
        $t->same(1937, $hiddenShapeMetadataPair['checkedInPptx']['bytes']);
        $t->same(304, $hiddenShapeMetadataPair['checkedInNative']['bytes']);
        $t->same('hidden-slide', $hiddenSlidePair['stem']);
        $t->same('generated hidden slide inclusion parity', $hiddenSlidePair['name']);
        $t->same('pptx-reader/hidden-slide.pptx|pptx-reader/hidden-slide.native', $hiddenSlidePair['pairKey']);
        $t->same('01627fa5f56ca583f3604306984cc1df4b69a15339396b061e44604265cb802f', $hiddenSlidePair['checkedInPptx']['sha256']);
        $t->same('a543e3ed60ca4d5f187fba970ed855d5f064a911e3ee3224b07929481c62b515', $hiddenSlidePair['checkedInNative']['sha256']);
        $t->same(1893, $hiddenSlidePair['checkedInPptx']['bytes']);
        $t->same(178, $hiddenSlidePair['checkedInNative']['bytes']);
        $t->same('ignored-slide-id-attributes', $ignoredSlideIdAttributesPair['stem']);
        $t->same('generated ignored presentation slide id attributes parity', $ignoredSlideIdAttributesPair['name']);
        $t->same('pptx-reader/ignored-slide-id-attributes.pptx|pptx-reader/ignored-slide-id-attributes.native', $ignoredSlideIdAttributesPair['pairKey']);
        $t->same('089bf291993684e3a30d1dcd4caa047475bf38ca13b0b730fd97bebaf56092b6', $ignoredSlideIdAttributesPair['checkedInPptx']['sha256']);
        $t->same('2be6bf3f3e934918925565417fb8e3fb200eb6ba904ebe16dbbf4ebb2e018372', $ignoredSlideIdAttributesPair['checkedInNative']['sha256']);
        $t->same(1944, $ignoredSlideIdAttributesPair['checkedInPptx']['bytes']);
        $t->same(150, $ignoredSlideIdAttributesPair['checkedInNative']['bytes']);
        $t->same('hyperlink-text', $hyperlinkTextPair['stem']);
        $t->same('generated text hyperlink invisibility parity', $hyperlinkTextPair['name']);
        $t->same('pptx-reader/hyperlink-text.pptx|pptx-reader/hyperlink-text.native', $hyperlinkTextPair['pairKey']);
        $t->same('22180e777f4a145bd3aff34f6fd5c2a846ce5567d758a78565b5dfc6addca6e3', $hyperlinkTextPair['checkedInPptx']['sha256']);
        $t->same('f4334af63e88a238caf0dcb2a4bf37fa1745d54bb2d703ec287fb3cc0474bcd7', $hyperlinkTextPair['checkedInNative']['sha256']);
        $t->same(2004, $hyperlinkTextPair['checkedInPptx']['bytes']);
        $t->same(100, $hyperlinkTextPair['checkedInNative']['bytes']);
        $t->same('inline-formatting', $inlineFormattingPair['stem']);
        $t->same('generated inline run formatting flattening parity', $inlineFormattingPair['name']);
        $t->same('pptx-reader/inline-formatting.pptx|pptx-reader/inline-formatting.native', $inlineFormattingPair['pairKey']);
        $t->same('ab06c31070771529002bc03bb08bb53dd7212374d1596ef0af278226237a793a', $inlineFormattingPair['checkedInPptx']['sha256']);
        $t->same('1a3e45263240e1ac99eff8a222867e11db0de7a3ff53a9972769c41fd30518de', $inlineFormattingPair['checkedInNative']['sha256']);
        $t->same(27659, $inlineFormattingPair['checkedInPptx']['bytes']);
        $t->same(142, $inlineFormattingPair['checkedInNative']['bytes']);
        $t->same('list-continuation', $listContinuationPair['stem']);
        $t->same('generated buNone list-continuation boundary parity', $listContinuationPair['name']);
        $t->same('pptx-reader/list-continuation.pptx|pptx-reader/list-continuation.native', $listContinuationPair['pairKey']);
        $t->same('2b7ae7359fde4edb717371d518ef80c8bbda374fa72def88c3dcd744c91fdf5f', $listContinuationPair['checkedInPptx']['sha256']);
        $t->same('d5dd188d56624d8aa5a8a848a40d2e4568e3f522f034573dc8b539842ae702de', $listContinuationPair['checkedInNative']['sha256']);
        $t->same(1713, $listContinuationPair['checkedInPptx']['bytes']);
        $t->same(294, $listContinuationPair['checkedInNative']['bytes']);
        $t->same('linked-image-skip', $linkedImageSkipPair['stem']);
        $t->same('generated linked image skip parity', $linkedImageSkipPair['name']);
        $t->same('pptx-reader/linked-image-skip.pptx|pptx-reader/linked-image-skip.native', $linkedImageSkipPair['pairKey']);
        $t->same('59da55af98fd7bd06f69b0effb9283e8404e3496e3ff4b5f01bff02c0b1d7f05', $linkedImageSkipPair['checkedInPptx']['sha256']);
        $t->same('d170e15f31fa6600cd7fa3eb9560e48ebcc5caaff8ce207d43c48c9fe2b49317', $linkedImageSkipPair['checkedInNative']['sha256']);
        $t->same(2240, $linkedImageSkipPair['checkedInPptx']['bytes']);
        $t->same(118, $linkedImageSkipPair['checkedInNative']['bytes']);
        $t->same('literal-image-targets', $literalImageTargetsPair['stem']);
        $t->same('generated literal image target skip parity', $literalImageTargetsPair['name']);
        $t->same('pptx-reader/literal-image-targets.pptx|pptx-reader/literal-image-targets.native', $literalImageTargetsPair['pairKey']);
        $t->same('ff432a5190dfade93799e6ded4985fab28230a1ccccf7f3f272fbd9d326b2b24', $literalImageTargetsPair['checkedInPptx']['sha256']);
        $t->same('709139da441ee326f9627fed2515a80ffffd0e231c3a16e5bce6a8864b5d5d3b', $literalImageTargetsPair['checkedInNative']['sha256']);
        $t->same(2088, $literalImageTargetsPair['checkedInPptx']['bytes']);
        $t->same(73, $literalImageTargetsPair['checkedInNative']['bytes']);
        $t->same('picture-shape-hyperlink', $pictureShapeHyperlinkPair['stem']);
        $t->same('generated picture shape hyperlink ignore parity', $pictureShapeHyperlinkPair['name']);
        $t->same('pptx-reader/picture-shape-hyperlink.pptx|pptx-reader/picture-shape-hyperlink.native', $pictureShapeHyperlinkPair['pairKey']);
        $t->same('9d4c19437a20700472715756f14926783f6a1fa9f2379bb5187f3fd19391f07a', $pictureShapeHyperlinkPair['checkedInPptx']['sha256']);
        $t->same('4fa780ef19805afc3e13c3fee77b4bff99c03468ef2569099aa92e7eef6d3aa5', $pictureShapeHyperlinkPair['checkedInNative']['sha256']);
        $t->same(2491, $pictureShapeHyperlinkPair['checkedInPptx']['bytes']);
        $t->same(261, $pictureShapeHyperlinkPair['checkedInNative']['bytes']);
        $t->same('media-relative-image-target', $mediaRelativeImageTargetPair['stem']);
        $t->same('generated media-relative image target parity', $mediaRelativeImageTargetPair['name']);
        $t->same('pptx-reader/media-relative-image-target.pptx|pptx-reader/media-relative-image-target.native', $mediaRelativeImageTargetPair['pairKey']);
        $t->same('6058c05a26c250acd076cad8174a2f98b8ceeae3b09ae24f68fd13d0d0f499ee', $mediaRelativeImageTargetPair['checkedInPptx']['sha256']);
        $t->same('e626f61a8eb6163e5022a15814de316ae461a73389af1d4694ee87d81a4211ce', $mediaRelativeImageTargetPair['checkedInNative']['sha256']);
        $t->same(3621, $mediaRelativeImageTargetPair['checkedInPptx']['bytes']);
        $t->same(236, $mediaRelativeImageTargetPair['checkedInNative']['bytes']);
        $t->same('two-slides', $twoSlidesPair['stem']);
        $t->same('generated two-slide ordering parity', $twoSlidesPair['name']);
        $t->same('pptx-reader/two-slides.pptx|pptx-reader/two-slides.native', $twoSlidesPair['pairKey']);
        $t->same('58e37ebe22ba5f7e5b9f7c3fe886ae5ff085876371178e63cc115a8f6d4e052c', $twoSlidesPair['checkedInPptx']['sha256']);
        $t->same('269e2c8b638af9834b52a0ff23c795578f9b21404e27c60d846cf81b3520596a', $twoSlidesPair['checkedInNative']['sha256']);
        $t->same(1897, $twoSlidesPair['checkedInPptx']['bytes']);
        $t->same(177, $twoSlidesPair['checkedInNative']['bytes']);
        $t->same('unicode-drawing-text', $unicodeDrawingTextPair['stem']);
        $t->same('pandoc 3.10 Unicode drawing text parity', $unicodeDrawingTextPair['name']);
        $t->same('pptx-reader/unicode-drawing-text.pptx|pptx-reader/unicode-drawing-text.native', $unicodeDrawingTextPair['pairKey']);
        $t->same('6bae0a4e7a6ccf8a08a04bb6bfab89f7912b35ef2f2ee0074b886f2383911136', $unicodeDrawingTextPair['checkedInPptx']['sha256']);
        $t->same('d0309729b6886e5c7c8b72813360c1e1a0a88ccc9f6ce2364e8a2a991441c252', $unicodeDrawingTextPair['checkedInNative']['sha256']);
        $t->same(1496, $unicodeDrawingTextPair['checkedInPptx']['bytes']);
        $t->same(127, $unicodeDrawingTextPair['checkedInNative']['bytes']);
        $t->same('speaker-notes', $speakerNotesPair['stem']);
        $t->same('generated speaker notes visibility parity', $speakerNotesPair['name']);
        $t->same('pptx-reader/speaker-notes.pptx|pptx-reader/speaker-notes.native', $speakerNotesPair['pairKey']);
        $t->same('52d0a82f3a84c594a9be816307c90b918cb914802bd3622a4cf9e2c06f40ddc5', $speakerNotesPair['checkedInPptx']['sha256']);
        $t->same('24f10e8e2632d64f9afb7a3aac8b0e48570d8ef61d76f6f0a51f841d104142f1', $speakerNotesPair['checkedInNative']['sha256']);
        $t->same(2511, $speakerNotesPair['checkedInPptx']['bytes']);
        $t->same(95, $speakerNotesPair['checkedInNative']['bytes']);
        $t->same('subtitle-placeholder', $subtitlePlaceholderPair['stem']);
        $t->same('generated subtitle placeholder body parity', $subtitlePlaceholderPair['name']);
        $t->same('pptx-reader/subtitle-placeholder.pptx|pptx-reader/subtitle-placeholder.native', $subtitlePlaceholderPair['pairKey']);
        $t->same('3f15d4e0767367861baf040c1853b22900e821d32980e555cf5e3c10d41be5ea', $subtitlePlaceholderPair['checkedInPptx']['sha256']);
        $t->same('a217ec6f6e2a1cee8844f7c7230ae57ed5fc13bca6269aaeab2086db2799ee5f', $subtitlePlaceholderPair['checkedInNative']['sha256']);
        $t->same(1505, $subtitlePlaceholderPair['checkedInPptx']['bytes']);
        $t->same(129, $subtitlePlaceholderPair['checkedInNative']['bytes']);
        $t->same('numbered-list', $numberedListPair['stem']);
        $t->same('generated auto-numbered paragraph boundary parity', $numberedListPair['name']);
        $t->same('pptx-reader/numbered-list.pptx|pptx-reader/numbered-list.native', $numberedListPair['pairKey']);
        $t->same('ba1162b8a31aba2b9cc01b1d346a070d66a0f8666afa44e0ace72bfdd76f1d4b', $numberedListPair['checkedInPptx']['sha256']);
        $t->same('be9e2f1c3a9f5815ea6cc86debe2ff081a4666931dd2e48c32245cd3de40cd9f', $numberedListPair['checkedInNative']['sha256']);
        $t->same(1520, $numberedListPair['checkedInPptx']['bytes']);
        $t->same(118, $numberedListPair['checkedInNative']['bytes']);
        $t->same('octal-list-level', $octalListLevelPair['stem']);
        $t->same('generated octal list level parity', $octalListLevelPair['name']);
        $t->same('pptx-reader/octal-list-level.pptx|pptx-reader/octal-list-level.native', $octalListLevelPair['pairKey']);
        $t->same('daa5a906fd87b0b35323e93f108fe1b72a5b3f958006876e8944020f872da88f', $octalListLevelPair['checkedInPptx']['sha256']);
        $t->same('d9e8c07df9c64b726e687ee75f9e1ff3d6eae528d2df3e853f7f454022a26573', $octalListLevelPair['checkedInNative']['sha256']);
        $t->same(1423, $octalListLevelPair['checkedInPptx']['bytes']);
        $t->same(249, $octalListLevelPair['checkedInNative']['bytes']);
        $t->same('overflow-bullet-level', $overflowBulletLevelPair['stem']);
        $t->same('generated Haskell Int overflow bullet level parity', $overflowBulletLevelPair['name']);
        $t->same('pptx-reader/overflow-bullet-level.pptx|pptx-reader/overflow-bullet-level.native', $overflowBulletLevelPair['pairKey']);
        $t->same('c6ccda57f94b450e0aa26ead7887c11edafe4d4efdd183bda2b7fbcbbe469e65', $overflowBulletLevelPair['checkedInPptx']['sha256']);
        $t->same('93a7f80f530d6db03d4f9fcac88e2d3a0758ed6e8afa360d90eee0e722937563', $overflowBulletLevelPair['checkedInNative']['sha256']);
        $t->same(1622, $overflowBulletLevelPair['checkedInPptx']['bytes']);
        $t->same(378, $overflowBulletLevelPair['checkedInNative']['bytes']);
        $t->same('parenthesized-bullet-level', $parenthesizedBulletLevelPair['stem']);
        $t->same('generated parenthesized bullet level parity', $parenthesizedBulletLevelPair['name']);
        $t->same('pptx-reader/parenthesized-bullet-level.pptx|pptx-reader/parenthesized-bullet-level.native', $parenthesizedBulletLevelPair['pairKey']);
        $t->same('7378843edb65e1063a902bbb646e5602a4fa2ed3fb8632dfddc585ccab202931', $parenthesizedBulletLevelPair['checkedInPptx']['sha256']);
        $t->same('6f7909e6f75fa02bb9941a58789b9b27d83740ff78cb3b6ad0d54ddf082af32e', $parenthesizedBulletLevelPair['checkedInNative']['sha256']);
        $t->same(1430, $parenthesizedBulletLevelPair['checkedInPptx']['bytes']);
        $t->same(276, $parenthesizedBulletLevelPair['checkedInNative']['bytes']);
        $t->same('pandoc-generated-image-alt-title', $pandocGeneratedImageAltTitlePair['stem']);
        $t->same('pandoc 3.10 generated image title and alt parity', $pandocGeneratedImageAltTitlePair['name']);
        $t->same('pptx-reader/pandoc-generated-image-alt-title.pptx|pptx-reader/pandoc-generated-image-alt-title.native', $pandocGeneratedImageAltTitlePair['pairKey']);
        $t->same('8603ee4876a9d3e5dcc713e283fd256b507555e37b5e29bc4eb24e51077df3a6', $pandocGeneratedImageAltTitlePair['checkedInPptx']['sha256']);
        $t->same('e268004d2c0de80415609e20914e4c949b418cf01b860b58cdb02649badf1136', $pandocGeneratedImageAltTitlePair['checkedInNative']['sha256']);
        $t->same(28067, $pandocGeneratedImageAltTitlePair['checkedInPptx']['bytes']);
        $t->same(233, $pandocGeneratedImageAltTitlePair['checkedInNative']['bytes']);
        $t->same('shape-order', $shapeOrderPair['stem']);
        $t->same('generated plain text shape ordering parity', $shapeOrderPair['name']);
        $t->same('pptx-reader/shape-order.pptx|pptx-reader/shape-order.native', $shapeOrderPair['pairKey']);
        $t->same('3f92fd142900b957b23cfe2b1afb01d2785d23b77ae62c23429d6bd11fd3c02f', $shapeOrderPair['checkedInPptx']['sha256']);
        $t->same('911f29fe22d020d181e007478bff7c157f6df49d06f7c42798bb3a933d33f427', $shapeOrderPair['checkedInNative']['sha256']);
        $t->same(1521, $shapeOrderPair['checkedInPptx']['bytes']);
        $t->same(135, $shapeOrderPair['checkedInNative']['bytes']);
        $t->same('slide-layout-placeholder-no-inherit', $slideLayoutPlaceholderNoInheritPair['stem']);
        $t->same('generated slide layout empty placeholder no-inherit parity', $slideLayoutPlaceholderNoInheritPair['name']);
        $t->same('pptx-reader/slide-layout-placeholder-no-inherit.pptx|pptx-reader/slide-layout-placeholder-no-inherit.native', $slideLayoutPlaceholderNoInheritPair['pairKey']);
        $t->same('16a18e8709ed45075cf556bc8f78527edf86e7c15c76058d6557270defeb64c5', $slideLayoutPlaceholderNoInheritPair['checkedInPptx']['sha256']);
        $t->same('5fe1085d4fe7bacf348cc26ee690429dc4f783d3f2fdb9202d63a8e9a27176b3', $slideLayoutPlaceholderNoInheritPair['checkedInNative']['sha256']);
        $t->same(2279, $slideLayoutPlaceholderNoInheritPair['checkedInPptx']['bytes']);
        $t->same(129, $slideLayoutPlaceholderNoInheritPair['checkedInNative']['bytes']);
        $t->same('slide-placeholders', $slidePlaceholdersPair['stem']);
        $t->same('generated slide footer/date/number placeholder visibility parity', $slidePlaceholdersPair['name']);
        $t->same('pptx-reader/slide-placeholders.pptx|pptx-reader/slide-placeholders.native', $slidePlaceholdersPair['pairKey']);
        $t->same('c8e3aebc55d7e464bb43409263586042420acaac2ce308601dadd081ab17354b', $slidePlaceholdersPair['checkedInPptx']['sha256']);
        $t->same('f76963e6f7aa7b051bddb6ad4fa62016af8f580963f75fee42ecb840c7a64cc6', $slidePlaceholdersPair['checkedInNative']['sha256']);
        $t->same(1598, $slidePlaceholdersPair['checkedInPptx']['bytes']);
        $t->same(203, $slidePlaceholdersPair['checkedInNative']['bytes']);
        $t->same('smartart-hierarchy', $smartartHierarchyPair['stem']);
        $t->same('generated SmartArt hierarchy native parity', $smartartHierarchyPair['name']);
        $t->same('pptx-reader/smartart-hierarchy.pptx|pptx-reader/smartart-hierarchy.native', $smartartHierarchyPair['pairKey']);
        $t->same('186195196185f1c5b95a0e7e2c327dc551371edbd09de4d2f94e418ff10420eb', $smartartHierarchyPair['checkedInPptx']['sha256']);
        $t->same('bc41c663b7f2711c8d12039d385926db19bae387c07290cd7629e5ab278e2ce9', $smartartHierarchyPair['checkedInNative']['sha256']);
        $t->same(2664, $smartartHierarchyPair['checkedInPptx']['bytes']);
        $t->same(332, $smartartHierarchyPair['checkedInNative']['bytes']);
        $t->same('smartart-title-fallback', $smartartTitleFallbackPair['stem']);
        $t->same('generated SmartArt layout title fallback parity', $smartartTitleFallbackPair['name']);
        $t->same('pptx-reader/smartart-title-fallback.pptx|pptx-reader/smartart-title-fallback.native', $smartartTitleFallbackPair['pairKey']);
        $t->same('bbc1325cd9324ccd14898628b55589da7ddc4fb7079a071599069e842d985046', $smartartTitleFallbackPair['checkedInPptx']['sha256']);
        $t->same('46bf18c4facc20fee3f231d19516c94521883988bdc0f0c07a158d477bf51396', $smartartTitleFallbackPair['checkedInNative']['sha256']);
        $t->same(2587, $smartartTitleFallbackPair['checkedInPptx']['bytes']);
        $t->same(296, $smartartTitleFallbackPair['checkedInNative']['bytes']);
        $t->same('percent-encoded-target', $percentEncodedTargetPair['stem']);
        $t->same('generated literal percent-encoded relationship target parity', $percentEncodedTargetPair['name']);
        $t->same('pptx-reader/percent-encoded-target.pptx|pptx-reader/percent-encoded-target.native', $percentEncodedTargetPair['pairKey']);
        $t->same('c43d087016af3aca9afd325e3c630c072e8629722a610bfcba248b18c37eddc3', $percentEncodedTargetPair['checkedInPptx']['sha256']);
        $t->same('9ceb6189090309ad8b3ea4ec49622cbf6f64d110928046136578c33c8fc48242', $percentEncodedTargetPair['checkedInNative']['sha256']);
        $t->same(2506, $percentEncodedTargetPair['checkedInPptx']['bytes']);
        $t->same(117, $percentEncodedTargetPair['checkedInNative']['bytes']);
        $t->same('qualified-bullet-level', $qualifiedBulletLevelPair['stem']);
        $t->same('generated qualified bullet level fallback parity', $qualifiedBulletLevelPair['name']);
        $t->same('pptx-reader/qualified-bullet-level.pptx|pptx-reader/qualified-bullet-level.native', $qualifiedBulletLevelPair['pairKey']);
        $t->same('975698555e2a3766273d24f0c8b11510f1856b9fae77d3fb53c4d70f01abf55b', $qualifiedBulletLevelPair['checkedInPptx']['sha256']);
        $t->same('c80440348f5567bb5cdd29313dc97aaf86b339b0c44211c873350f22cc49177b', $qualifiedBulletLevelPair['checkedInNative']['sha256']);
        $t->same(1431, $qualifiedBulletLevelPair['checkedInPptx']['bytes']);
        $t->same(259, $qualifiedBulletLevelPair['checkedInNative']['bytes']);
        $t->same('qualified-picture-metadata', $qualifiedPictureMetadataPair['stem']);
        $t->same('generated qualified picture metadata attribute parity', $qualifiedPictureMetadataPair['name']);
        $t->same('pptx-reader/qualified-picture-metadata.pptx|pptx-reader/qualified-picture-metadata.native', $qualifiedPictureMetadataPair['pairKey']);
        $t->same('701d31f75d1d665bf1ba38cd2ac14963a97193c41b4296ced7bd96e556136229', $qualifiedPictureMetadataPair['checkedInPptx']['sha256']);
        $t->same('19bd88efcd70aee87a078b7070ac1909f9c8ba2cb6b05b81e63bc9fcaccc179c', $qualifiedPictureMetadataPair['checkedInNative']['sha256']);
        $t->same(1936, $qualifiedPictureMetadataPair['checkedInPptx']['bytes']);
        $t->same(207, $qualifiedPictureMetadataPair['checkedInNative']['bytes']);
        $t->same('query-fragment-presentation-target', $queryFragmentPresentationTargetPair['stem']);
        $t->same('generated literal query/fragment presentation target parity', $queryFragmentPresentationTargetPair['name']);
        $t->same('pptx-reader/query-fragment-presentation-target.pptx|pptx-reader/query-fragment-presentation-target.native', $queryFragmentPresentationTargetPair['pairKey']);
        $t->same('ab5d9267694ea958a26c5f18262481400b7b4b5ab5ae821cb8ce05e1f1494eae', $queryFragmentPresentationTargetPair['checkedInPptx']['sha256']);
        $t->same('17bc3c608870f832f4e2f72c1da296d9895562e415ec1ee0ab593ffa222897b3', $queryFragmentPresentationTargetPair['checkedInNative']['sha256']);
        $t->same(2540, $queryFragmentPresentationTargetPair['checkedInPptx']['bytes']);
        $t->same(94, $queryFragmentPresentationTargetPair['checkedInNative']['bytes']);
        $t->same('rel-prefix-image-skip', $relPrefixImageSkipPair['stem']);
        $t->same('generated noncanonical relationship prefix image skip parity', $relPrefixImageSkipPair['name']);
        $t->same('pptx-reader/rel-prefix-image-skip.pptx|pptx-reader/rel-prefix-image-skip.native', $relPrefixImageSkipPair['pairKey']);
        $t->same('45a26c62512c0e943dc3ef8c007cc94af758ad1e9ca13a2d63ec08ac338fd05f', $relPrefixImageSkipPair['checkedInPptx']['sha256']);
        $t->same('346a3a5c5484f21810c5745c41db13a68de5c20a87fe3332bc7da5688fa6ea6b', $relPrefixImageSkipPair['checkedInNative']['sha256']);
        $t->same(2380, $relPrefixImageSkipPair['checkedInPptx']['bytes']);
        $t->same(90, $relPrefixImageSkipPair['checkedInNative']['bytes']);
        $t->same('repeated-slash-slide-target', $repeatedSlashSlideTargetPair['stem']);
        $t->same('generated repeated-slash slide target parity', $repeatedSlashSlideTargetPair['name']);
        $t->same('pptx-reader/repeated-slash-slide-target.pptx|pptx-reader/repeated-slash-slide-target.native', $repeatedSlashSlideTargetPair['pairKey']);
        $t->same('8592b2e23603c128d9a70849acbd17a50058c405c8699b60f4b01a83ef471300', $repeatedSlashSlideTargetPair['checkedInPptx']['sha256']);
        $t->same('8174d5156e67751932faec8de101c6e66bd7e7339183cc0bd0248a6137b06e05', $repeatedSlashSlideTargetPair['checkedInNative']['sha256']);
        $t->same(1310, $repeatedSlashSlideTargetPair['checkedInPptx']['bytes']);
        $t->same(92, $repeatedSlashSlideTargetPair['checkedInNative']['bytes']);
        $t->same('repeated-slash-presentation-target', $repeatedSlashPresentationTargetPair['stem']);
        $t->same('generated repeated-slash presentation target parity', $repeatedSlashPresentationTargetPair['name']);
        $t->same('pptx-reader/repeated-slash-presentation-target.pptx|pptx-reader/repeated-slash-presentation-target.native', $repeatedSlashPresentationTargetPair['pairKey']);
        $t->same('41899644c0c987c2ec8e96aa25b9ec8160476d1de1d02042c37721d150786f7c', $repeatedSlashPresentationTargetPair['checkedInPptx']['sha256']);
        $t->same('8f5ecb6310e128bbb0c8ec8203c5639722be03864ddcf5ef3e6f9c4f474ce331', $repeatedSlashPresentationTargetPair['checkedInNative']['sha256']);
        $t->same(1453, $repeatedSlashPresentationTargetPair['checkedInPptx']['bytes']);
        $t->same(94, $repeatedSlashPresentationTargetPair['checkedInNative']['bytes']);
        $t->same('rich-media-skip', $richMediaSkipPair['stem']);
        $t->same('generated rich media placeholder skip parity', $richMediaSkipPair['name']);
        $t->same('pptx-reader/rich-media-skip.pptx|pptx-reader/rich-media-skip.native', $richMediaSkipPair['pairKey']);
        $t->same('2d6d32f08c2c694292d220184cecbfd116e9260e9534720f8f313c56516b1226', $richMediaSkipPair['checkedInPptx']['sha256']);
        $t->same('dde7cc213ac82ae4f03a1c97dfaf72650bcafb5c9d5ce06497bf60ea8ceb688a', $richMediaSkipPair['checkedInNative']['sha256']);
        $t->same(2633, $richMediaSkipPair['checkedInPptx']['bytes']);
        $t->same(122, $richMediaSkipPair['checkedInNative']['bytes']);
        $t->same('rooted-slide-target', $rootedSlideTargetPair['stem']);
        $t->same('generated rooted slide relationship target parity', $rootedSlideTargetPair['name']);
        $t->same('pptx-reader/rooted-slide-target.pptx|pptx-reader/rooted-slide-target.native', $rootedSlideTargetPair['pairKey']);
        $t->same('f7ae4f4e696bee21ecbfc967fa96e70d9c67dbf5035ad1fa05e5f5974f6bd433', $rootedSlideTargetPair['checkedInPptx']['sha256']);
        $t->same('6059cb62d9ff8d71c8d9719a067256089eb239683e2486b6661f576748b2061b', $rootedSlideTargetPair['checkedInNative']['sha256']);
        $t->same(1529, $rootedSlideTargetPair['checkedInPptx']['bytes']);
        $t->same(129, $rootedSlideTargetPair['checkedInNative']['bytes']);
        $t->same('unknown-graphic-uri', $unknownGraphicUriPair['stem']);
        $t->same('generated unknown graphicData URI placeholder parity', $unknownGraphicUriPair['name']);
        $t->same('pptx-reader/unknown-graphic-uri.pptx|pptx-reader/unknown-graphic-uri.native', $unknownGraphicUriPair['pairKey']);
        $t->same('23e41aa0f6462f4c59aa42a3072ac6d76418571e234eeecdf8bce1bb4379e525', $unknownGraphicUriPair['checkedInPptx']['sha256']);
        $t->same('3b55757f406ad3bfc31c5928c6e978536cef7bf81e575cf9308ae092172b6c28', $unknownGraphicUriPair['checkedInNative']['sha256']);
        $t->same(1642, $unknownGraphicUriPair['checkedInPptx']['bytes']);
        $t->same(143, $unknownGraphicUriPair['checkedInNative']['bytes']);
        $t->same('wrong-typed-slide-relationship', $wrongTypedSlideRelationshipPair['stem']);
        $t->same('generated slide relationship Type ignored parity', $wrongTypedSlideRelationshipPair['name']);
        $t->same('pptx-reader/wrong-typed-slide-relationship.pptx|pptx-reader/wrong-typed-slide-relationship.native', $wrongTypedSlideRelationshipPair['pairKey']);
        $t->same('f781a8b009b67786a1df88e500dfd5111ce8848ee72a9eb9d20ba944172e2d70', $wrongTypedSlideRelationshipPair['checkedInPptx']['sha256']);
        $t->same('85a93bce0aa4d85219e859b3abdbaf78cf1e0c1884a6a59934b0e39e2790baf5', $wrongTypedSlideRelationshipPair['checkedInNative']['sha256']);
        $t->same(1449, $wrongTypedSlideRelationshipPair['checkedInPptx']['bytes']);
        $t->same(121, $wrongTypedSlideRelationshipPair['checkedInNative']['bytes']);
        $t->same(false, PptxUpstreamReaderEvidence::hasNoValidationIssues($report));
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticNativeMappedParity($report));
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticExecutableNativeAstParity($report));
        $t->same(true, PptxUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
        $t->same(true, PptxUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
        $t->same('planned-not-run', $report['runnerEvidence']['commandPlanStatus']);
        $t->same(PptxUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $report['runnerEvidence']['upstreamBinding']['expectedCommit']);
        $t->same('test:test-pandoc', $report['runnerEvidence']['target']['testSuite']);
        $t->same(['Readers', 'Pptx'], $report['runnerEvidence']['target']['tastyGroupPath']);
        $t->same('$2 == "Readers" && $3 == "Pptx"', $report['runnerEvidence']['target']['tastyPattern']);
        $t->true(in_array('.port-libs/pandoc-runner/logs/pptx-targeted-list-tests.txt', $report['runnerEvidence']['requiredTranscripts'], true));
        $t->true(in_array('.port-libs/pandoc-runner/artifacts/pptx-targeted-run/result.json', $report['runnerEvidence']['requiredArtifacts'], true));
        $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('local PHP PPTX reader output matches all 101 checked-in current PPTX/native pairs by normalized AST shape', $static['claimBoundaries']['doesAssert'], true));
        $t->true(in_array('checked-in executable native AST evidence shows pandoc 3.10, local PHP output, and paired .native fixtures match all 101 checked-in current PPTX fixtures by normalized AST shape', $static['claimBoundaries']['doesAssert'], true));
        $t->true(in_array('additional PPTX fixture discovery outside the checked-in 101-pair corpus', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('checked-in chart review metadata covers chart-placeholder.pptx and chart-embedded-workbook.pptx, including embedded workbook package relationships with hashed byte exposure', $static['claimBoundaries']['doesAssert'], true));
        $t->true(in_array('checked-in speaker note and comment review metadata covers speaker-notes.pptx and comments-ignored.pptx without rendering those records into native AST output', $static['claimBoundaries']['doesAssert'], true));
        $t->true(in_array('that alternate-content-skip.pptx/alternate-content-skip.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that background-image-skip.pptx/background-image-skip.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that body-before-title.pptx/body-before-title.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that minimal.pptx/minimal.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that missing-relationship-skip.pptx/missing-relationship-skip.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that missing-slide-relationship-type.pptx/missing-slide-relationship-type.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that multi-paragraph-table-cell.pptx/multi-paragraph-table-cell.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that multi-paragraph-textbox.pptx/multi-paragraph-textbox.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that multiple-paragraph-properties.pptx/multiple-paragraph-properties.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that namespace-agnostic-drawing-text.pptx/namespace-agnostic-drawing-text.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that namespace-scoped-table.pptx/namespace-scoped-table.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that nested-list.pptx/nested-list.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that no-slides.pptx/no-slides.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that no-title-fallback.pptx/no-title-fallback.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that non-relationship-child-relationships.pptx/non-relationship-child-relationships.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that paragraphless-textbox.pptx/paragraphless-textbox.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that empty-paragraph-textbox.pptx/empty-paragraph-textbox.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that empty-title-placeholder.pptx/empty-title-placeholder.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that end-paragraph-symbol.pptx/end-paragraph-symbol.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that external-mode-slide-target.pptx/external-mode-slide-target.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that external-rich-media-skip.pptx/external-rich-media-skip.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that root-targetmode-external.pptx/root-targetmode-external.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that first-text-body.pptx/first-text-body.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that break-tab-field.pptx/break-tab-field.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that whitespace-drawing-text.pptx/whitespace-drawing-text.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that bullets.pptx/bullets.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that bunone-wingdings.pptx/bunone-wingdings.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that case-sensitive-placeholder-type.pptx/case-sensitive-placeholder-type.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that wingdings-typeface-case.pptx/wingdings-typeface-case.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that center-title-placeholder.pptx/center-title-placeholder.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that cdata-entity-text.pptx/cdata-entity-text.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that chart-placeholder.pptx/chart-placeholder.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that chart-embedded-workbook.pptx/chart-embedded-workbook.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that comments-ignored.pptx/comments-ignored.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that content-part-skip.pptx/content-part-skip.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that diagram-missing-rels.pptx/diagram-missing-rels.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that diagram-no-relids.pptx/diagram-no-relids.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that direct-drawing-paragraphs.pptx/direct-drawing-paragraphs.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that document-properties.pptx/document-properties.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that dot-presentation-target.pptx/dot-presentation-target.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that dot-slide-target.pptx/dot-slide-target.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that duplicate-relationship-id.pptx/duplicate-relationship-id.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that duplicate-slide-reference.pptx/duplicate-slide-reference.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that embed-and-link-image.pptx/embed-and-link-image.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that connector-skip.pptx/connector-skip.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that connector-text-skip.pptx/connector-text-skip.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that embedded-image.pptx/embedded-image.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that empty-bullet-paragraph.pptx/empty-bullet-paragraph.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that empty-header-table.pptx/empty-header-table.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that first-run-property-symbol.pptx/first-run-property-symbol.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that generated-table.pptx/generated-table.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that graphic-no-uri.pptx/graphic-no-uri.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that table-span-review.pptx/table-span-review.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that grouped-shape-media-review.pptx/grouped-shape-media-review.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that grouped-shapes.pptx/grouped-shapes.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that hex-list-level.pptx/hex-list-level.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that signed-bullet-level.pptx/signed-bullet-level.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that hidden-shape-metadata.pptx/hidden-shape-metadata.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that hidden-slide.pptx/hidden-slide.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that ignored-slide-id-attributes.pptx/ignored-slide-id-attributes.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that hyperlink-text.pptx/hyperlink-text.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that inline-formatting.pptx/inline-formatting.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that list-continuation.pptx/list-continuation.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that linked-image-skip.pptx/linked-image-skip.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that literal-image-targets.pptx/literal-image-targets.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that picture-shape-hyperlink.pptx/picture-shape-hyperlink.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that media-relative-image-target.pptx/media-relative-image-target.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that two-slides.pptx/two-slides.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that unicode-drawing-text.pptx/unicode-drawing-text.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that speaker-notes.pptx/speaker-notes.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that subtitle-placeholder.pptx/subtitle-placeholder.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that numbered-list.pptx/numbered-list.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that octal-list-level.pptx/octal-list-level.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that overflow-bullet-level.pptx/overflow-bullet-level.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that parenthesized-bullet-level.pptx/parenthesized-bullet-level.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that pandoc-generated-image-alt-title.pptx/pandoc-generated-image-alt-title.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that percent-encoded-target.pptx/percent-encoded-target.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that qualified-bullet-level.pptx/qualified-bullet-level.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that rel-prefix-image-skip.pptx/rel-prefix-image-skip.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that repeated-slash-slide-target.pptx/repeated-slash-slide-target.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that repeated-slash-presentation-target.pptx/repeated-slash-presentation-target.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that rich-media-skip.pptx/rich-media-skip.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that rooted-slide-target.pptx/rooted-slide-target.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that unknown-graphic-uri.pptx/unknown-graphic-uri.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that wrong-typed-slide-relationship.pptx/wrong-typed-slide-relationship.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that shape-order.pptx/shape-order.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that slide-layout-placeholder-no-inherit.pptx/slide-layout-placeholder-no-inherit.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that slide-placeholders.pptx/slide-placeholders.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that smartart-hierarchy.pptx/smartart-hierarchy.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that smartart-title-fallback.pptx/smartart-title-fallback.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that qualified-picture-metadata.pptx/qualified-picture-metadata.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that table-styles-relationship.pptx/table-styles-relationship.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that text-comment-boundary.pptx/text-comment-boundary.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that textbox-without-nonvisual-properties.pptx/textbox-without-nonvisual-properties.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that transition-animation-metadata.pptx/transition-animation-metadata.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that transition-sound-media.pptx/transition-sound-media.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->contains('Static current evidence: valid-checked-in-current-pptx-reader-evidence comparisons=1 checkedInPairs=101', $text);
        $t->contains('Static native AST mapped parity: normalized-ast-equality-observed-not-runner-parity matches=101 mismatches=0 required=101', $text);
        $t->contains('Static executable native AST parity: normalized-ast-equality-observed-against-pandoc-executable matches=101 mismatches=0 required=101', $text);
        $t->contains('Static checked-in review metadata: valid-checked-in-current-pptx-review-metadata chartFixtures=2 charts=2 noteFixtures=1 notes=1 commentFixtures=1 comments=1', $text);
        $t->contains('Runner status: not-run', $text);
        $t->contains('Runner plan: planned-not-run', $text);
    },
    'reports invalid pptx reader evidence for missing and unreferenced fixtures' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile): void {
        $root = $makeTempDir();
        try {
            $writeFile($root, 'test/Tests/Readers/Pptx.hs', <<<'HS'
tests = [ testCompare "missing native" "pptx-reader/basic.pptx" "pptx-reader/basic.native" ]
HS);
            $writeFile($root, 'test/pptx-reader/basic.pptx', 'pptx bytes');
            $writeFile($root, 'test/pptx-reader/extra.pptx', 'extra pptx bytes');
            $writeFile($root, 'test/pptx-reader/extra.native', '[Para [Str "extra"]]');
            $writeFile($root, 'test/pptx-reader/extra2.pptx', 'extra2 pptx bytes');
            $writeFile($root, 'test/pptx-reader/extra2.native', '[Para [Str "extra2"]]');
            $writeFile($root, 'test/pptx-reader/orphan.native', '[Para [Str "orphan"]]');

            $report = (new PptxUpstreamReaderEvidence($root, '.'))->report();

            $t->same('invalid-upstream-pptx-reader-denominator', $report['validation']['status']);
            $t->true(in_array('missing-referenced-fixture-files', $report['validation']['issues'], true));
            $t->true(in_array('unreferenced-fixture-pairs', $report['validation']['issues'], true));
            $t->true(in_array('unpaired-pptx-fixtures', $report['validation']['issues'], true));
            $t->true(in_array('unpaired-native-fixtures', $report['validation']['issues'], true));
            $t->true(in_array('reader-test-count-does-not-match-fixture-pair-count', $report['validation']['issues'], true));
            $t->same(1, $report['denominator']['unpairedPptxFixtureCount']);
            $t->same(1, $report['denominator']['unpairedNativeFixtureCount']);
            $t->same(['pptx-reader/basic.pptx'], $report['denominator']['unpairedPptxFixtures']);
            $t->same(['pptx-reader/orphan.native'], $report['denominator']['unpairedNativeFixtures']);
            $t->same(1, count($report['denominator']['missingReferencedFiles']));
            $t->same('test/pptx-reader/basic.native', $report['denominator']['missingReferencedFiles'][0]['path']);
            $t->same(2, count($report['denominator']['unreferencedFixturePairs']));
            $t->same(false, PptxUpstreamReaderEvidence::hasNoValidationIssues($report));
        } finally {
            $removeTree($root);
        }
    },
    'cli gates checked-in current pptx static evidence without upstream runner claim' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $repoRoot = dirname(__DIR__, 3);
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($repoRoot . '/tools/pandoc-pptx-reader-evidence.php')
            . ' --repo-root=' . escapeshellarg($repoRoot)
            . ' --checked-in-fixtures'
            . ' --json'
            . ' --require-test-count=1'
            . ' --require-fixture-pair-count=101'
            . ' --require-static-current-evidence'
            . ' --require-static-native-mapped-parity'
            . ' --require-static-executable-native-ast-parity'
            . ' --require-runner-not-run'
            . ' --require-runner-plan';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(PptxUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $decoded['status']);
        $t->same('not-evaluated-missing-upstream-root', $decoded['validation']['status']);
        $t->same('valid-checked-in-current-pptx-reader-evidence', $decoded['staticCurrentEvidence']['validation']['status']);
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredCheckedInReaderTestCount($decoded, 1));
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredCheckedInFixturePairCount($decoded, 101));
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($decoded));
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticNativeMappedParity($decoded));
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticExecutableNativeAstParity($decoded));
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticReviewMetadata($decoded));
        $t->same('valid-checked-in-current-pptx-review-metadata', $decoded['staticCurrentEvidence']['checkedInReviewMetadata']['validation']['status']);
        $t->same(2, $decoded['staticCurrentEvidence']['checkedInReviewMetadata']['chartReviewCount']);
        $t->same(1, $decoded['staticCurrentEvidence']['checkedInReviewMetadata']['speakerNoteCount']);
        $t->same(1, $decoded['staticCurrentEvidence']['checkedInReviewMetadata']['commentAuthorCount']);
        $t->same(1, $decoded['staticCurrentEvidence']['checkedInReviewMetadata']['commentCount']);
        $t->same(true, PptxUpstreamReaderEvidence::hasRunnerNotRunEvidence($decoded));
        $t->same(true, PptxUpstreamReaderEvidence::hasRunnerPlanEvidence($decoded));
        $t->same('not-run', $decoded['runnerEvidence']['status']);
        $t->same('$2 == "Readers" && $3 == "Pptx"', $decoded['runnerEvidence']['target']['tastyPattern']);
        $t->same(false, PptxUpstreamReaderEvidence::hasNoValidationIssues($decoded));

        $summaryCommand = $command . ' summary';
        $summaryOutput = [];
        $summaryExitCode = 0;
        exec($summaryCommand, $summaryOutput, $summaryExitCode);
        $summary = json_decode(implode("\n", $summaryOutput), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $summaryExitCode);
        $t->same(PptxUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $summary['status']);
        $t->same(0, $summary['denominator']['readerTestCompareCount']);
        $t->same(101, $summary['staticCurrentEvidence']['checkedInFixturePairCount']);
        $t->same(101, $summary['staticCurrentEvidence']['nativeAstMappedParity']['normalizedAstMatchCount']);
        $t->same(0, $summary['staticCurrentEvidence']['nativeAstMappedParity']['normalizedAstMismatchCount']);
        $t->same(101, $summary['staticCurrentEvidence']['executableNativeAstMappedParity']['normalizedAstMatchCount']);
        $t->same(0, $summary['staticCurrentEvidence']['executableNativeAstMappedParity']['normalizedAstMismatchCount']);
        $t->same(101, $summary['staticCurrentEvidence']['executableNativeAstMappedParity']['pandocNativeFixtureMatchCount']);
        $t->same('pandoc 3.10', $summary['staticCurrentEvidence']['executableNativeAstMappedParity']['requiredPandocVersion']);
        $t->same('pandoc 3.10', $summary['staticCurrentEvidence']['executableNativeAstMappedParity']['pandocVersion']);
        $t->same(true, $summary['staticCurrentEvidence']['executableNativeAstMappedParity']['hasRequiredPandocVersion']);
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($summary));
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticNativeMappedParity($summary));
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticExecutableNativeAstParity($summary));
        $t->same(true, PptxUpstreamReaderEvidence::hasRunnerNotRunEvidence($summary));
        $t->same(true, PptxUpstreamReaderEvidence::hasRunnerPlanEvidence($summary));
        $t->true(!isset($summary['staticCurrentEvidence']['checkedInFixturePairs']), 'Reader evidence summary should omit bulky checked-in fixture rows');

        $unpairedNativeParitySummary = $summary;
        $unpairedNativeParitySummary['staticCurrentEvidence']['nativeAstMappedParity']['unpairedNativeCount'] = 1;
        $unpairedNativeParitySummary['staticCurrentEvidence']['nativeAstMappedParity']['unpairedNativeFixtures'] = ['orphan.native'];
        $t->same(false, PptxUpstreamReaderEvidence::hasRequiredStaticNativeMappedParity($unpairedNativeParitySummary));
        $t->same(false, PptxUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($unpairedNativeParitySummary));

        $unpairedStaticSummary = $summary;
        $unpairedStaticSummary['staticCurrentEvidence']['checkedInUnpairedPptxFixtureCount'] = 1;
        $t->same(false, PptxUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($unpairedStaticSummary));

        $missingRoot = $makeTempDir();
        try {
            $failingCommand = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg($repoRoot . '/tools/pandoc-pptx-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg($missingRoot)
                . ' --checked-in-fixtures'
                . ' --json'
                . ' --require-static-current-evidence'
                . ' 2>/dev/null';
            $failingOutput = [];
            $failingExitCode = 0;
            exec($failingCommand, $failingOutput, $failingExitCode);

            $t->same(1, $failingExitCode);
        } finally {
            $removeTree($missingRoot);
        }

        $conflictingCommand = str_replace(
            '--checked-in-fixtures',
            '--checked-in-fixtures --upstream-root=missing-upstream-root-for-static-pptx-gate',
            $command
        ) . ' 2>/dev/null';
        $conflictingOutput = [];
        $conflictingExitCode = 0;
        exec($conflictingCommand, $conflictingOutput, $conflictingExitCode);

        $t->same(2, $conflictingExitCode);

        $wrongCountCommand = str_replace('--require-fixture-pair-count=101', '--require-fixture-pair-count=92', $command) . ' 2>/dev/null';
        $wrongCountOutput = [];
        $wrongCountExitCode = 0;
        exec($wrongCountCommand, $wrongCountOutput, $wrongCountExitCode);

        $t->same(1, $wrongCountExitCode);
    },
    'workflow gates checked-in pptx native and executable parity corpora' => static function (TestRunner $t): void {
        $workflow = (string) file_get_contents(dirname(__DIR__, 3) . '/.github/workflows/pandoc-pptx.yml');

        $t->contains('Require upstream PPTX native AST parity fixture smoke', $workflow);
        $t->contains('Require checked-in PPTX native AST parity corpus', $workflow);
        $t->contains('Require upstream pandoc executable PPTX comparison smoke', $workflow);
        $t->contains('Require checked-in pandoc executable PPTX comparison corpus', $workflow);
        $t->contains('php tools/pandoc-pptx-native-ast.php', $workflow);
        $t->contains('php tools/pandoc-pptx-executable-native-ast.php', $workflow);
        $t->contains('--checked-in-fixtures', $workflow);
        $t->contains('--require-mapped-parity=101', $workflow);
        $t->contains('--require-executable-parity=101', $workflow);
        $t->contains('--require-pandoc-version="pandoc ${PANDOC_EXECUTABLE_VERSION}"', $workflow);
    },
    'cli gates supplied pptx reader upstream runner result artifact' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writePptxEvidenceTree, $writeRunnerTranscripts): void {
        $root = $makeTempDir();
        try {
            $writePptxEvidenceTree($root);
            $baseReport = (new PptxUpstreamReaderEvidence($root, '.'))->report();
            $runnerPlan = $baseReport['runnerEvidence'];
            $transcripts = $writeRunnerTranscripts($root, $runnerPlan['requiredTranscripts']);
            $testNames = array_map(
                static fn (array $case): string => $case['name'],
                $baseReport['denominator']['readerCases']
            );
            $payload = [
                'schemaVersion' => 2,
                'runner' => 'Cabal/Tasty Pandoc PPTX reader suite',
                'runnerExecuted' => true,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'commit' => PptxUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT,
                ],
                'target' => $runnerPlan['target'],
                'command' => $runnerPlan['futureCommands'][2],
                'exitCode' => 0,
                'testCount' => 1,
                'passedCount' => 1,
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
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-pptx-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg($root)
                . ' --upstream-root=' . escapeshellarg($root)
                . ' --runner-result-artifact=' . escapeshellarg($root . '/result.json')
                . ' --json'
                . ' --require-test-count=1'
                . ' --require-fixture-pair-count=1'
                . ' --require-no-validation-issues'
                . ' --require-runner-result-artifact';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same('completed', $decoded['runnerEvidence']['status']);
            $t->same('valid-upstream-pptx-reader-runner-result-artifact', $decoded['runnerEvidence']['validation']['status']);
            $t->same(true, PptxUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($decoded));

            $payload = $validPayload;
            $payload['failedCount'] = 1;
            $payload['exitCode'] = 1;
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
    'generic reader runner artifact tool writes and validates pptx result artifact' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writePptxEvidenceTree, $writeRunnerTranscripts): void {
        $root = $makeTempDir();
        try {
            $writePptxEvidenceTree($root);
            $baseReport = (new PptxUpstreamReaderEvidence($root, '.'))->report();
            $runnerPlan = $baseReport['runnerEvidence'];
            $testNames = ['text extraction'];
            $writeRunnerTranscripts($root, $runnerPlan['requiredTranscripts'], 'pptx', $testNames);

            $artifactPath = '.port-libs/pandoc-runner/artifacts/pptx-targeted-run/result.json';
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-reader-runner-artifact.php')
                . ' --repo-root=' . escapeshellarg($root)
                . ' --upstream-root=' . escapeshellarg($root)
                . ' --format=pptx'
                . ' --write-result-artifact=' . escapeshellarg($artifactPath)
                . ' --result-started-at-utc=2026-07-05T00:00:00Z'
                . ' --result-finished-at-utc=2026-07-05T00:00:01Z'
                . ' --require-valid-result-artifact'
                . ' --json';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
            $writtenArtifact = $root . '/' . $artifactPath;
            $payload = json_decode((string) file_get_contents($writtenArtifact), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same('pandoc-reader-runner-artifact', $decoded['tool']);
            $t->same('pptx', $decoded['format']);
            $t->same('runner-result-artifact-valid', $decoded['status']);
            $t->same('valid-upstream-pptx-reader-runner-result-artifact', $decoded['validation']['status']);
            $t->same(true, $decoded['resultArtifact']['written']);
            $t->same($artifactPath, $decoded['resultArtifact']['path']);
            $t->same(['text extraction'], $decoded['expectedTestNames']);
            $t->same(2, $payload['schemaVersion']);
            $t->same('Cabal/Tasty Pandoc PPTX reader suite', $payload['runner']);
            $t->same(true, $payload['runnerExecuted']);
            $t->same(1, $payload['testCount']);
            $t->same(1, $payload['passedCount']);
            $t->same(0, $payload['failedCount']);
            $t->same('valid-targeted-runner-transcripts', $payload['transcriptEvidence']['status']);
            $t->same($runnerPlan['futureCommands'][2], $payload['command']);
            $t->same($runnerPlan['requiredTranscripts'], $payload['transcriptPaths']);
        } finally {
            $removeTree($root);
        }
    },
    'cli gates pptx reader evidence counts and validation issues' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writePptxEvidenceTree): void {
        $root = $makeTempDir();
        try {
            $writePptxEvidenceTree($root);
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-pptx-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg(dirname($root))
                . ' --upstream-root=' . escapeshellarg($root)
                . ' --json'
                . ' --require-test-count=1'
                . ' --require-fixture-pair-count=1'
                . ' --require-no-validation-issues'
                . ' --require-runner-not-run'
                . ' --require-runner-plan';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same(1, $decoded['denominator']['readerTestCompareCount']);
            $t->same(1, $decoded['denominator']['fixturePairCount']);
            $t->same('valid-upstream-pptx-reader-denominator', $decoded['validation']['status']);
            $t->same(true, PptxUpstreamReaderEvidence::hasRunnerPlanEvidence($decoded));

            $failingCommand = str_replace('--require-test-count=1', '--require-test-count=2', $command) . ' 2>/dev/null';
            $failingOutput = [];
            $failingExitCode = 0;
            exec($failingCommand, $failingOutput, $failingExitCode);

            $t->same(1, $failingExitCode);
        } finally {
            $removeTree($root);
        }
    },
];
