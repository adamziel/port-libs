<?php

declare(strict_types=1);

use PortLibs\Pandoc\PptxExecutableNativeAstComparisonHarness;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-pptx-exec-ast-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary PPTX executable AST directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary PPTX executable AST directory {$base}");
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

$writeFakePandoc = static function (string $path, string $nativePath): void {
    file_put_contents($path, "#!/bin/sh\nif [ \"\$1\" = \"--version\" ]; then echo \"pandoc fake 1.0\"; exit 0; fi\ncat " . escapeshellarg($nativePath) . "\n");
    chmod($path, 0755);
};

$writeFixtureAwareFakePandoc = static function (string $path, string $fixtureRoot): void {
    file_put_contents(
        $path,
        "#!/bin/sh\n"
        . "if [ \"\$1\" = \"--version\" ]; then echo \"pandoc fake checked-in 1.0\"; exit 0; fi\n"
        . "for last do :; done\n"
        . "stem=\$(basename \"\$last\" .pptx)\n"
        . "cat " . escapeshellarg($fixtureRoot) . "/\"\$stem\".native\n"
    );
    chmod($path, 0755);
};

$copyBasicFixture = static function (string $root): void {
    $fixtureRoot = dirname(__DIR__) . '/fixtures/upstream-current-pptx-reader';
    copy($fixtureRoot . '/basic.pptx', $root . '/basic.pptx');
    copy($fixtureRoot . '/basic.native', $root . '/basic.native');
};

