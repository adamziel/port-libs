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
        $pandocStatus = $readText('PANDOC_STATUS.md');

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
        $t->same(false, $manifestAudit['upstreamDocxGoldenPackageRoundTripExecuted'] ?? null);
        $t->same('writer-golden-package-inventory-only', $manifestAudit['writerGoldenEvidenceKind'] ?? null);
        $t->same($manifestAudit['writerGoldenEvidenceKind'] ?? null, $statusAudit['writerGoldenEvidenceKind'] ?? null);
        $t->same('tools/pandoc-docx-writer-golden-audit.php --json', $manifestAudit['writerGoldenPackageManifestTool'] ?? null);
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
        $t->same(false, $manifestAudit['docxWriterImplementation']['classExists'] ?? null);
        $t->same(false, $manifestAudit['docxWriterImplementation']['fileExists'] ?? null);
        $t->same(false, is_file(dirname(__DIR__) . '/src/DocxWriter.php'));
        $t->same('unsupported', $manifestAudit['docxWriterImplementation']['outputRegistryStatus'] ?? null);
        $t->same($manifestAudit['docxWriterImplementation'], $statusAudit['docxWriterImplementation'] ?? null);
        $t->same(false, $manifestAudit['writerGoldenPackageComparison']['run'] ?? null);
        $t->same(0, $manifestAudit['writerGoldenPackageComparison']['generatedPackageCount'] ?? null);
        $t->same(0, $manifestAudit['writerGoldenPackageComparison']['comparedPackageCount'] ?? null);
        $t->same('writer-unsupported-no-DocxWriter-implementation-and-docx-output-registry-unsupported', $manifestAudit['writerGoldenPackageComparison']['openReason'] ?? null);
        $t->same($manifestAudit['writerGoldenPackageComparison'], $statusAudit['writerGoldenPackageComparison'] ?? null);

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
        $t->same('($2 == "Readers" || $2 == "Writers") && $3 == "Docx"', $runnerPlan['futureTargetedRunCommand']['arguments'][7] ?? null);
        $t->contains('not executed by this audit', (string) ($runnerPlan['futureTargetedRunCommand']['executionPolicy'] ?? ''));
        $t->contains('not an upstream DOCX runner result', (string) ($runnerPlan['honestClaim'] ?? ''));

        $t->contains('Full DOCX/OpenXML parity is not defensible', (string) ($status['blocker'] ?? ''));
        $t->contains('no local DocxWriter implementation', (string) ($status['blocker'] ?? ''));
        $t->contains('full upstream DOCX parity is not defensible', $pandocStatus);
        $t->contains('DOCX runner evidence plan records `test:test-pandoc`', $pandocStatus);
        $t->contains('2 checked-in current-upstream `.docx` package fixtures', $pandocStatus);
        $t->contains('0 checked-in pinned upstream `.docx` package fixtures', $pandocStatus);
        $t->contains('UPSTREAM_DOCX_CACHE_MANIFEST.json', $pandocStatus);
        $t->contains('checked-in pinned DOCX package corpus gap remains open', $pandocStatus);
        $t->contains('three current-upstream DOCX drift fixtures', (string) ($manifestAudit['defensibleClaim'] ?? ''));
    },
];
