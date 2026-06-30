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
    'keeps DOCX parity evidence bounded to defensible coverage' => static function (TestRunner $t) use ($readJson, $readText, $countTrackedFiles): void {
        $manifest = $readJson('lanes/pandoc/UPSTREAM_TEST_MANIFEST.json');
        $status = $readJson('lanes/pandoc/lane-status.json');
        $pandocStatus = $readText('PANDOC_STATUS.md');

        $manifestAudit = $manifest['docxParityAudit'] ?? null;
        $statusAudit = $status['docxParityAudit'] ?? null;
        $t->true(is_array($manifestAudit), 'UPSTREAM_TEST_MANIFEST.json must carry DOCX parity audit evidence');
        $t->true(is_array($statusAudit), 'lane-status.json must carry DOCX parity audit evidence');

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

        $t->contains('Full DOCX/OpenXML parity is not defensible', (string) ($status['blocker'] ?? ''));
        $t->contains('no local DocxWriter implementation', (string) ($status['blocker'] ?? ''));
        $t->contains('full upstream DOCX parity is not defensible', $pandocStatus);
        $t->contains('2 checked-in current-upstream `.docx` package fixtures', $pandocStatus);
        $t->contains('0 checked-in pinned upstream `.docx` package fixtures', $pandocStatus);
        $t->contains('three current-upstream DOCX drift fixtures', (string) ($manifestAudit['defensibleClaim'] ?? ''));
    },
];
