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

$writeFile = static function (string $root, string $relativePath, string $contents): void {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to create fixture directory {$directory}");
    }
    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException("Unable to write fixture file {$path}");
    }
};

$writeRunnerTranscripts = static function (string $root, array $paths) use ($writeFile): array {
    $records = [];
    foreach ($paths as $path) {
        $contents = "native/package runner transcript for {$path}\n";
        $writeFile($root, $path, $contents);
        $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $records[] = [
            'path' => $path,
            'sha256' => hash_file('sha256', $absolutePath),
            'bytes' => filesize($absolutePath),
        ];
    }

    return $records;
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

$writeNavigationEvidenceEpub = static function (string $path): void {
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
        ['name' => 'EPUB/package.opf', 'data' => <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf" xmlns:dc="http://purl.org/dc/elements/1.1/" version="3.0" unique-identifier="bookid">
  <metadata>
    <dc:identifier id="bookid">urn:uuid:generated-navigation-evidence</dc:identifier>
    <dc:title>Generated Navigation Evidence</dc:title>
    <dc:creator id="creator">Package Auditor</dc:creator>
    <dc:language>en</dc:language>
    <dc:publisher>Port Libs Press</dc:publisher>
    <dc:date>2026-07-03</dc:date>
    <meta property="file-as" refines="#creator">Auditor, Package</meta>
    <link id="review-record" rel="record" href="review.json" media-type="application/json"/>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="review" href="review.json" media-type="application/json"/>
    <item id="remote-style" href="https://example.invalid/reader-package.css" media-type="text/css" properties="remote-resources"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
  <guide>
    <reference type="text" title="Start" href="chapter.xhtml"/>
  </guide>
</package>
XML],
        ['name' => 'EPUB/nav.xhtml', 'data' => <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Navigation Evidence</a></li>
      </ol>
    </nav>
    <nav epub:type="landmarks">
      <ol>
        <li><a href="chapter.xhtml">Start</a></li>
      </ol>
    </nav>
    <nav epub:type="page-list">
      <ol>
        <li><a epub:type="pagebreak" href="chapter.xhtml">1</a></li>
      </ol>
    </nav>
    <nav epub:type="loi">
      <ol>
        <li><a href="chapter.xhtml">Illustrations</a></li>
      </ol>
    </nav>
  </body>
</html>
HTML],
        ['name' => 'EPUB/chapter.xhtml', 'data' => <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body><h1>Navigation Evidence</h1><p>Reader package audit.</p></body>
</html>
HTML],
        ['name' => 'EPUB/review.json', 'data' => '{"kind":"generated-local-epub-package-evidence"}'],
    ], 'generated EPUB package evidence')->bytes();

    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException("Unable to write generated EPUB evidence fixture {$path}");
    }
};

