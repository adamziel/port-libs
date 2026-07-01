<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__, 3);

$readInventory = static function () use ($repoRoot): array {
    $path = $repoRoot . '/lanes/pandoc/UPSTREAM_DOCX_HASKELL_INVENTORY.json';
    $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Unable to decode DOCX Haskell inventory manifest');
    }

    return $decoded;
};

$labels = static function (array $rows): array {
    return array_values(array_map(
        static fn (array $row): string => (string) ($row['label'] ?? ''),
        $rows
    ));
};

return [
    'maps pinned upstream docx haskell cases to local reader and writer gates' => static function (TestRunner $t) use ($readInventory, $labels): void {
        $inventory = $readInventory();

        $t->same(1, $inventory['schemaVersion']);
        $t->same('reported-upstream-docx-haskell-inventory-local-gate-map', $inventory['status']);
        $t->same('612e143fbe6d735b612c4800d21e61b7d44e4dca', $inventory['upstream']['commit']);
        $t->same('static-upstream-haskell-docx-inventory-to-local-gate-map', $inventory['evidenceKind']);
        $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $inventory['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('full DOCX/OpenXML parity', $inventory['claimBoundaries']['doesNotAssert'], true));

        $readerGate = $inventory['localGates']['readerNativeParserAcceptance'];
        $t->same('parser-acceptance-only', $readerGate['evidenceKind']);
        $t->same(74, $readerGate['pairedRootDocxNativeArtifacts']);
        $t->same(74, $readerGate['docxParsedCount']);
        $t->same(74, $readerGate['nativeParsedCount']);
        $t->same(74, $readerGate['bothParsedCount']);
        $t->same(0, $readerGate['bothFailedOrPartialCount']);
        $t->true(in_array('Pandoc Haskell readDocx/readNative AST equality', $readerGate['doesNotProve'], true));
        $t->true(in_array('MediaBag extraction equality', $readerGate['doesNotProve'], true));

        $reader = $inventory['haskellReaderInventory'];
        $readerRows = $reader['notCoveredCases'];
        $t->same('test/Tests/Readers/Docx.hs', $reader['sourceFile']);
        $t->same('Tests.Readers.Docx.tests', $reader['entryPoint']);
        $t->same(103, $reader['totalStaticCases']);
        $t->same(95, $reader['compareCases']);
        $t->same(4, $reader['warningCases']);
        $t->same(4, $reader['mediaBagCases']);
        $t->same(72, $reader['fixturePairCasesReferencedByLocal74Gate']);
        $t->same(67, $reader['strictDefaultSameStemCasesCoveredByLocal74Gate']);
        $t->same(5, $reader['nonDefaultSameStemCasesNotSemanticallyCovered']);
        $t->same(36, $reader['casesNotCoveredByLocal74GateSemantics']);
        $t->same(36, count($readerRows));
        $t->same([
            'comments',
            'compact-style-removal',
            'cross_reference',
            'diagram',
            'metadata',
            'metadata_after_normal',
            'track_changes_scrubbed_metadata',
        ], $reader['local74GateExtraPairedStemsWithoutStrictDefaultHaskellReaderCase']);
        $readerLabels = $labels($readerRows);
        $t->true(in_array('zotero with +citations', $readerLabels, true));
        $t->true(in_array('comments (all comments)', $readerLabels, true));
        $t->true(in_array('comment warnings (all)', $readerLabels, true));
        $t->true(in_array('image with textbox caption in same paragraph populates media bag', $readerLabels, true));

        $writerGate = $inventory['localGates']['writerGoldenPackageGate'];
        $t->same('writer-golden-package-generated-stable-comparison', $writerGate['evidenceKind']);
        $t->same(38, $writerGate['expectedGoldenCaseCount']);
        $t->same(38, $writerGate['generatedPackageCount']);
        $t->same(38, $writerGate['comparedPackageCount']);
        $t->same(38, $writerGate['matchedPackageCount']);
        $t->same(0, $writerGate['mismatchedPackageCount']);
        $t->true(in_array('stable package comparison matched each pinned upstream writer golden .docx', $writerGate['proves'], true));
        $t->true(in_array('upstream Tasty runner success', $writerGate['doesNotProve'], true));

        $writer = $inventory['haskellWriterInventory'];
        $writerRows = $writer['notCoveredCases'];
        $t->same('test/Tests/Writers/Docx.hs', $writer['sourceFile']);
        $t->same('Tests.Writers.Docx.tests', $writer['entryPoint']);
        $t->same(45, $writer['totalStaticCases']);
        $t->same(38, $writer['writerGoldenDocxTestCases']);
        $t->same(7, $writer['directHunitTestCases']);
        $t->same(38, $writer['writerGoldenCasesGeneratedAndComparedByLocal38Gate']);
        $t->same(38, $writer['writerGoldenCasesMatchingStableSemantics']);
        $t->same(7, $writer['casesNotCoveredByLocal38Gate']);
        $t->same(7, count($writerRows));
        $writerLabels = $labels($writerRows);
        $t->true(in_array('no section break before first chapter (#10578)', $writerLabels, true));
        $t->true(in_array('language from reference docx is preserved', $writerLabels, true));
        $t->true(in_array('FirstParagraph after heading with footnote (#11573)', $writerLabels, true));
    },
];
