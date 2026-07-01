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

$projectRows = static function (array $rows, array $keys): array {
    return array_values(array_map(
        static function (array $row) use ($keys): array {
            $projected = [];
            foreach ($keys as $key) {
                $projected[$key] = $row[$key] ?? null;
            }

            return $projected;
        },
        $rows
    ));
};

$countsBy = static function (array $rows, string $key): array {
    $counts = [];
    foreach ($rows as $row) {
        $value = (string) ($row[$key] ?? '');
        $counts[$value] = ($counts[$value] ?? 0) + 1;
    }
    ksort($counts, SORT_STRING);

    return $counts;
};

return [
    'maps pinned upstream docx haskell cases to local reader and writer gates' => static function (TestRunner $t) use ($readInventory, $labels, $projectRows, $countsBy): void {
        $inventory = $readInventory();

        $t->same(1, $inventory['schemaVersion']);
        $t->same('reported-upstream-docx-haskell-inventory-local-gate-map', $inventory['status']);
        $t->same('612e143fbe6d735b612c4800d21e61b7d44e4dca', $inventory['upstream']['commit']);
        $t->same('static-upstream-haskell-docx-inventory-to-local-gate-map', $inventory['evidenceKind']);
        $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $inventory['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that the upstream Haskell direct writer assertions themselves were executed', $inventory['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('full DOCX/OpenXML parity', $inventory['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('local PHP DocxWriter mirror assertions cover the seven direct upstream writer HUnit cases not exercised by the writer-golden gate', $inventory['claimBoundaries']['doesAssert'], true));

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
        $t->same(
            (int) $reader['totalStaticCases'] - (int) $reader['strictDefaultSameStemCasesCoveredByLocal74Gate'],
            $reader['casesNotCoveredByLocal74GateSemantics'],
            'Reader uncovered denominator must match total static Haskell cases minus strict local semantic coverage'
        );
        $t->same([
            'different-native-expectation-stem' => 6,
            'different-native-expectation-stem-and-non-default-reader-options' => 17,
            'media-bag-only-no-native-comparison' => 4,
            'non-default-reader-options' => 5,
            'warning-only-no-native-comparison' => 4,
        ], $countsBy($readerRows, 'reason'));
        $t->same([
            'testCompare' => 6,
            'testCompareWithOpts' => 22,
            'testForWarningsWithOpts' => 4,
            'testMediaBag' => 4,
        ], $countsBy($readerRows, 'call'));
        $t->same([
            ['call' => 'testCompare', 'label' => 'inline image', 'docx' => 'docx/image.docx', 'native' => 'docx/image_no_embed.native', 'reason' => 'different-native-expectation-stem'],
            ['call' => 'testCompare', 'label' => 'zotero with -citations', 'docx' => 'docx/zotero_citations.docx', 'native' => 'docx/zotero_citations_minus.native', 'reason' => 'different-native-expectation-stem'],
            ['call' => 'testCompareWithOpts', 'label' => 'zotero with +citations', 'docx' => 'docx/zotero_citations.docx', 'native' => 'docx/zotero_citations_plus.native', 'reason' => 'different-native-expectation-stem-and-non-default-reader-options'],
            ['call' => 'testCompare', 'label' => 'mendeley with -citations', 'docx' => 'docx/mendeley_citations.docx', 'native' => 'docx/mendeley_citations_minus.native', 'reason' => 'different-native-expectation-stem'],
            ['call' => 'testCompareWithOpts', 'label' => 'mendeley with +citations', 'docx' => 'docx/mendeley_citations.docx', 'native' => 'docx/mendeley_citations_plus.native', 'reason' => 'different-native-expectation-stem-and-non-default-reader-options'],
            ['call' => 'testCompare', 'label' => 'insertion (default)', 'docx' => 'docx/track_changes_insertion.docx', 'native' => 'docx/track_changes_insertion_accept.native', 'reason' => 'different-native-expectation-stem'],
            ['call' => 'testCompareWithOpts', 'label' => 'insert insertion (accept)', 'docx' => 'docx/track_changes_insertion.docx', 'native' => 'docx/track_changes_insertion_accept.native', 'reason' => 'different-native-expectation-stem-and-non-default-reader-options'],
            ['call' => 'testCompareWithOpts', 'label' => 'remove insertion (reject)', 'docx' => 'docx/track_changes_insertion.docx', 'native' => 'docx/track_changes_insertion_reject.native', 'reason' => 'different-native-expectation-stem-and-non-default-reader-options'],
            ['call' => 'testCompare', 'label' => 'deletion (default)', 'docx' => 'docx/track_changes_deletion.docx', 'native' => 'docx/track_changes_deletion_accept.native', 'reason' => 'different-native-expectation-stem'],
            ['call' => 'testCompareWithOpts', 'label' => 'remove deletion (accept)', 'docx' => 'docx/track_changes_deletion.docx', 'native' => 'docx/track_changes_deletion_accept.native', 'reason' => 'different-native-expectation-stem-and-non-default-reader-options'],
            ['call' => 'testCompareWithOpts', 'label' => 'insert deletion (reject)', 'docx' => 'docx/track_changes_deletion.docx', 'native' => 'docx/track_changes_deletion_reject.native', 'reason' => 'different-native-expectation-stem-and-non-default-reader-options'],
            ['call' => 'testCompareWithOpts', 'label' => 'keep insertion (all)', 'docx' => 'docx/track_changes_deletion.docx', 'native' => 'docx/track_changes_deletion_all.native', 'reason' => 'different-native-expectation-stem-and-non-default-reader-options'],
            ['call' => 'testCompareWithOpts', 'label' => 'keep deletion (all)', 'docx' => 'docx/track_changes_deletion.docx', 'native' => 'docx/track_changes_deletion_all.native', 'reason' => 'different-native-expectation-stem-and-non-default-reader-options'],
            ['call' => 'testCompareWithOpts', 'label' => 'move text (accept)', 'docx' => 'docx/track_changes_move.docx', 'native' => 'docx/track_changes_move_accept.native', 'reason' => 'different-native-expectation-stem-and-non-default-reader-options'],
            ['call' => 'testCompareWithOpts', 'label' => 'move text (reject)', 'docx' => 'docx/track_changes_move.docx', 'native' => 'docx/track_changes_move_reject.native', 'reason' => 'different-native-expectation-stem-and-non-default-reader-options'],
            ['call' => 'testCompareWithOpts', 'label' => 'move text (all)', 'docx' => 'docx/track_changes_move.docx', 'native' => 'docx/track_changes_move_all.native', 'reason' => 'different-native-expectation-stem-and-non-default-reader-options'],
            ['call' => 'testCompareWithOpts', 'label' => 'comments (accept -- no comments)', 'docx' => 'docx/comments.docx', 'native' => 'docx/comments_no_comments.native', 'reason' => 'different-native-expectation-stem-and-non-default-reader-options'],
            ['call' => 'testCompareWithOpts', 'label' => 'comments (reject -- comments)', 'docx' => 'docx/comments.docx', 'native' => 'docx/comments_no_comments.native', 'reason' => 'different-native-expectation-stem-and-non-default-reader-options'],
            ['call' => 'testCompareWithOpts', 'label' => 'comments (all comments)', 'docx' => 'docx/comments.docx', 'native' => 'docx/comments.native', 'reason' => 'non-default-reader-options'],
            ['call' => 'testCompareWithOpts', 'label' => 'paragraph insertion/deletion (accept)', 'docx' => 'docx/paragraph_insertion_deletion.docx', 'native' => 'docx/paragraph_insertion_deletion_accept.native', 'reason' => 'different-native-expectation-stem-and-non-default-reader-options'],
            ['call' => 'testCompareWithOpts', 'label' => 'paragraph insertion/deletion (reject)', 'docx' => 'docx/paragraph_insertion_deletion.docx', 'native' => 'docx/paragraph_insertion_deletion_reject.native', 'reason' => 'different-native-expectation-stem-and-non-default-reader-options'],
            ['call' => 'testCompareWithOpts', 'label' => 'paragraph insertion/deletion (all)', 'docx' => 'docx/paragraph_insertion_deletion.docx', 'native' => 'docx/paragraph_insertion_deletion_all.native', 'reason' => 'different-native-expectation-stem-and-non-default-reader-options'],
            ['call' => 'testCompareWithOpts', 'label' => 'paragraph insertion/deletion (all)', 'docx' => 'docx/track_changes_scrubbed_metadata.docx', 'native' => 'docx/track_changes_scrubbed_metadata.native', 'reason' => 'non-default-reader-options'],
            ['call' => 'testCompare', 'label' => 'custom styles (`+styles`) not enabled (default)', 'docx' => 'docx/custom-style-reference.docx', 'native' => 'docx/custom-style-no-styles.native', 'reason' => 'different-native-expectation-stem'],
            ['call' => 'testCompareWithOpts', 'label' => 'custom styles (`+styles`) enabled', 'docx' => 'docx/custom-style-reference.docx', 'native' => 'docx/custom-style-with-styles.native', 'reason' => 'different-native-expectation-stem-and-non-default-reader-options'],
            ['call' => 'testCompareWithOpts', 'label' => 'custom styles (`+styles`): Compact style is removed from output', 'docx' => 'docx/compact-style-removal.docx', 'native' => 'docx/compact-style-removal.native', 'reason' => 'non-default-reader-options'],
            ['call' => 'testCompareWithOpts', 'label' => 'metadata fields', 'docx' => 'docx/metadata.docx', 'native' => 'docx/metadata.native', 'reason' => 'non-default-reader-options'],
            ['call' => 'testCompareWithOpts', 'label' => 'stop recording metadata with normal text', 'docx' => 'docx/metadata_after_normal.docx', 'native' => 'docx/metadata_after_normal.native', 'reason' => 'non-default-reader-options'],
            ['call' => 'testForWarningsWithOpts', 'label' => 'comment warnings (accept -- no warnings)', 'docx' => 'docx/comments_warning.docx', 'native' => null, 'reason' => 'warning-only-no-native-comparison'],
            ['call' => 'testForWarningsWithOpts', 'label' => 'comment warnings (reject -- no warnings)', 'docx' => 'docx/comments_warning.docx', 'native' => null, 'reason' => 'warning-only-no-native-comparison'],
            ['call' => 'testForWarningsWithOpts', 'label' => 'comment warnings (all)', 'docx' => 'docx/comments_warning.docx', 'native' => null, 'reason' => 'warning-only-no-native-comparison'],
            ['call' => 'testForWarningsWithOpts', 'label' => 'comments (with styles extension)', 'docx' => 'docx/comments.docx', 'native' => null, 'reason' => 'warning-only-no-native-comparison'],
            ['call' => 'testMediaBag', 'label' => 'image extraction', 'docx' => 'docx/image.docx', 'native' => null, 'reason' => 'media-bag-only-no-native-comparison'],
            ['call' => 'testMediaBag', 'label' => 'image inside textbox content populates media bag', 'docx' => 'docx/textbox_image.docx', 'native' => null, 'reason' => 'media-bag-only-no-native-comparison'],
            ['call' => 'testMediaBag', 'label' => 'image inside textbox content with duplicate encoding populates media bag', 'docx' => 'docx/textbox_image_duplicate_encoding.docx', 'native' => null, 'reason' => 'media-bag-only-no-native-comparison'],
            ['call' => 'testMediaBag', 'label' => 'image with textbox caption in same paragraph populates media bag', 'docx' => 'docx/image_with_textbox_caption.docx', 'native' => null, 'reason' => 'media-bag-only-no-native-comparison'],
        ], $projectRows($readerRows, ['call', 'label', 'docx', 'native', 'reason']));
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

        $directWriterGate = $inventory['localGates']['writerDirectHunitMirrorGate'];
        $t->same('passed-local-direct-writer-hunit-mirrors', $directWriterGate['status']);
        $t->same('php-docx-writer-direct-hunit-mirror-tests', $directWriterGate['evidenceKind']);
        $t->same('php tools/run-tests.php lanes/pandoc/tests/DocxWriterTest.php', $directWriterGate['tool']);
        $t->same('lanes/pandoc/tests/DocxWriterTest.php', $directWriterGate['testFile']);
        $t->same('haskellWriterInventory.notCoveredCases', $directWriterGate['upstreamDenominator']);
        $t->same(7, $directWriterGate['expectedDirectHunitCaseCount']);
        $t->same(7, $directWriterGate['mirroredDirectHunitCaseCount']);
        $t->same(7, $directWriterGate['passedMirrorCaseCount']);
        $t->same(0, $directWriterGate['failedMirrorCaseCount']);
        $t->true(in_array('local PHP DocxWriter has focused XML-level mirror assertions for each direct upstream writer HUnit case outside the 38/38 writer-golden gate', $directWriterGate['proves'], true));
        $t->true(in_array('upstream Haskell/Cabal/Tasty runner success', $directWriterGate['doesNotProve'], true));
        $t->true(in_array('byte-for-byte DOCX package equality for the direct HUnit cases', $directWriterGate['doesNotProve'], true));

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
        $t->same(
            (int) $writer['writerGoldenDocxTestCases'] + (int) $writer['directHunitTestCases'],
            $writer['totalStaticCases'],
            'Writer static Haskell denominator must stay split between golden and direct HUnit cases'
        );
        $t->same($writer['directHunitTestCases'], $writer['casesNotCoveredByLocal38Gate']);
        $t->same([
            'direct-writer-assertion-no-golden-package' => 7,
        ], $countsBy($writerRows, 'reason'));
        $t->same([
            'testCase' => 7,
        ], $countsBy($writerRows, 'call'));
        $t->same([
            ['call' => 'testCase', 'label' => 'no section break before first chapter (#10578)', 'reason' => 'direct-writer-assertion-no-golden-package'],
            ['call' => 'testCase', 'label' => 'section breaks between chapters (#11482)', 'reason' => 'direct-writer-assertion-no-golden-package'],
            ['call' => 'testCase', 'label' => 'no media directory override in content types', 'reason' => 'direct-writer-assertion-no-golden-package'],
            ['call' => 'testCase', 'label' => 'language from reference docx is preserved', 'reason' => 'direct-writer-assertion-no-golden-package'],
            ['call' => 'testCase', 'label' => 'section properties from non-w-prefix reference docx', 'reason' => 'direct-writer-assertion-no-golden-package'],
            ['call' => 'testCase', 'label' => 'language from metadata overrides reference docx', 'reason' => 'direct-writer-assertion-no-golden-package'],
            ['call' => 'testCase', 'label' => 'FirstParagraph after heading with footnote (#11573)', 'reason' => 'direct-writer-assertion-no-golden-package'],
        ], $projectRows($writerRows, ['call', 'label', 'reason']));
        $writerLabels = $labels($writerRows);
        $t->true(in_array('no section break before first chapter (#10578)', $writerLabels, true));
        $t->true(in_array('language from reference docx is preserved', $writerLabels, true));
        $t->true(in_array('FirstParagraph after heading with footnote (#11573)', $writerLabels, true));
        $t->same($writerLabels, $directWriterGate['mirroredLabels']);
    },
];
