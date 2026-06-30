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

return [
    'skips upstream docx native comparison when cache is absent' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        $missing = $root . '/missing/test/docx';

        try {
            $harness = new DocxNativeComparisonSmokeHarness();
            $report = $harness->run($missing);

            $t->same('skipped', $report['status']);
            $t->same('upstream-cache-missing', $report['reason']);
            $t->same(0, $report['totalPairCount']);
            $t->same(0, $report['comparedPairCount']);
            $t->same(0, $report['parseFailureCount']);
            $t->contains('Pandoc DOCX/native smoke: skipped', $harness->formatReport($report));
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
            $t->same(1, $report['semanticGapPairCount']);
            $t->true(in_array('raw-openxml-field-or-bookmark-markup', $categoryNames, true));
            $t->true(in_array('field-bookmark-cross-reference-resolution', $categoryNames, true));
            $t->true(in_array('text-normalization', $categoryNames, true));
            $t->contains('same: text=1 topTypeSequence=2 semanticGapPairs=1', $harness->formatReport($report));
        } finally {
            $removeTree($root);
        }
    },
];