return [
    'validates checked-in real pandoc pptx executable evidence snapshot' => static function (TestRunner $t): void {
        $snapshotPath = dirname(__DIR__) . '/fixtures/upstream-current-pptx-reader/checked-in.executable-native-ast.json';
        $snapshot = json_decode((string) file_get_contents($snapshotPath), true, 512, JSON_THROW_ON_ERROR);

        $t->same('pandoc-pptx-executable-native-ast', $snapshot['tool']);
        $t->same('completed', $snapshot['status']);
        $t->same(false, $snapshot['skipped']);
        $t->same('checked-in-real-pandoc-executable-pptx-native-ast-snapshot', $snapshot['evidenceKind']);
        $t->same('tools/pandoc-pptx-executable-native-ast.php', $snapshot['sourceTool']);
        $t->same('2026-07-03', $snapshot['capturedDate']);
        $t->same('lanes/pandoc/fixtures/upstream-current-pptx-reader', $snapshot['pptxDirectory']);
        $t->same('/opt/homebrew/bin/pandoc', $snapshot['pandocExecutable']);
        $t->same('pandoc 3.10', $snapshot['pandocVersion']);
        $t->same(['basic', 'body-before-title', 'break-tab-field', 'bullets', 'bunone-wingdings', 'center-title-placeholder', 'chart-placeholder', 'comments-ignored', 'connector-skip', 'content-part-skip', 'embedded-image', 'empty-paragraph-textbox', 'generated-table', 'grouped-shapes', 'hidden-slide', 'hyperlink-text', 'inline-formatting', 'list-continuation', 'minimal', 'missing-relationship-skip', 'multi-paragraph-textbox', 'multiple-paragraph-properties', 'nested-list', 'no-title-fallback', 'numbered-list', 'paragraphless-textbox', 'shape-order', 'slide-placeholders', 'smartart-hierarchy', 'speaker-notes', 'table-span-review', 'two-slides', 'wingdings-typeface-case'], $snapshot['fixtureStems']);
        $t->same(33, $snapshot['totalPptxCount']);
        $t->same(33, $snapshot['comparedPptxCount']);
        $t->same(33, $snapshot['localParsedCount']);
        $t->same(33, $snapshot['pandocParsedCount']);
        $t->same(33, $snapshot['nativeFixtureParsedCount']);
        $t->same(33, $snapshot['bothParsedCount']);
        $t->same(33, $snapshot['normalizedAstMatchCount']);
        $t->same(0, $snapshot['normalizedAstMismatchCount']);
        $t->same(33, $snapshot['pandocNativeFixtureComparedCount']);
        $t->same(33, $snapshot['pandocNativeFixtureMatchCount']);
        $t->same(0, $snapshot['pandocNativeFixtureMismatchCount']);
        $t->same('normalized-ast-equality-observed-against-pandoc-executable', $snapshot['astParityStatus']);
        $t->same(true, PptxExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($snapshot, 33));
        $t->contains('--require-executable-parity=33', implode(' ', $snapshot['sourceCommand']));
        $t->true(in_array('that upstream Haskell/Cabal/Tasty Tests.Readers.Pptx was executed', $snapshot['claimBoundaries']['doesNotAssert'], true));
        $t->true(in_array('that generated body-before-title, break-tab-field, bullets, bunone-wingdings, center-title-placeholder, chart-placeholder, comments-ignored, connector-skip, content-part-skip, embedded-image, empty-paragraph-textbox, generated-table, grouped-shapes, hidden-slide, hyperlink-text, inline-formatting, list-continuation, minimal, missing-relationship-skip, multi-paragraph-textbox, multiple-paragraph-properties, nested-list, no-title-fallback, numbered-list, paragraphless-textbox, shape-order, slide-placeholders, smartart-hierarchy, speaker-notes, table-span-review, two-slides, wingdings-typeface-case fixtures are upstream Tests.Readers.Pptx fixtures', $snapshot['claimBoundaries']['doesNotAssert'], true));
        $t->same('covered-by-current-executable-evidence', $snapshot['orderedRemainingGaps'][0]['status']);
        $t->same('open', $snapshot['orderedRemainingGaps'][1]['status']);
    },
    'skips pptx executable comparison when pandoc is absent' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $copyBasicFixture): void {
        $root = $makeTempDir();
        try {
            $copyBasicFixture($root);
            $report = (new PptxExecutableNativeAstComparisonHarness())->run($root, [
                'pandocBin' => $root . '/missing-pandoc',
            ]);
            $text = (new PptxExecutableNativeAstComparisonHarness())->formatReport($report);

            $t->same('skipped', $report['status']);
            $t->same(true, $report['skipped']);
            $t->same('pandoc-executable-missing', $report['reason']);
            $t->same(1, $report['totalPptxCount']);
            $t->same(0, $report['comparedPptxCount']);
            $t->same(0, $report['nativeFixtureParsedCount']);
            $t->same(0, $report['pandocNativeFixtureMatchCount']);
            $t->same('not-evaluated-pandoc-executable-missing', $report['astParityStatus']);
            $t->same('not-evaluated', $report['orderedRemainingGaps'][0]['status']);
            $t->contains('Pandoc PPTX executable/native AST comparison: skipped', $text);
        } finally {
            $removeTree($root);
        }
    },
    'matches local pptx reader output against fake pandoc native output' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $copyBasicFixture, $writeFakePandoc): void {
        $root = $makeTempDir();
        try {
            $copyBasicFixture($root);
            $fakePandoc = $root . '/pandoc';
            $writeFakePandoc($fakePandoc, $root . '/basic.native');

            $report = (new PptxExecutableNativeAstComparisonHarness())->run($root, [
                'pandocBin' => $fakePandoc,
            ]);

            $t->same('completed', $report['status']);
            $t->same(false, $report['skipped']);
            $t->same(1, $report['totalPptxCount']);
            $t->same(1, $report['comparedPptxCount']);
            $t->same(1, $report['localParsedCount']);
            $t->same(1, $report['pandocParsedCount']);
            $t->same(1, $report['nativeFixtureParsedCount']);
            $t->same(0, $report['parseFailureCount']);
            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(0, $report['normalizedAstMismatchCount']);
            $t->same(1, $report['pandocNativeFixtureComparedCount']);
            $t->same(1, $report['pandocNativeFixtureMatchCount']);
            $t->same(0, $report['pandocNativeFixtureMismatchCount']);
            $t->same('pandoc fake 1.0', $report['pandocVersion']);
            $t->same('normalized-ast-equality-observed-against-pandoc-executable', $report['astParityStatus']);
            $t->same(true, PptxExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($report, 1));
        } finally {
            $removeTree($root);
        }
    },
    'reports executable native ast mismatches without claiming parity' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $copyBasicFixture, $writeFakePandoc): void {
        $root = $makeTempDir();
        try {
            $copyBasicFixture($root);
            file_put_contents($root . '/mismatch.native', '[Para [Str "different"]]');
            $fakePandoc = $root . '/pandoc';
            $writeFakePandoc($fakePandoc, $root . '/mismatch.native');

            $report = (new PptxExecutableNativeAstComparisonHarness())->run($root, [
                'pandocBin' => $fakePandoc,
            ]);

            $t->same('completed', $report['status']);
            $t->same(1, $report['normalizedAstMismatchCount']);
            $t->same(1, $report['pandocNativeFixtureComparedCount']);
            $t->same(0, $report['pandocNativeFixtureMatchCount']);
            $t->same(1, $report['pandocNativeFixtureMismatchCount']);
            $t->same('normalized-ast-mismatches-observed', $report['astParityStatus']);
            $t->same('basic', $report['mismatchComparisons'][0]['fixture']);
            $t->contains('root.children keys', $report['mismatchComparisons'][0]['firstDifference']);
            $t->same('basic', $report['pandocNativeFixtureMismatchComparisons'][0]['fixture']);
            $t->contains('root.children keys', $report['pandocNativeFixtureMismatchComparisons'][0]['firstDifference']);
            $t->same(false, PptxExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($report, 1));
        } finally {
            $removeTree($root);
        }
    },
    'requires paired native fixtures for executable parity' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFakePandoc): void {
        $root = $makeTempDir();
        try {
            $fixtureRoot = dirname(__DIR__) . '/fixtures/upstream-current-pptx-reader';
            copy($fixtureRoot . '/basic.pptx', $root . '/basic.pptx');
            copy($fixtureRoot . '/basic.native', $root . '/expected.native');
            $fakePandoc = $root . '/pandoc';
            $writeFakePandoc($fakePandoc, $root . '/expected.native');

            $report = (new PptxExecutableNativeAstComparisonHarness())->run($root, [
                'pandocBin' => $fakePandoc,
            ]);

            $t->same('completed', $report['status']);
            $t->same(1, $report['localParsedCount']);
            $t->same(1, $report['pandocParsedCount']);
            $t->same(0, $report['nativeFixtureParsedCount']);
            $t->same(1, $report['bothParsedCount']);
            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(0, $report['pandocNativeFixtureComparedCount']);
            $t->same(1, $report['parseFailureCount']);
            $t->same('missing paired .native fixture', $report['parseFailures'][0]['nativeFixtureError']);
            $t->same('blocked-by-parse-failures', $report['astParityStatus']);
            $t->same(false, PptxExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($report, 1));
        } finally {
            $removeTree($root);
        }
    },
    'cli gates required executable pptx parity with fake pandoc' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $copyBasicFixture, $writeFakePandoc): void {
        $root = $makeTempDir();
        try {
            $copyBasicFixture($root);
            $fakePandoc = $root . '/pandoc';
            $writeFakePandoc($fakePandoc, $root . '/basic.native');
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-pptx-executable-native-ast.php')
                . ' --pptx-dir=' . escapeshellarg($root)
                . ' --pandoc-bin=' . escapeshellarg($fakePandoc)
                . ' --json'
                . ' summary'
                . ' --require-executable-parity=1';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same(1, $decoded['normalizedAstMatchCount']);
            $t->same(0, $decoded['normalizedAstMismatchCount']);
            $t->same(1, $decoded['pandocNativeFixtureComparedCount']);
            $t->same(1, $decoded['pandocNativeFixtureMatchCount']);
            $t->same(0, $decoded['pandocNativeFixtureMismatchCount']);
            $t->same(true, PptxExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($decoded, 1));

            $missingCommand = str_replace('--pandoc-bin=' . escapeshellarg($fakePandoc), '--pandoc-bin=' . escapeshellarg($root . '/missing'), $command) . ' 2>/dev/null';
            $missingOutput = [];
            $missingExitCode = 0;
            exec($missingCommand, $missingOutput, $missingExitCode);

            $t->same(1, $missingExitCode);
        } finally {
            $removeTree($root);
        }
    },
    'cli selects checked-in fixtures for executable pptx parity with fake pandoc' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFixtureAwareFakePandoc): void {
        $root = $makeTempDir();
        try {
            $fixtureRoot = dirname(__DIR__) . '/fixtures/upstream-current-pptx-reader';
            $fakePandoc = $root . '/pandoc';
            $writeFixtureAwareFakePandoc($fakePandoc, $fixtureRoot);
            $tool = dirname(__DIR__, 3) . '/tools/pandoc-pptx-executable-native-ast.php';
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg($tool)
                . ' --checked-in-fixtures'
                . ' --pandoc-bin=' . escapeshellarg($fakePandoc)
                . ' --json'
                . ' summary'
                . ' --require-executable-parity=33';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same(dirname(__DIR__, 3) . '/lanes/pandoc/fixtures/upstream-current-pptx-reader', $decoded['pptxDirectory']);
            $t->same('pandoc fake checked-in 1.0', $decoded['pandocVersion']);
            $t->same(33, $decoded['comparedPptxCount']);
            $t->same(33, $decoded['localParsedCount']);
            $t->same(33, $decoded['pandocParsedCount']);
            $t->same(33, $decoded['nativeFixtureParsedCount']);
            $t->same(33, $decoded['bothParsedCount']);
            $t->same(33, $decoded['normalizedAstMatchCount']);
            $t->same(0, $decoded['normalizedAstMismatchCount']);
            $t->same(33, $decoded['pandocNativeFixtureComparedCount']);
            $t->same(33, $decoded['pandocNativeFixtureMatchCount']);
            $t->same(0, $decoded['pandocNativeFixtureMismatchCount']);
            $t->same(true, PptxExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($decoded, 33));

            $conflictingCommand = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg($tool)
                . ' --checked-in-fixtures'
                . ' --pptx-dir=' . escapeshellarg($fixtureRoot)
                . ' --pandoc-bin=' . escapeshellarg($fakePandoc)
                . ' --json'
                . ' summary'
                . ' 2>/dev/null';
            $conflictingOutput = [];
            $conflictingExitCode = 0;
            exec($conflictingCommand, $conflictingOutput, $conflictingExitCode);

            $t->same(2, $conflictingExitCode);
        } finally {
            $removeTree($root);
        }
    },
];
