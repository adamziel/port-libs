<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxNativeAstComparisonHarness;

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
];
