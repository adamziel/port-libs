<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__, 3);

$readText = static function (string $relativePath) use ($repoRoot): string {
    $path = $repoRoot . DIRECTORY_SEPARATOR . $relativePath;
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Unable to read {$relativePath}");
    }

    return $contents;
};

$readJson = static function (string $relativePath) use ($readText): array {
    $decoded = json_decode($readText($relativePath), true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Unable to decode {$relativePath}: " . json_last_error_msg());
    }

    return $decoded;
};

$countTrackedFiles = static function (array $patterns) use ($repoRoot): int {
    $command = 'git -C ' . escapeshellarg($repoRoot) . ' ls-files --';
    foreach ($patterns as $pattern) {
        $command .= ' ' . escapeshellarg($pattern);
    }

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException('Unable to inspect tracked files for DOCX parity evidence');
    }

    return count(array_filter($output, static fn (string $line): bool => $line !== ''));
};

return [
    'keeps DOCX parity evidence bounded to defensible coverage' => static function (TestRunner $t) use ($repoRoot, $readJson, $readText, $countTrackedFiles): void {
        $manifest = $readJson('lanes/pandoc/UPSTREAM_TEST_MANIFEST.json');
        $status = $readJson('lanes/pandoc/lane-status.json');
        $cacheManifest = $readJson('lanes/pandoc/UPSTREAM_DOCX_CACHE_MANIFEST.json');
        $haskellInventory = $readJson('lanes/pandoc/UPSTREAM_DOCX_HASKELL_INVENTORY.json');
        $focusedReaderEvidence = $readJson('lanes/pandoc/UPSTREAM_DOCX_HASKELL_FOCUSED_READER_EVIDENCE.json');
        $pandocStatus = $readText('PANDOC_STATUS.md');
        $workflow = $readText('.github/workflows/pandoc-docx.yml');

        $manifestAudit = $manifest['docxParityAudit'] ?? null;
        $statusAudit = $status['docxParityAudit'] ?? null;
        $t->true(is_array($manifestAudit), 'UPSTREAM_TEST_MANIFEST.json must carry DOCX parity audit evidence');
        $t->true(is_array($statusAudit), 'lane-status.json must carry DOCX parity audit evidence');
        $optionalCacheManifest = $manifestAudit['optionalUpstreamCacheManifest'] ?? null;
        $statusOptionalCacheManifest = $statusAudit['optionalUpstreamCacheManifest'] ?? null;
        $t->true(is_array($optionalCacheManifest), 'UPSTREAM_TEST_MANIFEST.json must reference the optional upstream DOCX cache manifest');
        $t->same($optionalCacheManifest, $statusOptionalCacheManifest);

        $t->same('not-full-upstream-docx-parity', $manifestAudit['verdict'] ?? null);
        $t->same($manifestAudit['verdict'], $statusAudit['verdict'] ?? null);
        $t->same(233, $manifestAudit['upstreamDocxDirectoryArtifacts'] ?? null);
        $t->same(112, $manifestAudit['upstreamNativeExpectedArtifacts'] ?? null);
        $t->same(121, $manifestAudit['upstreamDocxPackageArtifacts'] ?? null);
        $t->same(38, $manifestAudit['upstreamGoldenDocxArtifacts'] ?? null);
        $t->same('612e143fbe6d735b612c4800d21e61b7d44e4dca', $manifestAudit['currentUpstreamCommit'] ?? null);
        $t->same(236, $manifestAudit['currentUpstreamDocxDirectoryArtifacts'] ?? null);
        $t->same(113, $manifestAudit['currentUpstreamNativeExpectedArtifacts'] ?? null);
        $t->same(123, $manifestAudit['currentUpstreamDocxPackageArtifacts'] ?? null);
        $t->same([
            'test/docx/lists_restart_8367.docx',
            'test/docx/lists_restart_8367.native',
            'test/docx/ns0-reference.docx',
        ], $manifestAudit['currentUpstreamDriftFromPinnedArtifacts'] ?? null);
        $t->same($manifest['inventory']['docxDirectoryArtifacts'] ?? null, $manifestAudit['upstreamDocxDirectoryArtifacts'] ?? null);
        $t->same($manifest['inventory']['docxNativeExpectedArtifacts'] ?? null, $manifestAudit['upstreamNativeExpectedArtifacts'] ?? null);
        $t->same($manifest['inventory']['docxDocxArtifacts'] ?? null, $manifestAudit['upstreamDocxPackageArtifacts'] ?? null);
        $t->same($manifest['inventory']['docxGoldenDocxArtifacts'] ?? null, $manifestAudit['upstreamGoldenDocxArtifacts'] ?? null);

        $manifestHaskellInventory = $manifestAudit['haskellInventory'] ?? null;
        $statusHaskellInventory = $statusAudit['haskellInventory'] ?? null;
        $t->true(is_array($manifestHaskellInventory), 'UPSTREAM_TEST_MANIFEST.json must carry DOCX Haskell inventory denominator gates');
        $t->same($manifestHaskellInventory, $statusHaskellInventory);
        $t->same('lanes/pandoc/UPSTREAM_DOCX_HASKELL_INVENTORY.json', $manifestHaskellInventory['reportPath'] ?? null);
        $t->same($haskellInventory['schemaVersion'] ?? null, $manifestHaskellInventory['schemaVersion'] ?? null);
        $t->same($haskellInventory['tool'] ?? null, $manifestHaskellInventory['tool'] ?? null);
        $t->same($haskellInventory['evidenceKind'] ?? null, $manifestHaskellInventory['evidenceKind'] ?? null);
        $t->same($haskellInventory['upstream']['commit'] ?? null, $manifestHaskellInventory['upstreamCommit'] ?? null);
        $t->same('checked-docx-haskell-inventory-denominator-gates', $manifestHaskellInventory['status'] ?? null);
        $readerInventory = $haskellInventory['haskellReaderInventory'] ?? null;
        $writerInventory = $haskellInventory['haskellWriterInventory'] ?? null;
        $t->true(is_array($readerInventory), 'DOCX Haskell inventory must include reader denominator evidence');
        $t->true(is_array($writerInventory), 'DOCX Haskell inventory must include writer denominator evidence');
        $t->same($readerInventory['sourceFile'] ?? null, $manifestHaskellInventory['reader']['sourceFile'] ?? null);
        $t->same($readerInventory['entryPoint'] ?? null, $manifestHaskellInventory['reader']['entryPoint'] ?? null);
        $t->same(103, $manifestHaskellInventory['reader']['totalStaticCases'] ?? null);
        $t->same($readerInventory['totalStaticCases'] ?? null, $manifestHaskellInventory['reader']['totalStaticCases'] ?? null);
        $t->same($haskellInventory['localGates']['readerNativeParserAcceptance']['pairedRootDocxNativeArtifacts'] ?? null, $manifestHaskellInventory['reader']['localParserAcceptanceGateCases'] ?? null);
        $t->same($readerInventory['strictDefaultSameStemCasesCoveredByLocal74Gate'] ?? null, $manifestHaskellInventory['reader']['strictDefaultSameStemCasesCoveredByLocal74Gate'] ?? null);
        $t->same(36, $manifestHaskellInventory['reader']['casesNotCoveredByLocal74GateSemantics'] ?? null);
        $t->same($readerInventory['casesNotCoveredByLocal74GateSemantics'] ?? null, $manifestHaskellInventory['reader']['casesNotCoveredByLocal74GateSemantics'] ?? null);
        $t->same(36, $manifestHaskellInventory['reader']['notCoveredCaseCount'] ?? null);
        $t->same(count($readerInventory['notCoveredCases'] ?? []), $manifestHaskellInventory['reader']['notCoveredCaseCount'] ?? null);
        $t->same('requires-36-reader-uncovered-cases-preserved', $manifestHaskellInventory['reader']['gateStatus'] ?? null);
        $t->same($writerInventory['sourceFile'] ?? null, $manifestHaskellInventory['writer']['sourceFile'] ?? null);
        $t->same($writerInventory['entryPoint'] ?? null, $manifestHaskellInventory['writer']['entryPoint'] ?? null);
        $t->same(45, $manifestHaskellInventory['writer']['totalStaticCases'] ?? null);
        $t->same($writerInventory['totalStaticCases'] ?? null, $manifestHaskellInventory['writer']['totalStaticCases'] ?? null);
        $t->same(38, $manifestHaskellInventory['writer']['writerGoldenDocxTestCases'] ?? null);
        $t->same($writerInventory['writerGoldenDocxTestCases'] ?? null, $manifestHaskellInventory['writer']['writerGoldenDocxTestCases'] ?? null);
        $t->same(7, $manifestHaskellInventory['writer']['directHunitTestCases'] ?? null);
        $t->same($writerInventory['directHunitTestCases'] ?? null, $manifestHaskellInventory['writer']['directHunitTestCases'] ?? null);
        $t->same(38, $manifestHaskellInventory['writer']['writerGoldenCasesGeneratedAndComparedByLocal38Gate'] ?? null);
        $t->same($writerInventory['writerGoldenCasesGeneratedAndComparedByLocal38Gate'] ?? null, $manifestHaskellInventory['writer']['writerGoldenCasesGeneratedAndComparedByLocal38Gate'] ?? null);
        $t->same(38, $manifestHaskellInventory['writer']['writerGoldenCasesMatchingStableSemantics'] ?? null);
        $t->same($writerInventory['writerGoldenCasesMatchingStableSemantics'] ?? null, $manifestHaskellInventory['writer']['writerGoldenCasesMatchingStableSemantics'] ?? null);
        $t->same(7, $manifestHaskellInventory['writer']['casesNotCoveredByLocal38Gate'] ?? null);
        $t->same($writerInventory['casesNotCoveredByLocal38Gate'] ?? null, $manifestHaskellInventory['writer']['casesNotCoveredByLocal38Gate'] ?? null);
        $t->same(7, $manifestHaskellInventory['writer']['notCoveredCaseCount'] ?? null);
        $t->same(count($writerInventory['notCoveredCases'] ?? []), $manifestHaskellInventory['writer']['notCoveredCaseCount'] ?? null);
        $t->same('requires-7-direct-hunit-uncovered-cases-preserved', $manifestHaskellInventory['writer']['gateStatus'] ?? null);
        $t->contains('36 reader uncovered cases and 7 writer direct HUnit uncovered cases', (string) ($manifestHaskellInventory['claim'] ?? ''));

        $manifestFocusedReader = $manifestAudit['focusedReaderEvidence'] ?? null;
        $statusFocusedReader = $statusAudit['focusedReaderEvidence'] ?? null;
        $t->true(is_array($manifestFocusedReader), 'UPSTREAM_TEST_MANIFEST.json must carry DOCX focused reader evidence gates');
        $t->same($manifestFocusedReader, $statusFocusedReader);
        $t->same('lanes/pandoc/UPSTREAM_DOCX_HASKELL_FOCUSED_READER_EVIDENCE.json', $manifestFocusedReader['reportPath'] ?? null);
        $t->same($focusedReaderEvidence['schemaVersion'] ?? null, $manifestFocusedReader['schemaVersion'] ?? null);
        $t->same($focusedReaderEvidence['status'] ?? null, $manifestFocusedReader['status'] ?? null);
        $t->same($focusedReaderEvidence['evidenceKind'] ?? null, $manifestFocusedReader['evidenceKind'] ?? null);
        $t->same($focusedReaderEvidence['upstream']['commit'] ?? null, $manifestFocusedReader['upstreamCommit'] ?? null);
        $t->same(36, $manifestFocusedReader['denominatorCaseRows'] ?? null);
        $t->same(31, $manifestFocusedReader['coveredCaseCount'] ?? null);
        $t->same(5, $manifestFocusedReader['remainingOpenCaseCount'] ?? null);
        $t->same('completed-targeted-docx-reader-checks', $manifestFocusedReader['targetedHydratedCacheStatus'] ?? null);
        $t->same(27, $manifestFocusedReader['passedTargetedCaseCount'] ?? null);
        $t->same(0, $manifestFocusedReader['failedTargetedCaseCount'] ?? null);
        $t->same(0, $manifestFocusedReader['skippedTargetedCaseCount'] ?? null);
        $t->same(4, $manifestFocusedReader['mappedOnlyCaseCount'] ?? null);
        $t->same('valid-denominator-map', $manifestFocusedReader['mappingValidationStatus'] ?? null);
        $t->same('requires-31-covered-27-targeted-and-zero-targeted-failures', $manifestFocusedReader['gateStatus'] ?? null);
        $t->contains('31 covered, 5 remaining open', (string) ($manifestFocusedReader['claim'] ?? ''));
        $t->contains('27 passed / 0 failed / 4 mapped-only', (string) ($manifestFocusedReader['claim'] ?? ''));
        $t->contains('not an upstream Haskell/Cabal/Tasty runner result', (string) ($manifestFocusedReader['claim'] ?? ''));

        $localNativeFixtures = $countTrackedFiles(['lanes/pandoc/fixtures/upstream-native-docx-*.native']);
        $localCurrentNativeFixtures = $countTrackedFiles(['lanes/pandoc/fixtures/upstream-current-docx/*.native']);
        $localDocxPackageFixtures = $countTrackedFiles(['lanes/pandoc/**/*.docx', 'lanes/pandoc/*.docx']);
        $t->same($localNativeFixtures, $manifestAudit['localUpstreamNativeDocxFixtureFiles'] ?? null);
        $t->same(31, $manifestAudit['localUpstreamNativeDocxFixtureFiles'] ?? null);
        $t->same($localCurrentNativeFixtures, $manifestAudit['localCurrentUpstreamNativeFixtureFiles'] ?? null);
        $t->same(1, $manifestAudit['localCurrentUpstreamNativeFixtureFiles'] ?? null);
        $t->same(0, $manifestAudit['localPinnedDocxPackageFixtureFiles'] ?? null);
        $t->same(2, $manifestAudit['localCurrentUpstreamDocxPackageFixtureFiles'] ?? null);
        $t->same(2, $manifestAudit['localDocxPackageFixtureFiles'] ?? null);
        $t->same(2, $localDocxPackageFixtures);
        $t->same('parser-acceptance-only', $manifestAudit['parserAcceptanceEvidenceKind'] ?? null);
        $t->same($manifestAudit['parserAcceptanceEvidenceKind'] ?? null, $statusAudit['parserAcceptanceEvidenceKind'] ?? null);
        $t->same('local-upstream-docx-parser-acceptance-20260630', $manifestAudit['parserAcceptanceBaseline']['baselineName'] ?? null);
        $t->same(74, $manifestAudit['parserAcceptanceBaseline']['pairedDocxNativeArtifacts'] ?? null);
        $t->same(74, $manifestAudit['parserAcceptanceBaseline']['docxParsedCount'] ?? null);
        $t->same(74, $manifestAudit['parserAcceptanceBaseline']['nativeParsedCount'] ?? null);
        $t->same(74, $manifestAudit['parserAcceptanceBaseline']['bothParsedCount'] ?? null);
        $t->same($manifestAudit['parserAcceptanceBaseline'], $statusAudit['parserAcceptanceBaseline'] ?? null);
        $t->same(74, $manifestAudit['lastObservedParserAcceptance']['pairedDocxNativeArtifacts'] ?? null);
        $t->same(74, $manifestAudit['lastObservedParserAcceptance']['bothParsedCount'] ?? null);
        $t->same(0, $manifestAudit['lastObservedParserAcceptance']['bothFailedOrPartialCount'] ?? null);
        $t->same($manifestAudit['lastObservedParserAcceptance'], $statusAudit['lastObservedParserAcceptance'] ?? null);
        $t->contains('no AST equality', (string) ($manifestAudit['parserAcceptanceBaseline']['claim'] ?? ''));
        $t->same(
            (int) $manifestAudit['upstreamNativeExpectedArtifacts'] - (int) $manifestAudit['localExactNormalizedNativeFixtureMatches'],
            $manifestAudit['missingPinnedUpstreamNativeFixtures'] ?? null
        );
        $t->true((int) $manifestAudit['missingPinnedUpstreamNativeFixtures'] > 0, 'DOCX parity audit must not hide missing upstream native fixtures');
        $t->same(false, $manifestAudit['upstreamDocxRunnerExecuted'] ?? null);
        $t->same(true, $manifestAudit['upstreamDocxGoldenPackageRoundTripExecuted'] ?? null);
        $t->same('writer-golden-package-generated-stable-comparison', $manifestAudit['writerGoldenEvidenceKind'] ?? null);
        $t->same($manifestAudit['writerGoldenEvidenceKind'] ?? null, $statusAudit['writerGoldenEvidenceKind'] ?? null);
        $t->same('tools/pandoc-docx-writer-golden-audit.php --json --docx-dir .upstream-cache/pandoc-current/test/docx --generate-supported-dir .port-libs/pandoc-docx-writer-golden/status-truth --require-generated-stable-matches 38', $manifestAudit['writerGoldenPackageManifestTool'] ?? null);
        $t->same(38, $manifestAudit['writerGoldenPackageInventory']['goldenPackageCount'] ?? null);
        $t->same($manifestAudit['writerGoldenPackageInventory'], $statusAudit['writerGoldenPackageInventory'] ?? null);
        $t->same([
            'src/Text/Pandoc/Writers/Docx.hs',
            'test/Tests/Writers/Docx.hs',
            'test/docx/golden/*.docx',
            'data/default.docx',
        ], $manifestAudit['writerGoldenPackageInventory']['expectedUpstreamWriterSourceReferences'] ?? null);
        $t->same('PortLibs\\Pandoc\\DocxWriter', $manifestAudit['docxWriterImplementation']['expectedClass'] ?? null);
        $t->same('lanes/pandoc/src/DocxWriter.php', $manifestAudit['docxWriterImplementation']['expectedPath'] ?? null);
        $t->same(true, $manifestAudit['docxWriterImplementation']['classExists'] ?? null);
        $t->same(true, $manifestAudit['docxWriterImplementation']['fileExists'] ?? null);
        $t->same(true, is_file(dirname(__DIR__) . '/src/DocxWriter.php'));
        $t->same('partial', $manifestAudit['docxWriterImplementation']['outputRegistryStatus'] ?? null);
        $t->same($manifestAudit['docxWriterImplementation'], $statusAudit['docxWriterImplementation'] ?? null);
        $t->same(true, $manifestAudit['writerGoldenPackageGeneration']['run'] ?? null);
        $t->same('generated-all-writer-golden-cases', $manifestAudit['writerGoldenPackageGeneration']['status'] ?? null);
        $t->same('.upstream-cache/pandoc-current/test/docx', $manifestAudit['writerGoldenPackageGeneration']['sourceDirectory'] ?? null);
        $t->same(true, $manifestAudit['writerGoldenPackageGeneration']['sourceDirectoryPresent'] ?? null);
        $t->same(true, $manifestAudit['writerGoldenPackageGeneration']['outputDirectoryConfigured'] ?? null);
        $t->same('.port-libs/pandoc-docx-writer-golden/status-truth', $manifestAudit['writerGoldenPackageGeneration']['outputDirectory'] ?? null);
        $t->same(true, $manifestAudit['writerGoldenPackageGeneration']['outputDirectoryPresent'] ?? null);
        $t->same(38, $manifestAudit['writerGoldenPackageGeneration']['expectedGoldenCaseCount'] ?? null);
        $t->same(38, $manifestAudit['writerGoldenPackageGeneration']['attemptedCaseCount'] ?? null);
        $t->same(38, $manifestAudit['writerGoldenPackageGeneration']['generatedPackageCount'] ?? null);
        $t->same(0, $manifestAudit['writerGoldenPackageGeneration']['skippedCaseCount'] ?? null);
        $t->same(0, $manifestAudit['writerGoldenPackageGeneration']['failedCaseCount'] ?? null);
        $t->same(100, $manifestAudit['writerGoldenPackageGeneration']['generationCoveragePercent'] ?? null);
        $t->same([], $manifestAudit['writerGoldenPackageGeneration']['blockerCounts'] ?? null);
        $t->same('generated-all-writer-golden-cases', $manifestAudit['writerGoldenPackageGeneration']['openReason'] ?? null);
        $t->same($manifestAudit['writerGoldenPackageGeneration'], $statusAudit['writerGoldenPackageGeneration'] ?? null);
        $t->same(true, $manifestAudit['writerGoldenPackageComparison']['run'] ?? null);
        $t->same('matched-stable-package-semantics', $manifestAudit['writerGoldenPackageComparison']['status'] ?? null);
        $t->same(true, $manifestAudit['writerGoldenPackageComparison']['generatedDirectoryConfigured'] ?? null);
        $t->same('.port-libs/pandoc-docx-writer-golden/status-truth', $manifestAudit['writerGoldenPackageComparison']['generatedDirectory'] ?? null);
        $t->same(true, $manifestAudit['writerGoldenPackageComparison']['generatedDirectoryPresent'] ?? null);
        $t->same(38, $manifestAudit['writerGoldenPackageComparison']['expectedGoldenPackageCount'] ?? null);
        $t->same(38, $manifestAudit['writerGoldenPackageComparison']['generatedPackageCount'] ?? null);
        $t->same(38, $manifestAudit['writerGoldenPackageComparison']['comparedPackageCount'] ?? null);
        $t->same(38, $manifestAudit['writerGoldenPackageComparison']['matchedPackageCount'] ?? null);
        $t->same(0, $manifestAudit['writerGoldenPackageComparison']['mismatchedPackageCount'] ?? null);
        $t->same(0, $manifestAudit['writerGoldenPackageComparison']['missingGeneratedPackageCount'] ?? null);
        $t->same(0, $manifestAudit['writerGoldenPackageComparison']['unexpectedGeneratedPackageCount'] ?? null);
        $t->same(100, $manifestAudit['writerGoldenPackageComparison']['comparisonCoveragePercent'] ?? null);
        $t->same(100, $manifestAudit['writerGoldenPackageComparison']['stableMatchPercent'] ?? null);
        $t->same(true, $manifestAudit['writerGoldenPackageComparison']['allStableSemanticsMatch'] ?? null);
        $t->same($manifestAudit['writerGoldenPackageInventory']['goldenPackageCount'] ?? null, $manifestAudit['writerGoldenPackageGeneration']['expectedGoldenCaseCount'] ?? null);
        $t->same($manifestAudit['writerGoldenPackageInventory']['goldenPackageCount'] ?? null, $manifestAudit['writerGoldenPackageGeneration']['generatedPackageCount'] ?? null);
        $t->same($manifestAudit['writerGoldenPackageInventory']['goldenPackageCount'] ?? null, $manifestAudit['writerGoldenPackageComparison']['expectedGoldenPackageCount'] ?? null);
        $t->same($manifestAudit['writerGoldenPackageInventory']['goldenPackageCount'] ?? null, $manifestAudit['writerGoldenPackageComparison']['generatedPackageCount'] ?? null);
        $t->same($manifestAudit['writerGoldenPackageInventory']['goldenPackageCount'] ?? null, $manifestAudit['writerGoldenPackageComparison']['comparedPackageCount'] ?? null);
        $t->same($manifestAudit['writerGoldenPackageInventory']['goldenPackageCount'] ?? null, $manifestAudit['writerGoldenPackageComparison']['matchedPackageCount'] ?? null);
        $t->same($haskellInventory['localGates']['writerGoldenPackageGate']['expectedGoldenCaseCount'] ?? null, $manifestAudit['writerGoldenPackageInventory']['goldenPackageCount'] ?? null);
        $t->same($haskellInventory['localGates']['writerGoldenPackageGate']['generatedPackageCount'] ?? null, $manifestAudit['writerGoldenPackageGeneration']['generatedPackageCount'] ?? null);
        $t->same($haskellInventory['localGates']['writerGoldenPackageGate']['comparedPackageCount'] ?? null, $manifestAudit['writerGoldenPackageComparison']['comparedPackageCount'] ?? null);
        $t->same($haskellInventory['localGates']['writerGoldenPackageGate']['matchedPackageCount'] ?? null, $manifestAudit['writerGoldenPackageComparison']['matchedPackageCount'] ?? null);
        $stableContract = $manifestAudit['writerGoldenPackageComparison']['stableComparisonContract'] ?? null;
        $t->true(is_array($stableContract), 'writer golden comparison must record stable package semantics');
        $t->contains('non-directory OPC package part-name set', implode("\n", $stableContract['compares'] ?? []));
        $t->contains('raw ZIP package byte equality', implode("\n", $stableContract['ignores'] ?? []));
        $diagnostics = $manifestAudit['writerGoldenPackageComparison']['mismatchDiagnostics'] ?? null;
        $t->true(is_array($diagnostics), 'writer golden comparison must record aggregate mismatch diagnostics');
        $t->same(0, $diagnostics['stableMismatchPackageCount'] ?? null);
        $t->same([], $diagnostics['mismatchKindCounts'] ?? null);
        $t->same(0, $diagnostics['partNameDeltas']['packagesWithMissingParts'] ?? null);
        $t->same(0, $diagnostics['partNameDeltas']['packagesWithExtraParts'] ?? null);
        $t->same([], $diagnostics['partNameDeltas']['missingPartNameCounts'] ?? null);
        $t->same([], $diagnostics['partNameDeltas']['extraPartNameCounts'] ?? null);
        $t->same(0, $diagnostics['contentTypeDeltas']['packagesWithMissingRecords'] ?? null);
        $t->same(0, $diagnostics['contentTypeDeltas']['packagesWithExtraRecords'] ?? null);
        $t->same([], $diagnostics['contentTypeDeltas']['missingRecordCounts'] ?? null);
        $t->same([], $diagnostics['contentTypeDeltas']['extraRecordCounts'] ?? null);
        $t->same(0, $diagnostics['relationshipDeltas']['packagesWithMissingRecords'] ?? null);
        $t->same(0, $diagnostics['relationshipDeltas']['packagesWithExtraRecords'] ?? null);
        $t->same([], $diagnostics['relationshipDeltas']['missingRecordCounts'] ?? null);
        $t->same([], $diagnostics['relationshipDeltas']['extraRecordCounts'] ?? null);
        $t->same(0, $diagnostics['xmlPartDeltas']['packagesWithChangedXmlParts'] ?? null);
        $t->same([], $diagnostics['xmlPartDeltas']['changedXmlPartCounts'] ?? null);
        $t->same($diagnostics, $statusAudit['writerGoldenPackageComparison']['mismatchDiagnostics'] ?? null);
        $t->same('all-generated-docx-packages-match-upstream-golden-stable-semantics', $manifestAudit['writerGoldenPackageComparison']['openReason'] ?? null);
        $t->same($manifestAudit['writerGoldenPackageComparison'], $statusAudit['writerGoldenPackageComparison'] ?? null);
        $focusedCi = $manifestAudit['focusedCiEvidenceWiring'] ?? null;
        $statusFocusedCi = $statusAudit['focusedCiEvidenceWiring'] ?? null;
        $t->true(is_array($focusedCi), 'DOCX parity evidence must record focused CI wiring');
        $t->same($focusedCi, $statusFocusedCi);
        $t->same('focused-ci-evidence-wired-generated-writer-golden-38-of-38-haskell-inventory-and-focused-reader-gated', $focusedCi['status'] ?? null);
        $t->same('php tools/run-tests.php lanes/pandoc/tests/DocxWriterTest.php', $focusedCi['commands']['writerCoreTest'] ?? null);
        $t->same('php tools/run-tests.php lanes/pandoc/tests/DocxUpstreamHaskellInventoryTest.php', $focusedCi['commands']['haskellInventoryStatusTest'] ?? null);
        $t->same('php tools/pandoc-docx-writer-golden-audit.php --json --generate-supported-dir .port-libs/pandoc-docx-writer-golden/generated --require-generated-stable-matches 38', $focusedCi['commands']['writerGoldenAudit'] ?? null);
        $t->same('passed-1-file-48-assertions-0-failures', $focusedCi['localValidation']['writerCoreTestStatus'] ?? null);
        $t->same('skipped_missing_writer_golden_directory', $focusedCi['localValidation']['writerGoldenAuditStatus'] ?? null);
        $t->same('not-run-golden-directory-missing', $focusedCi['localValidation']['writerGoldenComparisonStatus'] ?? null);
        $t->same('writer-golden-package-generated-stable-comparison', $focusedCi['localValidation']['recordedWriterGoldenEvidenceKind'] ?? null);
        $t->same(38, $focusedCi['localValidation']['recordedWriterGoldenGeneratedPackageCount'] ?? null);
        $t->same(38, $focusedCi['localValidation']['recordedWriterGoldenComparedPackageCount'] ?? null);
        $t->same(38, $focusedCi['localValidation']['recordedWriterGoldenMatchedPackageCount'] ?? null);
        $t->same(0, $focusedCi['localValidation']['recordedWriterGoldenMismatchedPackageCount'] ?? null);
        $t->same('requires-38-generated-stable-matches', $focusedCi['ciHydratedWriterGoldenGateStatus'] ?? null);
        $t->contains('DocxWriterTest.php', (string) ($focusedCi['claim'] ?? ''));
        $t->contains('DocxUpstreamHaskellInventoryTest.php', (string) ($focusedCi['claim'] ?? ''));
        $t->contains('36 reader uncovered cases and 7 writer direct HUnit uncovered cases', (string) ($focusedCi['claim'] ?? ''));
        $t->contains('38 generated / 38 compared / 38 matched / 0 mismatched', (string) ($focusedCi['claim'] ?? ''));
        $t->contains('not an upstream Haskell/Cabal/Tasty runner result', (string) ($focusedCi['claim'] ?? ''));
        $t->contains('php -l lanes/pandoc/src/DocxWriter.php', $workflow);
        $t->contains('php -l lanes/pandoc/tests/DocxWriterTest.php', $workflow);
        $t->contains('php -l lanes/pandoc/tests/DocxUpstreamHaskellInventoryTest.php', $workflow);
        $t->contains('uses: actions/cache@v4', $workflow);
        $t->contains('key: pandoc-docx-upstream-sparse-v3-${{ runner.os }}-612e143fbe6d735b612c4800d21e61b7d44e4dca', $workflow);
        $t->contains('git -C "$target" sparse-checkout set \\', $workflow);
        $t->contains('/cabal.project \\', $workflow);
        $t->contains('/pandoc.cabal \\', $workflow);
        $t->contains('/test/test-pandoc.hs \\', $workflow);
        $t->contains('/test/lalune.jpg \\', $workflow);
        $t->contains('/test/Tests/Readers/Docx.hs \\', $workflow);
        $t->contains('/test/Tests/Writers/Docx.hs \\', $workflow);
        $t->contains('/test/docx \\', $workflow);
        $t->contains('/data/docx', $workflow);
        $t->true(!str_contains($workflow, 'data/default.docx'), 'DOCX CI sparse cache must use the current data/docx template directory, not legacy data/default.docx');
        $t->contains('git -C "$target" fetch --depth=1 --filter=blob:none origin 612e143fbe6d735b612c4800d21e61b7d44e4dca', $workflow);
        $t->contains('test "$(git -C "$target" rev-parse HEAD)" = "612e143fbe6d735b612c4800d21e61b7d44e4dca"', $workflow);
        $t->contains('test -f "$target/test/lalune.jpg"', $workflow);
        $t->contains('test -d "$target/test/docx"', $workflow);
        $t->contains('test -d "$target/data/docx"', $workflow);
        $t->contains('test -f "$target/data/docx/[Content_Types].xml"', $workflow);
        $t->contains('test -f "$target/data/docx/word/document.xml"', $workflow);
        $t->contains('--generate-supported-dir .port-libs/pandoc-docx-writer-golden/generated', $workflow);
        $t->contains('--require-generated-stable-matches 38', $workflow);
        $t->contains('lanes/pandoc/UPSTREAM_DOCX_HASKELL_INVENTORY.json', $workflow);
        $t->contains('php tools/pandoc-docx-native-ast.php summary --require-mapped-parity=74', $workflow);
        $t->true(!str_contains($workflow, 'pandoc-docx-native-ast.php --limit=12'), 'DOCX CI must not gate native AST parity with a capped sample');
        $t->true(!str_contains($workflow, '.port-libs/pandoc-docx-writer-golden/generated/*.docx'), 'DOCX CI must not upload generated writer-golden DOCX packages');
        $t->contains('lanes/pandoc/tests/DocxWriterTest.php', $workflow);
        $t->contains('lanes/pandoc/tests/DocxUpstreamHaskellInventoryTest.php', $workflow);

        $t->same('reported_optional_upstream_docx_cache_manifest', $cacheManifest['status'] ?? null);
        $t->same(false, $cacheManifest['skipped'] ?? null);
        $t->same('artifact-identity-manifest-only', $cacheManifest['evidenceKind'] ?? null);
        $t->same('612e143fbe6d735b612c4800d21e61b7d44e4dca', $cacheManifest['upstream']['commit'] ?? null);
        $t->same(true, $cacheManifest['upstream']['commitMatchesExpected'] ?? null);
        $t->same(true, $cacheManifest['source']['workingTreeCleanForTestDocx'] ?? null);
        $t->same(236, $cacheManifest['artifactCounts']['totalDocxNativeGoldenArtifacts'] ?? null);
        $t->same(85, $cacheManifest['artifactCounts']['rootDocxPackageArtifacts'] ?? null);
        $t->same(113, $cacheManifest['artifactCounts']['rootNativeExpectedArtifacts'] ?? null);
        $t->same(38, $cacheManifest['artifactCounts']['goldenDocxPackageArtifacts'] ?? null);
        $t->same(123, $cacheManifest['artifactCounts']['totalDocxPackageArtifacts'] ?? null);
        $t->same(74, $cacheManifest['artifactCounts']['pairedRootDocxNativeStems'] ?? null);
        $t->same(11, $cacheManifest['artifactCounts']['unpairedRootDocxPackageStems'] ?? null);
        $t->same(39, $cacheManifest['artifactCounts']['unpairedRootNativeExpectedStems'] ?? null);
        $t->same(236, count($cacheManifest['artifactRows'] ?? []));
        $t->same(85, count($cacheManifest['rootDocxPackageStems'] ?? []));
        $t->same(113, count($cacheManifest['rootNativeExpectedStems'] ?? []));
        $t->same(38, count($cacheManifest['goldenDocxPackageStems'] ?? []));
        $t->same('test/docx/0_level_headers.docx', $cacheManifest['artifactRows'][0]['path'] ?? null);
        $t->same('0d99c52804c856788c773639a36f695e908bff14b07e4b242e377f6131f8941e', $cacheManifest['artifactRows'][0]['sha256'] ?? null);
        $t->contains('checked-in DOCX package bytes', implode(',', $cacheManifest['claimBoundaries']['doesNotAssert'] ?? []));
        $t->contains('pinned upstream DOCX package corpus availability in every worktree or CI job', implode(',', $cacheManifest['claimBoundaries']['doesNotAssert'] ?? []));
        $t->true(filesize($repoRoot . '/lanes/pandoc/UPSTREAM_DOCX_CACHE_MANIFEST.json') < 100000, 'DOCX cache manifest must stay metadata-sized');
        $t->same('lanes/pandoc/UPSTREAM_DOCX_CACHE_MANIFEST.json', $optionalCacheManifest['reportPath'] ?? null);
        $t->same('tools/pandoc-docx-cache-manifest.php', $optionalCacheManifest['tool'] ?? null);
        $t->same($cacheManifest['evidenceKind'], $optionalCacheManifest['evidenceKind'] ?? null);
        $t->same($cacheManifest['upstream']['commit'], $optionalCacheManifest['upstreamCommit'] ?? null);
        $t->same($cacheManifest['source']['cachePath'], $optionalCacheManifest['cachePath'] ?? null);
        $t->same($cacheManifest['artifactSetSha256'], $optionalCacheManifest['artifactSetSha256'] ?? null);
        $t->same($cacheManifest['artifactCounts']['totalDocxNativeGoldenArtifacts'], $optionalCacheManifest['totalDocxNativeGoldenArtifacts'] ?? null);
        $t->same($cacheManifest['artifactCounts']['rootDocxPackageArtifacts'], $optionalCacheManifest['rootDocxPackageArtifacts'] ?? null);
        $t->same($cacheManifest['artifactCounts']['rootNativeExpectedArtifacts'], $optionalCacheManifest['rootNativeExpectedArtifacts'] ?? null);
        $t->same($cacheManifest['artifactCounts']['goldenDocxPackageArtifacts'], $optionalCacheManifest['goldenDocxPackageArtifacts'] ?? null);
        $t->same($cacheManifest['artifactCounts']['pairedRootDocxNativeStems'], $optionalCacheManifest['pairedRootDocxNativeStems'] ?? null);
        $t->contains('without checking in DOCX package bytes', (string) ($optionalCacheManifest['claim'] ?? ''));

        $runnerPlan = $manifestAudit['upstreamDocxRunnerEvidencePlan'] ?? null;
        $statusRunnerPlan = $statusAudit['upstreamDocxRunnerEvidencePlan'] ?? null;
        $t->true(is_array($runnerPlan), 'UPSTREAM_TEST_MANIFEST.json must carry a DOCX runner evidence plan');
        $t->same($runnerPlan, $statusRunnerPlan);
        $t->same('open-no-targeted-runner-result', $runnerPlan['status'] ?? null);
        $t->same('runner-entry-fixture-command-plan-only', $runnerPlan['evidenceKind'] ?? null);
        $t->same('tools/pandoc-docx-upstream-runner-plan.php --json --upstream-root .upstream-cache/pandoc-current', $runnerPlan['preflightPlanTool'] ?? null);
        $t->same('targeted-docx-runner-preflight-plan-only', $runnerPlan['preflightEvidenceKind'] ?? null);
        $readinessGate = $runnerPlan['localReadinessGate'] ?? [];
        $t->true(is_array($readinessGate), 'DOCX runner plan must record the local readiness gate');
        $t->same('targeted-docx-runner-local-readiness-gate-only', $readinessGate['evidenceKind'] ?? null);
        $t->same('ready-for-targeted-docx-runner-execution', $readinessGate['readyStatus'] ?? null);
        $t->same('blocked-targeted-docx-runner-local-prerequisites', $readinessGate['blockedStatus'] ?? null);
        $t->same([
            'missing-docx-upstream-source',
            'unverified-pinned-upstream-commit',
            'missing-cabal-executable',
            'missing-ghc-executable',
            'insufficient-disk-for-targeted-runner-workspace',
        ], $readinessGate['machineReadableBlockerCodes'] ?? null);
        $t->contains('source/tool/disk readiness only', (string) ($readinessGate['executionPolicy'] ?? ''));
        $selectedInventoryArtifact = $runnerPlan['selectedTestInventoryArtifact'] ?? [];
        $t->true(is_array($selectedInventoryArtifact), 'DOCX runner plan must name the selected test inventory artifact');
        $t->same('tools/pandoc-docx-upstream-runner-plan.php --json --upstream-root .upstream-cache/pandoc-current --write-selected-inventory .port-libs/pandoc-runner/artifacts/docx-targeted-run/selected-test-inventory.json', $selectedInventoryArtifact['command'] ?? null);
        $t->same('.port-libs/pandoc-runner/artifacts/docx-targeted-run/selected-test-inventory.json', $selectedInventoryArtifact['path'] ?? null);
        $t->same('static-docx-selected-test-inventory-only', $selectedInventoryArtifact['evidenceKind'] ?? null);
        $t->same('reported-static-docx-selected-test-inventory', $selectedInventoryArtifact['statusWhenHydrated'] ?? null);
        $t->contains('static source/fixture inventory', (string) ($selectedInventoryArtifact['executionPolicy'] ?? ''));
        $t->contains('no Cabal command', (string) ($selectedInventoryArtifact['executionPolicy'] ?? ''));
        $resultArtifactGate = $runnerPlan['resultArtifactGate'] ?? [];
        $t->true(is_array($resultArtifactGate), 'DOCX runner plan must name the result artifact gate');
        $t->same('tools/pandoc-docx-upstream-runner-plan.php --json --upstream-root .upstream-cache/pandoc-current --validate-result-artifacts --artifact-root .port-libs/pandoc-runner/artifacts/docx-targeted-run --log-root .port-libs/pandoc-runner/logs', $resultArtifactGate['command'] ?? null);
        $t->same('targeted-docx-runner-result-artifact-gate-only', $resultArtifactGate['evidenceKind'] ?? null);
        $t->same('admissible-targeted-runner-result-artifacts-no-parity-claim', $resultArtifactGate['admissibleStatus'] ?? null);
        $t->contains('result.json SHA-256 fields bound', implode("\n", is_array($resultArtifactGate['checks'] ?? null) ? $resultArtifactGate['checks'] : []));
        $t->contains('transcripts include exact Cabal command lines', implode("\n", is_array($resultArtifactGate['checks'] ?? null) ? $resultArtifactGate['checks'] : []));
        $t->contains('hard evidence gap', implode("\n", is_array($resultArtifactGate['checks'] ?? null) ? $resultArtifactGate['checks'] : []));
        $t->contains('does not execute Cabal/Tasty', (string) ($resultArtifactGate['executionPolicy'] ?? ''));
        $t->contains('transcript-evidence', (string) ($resultArtifactGate['executionPolicy'] ?? ''));
        $manualWorkflow = $runnerPlan['manualWorkflow'] ?? [];
        $t->true(is_array($manualWorkflow), 'DOCX runner plan must name the manual workflow execution path');
        $t->same('.github/workflows/pandoc-docx.yml', $manualWorkflow['workflow'] ?? null);
        $t->same('workflow_dispatch', $manualWorkflow['trigger'] ?? null);
        $t->same('run_upstream_docx_tasty=true', $manualWorkflow['input'] ?? null);
        $t->same('.upstream-cache/pandoc-docx-runner-pinned', $manualWorkflow['upstreamRoot'] ?? null);
        $t->same('0640c4c9859aa5a3ede082c190fcd5883c24ac83', $manualWorkflow['upstreamCommit'] ?? null);
        $t->same('test:test-pandoc', $manualWorkflow['runnerTarget'] ?? null);
        $t->same('($2 == "Readers" || $2 == "Writers") && $3 == "Docx"', $manualWorkflow['tastyPattern'] ?? null);
        $t->contains('--write-result-artifact .port-libs/pandoc-runner/artifacts/docx-targeted-run/result.json', (string) ($manualWorkflow['resultWriterCommand'] ?? ''));
        $t->contains('admissible-targeted-runner-result-artifacts-no-parity-claim', (string) ($manualWorkflow['gatePolicy'] ?? ''));
        $t->contains('run_upstream_docx_tasty:', $workflow);
        $t->contains('uses: haskell-actions/setup@v2', $workflow);
        $t->contains('PANDOC_DOCX_RUNNER_UPSTREAM_ROOT: .upstream-cache/pandoc-docx-runner-pinned', $workflow);
        $t->contains('Run targeted upstream DOCX Cabal/Tasty pattern', $workflow);
        $t->contains('--write-result-artifact "$artifact_root/result.json"', $workflow);
        $t->contains('Require targeted upstream DOCX runner artifact gate', $workflow);
        $t->same(false, $runnerPlan['resultRecorded'] ?? null);
        $t->same(false, $runnerPlan['runnerExecuted'] ?? null);
        $t->same('test:test-pandoc', $runnerPlan['runnerTarget'] ?? null);
        $t->same('test/test-pandoc.hs', $runnerPlan['runnerEntryPoint']['entryFile'] ?? null);
        $t->same('test/Tests/Readers/Docx.hs', $runnerPlan['docxReaderEntryPoint']['sourceFile'] ?? null);
        $t->same('Tests.Readers.Docx.tests', $runnerPlan['docxReaderEntryPoint']['entryPointSnippet'] ?? null);
        $t->same('test/Tests/Writers/Docx.hs', $runnerPlan['docxWriterEntryPoint']['sourceFile'] ?? null);
        $t->same('Tests.Writers.Docx.tests', $runnerPlan['docxWriterEntryPoint']['entryPointSnippet'] ?? null);
        $t->same(['test/docx/*.docx', 'test/docx/*.native'], $runnerPlan['fixtureClosure']['readerFixtureGlobs'] ?? null);
        $t->same(['test/docx/golden/*.docx'], $runnerPlan['fixtureClosure']['writerGoldenFixtureGlobs'] ?? null);
        $t->same(233, $runnerPlan['fixtureClosure']['pinnedInventoryCounts']['docxDirectoryArtifacts'] ?? null);
        $t->same(112, $runnerPlan['fixtureClosure']['pinnedInventoryCounts']['nativeExpectedArtifacts'] ?? null);
        $t->same(121, $runnerPlan['fixtureClosure']['pinnedInventoryCounts']['docxPackageArtifacts'] ?? null);
        $t->same(38, $runnerPlan['fixtureClosure']['pinnedInventoryCounts']['goldenDocxArtifacts'] ?? null);
        $t->contains('cabal v2-build --offline --project-dir=.', (string) ($runnerPlan['nonMutatingDryRunPlanCommand']['commandLine'] ?? ''));
        $t->contains('--dry-run', (string) ($runnerPlan['nonMutatingDryRunPlanCommand']['commandLine'] ?? ''));
        $t->contains('test:test-pandoc test:test-pandoc-lua-engine', (string) ($runnerPlan['nonMutatingDryRunPlanCommand']['commandLine'] ?? ''));
        $t->same('descriptor-only; do not execute from this isolated PHP lane', $runnerPlan['nonMutatingDryRunPlanCommand']['executionPolicy'] ?? null);
        $t->contains('--list-tests --pattern', (string) ($runnerPlan['futureListTestsCommand']['commandLine'] ?? ''));
        $t->same('($2 == "Readers" || $2 == "Writers") && $3 == "Docx"', $runnerPlan['futureListTestsCommand']['arguments'][8] ?? null);
        $t->same('($2 == "Readers" || $2 == "Writers") && $3 == "Docx"', $runnerPlan['futureTargetedRunCommand']['arguments'][7] ?? null);
        $t->contains('.port-libs/pandoc-runner/artifacts/docx-targeted-run/selected-test-inventory.json', implode(',', $runnerPlan['resultArtifactContract']['requiredBeforeResultRecorded'] ?? []));
        $t->contains('.port-libs/pandoc-runner/artifacts/docx-targeted-run/result.json', implode(',', $runnerPlan['resultArtifactContract']['requiredBeforeResultRecorded'] ?? []));
        $t->contains('runnerExecuted', implode(',', $runnerPlan['resultArtifactContract']['resultJsonRequiredFields'] ?? []));
        $t->contains('exitCode', implode(',', $runnerPlan['resultArtifactContract']['resultJsonRequiredFields'] ?? []));
        $t->contains('selectedTestInventorySha256', implode(',', $runnerPlan['resultArtifactContract']['resultJsonRequiredFields'] ?? []));
        $t->contains('targetedRunTranscriptSha256', implode(',', $runnerPlan['resultArtifactContract']['resultJsonRequiredFields'] ?? []));
        $t->contains('DOCX Tasty result output', (string) ($runnerPlan['resultArtifactContract']['transcriptEvidenceRequirements']['targetedRunTranscript'] ?? ''));
        $t->contains('concrete Tasty DOCX list/run output', (string) ($runnerPlan['resultArtifactContract']['admissionRule'] ?? ''));
        $t->contains('not executed by this audit', (string) ($runnerPlan['futureTargetedRunCommand']['executionPolicy'] ?? ''));
        $t->contains('not an upstream DOCX runner result', (string) ($runnerPlan['honestClaim'] ?? ''));

        $t->contains('Full DOCX/OpenXML parity is not defensible', (string) ($status['blocker'] ?? ''));
        $t->contains('core DocxWriter', (string) ($status['blocker'] ?? ''));
        $t->contains('generated writer-golden stable package comparison is gated at 38/38 matches', (string) ($status['blocker'] ?? ''));
        $t->contains('upstream DOCX Cabal/Tasty runner parity is missing', (string) ($status['blocker'] ?? ''));
        $t->contains('full upstream DOCX parity is not defensible', $pandocStatus);
        $t->contains('DOCX runner evidence plan records `test:test-pandoc`', $pandocStatus);
        $t->contains('--write-selected-inventory .port-libs/pandoc-runner/artifacts/docx-targeted-run/selected-test-inventory.json', $pandocStatus);
        $t->contains('--write-result-artifact .port-libs/pandoc-runner/artifacts/docx-targeted-run/result.json', $pandocStatus);
        $t->contains('--validate-result-artifacts --artifact-root .port-libs/pandoc-runner/artifacts/docx-targeted-run --log-root .port-libs/pandoc-runner/logs', $pandocStatus);
        $t->contains('run_upstream_docx_tasty=true', $pandocStatus);
        $t->contains('Missing or placeholder targeted runner transcripts are a hard evidence gap, not pass evidence', $pandocStatus);
        $t->contains('With `--generate-supported-dir`, it uses the bounded `PortLibs\\Pandoc\\DocxWriter`', $pandocStatus);
        $t->contains('current generated stable package comparison coverage is 38/38 generated, 38/38 compared, 38/38 matched', $pandocStatus);
        $t->contains('Focused DOCX parity CI now validates `UPSTREAM_DOCX_HASKELL_INVENTORY.json`', $pandocStatus);
        $t->contains('runs `DocxUpstreamHaskellInventoryTest.php` so the 36 reader uncovered cases and 7 writer direct HUnit uncovered cases cannot be silently dropped', $pandocStatus);
        $t->contains('runs the bounded `DocxWriterTest.php` writer-core package test', $pandocStatus);
        $t->contains('CI invokes the writer-golden audit with `--generate-supported-dir --require-generated-stable-matches 38`', $pandocStatus);
        $t->contains('no upstream Haskell/Cabal DOCX runner result, Tasty `--list-tests` output', $pandocStatus);
        $t->contains('2 checked-in current-upstream `.docx` package fixtures', $pandocStatus);
        $t->contains('0 checked-in pinned upstream `.docx` package fixtures', $pandocStatus);
        $t->contains('core `PortLibs\\Pandoc\\DocxWriter` implementation registered as partial output support', $pandocStatus);
        $t->contains('matched all 38 by stable package semantics', $pandocStatus);
        $t->contains('UPSTREAM_DOCX_HASKELL_INVENTORY.json', $pandocStatus);
        $staleZeroMatch = '0' . '/38 matched';
        $staleMismatchCount = '38 ' . 'mismatched';
        $t->true(!str_contains($pandocStatus, $staleZeroMatch), 'PANDOC_STATUS.md must not retain stale writer-golden zero-match evidence');
        $t->true(!str_contains($pandocStatus, $staleMismatchCount), 'PANDOC_STATUS.md must not retain stale writer-golden mismatch evidence');
        $t->contains('UPSTREAM_DOCX_CACHE_MANIFEST.json', $pandocStatus);
        $t->contains('checked-in pinned DOCX package corpus gap remains open', $pandocStatus);
        $t->contains('three current-upstream DOCX drift fixtures', (string) ($manifestAudit['defensibleClaim'] ?? ''));
    },
];
