<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\DocxNativeAstComparisonHarness;
use PortLibs\Pandoc\NativeWriter;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-docx-native-ast-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary AST directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary AST directory {$base}");
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

$writeDocx = static function (string $path, string $text): void {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Unable to create DOCX fixture {$path}");
    }

    $escaped = htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('word/document.xml', <<<XML
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>{$escaped}</w:t></w:r></w:p></w:body>
</w:document>
XML);
    $zip->close();
};

$writeDocxDocument = static function (string $path, string $documentXml): void {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Unable to create DOCX fixture {$path}");
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('word/document.xml', $documentXml);
    $zip->close();
};

$writeDocxParts = static function (string $path, array $parts): void {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Unable to create DOCX fixture {$path}");
    }

    foreach ($parts as $name => $data) {
        $zip->addFromString((string) $name, (string) $data);
    }

    $zip->close();
};

return [
    'skips docx native ast comparison when cache is absent' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        $missing = $root . '/missing/test/docx';

        try {
            $harness = new DocxNativeAstComparisonHarness();
            $report = $harness->run($missing);
            $text = $harness->formatReport($report);

            $t->same(1, $report['schemaVersion']);
            $t->same('pandoc-docx-native-ast', $report['tool']);
            $t->same('skipped', $report['status']);
            $t->same(true, $report['skipped']);
            $t->same('upstream-cache-missing', $report['reason']);
            $t->same('normalized-ast-comparison-not-full-docx-parity', $report['verdict']);
            $t->same('docx-native-normalized-ast-comparison', $report['evidenceKind']);
            $t->same(0, $report['comparedPairCount']);
            $t->same(0, $report['normalizedAstMatchCount']);
            $t->same(0, $report['normalizedAstMismatchCount']);
            $t->same('not-evaluated-source-directory-unavailable', $report['astParityStatus']);
            $t->same('normalized-docx-native-ast-equality', $report['orderedRemainingGaps'][0]['id']);
            $t->same('not-evaluated', $report['orderedRemainingGaps'][0]['status']);
            $t->contains('Pandoc DOCX/native AST comparison: skipped', $text);
            $t->contains('orderedRemainingGaps:', $text);
        } finally {
            $removeTree($root);
        }
    },
    'reports normalized ast matches and mismatches without claiming full parity' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeDocx): void {
        $root = $makeTempDir();

        try {
            $writeDocx($root . '/same.docx', 'Hello world');
            file_put_contents($root . '/same.native', '[Para [Str "Hello",Space,Str "world"]]');

            $writeDocx($root . '/different.docx', 'Hello docx');
            file_put_contents($root . '/different.native', '[Para [Str "Hello native"]]');

            $harness = new DocxNativeAstComparisonHarness();
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
            $t->contains('root.children.0.children.0.attrs.text value', $report['mismatchComparisons'][0]['firstDifference']);
            $t->true(in_array('scalar-value', $categoryNames, true));
            $t->same('open', $report['orderedRemainingGaps'][0]['status']);
            $t->contains('normalizedAst: matches=1 (50.00%) mismatches=1 status=normalized-ast-mismatches-observed', $text);
            $t->contains('mismatchExamples:', $text);
            $t->contains('1. normalized-docx-native-ast-equality [open]', $text);
        } finally {
            $removeTree($root);
        }
    },
    'reports normalized ast equality separately from runner and writer parity' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeDocx): void {
        $root = $makeTempDir();

        try {
            $writeDocx($root . '/same.docx', 'Exact body');
            file_put_contents($root . '/same.native', '[Para [Str "Exact",Space,Str "body"]]');

            $report = (new DocxNativeAstComparisonHarness())->run($root);

            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(0, $report['normalizedAstMismatchCount']);
            $t->same('normalized-ast-equality-observed-not-runner-or-writer-parity', $report['astParityStatus']);
            $t->same('covered-by-current-normalized-ast-evidence', $report['orderedRemainingGaps'][0]['status']);
            $t->same('upstream-docx-runner-results', $report['orderedRemainingGaps'][1]['id']);
            $t->same('open', $report['orderedRemainingGaps'][1]['status']);
            $t->same('writer-golden-docx-package-parity', $report['orderedRemainingGaps'][2]['id']);
            $t->same('open', $report['orderedRemainingGaps'][2]['status']);
            $t->true(in_array('document-level metadata added by local DOCX package parsing', $report['normalizationPolicy']['excludes'], true));
            $t->true(in_array('reader-specific adjacent Str/Space text-node segmentation', $report['normalizationPolicy']['excludes'], true));
        } finally {
            $removeTree($root);
        }
    },
    'normalizes docx provenance wrappers and bookmark markers in ast comparisons' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeDocxDocument): void {
        $root = $makeTempDir();

        try {
            $writeDocxDocument($root . '/same.docx', <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:bookmarkStart w:id="1" w:name="_TocLocal"/>
      <w:r><w:t xml:space="preserve">Last update: </w:t></w:r>
      <w:fldSimple w:instr=' SAVEDATE \@ "MMMM d, yyyy" \* MERGEFORMAT '>
        <w:r><w:t>May 1, 2017</w:t></w:r>
      </w:fldSimple>
      <w:bookmarkEnd w:id="1"/>
    </w:p>
    <w:p>
      <w:sdt>
        <w:sdtPr><w:alias w:val="Reviewer"/><w:id w:val="7"/></w:sdtPr>
        <w:sdtContent><w:r><w:t>Controlled text</w:t></w:r></w:sdtContent>
      </w:sdt>
    </w:p>
  </w:body>
</w:document>
XML);
            file_put_contents($root . '/same.native', '[Para [Str "Last",Space,Str "update:",Space,Str "May",Space,Str "1,",Space,Str "2017"],Para [Str "Controlled",Space,Str "text"]]');

            $report = (new DocxNativeAstComparisonHarness())->run($root);

            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(0, $report['normalizedAstMismatchCount']);
            $t->true(in_array('local DOCX raw bookmark markers and visible field/content-control provenance wrappers', $report['normalizationPolicy']['excludes'], true));
            $t->true(in_array('DOCX tab separator encoding when upstream native exposes equivalent spacing', $report['normalizationPolicy']['excludes'], true));
        } finally {
            $removeTree($root);
        }
    },
    'compares docx field links and note references without local attribute drift' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeDocxParts): void {
        $root = $makeTempDir();

        try {
            $writeDocxParts($root . '/same.docx', [
                '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/></Types>',
                'word/document.xml' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">See </w:t></w:r>
      <w:fldSimple w:instr=" PAGEREF TargetAnchor \h "><w:r><w:t>2</w:t></w:r></w:fldSimple>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <w:fldSimple w:instr=' HYPERLINK "https://example.test/source" '><w:r><w:t>source</w:t></w:r></w:fldSimple>
      <w:r><w:t xml:space="preserve"> with note</w:t></w:r>
      <w:r><w:footnoteReference w:id="1"/></w:r>
    </w:p>
    <w:p><w:bookmarkStart w:id="9" w:name="TargetAnchor"/><w:r><w:t>Target paragraph</w:t></w:r><w:bookmarkEnd w:id="9"/></w:p>
  </w:body>
</w:document>
XML,
                'word/footnotes.xml' => <<<'XML'
<?xml version="1.0"?>
<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:footnote w:id="1">
    <w:p><w:r><w:t xml:space="preserve">Foot </w:t></w:r><w:hyperlink w:anchor="TargetAnchor"><w:r><w:t>jump</w:t></w:r></w:hyperlink></w:p>
  </w:footnote>
</w:footnotes>
XML,
            ]);
            file_put_contents(
                $root . '/same.native',
                '[Para [Str "See",Space,Link ("",[],[]) [Str "2"] ("#TargetAnchor",""),Space,Str "and",Space,Link ("",[],[]) [Str "source"] ("https://example.test/source",""),Space,Str "with",Space,Str "note",Note [Para [Str "Foot",Space,Link ("",[],[]) [Str "jump"] ("#TargetAnchor","")]]],Para [Span ("TargetAnchor",["anchor"],[]) [],Str "Target",Space,Str "paragraph"]]'
            );
            $report = (new DocxNativeAstComparisonHarness())->run($root);

            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(0, $report['normalizedAstMismatchCount']);
        } finally {
            $removeTree($root);
        }
    },
    'normalizes docx provenance wrappers inside ast list attributes' => static function (TestRunner $t): void {
        $harness = new DocxNativeAstComparisonHarness();
        $method = new ReflectionMethod(DocxNativeAstComparisonHarness::class, 'normalizedNode');

        $docxFigure = new AstNode('figure', [
            'captionInlines' => [
                new AstNode('span', [
                    'classes' => ['docx-field'],
                    'attributes' => ['data-docx-field-instruction' => 'SEQ Figure'],
                ], [
                    new AstNode('text', ['text' => '1']),
                ]),
                new AstNode('text', ['text' => ' Caption']),
            ],
        ]);
        $nativeFigure = new AstNode('figure', [
            'captionInlines' => [
                new AstNode('text', ['text' => '1']),
                new AstNode('text', ['text' => ' ']),
                new AstNode('text', ['text' => 'Caption']),
            ],
        ]);

        $normalizedDocx = $method->invoke($harness, $docxFigure);
        $normalizedNative = $method->invoke($harness, $nativeFigure);

        $t->same($normalizedNative, $normalizedDocx);
        $t->same([
            [
                'type' => 'text',
                'attrs' => ['text' => '1 Caption'],
                'children' => [],
            ],
        ], $normalizedDocx['attrs']['captionInlines']);
    },
    'normalizes docx table provenance defaults and mirrored image dimensions in ast comparisons' => static function (TestRunner $t): void {
        $harness = new DocxNativeAstComparisonHarness();
        $method = new ReflectionMethod(DocxNativeAstComparisonHarness::class, 'normalizedNode');

        $table = new AstNode('table', [
            'alignments' => ['default'],
            'htmlAttributes' => [
                'data-docx-table-style' => 'DerivedTable',
                'data-docx-table-style-name' => 'Derived Table',
            ],
            'widths' => [0.33333333333333331],
        ], [
            new AstNode('table_body', ['rowHeadColumns' => 0], [
                new AstNode('table_row', [], [
                    new AstNode('table_cell', [
                        'text' => 'Ready',
                        'colspan' => 1,
                        'rowspan' => 1,
                        'htmlAttributes' => ['data-docx-vmerge' => 'restart'],
                    ], [
                        new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Ready'])]),
                    ]),
                ]),
            ]),
        ]);
        $image = new AstNode('image', [
            'url' => 'media/image1.png',
            'title' => '',
            'alt' => 'Chart',
            'width' => '2in',
            'height' => '1in',
            'attributes' => [
                'width' => '2in',
                'height' => '1in',
                'data-docx-image-relationship-id' => 'rId7',
            ],
        ], [new AstNode('text', ['text' => 'Chart'])]);

        $normalizedTable = $method->invoke($harness, $table);
        $normalizedImage = $method->invoke($harness, $image);

        $t->same([
            'alignments' => ['default'],
            'widths' => [0.333333333333],
        ], $normalizedTable['attrs']);
        $t->same([], $normalizedTable['children'][0]['attrs']);
        $t->same([], $normalizedTable['children'][0]['children'][0]['children'][0]['attrs']);
        $t->same([
            'alt' => 'Chart',
            'attributes' => ['height' => '1in', 'width' => '2in'],
            'title' => '',
            'url' => 'media/image1.png',
        ], $normalizedImage['attrs']);
    },
    'matches one row docx table against native table shape after focused normalization' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeDocxDocument): void {
        $root = $makeTempDir();

        try {
            $writeDocxDocument($root . '/table_one_row.docx', <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:tbl>
      <w:tblGrid><w:gridCol w:w="3000"/></w:tblGrid>
      <w:tr><w:tc><w:p><w:r><w:t>Ready</w:t></w:r></w:p></w:tc></w:tr>
    </w:tbl>
  </w:body>
</w:document>
XML);
            file_put_contents($root . '/table_one_row.native', <<<'NATIVE'
[ Table ( "" , [  ] , [  ] ) (Caption Nothing []) [ ( AlignDefault , ColWidth 0.320512820512821 ) ] (TableHead ( "" , [  ] , [  ] ) [  ]) [ TableBody ( "" , [  ] , [  ] ) (RowHeadColumns 0) [  ] [ Row ( "" , [  ] , [  ] ) [ Cell ( "" , [  ] , [  ] ) AlignDefault (RowSpan 1) (ColSpan 1) [ Plain [ Str "Ready" ] ] ] ] ] (TableFoot ( "" , [  ] , [  ] ) [  ]) ]
NATIVE);

            $report = (new DocxNativeAstComparisonHarness())->run($root);

            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(0, $report['normalizedAstMismatchCount']);
            $t->same('normalized-ast-equality-observed-not-runner-or-writer-parity', $report['astParityStatus']);
            $t->true(in_array('DOCX data-docx-* provenance attributes retained for local writer diagnostics', $report['normalizationPolicy']['excludes'], true));
            $t->true(in_array('default table cell spans and row-head counts omitted by native Attr tuples', $report['normalizationPolicy']['excludes'], true));
            $t->true(in_array('floating-point serialization noise in table column width fractions', $report['normalizationPolicy']['excludes'], true));
        } finally {
            $removeTree($root);
        }
    },
    'normalizes docx table cell derived metadata in ast comparisons' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeDocxDocument): void {
        $root = $makeTempDir();

        try {
            $writeDocxDocument($root . '/same.docx', <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:tbl>
      <w:tblPr>
        <w:tblLook w:firstRow="1"/>
      </w:tblPr>
      <w:tr>
        <w:tc>
          <w:tcPr><w:tcW w:w="2400" w:type="dxa"/><w:shd w:fill="D9EAF7"/></w:tcPr>
          <w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>Head</w:t></w:r></w:p>
        </w:tc>
        <w:tc>
          <w:tcPr><w:tcW w:w="2400" w:type="dxa"/><w:shd w:fill="D9EAF7"/></w:tcPr>
          <w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>Value</w:t></w:r></w:p>
        </w:tc>
      </w:tr>
      <w:tr>
        <w:tc><w:p><w:r><w:t>North</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>12</w:t></w:r></w:p></w:tc>
      </w:tr>
    </w:tbl>
  </w:body>
</w:document>
XML);
            $plain = static fn (string $text): AstNode => new AstNode('plain', [], [new AstNode('text', ['text' => $text])]);
            $cell = static fn (string $text, array $attrs = []): AstNode => new AstNode('table_cell', $attrs, [$plain($text)]);
            $row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
            $expected = new AstNode('document', [], [
                new AstNode('table', ['alignments' => ['default', 'default'], 'widths' => [0.0, 0.0]], [
                    new AstNode('table_head', [], [
                        $row([
                            $cell('Head', ['align' => 'center']),
                            $cell('Value', ['align' => 'center']),
                        ]),
                    ]),
                    new AstNode('table_body', ['rowHeadColumns' => 0], [
                        $row([
                            $cell('North'),
                            $cell('12'),
                        ]),
                    ]),
                    new AstNode('table_foot'),
                ]),
            ]);
            file_put_contents($root . '/same.native', (new NativeWriter())->write($expected));

            $report = (new DocxNativeAstComparisonHarness())->run($root);

            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(0, $report['normalizedAstMismatchCount']);
            $t->true(in_array('derived text attrs on plain, paragraph, heading, and table_cell nodes', $report['normalizationPolicy']['excludes'], true));
            $t->true(in_array('default table cell spans and row-head counts omitted by native Attr tuples', $report['normalizationPolicy']['excludes'], true));
        } finally {
            $removeTree($root);
        }
    },
];
