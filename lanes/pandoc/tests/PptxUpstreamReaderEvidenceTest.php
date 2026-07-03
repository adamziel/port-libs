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

$writePptxEvidenceTree = static function (string $root) use ($writeFile): void {
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
            $t->same('not-run', $report['runnerEvidence']['status']);
            $t->same(false, $report['runnerEvidence']['executed']);
            $t->same(null, $report['runnerEvidence']['command']);
            $t->same(null, $report['runnerEvidence']['resultArtifact']);
            $t->contains('Pandoc PPTX reader evidence', $text);
            $t->contains('Runner status: not-run', $text);
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
            $t->same('upstream-haskell-runner', $report['runnerEvidence']['scope']);
            $t->same('$2 == "Readers" && $3 == "Pptx"', $report['runnerEvidence']['futureCommands'][1]['arguments'][8]);
            $t->true(in_array('.port-libs/pandoc-runner/logs/pptx-targeted-run.txt', $report['runnerEvidence']['requiredArtifacts'], true));
            $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $report['claimBoundaries']['doesNotAssert'], true));
            $t->true(in_array('that upstream Haskell runner evidence is explicitly not-run', $report['claimBoundaries']['doesAssert'], true));
        } finally {
            $removeTree($root);
        }
    },
    'reports checked-in current upstream pptx static evidence gate' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $report = (new PptxUpstreamReaderEvidence($repoRoot, 'missing-upstream-root-for-static-gate'))->report();
        $text = PptxUpstreamReaderEvidence::formatTextReport($report);
        $static = $report['staticCurrentEvidence'];
        $pairsByStem = [];
        foreach ($static['checkedInFixturePairs'] as $fixturePair) {
            $pairsByStem[$fixturePair['stem']] = $fixturePair;
        }
        $pair = $pairsByStem['basic'];
        $bodyBeforeTitlePair = $pairsByStem['body-before-title'];
        $minimalPair = $pairsByStem['minimal'];
        $missingRelationshipSkipPair = $pairsByStem['missing-relationship-skip'];
        $multiParagraphTextboxPair = $pairsByStem['multi-paragraph-textbox'];
        $nestedListPair = $pairsByStem['nested-list'];
        $emptyParagraphTextboxPair = $pairsByStem['empty-paragraph-textbox'];
        $breakTabFieldPair = $pairsByStem['break-tab-field'];
        $bulletsPair = $pairsByStem['bullets'];
        $commentsIgnoredPair = $pairsByStem['comments-ignored'];
        $contentPartSkipPair = $pairsByStem['content-part-skip'];
        $connectorSkipPair = $pairsByStem['connector-skip'];
        $embeddedImagePair = $pairsByStem['embedded-image'];
        $generatedTablePair = $pairsByStem['generated-table'];
        $tableSpanReviewPair = $pairsByStem['table-span-review'];
        $groupedShapesPair = $pairsByStem['grouped-shapes'];
        $hiddenSlidePair = $pairsByStem['hidden-slide'];
        $hyperlinkTextPair = $pairsByStem['hyperlink-text'];
        $listContinuationPair = $pairsByStem['list-continuation'];
        $twoSlidesPair = $pairsByStem['two-slides'];
        $speakerNotesPair = $pairsByStem['speaker-notes'];
        $numberedListPair = $pairsByStem['numbered-list'];
        $shapeOrderPair = $pairsByStem['shape-order'];
        $slidePlaceholdersPair = $pairsByStem['slide-placeholders'];

        $t->same(PptxUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
        $t->same('valid-checked-in-current-pptx-reader-evidence', $static['validation']['status']);
        $t->same([], $static['validation']['issues']);
        $t->same(1, $static['readerDenominator']['expectedCompareCount']);
        $t->same('text extraction', $static['readerDenominator']['expectedReaderCases'][0]['name']);
        $t->same('pptx-reader/basic.pptx', $static['readerDenominator']['expectedReaderCases'][0]['pptx']);
        $t->same('pptx-reader/basic.native', $static['readerDenominator']['expectedReaderCases'][0]['native']);
        $t->same(24, $static['checkedInFixturePairCount']);
        $t->same(0, $static['checkedInUnpairedPptxFixtureCount']);
        $t->same(0, $static['checkedInUnpairedNativeFixtureCount']);
        $t->same([], $static['checkedInUnpairedPptxFixtures']);
        $t->same([], $static['checkedInUnpairedNativeFixtures']);
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
        $t->same('nested-list', $nestedListPair['stem']);
        $t->same('generated adjacent list-level split parity', $nestedListPair['name']);
        $t->same('pptx-reader/nested-list.pptx|pptx-reader/nested-list.native', $nestedListPair['pairKey']);
        $t->same('c85b56c09a3568286e4c0d7b1979d88b700d5f609e121955c691a58f2bb97ff0', $nestedListPair['checkedInPptx']['sha256']);
        $t->same('395c237357a332023f6bb3c991f2f84d54be6fb277ce964cdaad6d9ffe2336a6', $nestedListPair['checkedInNative']['sha256']);
        $t->same(1703, $nestedListPair['checkedInPptx']['bytes']);
        $t->same(253, $nestedListPair['checkedInNative']['bytes']);
        $t->same('empty-paragraph-textbox', $emptyParagraphTextboxPair['stem']);
        $t->same('generated explicit empty paragraph text box parity', $emptyParagraphTextboxPair['name']);
        $t->same('pptx-reader/empty-paragraph-textbox.pptx|pptx-reader/empty-paragraph-textbox.native', $emptyParagraphTextboxPair['pairKey']);
        $t->same('3c2746d48004a382c77a6b0780c31dae0246c9f9063251db2f93bcc16e688655', $emptyParagraphTextboxPair['checkedInPptx']['sha256']);
        $t->same('9a1dd6f8ddf28f555cd1f128f5e24864284f1a721d2ae3c1e4598ebdcbe9b21b', $emptyParagraphTextboxPair['checkedInNative']['sha256']);
        $t->same(1519, $emptyParagraphTextboxPair['checkedInPptx']['bytes']);
        $t->same(169, $emptyParagraphTextboxPair['checkedInNative']['bytes']);
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
        $t->same('hidden-slide', $hiddenSlidePair['stem']);
        $t->same('generated hidden slide inclusion parity', $hiddenSlidePair['name']);
        $t->same('pptx-reader/hidden-slide.pptx|pptx-reader/hidden-slide.native', $hiddenSlidePair['pairKey']);
        $t->same('01627fa5f56ca583f3604306984cc1df4b69a15339396b061e44604265cb802f', $hiddenSlidePair['checkedInPptx']['sha256']);
        $t->same('a543e3ed60ca4d5f187fba970ed855d5f064a911e3ee3224b07929481c62b515', $hiddenSlidePair['checkedInNative']['sha256']);
        $t->same(1893, $hiddenSlidePair['checkedInPptx']['bytes']);
        $t->same(178, $hiddenSlidePair['checkedInNative']['bytes']);
        $t->same('hyperlink-text', $hyperlinkTextPair['stem']);
        $t->same('generated text hyperlink invisibility parity', $hyperlinkTextPair['name']);
        $t->same('pptx-reader/hyperlink-text.pptx|pptx-reader/hyperlink-text.native', $hyperlinkTextPair['pairKey']);
        $t->same('22180e777f4a145bd3aff34f6fd5c2a846ce5567d758a78565b5dfc6addca6e3', $hyperlinkTextPair['checkedInPptx']['sha256']);
        $t->same('f4334af63e88a238caf0dcb2a4bf37fa1745d54bb2d703ec287fb3cc0474bcd7', $hyperlinkTextPair['checkedInNative']['sha256']);
        $t->same(2004, $hyperlinkTextPair['checkedInPptx']['bytes']);
        $t->same(100, $hyperlinkTextPair['checkedInNative']['bytes']);
        $t->same('list-continuation', $listContinuationPair['stem']);
        $t->same('generated buNone list-continuation boundary parity', $listContinuationPair['name']);
        $t->same('pptx-reader/list-continuation.pptx|pptx-reader/list-continuation.native', $listContinuationPair['pairKey']);
        $t->same('2b7ae7359fde4edb717371d518ef80c8bbda374fa72def88c3dcd744c91fdf5f', $listContinuationPair['checkedInPptx']['sha256']);
        $t->same('d5dd188d56624d8aa5a8a848a40d2e4568e3f522f034573dc8b539842ae702de', $listContinuationPair['checkedInNative']['sha256']);
        $t->same(1713, $listContinuationPair['checkedInPptx']['bytes']);
        $t->same(294, $listContinuationPair['checkedInNative']['bytes']);
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
        $t->same(false, PptxUpstreamReaderEvidence::hasNoValidationIssues($report));
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
        $t->same(true, PptxUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
        $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that body-before-title.pptx/body-before-title.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that minimal.pptx/minimal.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that missing-relationship-skip.pptx/missing-relationship-skip.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that multi-paragraph-textbox.pptx/multi-paragraph-textbox.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that nested-list.pptx/nested-list.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that empty-paragraph-textbox.pptx/empty-paragraph-textbox.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that break-tab-field.pptx/break-tab-field.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that bullets.pptx/bullets.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that comments-ignored.pptx/comments-ignored.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that content-part-skip.pptx/content-part-skip.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that connector-skip.pptx/connector-skip.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that embedded-image.pptx/embedded-image.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that generated-table.pptx/generated-table.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that table-span-review.pptx/table-span-review.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that grouped-shapes.pptx/grouped-shapes.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that hidden-slide.pptx/hidden-slide.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that hyperlink-text.pptx/hyperlink-text.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that list-continuation.pptx/list-continuation.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that two-slides.pptx/two-slides.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that speaker-notes.pptx/speaker-notes.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that numbered-list.pptx/numbered-list.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that shape-order.pptx/shape-order.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that slide-placeholders.pptx/slide-placeholders.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->contains('Static current evidence: valid-checked-in-current-pptx-reader-evidence comparisons=1 checkedInPairs=24', $text);
        $t->contains('Runner status: not-run', $text);
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
            . ' --require-runner-not-run';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(PptxUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $decoded['status']);
        $t->same('not-evaluated-missing-upstream-root', $decoded['validation']['status']);
        $t->same('valid-checked-in-current-pptx-reader-evidence', $decoded['staticCurrentEvidence']['validation']['status']);
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($decoded));
        $t->same(true, PptxUpstreamReaderEvidence::hasRunnerNotRunEvidence($decoded));
        $t->same('not-run', $decoded['runnerEvidence']['status']);
        $t->same(false, PptxUpstreamReaderEvidence::hasNoValidationIssues($decoded));

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
                . ' --require-no-validation-issues';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same(1, $decoded['denominator']['readerTestCompareCount']);
            $t->same(1, $decoded['denominator']['fixturePairCount']);
            $t->same('valid-upstream-pptx-reader-denominator', $decoded['validation']['status']);

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
