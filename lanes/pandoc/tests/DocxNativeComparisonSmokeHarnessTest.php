<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxNativeComparisonSmokeHarness;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-docx-native-smoke-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary smoke directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary smoke directory {$base}");
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

/**
 * @param array<string, string> $parts
 */
$writeDocxParts = static function (string $path, array $parts): void {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Unable to create DOCX fixture {$path}");
    }

    foreach ($parts as $name => $contents) {
        $zip->addFromString($name, $contents);
    }
    $zip->close();
};

$writeDocxDocumentXml = static function (string $path, string $documentXml) use ($writeDocxParts): void {
    $writeDocxParts($path, [
        '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
        'word/document.xml' => $documentXml,
    ]);
};

return [
    'skips upstream docx native comparison when cache is absent' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        $missing = $root . '/missing/test/docx';

        try {
            $harness = new DocxNativeComparisonSmokeHarness();
            $report = $harness->run($missing);
            $text = $harness->formatReport($report);

            $t->same(1, $report['schemaVersion']);
            $t->same('pandoc-docx-native-smoke', $report['tool']);
            $t->same('skipped', $report['status']);
            $t->same(true, $report['skipped']);
            $t->same('upstream-cache-missing', $report['reason']);
            $t->same('smoke-only-not-full-docx-parity', $report['verdict']);
            $t->same('docx-native-reader-smoke-comparison', $report['evidenceKind']);
            $t->same(0, $report['totalPairCount']);
            $t->same(0, $report['comparedPairCount']);
            $t->same(0, $report['parseFailureCount']);
            $t->same(null, $report['sameTextPercent']);
            $t->same(null, $report['sameTopTypeSequencePercent']);
            $t->same('not-evaluated-source-directory-unavailable', $report['semanticParityStatus']);
            $t->same([], $report['semanticGapComparisons']);
            $t->same('upstream-docx-runner-results', $report['orderedRemainingGaps'][0]['id']);
            $t->same('not-evaluated', $report['orderedRemainingGaps'][3]['status']);
            $t->contains('Pandoc DOCX/native smoke: skipped', $text);
            $t->contains('Verdict: smoke-only-not-full-docx-parity', $text);
            $t->contains('orderedRemainingGaps:', $text);
            $t->contains('4. semantic-gap-zero-tolerance [not-evaluated]', $text);
        } finally {
            $removeTree($root);
        }
    },
    'keeps field bookmark smoke fixtures out of semantic gap examples' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeDocxDocumentXml): void {
        $root = $makeTempDir();

        try {
            $writeDocxDocumentXml($root . '/empty_field.docx', <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t xml:space="preserve">Index marker </w:t></w:r><w:fldSimple w:instr=" XE &quot;French&quot; "/><w:r><w:t>after</w:t></w:r></w:p>
  </w:body>
</w:document>
XML);
            file_put_contents($root . '/empty_field.native', '[Para [Str "Index",Space,Str "marker",Space,Span ( "" , [ "indexref" ] , [ ( "entry" , "French" ) ] ) [],Str "after"]]');

            $writeDocxDocumentXml($root . '/pageref.docx', <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t xml:space="preserve">Page </w:t></w:r><w:fldSimple w:instr=" PAGEREF TargetAnchor \h "><w:r><w:t>7</w:t></w:r></w:fldSimple></w:p>
    <w:p><w:bookmarkStart w:id="9" w:name="TargetAnchor"/><w:r><w:t>Target paragraph</w:t></w:r><w:bookmarkEnd w:id="9"/></w:p>
  </w:body>
</w:document>
XML);
            file_put_contents($root . '/pageref.native', '[Para [Str "Page",Space,Str "7"],Para [Str "Target",Space,Str "paragraph"]]');

            $writeDocxDocumentXml($root . '/unused_anchors.docx', <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:bookmarkStart w:id="11" w:name="UnusedAnchor"/><w:r><w:t>Unused anchor text</w:t></w:r><w:bookmarkEnd w:id="11"/></w:p>
  </w:body>
</w:document>
XML);
            file_put_contents($root . '/unused_anchors.native', '[Para [Str "Unused",Space,Str "anchor",Space,Str "text"]]');

            $report = (new DocxNativeComparisonSmokeHarness())->run($root);

            $t->same('completed', $report['status']);
            $t->same(3, $report['totalPairCount']);
            $t->same(3, $report['comparedPairCount']);
            $t->same(3, $report['bothParsedCount']);
            $t->same(0, $report['parseFailureCount']);
            $t->same(3, $report['sameTextCount']);
            $t->same(3, $report['sameTopTypeSequenceCount']);
            $t->same(100.0, $report['sameTextPercent']);
            $t->same(100.0, $report['sameTopTypeSequencePercent']);
            $t->same(0, $report['semanticGapPairCount']);
            $t->same('smoke-text-and-top-types-match-not-full-parity', $report['semanticParityStatus']);
            $t->same([], $report['knownSemanticGapCategories']);
            $t->same([], $report['semanticGapComparisons']);
            $t->same('not-observed-in-smoke', $report['orderedRemainingGaps'][3]['status']);
        } finally {
            $removeTree($root);
        }
    },
    'reports paired docx native smoke counts and semantic gap categories' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeDocx): void {
        $root = $makeTempDir();

        try {
            $writeDocx($root . '/same.docx', 'Hello world');
            file_put_contents($root . '/same.native', '[Para [Str "Hello",Space,Str "world"]]');

            $writeDocx($root . '/raw-bookmark.docx', '<w:bookmarkStart w:id="1"/> Hello');
            file_put_contents($root . '/raw-bookmark.native', '[Para [Str "Hello"]]');

            $harness = new DocxNativeComparisonSmokeHarness();
            $report = $harness->run($root);
            $categoryNames = array_map(
                static fn (array $category): string => (string) $category['category'],
                $report['knownSemanticGapCategories']
            );

            $t->same('completed', $report['status']);
            $t->same(false, $report['skipped']);
            $t->same('smoke-only-not-full-docx-parity', $report['verdict']);
            $t->same('docx-native-reader-smoke-comparison', $report['evidenceKind']);
            $t->same(2, $report['docxArtifactCount']);
            $t->same(2, $report['nativeArtifactCount']);
            $t->same(2, $report['totalPairCount']);
            $t->same(2, $report['comparedPairCount']);
            $t->same(2, $report['docxParsedCount']);
            $t->same(2, $report['nativeParsedCount']);
            $t->same(2, $report['bothParsedCount']);
            $t->same(0, $report['parseFailureCount']);
            $t->same(1, $report['sameTextCount']);
            $t->same(2, $report['sameTopTypeSequenceCount']);
            $t->same(50.0, $report['sameTextPercent']);
            $t->same(100.0, $report['sameTopTypeSequencePercent']);
            $t->same(1, $report['semanticGapPairCount']);
            $t->same('semantic-gaps-observed', $report['semanticParityStatus']);
            $t->same('raw-bookmark', $report['semanticGapComparisons'][0]['fixture']);
            $t->same('full-ast-equality', $report['orderedRemainingGaps'][1]['id']);
            $t->same('open', $report['orderedRemainingGaps'][3]['status']);
            $t->true(in_array('raw-openxml-field-or-bookmark-markup', $categoryNames, true));
            $t->true(in_array('field-bookmark-cross-reference-resolution', $categoryNames, true));
            $t->true(in_array('text-normalization', $categoryNames, true));
            $text = $harness->formatReport($report);
            $t->contains('same: text=1 (50.00%) topTypeSequence=2 (100.00%) semanticGapPairs=1 semanticParityStatus=semantic-gaps-observed', $text);
            $t->contains('2. full-ast-equality [open]', $text);
        } finally {
            $removeTree($root);
        }
    },
    'keeps capped examples while reporting every semantic gap comparison' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeDocx): void {
        $root = $makeTempDir();

        try {
            foreach (['alpha', 'bravo', 'charlie'] as $fixture) {
                $writeDocx($root . '/' . $fixture . '.docx', $fixture . ' docx');
                file_put_contents($root . '/' . $fixture . '.native', '[Para [Str "' . $fixture . '",Space,Str "native"]]');
            }

            $report = (new DocxNativeComparisonSmokeHarness())->run($root, ['maxExamples' => 1]);

            $t->same(3, $report['semanticGapPairCount']);
            $t->same(1, count($report['comparisons']));
            $t->same(3, count($report['semanticGapComparisons']));
            $t->same(['alpha', 'bravo', 'charlie'], array_map(
                static fn (array $comparison): string => (string) $comparison['fixture'],
                $report['semanticGapComparisons']
            ));
        } finally {
            $removeTree($root);
        }
    },
    'clears targeted docx smoke gaps for heading zero and anchor before heading boundaries' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeDocxParts): void {
        $root = $makeTempDir();

        try {
            $writeDocxParts($root . '/0_level_headers.docx', [
                'word/document.xml' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:outlineLvl w:val="0"/></w:pPr><w:r><w:t>CONTENTS</w:t></w:r></w:p>
    <w:p><w:r><w:t>Section body</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            ]);
            file_put_contents(
                $root . '/0_level_headers.native',
                '[Header 1 ("contents",[],[]) [Str "CONTENTS"],Para [Str "Section",Space,Str "body"]]'
            );

            $writeDocxParts($root . '/anchor_header_after_anchor.docx', [
                'word/document.xml' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:hyperlink w:anchor="_RefHeading"><w:r><w:t>Jump</w:t></w:r></w:hyperlink></w:p>
    <w:p><w:bookmarkStart w:id="1" w:name="BoundaryAnchor"/><w:bookmarkEnd w:id="1"/></w:p>
    <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:bookmarkStart w:id="2" w:name="_RefHeading"/><w:r><w:t>Referenced title</w:t></w:r><w:bookmarkEnd w:id="2"/></w:p>
  </w:body>
</w:document>
XML,
            ]);
            file_put_contents(
                $root . '/anchor_header_after_anchor.native',
                '[Para [Link ("",[],[]) [Str "Jump"] ("#referenced-title","")],Para [],Header 1 ("referenced-title",[],[]) [Str "Referenced",Space,Str "title"]]'
            );

            $report = (new DocxNativeComparisonSmokeHarness())->run($root, ['maxExamples' => 5]);

            $t->same('completed', $report['status']);
            $t->same(2, $report['comparedPairCount']);
            $t->same(2, $report['sameTextCount']);
            $t->same(2, $report['sameTopTypeSequenceCount']);
            $t->same(0, $report['semanticGapPairCount']);
            $t->same([], $report['semanticGapComparisons']);
            $t->same('smoke-text-and-top-types-match-not-full-parity', $report['semanticParityStatus']);
        } finally {
            $removeTree($root);
        }
    },
];