$fixtureRoot = static fn (): string => dirname(__DIR__) . '/fixtures/upstream-current-epub-reader/epub';

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
            $t->same(0, $report['packageFeatureCoverage']['fixtureCount']);
            $t->same([], $report['packageFeatureCoverage']['navigationTypeCounts']);
            $t->same([], $report['packageFeatureCoverage']['manifestResourceKindCounts']);
            $t->same([], $report['packageFeatureCoverage']['guideReferenceTypeCounts']);
            $t->same([], $report['packageFeatureCoverage']['fixtureFeatureSignatures']);
            $t->same('not-evaluated-source-directory-unavailable', $report['currentNativeAstSignature']['validation']['status']);
            $t->same(true, EpubNativeAstPackageComparisonHarness::hasRunnerNotRunEvidence($report));
            $t->same(true, EpubNativeAstPackageComparisonHarness::hasRunnerPlanEvidence($report));
            $t->same('not-run', $report['runnerEvidence']['status']);
            $t->same(false, $report['runnerEvidence']['executed']);
            $t->same('planned-not-run', $report['runnerEvidence']['commandPlanStatus']);
            $t->same(EpubNativeAstPackageComparisonHarness::EXPECTED_UPSTREAM_COMMIT, $report['runnerEvidence']['upstreamBinding']['expectedCommit']);
            $t->same('exe:pandoc', $report['runnerEvidence']['target']['cabalTarget']);
            $t->same('native', $report['runnerEvidence']['target']['outputFormat']);
            $t->same('not-evaluated', $report['orderedRemainingGaps'][0]['status']);
            $t->contains('Pandoc EPUB native/package comparison: skipped', $text);
            $t->contains('runnerEvidence: status=not-run plan=planned-not-run executed=false', $text);
        } finally {
            $removeTree($root);
        }
    },

    'reports epub package coverage and native ast drift separately' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeEpub): void {
        $root = $makeTempDir();
        try {
            $writeEpub($root . '/same.epub', 'Same', 'Hello body');
            file_put_contents($root . '/same.native', '[Para [Span ("chapter.xhtml",[],[]) []],Header 1 ("",[],[]) [Str "Same"],Para [Str "Hello",Space,Str "body"]]');
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

    'generated epub fixture gates metadata navigation and package summary evidence separately from current snapshot' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeNavigationEvidenceEpub): void {
        $root = $makeTempDir();
        try {
            $writeNavigationEvidenceEpub($root . '/generated-navigation.epub');
            file_put_contents($root . '/generated-navigation.native', '[Para [Span ("chapter.xhtml",[],[]) []],Header 1 ("",[],[]) [Str "Navigation",Space,Str "Evidence"],Para [Str "Reader",Space,Str "package",Space,Str "audit."]]');

            $harness = new EpubNativeAstPackageComparisonHarness();
            $report = $harness->run($root);
            $summary = $report['packageSummaries'][0];
            $coverage = $report['packageFeatureCoverage'];

            $t->same('completed', $report['status']);
            $t->same(1, $report['totalEpubCount']);
            $t->same(1, $report['packageParsedCount']);
            $t->same(1, $report['readerParsedCount']);
            $t->same(1, $report['totalPairCount']);
            $t->same(1, $report['nativeParsedCount']);
            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(0, $report['normalizedAstMismatchCount']);
            $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredPackageParity($report, 1));
            $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredNativeReadiness($report, 1));
            $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredMappedParity($report, 1));
            $t->same('generated-navigation', $summary['fixture']);
            $t->same('Generated Navigation Evidence', $summary['metadataTitle']);
            $t->same('en', $summary['metadataLanguage']);
            $t->same(1, $summary['metadataCreatorCount']);
            $t->same(1, $summary['packageLinkCount']);
            $t->same(['record' => 1], $summary['packageLinkRelCounts']);
            $t->same(1, $summary['guideReferenceCount']);
            $t->same(['text' => 1], $summary['guideReferenceTypeCounts']);
            $t->same(4, $summary['manifestItemCount']);
            $t->same([
                'application/json' => 1,
                'application/xhtml+xml' => 2,
                'text/css' => 1,
            ], $summary['manifestMediaTypeCounts']);
            $t->same([
                'nav' => 1,
                'remote-resources' => 1,
            ], $summary['manifestPropertyCounts']);
            $t->same([
                'asset' => 1,
                'navigation' => 1,
                'style' => 1,
                'xhtml' => 1,
            ], $summary['manifestResourceKindCounts']);
            $t->same(1, $summary['remoteResourceManifestItemCount']);
            $t->same(1, $summary['externalManifestItemCount']);
            $t->same(0, $summary['missingLocalManifestItemCount']);
            $t->same(1, $summary['readingOrderCount']);
            $t->same(['linear' => 1], $summary['spineLinearStateCounts']);
            $t->same('nav', $summary['navigationType']);
            $t->same(1, $summary['navigationEntryCount']);
            $t->same(4, $summary['navigationSectionCount']);
            $t->same(['landmarks', 'loi', 'page-list', 'toc'], $summary['navigationSectionTypes']);
            $t->same(1, $summary['landmarkEntryCount']);
            $t->same(1, $summary['pageListEntryCount']);
            $t->same(1, $summary['auxiliaryNavigationEntryCount']);
            $t->same(1, $coverage['fixtureCount']);
            $t->same(['/EPUB/package.opf' => 1], $coverage['opfPartNameCounts']);
            $t->same(['en' => 1], $coverage['metadataLanguageCounts']);
            $t->same(['nav' => 1], $coverage['navigationTypeCounts']);
            $t->same(['linear' => 1], $coverage['spineLinearStateCounts']);
            $t->same([
                'application/json' => 1,
                'application/xhtml+xml' => 2,
                'text/css' => 1,
            ], $coverage['manifestMediaTypeCounts']);
            $t->same([
                'nav' => 1,
                'remote-resources' => 1,
            ], $coverage['manifestPropertyCounts']);
            $t->same([
                'asset' => 1,
                'navigation' => 1,
                'style' => 1,
                'xhtml' => 1,
            ], $coverage['manifestResourceKindCounts']);
            $t->same(['landmarks', 'loi', 'page-list', 'toc'], $coverage['navigationSectionTypes']);
            $t->same(['text' => 1], $coverage['guideReferenceTypeCounts']);
            $t->same(['record' => 1], $coverage['packageLinkRelCounts']);
            $t->same([
                'generated-navigation' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => ['landmarks', 'loi', 'page-list', 'toc'],
                    'manifestResourceKindCounts' => [
                        'asset' => 1,
                        'navigation' => 1,
                        'style' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => ['text' => 1],
                    'packageLinkRelCounts' => ['record' => 1],
                    'coverImagePartPresent' => false,
                ],
            ], $coverage['fixtureFeatureSignatures']);
            $t->same(['generated-navigation'], $coverage['fixturesWithGuideReferences']);
            $t->same(['generated-navigation'], $coverage['fixturesWithPackageLinks']);
            $t->same(['generated-navigation'], $coverage['fixturesWithCreators']);
            $t->same([], $coverage['fixturesWithEncryption']);
            $t->same([], $coverage['fixturesWithObfuscatedFonts']);
            $t->same([], $coverage['fixturesWithBlockedEncryptedByteExposures']);
            $t->same([], $coverage['fixturesWithCoverImagePart']);
            $t->same([], $coverage['fixturesWithImages']);
            $t->same([], $coverage['fixturesWithStylesheets']);
            $t->same(['generated-navigation'], $coverage['fixturesWithLandmarks']);
            $t->same(['generated-navigation'], $coverage['fixturesWithPageLists']);
            $t->same(['generated-navigation'], $coverage['fixturesWithAuxiliaryNavigation']);
            $t->same(['generated-navigation'], $coverage['fixturesWithRemoteManifestResources']);
            $t->same(['generated-navigation'], $coverage['fixturesWithExternalManifestItems']);
            $t->same([], $coverage['fixturesWithMissingLocalManifestItems']);
            $t->same(['generated-navigation'], $coverage['fixturesWithManifestFallbackItems']);
            $t->same([], $coverage['fixturesWithManifestFallbacks']);
            $t->same([], $coverage['fixturesWithResolvedManifestFallbacks']);
            $t->same([], $coverage['fixturesWithUsableManifestFallbacks']);
            $t->same(['generated-navigation'], $coverage['fixturesWithMissingManifestFallbacks']);
            $t->same([], $coverage['fixturesWithMediaOverlays']);
            $t->same([], $coverage['fixturesWithResolvedMediaOverlays']);
            $t->same([], $coverage['fixturesWithMediaOverlayTextTargets']);
            $t->same([], $coverage['fixturesWithMediaOverlayAudioTargets']);
            $t->same([], $coverage['fixturesWithNonLinearSpineItems']);
            $t->same([
                'metadataCreators' => 1,
                'manifestItems' => 4,
                'readingOrderItems' => 1,
                'spinePageSpreadItems' => 0,
                'xhtmlAssets' => 2,
                'imageAssets' => 0,
                'stylesheetAssets' => 0,
                'navigationEntries' => 1,
                'landmarkEntries' => 1,
                'pageListEntries' => 1,
                'auxiliaryNavigationEntries' => 1,
                'packageLinks' => 1,
                'guideReferences' => 1,
                'remoteResourceManifestItems' => 1,
                'externalManifestItems' => 1,
                'missingLocalManifestItems' => 0,
                'manifestFallbackItems' => 1,
                'manifestFallbacks' => 0,
                'resolvedManifestFallbacks' => 0,
                'usableManifestFallbacks' => 0,
                'missingManifestFallbacks' => 1,
                'mediaOverlays' => 0,
                'resolvedMediaOverlays' => 0,
                'missingMediaOverlays' => 0,
                'mediaOverlayReferencedContentItems' => 0,
                'mediaOverlayTextLocalTargets' => 0,
                'mediaOverlayAudioLocalTargets' => 0,
                'mediaOverlayDurations' => 0,
                'encryptionItems' => 0,
                'obfuscatedFonts' => 0,
                'blockedEncryptedByteExposures' => 0,
                'encryptionDiagnostics' => 0,
                'collections' => 0,
                'collectionLinks' => 0,
                'bindingItems' => 0,
                'bindingResolvedHandlers' => 0,
                'bindingMediaTypeParameters' => 0,
                'ocfSidecars' => 0,
            ], $coverage['totals']);

            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-native-ast-package.php')
                . ' --epub-dir=' . escapeshellarg($root)
                . ' --json'
                . ' summary'
                . ' --require-package-parity=1'
                . ' --require-native-readiness=1'
                . ' --require-mapped-parity=1';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same(1, $decoded['packageParsedCount']);
            $t->same(1, $decoded['readerParsedCount']);
            $t->same(1, $decoded['nativeParsedCount']);
            $t->same(1, $decoded['normalizedAstMatchCount']);
            $t->same(0, $decoded['normalizedAstMismatchCount']);
            $t->same(['/EPUB/package.opf' => 1], $decoded['packageFeatureCoverage']['opfPartNameCounts']);
            $t->same(['nav' => 1], $decoded['packageFeatureCoverage']['navigationTypeCounts']);
            $t->same(['linear' => 1], $decoded['packageFeatureCoverage']['spineLinearStateCounts']);
            $t->same(['nav' => 1, 'remote-resources' => 1], $decoded['packageFeatureCoverage']['manifestPropertyCounts']);
            $t->same([
                'asset' => 1,
                'navigation' => 1,
                'style' => 1,
                'xhtml' => 1,
            ], $decoded['packageFeatureCoverage']['manifestResourceKindCounts']);
            $t->same(['text' => 1], $decoded['packageFeatureCoverage']['guideReferenceTypeCounts']);
            $t->same(['record' => 1], $decoded['packageFeatureCoverage']['packageLinkRelCounts']);
            $t->same(['generated-navigation'], $decoded['packageFeatureCoverage']['fixturesWithManifestFallbackItems']);
            $t->same([], $decoded['packageFeatureCoverage']['fixturesWithResolvedManifestFallbacks']);
            $t->same([], $decoded['packageFeatureCoverage']['fixturesWithUsableManifestFallbacks']);
            $t->same(['generated-navigation'], $decoded['packageFeatureCoverage']['fixturesWithMissingManifestFallbacks']);
            $t->same([], $decoded['packageFeatureCoverage']['fixturesWithMediaOverlays']);
            $t->same([], $decoded['packageFeatureCoverage']['fixturesWithResolvedMediaOverlays']);
            $t->same([], $decoded['packageFeatureCoverage']['fixturesWithMediaOverlayTextTargets']);
            $t->same([], $decoded['packageFeatureCoverage']['fixturesWithMediaOverlayAudioTargets']);
            $t->same([], $decoded['packageFeatureCoverage']['fixturesWithEncryption']);
            $t->same([], $decoded['packageFeatureCoverage']['fixturesWithObfuscatedFonts']);
            $t->same([], $decoded['packageFeatureCoverage']['fixturesWithBlockedEncryptedByteExposures']);
            $t->same(
                $coverage['fixtureFeatureSignatures'],
                $decoded['packageFeatureCoverage']['fixtureFeatureSignatures']
            );
            $t->same(['generated-navigation'], $decoded['packageFeatureCoverage']['fixturesWithCreators']);
            $t->same(1, $decoded['packageFeatureCoverage']['totals']['metadataCreators']);
            $t->same(0, $decoded['packageFeatureCoverage']['totals']['mediaOverlays']);
            $t->same(0, $decoded['packageFeatureCoverage']['totals']['encryptionItems']);
        } finally {
            $removeTree($root);
        }
    },

    'validates supplied epub native package runner result artifact' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writeRunnerTranscripts, $fixtureRoot): void {
        $root = $makeTempDir();
        try {
            $harness = new EpubNativeAstPackageComparisonHarness();
            $baseReport = $harness->run($fixtureRoot());
            $runnerPlan = $baseReport['runnerEvidence'];
            $transcripts = $writeRunnerTranscripts($root, $runnerPlan['requiredTranscripts']);
            $generatedNativeManifest = [];
            foreach ($baseReport['fixtureIdentity']['files'] as $file) {
                if (!is_array($file) || !is_string($file['path'] ?? null) || !str_ends_with($file['path'], '.native')) {
                    continue;
                }
                $generatedNativeManifest[] = [
                    'fixture' => basename($file['path'], '.native'),
                    'path' => 'test/epub/' . $file['path'],
                    'sha256' => $file['sha256'],
                    'bytes' => $file['bytes'],
                ];
            }
            usort(
                $generatedNativeManifest,
                static fn (array $left, array $right): int => $left['fixture'] <=> $right['fixture']
            );

            $payload = [
                'schemaVersion' => 2,
                'runner' => 'Cabal-built Pandoc EPUB to native executable',
                'runnerExecuted' => true,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'commit' => EpubNativeAstPackageComparisonHarness::EXPECTED_UPSTREAM_COMMIT,
                ],
                'target' => $runnerPlan['target'],
                'command' => $runnerPlan['futureCommands'][2],
                'exitCode' => 0,
                'fixtureCount' => count($runnerPlan['target']['fixtureBasenames']),
                'generatedNativeCount' => count($generatedNativeManifest),
                'failedCount' => 0,
                'fixtureBasenames' => $runnerPlan['target']['fixtureBasenames'],
                'generatedNativeManifest' => $generatedNativeManifest,
                'transcriptPaths' => $runnerPlan['requiredTranscripts'],
                'transcripts' => $transcripts,
            ];
            $validPayload = $payload;
            $writeFile($root, 'result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            $report = $harness->run($fixtureRoot(), [
                'repoRoot' => $root,
                'runnerResultArtifact' => 'result.json',
            ]);
            $text = $harness->formatReport($report);

            $t->same('completed', $report['runnerEvidence']['status']);
            $t->same(true, $report['runnerEvidence']['executed']);
            $t->same('runner-result-artifact-validated', $report['runnerEvidence']['commandPlanStatus']);
            $t->same('valid-upstream-epub-native-package-runner-result-artifact', $report['runnerEvidence']['validation']['status']);
            $t->same([], $report['runnerEvidence']['validation']['issues']);
            $t->same('upstream-epub-native-package-runner-result-artifact', $report['runnerEvidence']['resultArtifact']['kind']);
            $t->same(true, $report['runnerEvidence']['resultArtifact']['present']);
            $t->same('result.json', $report['runnerEvidence']['resultArtifact']['path']);
            $t->same($runnerPlan['target'], $report['runnerEvidence']['target']);
            $t->same($runnerPlan['futureCommands'][2], $report['runnerEvidence']['command']);
            $t->same($runnerPlan['target']['fixtureBasenames'], $report['runnerEvidence']['observed']['fixtureBasenames']);
            $t->same($generatedNativeManifest, $report['runnerEvidence']['expected']['generatedNativeManifest']);
            $t->same($generatedNativeManifest, $report['runnerEvidence']['observed']['generatedNativeManifest']);
            $t->same($transcripts, $report['runnerEvidence']['expected']['transcripts']);
            $t->same($transcripts, $report['runnerEvidence']['observed']['transcripts']);
            $t->same('upstream-epub-native-package-runner-transcript', $report['runnerEvidence']['transcripts'][0]['kind']);
            $t->same(true, $report['runnerEvidence']['transcripts'][0]['present']);
            $t->same(true, EpubNativeAstPackageComparisonHarness::hasRunnerResultArtifactEvidence($report));
            $t->same(false, EpubNativeAstPackageComparisonHarness::hasRunnerNotRunEvidence($report));
            $t->same(false, EpubNativeAstPackageComparisonHarness::hasRunnerPlanEvidence($report));
            $t->same('covered-by-supplied-runner-result-artifact', $report['orderedRemainingGaps'][2]['status']);
            $t->contains('runnerEvidence: status=completed plan=runner-result-artifact-validated executed=true', $text);

            $payload = $validPayload;
            $payload['generatedNativeManifest'][0]['sha256'] = str_repeat('0', 64);
            $writeFile($root, 'bad-native-result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $badNativeReport = $harness->run($fixtureRoot(), [
                'repoRoot' => $root,
                'runnerResultArtifact' => 'bad-native-result.json',
            ]);

            $t->same('invalid', $badNativeReport['runnerEvidence']['status']);
            $t->same('invalid-upstream-epub-native-package-runner-result-artifact', $badNativeReport['runnerEvidence']['validation']['status']);
            $t->true(in_array('runner-result-generated-native-manifest-mismatch', $badNativeReport['runnerEvidence']['validation']['issues'], true));
            $t->same(false, EpubNativeAstPackageComparisonHarness::hasRunnerResultArtifactEvidence($badNativeReport));

            $payload = $validPayload;
            $payload['transcripts'][0]['bytes'] = 0;
            $writeFile($root, 'bad-transcript-result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $badTranscriptReport = $harness->run($fixtureRoot(), [
                'repoRoot' => $root,
                'runnerResultArtifact' => 'bad-transcript-result.json',
            ]);

            $t->same('invalid', $badTranscriptReport['runnerEvidence']['status']);
            $t->true(in_array('runner-result-transcript-bytes-mismatch', $badTranscriptReport['runnerEvidence']['validation']['issues'], true));
            $t->same(false, EpubNativeAstPackageComparisonHarness::hasRunnerResultArtifactEvidence($badTranscriptReport));

            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-native-ast-package.php')
                . ' --checked-in-fixtures'
                . ' --json'
                . ' summary'
                . ' --runner-result-artifact=' . escapeshellarg($root . '/missing-result.json')
                . ' --require-runner-result-artifact'
                . ' 2>/dev/null';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);

            $t->same(1, $exitCode);
        } finally {
            $removeTree($root);
        }
    },

    'checked-in upstream current epub fixtures satisfy strict package and native ast gates' => static function (TestRunner $t) use ($fixtureRoot): void {
        $root = $fixtureRoot();
        $epubFiles = glob($root . '/*.epub') ?: [];
        $nativeFiles = glob($root . '/*.native') ?: [];
        $expectedFixtureIdentity = [
            'all-nonlinear-spine.epub' => [
                'sha256' => '83fc005e5ab9feaca5c6a08b61d590d0cc3958bbe75b43b5f5a108c599e59882',
                'bytes' => 1364,
            ],
            'all-nonlinear-spine.native' => [
                'sha256' => '37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570',
                'bytes' => 3,
            ],
            'audio-navigation.epub' => [
                'sha256' => '09e5d95402b3a0b34fc1843b61534fe02a58df21ec524071776698f66b8c43a2',
                'bytes' => 1509,
            ],
            'audio-navigation.native' => [
                'sha256' => '86911ce05ad45760deb8f82eb4fe1b569626b09cddcf9fabc2b45cae50b37a22',
                'bytes' => 262,
            ],
            'auxiliary-lot-guide-index.epub' => [
                'sha256' => '8581efb4630635b95af119442cb682181b0004b90d53c6c43dfa255fc1c5bb58',
                'bytes' => 1434,
            ],
            'auxiliary-lot-guide-index.native' => [
                'sha256' => '0cdecc48fd17c19b93fe001e19aac7fb7f4a09e04c80a4d833e55c1828485995',
                'bytes' => 211,
            ],
            'bindings-collections-sidecars.epub' => [
                'sha256' => '82cd32b901ed412a69c5080707ed566207b06030c074bffa3b83460692f07834',
                'bytes' => 3767,
            ],
            'bindings-collections-sidecars.native' => [
                'sha256' => '2dc016af0d0e6f660a7a825acebf27d3bd2a74d30cc0914651b099877774932d',
                'bytes' => 679,
            ],
            'content-image-nav-media.epub' => [
                'sha256' => 'd02bb4c45558841903bb5e83ea3f15af2ca00d4221236d10978b4c0d672e8ce6',
                'bytes' => 2410,
            ],
            'content-image-nav-media.native' => [
                'sha256' => '258f9b8a1b2a9c8df41cbe9142d573d52e248b45cb3872ef2c071328d0e80b34',
                'bytes' => 589,
            ],
            'direct-image-spine.epub' => [
                'sha256' => '695bb5c110c2011b4567c6f4a62b5d3249e00be37cfaff92b965ce346b376cb7',
                'bytes' => 1355,
            ],
            'direct-image-spine.native' => [
                'sha256' => '122dde0a14358daeea4987bdf7a378eb97e59f125bfecbadb404129fd58b2269',
                'bytes' => 4270,
            ],
            'duplicate-spine-idref.epub' => [
                'sha256' => 'cdcd53351890ca8b684b2ad5581be3f57a49c80296c1c7c70bf52fa5220ea3cd',
                'bytes' => 1423,
            ],
            'duplicate-spine-idref.native' => [
                'sha256' => 'a531ce241637505ddcc5a03704f159d5fd5ee213cc59721bb1fb4e93105bb5ff',
                'bytes' => 1312,
            ],
            'epub2_cover.epub' => [
                'sha256' => '4af73a135aa632cbf0c00b2889a5fc1d39a59a77fa294fdeff5ede72ff6ffed1',
                'bytes' => 11794,
            ],
            'epub2_cover.native' => [
                'sha256' => '4107c44d7711b63dac21745139f9cfb6dd99288b38ecf0d43e07b5ecd2493618',
                'bytes' => 1314,
            ],
            'epub2_no_cover.epub' => [
                'sha256' => '8369dbe5cf315f1fe00f9dd1bf7c500cc663d7648edbf0d7b6a9b4d785fedf4e',
                'bytes' => 3584,
            ],
            'epub2_no_cover.native' => [
                'sha256' => '48808c2e009669341a887a3c23adf033744aa652b0f69c319f0058396b59c6b8',
                'bytes' => 1242,
            ],
            'epub2_picture.epub' => [
                'sha256' => '6049dde9e1d0ebcd175a8c5b937984f349af996e293310eafbce09e4c7384495',
                'bytes' => 11742,
            ],
            'epub2_picture.native' => [
                'sha256' => 'fa1cc897a5172b6f66411f2b61156a86669654e0338d137f543e069d4f73fb39',
                'bytes' => 1314,
            ],
            'epub3-ncx-toc-fallback.epub' => [
                'sha256' => 'ead984a9fdd9e85194a55d0c1a4f28d67182493bad9692f8ee19424b33ddd225',
                'bytes' => 2189,
            ],
            'epub3-ncx-toc-fallback.native' => [
                'sha256' => 'd2af2d91536fe498affbe70f0de4a917c30c5c8e0cc147dc631bbb5cf49af781',
                'bytes' => 1013,
            ],
            'external-footnote-reference.epub' => [
                'sha256' => '9df47e23e87d0385737c76fbc518bec86d7ab222e9a007c1db1d0e5f9c0ec5d2',
                'bytes' => 1766,
            ],
            'external-footnote-reference.native' => [
                'sha256' => 'ee4878561dad1a0f53703d0cb4bd8b2726068cee9482c32df47fa481194675ee',
                'bytes' => 286,
            ],
            'features.epub' => [
                'sha256' => '6bf9a102249d58b32f14b39dfbc966bdecadff68a3fb707cb3ca62334734358a',
                'bytes' => 8970,
            ],
            'features.native' => [
                'sha256' => 'c384a314081ecc860bb0f8a9ffb5273976ed56341e4f16e05dd448126e85c41f',
                'bytes' => 48453,
            ],
            'font-manifest-resource.epub' => [
                'sha256' => 'ab561d6de4579fbe572ae1e99e56c3dcba464f1d9c2906310f1324d1a1243d0e',
                'bytes' => 1512,
            ],
            'font-manifest-resource.native' => [
                'sha256' => 'f1f123f4ab0d1a612523707a09504a1e3e9b61194f6cbe1338dcb5d920c089d1',
                'bytes' => 177,
            ],
            'formatting.epub' => [
                'sha256' => '491fc57ec384449a23c4f2abdcfe91be9ab2a07f50f466fb8d80775b89bf3965',
                'bytes' => 14022,
            ],
            'formatting.native' => [
                'sha256' => '9041b6aa23827579a4db45074bd9b26077337defc26ec62ab3b57f676f4eeb21',
                'bytes' => 172999,
            ],
            'fragment-nav-spine.epub' => [
                'sha256' => 'cf582d0b887cd5c7a01180a7fe45138144bb650dc257f21c32ef33765a50a6b8',
                'bytes' => 1372,
            ],
            'fragment-nav-spine.native' => [
                'sha256' => '81ffc5d60c1d7c49cfe3f95c44036d87d922c2aef8d71425dce3cb666da5576e',
                'bytes' => 550,
            ],
            'guide-bibliography-reference.epub' => [
                'sha256' => 'c41d806bf13306837ecfdbc12504a1f134f85d40545bb4694447763297f891fd',
                'bytes' => 1391,
            ],
            'guide-bibliography-reference.native' => [
                'sha256' => 'de4ce57368f4f73e70c2f2018c52548ccbe7dcc275fcf50cab0b05277191ec9d',
                'bytes' => 188,
            ],
            'guide-glossary-reference.epub' => [
                'sha256' => '699550c8c91e9f11cb430c24e2e157a1f6dfb4f11cff2b98f5ad3cce72b6141d',
                'bytes' => 1386,
            ],
            'guide-glossary-reference.native' => [
                'sha256' => 'bd285d34bd9a24f860fb1f398ad291957f68189468858f15192d9823b6f06279',
                'bytes' => 181,
            ],
            'guide-notes-reference.epub' => [
                'sha256' => '7fdc04f51cc6f359c5f44cd56661d953f2ccd00983a45ae4fedcb91c275fccee',
                'bytes' => 1378,
            ],
            'guide-notes-reference.native' => [
                'sha256' => '53f14b3a3553b8ba92832a2736b8c08dc75218ebf60c92585c4c0056875ac75d',
                'bytes' => 174,
            ],
            'guide-preface-reference.epub' => [
                'sha256' => 'd4470953a6b05f8a8d33a1aa766a04fd9a58ea897b3017a41aed7d2410990d37',
                'bytes' => 1367,
            ],
            'guide-preface-reference.native' => [
                'sha256' => '521b247130c5f4e5d561857912fb378bbf6305108ccc0f3700bad8847ee9e9e9',
                'bytes' => 178,
            ],
            'img.epub' => [
                'sha256' => 'f2c25e0e0612b7ac33a8d6a1c9719a86e7d2a0290472fc7d8b5068de781a822f',
                'bytes' => 20478,
            ],
            'img.native' => [
                'sha256' => '817c691f8fab94b1ed9092b9cc23a2299771af8df99c8b0a8dded51ce63baf91',
                'bytes' => 6762,
            ],
            'img_no_cover.epub' => [
                'sha256' => '3063f5e9b9610df1ddcc682ce49c293bcf681f1958700a5b6c3eda344383cf2a',
                'bytes' => 10602,
            ],
            'img_no_cover.native' => [
                'sha256' => '0e0152ba08256f6926bb9e9bba1892b673aa994ddbc8ab369d36f0abeab0b2b2',
                'bytes' => 6630,
            ],
            'language-french-metadata.epub' => [
                'sha256' => 'a64733afbdd101dcf679227227eacaa6dd8ec1649721e406cbc245e4e91a5f87',
                'bytes' => 1317,
            ],
            'language-french-metadata.native' => [
                'sha256' => '66ed9d9c546eb58f4fb7685ab5c30affa158a84b415a068408f95d52298a4dcf',
                'bytes' => 144,
            ],
            'manifest-fallback-chain.epub' => [
                'sha256' => 'af579a53102ff39e74bf2f79df687384ba1897c961aba9be197ba575079e18a4',
                'bytes' => 1735,
            ],
            'manifest-fallback-chain.native' => [
                'sha256' => '54fe7e8b655152d47863121ec647bddd468e69bfab601a05af54fc00f07893d3',
                'bytes' => 180,
            ],
            'manifest-href-encoding.epub' => [
                'sha256' => 'a5f5643ef8d10b7ed6339a14153991273db0d78e23b2b8c2fcf949922f0c11e8',
                'bytes' => 2281,
            ],
            'manifest-href-encoding.native' => [
                'sha256' => '59c8166ffa04fa003cf7a11d2f8b5e9097d3402218f1d7553d760f9cad70f8e5',
                'bytes' => 513,
            ],
            'media-manifest-mix.epub' => [
                'sha256' => 'd74b69c881a8a46913a719fe2aa5311cb7fdf5ac747f98e7c5b342a3a78fe04c',
                'bytes' => 1801,
            ],
            'media-manifest-mix.native' => [
                'sha256' => 'aa1c71ce01bcc9a0a893188663f1c381fad780371edd17ac791c60c183ae5f85',
                'bytes' => 415,
            ],
            'media-overlay-package.epub' => [
                'sha256' => '6af50dc4bf618cd964af7274a688aebcbd16da6804581325c00195b1721ed972',
                'bytes' => 1894,
            ],
            'media-overlay-package.native' => [
                'sha256' => '2083a3e8168ce9f47a3f6e8574fb8917a29b0760736a6123e238fc5681eef5e7',
                'bytes' => 192,
            ],
            'metadata-link-page-list-image.epub' => [
                'sha256' => 'ed2da17a5ea5cc370bde15d43e9480558654e644cf3c4d637ea50c71c1a3241c',
                'bytes' => 1926,
            ],
            'metadata-link-page-list-image.native' => [
                'sha256' => '884c97ef31814c40e380663f07792a4dd223d67457fd4b7cfbf0bae9be158cc5',
                'bytes' => 1140,
            ],
            'missing-local-manifest-resource.epub' => [
                'sha256' => '5ce06b74cde06eb0d06f1b41b73f99840983451abb9bb120e8206979ac16dca5',
                'bytes' => 1386,
            ],
            'missing-local-manifest-resource.native' => [
                'sha256' => '2eaad3b88904dc836c7d9993ccba2894946df1bb91d59524b63346c5ea24921c',
                'bytes' => 200,
            ],
            'missing-media-overlay.epub' => [
                'sha256' => '2f6f3b7da6babcda4101045e106c1bfac5ea56377ae96764793d8ccd98cadf07',
                'bytes' => 1422,
            ],
            'missing-media-overlay.native' => [
                'sha256' => 'fb2b6d05c5d95f316dd8f73f4898ec493f509bc287084e81427c5617e182d252',
                'bytes' => 213,
            ],
            'nav-ncx-linear-guide.epub' => [
                'sha256' => '45b914d6e5ef83949c5432b7c523c383d323a3b9aa56499946155b88ace41f26',
                'bytes' => 2336,
            ],
            'nav-ncx-linear-guide.native' => [
                'sha256' => '0e44bc8507ce00254743af59dbdc8ab96508730543ae0fd19f8a1a26b97cc95f',
                'bytes' => 202,
            ],
            'nested-path-media-metadata.epub' => [
                'sha256' => '685025a751e882b4700b6b31a0cdb8f51eceecaae86be1d83e0590beb2d876b7',
                'bytes' => 3588,
            ],
            'nested-path-media-metadata.native' => [
                'sha256' => '237760af79e8ff533a0bdab616e5a100ec81c85f7543b34ab388844bb8ad9766',
                'bytes' => 1899,
            ],
            'nested-rootfile-nonlinear-spine.epub' => [
                'sha256' => 'e0e41f25280f3b7a092ea2ed105af51c33e445221b2d54c877181c96aed191f4',
                'bytes' => 2043,
            ],
            'nested-rootfile-nonlinear-spine.native' => [
                'sha256' => '9f857344d02b81e87d3643b01fc7a98e2ed1504d5c61da8a116d4bd3e725222e',
                'bytes' => 200,
            ],
            'package-spine-nav-media-metadata.epub' => [
                'sha256' => '64981f08e5f4b2ae41baf55233e3cf4419c62c25d2606347bfedf0ee7e181a18',
                'bytes' => 2402,
            ],
            'package-spine-nav-media-metadata.native' => [
                'sha256' => '6d5be8a2ed05f750c291ce141c0110e2264605960ccaf89175de7cf6179fffbd',
                'bytes' => 993,
            ],
            'page-list-navigation.epub' => [
                'sha256' => '449c6114a473e2db1df8cf69cd29fddaef4a14a160b65fd7fe30adf0c80b9365',
                'bytes' => 1394,
            ],
            'page-list-navigation.native' => [
                'sha256' => '3b5fb7863f0df2ba4875092b369aa2b5f8e6797ec0a1edc17232d594ee1047c6',
                'bytes' => 175,
            ],
            'parent-relative-nav.epub' => [
                'sha256' => 'caafa83c3b42b02d6aa25905f04b045df1a3db37913a636a296193cc4f8f27f6',
                'bytes' => 1652,
            ],
            'parent-relative-nav.native' => [
                'sha256' => 'fa48842bd1b89d8ba991dc5d577bb526f61bf89c7e8966f66c0929ca6d149a9e',
                'bytes' => 705,
            ],
            'remote-manifest-resource.epub' => [
                'sha256' => 'aaf4a5557c55af341a6a2ed5950ccc5807ce529f6ae4ed4398336345b0646c7f',
                'bytes' => 1385,
            ],
            'remote-manifest-resource.native' => [
                'sha256' => '96cafe1fc0398a6f41e4ec352d52f961e6bdb1206bfcc5637505f4cd5ebc2c2b',
                'bytes' => 181,
            ],
            'rendition-layout-property.epub' => [
                'sha256' => 'abdbb293f94d979445600249a1162c0607a2fbcb73fc260d77d61334edef3671',
                'bytes' => 1390,
            ],
            'rendition-layout-property.native' => [
                'sha256' => '3147a4f4255f778f5419ea67d411038c008173a08ca94a8b5fefc37e4bb668e5',
                'bytes' => 206,
            ],
            'scripted-svg-manifest.epub' => [
                'sha256' => '8845d9a35825bdf882b5d2239b60c1e7fd0f9589c8d06f5be74f0565fc56bb1b',
                'bytes' => 1577,
            ],
            'scripted-svg-manifest.native' => [
                'sha256' => 'c4c89cc198ed6aab17f1f6c417e9b4bb919ba704af09eb508f5805d2077c193e',
                'bytes' => 180,
            ],
            'scripted-xhtml-resource.epub' => [
                'sha256' => '4600cb6c58330de0c0dc6e27deb73c41dae16a395c98ad0774fb3812323d77e5',
                'bytes' => 1556,
            ],
            'scripted-xhtml-resource.native' => [
                'sha256' => '0da002a70192ef1d75d04151403344c1f5fce75769ed97bf335b4a316545b85d',
                'bytes' => 177,
            ],
            'spine-fallback-resource.epub' => [
                'sha256' => 'c042da479466e7353f063d986eb5481e49d2a6d9b93a8348576994f6ae3dbde6',
                'bytes' => 1661,
            ],
            'spine-fallback-resource.native' => [
                'sha256' => '56a094f8d97c055aeca928ad6d5162be7ca396ea1f869a2b29740aef3415baaa',
                'bytes' => 48,
            ],
            'spine-page-spread.epub' => [
                'sha256' => '47c48d493ff2846023ce78c1cb407d8025865ef7eb986c9f60607de4189bd5e1',
                'bytes' => 1562,
            ],
            'spine-page-spread.native' => [
                'sha256' => 'ecdae2b7e18be738e3530727e3d04f253fed3a6474091964d0b9c0c16c984dd9',
                'bytes' => 483,
            ],
            'standalone-footnote.epub' => [
                'sha256' => '5058fb925a59dadae5ac5e371f4907c5a192b074410d2c668b4e2b6ff483ab53',
                'bytes' => 1384,
            ],
            'standalone-footnote.native' => [
                'sha256' => '8ba2f5a23a13f1c6d0e309e3ba77ea8bb65702e5c166c38589d954fcc5026657',
                'bytes' => 431,
            ],
            'title-page-guide-media-metadata.epub' => [
                'sha256' => '9a21d071427572212113af33e11d1d39cd692ea840a81980dfaf471840d28dc7',
                'bytes' => 2801,
            ],
            'title-page-guide-media-metadata.native' => [
                'sha256' => '8f2c47bb97258bdf88a8cf1a8f8f398e42d2afa8bf2633ceda835785aefdf3d0',
                'bytes' => 747,
            ],
            'video-manifest-resource.epub' => [
                'sha256' => '7db258c0f96c66dc1de9eeaa1fc75ca5e9fddf821b6f0783cd4b74f4f59013b5',
                'bytes' => 1508,
            ],
            'video-manifest-resource.native' => [
                'sha256' => '844b189a6f0de4d43e260e07766cdc0329db17c0963024d9fd866c80a73d2f6b',
                'bytes' => 179,
            ],
            'video-navigation.epub' => [
                'sha256' => '71bf3f39156a0911cd9b542aee3c45d88aabd608a9a268a4c4fe6a949f1956fe',
                'bytes' => 1505,
            ],
            'video-navigation.native' => [
                'sha256' => '0a7d0436add9426392a1a10b4d4b725848931a4b7f49fdf6c8acea5e86f14241',
                'bytes' => 262,
            ],
            'wasteland.epub' => [
                'sha256' => '151ec5dbca33e39a4e3f6894e92fa5a101290bdeaaa792e0700595971456a278',
                'bytes' => 25840,
            ],
            'wasteland.native' => [
                'sha256' => '0a268af28518f063604659adb2ff27b123c771f8312b60fb40445bb2c551bbac',
                'bytes' => 150477,
            ],
            'xhtml-semantics-spine.epub' => [
                'sha256' => 'd2a4df3e7287b534b0ad1685d8f241940dd728fa3541ae1d14924506f7544452',
                'bytes' => 1893,
            ],
            'xhtml-semantics-spine.native' => [
                'sha256' => 'd2e7da70eb00cd5172cc2382532b972a62d9ef9fc1e4c107aa3c504fa2367fa2',
                'bytes' => 3228,
            ],
        ];
        $expectedPackageFeatureCoverage = [
            'kind' => 'epub-package-feature-coverage',
            'fixtureCount' => 48,
            'opfPartNameCounts' => [
                '/EPUB/package.opf' => 35,
                '/EPUB/wasteland.opf' => 1,
                '/OEBPS/content.opf' => 3,
                '/OPS/book/package.opf' => 3,
                '/OPS/package.opf' => 6,
            ],
            'metadataLanguageCounts' => [
                'de-DE' => 3,
                'en' => 41,
                'en-GB' => 1,
                'en-US' => 2,
                'fr' => 1,
            ],
            'fixturesWithCreators' => [
                'bindings-collections-sidecars',
                'content-image-nav-media',
                'duplicate-spine-idref',
                'epub2_cover',
                'epub2_no_cover',
                'epub2_picture',
                'epub3-ncx-toc-fallback',
                'external-footnote-reference',
                'features',
                'formatting',
                'img',
                'img_no_cover',
                'language-french-metadata',
                'manifest-href-encoding',
                'media-manifest-mix',
                'metadata-link-page-list-image',
                'missing-media-overlay',
                'nested-path-media-metadata',
                'nested-rootfile-nonlinear-spine',
                'package-spine-nav-media-metadata',
                'parent-relative-nav',
                'spine-fallback-resource',
                'title-page-guide-media-metadata',
                'wasteland',
                'xhtml-semantics-spine',
            ],
            'navigationTypeCounts' => [
                'nav' => 43,
                'ncx' => 4,
            ],
            'spineLinearStateCounts' => [
                'linear' => 63,
                'non-linear' => 14,
            ],
            'spinePageSpreadPlacementCounts' => [
                'left' => 2,
                'right' => 2,
            ],
            'manifestMediaTypeCounts' => [
                'application/javascript' => 1,
                'application/json' => 4,
                'application/octet-stream' => 1,
                'application/pdf' => 1,
                'application/smil+xml' => 1,
                'application/x-bound-widget' => 1,
                'application/x-dtbncx+xml' => 6,
                'application/x-fallback-demo' => 2,
                'application/xhtml+xml' => 114,
                'audio/mpeg' => 4,
                'font/woff2' => 1,
                'image/gif' => 5,
                'image/jpeg' => 7,
                'image/png' => 9,
                'image/svg+xml' => 1,
                'text/css' => 22,
                'video/mp4' => 2,
            ],
            'manifestPropertyCounts' => [
                'cover-image' => 3,
                'mathml' => 2,
                'nav' => 43,
                'remote-resources' => 3,
                'rendition:layout-pre-paginated' => 1,
                'scripted' => 2,
                'svg' => 2,
                'switch' => 1,
            ],
            'manifestResourceKindCounts' => [
                'asset' => 9,
                'audio' => 4,
                'cover-image' => 3,
                'font' => 1,
                'image' => 18,
                'media-overlay' => 1,
                'navigation' => 49,
                'script' => 1,
                'style' => 22,
                'svg' => 1,
                'video' => 2,
                'xhtml' => 71,
            ],
            'navigationSectionTypes' => [
                'landmarks',
                'loa',
                'loi',
                'lot',
                'lov',
                'page-list',
                'toc',
            ],
            'guideReferenceTypeCounts' => [
                'bibliography' => 1,
                'cover' => 3,
                'glossary' => 1,
                'index' => 1,
                'notes' => 1,
                'preface' => 1,
                'text' => 7,
                'title-page' => 1,
                'toc' => 1,
            ],
            'packageLinkRelCounts' => [
                'alternate' => 1,
                'cc:attributionURL' => 1,
                'cc:license' => 2,
                'preview' => 1,
                'record' => 5,
            ],
            'encryptionRoleCounts' => [
                'font' => 3,
            ],
            'collectionRoleCounts' => [
                'index' => 1,
                'role:primary' => 1,
                'schema:hasPart' => 1,
            ],
            'collectionLinkRelCounts' => [
                'contents' => 1,
                'index' => 1,
                'record' => 1,
            ],
            'bindingMediaTypeCounts' => [
                'application/x-bound-widget' => 1,
            ],
            'ocfSidecarKindCounts' => [
                'manifest' => 1,
                'metadata' => 1,
                'rights' => 1,
                'signatures' => 1,
            ],
            'fixtureFeatureSignatures' => [
                'all-nonlinear-spine' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'audio-navigation' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'loa',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'audio' => 1,
                        'navigation' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'auxiliary-lot-guide-index' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'lot',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [
                        'index' => 1,
                    ],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'bindings-collections-sidecars' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'landmarks',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'asset' => 1,
                        'navigation' => 1,
                        'xhtml' => 2,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [
                        'record' => 1,
                    ],
                    'coverImagePartPresent' => false,
                ],
                'content-image-nav-media' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'landmarks',
                        'page-list',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'audio' => 1,
                        'image' => 2,
                        'navigation' => 1,
                        'xhtml' => 2,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'direct-image-spine' => [
                    'navigationType' => '',
                    'navigationSectionTypes' => [],
                    'manifestResourceKindCounts' => [
                        'image' => 3,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'duplicate-spine-idref' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'epub2_cover' => [
                    'navigationType' => 'ncx',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'image' => 1,
                        'navigation' => 1,
                        'style' => 1,
                        'xhtml' => 2,
                    ],
                    'guideReferenceTypeCounts' => [
                        'cover' => 1,
                    ],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => true,
                ],
                'epub2_no_cover' => [
                    'navigationType' => 'ncx',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'style' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [
                        'toc' => 1,
                    ],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'epub2_picture' => [
                    'navigationType' => 'ncx',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'image' => 1,
                        'navigation' => 1,
                        'style' => 1,
                        'xhtml' => 2,
                    ],
                    'guideReferenceTypeCounts' => [
                        'cover' => 1,
                    ],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => true,
                ],
                'epub3-ncx-toc-fallback' => [
                    'navigationType' => 'ncx',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'xhtml' => 2,
                    ],
                    'guideReferenceTypeCounts' => [
                        'text' => 1,
                    ],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'external-footnote-reference' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'landmarks',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'xhtml' => 2,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'features' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'landmarks',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'style' => 2,
                        'xhtml' => 3,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'font-manifest-resource' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'font' => 1,
                        'navigation' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'formatting' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'landmarks',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'image' => 1,
                        'navigation' => 1,
                        'style' => 2,
                        'xhtml' => 7,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'fragment-nav-spine' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'guide-bibliography-reference' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [
                        'bibliography' => 1,
                    ],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'guide-glossary-reference' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [
                        'glossary' => 1,
                    ],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'guide-notes-reference' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [
                        'notes' => 1,
                    ],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'guide-preface-reference' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [
                        'preface' => 1,
                    ],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'img' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'landmarks',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'cover-image' => 1,
                        'image' => 3,
                        'navigation' => 1,
                        'style' => 2,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => true,
                ],
                'img_no_cover' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'landmarks',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'image' => 3,
                        'navigation' => 1,
                        'style' => 2,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'language-french-metadata' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'manifest-fallback-chain' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'asset' => 1,
                        'navigation' => 1,
                        'xhtml' => 2,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'manifest-href-encoding' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'landmarks',
                        'page-list',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'asset' => 1,
                        'navigation' => 1,
                        'style' => 1,
                        'xhtml' => 2,
                    ],
                    'guideReferenceTypeCounts' => [
                        'text' => 1,
                    ],
                    'packageLinkRelCounts' => [
                        'record' => 1,
                    ],
                    'coverImagePartPresent' => false,
                ],
                'media-manifest-mix' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'asset' => 2,
                        'navigation' => 1,
                        'style' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'media-overlay-package' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'audio' => 1,
                        'media-overlay' => 1,
                        'navigation' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'metadata-link-page-list-image' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'page-list',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'image' => 1,
                        'navigation' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [
                        'alternate' => 1,
                        'record' => 1,
                    ],
                    'coverImagePartPresent' => false,
                ],
                'missing-local-manifest-resource' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'style' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'missing-media-overlay' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'nav-ncx-linear-guide' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'landmarks',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'asset' => 1,
                        'navigation' => 2,
                        'xhtml' => 2,
                    ],
                    'guideReferenceTypeCounts' => [
                        'text' => 1,
                    ],
                    'packageLinkRelCounts' => [
                        'record' => 1,
                    ],
                    'coverImagePartPresent' => false,
                ],
                'nested-path-media-metadata' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'landmarks',
                        'lot',
                        'page-list',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'asset' => 1,
                        'audio' => 1,
                        'cover-image' => 1,
                        'image' => 1,
                        'navigation' => 1,
                        'style' => 1,
                        'xhtml' => 3,
                    ],
                    'guideReferenceTypeCounts' => [
                        'cover' => 1,
                        'text' => 1,
                    ],
                    'packageLinkRelCounts' => [
                        'record' => 1,
                    ],
                    'coverImagePartPresent' => true,
                ],
                'nested-rootfile-nonlinear-spine' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'landmarks',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'style' => 1,
                        'xhtml' => 2,
                    ],
                    'guideReferenceTypeCounts' => [
                        'text' => 1,
                    ],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'package-spine-nav-media-metadata' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'landmarks',
                        'page-list',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'image' => 1,
                        'navigation' => 1,
                        'style' => 1,
                        'xhtml' => 2,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'page-list-navigation' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'loi',
                        'page-list',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'parent-relative-nav' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'landmarks',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [
                        'text' => 1,
                    ],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'remote-manifest-resource' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'style' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'rendition-layout-property' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'scripted-svg-manifest' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'svg' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'scripted-xhtml-resource' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'script' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'spine-fallback-resource' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'landmarks',
                        'page-list',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'asset' => 1,
                        'navigation' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'spine-page-spread' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'xhtml' => 2,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'standalone-footnote' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'title-page-guide-media-metadata' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'landmarks',
                        'loa',
                        'page-list',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'asset' => 1,
                        'image' => 1,
                        'navigation' => 1,
                        'style' => 1,
                        'xhtml' => 3,
                    ],
                    'guideReferenceTypeCounts' => [
                        'title-page' => 1,
                    ],
                    'packageLinkRelCounts' => [
                        'preview' => 1,
                    ],
                    'coverImagePartPresent' => false,
                ],
                'video-manifest-resource' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'video' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'video-navigation' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'lov',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'video' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
                'wasteland' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'landmarks',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'cover-image' => 1,
                        'navigation' => 2,
                        'style' => 2,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [],
                    'packageLinkRelCounts' => [
                        'cc:attributionURL' => 1,
                        'cc:license' => 2,
                    ],
                    'coverImagePartPresent' => true,
                ],
                'xhtml-semantics-spine' => [
                    'navigationType' => 'nav',
                    'navigationSectionTypes' => [
                        'landmarks',
                        'toc',
                    ],
                    'manifestResourceKindCounts' => [
                        'navigation' => 1,
                        'style' => 1,
                        'xhtml' => 1,
                    ],
                    'guideReferenceTypeCounts' => [
                        'text' => 1,
                    ],
                    'packageLinkRelCounts' => [],
                    'coverImagePartPresent' => false,
                ],
            ],
            'fixturesWithGuideReferences' => [
                'auxiliary-lot-guide-index',
                'epub2_cover',
                'epub2_no_cover',
                'epub2_picture',
                'epub3-ncx-toc-fallback',
                'guide-bibliography-reference',
                'guide-glossary-reference',
                'guide-notes-reference',
                'guide-preface-reference',
                'manifest-href-encoding',
                'nav-ncx-linear-guide',
                'nested-path-media-metadata',
                'nested-rootfile-nonlinear-spine',
                'parent-relative-nav',
                'title-page-guide-media-metadata',
                'xhtml-semantics-spine',
            ],
            'fixturesWithPackageLinks' => [
                'bindings-collections-sidecars',
                'manifest-href-encoding',
                'metadata-link-page-list-image',
                'nav-ncx-linear-guide',
                'nested-path-media-metadata',
                'title-page-guide-media-metadata',
                'wasteland',
            ],
            'fixturesWithCoverImagePart' => [
                'epub2_cover',
                'epub2_picture',
                'img',
                'nested-path-media-metadata',
                'wasteland',
            ],
            'fixturesWithEncryption' => [
                'epub2_cover',
                'epub2_no_cover',
                'epub2_picture',
            ],
            'fixturesWithObfuscatedFonts' => [
                'epub2_cover',
                'epub2_no_cover',
                'epub2_picture',
            ],
            'fixturesWithBlockedEncryptedByteExposures' => [
                'epub2_cover',
                'epub2_no_cover',
                'epub2_picture',
            ],
            'fixturesWithImages' => [
                'content-image-nav-media',
                'direct-image-spine',
                'epub2_cover',
                'epub2_picture',
                'formatting',
                'img',
                'img_no_cover',
                'metadata-link-page-list-image',
                'nested-path-media-metadata',
                'package-spine-nav-media-metadata',
                'scripted-svg-manifest',
                'title-page-guide-media-metadata',
                'wasteland',
            ],
            'fixturesWithStylesheets' => [
                'epub2_cover',
                'epub2_no_cover',
                'epub2_picture',
                'features',
                'formatting',
                'img',
                'img_no_cover',
                'manifest-href-encoding',
                'missing-local-manifest-resource',
                'nested-path-media-metadata',
                'nested-rootfile-nonlinear-spine',
                'package-spine-nav-media-metadata',
                'title-page-guide-media-metadata',
                'wasteland',
                'xhtml-semantics-spine',
            ],
            'fixturesWithLandmarks' => [
                'bindings-collections-sidecars',
                'content-image-nav-media',
                'external-footnote-reference',
                'features',
                'formatting',
                'img',
                'img_no_cover',
                'manifest-href-encoding',
                'nav-ncx-linear-guide',
                'nested-path-media-metadata',
                'nested-rootfile-nonlinear-spine',
                'package-spine-nav-media-metadata',
                'parent-relative-nav',
                'spine-fallback-resource',
                'title-page-guide-media-metadata',
                'wasteland',
                'xhtml-semantics-spine',
            ],
            'fixturesWithPageLists' => [
                'content-image-nav-media',
                'manifest-href-encoding',
                'metadata-link-page-list-image',
                'nested-path-media-metadata',
                'package-spine-nav-media-metadata',
                'page-list-navigation',
                'spine-fallback-resource',
                'title-page-guide-media-metadata',
            ],
            'fixturesWithAuxiliaryNavigation' => [
                'audio-navigation',
                'auxiliary-lot-guide-index',
                'nested-path-media-metadata',
                'page-list-navigation',
                'title-page-guide-media-metadata',
                'video-navigation',
            ],
            'fixturesWithRemoteManifestResources' => [
                'media-manifest-mix',
                'nested-path-media-metadata',
                'remote-manifest-resource',
            ],
            'fixturesWithExternalManifestItems' => [
                'media-manifest-mix',
                'nested-path-media-metadata',
                'remote-manifest-resource',
            ],
            'fixturesWithMissingLocalManifestItems' => [
                'missing-local-manifest-resource',
            ],
            'fixturesWithManifestFallbackItems' => [
                'bindings-collections-sidecars',
                'manifest-fallback-chain',
                'manifest-href-encoding',
                'media-manifest-mix',
                'nav-ncx-linear-guide',
                'nested-path-media-metadata',
                'spine-fallback-resource',
                'title-page-guide-media-metadata',
                'video-manifest-resource',
                'video-navigation',
            ],
            'fixturesWithManifestFallbacks' => [
                'bindings-collections-sidecars',
                'manifest-fallback-chain',
                'media-manifest-mix',
                'spine-fallback-resource',
            ],
            'fixturesWithResolvedManifestFallbacks' => [
                'bindings-collections-sidecars',
                'manifest-fallback-chain',
                'media-manifest-mix',
                'spine-fallback-resource',
            ],
            'fixturesWithUsableManifestFallbacks' => [
                'bindings-collections-sidecars',
                'manifest-fallback-chain',
                'media-manifest-mix',
                'spine-fallback-resource',
            ],
            'fixturesWithMissingManifestFallbacks' => [
                'manifest-href-encoding',
                'nav-ncx-linear-guide',
                'nested-path-media-metadata',
                'title-page-guide-media-metadata',
                'video-manifest-resource',
                'video-navigation',
            ],
            'fixturesWithMediaOverlays' => [
                'media-overlay-package',
                'missing-media-overlay',
            ],
            'fixturesWithResolvedMediaOverlays' => [
                'media-overlay-package',
            ],
            'fixturesWithMediaOverlayTextTargets' => [
                'media-overlay-package',
            ],
            'fixturesWithMediaOverlayAudioTargets' => [
                'media-overlay-package',
            ],
            'fixturesWithNonLinearSpineItems' => [
                'all-nonlinear-spine',
                'content-image-nav-media',
                'epub2_cover',
                'epub2_picture',
                'external-footnote-reference',
                'features',
                'formatting',
                'img',
                'img_no_cover',
                'manifest-href-encoding',
                'nav-ncx-linear-guide',
                'nested-path-media-metadata',
                'nested-rootfile-nonlinear-spine',
                'title-page-guide-media-metadata',
            ],
            'fixturesWithSpinePageSpreadItems' => [
                'nested-path-media-metadata',
                'spine-page-spread',
            ],
            'fixturesWithCollections' => [
                'bindings-collections-sidecars',
            ],
            'fixturesWithBindings' => [
                'bindings-collections-sidecars',
            ],
            'fixturesWithOcfSidecars' => [
                'bindings-collections-sidecars',
            ],
            'totals' => [
                'metadataCreators' => 45,
                'manifestItems' => 182,
                'readingOrderItems' => 77,
                'spinePageSpreadItems' => 4,
                'xhtmlAssets' => 114,
                'imageAssets' => 22,
                'stylesheetAssets' => 20,
                'navigationEntries' => 135,
                'landmarkEntries' => 20,
                'pageListEntries' => 11,
                'auxiliaryNavigationEntries' => 6,
                'packageLinks' => 9,
                'guideReferences' => 17,
                'remoteResourceManifestItems' => 3,
                'externalManifestItems' => 3,
                'missingLocalManifestItems' => 1,
                'manifestFallbackItems' => 11,
                'manifestFallbacks' => 5,
                'resolvedManifestFallbacks' => 5,
                'usableManifestFallbacks' => 5,
                'missingManifestFallbacks' => 6,
                'mediaOverlays' => 2,
                'resolvedMediaOverlays' => 1,
                'missingMediaOverlays' => 1,
                'mediaOverlayReferencedContentItems' => 2,
                'mediaOverlayTextLocalTargets' => 1,
                'mediaOverlayAudioLocalTargets' => 1,
                'mediaOverlayDurations' => 3,
                'encryptionItems' => 3,
                'obfuscatedFonts' => 3,
                'blockedEncryptedByteExposures' => 3,
                'encryptionDiagnostics' => 6,
                'collections' => 2,
                'collectionLinks' => 3,
                'bindingItems' => 1,
                'bindingResolvedHandlers' => 1,
                'bindingMediaTypeParameters' => 1,
                'ocfSidecars' => 4,
            ],
        ];
        $expectedPackageFeatureSignatureSha256 = '8fa55135309294ef9fd5943f6a25e2d69e8fb962905864280258f28f7ab5320e';
        $expectedCurrentNativeAstSignatureSha256 = 'b53fff1d6e8d64fc996b9787c39ca4a152feb8b2322bb9d3c341991cfbdebccb';
        $expectedCurrentNativeAstFixtures = [
            'all-nonlinear-spine',
            'audio-navigation',
            'auxiliary-lot-guide-index',
            'bindings-collections-sidecars',
            'content-image-nav-media',
            'direct-image-spine',
            'duplicate-spine-idref',
            'epub2_cover',
            'epub2_no_cover',
            'epub2_picture',
            'epub3-ncx-toc-fallback',
            'external-footnote-reference',
            'features',
            'font-manifest-resource',
            'formatting',
            'fragment-nav-spine',
            'guide-bibliography-reference',
            'guide-glossary-reference',
            'guide-notes-reference',
            'guide-preface-reference',
            'img',
            'img_no_cover',
            'language-french-metadata',
            'manifest-fallback-chain',
            'manifest-href-encoding',
            'media-manifest-mix',
            'media-overlay-package',
            'metadata-link-page-list-image',
            'missing-local-manifest-resource',
            'missing-media-overlay',
            'nav-ncx-linear-guide',
            'nested-path-media-metadata',
            'nested-rootfile-nonlinear-spine',
            'package-spine-nav-media-metadata',
            'page-list-navigation',
            'parent-relative-nav',
            'remote-manifest-resource',
            'rendition-layout-property',
            'scripted-svg-manifest',
            'scripted-xhtml-resource',
            'spine-fallback-resource',
            'spine-page-spread',
            'standalone-footnote',
            'title-page-guide-media-metadata',
            'video-manifest-resource',
            'video-navigation',
            'wasteland',
            'xhtml-semantics-spine',
        ];

        $t->same(48, count($epubFiles), 'Checked-in EPUB fixture count changed');
        $t->same(48, count($nativeFiles), 'Checked-in native fixture count changed');

        $harness = new EpubNativeAstPackageComparisonHarness();
        $report = $harness->run($root);
        $text = $harness->formatReport($report);

        $t->same('completed', $report['status']);
        $t->same(48, $report['totalEpubCount']);
        $t->same(48, $report['comparedEpubCount']);
        $t->same(48, $report['packageParsedCount']);
        $t->same(48, $report['readerParsedCount']);
        $t->same(0, $report['packageParseFailureCount']);
        $t->same(0, $report['readerParseFailureCount']);
        $t->same(48, $report['totalPairCount']);
        $t->same(48, $report['comparedPairCount']);
        $t->same(48, $report['epubPairParsedCount']);
        $t->same(48, $report['nativeParsedCount']);
        $t->same(48, $report['bothParsedCount']);
        $t->same(0, $report['astParseFailureCount']);
        $t->same(0, $report['nativeParseFailureCount']);
        $t->same(48, $report['normalizedAstMatchCount']);
        $t->same(0, $report['normalizedAstMismatchCount']);
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredPackageParity($report, 48));
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredNativeReadiness($report, 48));
        $t->same(false, EpubNativeAstPackageComparisonHarness::hasRequiredMappedParity($report, 2));
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredMappedParity($report, 48));
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredFixtureIdentity($report));
        $t->same('valid-checked-in-current-epub-fixture-identity', $report['fixtureIdentity']['validation']['status']);
        $t->same([], $report['fixtureIdentity']['validation']['issues']);
        $t->same(96, $report['fixtureIdentity']['expectedFileCount']);
        $t->same(96, $report['fixtureIdentity']['observedFileCount']);
        $observedFixtureIdentity = [];
        foreach ($report['fixtureIdentity']['files'] as $file) {
            $observedFixtureIdentity[$file['path']] = [
                'sha256' => $file['sha256'],
                'bytes' => $file['bytes'],
            ];
            $t->same(true, $file['matchesExpected']);
        }
        $t->same($expectedFixtureIdentity, $observedFixtureIdentity);
        foreach ($expectedPackageFeatureCoverage as $key => $expected) {
            $t->same($expected, $report['packageFeatureCoverage'][$key]);
        }
        $t->same('checked-in-current-epub-package-feature-signature', $report['packageFeatureSignature']['kind']);
        $t->same('sha256-canonical-json-v1', $report['packageFeatureSignature']['algorithm']);
        $t->same('checked-in-current-upstream-epub-reader-48-fixture-snapshot', $report['packageFeatureSignature']['scope']);
        $t->same($expectedPackageFeatureSignatureSha256, $report['packageFeatureSignature']['sha256']);
        $t->same($expectedPackageFeatureSignatureSha256, $report['packageFeatureSignature']['expectedSha256']);
        $t->same(true, $report['packageFeatureSignature']['hashMatchesExpected']);
        $t->same(true, $report['packageFeatureSignature']['matchesExpected']);
        $t->same('valid-checked-in-current-epub-package-feature-signature', $report['packageFeatureSignature']['validation']['status']);
        $t->same([], $report['packageFeatureSignature']['validation']['issues']);
        $t->same(true, $report['packageFeatureSignature']['validation']['packageFeatureCoverageMatchesExpected']);
        $t->same('checked-in-current-epub-normalized-native-ast-signature', $report['currentNativeAstSignature']['kind']);
        $t->same('sha256-canonical-json-v1', $report['currentNativeAstSignature']['algorithm']);
        $t->same('checked-in-current-upstream-epub-reader-48-fixture-normalized-ast-snapshot', $report['currentNativeAstSignature']['scope']);
        $t->same(48, $report['currentNativeAstSignature']['fixtureCount']);
        $t->same(48, $report['currentNativeAstSignature']['expectedFixtureCount']);
        $t->same($expectedCurrentNativeAstFixtures, $report['currentNativeAstSignature']['expectedFixtures']);
        $t->same($expectedCurrentNativeAstFixtures, $report['currentNativeAstSignature']['observedFixtures']);
        $t->same($expectedCurrentNativeAstSignatureSha256, $report['currentNativeAstSignature']['sha256']);
        $t->same($expectedCurrentNativeAstSignatureSha256, $report['currentNativeAstSignature']['expectedSha256']);
        $t->same(true, $report['currentNativeAstSignature']['hashMatchesExpected']);
        $t->same(true, $report['currentNativeAstSignature']['matchesExpected']);
        $t->same('valid-checked-in-current-epub-normalized-native-ast-signature', $report['currentNativeAstSignature']['validation']['status']);
        $t->same([], $report['currentNativeAstSignature']['validation']['issues']);
        $t->same(true, $report['currentNativeAstSignature']['validation']['fixturesMatchExpected']);
        $t->same(true, $report['currentNativeAstSignature']['validation']['normalizedAstComparisonMatchesExpected']);
        $t->same(48, count($report['packageFeatureCoverage']['fixtureFeatureSignatures']));
        $t->same(48, count($report['currentNativeAstSignature']['fixtureSignatures']));
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['loa', 'toc'],
            'manifestResourceKindCounts' => [
                'audio' => 1,
                'navigation' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['audio-navigation']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['audio-navigation']['normalizedAstMatches']);
        $t->same(
            '3cb66c99d311a38d412adfd4d7b750826659b6ed16faefb8693623c66a5d7e94',
            $report['currentNativeAstSignature']['fixtureSignatures']['audio-navigation']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['audio-navigation']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['audio-navigation']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['audio-navigation']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['lot', 'toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => ['index' => 1],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['auxiliary-lot-guide-index']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['auxiliary-lot-guide-index']['normalizedAstMatches']);
        $t->same(
            'ab0659616271be990942bb2ac02102fdf2ebae25fc07c5b21d8d18578a659a74',
            $report['currentNativeAstSignature']['fixtureSignatures']['auxiliary-lot-guide-index']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['auxiliary-lot-guide-index']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['auxiliary-lot-guide-index']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['auxiliary-lot-guide-index']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['landmarks', 'page-list', 'toc'],
            'manifestResourceKindCounts' => [
                'audio' => 1,
                'image' => 2,
                'navigation' => 1,
                'xhtml' => 2,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['content-image-nav-media']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['content-image-nav-media']['normalizedAstMatches']);
        $t->same(
            '5968335b649e02fb35c6c0f1455c6327f4195c1ab00dc57c96a1a6ae02563ec7',
            $report['currentNativeAstSignature']['fixtureSignatures']['content-image-nav-media']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['content-image-nav-media']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['content-image-nav-media']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph', 'heading', 'paragraph', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['content-image-nav-media']['epubTopTypes']);
        $t->same([
            'navigationType' => '',
            'navigationSectionTypes' => [],
            'manifestResourceKindCounts' => [
                'image' => 3,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['direct-image-spine']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['direct-image-spine']['normalizedAstMatches']);
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['direct-image-spine']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['direct-image-spine']['nativeNormalizedAstSha256']
        );
        $t->same(
            ['paragraph', 'paragraph', 'paragraph', 'paragraph', 'paragraph', 'paragraph'],
            $report['currentNativeAstSignature']['fixtureSignatures']['direct-image-spine']['epubTopTypes']
        );
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['duplicate-spine-idref']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['duplicate-spine-idref']['normalizedAstMatches']);
        $t->same(
            '3110079f803100f0acc9f4f36aecbd325f9f93e984e3c50e715bdccdf879a6e2',
            $report['currentNativeAstSignature']['fixtureSignatures']['duplicate-spine-idref']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['duplicate-spine-idref']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['duplicate-spine-idref']['nativeNormalizedAstSha256']
        );
        $t->same(
            ['paragraph', 'div', 'paragraph', 'div'],
            $report['currentNativeAstSignature']['fixtureSignatures']['duplicate-spine-idref']['epubTopTypes']
        );
        $t->same([
            'navigationType' => 'ncx',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'xhtml' => 2,
            ],
            'guideReferenceTypeCounts' => ['text' => 1],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['epub3-ncx-toc-fallback']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['epub3-ncx-toc-fallback']['normalizedAstMatches']);
        $t->same(
            '811fcb41c24f28c59348fa1c0028ec56f0fe93f62963ae73915abab8cb916bd6',
            $report['currentNativeAstSignature']['fixtureSignatures']['epub3-ncx-toc-fallback']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['epub3-ncx-toc-fallback']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['epub3-ncx-toc-fallback']['nativeNormalizedAstSha256']
        );
        $t->same(
            ['paragraph', 'heading', 'paragraph', 'paragraph', 'div'],
            $report['currentNativeAstSignature']['fixtureSignatures']['epub3-ncx-toc-fallback']['epubTopTypes']
        );
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'font' => 1,
                'navigation' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['font-manifest-resource']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['font-manifest-resource']['normalizedAstMatches']);
        $t->same(
            '34d2fc8c46ff3314b2555f90993cb018110bc4a4620041114b84de22a3a95cb9',
            $report['currentNativeAstSignature']['fixtureSignatures']['font-manifest-resource']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['font-manifest-resource']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['font-manifest-resource']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['font-manifest-resource']['epubTopTypes']);
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['wasteland']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['wasteland']['nativeNormalizedAstSha256']
        );
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['wasteland']['normalizedAstMatches']);
        $t->same(['paragraph', 'paragraph', 'div', 'div', 'div'], $report['currentNativeAstSignature']['fixtureSignatures']['wasteland']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => ['bibliography' => 1],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['guide-bibliography-reference']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['guide-bibliography-reference']['normalizedAstMatches']);
        $t->same(
            '7f58cf0c7bd15cac51af2623dd83020055238ceb7165ce8a60fb7b5b0abf662c',
            $report['currentNativeAstSignature']['fixtureSignatures']['guide-bibliography-reference']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['guide-bibliography-reference']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['guide-bibliography-reference']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['guide-bibliography-reference']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => ['glossary' => 1],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['guide-glossary-reference']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['guide-glossary-reference']['normalizedAstMatches']);
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['guide-glossary-reference']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => ['notes' => 1],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['guide-notes-reference']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['guide-notes-reference']['normalizedAstMatches']);
        $t->same(
            'e2512ca110620900b1b28ef571bdaee3c7ad79f07c0c027f019f413e13a181a6',
            $report['currentNativeAstSignature']['fixtureSignatures']['guide-notes-reference']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['guide-notes-reference']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['guide-notes-reference']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['guide-notes-reference']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => ['preface' => 1],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['guide-preface-reference']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['guide-preface-reference']['normalizedAstMatches']);
        $t->same(
            '087106b3966cc944f8e47774d969eb71ca333d91e2125b70096444ba609e1f23',
            $report['currentNativeAstSignature']['fixtureSignatures']['guide-preface-reference']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['guide-preface-reference']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['guide-preface-reference']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['guide-preface-reference']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['landmarks', 'toc'],
            'manifestResourceKindCounts' => [
                'cover-image' => 1,
                'image' => 3,
                'navigation' => 1,
                'style' => 2,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => true,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['img']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'audio' => 1,
                'media-overlay' => 1,
                'navigation' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['media-overlay-package']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['media-overlay-package']['normalizedAstMatches']);
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['media-overlay-package']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['landmarks', 'toc'],
            'manifestResourceKindCounts' => [
                'cover-image' => 1,
                'navigation' => 2,
                'style' => 2,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [
                'cc:attributionURL' => 1,
                'cc:license' => 2,
            ],
            'coverImagePartPresent' => true,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['wasteland']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['landmarks', 'toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'style' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => ['text' => 1],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['xhtml-semantics-spine']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['xhtml-semantics-spine']['normalizedAstMatches']);
        $t->same(
            'a0feee13fa730fb1e7bfe371a5f0f6df7541d756a8a9a68fe694899f43e54b39',
            $report['currentNativeAstSignature']['fixtureSignatures']['xhtml-semantics-spine']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['xhtml-semantics-spine']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['xhtml-semantics-spine']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph', 'div'], $report['currentNativeAstSignature']['fixtureSignatures']['xhtml-semantics-spine']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['loi', 'page-list', 'toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['page-list-navigation']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['landmarks', 'loa', 'page-list', 'toc'],
            'manifestResourceKindCounts' => [
                'asset' => 1,
                'image' => 1,
                'navigation' => 1,
                'style' => 1,
                'xhtml' => 3,
            ],
            'guideReferenceTypeCounts' => ['title-page' => 1],
            'packageLinkRelCounts' => ['preview' => 1],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['title-page-guide-media-metadata']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['title-page-guide-media-metadata']['normalizedAstMatches']);
        $t->same(
            'f84e4b08cb10ced13d2940f9b231e7f73928a23c39a04b555349189a3a665824',
            $report['currentNativeAstSignature']['fixtureSignatures']['title-page-guide-media-metadata']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['title-page-guide-media-metadata']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['title-page-guide-media-metadata']['nativeNormalizedAstSha256']
        );
        $t->same(
            ['paragraph', 'heading', 'paragraph', 'paragraph', 'paragraph', 'heading', 'paragraph'],
            $report['currentNativeAstSignature']['fixtureSignatures']['title-page-guide-media-metadata']['epubTopTypes']
        );
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'style' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['missing-local-manifest-resource']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['missing-local-manifest-resource']['normalizedAstMatches']);
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['missing-local-manifest-resource']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['missing-media-overlay']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['missing-media-overlay']['normalizedAstMatches']);
        $t->same(
            '5c6b968ab91425b25e28244c893adc4f150cfa6edce43fb38f2d785a20796820',
            $report['currentNativeAstSignature']['fixtureSignatures']['missing-media-overlay']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['missing-media-overlay']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['missing-media-overlay']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['missing-media-overlay']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['landmarks', 'toc'],
            'manifestResourceKindCounts' => [
                'asset' => 1,
                'navigation' => 2,
                'xhtml' => 2,
            ],
            'guideReferenceTypeCounts' => ['text' => 1],
            'packageLinkRelCounts' => ['record' => 1],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['nav-ncx-linear-guide']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['nav-ncx-linear-guide']['normalizedAstMatches']);
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['nav-ncx-linear-guide']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['landmarks', 'toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'style' => 1,
                'xhtml' => 2,
            ],
            'guideReferenceTypeCounts' => ['text' => 1],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['nested-rootfile-nonlinear-spine']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['nested-rootfile-nonlinear-spine']['normalizedAstMatches']);
        $t->same(
            '5d71e3437d7e47b0e1ddb4d34daec11a5cca6278e6b3ddd33df9f87b9d012d3b',
            $report['currentNativeAstSignature']['fixtureSignatures']['nested-rootfile-nonlinear-spine']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['nested-rootfile-nonlinear-spine']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['nested-rootfile-nonlinear-spine']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['nested-rootfile-nonlinear-spine']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'style' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['remote-manifest-resource']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['remote-manifest-resource']['normalizedAstMatches']);
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['remote-manifest-resource']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['rendition-layout-property']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['rendition-layout-property']['normalizedAstMatches']);
        $t->same(
            '79d9343d9cec496e748abae4bee9d1f702bbee428e10095fc41a56e23678fbfc',
            $report['currentNativeAstSignature']['fixtureSignatures']['rendition-layout-property']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['rendition-layout-property']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['rendition-layout-property']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['rendition-layout-property']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'svg' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['scripted-svg-manifest']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['scripted-svg-manifest']['normalizedAstMatches']);
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['scripted-svg-manifest']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'script' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['scripted-xhtml-resource']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['scripted-xhtml-resource']['normalizedAstMatches']);
        $t->same(
            '1f4c061f0de6f7644ba99d2a65dc4dfb0493e5a477fa6b3c468118c27c943938',
            $report['currentNativeAstSignature']['fixtureSignatures']['scripted-xhtml-resource']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['scripted-xhtml-resource']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['scripted-xhtml-resource']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['scripted-xhtml-resource']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['landmarks', 'page-list', 'toc'],
            'manifestResourceKindCounts' => [
                'asset' => 1,
                'navigation' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['spine-fallback-resource']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['spine-fallback-resource']['normalizedAstMatches']);
        $t->same(
            '9c802777ac1eebbafbd98381c10786b59e51014d3129e3959a3490737f0f392d',
            $report['currentNativeAstSignature']['fixtureSignatures']['spine-fallback-resource']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['spine-fallback-resource']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['spine-fallback-resource']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['spine-fallback-resource']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'video' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['video-manifest-resource']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['video-manifest-resource']['normalizedAstMatches']);
        $t->same(
            '570243f3adde1f1cdb6c4decdcbcd8d1f1b0648f90d06cdd617fc8856609f5b3',
            $report['currentNativeAstSignature']['fixtureSignatures']['video-manifest-resource']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['video-manifest-resource']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['video-manifest-resource']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['video-manifest-resource']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'asset' => 1,
                'navigation' => 1,
                'xhtml' => 2,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['manifest-fallback-chain']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['manifest-fallback-chain']['normalizedAstMatches']);
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['manifest-fallback-chain']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'asset' => 2,
                'navigation' => 1,
                'style' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['media-manifest-mix']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['media-manifest-mix']['normalizedAstMatches']);
        $t->same(
            '9186ca2cf7bb65985000a37b32a279f14caaf87c14263c284d6ea25e419130ec',
            $report['currentNativeAstSignature']['fixtureSignatures']['media-manifest-mix']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['media-manifest-mix']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['media-manifest-mix']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['media-manifest-mix']['epubTopTypes']);
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredCurrentPackageFeatureCoverage($report));
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredCurrentPackageFeatureSignature($report));
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredCurrentNativeAstSignature($report));
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRunnerNotRunEvidence($report));
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRunnerPlanEvidence($report));
        $t->same('not-run', $report['runnerEvidence']['status']);
        $t->same(false, $report['runnerEvidence']['executed']);
        $t->same(null, $report['runnerEvidence']['command']);
        $t->same(null, $report['runnerEvidence']['resultArtifact']);
        $t->same('planned-not-run', $report['runnerEvidence']['commandPlanStatus']);
        $t->same(EpubNativeAstPackageComparisonHarness::EXPECTED_UPSTREAM_COMMIT, $report['runnerEvidence']['upstreamBinding']['expectedCommit']);
        $t->same('exe:pandoc', $report['runnerEvidence']['upstreamBinding']['executableTarget']);
        $t->same('test/epub', $report['runnerEvidence']['upstreamBinding']['fixtureDirectory']);
        $t->same('exe:pandoc', $report['runnerEvidence']['target']['cabalTarget']);
        $t->same('epub', $report['runnerEvidence']['target']['inputFormat']);
        $t->same('native', $report['runnerEvidence']['target']['outputFormat']);
        $t->same('test/epub', $report['runnerEvidence']['target']['fixtureDirectory']);
        $t->same($expectedCurrentNativeAstFixtures, $report['runnerEvidence']['target']['fixtureBasenames']);
        $t->same(96, $report['runnerEvidence']['checkedInSnapshot']['expectedFileCount']);
        $t->same(48, $report['runnerEvidence']['checkedInSnapshot']['expectedPairCount']);
        $t->same($expectedPackageFeatureSignatureSha256, $report['runnerEvidence']['checkedInSnapshot']['packageFeatureSignature']);
        $t->same($expectedCurrentNativeAstSignatureSha256, $report['runnerEvidence']['checkedInSnapshot']['nativeAstSignature']);
        $t->same('exe:pandoc', $report['runnerEvidence']['futureCommands'][2]['arguments'][4]);
        $t->same('test/epub/{fixture}.epub', $report['runnerEvidence']['futureCommands'][2]['arguments'][10]);
        $t->true(in_array('.port-libs/pandoc-runner/logs/epub-native-package-native-generation.txt', $report['runnerEvidence']['requiredTranscripts'], true));
        $t->true(in_array('.port-libs/pandoc-runner/artifacts/epub-native-package/generated-native-manifest.json', $report['runnerEvidence']['requiredArtifacts'], true));
        $mutatedReport = $report;
        $mutatedReport['runnerEvidence']['target']['outputFormat'] = 'json';
        $t->same(false, EpubNativeAstPackageComparisonHarness::hasRunnerPlanEvidence($mutatedReport));
        $t->same('covered-by-current-package-evidence', $report['orderedRemainingGaps'][0]['status']);
        $t->same('covered-by-current-normalized-ast-evidence', $report['orderedRemainingGaps'][1]['status']);
        $t->contains('packages: total=48 compared=48 packageParsed=48 readerParsed=48 packageFailures=0 readerFailures=0', $text);
        $t->contains('normalizedAst: matches=48 (100.00%) mismatches=0', $text);
        $t->contains('fixtureIdentity: status=valid-checked-in-current-epub-fixture-identity expected=96 observed=96', $text);
        $t->contains('packageFeatureCoverage: fixtures=48 nav=43 ncx=4 covers=5 landmarks=17 pageLists=8 auxiliaryNav=6 metadataCreators=45 manifestItems=182', $text);
        $t->contains('spineLinear=linear:63,non-linear:14 nonLinearSpineFixtures=14 spinePageSpread=left:2,right:2 pageSpreadFixtures=2', $text);
        $t->contains('packageFeatureSignature: status=valid-checked-in-current-epub-package-feature-signature matchesExpected=true sha256=' . $expectedPackageFeatureSignatureSha256, $text);
        $t->contains('currentNativeAstSignature: status=valid-checked-in-current-epub-normalized-native-ast-signature matchesExpected=true fixtures=48 sha256=' . $expectedCurrentNativeAstSignatureSha256, $text);
        $t->contains('runnerEvidence: status=not-run plan=planned-not-run executed=false', $text);
        $t->contains('resourceKinds=asset:9,audio:4,cover-image:3,font:1,image:18,media-overlay:1,navigation:49,script:1,style:22,svg:1,video:2,xhtml:71', $text);
        $t->contains('guideRefTypes=bibliography:1,cover:3,glossary:1,index:1,notes:1,preface:1,text:7,title-page:1,toc:1', $text);
        $t->contains('packageLinkRels=alternate:1,cc:attributionURL:1,cc:license:2,preview:1,record:5', $text);
        $t->contains('remoteManifest=3 externalManifest=3 missingLocalManifest=1 manifestFallbackItems=10 manifestFallbacks=5 resolvedFallbacks=4 usableFallbacks=4 missingFallbacks=6', $text);
        $t->contains('mediaOverlayFixtures=2 resolvedMediaOverlayFixtures=1 mediaOverlays=2 resolvedMediaOverlays=1 mediaOverlayTextTargets=1 mediaOverlayAudioTargets=1 mediaOverlayDurations=3', $text);
        $t->contains('encryptionFixtures=3 obfuscatedFontFixtures=3 blockedEncryptedByteExposureFixtures=3 encryptionItems=3 obfuscatedFonts=3 blockedEncryptedByteExposures=3 encryptionDiagnostics=6 encryptionRoles=font:3', $text);
        $t->contains('collectionFixtures=1 collections=2 collectionLinks=3 collectionRoles=index:1,role:primary:1,schema:hasPart:1 collectionLinkRels=contents:1,index:1,record:1', $text);
        $t->contains('bindingFixtures=1 bindings=1 bindingResolvedHandlers=1 bindingParams=1 bindingMediaTypes=application/x-bound-widget:1', $text);
        $t->contains('ocfSidecarFixtures=1 ocfSidecars=4 ocfSidecarKinds=manifest:1,metadata:1,rights:1,signatures:1', $text);
        $t->contains('opfParts=/EPUB/package.opf:35,/EPUB/wasteland.opf:1,/OEBPS/content.opf:3,/OPS/book/package.opf:3,/OPS/package.opf:6', $text);

        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-native-ast-package.php')
            . ' --checked-in-fixtures'
            . ' --json'
            . ' summary'
            . ' --require-fixture-identity'
            . ' --require-current-package-feature-coverage'
            . ' --require-current-package-feature-signature'
            . ' --require-current-native-ast-signature'
            . ' --require-runner-plan'
            . ' --require-package-parity=48'
            . ' --require-native-readiness=48'
            . ' --require-mapped-parity=48';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same($root, $decoded['upstreamEpubDirectory']);
        $t->same(48, $decoded['packageParsedCount']);
        $t->same(48, $decoded['readerParsedCount']);
        $t->same(48, $decoded['nativeParsedCount']);
        $t->same(48, $decoded['normalizedAstMatchCount']);
        $t->same(0, $decoded['normalizedAstMismatchCount']);
        $t->same('valid-checked-in-current-epub-fixture-identity', $decoded['fixtureIdentity']['validation']['status']);
        foreach ($expectedPackageFeatureCoverage as $key => $expected) {
            $t->same($expected, $decoded['packageFeatureCoverage'][$key]);
        }
        $t->same($expectedPackageFeatureSignatureSha256, $decoded['packageFeatureSignature']['sha256']);
        $t->same(true, $decoded['packageFeatureSignature']['matchesExpected']);
        $t->same('valid-checked-in-current-epub-package-feature-signature', $decoded['packageFeatureSignature']['validation']['status']);
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredCurrentPackageFeatureSignature($decoded));
        $t->same($expectedCurrentNativeAstSignatureSha256, $decoded['currentNativeAstSignature']['sha256']);
        $t->same(true, $decoded['currentNativeAstSignature']['matchesExpected']);
        $t->same('valid-checked-in-current-epub-normalized-native-ast-signature', $decoded['currentNativeAstSignature']['validation']['status']);
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredCurrentNativeAstSignature($decoded));
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRunnerPlanEvidence($decoded));
        $t->same('planned-not-run', $decoded['runnerEvidence']['commandPlanStatus']);
        $t->same($expectedCurrentNativeAstFixtures, $decoded['runnerEvidence']['target']['fixtureBasenames']);
        $t->same(
            $report['packageFeatureCoverage']['fixtureFeatureSignatures'],
            $decoded['packageFeatureCoverage']['fixtureFeatureSignatures']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures'],
            $decoded['currentNativeAstSignature']['fixtureSignatures']
        );

        $defaultFixtureIdentityCommand = 'env -u PANDOC_UPSTREAM_EPUB_DIR '
            . escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-native-ast-package.php')
            . ' --json'
            . ' summary'
            . ' --require-package-parity=48'
            . ' --require-native-readiness=48'
            . ' --require-mapped-parity=48'
            . ' --require-fixture-identity'
            . ' --require-current-package-feature-coverage'
            . ' --require-current-package-feature-signature'
            . ' --require-current-native-ast-signature'
            . ' --require-runner-plan';
        $defaultFixtureIdentityOutput = [];
        $defaultFixtureIdentityExitCode = 0;
        exec($defaultFixtureIdentityCommand, $defaultFixtureIdentityOutput, $defaultFixtureIdentityExitCode);
        $defaultFixtureIdentityDecoded = json_decode(
            implode("\n", $defaultFixtureIdentityOutput),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $t->same(0, $defaultFixtureIdentityExitCode);
        $t->same($root, $defaultFixtureIdentityDecoded['upstreamEpubDirectory']);
        $t->same(48, $defaultFixtureIdentityDecoded['normalizedAstMatchCount']);
        $t->same('valid-checked-in-current-epub-fixture-identity', $defaultFixtureIdentityDecoded['fixtureIdentity']['validation']['status']);
        $t->same($expectedPackageFeatureSignatureSha256, $defaultFixtureIdentityDecoded['packageFeatureSignature']['sha256']);
        $t->same(true, $defaultFixtureIdentityDecoded['packageFeatureSignature']['matchesExpected']);
        $t->same($expectedCurrentNativeAstSignatureSha256, $defaultFixtureIdentityDecoded['currentNativeAstSignature']['sha256']);
        $t->same(true, $defaultFixtureIdentityDecoded['currentNativeAstSignature']['matchesExpected']);
        $t->same($expectedCurrentNativeAstFixtures, $defaultFixtureIdentityDecoded['currentNativeAstSignature']['observedFixtures']);
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRunnerPlanEvidence($defaultFixtureIdentityDecoded));
        $t->same('exe:pandoc', $defaultFixtureIdentityDecoded['runnerEvidence']['target']['cabalTarget']);
        $t->same($expectedPackageFeatureCoverage['opfPartNameCounts'], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['opfPartNameCounts']);
        $t->same($expectedPackageFeatureCoverage['navigationTypeCounts'], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['navigationTypeCounts']);
        $t->same($expectedPackageFeatureCoverage['spineLinearStateCounts'], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['spineLinearStateCounts']);
        $t->same($expectedPackageFeatureCoverage['fixturesWithCreators'], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['fixturesWithCreators']);
        $t->same($expectedPackageFeatureCoverage['fixturesWithManifestFallbackItems'], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['fixturesWithManifestFallbackItems']);
        $t->same($expectedPackageFeatureCoverage['fixturesWithResolvedManifestFallbacks'], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['fixturesWithResolvedManifestFallbacks']);
        $t->same($expectedPackageFeatureCoverage['fixturesWithUsableManifestFallbacks'], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['fixturesWithUsableManifestFallbacks']);
        $t->same($expectedPackageFeatureCoverage['fixturesWithMissingManifestFallbacks'], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['fixturesWithMissingManifestFallbacks']);
        $t->same($expectedPackageFeatureCoverage['totals']['metadataCreators'], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['totals']['metadataCreators']);
        $t->same($expectedPackageFeatureCoverage['manifestPropertyCounts'], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['manifestPropertyCounts']);
        $t->same($expectedPackageFeatureCoverage['manifestResourceKindCounts'], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['manifestResourceKindCounts']);
        $t->same($expectedPackageFeatureCoverage['guideReferenceTypeCounts'], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['guideReferenceTypeCounts']);
        $t->same($expectedPackageFeatureCoverage['packageLinkRelCounts'], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['packageLinkRelCounts']);
        $t->same(
            $report['packageFeatureCoverage']['fixtureFeatureSignatures']['wasteland'],
            $defaultFixtureIdentityDecoded['packageFeatureCoverage']['fixtureFeatureSignatures']['wasteland']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['wasteland'],
            $defaultFixtureIdentityDecoded['currentNativeAstSignature']['fixtureSignatures']['wasteland']
        );

        $failingSignatureCommand = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-native-ast-package.php')
            . ' --checked-in-fixtures'
            . ' --json'
            . ' summary'
            . ' --limit=7'
            . ' --require-current-package-feature-signature'
            . ' 2>/dev/null';
        $failingSignatureOutput = [];
        $failingSignatureExitCode = 0;
        exec($failingSignatureCommand, $failingSignatureOutput, $failingSignatureExitCode);

        $t->same(1, $failingSignatureExitCode);

        $failingNativeAstSignatureCommand = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-native-ast-package.php')
            . ' --checked-in-fixtures'
            . ' --json'
            . ' summary'
            . ' --limit=7'
            . ' --require-current-native-ast-signature'
            . ' 2>/dev/null';
        $failingNativeAstSignatureOutput = [];
        $failingNativeAstSignatureExitCode = 0;
        exec($failingNativeAstSignatureCommand, $failingNativeAstSignatureOutput, $failingNativeAstSignatureExitCode);

        $t->same(1, $failingNativeAstSignatureExitCode);
    },

    'cli gates epub package and native readiness without requiring ast equality' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeEpub): void {
        $root = $makeTempDir();
        try {
            $writeEpub($root . '/same.epub', 'Same', 'Hello body');
            file_put_contents($root . '/same.native', '[Para [Span ("chapter.xhtml",[],[]) []],Header 1 ("",[],[]) [Str "Same"],Para [Str "Hello",Space,Str "body"]]');
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
