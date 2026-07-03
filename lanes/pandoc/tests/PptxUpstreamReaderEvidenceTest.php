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

$writeRunnerTranscripts = static function (string $root, array $paths, string $label = 'pptx') use ($writeFile): array {
    $records = [];
    foreach (array_values($paths) as $index => $path) {
        $contents = $label . " runner transcript " . (string) ($index + 1) . "\n" . $path . "\n";
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
        $pairsByStem = [];
        foreach ($static['checkedInFixturePairs'] as $fixturePair) {
            $pairsByStem[$fixturePair['stem']] = $fixturePair;
        }
        $pair = $pairsByStem['basic'];
        $bodyBeforeTitlePair = $pairsByStem['body-before-title'];
        $minimalPair = $pairsByStem['minimal'];
        $missingRelationshipSkipPair = $pairsByStem['missing-relationship-skip'];
        $multiParagraphTextboxPair = $pairsByStem['multi-paragraph-textbox'];
        $multipleParagraphPropertiesPair = $pairsByStem['multiple-paragraph-properties'];
        $nestedListPair = $pairsByStem['nested-list'];
        $emptyParagraphTextboxPair = $pairsByStem['empty-paragraph-textbox'];
        $firstTextBodyPair = $pairsByStem['first-text-body'];
        $breakTabFieldPair = $pairsByStem['break-tab-field'];
        $bulletsPair = $pairsByStem['bullets'];
        $bunoneWingdingsPair = $pairsByStem['bunone-wingdings'];
        $caseSensitivePlaceholderTypePair = $pairsByStem['case-sensitive-placeholder-type'];
        $wingdingsTypefaceCasePair = $pairsByStem['wingdings-typeface-case'];
        $centerTitlePlaceholderPair = $pairsByStem['center-title-placeholder'];
        $chartPlaceholderPair = $pairsByStem['chart-placeholder'];
        $commentsIgnoredPair = $pairsByStem['comments-ignored'];
        $contentPartSkipPair = $pairsByStem['content-part-skip'];
        $directDrawingParagraphsPair = $pairsByStem['direct-drawing-paragraphs'];
        $dotSlideTargetPair = $pairsByStem['dot-slide-target'];
        $duplicateSlideReferencePair = $pairsByStem['duplicate-slide-reference'];
        $connectorSkipPair = $pairsByStem['connector-skip'];
        $embeddedImagePair = $pairsByStem['embedded-image'];
        $emptyBulletParagraphPair = $pairsByStem['empty-bullet-paragraph'];
        $generatedTablePair = $pairsByStem['generated-table'];
        $tableSpanReviewPair = $pairsByStem['table-span-review'];
        $groupedShapesPair = $pairsByStem['grouped-shapes'];
        $hexListLevelPair = $pairsByStem['hex-list-level'];
        $signedBulletLevelPair = $pairsByStem['signed-bullet-level'];
        $hiddenSlidePair = $pairsByStem['hidden-slide'];
        $ignoredSlideIdAttributesPair = $pairsByStem['ignored-slide-id-attributes'];
        $hyperlinkTextPair = $pairsByStem['hyperlink-text'];
        $inlineFormattingPair = $pairsByStem['inline-formatting'];
        $listContinuationPair = $pairsByStem['list-continuation'];
        $linkedImageSkipPair = $pairsByStem['linked-image-skip'];
        $twoSlidesPair = $pairsByStem['two-slides'];
        $speakerNotesPair = $pairsByStem['speaker-notes'];
        $numberedListPair = $pairsByStem['numbered-list'];
        $shapeOrderPair = $pairsByStem['shape-order'];
        $slidePlaceholdersPair = $pairsByStem['slide-placeholders'];
        $smartartHierarchyPair = $pairsByStem['smartart-hierarchy'];
        $noTitleFallbackPair = $pairsByStem['no-title-fallback'];
        $paragraphlessTextboxPair = $pairsByStem['paragraphless-textbox'];
        $percentEncodedTargetPair = $pairsByStem['percent-encoded-target'];
        $richMediaSkipPair = $pairsByStem['rich-media-skip'];

        $t->same(PptxUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
        $t->same('valid-checked-in-current-pptx-reader-evidence', $static['validation']['status']);
        $t->same([], $static['validation']['issues']);
        $t->same(1, $static['readerDenominator']['expectedCompareCount']);
        $t->same('text extraction', $static['readerDenominator']['expectedReaderCases'][0]['name']);
        $t->same('pptx-reader/basic.pptx', $static['readerDenominator']['expectedReaderCases'][0]['pptx']);
        $t->same('pptx-reader/basic.native', $static['readerDenominator']['expectedReaderCases'][0]['native']);
        $t->same(45, $static['checkedInFixturePairCount']);
        $t->same(0, $static['checkedInUnpairedPptxFixtureCount']);
        $t->same(0, $static['checkedInUnpairedNativeFixtureCount']);
        $t->same([], $static['checkedInUnpairedPptxFixtures']);
        $t->same([], $static['checkedInUnpairedNativeFixtures']);
        $t->same('checked-in-current-pptx-native-normalized-ast-parity', $nativeParity['kind']);
        $t->same('completed', $nativeParity['status']);
        $t->same(false, $nativeParity['skipped']);
        $t->same(45, $nativeParity['requiredPairCount']);
        $t->same(45, $nativeParity['totalPairCount']);
        $t->same(45, $nativeParity['comparedPairCount']);
        $t->same(45, $nativeParity['pptxParsedCount']);
        $t->same(45, $nativeParity['nativeParsedCount']);
        $t->same(45, $nativeParity['bothParsedCount']);
        $t->same(0, $nativeParity['parseFailureCount']);
        $t->same(45, $nativeParity['normalizedAstMatchCount']);
        $t->same(0, $nativeParity['normalizedAstMismatchCount']);
        $t->same('normalized-ast-equality-observed-not-runner-parity', $nativeParity['astParityStatus']);
        $t->same(true, $nativeParity['hasRequiredMappedParity']);
        $t->same('checked-in-current-pptx-executable-native-normalized-ast-parity', $executableParity['kind']);
        $t->same('completed', $executableParity['status']);
        $t->same(false, $executableParity['skipped']);
        $t->same(45, $executableParity['requiredPptxCount']);
        $t->same('valid-checked-in-current-pptx-executable-native-ast-parity', $executableParity['validation']['status']);
        $t->same([], $executableParity['validation']['issues']);
        $t->same('lanes/pandoc/fixtures/upstream-current-pptx-reader/checked-in.executable-native-ast.json', $executableParity['snapshotFile']['path']);
        $t->same(true, $executableParity['snapshotFile']['present']);
        $t->same('a4f6bc5e4f2bdf1cec32b16c2a44c328ebc9f711588b84ae57f1a5f4c57fbd88', $executableParity['snapshotFile']['sha256']);
        $t->same(9398, $executableParity['snapshotFile']['bytes']);
        $t->same('2026-07-03', $executableParity['capturedDate']);
        $t->same('pandoc 3.10', $executableParity['requiredPandocVersion']);
        $t->same('pandoc 3.10', $executableParity['pandocVersion']);
        $t->same(45, $executableParity['totalPptxCount']);
        $t->same(45, $executableParity['comparedPptxCount']);
        $t->same(45, $executableParity['localParsedCount']);
        $t->same(45, $executableParity['pandocParsedCount']);
        $t->same(45, $executableParity['nativeFixtureParsedCount']);
        $t->same(45, $executableParity['bothParsedCount']);
        $t->same(0, $executableParity['parseFailureCount']);
        $t->same(45, $executableParity['normalizedAstMatchCount']);
        $t->same(0, $executableParity['normalizedAstMismatchCount']);
        $t->same(45, $executableParity['pandocNativeFixtureComparedCount']);
        $t->same(45, $executableParity['pandocNativeFixtureMatchCount']);
        $t->same(0, $executableParity['pandocNativeFixtureMismatchCount']);
        $t->same('normalized-ast-equality-observed-against-pandoc-executable', $executableParity['astParityStatus']);
        $t->same(true, $executableParity['hasRequiredExecutableParity']);
        $t->same(true, $executableParity['hasRequiredPandocVersion']);
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticExecutableNativeAstParity($report));
        $t->same('basic', $pair['stem']);
        $t->same('pptx-reader/basic.pptx|pptx-reader/basic.native', $pair['pairKey']);
        $t->same('e48fd9c2f8369d1792197e301d5fea676bf6e51097a24af7d85831a6f96dc2dc', $pair['checkedInPptx']['sha256']);
        $t->same('42804b9b1954094a4b0ff0be20084e2e6d9bc0a84272f34f7f219f82505da6b4', $pair['checkedInNative']['sha256']);
        $t->same(111674, $pair['checkedInPptx']['bytes']);
        $t->same(3966, $pair['checkedInNative']['bytes']);
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
        $t->same('nested-list', $nestedListPair['stem']);
        $t->same('generated adjacent list-level split parity', $nestedListPair['name']);
        $t->same('pptx-reader/nested-list.pptx|pptx-reader/nested-list.native', $nestedListPair['pairKey']);
        $t->same('c85b56c09a3568286e4c0d7b1979d88b700d5f609e121955c691a58f2bb97ff0', $nestedListPair['checkedInPptx']['sha256']);
        $t->same('395c237357a332023f6bb3c991f2f84d54be6fb277ce964cdaad6d9ffe2336a6', $nestedListPair['checkedInNative']['sha256']);
        $t->same(1703, $nestedListPair['checkedInPptx']['bytes']);
        $t->same(253, $nestedListPair['checkedInNative']['bytes']);
        $t->same('no-title-fallback', $noTitleFallbackPair['stem']);
        $t->same('generated no-title slide fallback parity', $noTitleFallbackPair['name']);
        $t->same('pptx-reader/no-title-fallback.pptx|pptx-reader/no-title-fallback.native', $noTitleFallbackPair['pairKey']);
        $t->same('de4ad6bc1bf66072bdcb96e31390955f4a283e3be94b0f691dc96ba36765f557', $noTitleFallbackPair['checkedInPptx']['sha256']);
        $t->same('fcd4183bbfebc6ecd4118786cf7bbc1fb760f2e385d6bbb9bab6031851557763', $noTitleFallbackPair['checkedInNative']['sha256']);
        $t->same(1533, $noTitleFallbackPair['checkedInPptx']['bytes']);
        $t->same(103, $noTitleFallbackPair['checkedInNative']['bytes']);
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
        $t->same('first-text-body', $firstTextBodyPair['stem']);
        $t->same('generated first text body child parity', $firstTextBodyPair['name']);
        $t->same('pptx-reader/first-text-body.pptx|pptx-reader/first-text-body.native', $firstTextBodyPair['pairKey']);
        $t->same('9632d9605fcc1ee78db83843121f12c297eafeab15d6098932e8738d6dd74624', $firstTextBodyPair['checkedInPptx']['sha256']);
        $t->same('98aabf841a37c3c677ef20c7ac0a3987ec55bcc38bde09efcaac83bfc39619e7', $firstTextBodyPair['checkedInNative']['sha256']);
        $t->same(1552, $firstTextBodyPair['checkedInPptx']['bytes']);
        $t->same(63, $firstTextBodyPair['checkedInNative']['bytes']);
        $t->same('break-tab-field', $breakTabFieldPair['stem']);
        $t->same('generated break, tab, and field text boundary parity', $breakTabFieldPair['name']);
        $t->same('pptx-reader/break-tab-field.pptx|pptx-reader/break-tab-field.native', $breakTabFieldPair['pairKey']);
        $t->same('eab556ea99844fb5f815f977d46d5a1923d59f71682c7cceae5e23b5937f113c', $breakTabFieldPair['checkedInPptx']['sha256']);
        $t->same('e619a9e7b375700d5fd8c2c74cd9bb5c424098d39b972212a86f58764affadf4', $breakTabFieldPair['checkedInNative']['sha256']);
        $t->same(1435, $breakTabFieldPair['checkedInPptx']['bytes']);
        $t->same(113, $breakTabFieldPair['checkedInNative']['bytes']);
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
        $t->same('chart-placeholder', $chartPlaceholderPair['stem']);
        $t->same('generated chart graphic placeholder parity', $chartPlaceholderPair['name']);
        $t->same('pptx-reader/chart-placeholder.pptx|pptx-reader/chart-placeholder.native', $chartPlaceholderPair['pairKey']);
        $t->same('43da4b9bc501e22c665706a9cf93597e445ee123e06ea1edaed49858e862f2ed', $chartPlaceholderPair['checkedInPptx']['sha256']);
        $t->same('c583540a28768d66ecd7aca44a211ae5ebff6cdec77eeb38b19ac10e5ad11f27', $chartPlaceholderPair['checkedInNative']['sha256']);
        $t->same(1659, $chartPlaceholderPair['checkedInPptx']['bytes']);
        $t->same(180, $chartPlaceholderPair['checkedInNative']['bytes']);
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
        $t->same('direct-drawing-paragraphs', $directDrawingParagraphsPair['stem']);
        $t->same('generated direct drawing paragraph boundary parity', $directDrawingParagraphsPair['name']);
        $t->same('pptx-reader/direct-drawing-paragraphs.pptx|pptx-reader/direct-drawing-paragraphs.native', $directDrawingParagraphsPair['pairKey']);
        $t->same('98867e5898bd31ff7f82498fe13d464b237b784ce149c6591c489c00b9bc0979', $directDrawingParagraphsPair['checkedInPptx']['sha256']);
        $t->same('cc7a90f26bdb968391ccaecfd3172a4666636d309f3024f67948022e88460b22', $directDrawingParagraphsPair['checkedInNative']['sha256']);
        $t->same(1405, $directDrawingParagraphsPair['checkedInPptx']['bytes']);
        $t->same(124, $directDrawingParagraphsPair['checkedInNative']['bytes']);
        $t->same('dot-slide-target', $dotSlideTargetPair['stem']);
        $t->same('generated dot-segment slide target parity', $dotSlideTargetPair['name']);
        $t->same('pptx-reader/dot-slide-target.pptx|pptx-reader/dot-slide-target.native', $dotSlideTargetPair['pairKey']);
        $t->same('89b8ceba7657e20909a0406ac371c75b81929b72555b4a698e77ed0bcf944373', $dotSlideTargetPair['checkedInPptx']['sha256']);
        $t->same('835dfba7de0cdcc016d24d7eba54ff6eee05d0434da2154bd49f51fe25a66bb4', $dotSlideTargetPair['checkedInNative']['sha256']);
        $t->same(1280, $dotSlideTargetPair['checkedInPptx']['bytes']);
        $t->same(101, $dotSlideTargetPair['checkedInNative']['bytes']);
        $t->same('duplicate-slide-reference', $duplicateSlideReferencePair['stem']);
        $t->same('generated duplicate slide reference parity', $duplicateSlideReferencePair['name']);
        $t->same('pptx-reader/duplicate-slide-reference.pptx|pptx-reader/duplicate-slide-reference.native', $duplicateSlideReferencePair['pairKey']);
        $t->same('f598167fa0995e6069126c2a00f9ed92c7732df26210b9952c8c0a54022d30c6', $duplicateSlideReferencePair['checkedInPptx']['sha256']);
        $t->same('a13be46a0ba2e04ae56ee4da86015c4f30401c654f942f574c2d3516a0eb2a3d', $duplicateSlideReferencePair['checkedInNative']['sha256']);
        $t->same(1895, $duplicateSlideReferencePair['checkedInPptx']['bytes']);
        $t->same(176, $duplicateSlideReferencePair['checkedInNative']['bytes']);
        $t->same('connector-skip', $connectorSkipPair['stem']);
        $t->same('generated connector shape skip parity', $connectorSkipPair['name']);
        $t->same('pptx-reader/connector-skip.pptx|pptx-reader/connector-skip.native', $connectorSkipPair['pairKey']);
        $t->same('ea84954b53c9ff9b53419df4828b32e191261c8e00375f20bd03ea160326a25b', $connectorSkipPair['checkedInPptx']['sha256']);
        $t->same('df89712378d3c5d4994094744ecd4e20f482e0231acd053619ebf92eff5b1254', $connectorSkipPair['checkedInNative']['sha256']);
        $t->same(1493, $connectorSkipPair['checkedInPptx']['bytes']);
        $t->same(139, $connectorSkipPair['checkedInNative']['bytes']);
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
        $t->same('generated-table', $generatedTablePair['stem']);
        $t->same('generated table extraction parity', $generatedTablePair['name']);
        $t->same('pptx-reader/generated-table.pptx|pptx-reader/generated-table.native', $generatedTablePair['pairKey']);
        $t->same('85fec7638ef6f82c43cd805e9064146c4602cf5e7384ccdfa60a55048ec67b78', $generatedTablePair['checkedInPptx']['sha256']);
        $t->same('17b1efbb9d7b21ddf994fffd6c9d34110c48668ab144fd5b027d40034ec2e832', $generatedTablePair['checkedInNative']['sha256']);
        $t->same(1702, $generatedTablePair['checkedInPptx']['bytes']);
        $t->same(1192, $generatedTablePair['checkedInNative']['bytes']);
        $t->same('table-span-review', $tableSpanReviewPair['stem']);
        $t->same('generated table span review-only parity', $tableSpanReviewPair['name']);
        $t->same('pptx-reader/table-span-review.pptx|pptx-reader/table-span-review.native', $tableSpanReviewPair['pairKey']);
        $t->same('6d39a50f3215706922877dd2148afb0e55208a7600d2ebb48e60830d7d160b0c', $tableSpanReviewPair['checkedInPptx']['sha256']);
        $t->same('8df034dad767bbd20cc5f1f9fb875eecf84b8636dc74100677433cda03b304ce', $tableSpanReviewPair['checkedInNative']['sha256']);
        $t->same(1739, $tableSpanReviewPair['checkedInPptx']['bytes']);
        $t->same(1598, $tableSpanReviewPair['checkedInNative']['bytes']);
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
        $t->same('two-slides', $twoSlidesPair['stem']);
        $t->same('generated two-slide ordering parity', $twoSlidesPair['name']);
        $t->same('pptx-reader/two-slides.pptx|pptx-reader/two-slides.native', $twoSlidesPair['pairKey']);
        $t->same('58e37ebe22ba5f7e5b9f7c3fe886ae5ff085876371178e63cc115a8f6d4e052c', $twoSlidesPair['checkedInPptx']['sha256']);
        $t->same('269e2c8b638af9834b52a0ff23c795578f9b21404e27c60d846cf81b3520596a', $twoSlidesPair['checkedInNative']['sha256']);
        $t->same(1897, $twoSlidesPair['checkedInPptx']['bytes']);
        $t->same(177, $twoSlidesPair['checkedInNative']['bytes']);
        $t->same('speaker-notes', $speakerNotesPair['stem']);
        $t->same('generated speaker notes visibility parity', $speakerNotesPair['name']);
        $t->same('pptx-reader/speaker-notes.pptx|pptx-reader/speaker-notes.native', $speakerNotesPair['pairKey']);
        $t->same('52d0a82f3a84c594a9be816307c90b918cb914802bd3622a4cf9e2c06f40ddc5', $speakerNotesPair['checkedInPptx']['sha256']);
        $t->same('24f10e8e2632d64f9afb7a3aac8b0e48570d8ef61d76f6f0a51f841d104142f1', $speakerNotesPair['checkedInNative']['sha256']);
        $t->same(2511, $speakerNotesPair['checkedInPptx']['bytes']);
        $t->same(95, $speakerNotesPair['checkedInNative']['bytes']);
        $t->same('numbered-list', $numberedListPair['stem']);
        $t->same('generated auto-numbered paragraph boundary parity', $numberedListPair['name']);
        $t->same('pptx-reader/numbered-list.pptx|pptx-reader/numbered-list.native', $numberedListPair['pairKey']);
        $t->same('ba1162b8a31aba2b9cc01b1d346a070d66a0f8666afa44e0ace72bfdd76f1d4b', $numberedListPair['checkedInPptx']['sha256']);
        $t->same('be9e2f1c3a9f5815ea6cc86debe2ff081a4666931dd2e48c32245cd3de40cd9f', $numberedListPair['checkedInNative']['sha256']);
        $t->same(1520, $numberedListPair['checkedInPptx']['bytes']);
        $t->same(118, $numberedListPair['checkedInNative']['bytes']);
        $t->same('shape-order', $shapeOrderPair['stem']);
        $t->same('generated plain text shape ordering parity', $shapeOrderPair['name']);
        $t->same('pptx-reader/shape-order.pptx|pptx-reader/shape-order.native', $shapeOrderPair['pairKey']);
        $t->same('3f92fd142900b957b23cfe2b1afb01d2785d23b77ae62c23429d6bd11fd3c02f', $shapeOrderPair['checkedInPptx']['sha256']);
        $t->same('911f29fe22d020d181e007478bff7c157f6df49d06f7c42798bb3a933d33f427', $shapeOrderPair['checkedInNative']['sha256']);
        $t->same(1521, $shapeOrderPair['checkedInPptx']['bytes']);
        $t->same(135, $shapeOrderPair['checkedInNative']['bytes']);
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
        $t->same('percent-encoded-target', $percentEncodedTargetPair['stem']);
        $t->same('generated literal percent-encoded relationship target parity', $percentEncodedTargetPair['name']);
        $t->same('pptx-reader/percent-encoded-target.pptx|pptx-reader/percent-encoded-target.native', $percentEncodedTargetPair['pairKey']);
        $t->same('c43d087016af3aca9afd325e3c630c072e8629722a610bfcba248b18c37eddc3', $percentEncodedTargetPair['checkedInPptx']['sha256']);
        $t->same('9ceb6189090309ad8b3ea4ec49622cbf6f64d110928046136578c33c8fc48242', $percentEncodedTargetPair['checkedInNative']['sha256']);
        $t->same(2506, $percentEncodedTargetPair['checkedInPptx']['bytes']);
        $t->same(117, $percentEncodedTargetPair['checkedInNative']['bytes']);
        $t->same('rich-media-skip', $richMediaSkipPair['stem']);
        $t->same('generated rich media placeholder skip parity', $richMediaSkipPair['name']);
        $t->same('pptx-reader/rich-media-skip.pptx|pptx-reader/rich-media-skip.native', $richMediaSkipPair['pairKey']);
        $t->same('2d6d32f08c2c694292d220184cecbfd116e9260e9534720f8f313c56516b1226', $richMediaSkipPair['checkedInPptx']['sha256']);
        $t->same('dde7cc213ac82ae4f03a1c97dfaf72650bcafb5c9d5ce06497bf60ea8ceb688a', $richMediaSkipPair['checkedInNative']['sha256']);
        $t->same(2633, $richMediaSkipPair['checkedInPptx']['bytes']);
        $t->same(122, $richMediaSkipPair['checkedInNative']['bytes']);
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
        $t->true(in_array('local PHP PPTX reader output matches all 45 checked-in current PPTX/native pairs by normalized AST shape', $static['claimBoundaries']['doesAssert'], true));
        $t->true(in_array('checked-in executable native AST evidence shows pandoc 3.10, local PHP output, and paired .native fixtures match all 45 checked-in current PPTX fixtures by normalized AST shape', $static['claimBoundaries']['doesAssert'], true));
        $t->true(in_array('that body-before-title.pptx/body-before-title.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that minimal.pptx/minimal.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that missing-relationship-skip.pptx/missing-relationship-skip.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that multi-paragraph-textbox.pptx/multi-paragraph-textbox.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that multiple-paragraph-properties.pptx/multiple-paragraph-properties.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that nested-list.pptx/nested-list.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that no-title-fallback.pptx/no-title-fallback.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that paragraphless-textbox.pptx/paragraphless-textbox.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that empty-paragraph-textbox.pptx/empty-paragraph-textbox.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that first-text-body.pptx/first-text-body.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that break-tab-field.pptx/break-tab-field.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that bullets.pptx/bullets.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that bunone-wingdings.pptx/bunone-wingdings.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that case-sensitive-placeholder-type.pptx/case-sensitive-placeholder-type.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that wingdings-typeface-case.pptx/wingdings-typeface-case.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that center-title-placeholder.pptx/center-title-placeholder.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that chart-placeholder.pptx/chart-placeholder.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that comments-ignored.pptx/comments-ignored.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that content-part-skip.pptx/content-part-skip.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that direct-drawing-paragraphs.pptx/direct-drawing-paragraphs.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that dot-slide-target.pptx/dot-slide-target.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that duplicate-slide-reference.pptx/duplicate-slide-reference.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that connector-skip.pptx/connector-skip.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that embedded-image.pptx/embedded-image.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that empty-bullet-paragraph.pptx/empty-bullet-paragraph.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that generated-table.pptx/generated-table.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that table-span-review.pptx/table-span-review.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that grouped-shapes.pptx/grouped-shapes.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that hex-list-level.pptx/hex-list-level.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that signed-bullet-level.pptx/signed-bullet-level.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that hidden-slide.pptx/hidden-slide.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that ignored-slide-id-attributes.pptx/ignored-slide-id-attributes.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that hyperlink-text.pptx/hyperlink-text.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that inline-formatting.pptx/inline-formatting.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that list-continuation.pptx/list-continuation.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that linked-image-skip.pptx/linked-image-skip.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that two-slides.pptx/two-slides.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that speaker-notes.pptx/speaker-notes.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that numbered-list.pptx/numbered-list.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that percent-encoded-target.pptx/percent-encoded-target.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that rich-media-skip.pptx/rich-media-skip.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that shape-order.pptx/shape-order.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that slide-placeholders.pptx/slide-placeholders.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that smartart-hierarchy.pptx/smartart-hierarchy.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->contains('Static current evidence: valid-checked-in-current-pptx-reader-evidence comparisons=1 checkedInPairs=45', $text);
        $t->contains('Static native AST mapped parity: normalized-ast-equality-observed-not-runner-parity matches=45 mismatches=0 required=45', $text);
        $t->contains('Static executable native AST parity: normalized-ast-equality-observed-against-pandoc-executable matches=45 mismatches=0 required=45', $text);
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
            . ' --upstream-root=' . escapeshellarg('missing-upstream-root-for-static-gate')
            . ' --json'
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
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($decoded));
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticNativeMappedParity($decoded));
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticExecutableNativeAstParity($decoded));
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
        $t->same(45, $summary['staticCurrentEvidence']['checkedInFixturePairCount']);
        $t->same(45, $summary['staticCurrentEvidence']['nativeAstMappedParity']['normalizedAstMatchCount']);
        $t->same(0, $summary['staticCurrentEvidence']['nativeAstMappedParity']['normalizedAstMismatchCount']);
        $t->same(45, $summary['staticCurrentEvidence']['executableNativeAstMappedParity']['normalizedAstMatchCount']);
        $t->same(0, $summary['staticCurrentEvidence']['executableNativeAstMappedParity']['normalizedAstMismatchCount']);
        $t->same(45, $summary['staticCurrentEvidence']['executableNativeAstMappedParity']['pandocNativeFixtureMatchCount']);
        $t->same('pandoc 3.10', $summary['staticCurrentEvidence']['executableNativeAstMappedParity']['requiredPandocVersion']);
        $t->same('pandoc 3.10', $summary['staticCurrentEvidence']['executableNativeAstMappedParity']['pandocVersion']);
        $t->same(true, $summary['staticCurrentEvidence']['executableNativeAstMappedParity']['hasRequiredPandocVersion']);
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($summary));
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticNativeMappedParity($summary));
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticExecutableNativeAstParity($summary));
        $t->same(true, PptxUpstreamReaderEvidence::hasRunnerNotRunEvidence($summary));
        $t->same(true, PptxUpstreamReaderEvidence::hasRunnerPlanEvidence($summary));
        $t->true(!isset($summary['staticCurrentEvidence']['checkedInFixturePairs']), 'Reader evidence summary should omit bulky checked-in fixture rows');

        $missingRoot = $makeTempDir();
        try {
            $failingCommand = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg($repoRoot . '/tools/pandoc-pptx-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg($missingRoot)
                . ' --upstream-root=' . escapeshellarg('missing-upstream-root-for-static-gate')
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
    },
    'workflow gates checked-in pptx native and executable parity corpora' => static function (TestRunner $t): void {
        $workflow = (string) file_get_contents(dirname(__DIR__, 3) . '/.github/workflows/pandoc-pptx.yml');
        $nativeCorpusGate = <<<'YAML'
      - name: Require checked-in PPTX native AST parity corpus
        run: |
          php tools/pandoc-pptx-native-ast.php \
            --checked-in-fixtures \
            --json \
            summary \
            --require-mapped-parity=45
YAML;
        $executableCorpusGate = <<<'YAML'
      - name: Require checked-in pandoc executable PPTX comparison corpus
        run: |
          php tools/pandoc-pptx-executable-native-ast.php \
            --checked-in-fixtures \
            --json \
            summary \
            --require-executable-parity=45
YAML;

        $t->contains('Require upstream PPTX native AST parity fixture smoke', $workflow);
        $t->contains($nativeCorpusGate, $workflow);
        $t->contains('Require upstream pandoc executable PPTX comparison smoke', $workflow);
        $t->contains($executableCorpusGate, $workflow);
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
