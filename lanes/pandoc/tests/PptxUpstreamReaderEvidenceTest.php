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
        $minimalPair = $pairsByStem['minimal'];
        $breakTabFieldPair = $pairsByStem['break-tab-field'];
        $bulletsPair = $pairsByStem['bullets'];
        $embeddedImagePair = $pairsByStem['embedded-image'];
        $hyperlinkTextPair = $pairsByStem['hyperlink-text'];
        $listContinuationPair = $pairsByStem['list-continuation'];
        $twoSlidesPair = $pairsByStem['two-slides'];
        $speakerNotesPair = $pairsByStem['speaker-notes'];
        $numberedListPair = $pairsByStem['numbered-list'];

        $t->same(PptxUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
        $t->same('valid-checked-in-current-pptx-reader-evidence', $static['validation']['status']);
        $t->same([], $static['validation']['issues']);
        $t->same(1, $static['readerDenominator']['expectedCompareCount']);
        $t->same('text extraction', $static['readerDenominator']['expectedReaderCases'][0]['name']);
        $t->same('pptx-reader/basic.pptx', $static['readerDenominator']['expectedReaderCases'][0]['pptx']);
        $t->same('pptx-reader/basic.native', $static['readerDenominator']['expectedReaderCases'][0]['native']);
        $t->same(10, $static['checkedInFixturePairCount']);
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
        $t->same('minimal', $minimalPair['stem']);
        $t->same('generated minimal text extraction parity', $minimalPair['name']);
        $t->same('pptx-reader/minimal.pptx|pptx-reader/minimal.native', $minimalPair['pairKey']);
        $t->same('f4852d7b0455ae99a8ef2b3d419cb2aa9ab2f8b5c4167e3770a38483ab36f202', $minimalPair['checkedInPptx']['sha256']);
        $t->same('6ec8b821c9a28c12ca65c771d7dcb6df0ec7f9f91b139e318d4cdbbd4fde4c76', $minimalPair['checkedInNative']['sha256']);
        $t->same(1502, $minimalPair['checkedInPptx']['bytes']);
        $t->same(119, $minimalPair['checkedInNative']['bytes']);
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
        $t->same('embedded-image', $embeddedImagePair['stem']);
        $t->same('generated embedded image native parity', $embeddedImagePair['name']);
        $t->same('pptx-reader/embedded-image.pptx|pptx-reader/embedded-image.native', $embeddedImagePair['pairKey']);
        $t->same('de45bd6af2dcf74e29dd7d961e5459c3a5d2b420992b1bbf280b10ee6df7256a', $embeddedImagePair['checkedInPptx']['sha256']);
        $t->same('1aea7cedcb9155ee19a55db0d2825b1427dab1f51bbb460d140cd637e2bec266', $embeddedImagePair['checkedInNative']['sha256']);
        $t->same(2363, $embeddedImagePair['checkedInPptx']['bytes']);
        $t->same(195, $embeddedImagePair['checkedInNative']['bytes']);
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
        $t->same(false, PptxUpstreamReaderEvidence::hasNoValidationIssues($report));
        $t->same(true, PptxUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
        $t->same(true, PptxUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
        $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that minimal.pptx/minimal.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that break-tab-field.pptx/break-tab-field.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that bullets.pptx/bullets.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that embedded-image.pptx/embedded-image.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that hyperlink-text.pptx/hyperlink-text.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that list-continuation.pptx/list-continuation.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that two-slides.pptx/two-slides.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that speaker-notes.pptx/speaker-notes.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that numbered-list.pptx/numbered-list.native is an upstream Tests.Readers.Pptx fixture', $static['claimBoundaries']['doesNotAssert'], true));
        $t->contains('Static current evidence: valid-checked-in-current-pptx-reader-evidence comparisons=1 checkedInPairs=10', $text);
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
