<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PptxNativeAstComparisonHarness;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-pptx-native-ast-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary PPTX AST directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary PPTX AST directory {$base}");
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

$writePptx = static function (string $path, string $title, string $body): void {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Unable to create PPTX fixture {$path}");
    }

    $escapedTitle = htmlspecialchars($title, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    $escapedBody = htmlspecialchars($body, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/presentation.xml', <<<'XML'
<?xml version="1.0"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldSz cx="9144000" cy="6858000"/>
  <p:sldIdLst><p:sldId r:id="rId1"/></p:sldIdLst>
</p:presentation>
XML);
    $zip->addFromString('ppt/_rels/presentation.xml.rels', <<<'XML'
<?xml version="1.0"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide1.xml"/>
</Relationships>
XML);
    $zip->addFromString('ppt/slides/slide1.xml', <<<XML
<?xml version="1.0"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <p:cSld><p:spTree>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="2" name="Title"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>{$escapedTitle}</a:t></a:r></a:p></p:txBody>
    </p:sp>
    <p:sp>
      <p:nvSpPr><p:cNvPr id="3" name="Body"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>
      <p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>{$escapedBody}</a:t></a:r></a:p></p:txBody>
    </p:sp>
  </p:spTree></p:cSld>
</p:sld>
XML);
    $zip->close();
};

