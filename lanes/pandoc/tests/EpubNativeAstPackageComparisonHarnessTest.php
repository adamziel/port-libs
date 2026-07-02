<?php

declare(strict_types=1);

use PortLibs\Pandoc\EpubNativeAstPackageComparisonHarness;
use PortLibs\Pandoc\ZipPackage;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-epub-native-package-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary EPUB native/package directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary EPUB native/package directory {$base}");
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

$writeEpub = static function (string $path, string $title, string $body): void {
    $escapedTitle = htmlspecialchars($title, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    $escapedBody = htmlspecialchars($body, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    $bytes = ZipPackage::fromParts([
        ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
        ['name' => 'META-INF/container.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML],
        ['name' => 'EPUB/package.opf', 'data' => <<<XML
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf" xmlns:dc="http://purl.org/dc/elements/1.1/" version="3.0" unique-identifier="bookid">
  <metadata>
    <dc:identifier id="bookid">urn:uuid:{$escapedTitle}</dc:identifier>
    <dc:title>{$escapedTitle}</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML],
        ['name' => 'EPUB/nav.xhtml', 'data' => <<<HTML
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body><nav epub:type="toc"><ol><li><a href="chapter.xhtml">{$escapedTitle}</a></li></ol></nav></body>
</html>
HTML],
        ['name' => 'EPUB/chapter.xhtml', 'data' => <<<HTML
<html xmlns="http://www.w3.org/1999/xhtml">
  <body><h1>{$escapedTitle}</h1><p>{$escapedBody}</p></body>
</html>
HTML],
    ], 'epub native/package fixture')->bytes();

    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException("Unable to write EPUB fixture {$path}");
    }
};

return [
    'skips epub native package comparison when source directory is absent' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $missing = $root . '/missing';
            $report = (new EpubNativeAstPackageComparisonHarness())->run($missing);
            $text = (new EpubNativeAstPackageComparisonHarness())->formatReport($report);

            $t->same('skipped', $report['status']);
            $t->same(true, $report['skipped']);
            $t->same('upstream-cache-missing', $report['reason']);
            $t->same(0, $report['comparedEpubCount']);
            $t->same(0, $report['comparedPairCount']);
            $t->same('not-evaluated-source-directory-unavailable', $report['packageAcceptanceStatus']);
            $t->same('not-evaluated-source-directory-unavailable', $report['astParityStatus']);
            $t->same('not-evaluated', $report['orderedRemainingGaps'][0]['status']);
            $t->contains('Pandoc EPUB native/package comparison: skipped', $text);
        } finally {
            $removeTree($root);
        }
    },

    'reports epub package coverage and native ast drift separately' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeEpub): void {
        $root = $makeTempDir();
        try {
            $writeEpub($root . '/same.epub', 'Same', 'Hello body');
            file_put_contents($root . '/same.native', '[Header 1 ("same",[],[]) [Str "Same"],Para [Str "Hello",Space,Str "body"]]');
            $writeEpub($root . '/different.epub', 'Different', 'Hello body');
            file_put_contents($root . '/different.native', '[Para [Str "different"]]');
            $writeEpub($root . '/package-only.epub', 'Package Only', 'Only package coverage');

            $harness = new EpubNativeAstPackageComparisonHarness();
            $report = $harness->run($root);
            $text = $harness->formatReport($report);

            $t->same('completed', $report['status']);
            $t->same(3, $report['totalEpubCount']);
            $t->same(3, $report['comparedEpubCount']);
            $t->same(3, $report['packageParsedCount']);
            $t->same(3, $report['readerParsedCount']);
            $t->same(0, $report['packageParseFailureCount']);
            $t->same(0, $report['readerParseFailureCount']);
            $t->same('package-and-reader-acceptance-observed-not-full-epub-parity', $report['packageAcceptanceStatus']);
            $t->same(2, $report['totalPairCount']);
            $t->same(2, $report['comparedPairCount']);
            $t->same(2, $report['epubPairParsedCount']);
            $t->same(2, $report['nativeParsedCount']);
            $t->same(2, $report['bothParsedCount']);
            $t->same(0, $report['astParseFailureCount']);
            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(1, $report['normalizedAstMismatchCount']);
            $t->same('normalized-ast-mismatches-observed', $report['astParityStatus']);
            $t->same('covered-by-current-package-evidence', $report['orderedRemainingGaps'][0]['status']);
            $t->same('open', $report['orderedRemainingGaps'][1]['status']);
            $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredPackageParity($report, 3));
            $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredNativeReadiness($report, 2));
            $t->same(false, EpubNativeAstPackageComparisonHarness::hasRequiredMappedParity($report, 2));
            $t->same('different', $report['mismatchComparisons'][0]['fixture']);
            $t->contains('normalizedAst: matches=1 (50.00%) mismatches=1', $text);
            $t->contains('upstream-epub-native-ast-equality [open]', $text);
        } finally {
            $removeTree($root);
        }
    },

    'cli gates epub package and native readiness without requiring ast equality' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeEpub): void {
        $root = $makeTempDir();
        try {
            $writeEpub($root . '/same.epub', 'Same', 'Hello body');
            file_put_contents($root . '/same.native', '[Header 1 ("same",[],[]) [Str "Same"],Para [Str "Hello",Space,Str "body"]]');
            $writeEpub($root . '/different.epub', 'Different', 'Hello body');
            file_put_contents($root . '/different.native', '[Para [Str "different"]]');
            $writeEpub($root . '/package-only.epub', 'Package Only', 'Only package coverage');

            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-native-ast-package.php')
                . ' --epub-dir=' . escapeshellarg($root)
                . ' --json'
                . ' summary'
                . ' --require-package-parity=3'
                . ' --require-native-readiness=2';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same(3, $decoded['packageParsedCount']);
            $t->same(2, $decoded['nativeParsedCount']);
            $t->same(1, $decoded['normalizedAstMatchCount']);

            $strictCommand = $command . ' --require-mapped-parity=2 2>/dev/null';
            $strictOutput = [];
            $strictExitCode = 0;
            exec($strictCommand, $strictOutput, $strictExitCode);

            $t->same(1, $strictExitCode);
        } finally {
            $removeTree($root);
        }
    },
];
