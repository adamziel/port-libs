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
        $t->same('2026-07-05', $snapshot['capturedDate']);
        $t->same('lanes/pandoc/fixtures/upstream-current-pptx-reader', $snapshot['pptxDirectory']);
        $t->same('/opt/homebrew/bin/pandoc', $snapshot['pandocExecutable']);
        $t->same('pandoc 3.10', $snapshot['pandocVersion']);
        $t->same(['alternate-content-skip', 'background-image-skip', 'basic', 'body-before-title', 'break-tab-field', 'bullets', 'bunone-wingdings', 'case-sensitive-placeholder-type', 'cdata-entity-text', 'center-title-placeholder', 'chart-embedded-workbook', 'chart-placeholder', 'comments-ignored', 'connector-skip', 'connector-text-skip', 'content-part-skip', 'diagram-missing-rels', 'diagram-no-relids', 'direct-drawing-paragraphs', 'document-properties', 'dot-presentation-target', 'dot-slide-target', 'duplicate-relationship-id', 'duplicate-slide-reference', 'embed-and-link-image', 'embedded-image', 'empty-bullet-paragraph', 'empty-header-table', 'empty-paragraph-textbox', 'empty-title-placeholder', 'end-paragraph-symbol', 'external-mode-slide-target', 'external-rich-media-skip', 'first-run-property-symbol', 'first-text-body', 'first-title-placeholder', 'generated-table', 'graphic-no-uri', 'grouped-shape-media-review', 'grouped-shapes', 'hex-list-level', 'hidden-shape-metadata', 'hidden-slide', 'hyperlink-text', 'ignored-slide-id-attributes', 'inline-formatting', 'linked-image-skip', 'list-continuation', 'literal-image-targets', 'media-relative-image-target', 'minimal', 'missing-relationship-skip', 'missing-slide-relationship-type', 'multi-paragraph-table-cell', 'multi-paragraph-textbox', 'multiple-paragraph-properties', 'namespace-agnostic-drawing-text', 'namespace-scoped-table', 'nested-list', 'no-slides', 'no-title-fallback', 'non-relationship-child-relationships', 'numbered-list', 'octal-list-level', 'overflow-bullet-level', 'pandoc-generated-image-alt-title', 'paragraph-property-descendant-text', 'paragraphless-textbox', 'parenthesized-bullet-level', 'percent-encoded-target', 'picture-shape-hyperlink', 'presentation-sections-custom-shows', 'qualified-bullet-level', 'qualified-picture-metadata', 'query-fragment-presentation-target', 'rel-prefix-image-skip', 'repeated-slash-presentation-target', 'repeated-slash-slide-target', 'rich-media-skip', 'root-targetmode-external', 'rooted-slide-target', 'shape-order', 'signed-bullet-level', 'slide-layout-placeholder-no-inherit', 'slide-placeholders', 'smartart-hierarchy', 'smartart-title-fallback', 'speaker-notes', 'subtitle-placeholder', 'table-span-review', 'table-styles-relationship', 'text-comment-boundary', 'textbox-without-nonvisual-properties', 'transition-animation-metadata', 'transition-sound-media', 'two-slides', 'unicode-drawing-text', 'unknown-graphic-uri', 'whitespace-drawing-text', 'wingdings-typeface-case', 'wrong-typed-slide-relationship'], $snapshot['fixtureStems']);
        $t->same(101, $snapshot['totalPptxCount']);
        $t->same(101, $snapshot['comparedPptxCount']);
        $t->same(101, $snapshot['localParsedCount']);
        $t->same(101, $snapshot['pandocParsedCount']);
        $t->same(101, $snapshot['nativeFixtureParsedCount']);
        $t->same(101, $snapshot['bothParsedCount']);
        $t->same(101, $snapshot['normalizedAstMatchCount']);
        $t->same(0, $snapshot['normalizedAstMismatchCount']);
        $t->same(101, $snapshot['pandocNativeFixtureComparedCount']);
        $t->same(101, $snapshot['pandocNativeFixtureMatchCount']);
        $t->same(0, $snapshot['pandocNativeFixtureMismatchCount']);
        $t->same('normalized-ast-equality-observed-against-pandoc-executable', $snapshot['astParityStatus']);
        $t->same(true, PptxExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($snapshot, 101));
        $t->same(true, PptxExecutableNativeAstComparisonHarness::hasRequiredPandocVersion($snapshot, 'pandoc 3.10'));
        $t->same(false, PptxExecutableNativeAstComparisonHarness::hasRequiredPandocVersion($snapshot, 'pandoc 3.9'));
        $t->contains('--require-executable-parity=101', implode(' ', $snapshot['sourceCommand']));
        $t->contains('--require-pandoc-version=pandoc 3.10', implode(' ', $snapshot['sourceCommand']));
        $t->true(in_array('that upstream Haskell/Cabal/Tasty Tests.Readers.Pptx was executed', $snapshot['claimBoundaries']['doesNotAssert'], true));
        $generatedBoundary = array_values(array_filter(
            $snapshot['claimBoundaries']['doesNotAssert'],
            static fn (string $claim): bool => str_starts_with($claim, 'that generated ')
        ))[0] ?? '';
        $t->contains('external-mode-slide-target', $generatedBoundary);
        $t->contains('diagram-missing-rels', $generatedBoundary);
        $t->contains('graphic-no-uri', $generatedBoundary);
        $t->contains('rel-prefix-image-skip', $generatedBoundary);
        $t->contains('unicode-drawing-text', $generatedBoundary);
        $t->contains('namespace-scoped-table', $generatedBoundary);
        $t->contains('literal-image-targets', $generatedBoundary);
        $t->contains('grouped-shape-media-review', $generatedBoundary);
        $t->contains('hidden-shape-metadata', $generatedBoundary);
        $t->contains('qualified-picture-metadata', $generatedBoundary);
        $t->contains('query-fragment-presentation-target', $generatedBoundary);
        $t->contains('picture-shape-hyperlink', $generatedBoundary);
        $t->contains('presentation-sections-custom-shows', $generatedBoundary);
        $t->contains('unknown-graphic-uri', $generatedBoundary);
        $t->contains('text-comment-boundary', $generatedBoundary);
        $t->contains('repeated-slash-presentation-target', $generatedBoundary);
        $t->contains('root-targetmode-external', $generatedBoundary);
        $t->contains('rooted-slide-target', $generatedBoundary);
        $t->contains('textbox-without-nonvisual-properties', $generatedBoundary);
        $t->contains('missing-slide-relationship-type', $generatedBoundary);
        $t->contains('empty-title-placeholder', $generatedBoundary);
        $t->contains('non-relationship-child-relationships', $generatedBoundary);
        $t->contains('fixtures are upstream Tests.Readers.Pptx fixtures', $generatedBoundary);
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
            $t->same([], $report['fixtureComparisons']);
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
            $t->same(1, count($report['fixtureComparisons']));
            $t->same('basic', $report['fixtureComparisons'][0]['fixture']);
            $t->same('matched', $report['fixtureComparisons'][0]['status']);
            $t->same('matched', $report['fixtureComparisons'][0]['localPandocStatus']);
            $t->same('matched', $report['fixtureComparisons'][0]['pandocNativeFixtureStatus']);
            $t->same(true, PptxExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($report, 1));
            $t->same(true, PptxExecutableNativeAstComparisonHarness::hasRequiredPandocVersion($report, 'pandoc fake 1.0'));
            $t->same(false, PptxExecutableNativeAstComparisonHarness::hasRequiredPandocVersion($report, 'pandoc fake 2.0'));
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
            $t->same(1, count($report['fixtureComparisons']));
            $t->same('mismatched', $report['fixtureComparisons'][0]['status']);
            $t->same('mismatched', $report['fixtureComparisons'][0]['localPandocStatus']);
            $t->same('mismatched', $report['fixtureComparisons'][0]['pandocNativeFixtureStatus']);
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
            $t->same(1, count($report['fixtureComparisons']));
            $t->same('parse-failure', $report['fixtureComparisons'][0]['status']);
            $t->same('matched', $report['fixtureComparisons'][0]['localPandocStatus']);
            $t->same('not-compared', $report['fixtureComparisons'][0]['pandocNativeFixtureStatus']);
            $t->same('missing paired .native fixture', $report['fixtureComparisons'][0]['nativeFixtureError']);
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
                . ' --require-executable-parity=1'
                . ' --require-pandoc-version=' . escapeshellarg('pandoc fake 1.0');
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
            $t->same(1, count($decoded['fixtureComparisons']));
            $t->same('matched', $decoded['fixtureComparisons'][0]['status']);
            $t->same(true, PptxExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($decoded, 1));
            $t->same(true, PptxExecutableNativeAstComparisonHarness::hasRequiredPandocVersion($decoded, 'pandoc fake 1.0'));

            $missingCommand = str_replace('--pandoc-bin=' . escapeshellarg($fakePandoc), '--pandoc-bin=' . escapeshellarg($root . '/missing'), $command) . ' 2>/dev/null';
            $missingOutput = [];
            $missingExitCode = 0;
            exec($missingCommand, $missingOutput, $missingExitCode);

            $t->same(1, $missingExitCode);

            $versionMismatchCommand = str_replace(
                '--require-pandoc-version=' . escapeshellarg('pandoc fake 1.0'),
                '--require-pandoc-version=' . escapeshellarg('pandoc fake 2.0'),
                $command
            ) . ' 2>/dev/null';
            $versionMismatchOutput = [];
            $versionMismatchExitCode = 0;
            exec($versionMismatchCommand, $versionMismatchOutput, $versionMismatchExitCode);

            $t->same(1, $versionMismatchExitCode);
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
                . ' --require-executable-parity=101';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same(dirname(__DIR__, 3) . '/lanes/pandoc/fixtures/upstream-current-pptx-reader', $decoded['pptxDirectory']);
            $t->same('pandoc fake checked-in 1.0', $decoded['pandocVersion']);
            $t->same(101, $decoded['comparedPptxCount']);
            $t->same(101, $decoded['localParsedCount']);
            $t->same(101, $decoded['pandocParsedCount']);
            $t->same(101, $decoded['nativeFixtureParsedCount']);
            $t->same(101, $decoded['bothParsedCount']);
            $t->same(101, $decoded['normalizedAstMatchCount']);
            $t->same(0, $decoded['normalizedAstMismatchCount']);
            $t->same(101, $decoded['pandocNativeFixtureComparedCount']);
            $t->same(101, $decoded['pandocNativeFixtureMatchCount']);
            $t->same(0, $decoded['pandocNativeFixtureMismatchCount']);
            $t->same(101, count($decoded['fixtureComparisons']));
            $t->same([], array_values(array_filter(
                $decoded['fixtureComparisons'],
                static fn (array $row): bool => ($row['status'] ?? null) !== 'matched'
            )));
            $t->same(true, PptxExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($decoded, 101));

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