return [
    'skips pptx native ast comparison when cache is absent' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        $missing = $root . '/missing/test/pptx-reader';

        try {
            $harness = new PptxNativeAstComparisonHarness();
            $report = $harness->run($missing);
            $text = $harness->formatReport($report);

            $t->same(1, $report['schemaVersion']);
            $t->same('pandoc-pptx-native-ast', $report['tool']);
            $t->same('skipped', $report['status']);
            $t->same(true, $report['skipped']);
            $t->same('upstream-cache-missing', $report['reason']);
            $t->same('normalized-ast-comparison-not-full-pptx-parity', $report['verdict']);
            $t->same('pptx-native-normalized-ast-comparison', $report['evidenceKind']);
            $t->same(0, $report['comparedPairCount']);
            $t->same(0, $report['normalizedAstMatchCount']);
            $t->same(0, $report['normalizedAstMismatchCount']);
            $t->same([], $report['fixtureComparisons']);
            $t->same('not-evaluated-source-directory-unavailable', $report['astParityStatus']);
            $t->same('normalized-pptx-native-ast-equality', $report['orderedRemainingGaps'][0]['id']);
            $t->same('not-evaluated', $report['orderedRemainingGaps'][0]['status']);
            $t->contains('Pandoc PPTX/native AST comparison: skipped', $text);
            $t->contains('orderedRemainingGaps:', $text);
        } finally {
            $removeTree($root);
        }
    },
    'reports pptx normalized ast matches and mismatches without claiming full parity' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writePptx): void {
        $root = $makeTempDir();

        try {
            $writePptx($root . '/same.pptx', 'Exact deck', 'Hello body');
            file_put_contents($root . '/same.native', '[Header 2 ("slide-1",[],[]) [Str "Exact",Space,Str "deck"],Para [Str "Hello",Space,Str "body"]]');

            $writePptx($root . '/different.pptx', 'Exact deck', 'Hello body');
            file_put_contents($root . '/different.native', '[Header 2 ("slide-1",[],[]) [Str "Exact",Space,Str "deck"],Para [Str "Hello",Space,Str "native"]]');

            $harness = new PptxNativeAstComparisonHarness();
            $report = $harness->run($root);
            $text = $harness->formatReport($report);
            $categoryNames = array_map(
                static fn (array $category): string => (string) $category['category'],
                $report['mismatchCategories']
            );

            $t->same('completed', $report['status']);
            $t->same(false, $report['skipped']);
            $t->same(2, $report['totalPairCount']);
            $t->same(2, $report['comparedPairCount']);
            $t->same(2, $report['bothParsedCount']);
            $t->same(0, $report['parseFailureCount']);
            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(1, $report['normalizedAstMismatchCount']);
            $t->same(50.0, $report['normalizedAstMatchPercent']);
            $t->same('normalized-ast-mismatches-observed', $report['astParityStatus']);
            $t->same('different', $report['mismatchComparisons'][0]['fixture']);
            $t->contains('root.children.1.children.0.attrs.text value', $report['mismatchComparisons'][0]['firstDifference']);
            $t->same(2, count($report['fixtureComparisons']));
            $t->same('different', $report['fixtureComparisons'][0]['fixture']);
            $t->same('mismatched', $report['fixtureComparisons'][0]['status']);
            $t->same(false, $report['fixtureComparisons'][0]['normalizedAstMatched']);
            $t->same('same', $report['fixtureComparisons'][1]['fixture']);
            $t->same('matched', $report['fixtureComparisons'][1]['status']);
            $t->same(true, $report['fixtureComparisons'][1]['normalizedAstMatched']);
            $t->true(in_array('scalar-value', $categoryNames, true));
            $t->same('open', $report['orderedRemainingGaps'][0]['status']);
            $t->contains('normalizedAst: matches=1 (50.00%) mismatches=1 status=normalized-ast-mismatches-observed', $text);
            $t->contains('fixtureComparisons: rows=2', $text);
            $t->contains('mismatchExamples:', $text);
            $t->contains('1. normalized-pptx-native-ast-equality [open]', $text);
        } finally {
            $removeTree($root);
        }
    },
    'reports pptx normalized ast equality separately from upstream runner parity' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writePptx): void {
        $root = $makeTempDir();

        try {
            $writePptx($root . '/same.pptx', 'Exact deck', 'Hello body');
            file_put_contents($root . '/same.native', '[Header 2 ("slide-1",[],[]) [Str "Exact",Space,Str "deck"],Para [Str "Hello",Space,Str "body"]]');

            $report = (new PptxNativeAstComparisonHarness())->run($root);

            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(0, $report['normalizedAstMismatchCount']);
            $t->same(1, count($report['fixtureComparisons']));
            $t->same('matched', $report['fixtureComparisons'][0]['status']);
            $t->same('normalized-ast-equality-observed-not-runner-parity', $report['astParityStatus']);
            $t->same('covered-by-current-normalized-ast-evidence', $report['orderedRemainingGaps'][0]['status']);
            $t->same('upstream-pptx-reader-runner-results', $report['orderedRemainingGaps'][1]['id']);
            $t->same('open', $report['orderedRemainingGaps'][1]['status']);
            $t->same('upstream-pptx-fixture-corpus-coverage', $report['orderedRemainingGaps'][2]['id']);
            $t->same('open', $report['orderedRemainingGaps'][2]['status']);
            $t->true(in_array('local PPTX package review attrs', $report['normalizationPolicy']['excludes'], true));
            $t->true(in_array('reader-specific adjacent Str/Space text-node segmentation', $report['normalizationPolicy']['excludes'], true));
        } finally {
            $removeTree($root);
        }
    },
    'matches checked-in current pptx reader fixture pairs through normalized ast harness' => static function (TestRunner $t): void {
        $fixtureDir = dirname(__DIR__) . '/fixtures/upstream-current-pptx-reader';
        $report = (new PptxNativeAstComparisonHarness())->run($fixtureDir);

        $t->same('completed', $report['status']);
            $t->same(53, $report['totalPairCount']);
            $t->same(53, $report['comparedPairCount']);
            $t->same(53, $report['pptxParsedCount']);
            $t->same(53, $report['nativeParsedCount']);
            $t->same(53, $report['bothParsedCount']);
            $t->same(0, $report['parseFailureCount']);
            $t->same(53, $report['normalizedAstMatchCount']);
            $t->same(0, $report['normalizedAstMismatchCount']);
            $t->same(53, count($report['fixtureComparisons']));
            $t->same([], array_values(array_filter(
                $report['fixtureComparisons'],
                static fn (array $row): bool => ($row['status'] ?? null) !== 'matched'
            )));
            $t->same(true, PptxNativeAstComparisonHarness::hasRequiredMappedParity($report, 53));
    },
    'cli gates required mapped pptx parity from checked-in fixture selector' => static function (TestRunner $t): void {
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-pptx-native-ast.php')
            . ' --checked-in-fixtures'
            . ' --json'
            . ' summary'
                . ' --require-mapped-parity=53';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same('completed', $decoded['status']);
        $t->same(dirname(__DIR__, 3) . '/lanes/pandoc/fixtures/upstream-current-pptx-reader', $decoded['upstreamPptxDirectory']);
            $t->same(53, $decoded['normalizedAstMatchCount']);
            $t->same(0, $decoded['normalizedAstMismatchCount']);
            $t->same(53, count($decoded['fixtureComparisons']));
            $t->same('matched', $decoded['fixtureComparisons'][0]['status']);
            $t->same(true, PptxNativeAstComparisonHarness::hasRequiredMappedParity($decoded, 53));
    },
    'cli required mapped pptx parity fails on skipped and mismatched evidence' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writePptx): void {
        $missingRoot = $makeTempDir();
        $missing = $missingRoot . '/missing/test/pptx-reader';
        try {
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-pptx-native-ast.php')
                . ' --upstream-pptx-dir='
                . escapeshellarg($missing)
                . ' --json'
                . ' summary'
                . ' --require-mapped-parity=1';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(1, $exitCode);
            $t->same('skipped', $decoded['status']);
            $t->same(true, $decoded['skipped']);
            $t->same(false, PptxNativeAstComparisonHarness::hasRequiredMappedParity($decoded, 1));
        } finally {
            $removeTree($missingRoot);
        }

        $mismatchRoot = $makeTempDir();
        try {
            $writePptx($mismatchRoot . '/different.pptx', 'Exact deck', 'Hello body');
            file_put_contents($mismatchRoot . '/different.native', '[Header 2 ("slide-1",[],[]) [Str "Exact",Space,Str "deck"],Para [Str "Hello",Space,Str "native"]]');

            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-pptx-native-ast.php')
                . ' --upstream-pptx-dir='
                . escapeshellarg($mismatchRoot)
                . ' --json'
                . ' summary'
                . ' --require-mapped-parity=1';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(1, $exitCode);
            $t->same('completed', $decoded['status']);
            $t->same(1, $decoded['comparedPairCount']);
            $t->same(0, $decoded['normalizedAstMatchCount']);
            $t->same(1, $decoded['normalizedAstMismatchCount']);
            $t->same(false, PptxNativeAstComparisonHarness::hasRequiredMappedParity($decoded, 1));
        } finally {
            $removeTree($mismatchRoot);
        }
    },
    'normalizes pptx review provenance without hiding semantic attrs' => static function (TestRunner $t): void {
        $harness = new PptxNativeAstComparisonHarness();
        $method = new ReflectionMethod(PptxNativeAstComparisonHarness::class, 'normalizedNode');

        $pptxDiv = new AstNode('div', [
            'classes' => ['smartart', 'chevron2'],
            'attributes' => ['layout' => 'chevron2'],
            'pptxShape' => ['element' => 'graphicFrame', 'id' => '4'],
        ], [
            new AstNode('paragraph', [
                'text' => 'First',
                'pptxShape' => ['element' => 'sp'],
            ], [
                new AstNode('text', ['text' => 'First']),
            ]),
        ]);
        $nativeDiv = new AstNode('div', [
            'classes' => ['smartart', 'chevron2'],
            'attributes' => ['layout' => 'chevron2'],
        ], [
            new AstNode('paragraph', ['text' => 'First'], [
                new AstNode('text', ['text' => 'First']),
            ]),
        ]);

        $normalizedPptx = $method->invoke($harness, $pptxDiv);
        $normalizedNative = $method->invoke($harness, $nativeDiv);

        $t->same($normalizedNative, $normalizedPptx);
        $t->same(['layout' => 'chevron2'], $normalizedPptx['attrs']['attributes']);
        $t->same(['smartart', 'chevron2'], $normalizedPptx['attrs']['classes']);
    },
    'normalizes pptx image alt inline whitespace through semantic image attrs' => static function (TestRunner $t): void {
        $harness = new PptxNativeAstComparisonHarness();
        $method = new ReflectionMethod(PptxNativeAstComparisonHarness::class, 'normalizedNode');

        $pptxImage = new AstNode('image', [
            'url' => 'ppt/media/image1.png',
            'title' => 'Picture 1',
            'alt' => 'Image title image.png',
            'relationshipId' => 'rId2',
        ], [
            new AstNode('text', ['text' => 'Image title image.png']),
        ]);
        $nativeImage = new AstNode('image', [
            'url' => 'ppt/media/image1.png',
            'title' => 'Picture 1',
            'alt' => 'Image title image.png',
        ], [
            new AstNode('text', ['text' => "Image title\n\nimage.png"]),
        ]);

        $normalizedPptx = $method->invoke($harness, $pptxImage);
        $normalizedNative = $method->invoke($harness, $nativeImage);

        $t->same($normalizedNative, $normalizedPptx);
        $t->same('Image title image.png', $normalizedPptx['attrs']['alt']);
        $t->same([[
            'type' => 'text',
            'attrs' => ['text' => 'Image title image.png'],
            'children' => [],
        ]], $normalizedPptx['children']);
    },
];
