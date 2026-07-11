<?php

declare(strict_types=1);

use PortLibs\Pandoc\EpubNativeAstPackageComparisonHarness;
use PortLibs\Pandoc\EpubPackage;
use PortLibs\Pandoc\AstNode;
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
    <meta property="schema:accessMode">textual</meta>
    <meta property="schema:accessMode" content="visual"/>
    <meta property="schema:accessibilityFeature">alternativeText</meta>
    <meta name="schema:accessibilityHazard" content="noFlashingHazard"/>
    <meta property="schema:accessibilitySummary">Generated package accessibility summary.</meta>
    <meta property="dcterms:conformsTo">EPUB Accessibility 1.1 - WCAG 2.1 AA</meta>
    <link id="review-record" rel="record accessibility-summary" href="review.json?profile=accessibility#summary" media-type="application/ld+json;profile=schema-a11y" properties="accessibility-metadata"/>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="review" href="review.json" media-type="application/ld+json" properties="accessibility-metadata"/>
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
    'normalizes Pandoc JSON whitespace and metadata provenance like textual Native output' => static function (TestRunner $t): void {
        $native = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Hello world']),
            ]),
        ]);
        $json = new AstNode('document', [
            'metaConstructorProvenance' => ['/title' => ['constructor' => 'MetaInlines']],
            'metaNativeValues' => ['title' => ['t' => 'MetaInlines']],
        ], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Hello']),
                new AstNode('space'),
                new AstNode('text', ['text' => 'world']),
            ]),
        ]);

        $harness = new EpubNativeAstPackageComparisonHarness();

        $t->same($harness->normalizedDocument($native), $harness->normalizedDocument($json));
    },
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
            $t->same('package-and-reader-implementation-equivalence-observed', $report['packageAcceptanceStatus']);
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
            $t->same(['accessibility-summary' => 1, 'record' => 1], $summary['packageLinkRelCounts']);
            $t->same(2, $summary['packageLinkVocabularyRelTokenCount']);
            $t->same(1, $summary['packageLinkVocabularyPropertyTokenCount']);
            $t->same(0, $summary['packageLinkVocabularyResolvedTokenCount']);
            $t->same(0, $summary['packageLinkVocabularyAbsoluteUrlTokenCount']);
            $t->same(0, $summary['packageLinkVocabularyDuplicateTokenCount']);
            $t->same(0, $summary['packageLinkVocabularyDiagnosticCount']);
            $t->same(['accessibility-summary' => 1, 'record' => 1], $summary['packageLinkVocabularyRelCounts']);
            $t->same(['accessibility-metadata' => 1], $summary['packageLinkVocabularyPropertyCounts']);
            $t->same(1, $summary['packageLinkMediaTypeCount']);
            $t->same(['application/ld+json' => 1], $summary['packageLinkMediaTypeCounts']);
            $t->same(1, $summary['packageLinkMediaTypeParameterCount']);
            $t->same(['profile' => 1], $summary['packageLinkMediaTypeParameterNameCounts']);
            $t->same(1, $summary['linkHrefSuffixCount']);
            $t->same(1, $summary['linkHrefSuffixQueryCount']);
            $t->same(1, $summary['linkHrefSuffixFragmentCount']);
            $t->same(['package-link' => 1], $summary['linkHrefSuffixSourceCounts']);
            $t->same(true, $summary['accessibilityPresent']);
            $t->same(6, $summary['accessibilityEntryCount']);
            $t->same([
                'accessMode' => 2,
                'accessibilityFeature' => 1,
                'accessibilityHazard' => 1,
                'accessibilitySummary' => 1,
                'conformsTo' => 1,
            ], $summary['accessibilityPropertyCounts']);
            $t->same(1, $summary['accessibilityLinkedRecordCount']);
            $t->same(2, $summary['accessibilityAccessModeCount']);
            $t->same(1, $summary['accessibilityFeatureCount']);
            $t->same(1, $summary['accessibilityHazardCount']);
            $t->same(1, $summary['accessibilityConformsToCount']);
            $t->same(1, $summary['guideReferenceCount']);
            $t->same(['text' => 1], $summary['guideReferenceTypeCounts']);
            $t->same(4, $summary['manifestItemCount']);
            $t->same([
                'application/ld+json' => 1,
                'application/xhtml+xml' => 2,
                'text/css' => 1,
            ], $summary['manifestMediaTypeCounts']);
            $t->same([
                'accessibility-metadata' => 1,
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
                'application/ld+json' => 1,
                'application/xhtml+xml' => 2,
                'text/css' => 1,
            ], $coverage['manifestMediaTypeCounts']);
            $t->same([
                'accessibility-metadata' => 1,
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
            $t->same(['accessibility-summary' => 1, 'record' => 1], $coverage['packageLinkRelCounts']);
            $t->same(['accessibility-summary' => 1, 'record' => 1], $coverage['packageLinkVocabularyRelCounts']);
            $t->same(['accessibility-metadata' => 1], $coverage['packageLinkVocabularyPropertyCounts']);
            $t->same(['application/ld+json' => 1], $coverage['packageLinkMediaTypeCounts']);
            $t->same(['profile' => 1], $coverage['packageLinkMediaTypeParameterNameCounts']);
            $t->same(['package-link' => 1], $coverage['linkHrefSuffixSourceCounts']);
            $t->same([
                'accessMode' => 2,
                'accessibilityFeature' => 1,
                'accessibilityHazard' => 1,
                'accessibilitySummary' => 1,
                'conformsTo' => 1,
            ], $coverage['accessibilityPropertyCounts']);
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
                    'packageLinkRelCounts' => ['accessibility-summary' => 1, 'record' => 1],
                    'packageLinkVocabularyRelCounts' => ['accessibility-summary' => 1, 'record' => 1],
                    'packageLinkVocabularyPropertyCounts' => ['accessibility-metadata' => 1],
                    'accessibilityPropertyCounts' => [
                        'accessMode' => 2,
                        'accessibilityFeature' => 1,
                        'accessibilityHazard' => 1,
                        'accessibilitySummary' => 1,
                        'conformsTo' => 1,
                    ],
                    'accessibilityLinkedRecordCount' => 1,
                    'coverImagePartPresent' => false,
                ],
            ], $coverage['fixtureFeatureSignatures']);
            $t->same(['generated-navigation'], $coverage['fixturesWithGuideReferences']);
            $t->same(['generated-navigation'], $coverage['fixturesWithPackageLinks']);
            $t->same(['generated-navigation'], $coverage['fixturesWithPackageLinkVocabulary']);
            $t->same([], $coverage['fixturesWithPackageLinkVocabularyDiagnostics']);
            $t->same(['generated-navigation'], $coverage['fixturesWithPackageLinkMediaTypeParameters']);
            $t->same(['generated-navigation'], $coverage['fixturesWithLinkHrefSuffixes']);
            $t->same(['generated-navigation'], $coverage['fixturesWithAccessibilityMetadata']);
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
                'pageListCfiTargets' => 0,
                'auxiliaryNavigationEntries' => 1,
                'packageLinks' => 1,
                'packageLinkVocabularyRelTokens' => 2,
                'packageLinkVocabularyPropertyTokens' => 1,
                'packageLinkVocabularyResolvedTokens' => 0,
                'packageLinkVocabularyAbsoluteUrlTokens' => 0,
                'packageLinkVocabularyDuplicateTokens' => 0,
                'packageLinkVocabularyDiagnostics' => 0,
                'packageLinkMediaTypeItems' => 1,
                'packageLinkMediaTypeParameters' => 1,
                'linkHrefSuffixes' => 1,
                'linkHrefSuffixQueries' => 1,
                'linkHrefSuffixFragments' => 1,
                'guideReferences' => 1,
                'accessibilityEntries' => 6,
                'accessibilityLinkedRecords' => 1,
                'accessibilityAccessModes' => 2,
                'accessibilityFeatures' => 1,
                'accessibilityHazards' => 1,
                'accessibilityConformsTo' => 1,
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
                'mediaOverlayTimelineItems' => 0,
                'mediaOverlayClipTimings' => 0,
                'mediaOverlayValidClipTimings' => 0,
                'mediaOverlayInvalidClipTimings' => 0,
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
            $t->same(
                ['accessibility-metadata' => 1, 'nav' => 1, 'remote-resources' => 1],
                $decoded['packageFeatureCoverage']['manifestPropertyCounts']
            );
            $t->same([
                'asset' => 1,
                'navigation' => 1,
                'style' => 1,
                'xhtml' => 1,
            ], $decoded['packageFeatureCoverage']['manifestResourceKindCounts']);
            $t->same(['text' => 1], $decoded['packageFeatureCoverage']['guideReferenceTypeCounts']);
            $t->same(
                ['accessibility-summary' => 1, 'record' => 1],
                $decoded['packageFeatureCoverage']['packageLinkRelCounts']
            );
            $t->same(
                ['accessibility-summary' => 1, 'record' => 1],
                $decoded['packageFeatureCoverage']['packageLinkVocabularyRelCounts']
            );
            $t->same(
                ['accessibility-metadata' => 1],
                $decoded['packageFeatureCoverage']['packageLinkVocabularyPropertyCounts']
            );
            $t->same(
                ['application/ld+json' => 1],
                $decoded['packageFeatureCoverage']['packageLinkMediaTypeCounts']
            );
            $t->same(
                ['profile' => 1],
                $decoded['packageFeatureCoverage']['packageLinkMediaTypeParameterNameCounts']
            );
            $t->same(
                ['package-link' => 1],
                $decoded['packageFeatureCoverage']['linkHrefSuffixSourceCounts']
            );
            $t->same(
                [
                    'accessMode' => 2,
                    'accessibilityFeature' => 1,
                    'accessibilityHazard' => 1,
                    'accessibilitySummary' => 1,
                    'conformsTo' => 1,
                ],
                $decoded['packageFeatureCoverage']['accessibilityPropertyCounts']
            );
            $t->same(['generated-navigation'], $decoded['packageFeatureCoverage']['fixturesWithAccessibilityMetadata']);
            $t->same(['generated-navigation'], $decoded['packageFeatureCoverage']['fixturesWithPackageLinkVocabulary']);
            $t->same([], $decoded['packageFeatureCoverage']['fixturesWithPackageLinkVocabularyDiagnostics']);
            $t->same(['generated-navigation'], $decoded['packageFeatureCoverage']['fixturesWithPackageLinkMediaTypeParameters']);
            $t->same(['generated-navigation'], $decoded['packageFeatureCoverage']['fixturesWithLinkHrefSuffixes']);
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
            $t->same(2, $decoded['packageFeatureCoverage']['totals']['packageLinkVocabularyRelTokens']);
            $t->same(1, $decoded['packageFeatureCoverage']['totals']['packageLinkVocabularyPropertyTokens']);
            $t->same(0, $decoded['packageFeatureCoverage']['totals']['packageLinkVocabularyResolvedTokens']);
            $t->same(0, $decoded['packageFeatureCoverage']['totals']['packageLinkVocabularyDiagnostics']);
            $t->same(1, $decoded['packageFeatureCoverage']['totals']['packageLinkMediaTypeParameters']);
            $t->same(1, $decoded['packageFeatureCoverage']['totals']['linkHrefSuffixes']);
            $t->same(6, $decoded['packageFeatureCoverage']['totals']['accessibilityEntries']);
            $t->same(1, $decoded['packageFeatureCoverage']['totals']['accessibilityLinkedRecords']);
            $t->same(0, $decoded['packageFeatureCoverage']['totals']['mediaOverlays']);
            $t->same(0, $decoded['packageFeatureCoverage']['totals']['mediaOverlayTimelineItems']);
            $t->same(0, $decoded['packageFeatureCoverage']['totals']['mediaOverlayClipTimings']);
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
        $expectedFixtureIdentity = array (
  'accessibility-metadata-package.epub' =>
  array (
    'sha256' => '1f0fc1e25c99a96af5af9a0128f2eeb72affcf8e86afbbf7f1541e2a7695c9cc',
    'bytes' => 1855,
  ),
  'accessibility-metadata-package.native' =>
  array (
    'sha256' => '99e5e68f3899c680fcb162ffb432797a07e8194d5aa7ce73b8eca93f8900cfde',
    'bytes' => 289,
  ),
  'all-nonlinear-spine.epub' =>
  array (
    'sha256' => '83fc005e5ab9feaca5c6a08b61d590d0cc3958bbe75b43b5f5a108c599e59882',
    'bytes' => 1364,
  ),
  'all-nonlinear-spine.native' =>
  array (
    'sha256' => '37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570',
    'bytes' => 3,
  ),
  'appendix-navigation-guide.epub' =>
  array (
    'sha256' => '2c7b9ca20d38dcda15b63a1bf4aa210a3b11b132dc0a8fe6bedec13d24675c4f',
    'bytes' => 1645,
  ),
  'appendix-navigation-guide.native' =>
  array (
    'sha256' => '8e9029119f437f20c1ba3fa8055beb80087bf9230c083e297ac91f3115453bed',
    'bytes' => 272,
  ),
  'audio-navigation.epub' =>
  array (
    'sha256' => '09e5d95402b3a0b34fc1843b61534fe02a58df21ec524071776698f66b8c43a2',
    'bytes' => 1509,
  ),
  'audio-navigation.native' =>
  array (
    'sha256' => '86911ce05ad45760deb8f82eb4fe1b569626b09cddcf9fabc2b45cae50b37a22',
    'bytes' => 262,
  ),
  'auxiliary-lot-guide-index.epub' =>
  array (
    'sha256' => '8581efb4630635b95af119442cb682181b0004b90d53c6c43dfa255fc1c5bb58',
    'bytes' => 1434,
  ),
  'auxiliary-lot-guide-index.native' =>
  array (
    'sha256' => 'b472ccfd6ed29dd2b8f26da6da27509abd6a2c3b0964fddda31519a75a205ee2',
    'bytes' => 331,
  ),
  'bindings-collections-sidecars.epub' =>
  array (
    'sha256' => '82cd32b901ed412a69c5080707ed566207b06030c074bffa3b83460692f07834',
    'bytes' => 3767,
  ),
  'bindings-collections-sidecars.native' =>
  array (
    'sha256' => '2dc016af0d0e6f660a7a825acebf27d3bd2a74d30cc0914651b099877774932d',
    'bytes' => 679,
  ),
  'blockquote-list-spine.epub' =>
  array (
    'sha256' => '74fbf6f8f030e88a866ba652f72d0ec6864149c38ae8df45b9efd0bb4b8d0746',
    'bytes' => 1494,
  ),
  'blockquote-list-spine.native' =>
  array (
    'sha256' => 'dc459413755a1c07b599fb45cbb3cbd4c26bcdf8cf2caef31bdeaa1029bdfbc0',
    'bytes' => 761,
  ),
  'code-block-spine.epub' =>
  array (
    'sha256' => 'edce77261e123eb3aa3ef2614978a0854900a24ae02834d68f9625d70e5f5f3b',
    'bytes' => 1364,
  ),
  'code-block-spine.native' =>
  array (
    'sha256' => '996f70134a1e706fb7525e5fbac1d2d7d7c2ae4d95bfa52a99f1cfee5e3137f8',
    'bytes' => 334,
  ),
  'content-image-nav-media.epub' =>
  array (
    'sha256' => 'd02bb4c45558841903bb5e83ea3f15af2ca00d4221236d10978b4c0d672e8ce6',
    'bytes' => 2410,
  ),
  'content-image-nav-media.native' =>
  array (
    'sha256' => '258f9b8a1b2a9c8df41cbe9142d573d52e248b45cb3872ef2c071328d0e80b34',
    'bytes' => 589,
  ),
  'cross-spine-internal-links.epub' =>
  array (
    'sha256' => '10356bf8205f5eab35bb851bf155cdd279b23804740017cf59bb28d4f10a07e5',
    'bytes' => 1701,
  ),
  'cross-spine-internal-links.native' =>
  array (
    'sha256' => '55c6607d1baee634d2e06404a7c1b7c5271880b20d86c9ccf06d097045bb7f09',
    'bytes' => 743,
  ),
  'definition-list-spine.epub' =>
  array (
    'sha256' => '0baab26570c728b891093f14904fcebd543708c902091e851dce748a76ab2fa0',
    'bytes' => 1416,
  ),
  'definition-list-spine.native' =>
  array (
    'sha256' => 'c63677d9de4ea45d6fe74f5d68f433d73a2b2ec9076a803ecf4c6d7a5e5d78dd',
    'bytes' => 790,
  ),
  'direct-image-spine.epub' =>
  array (
    'sha256' => '695bb5c110c2011b4567c6f4a62b5d3249e00be37cfaff92b965ce346b376cb7',
    'bytes' => 1355,
  ),
  'direct-image-spine.native' =>
  array (
    'sha256' => '8fe089bfca1066f7d76935f553392c35991256fd50fe0ae24fa302793db766e2',
    'bytes' => 344,
  ),
  'direct-svg-spine.epub' =>
  array (
    'sha256' => '77dc5b929067cdcc9edd301ce78f8d391797e3dfcc029ee662dc4e2af4902884',
    'bytes' => 1048,
  ),
  'direct-svg-spine.native' =>
  array (
    'sha256' => '961617101a0680d85822c5483ca37ee262a3936ed7c2a29e6a10679fcca35795',
    'bytes' => 47,
  ),
  'duplicate-spine-idref.epub' =>
  array (
    'sha256' => 'cdcd53351890ca8b684b2ad5581be3f57a49c80296c1c7c70bf52fa5220ea3cd',
    'bytes' => 1423,
  ),
  'duplicate-spine-idref.native' =>
  array (
    'sha256' => 'a531ce241637505ddcc5a03704f159d5fd5ee213cc59721bb1fb4e93105bb5ff',
    'bytes' => 1312,
  ),
  'encoded-image-media-bag.epub' =>
  array (
    'sha256' => '38642e1a951ac69c1f8ff3874e2cb7d9b752310406efd42bb0109a6fd4c0f329',
    'bytes' => 1302,
  ),
  'encoded-image-media-bag.native' =>
  array (
    'sha256' => '1b0af7b52d751bc2524236104b8fb8b153e2bd9f255f4fa61a279446089518e2',
    'bytes' => 349,
  ),
  'epub2_cover.epub' =>
  array (
    'sha256' => '4af73a135aa632cbf0c00b2889a5fc1d39a59a77fa294fdeff5ede72ff6ffed1',
    'bytes' => 11794,
  ),
  'epub2_cover.native' =>
  array (
    'sha256' => '501e5182f6b213cb9482669ae4d9f506c8ece71f8aea29ab4abb014e57d8344a',
    'bytes' => 390,
  ),
  'epub2_no_cover.epub' =>
  array (
    'sha256' => '8369dbe5cf315f1fe00f9dd1bf7c500cc663d7648edbf0d7b6a9b4d785fedf4e',
    'bytes' => 3584,
  ),
  'epub2_no_cover.native' =>
  array (
    'sha256' => '6063e77cc1d1ce4feeaa110b43f6c8c452403a464951c36c156f41dbef269402',
    'bytes' => 322,
  ),
  'epub2_picture.epub' =>
  array (
    'sha256' => '6049dde9e1d0ebcd175a8c5b937984f349af996e293310eafbce09e4c7384495',
    'bytes' => 11742,
  ),
  'epub2_picture.native' =>
  array (
    'sha256' => '1c13430b583a0b9df6b98d7a285a5400571e8bc512acb4ea46d37acafbbac7da',
    'bytes' => 390,
  ),
  'epub3-ncx-toc-fallback.epub' =>
  array (
    'sha256' => 'ead984a9fdd9e85194a55d0c1a4f28d67182493bad9692f8ee19424b33ddd225',
    'bytes' => 2189,
  ),
  'epub3-ncx-toc-fallback.native' =>
  array (
    'sha256' => 'd2af2d91536fe498affbe70f0de4a917c30c5c8e0cc147dc631bbb5cf49af781',
    'bytes' => 1013,
  ),
  'external-footnote-reference.epub' =>
  array (
    'sha256' => '9df47e23e87d0385737c76fbc518bec86d7ab222e9a007c1db1d0e5f9c0ec5d2',
    'bytes' => 1766,
  ),
  'external-footnote-reference.native' =>
  array (
    'sha256' => 'ee4878561dad1a0f53703d0cb4bd8b2726068cee9482c32df47fa481194675ee',
    'bytes' => 286,
  ),
  'features.epub' =>
  array (
    'sha256' => '6bf9a102249d58b32f14b39dfbc966bdecadff68a3fb707cb3ca62334734358a',
    'bytes' => 8970,
  ),
  'features.native' =>
  array (
    'sha256' => 'a9019153ea883dccd5d67af2008079fa8daa763a8f4a0796d44026f67675043b',
    'bytes' => 43996,
  ),
  'figure-caption-spine.epub' =>
  array (
    'sha256' => 'ff953ce2bdaee8786620ea8deb2473c7886334d862112fc520a76658726e8c07',
    'bytes' => 1581,
  ),
  'figure-caption-spine.native' =>
  array (
    'sha256' => 'b470cb9f3fc84d90dda902de611d93b57b6090c2447a2e8a9637c3c97a56d50e',
    'bytes' => 616,
  ),
  'font-manifest-resource.epub' =>
  array (
    'sha256' => 'ab561d6de4579fbe572ae1e99e56c3dcba464f1d9c2906310f1324d1a1243d0e',
    'bytes' => 1512,
  ),
  'font-manifest-resource.native' =>
  array (
    'sha256' => 'd8dddfa841becc2b7bf6e730f790cb283396aec3444365a4e74177ef8843f7c3',
    'bytes' => 273,
  ),
  'formatting.epub' =>
  array (
    'sha256' => '491fc57ec384449a23c4f2abdcfe91be9ab2a07f50f466fb8d80775b89bf3965',
    'bytes' => 14022,
  ),
  'formatting.native' =>
  array (
    'sha256' => '3353ae64eee933d28caab426803ef61deb2ce26923beef703f3028148ffc419d',
    'bytes' => 160079,
  ),
  'fragment-nav-spine.epub' =>
  array (
    'sha256' => 'cf582d0b887cd5c7a01180a7fe45138144bb650dc257f21c32ef33765a50a6b8',
    'bytes' => 1372,
  ),
  'fragment-nav-spine.native' =>
  array (
    'sha256' => '81ffc5d60c1d7c49cfe3f95c44036d87d922c2aef8d71425dce3cb666da5576e',
    'bytes' => 550,
  ),
  'guide-bibliography-reference.epub' =>
  array (
    'sha256' => 'c41d806bf13306837ecfdbc12504a1f134f85d40545bb4694447763297f891fd',
    'bytes' => 1391,
  ),
  'guide-bibliography-reference.native' =>
  array (
    'sha256' => 'e42b3f67d1340493874064c45bb93147b467d1a91f06121655188b6b7640bfa2',
    'bytes' => 284,
  ),
  'guide-glossary-reference.epub' =>
  array (
    'sha256' => '699550c8c91e9f11cb430c24e2e157a1f6dfb4f11cff2b98f5ad3cce72b6141d',
    'bytes' => 1386,
  ),
  'guide-glossary-reference.native' =>
  array (
    'sha256' => 'c40a968d5df5756cbb0aa48cddad780ac399470754e73f6b0b3e55b7e4c24e80',
    'bytes' => 277,
  ),
  'guide-notes-reference.epub' =>
  array (
    'sha256' => '7fdc04f51cc6f359c5f44cd56661d953f2ccd00983a45ae4fedcb91c275fccee',
    'bytes' => 1378,
  ),
  'guide-notes-reference.native' =>
  array (
    'sha256' => '22f8995379eaa6108f1eb658468b1c3075047a0d4d4448d3d573137fb04bf500',
    'bytes' => 270,
  ),
  'guide-preface-reference.epub' =>
  array (
    'sha256' => 'd4470953a6b05f8a8d33a1aa766a04fd9a58ea897b3017a41aed7d2410990d37',
    'bytes' => 1367,
  ),
  'guide-preface-reference.native' =>
  array (
    'sha256' => '7e5c20fc82802f8f019f7de05fd6e38a96a961f0abc98905cb1581b808ce0077',
    'bytes' => 274,
  ),
  'img.epub' =>
  array (
    'sha256' => 'f2c25e0e0612b7ac33a8d6a1c9719a86e7d2a0290472fc7d8b5068de781a822f',
    'bytes' => 20478,
  ),
  'img.native' =>
  array (
    'sha256' => 'd23803b0e2ce59892cad94c660e093375847a08ed856739ca7dda50d2ac4e3a7',
    'bytes' => 5311,
  ),
  'img_no_cover.epub' =>
  array (
    'sha256' => '3063f5e9b9610df1ddcc682ce49c293bcf681f1958700a5b6c3eda344383cf2a',
    'bytes' => 10602,
  ),
  'img_no_cover.native' =>
  array (
    'sha256' => 'f2c48a5ac5a84d3bab0091ca2dfc7af9877bb06dbfb286e7bb40b4d4e9740b8f',
    'bytes' => 5191,
  ),
  'inline-abbr-subscript-superscript.epub' =>
  array (
    'sha256' => '60d188945fb302e0e658afdc5be5843422f94b2edb344b392703dae00f1d1409',
    'bytes' => 1476,
  ),
  'inline-abbr-subscript-superscript.native' =>
  array (
    'sha256' => '3d9b3e8d736bcb4f233b70228b0bebce47e5716334cb949adb80977716999e53',
    'bytes' => 615,
  ),
  'language-french-metadata.epub' =>
  array (
    'sha256' => 'a64733afbdd101dcf679227227eacaa6dd8ec1649721e406cbc245e4e91a5f87',
    'bytes' => 1317,
  ),
  'language-french-metadata.native' =>
  array (
    'sha256' => '66ed9d9c546eb58f4fb7685ab5c30affa158a84b415a068408f95d52298a4dcf',
    'bytes' => 144,
  ),
  'main-section-spine.epub' =>
  array (
    'sha256' => '99f8c2afa52f3cb97bed7466fdff1bcb9a94f795ba27d306cabc70314cab40dc',
    'bytes' => 1445,
  ),
  'main-section-spine.native' =>
  array (
    'sha256' => '9f9d405ee278ca5a586aa3a9cd1020c90e6b66d2c8019e985d7c779a1347e5a5',
    'bytes' => 353,
  ),
  'manifest-fallback-chain.epub' =>
  array (
    'sha256' => 'af579a53102ff39e74bf2f79df687384ba1897c961aba9be197ba575079e18a4',
    'bytes' => 1735,
  ),
  'manifest-fallback-chain.native' =>
  array (
    'sha256' => '1f5d434d455f5b92592e929f598bf1fc07a229969912675419be70ad034d31b8',
    'bytes' => 276,
  ),
  'manifest-fallback-style.epub' =>
  array (
    'sha256' => 'e9c4c86b4fc4d167600f09b0daf4cafa4cd15763b833119209ed42d01ffd5f8f',
    'bytes' => 2063,
  ),
  'manifest-fallback-style.native' =>
  array (
    'sha256' => 'e5ab69b21a48e6f8b0f907ec5dfb1d07bd8942de45fb1aff6e8ce6159f40abb7',
    'bytes' => 580,
  ),
  'manifest-href-encoding.epub' =>
  array (
    'sha256' => 'a5f5643ef8d10b7ed6339a14153991273db0d78e23b2b8c2fcf949922f0c11e8',
    'bytes' => 2281,
  ),
  'manifest-href-encoding.native' =>
  array (
    'sha256' => '59c8166ffa04fa003cf7a11d2f8b5e9097d3402218f1d7553d760f9cad70f8e5',
    'bytes' => 513,
  ),
  'mathml-spine.epub' =>
  array (
    'sha256' => 'c89ff2507ce6ca380f20bdf0e4d2ca15f27baf0c9a68fac7f482587727a568b3',
    'bytes' => 1562,
  ),
  'mathml-spine.native' =>
  array (
    'sha256' => '394d586dfd52a7717a6989f20d0e034d9ac1dbb0c904d43ed2ae598b91be81d0',
    'bytes' => 484,
  ),
  'measurement-inline-spine.epub' =>
  array (
    'sha256' => 'bb31e5ad3dbacbe7c348e0da2993d099b164511deb210be40472b030ed7ab73f',
    'bytes' => 1480,
  ),
  'measurement-inline-spine.native' =>
  array (
    'sha256' => 'af0f1eb46b1768445f0ebb45e879ac928326d14835396fefaef91dbc52e0b496',
    'bytes' => 1020,
  ),
  'media-manifest-mix.epub' =>
  array (
    'sha256' => 'd74b69c881a8a46913a719fe2aa5311cb7fdf5ac747f98e7c5b342a3a78fe04c',
    'bytes' => 1801,
  ),
  'media-manifest-mix.native' =>
  array (
    'sha256' => '73f358ea83264cdf33658f481e03264f515f8d810b01d0b04f0496be8c2f8895',
    'bytes' => 513,
  ),
  'media-overlay-invalid-clips.epub' =>
  array (
    'sha256' => '0a50bda53abe80c587b701b4246c32282656e8f6692d92a0df23f0d879254144',
    'bytes' => 1942,
  ),
  'media-overlay-invalid-clips.native' =>
  array (
    'sha256' => '7eef159079249b60224888cf7ecdf494532cd7a82f886769522aa4a29cf7afd6',
    'bytes' => 369,
  ),
  'media-overlay-package.epub' =>
  array (
    'sha256' => '6af50dc4bf618cd964af7274a688aebcbd16da6804581325c00195b1721ed972',
    'bytes' => 1894,
  ),
  'media-overlay-package.native' =>
  array (
    'sha256' => '4e229ee5d0053c02d5ee8aaa425e800991bdcd5d3efad788043346f68cad1421',
    'bytes' => 300,
  ),
  'metadata-link-page-list-image.epub' =>
  array (
    'sha256' => 'ed2da17a5ea5cc370bde15d43e9480558654e644cf3c4d637ea50c71c1a3241c',
    'bytes' => 1926,
  ),
  'metadata-link-page-list-image.native' =>
  array (
    'sha256' => '884c97ef31814c40e380663f07792a4dd223d67457fd4b7cfbf0bae9be158cc5',
    'bytes' => 1140,
  ),
  'metadata-link-vocab-diagnostics.epub' =>
  array (
    'sha256' => '676ac3a2b19834ae0b3c7527e6353c0b79f37c9fbd98cbeae9e99ca4df3db35f',
    'bytes' => 2353,
  ),
  'metadata-link-vocab-diagnostics.native' =>
  array (
    'sha256' => '36f45d3c72c6917b50e03edd0c0d007926473fa93569362081cbdbf1f70c1043',
    'bytes' => 165,
  ),
  'metadata-record-remote-nav.epub' =>
  array (
    'sha256' => '74f7d7ecaa89dea3d0085f1208a78abf951de22e057245d321036bcd4b35ffe8',
    'bytes' => 1944,
  ),
  'metadata-record-remote-nav.native' =>
  array (
    'sha256' => '9a0dffbca5d0b8a52ac7d12e570a0e671d6892f54298d396d808a49940f31bad',
    'bytes' => 844,
  ),
  'metadata-search-link-semantics.epub' =>
  array (
    'sha256' => '02d2f49316abf1e2f2abc8f6959090dc891e24857b849297201782918cca3a3f',
    'bytes' => 1892,
  ),
  'metadata-search-link-semantics.native' =>
  array (
    'sha256' => '8e78383af179a9392bdc99d397444133b2423163663cfdc41e4e24583c68cd48',
    'bytes' => 1861,
  ),
  'missing-local-manifest-resource.epub' =>
  array (
    'sha256' => '5ce06b74cde06eb0d06f1b41b73f99840983451abb9bb120e8206979ac16dca5',
    'bytes' => 1386,
  ),
  'missing-local-manifest-resource.native' =>
  array (
    'sha256' => '1d2219a57a0cd610c1835c392e0819c4866f91082e92f4015b079f5539a3f1c8',
    'bytes' => 308,
  ),
  'missing-media-overlay.epub' =>
  array (
    'sha256' => '2f6f3b7da6babcda4101045e106c1bfac5ea56377ae96764793d8ccd98cadf07',
    'bytes' => 1422,
  ),
  'missing-media-overlay.native' =>
  array (
    'sha256' => 'fe3aa9b18f5365ca6b16ecafd7b640aa4d64158b7c5c2bc2892b3d02359564b5',
    'bytes' => 334,
  ),
  'multi-rootfile-nested-nav.epub' =>
  array (
    'sha256' => 'd4d65c5c0c6db9dc89ddbe0545f7870815a770d1441be00211b865155a273961',
    'bytes' => 2715,
  ),
  'multi-rootfile-nested-nav.native' =>
  array (
    'sha256' => 'd6aaf8b80629420e9b3ea1854a751cca180bc408a0eac6c8a1513b83eb2aa96b',
    'bytes' => 479,
  ),
  'nav-ncx-linear-guide.epub' =>
  array (
    'sha256' => '45b914d6e5ef83949c5432b7c523c383d323a3b9aa56499946155b88ace41f26',
    'bytes' => 2336,
  ),
  'nav-ncx-linear-guide.native' =>
  array (
    'sha256' => 'abee3ec4119924923d8d1c96ababc92bc0aa9ad38646e198e5d0b384ee0c0dd4',
    'bytes' => 322,
  ),
  'nested-path-media-metadata.epub' =>
  array (
    'sha256' => '685025a751e882b4700b6b31a0cdb8f51eceecaae86be1d83e0590beb2d876b7',
    'bytes' => 3588,
  ),
  'nested-path-media-metadata.native' =>
  array (
    'sha256' => '237760af79e8ff533a0bdab616e5a100ec81c85f7543b34ab388844bb8ad9766',
    'bytes' => 1899,
  ),
  'nested-rootfile-nonlinear-spine.epub' =>
  array (
    'sha256' => 'e0e41f25280f3b7a092ea2ed105af51c33e445221b2d54c877181c96aed191f4',
    'bytes' => 2043,
  ),
  'nested-rootfile-nonlinear-spine.native' =>
  array (
    'sha256' => '49135d70c19c11588f6a316fa00787463ce195aefaf5372a8840d955943dc53c',
    'bytes' => 219,
  ),
  'package-spine-nav-media-metadata.epub' =>
  array (
    'sha256' => '64981f08e5f4b2ae41baf55233e3cf4419c62c25d2606347bfedf0ee7e181a18',
    'bytes' => 2402,
  ),
  'package-spine-nav-media-metadata.native' =>
  array (
    'sha256' => '6d5be8a2ed05f750c291ce141c0110e2264605960ccaf89175de7cf6179fffbd',
    'bytes' => 993,
  ),
  'page-list-cfi-navigation.epub' =>
  array (
    'sha256' => '88feb1210f770ffa341c907fe0f1b9a68c88677abf28021849e73197695d0a8f',
    'bytes' => 1411,
  ),
  'page-list-cfi-navigation.native' =>
  array (
    'sha256' => '36bc594058d69b633756e9080b826be908e244b852647bf8a888e22c770b26d1',
    'bytes' => 327,
  ),
  'page-list-navigation.epub' =>
  array (
    'sha256' => '449c6114a473e2db1df8cf69cd29fddaef4a14a160b65fd7fe30adf0c80b9365',
    'bytes' => 1394,
  ),
  'page-list-navigation.native' =>
  array (
    'sha256' => 'f565404556ec3487d55c3610b56882cebc0662d85e3c1135cf4c05a971544cfa',
    'bytes' => 271,
  ),
  'parent-relative-nav.epub' =>
  array (
    'sha256' => 'caafa83c3b42b02d6aa25905f04b045df1a3db37913a636a296193cc4f8f27f6',
    'bytes' => 1652,
  ),
  'parent-relative-nav.native' =>
  array (
    'sha256' => 'fa48842bd1b89d8ba991dc5d577bb526f61bf89c7e8966f66c0929ca6d149a9e',
    'bytes' => 705,
  ),
  'raw-media-source-spine.epub' =>
  array (
    'sha256' => 'c778ca2f726f06d72ea86cf575f5ee3e9ff37e1443be4c0c2ce83d2d15959340',
    'bytes' => 1913,
  ),
  'raw-media-source-spine.native' =>
  array (
    'sha256' => '6d0e323c314c6cf0ef2e97f2c560cd46b222912aaedd37071580293e53f3f149',
    'bytes' => 616,
  ),
  'remote-manifest-resource.epub' =>
  array (
    'sha256' => 'aaf4a5557c55af341a6a2ed5950ccc5807ce529f6ae4ed4398336345b0646c7f',
    'bytes' => 1385,
  ),
  'remote-manifest-resource.native' =>
  array (
    'sha256' => 'a2b15395968495a5376a60e63ae21b0c0a079f02ee447c1ef2063ec87a613c13',
    'bytes' => 277,
  ),
  'rendition-layout-property.epub' =>
  array (
    'sha256' => 'abdbb293f94d979445600249a1162c0607a2fbcb73fc260d77d61334edef3671',
    'bytes' => 1390,
  ),
  'rendition-layout-property.native' =>
  array (
    'sha256' => '8b595c803ae40a3dedbbff2a9cb6632daf17916e8a72c3788beff91f12033855',
    'bytes' => 314,
  ),
  'scripted-svg-manifest.epub' =>
  array (
    'sha256' => '8845d9a35825bdf882b5d2239b60c1e7fd0f9589c8d06f5be74f0565fc56bb1b',
    'bytes' => 1577,
  ),
  'scripted-svg-manifest.native' =>
  array (
    'sha256' => 'c46fe3dd878f6709fc7dc4db9ce94b4f813924acd40de064614ac5a2eb90caa4',
    'bytes' => 276,
  ),
  'scripted-xhtml-resource.epub' =>
  array (
    'sha256' => '4600cb6c58330de0c0dc6e27deb73c41dae16a395c98ad0774fb3812323d77e5',
    'bytes' => 1556,
  ),
  'scripted-xhtml-resource.native' =>
  array (
    'sha256' => 'e84a35411c739bc6a1d8a54f122eff6fcb3e2552df597ee4c08c3ad178e654f4',
    'bytes' => 273,
  ),
  'spine-fallback-resource.epub' =>
  array (
    'sha256' => 'c042da479466e7353f063d986eb5481e49d2a6d9b93a8348576994f6ae3dbde6',
    'bytes' => 1661,
  ),
  'spine-fallback-resource.native' =>
  array (
    'sha256' => '56a094f8d97c055aeca928ad6d5162be7ca396ea1f869a2b29740aef3415baaa',
    'bytes' => 48,
  ),
  'spine-page-spread.epub' =>
  array (
    'sha256' => '47c48d493ff2846023ce78c1cb407d8025865ef7eb986c9f60607de4189bd5e1',
    'bytes' => 1562,
  ),
  'spine-page-spread.native' =>
  array (
    'sha256' => 'ecdae2b7e18be738e3530727e3d04f253fed3a6474091964d0b9c0c16c984dd9',
    'bytes' => 483,
  ),
  'standalone-footnote.epub' =>
  array (
    'sha256' => '5058fb925a59dadae5ac5e371f4907c5a192b074410d2c668b4e2b6ff483ab53',
    'bytes' => 1384,
  ),
  'standalone-footnote.native' =>
  array (
    'sha256' => '8ba2f5a23a13f1c6d0e309e3ba77ea8bb65702e5c166c38589d954fcc5026657',
    'bytes' => 431,
  ),
  'text-track-captions.epub' =>
  array (
    'sha256' => '2559039311ac1b9a25be74e4b4a7587cadc5579563a8d0ff1fb3b80503c30da5',
    'bytes' => 1812,
  ),
  'text-track-captions.native' =>
  array (
    'sha256' => 'e1f54a06e556fcd9a130357978b110a6931ed79f27af8424df8a861155b71eed',
    'bytes' => 568,
  ),
  'title-page-guide-media-metadata.epub' =>
  array (
    'sha256' => '9a21d071427572212113af33e11d1d39cd692ea840a81980dfaf471840d28dc7',
    'bytes' => 2801,
  ),
  'title-page-guide-media-metadata.native' =>
  array (
    'sha256' => '8f2c47bb97258bdf88a8cf1a8f8f398e42d2afa8bf2633ceda835785aefdf3d0',
    'bytes' => 747,
  ),
  'video-manifest-resource.epub' =>
  array (
    'sha256' => '7db258c0f96c66dc1de9eeaa1fc75ca5e9fddf821b6f0783cd4b74f4f59013b5',
    'bytes' => 1508,
  ),
  'video-manifest-resource.native' =>
  array (
    'sha256' => 'd71b066f5fc0e0a1bef32649948e217373159694ed179af794914b6732618f68',
    'bytes' => 275,
  ),
  'video-navigation.epub' =>
  array (
    'sha256' => '71bf3f39156a0911cd9b542aee3c45d88aabd608a9a268a4c4fe6a949f1956fe',
    'bytes' => 1505,
  ),
  'video-navigation.native' =>
  array (
    'sha256' => '0a7d0436add9426392a1a10b4d4b725848931a4b7f49fdf6c8acea5e86f14241',
    'bytes' => 262,
  ),
  'wasteland.epub' =>
  array (
    'sha256' => '151ec5dbca33e39a4e3f6894e92fa5a101290bdeaaa792e0700595971456a278',
    'bytes' => 25840,
  ),
  'wasteland.native' =>
  array (
    'sha256' => 'c000ec1960f46c87039eef9cf256fd8dcaeb7a739dfe335d093d50174f2b1efd',
    'bytes' => 139698,
  ),
  'xhtml-address-spine.epub' =>
  array (
    'sha256' => '0c0587cc7ada8eeaf5fe7d544597696deee1a92a489aa9d4574be2ed2f85ddf5',
    'bytes' => 1460,
  ),
  'xhtml-address-spine.native' =>
  array (
    'sha256' => '9685f832a8124ad9cf13b68083218e1a5e6ac2b448edee388b4c6bfcff08dbb8',
    'bytes' => 636,
  ),
  'xhtml-chapter-section-spine.epub' =>
  array (
    'sha256' => 'f099215e3cb9b631b00704eff46c6a44d49383ec6008b12867ad5f8007c55ba5',
    'bytes' => 1453,
  ),
  'xhtml-chapter-section-spine.native' =>
  array (
    'sha256' => '67998b223dd1848852a2545e74c2b41175c0e4e611ee83fc7a50f86f5b944dfc',
    'bytes' => 477,
  ),
  'xhtml-del-edit-mark-spine.epub' =>
  array (
    'sha256' => 'd671033f47969f9de481b2ff9a7a0effa55cc69869868215e9206be947cc7f39',
    'bytes' => 1408,
  ),
  'xhtml-del-edit-mark-spine.native' =>
  array (
    'sha256' => '14299e1d878ef72e19b86795da7d53cef6e42765ba831d13c4c6751891fb3fb6',
    'bytes' => 405,
  ),
  'xhtml-details-summary-spine.epub' =>
  array (
    'sha256' => '8742d0b94103c01e4f2ebe6fdf6b2efb183c138c409268352a225cc9b67c51e5',
    'bytes' => 1457,
  ),
  'xhtml-details-summary-spine.native' =>
  array (
    'sha256' => 'fd0736c6261a6fae6c18f1aff8ea2169fdfe05a558c3b4e6a533dc918f00efce',
    'bytes' => 645,
  ),
  'xhtml-kbd-samp-var-spine.epub' =>
  array (
    'sha256' => '7869f24ed5d068397dc203ea5ffbd8975ca2976d4b722005676a06bd05cc4437',
    'bytes' => 1431,
  ),
  'xhtml-kbd-samp-var-spine.native' =>
  array (
    'sha256' => '61d9b29480004ca9347c1f427ffb2abf758ca44bdf951825f6c51fbc371185e0',
    'bytes' => 571,
  ),
  'xhtml-ruby-table-mark.epub' =>
  array (
    'sha256' => '19e2ed10e4aeafe94970c38606939b9dfbd561f15c7f71e4ee904425f9b13b4d',
    'bytes' => 1876,
  ),
  'xhtml-ruby-table-mark.native' =>
  array (
    'sha256' => 'ec35ac3bda86e5242aa9ceb8b5614be45f689b643c2620508687f55daa68a4b8',
    'bytes' => 2302,
  ),
  'xhtml-semantics-spine.epub' =>
  array (
    'sha256' => 'd2a4df3e7287b534b0ad1685d8f241940dd728fa3541ae1d14924506f7544452',
    'bytes' => 1893,
  ),
  'xhtml-semantics-spine.native' =>
  array (
    'sha256' => 'd2e7da70eb00cd5172cc2382532b972a62d9ef9fc1e4c107aa3c504fa2367fa2',
    'bytes' => 3228,
  ),
);

        $expectedPackageFeatureCoverage = array (
  'kind' => 'epub-package-feature-coverage',
  'fixtureCount' => 76,
  'opfPartNameCounts' =>
  array (
    '/EPUB/package.opf' => 59,
    '/EPUB/wasteland.opf' => 1,
    '/OEBPS/content.opf' => 3,
    '/OPS/book/package.opf' => 4,
    '/OPS/package.opf' => 9,
  ),
  'metadataLanguageCounts' =>
  array (
    'de-DE' => 3,
    'en' => 68,
    'en-GB' => 1,
    'en-US' => 3,
    'fr' => 1,
  ),
  'fixturesWithCreators' =>
  array (
    0 => 'accessibility-metadata-package',
    1 => 'bindings-collections-sidecars',
    2 => 'blockquote-list-spine',
    3 => 'code-block-spine',
    4 => 'content-image-nav-media',
    5 => 'cross-spine-internal-links',
    6 => 'definition-list-spine',
    7 => 'duplicate-spine-idref',
    8 => 'epub2_cover',
    9 => 'epub2_no_cover',
    10 => 'epub2_picture',
    11 => 'epub3-ncx-toc-fallback',
    12 => 'external-footnote-reference',
    13 => 'features',
    14 => 'figure-caption-spine',
    15 => 'formatting',
    16 => 'img',
    17 => 'img_no_cover',
    18 => 'inline-abbr-subscript-superscript',
    19 => 'language-french-metadata',
    20 => 'main-section-spine',
    21 => 'manifest-fallback-style',
    22 => 'manifest-href-encoding',
    23 => 'mathml-spine',
    24 => 'measurement-inline-spine',
    25 => 'media-manifest-mix',
    26 => 'media-overlay-invalid-clips',
    27 => 'metadata-link-page-list-image',
    28 => 'metadata-link-vocab-diagnostics',
    29 => 'metadata-record-remote-nav',
    30 => 'metadata-search-link-semantics',
    31 => 'missing-media-overlay',
    32 => 'multi-rootfile-nested-nav',
    33 => 'nested-path-media-metadata',
    34 => 'nested-rootfile-nonlinear-spine',
    35 => 'package-spine-nav-media-metadata',
    36 => 'parent-relative-nav',
    37 => 'raw-media-source-spine',
    38 => 'spine-fallback-resource',
    39 => 'text-track-captions',
    40 => 'title-page-guide-media-metadata',
    41 => 'wasteland',
    42 => 'xhtml-address-spine',
    43 => 'xhtml-chapter-section-spine',
    44 => 'xhtml-details-summary-spine',
    45 => 'xhtml-kbd-samp-var-spine',
    46 => 'xhtml-ruby-table-mark',
    47 => 'xhtml-semantics-spine',
  ),
  'navigationTypeCounts' =>
  array (
    'nav' => 68,
    'ncx' => 4,
  ),
  'spineLinearStateCounts' =>
  array (
    'linear' => 92,
    'non-linear' => 16,
  ),
  'spinePageSpreadPlacementCounts' =>
  array (
    'left' => 2,
    'right' => 4,
  ),
  'manifestMediaTypeCounts' =>
  array (
    'application/javascript' => 1,
    'application/json' => 6,
    'application/ld+json' => 2,
    'application/octet-stream' => 1,
    'application/pdf' => 1,
    'application/smil+xml' => 2,
    'application/x-bound-widget' => 1,
    'application/x-dtbncx+xml' => 6,
    'application/x-fallback-demo' => 3,
    'application/xhtml+xml' => 172,
    'audio/mpeg' => 5,
    'font/woff2' => 1,
    'image/gif' => 5,
    'image/jpeg' => 7,
    'image/png' => 13,
    'image/svg+xml' => 2,
    'text/css' => 25,
    'text/vtt' => 2,
    'video/mp4' => 5,
  ),
  'manifestPropertyCounts' =>
  array (
    'accessibility-metadata' => 1,
    'cover-image' => 4,
    'mathml' => 3,
    'nav' => 68,
    'remote-resources' => 4,
    'rendition:layout-pre-paginated' => 1,
    'scripted' => 2,
    'svg' => 3,
    'switch' => 1,
  ),
  'manifestResourceKindCounts' =>
  array (
    'asset' => 14,
    'audio' => 5,
    'cover-image' => 4,
    'font' => 1,
    'image' => 21,
    'media-overlay' => 2,
    'navigation' => 74,
    'script' => 1,
    'style' => 25,
    'svg' => 2,
    'text-track' => 2,
    'video' => 5,
    'xhtml' => 104,
  ),
  'navigationSectionTypes' =>
  array (
    0 => 'appendix',
    1 => 'landmarks',
    2 => 'loa',
    3 => 'loi',
    4 => 'lot',
    5 => 'lov',
    6 => 'page-list',
    7 => 'toc',
  ),
  'guideReferenceTypeCounts' =>
  array (
    'appendix' => 1,
    'bibliography' => 1,
    'cover' => 3,
    'glossary' => 1,
    'index' => 1,
    'notes' => 1,
    'preface' => 1,
    'text' => 12,
    'title-page' => 1,
    'toc' => 1,
  ),
  'packageLinkRelCounts' =>
  array (
    'accessibility-summary' => 1,
    'alternate' => 2,
    'bad/token' => 1,
    'cc:attributionURL' => 1,
    'cc:license' => 2,
    'https://example.invalid/link-rel#review' => 1,
    'preview' => 3,
    'record' => 11,
    'schema:associatedMedia' => 1,
    'search' => 1,
    'unknown:missing' => 1,
  ),
  'packageLinkVocabularyRelCounts' =>
  array (
    'accessibility-summary' => 1,
    'alternate' => 2,
    'bad/token' => 1,
    'cc:attributionURL' => 1,
    'cc:license' => 2,
    'https://example.invalid/link-rel#review' => 1,
    'preview' => 3,
    'record' => 11,
    'schema:associatedMedia' => 1,
    'search' => 1,
    'unknown:missing' => 1,
  ),
  'packageLinkVocabularyPropertyCounts' =>
  array (
    'accessibility-metadata' => 1,
    'bad/property' => 1,
    'https://example.invalid/props#review' => 1,
    'review:packet' => 1,
    'schema-org' => 2,
    'unknown:flag' => 1,
  ),
  'packageLinkMediaTypeCounts' =>
  array (
    'application/json' => 8,
    'application/ld+json' => 2,
    'application/opensearchdescription+xml' => 1,
    'text/html' => 1,
  ),
  'packageLinkMediaTypeParameterNameCounts' =>
  array (
    'profile' => 1,
  ),
  'linkHrefSuffixSourceCounts' =>
  array (
    'collection-link' => 2,
    'package-link' => 1,
  ),
  'accessibilityPropertyCounts' =>
  array (
    'accessMode' => 2,
    'accessibilityFeature' => 2,
    'accessibilityHazard' => 1,
    'accessibilitySummary' => 1,
    'conformsTo' => 1,
  ),
  'encryptionRoleCounts' =>
  array (
    'font' => 3,
  ),
  'collectionRoleCounts' =>
  array (
    'index' => 1,
    'role:primary' => 1,
    'schema:hasPart' => 1,
  ),
  'collectionLinkRelCounts' =>
  array (
    'contents' => 1,
    'index' => 1,
    'record' => 1,
  ),
  'bindingMediaTypeCounts' =>
  array (
    'application/x-bound-widget' => 1,
  ),
  'ocfSidecarKindCounts' =>
  array (
    'manifest' => 1,
    'metadata' => 1,
    'rights' => 1,
    'signatures' => 1,
  ),
  'fixtureFeatureSignatures' =>
  array (
    'accessibility-metadata-package' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
        'accessibility-summary' => 1,
        'record' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'accessibility-summary' => 1,
        'record' => 1,
      ),
      'packageLinkVocabularyPropertyCounts' =>
      array (
        'accessibility-metadata' => 1,
      ),
      'accessibilityPropertyCounts' =>
      array (
        'accessMode' => 2,
        'accessibilityFeature' => 1,
        'accessibilityHazard' => 1,
        'accessibilitySummary' => 1,
        'conformsTo' => 1,
      ),
      'accessibilityLinkedRecordCount' => 1,
      'coverImagePartPresent' => false,
    ),
    'all-nonlinear-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'appendix-navigation-guide' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'appendix',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'appendix' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'audio-navigation' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'loa',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'audio' => 1,
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'auxiliary-lot-guide-index' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'lot',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'index' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'bindings-collections-sidecars' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'navigation' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
        'record' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'record' => 1,
      ),
      'coverImagePartPresent' => false,
    ),
    'blockquote-list-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'code-block-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'content-image-nav-media' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'page-list',
        2 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'audio' => 1,
        'image' => 2,
        'navigation' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'cross-spine-internal-links' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'definition-list-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'direct-image-spine' =>
    array (
      'navigationType' => '',
      'navigationSectionTypes' =>
      array (
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 3,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'direct-svg-spine' =>
    array (
      'navigationType' => '',
      'navigationSectionTypes' =>
      array (
      ),
      'manifestResourceKindCounts' =>
      array (
        'svg' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'duplicate-spine-idref' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'encoded-image-media-bag' =>
    array (
      'navigationType' => '',
      'navigationSectionTypes' =>
      array (
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'epub2_cover' =>
    array (
      'navigationType' => 'ncx',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 1,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'cover' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => true,
    ),
    'epub2_no_cover' =>
    array (
      'navigationType' => 'ncx',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'toc' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'epub2_picture' =>
    array (
      'navigationType' => 'ncx',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 1,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'cover' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => true,
    ),
    'epub3-ncx-toc-fallback' =>
    array (
      'navigationType' => 'ncx',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'external-footnote-reference' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'features' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'style' => 2,
        'xhtml' => 3,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'figure-caption-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 1,
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'font-manifest-resource' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'font' => 1,
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'formatting' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 1,
        'navigation' => 1,
        'style' => 2,
        'xhtml' => 7,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'fragment-nav-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'guide-bibliography-reference' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'bibliography' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'guide-glossary-reference' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'glossary' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'guide-notes-reference' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'notes' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'guide-preface-reference' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'preface' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'img' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'cover-image' => 1,
        'image' => 3,
        'navigation' => 1,
        'style' => 2,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => true,
    ),
    'img_no_cover' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 3,
        'navigation' => 1,
        'style' => 2,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'inline-abbr-subscript-superscript' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'language-french-metadata' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'main-section-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'manifest-fallback-chain' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'navigation' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'manifest-fallback-style' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'manifest-href-encoding' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'page-list',
        2 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
        'record' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'record' => 1,
      ),
      'coverImagePartPresent' => false,
    ),
    'mathml-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'measurement-inline-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'media-manifest-mix' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 2,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'media-overlay-invalid-clips' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'audio' => 1,
        'media-overlay' => 1,
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'media-overlay-package' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'audio' => 1,
        'media-overlay' => 1,
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'metadata-link-page-list-image' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'page-list',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 1,
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
        'alternate' => 1,
        'record' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'alternate' => 1,
        'record' => 1,
      ),
      'coverImagePartPresent' => false,
    ),
    'metadata-link-vocab-diagnostics' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'cover-image' => 1,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
        'bad/token' => 1,
        'https://example.invalid/link-rel#review' => 1,
        'record' => 2,
        'schema:associatedMedia' => 1,
        'unknown:missing' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'bad/token' => 1,
        'https://example.invalid/link-rel#review' => 1,
        'record' => 2,
        'schema:associatedMedia' => 1,
        'unknown:missing' => 1,
      ),
      'packageLinkVocabularyPropertyCounts' =>
      array (
        'bad/property' => 1,
        'https://example.invalid/props#review' => 1,
        'review:packet' => 1,
        'schema-org' => 2,
        'unknown:flag' => 1,
      ),
      'packageLinkVocabularyDiagnosticCount' => 6,
      'coverImagePartPresent' => true,
    ),
    'metadata-record-remote-nav' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'loi',
        2 => 'page-list',
        3 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
        'alternate' => 1,
        'preview' => 1,
        'record' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'alternate' => 1,
        'preview' => 1,
        'record' => 1,
      ),
      'coverImagePartPresent' => false,
    ),
    'metadata-search-link-semantics' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
        'record' => 1,
        'search' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'record' => 1,
        'search' => 1,
      ),
      'coverImagePartPresent' => false,
    ),
    'missing-local-manifest-resource' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'missing-media-overlay' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'multi-rootfile-nested-nav' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'page-list',
        2 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 3,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'nav-ncx-linear-guide' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'navigation' => 2,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
        'record' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'record' => 1,
      ),
      'coverImagePartPresent' => false,
    ),
    'nested-path-media-metadata' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'lot',
        2 => 'page-list',
        3 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'audio' => 1,
        'cover-image' => 1,
        'image' => 1,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 3,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'cover' => 1,
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
        'record' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'record' => 1,
      ),
      'coverImagePartPresent' => true,
    ),
    'nested-rootfile-nonlinear-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'package-spine-nav-media-metadata' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'page-list',
        2 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 1,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'page-list-cfi-navigation' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'page-list',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'pageListCfiTargetCount' => 2,
      'coverImagePartPresent' => false,
    ),
    'page-list-navigation' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'loi',
        1 => 'page-list',
        2 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'parent-relative-nav' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'raw-media-source-spine' =>
    array (
      'navigationType' => '',
      'navigationSectionTypes' =>
      array (
      ),
      'manifestResourceKindCounts' =>
      array (
        'image' => 1,
        'text-track' => 1,
        'video' => 2,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'remote-manifest-resource' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'rendition-layout-property' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'scripted-svg-manifest' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'svg' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'scripted-xhtml-resource' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'script' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'spine-fallback-resource' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'page-list',
        2 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'spine-page-spread' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 2,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'standalone-footnote' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'text-track-captions' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'text-track' => 1,
        'video' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'title-page-guide-media-metadata' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'loa',
        2 => 'page-list',
        3 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'image' => 1,
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 3,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'title-page' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
        'preview' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'preview' => 1,
      ),
      'coverImagePartPresent' => false,
    ),
    'video-manifest-resource' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'video' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'video-navigation' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'lov',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'video' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'wasteland' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'cover-image' => 1,
        'navigation' => 2,
        'style' => 2,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
        'cc:attributionURL' => 1,
        'cc:license' => 2,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'cc:attributionURL' => 1,
        'cc:license' => 2,
      ),
      'coverImagePartPresent' => true,
    ),
    'xhtml-address-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'xhtml-chapter-section-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'xhtml-del-edit-mark-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'xhtml-details-summary-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'xhtml-kbd-samp-var-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
    'xhtml-ruby-table-mark' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'asset' => 1,
        'navigation' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
        'preview' => 1,
        'record' => 1,
      ),
      'packageLinkVocabularyRelCounts' =>
      array (
        'preview' => 1,
        'record' => 1,
      ),
      'accessibilityPropertyCounts' =>
      array (
        'accessibilityFeature' => 1,
      ),
      'coverImagePartPresent' => false,
    ),
    'xhtml-semantics-spine' =>
    array (
      'navigationType' => 'nav',
      'navigationSectionTypes' =>
      array (
        0 => 'landmarks',
        1 => 'toc',
      ),
      'manifestResourceKindCounts' =>
      array (
        'navigation' => 1,
        'style' => 1,
        'xhtml' => 1,
      ),
      'guideReferenceTypeCounts' =>
      array (
        'text' => 1,
      ),
      'packageLinkRelCounts' =>
      array (
      ),
      'coverImagePartPresent' => false,
    ),
  ),
  'fixturesWithGuideReferences' =>
  array (
    0 => 'appendix-navigation-guide',
    1 => 'auxiliary-lot-guide-index',
    2 => 'epub2_cover',
    3 => 'epub2_no_cover',
    4 => 'epub2_picture',
    5 => 'epub3-ncx-toc-fallback',
    6 => 'guide-bibliography-reference',
    7 => 'guide-glossary-reference',
    8 => 'guide-notes-reference',
    9 => 'guide-preface-reference',
    10 => 'manifest-fallback-style',
    11 => 'manifest-href-encoding',
    12 => 'mathml-spine',
    13 => 'metadata-record-remote-nav',
    14 => 'multi-rootfile-nested-nav',
    15 => 'nav-ncx-linear-guide',
    16 => 'nested-path-media-metadata',
    17 => 'nested-rootfile-nonlinear-spine',
    18 => 'parent-relative-nav',
    19 => 'title-page-guide-media-metadata',
    20 => 'xhtml-ruby-table-mark',
    21 => 'xhtml-semantics-spine',
  ),
  'fixturesWithPackageLinks' =>
  array (
    0 => 'accessibility-metadata-package',
    1 => 'bindings-collections-sidecars',
    2 => 'manifest-href-encoding',
    3 => 'metadata-link-page-list-image',
    4 => 'metadata-link-vocab-diagnostics',
    5 => 'metadata-record-remote-nav',
    6 => 'metadata-search-link-semantics',
    7 => 'nav-ncx-linear-guide',
    8 => 'nested-path-media-metadata',
    9 => 'title-page-guide-media-metadata',
    10 => 'wasteland',
    11 => 'xhtml-ruby-table-mark',
  ),
  'fixturesWithPackageLinkVocabulary' =>
  array (
    0 => 'accessibility-metadata-package',
    1 => 'bindings-collections-sidecars',
    2 => 'manifest-href-encoding',
    3 => 'metadata-link-page-list-image',
    4 => 'metadata-link-vocab-diagnostics',
    5 => 'metadata-record-remote-nav',
    6 => 'metadata-search-link-semantics',
    7 => 'nav-ncx-linear-guide',
    8 => 'nested-path-media-metadata',
    9 => 'title-page-guide-media-metadata',
    10 => 'wasteland',
    11 => 'xhtml-ruby-table-mark',
  ),
  'fixturesWithPackageLinkVocabularyDiagnostics' =>
  array (
    0 => 'metadata-link-vocab-diagnostics',
  ),
  'fixturesWithPackageLinkMediaTypeParameters' =>
  array (
    0 => 'metadata-search-link-semantics',
  ),
  'fixturesWithLinkHrefSuffixes' =>
  array (
    0 => 'bindings-collections-sidecars',
    1 => 'metadata-search-link-semantics',
  ),
  'fixturesWithAccessibilityMetadata' =>
  array (
    0 => 'accessibility-metadata-package',
    1 => 'xhtml-ruby-table-mark',
  ),
  'fixturesWithCoverImagePart' =>
  array (
    0 => 'epub2_cover',
    1 => 'epub2_picture',
    2 => 'img',
    3 => 'metadata-link-vocab-diagnostics',
    4 => 'nested-path-media-metadata',
    5 => 'wasteland',
  ),
  'fixturesWithEncryption' =>
  array (
    0 => 'epub2_cover',
    1 => 'epub2_no_cover',
    2 => 'epub2_picture',
  ),
  'fixturesWithObfuscatedFonts' =>
  array (
    0 => 'epub2_cover',
    1 => 'epub2_no_cover',
    2 => 'epub2_picture',
  ),
  'fixturesWithBlockedEncryptedByteExposures' =>
  array (
    0 => 'epub2_cover',
    1 => 'epub2_no_cover',
    2 => 'epub2_picture',
  ),
  'fixturesWithImages' =>
  array (
    0 => 'content-image-nav-media',
    1 => 'direct-image-spine',
    2 => 'direct-svg-spine',
    3 => 'encoded-image-media-bag',
    4 => 'epub2_cover',
    5 => 'epub2_picture',
    6 => 'figure-caption-spine',
    7 => 'formatting',
    8 => 'img',
    9 => 'img_no_cover',
    10 => 'metadata-link-page-list-image',
    11 => 'metadata-link-vocab-diagnostics',
    12 => 'nested-path-media-metadata',
    13 => 'package-spine-nav-media-metadata',
    14 => 'raw-media-source-spine',
    15 => 'scripted-svg-manifest',
    16 => 'title-page-guide-media-metadata',
    17 => 'wasteland',
  ),
  'fixturesWithStylesheets' =>
  array (
    0 => 'epub2_cover',
    1 => 'epub2_no_cover',
    2 => 'epub2_picture',
    3 => 'features',
    4 => 'formatting',
    5 => 'img',
    6 => 'img_no_cover',
    7 => 'manifest-fallback-style',
    8 => 'manifest-href-encoding',
    9 => 'metadata-link-vocab-diagnostics',
    10 => 'missing-local-manifest-resource',
    11 => 'nested-path-media-metadata',
    12 => 'nested-rootfile-nonlinear-spine',
    13 => 'package-spine-nav-media-metadata',
    14 => 'title-page-guide-media-metadata',
    15 => 'wasteland',
    16 => 'xhtml-semantics-spine',
  ),
  'fixturesWithLandmarks' =>
  array (
    0 => 'bindings-collections-sidecars',
    1 => 'content-image-nav-media',
    2 => 'external-footnote-reference',
    3 => 'features',
    4 => 'formatting',
    5 => 'img',
    6 => 'img_no_cover',
    7 => 'manifest-href-encoding',
    8 => 'metadata-record-remote-nav',
    9 => 'multi-rootfile-nested-nav',
    10 => 'nav-ncx-linear-guide',
    11 => 'nested-path-media-metadata',
    12 => 'nested-rootfile-nonlinear-spine',
    13 => 'package-spine-nav-media-metadata',
    14 => 'parent-relative-nav',
    15 => 'spine-fallback-resource',
    16 => 'title-page-guide-media-metadata',
    17 => 'wasteland',
    18 => 'xhtml-ruby-table-mark',
    19 => 'xhtml-semantics-spine',
  ),
  'fixturesWithPageLists' =>
  array (
    0 => 'content-image-nav-media',
    1 => 'manifest-href-encoding',
    2 => 'metadata-link-page-list-image',
    3 => 'metadata-record-remote-nav',
    4 => 'multi-rootfile-nested-nav',
    5 => 'nested-path-media-metadata',
    6 => 'package-spine-nav-media-metadata',
    7 => 'page-list-cfi-navigation',
    8 => 'page-list-navigation',
    9 => 'spine-fallback-resource',
    10 => 'title-page-guide-media-metadata',
  ),
  'fixturesWithPageListCfiTargets' =>
  array (
    0 => 'page-list-cfi-navigation',
  ),
  'fixturesWithAuxiliaryNavigation' =>
  array (
    0 => 'appendix-navigation-guide',
    1 => 'audio-navigation',
    2 => 'auxiliary-lot-guide-index',
    3 => 'metadata-record-remote-nav',
    4 => 'nested-path-media-metadata',
    5 => 'page-list-navigation',
    6 => 'title-page-guide-media-metadata',
    7 => 'video-navigation',
  ),
  'fixturesWithRemoteManifestResources' =>
  array (
    0 => 'media-manifest-mix',
    1 => 'metadata-record-remote-nav',
    2 => 'nested-path-media-metadata',
    3 => 'remote-manifest-resource',
  ),
  'fixturesWithExternalManifestItems' =>
  array (
    0 => 'media-manifest-mix',
    1 => 'metadata-record-remote-nav',
    2 => 'nested-path-media-metadata',
    3 => 'remote-manifest-resource',
  ),
  'fixturesWithMissingLocalManifestItems' =>
  array (
    0 => 'missing-local-manifest-resource',
  ),
  'fixturesWithManifestFallbackItems' =>
  array (
    0 => 'accessibility-metadata-package',
    1 => 'bindings-collections-sidecars',
    2 => 'manifest-fallback-chain',
    3 => 'manifest-fallback-style',
    4 => 'manifest-href-encoding',
    5 => 'media-manifest-mix',
    6 => 'metadata-link-vocab-diagnostics',
    7 => 'metadata-record-remote-nav',
    8 => 'nav-ncx-linear-guide',
    9 => 'nested-path-media-metadata',
    10 => 'raw-media-source-spine',
    11 => 'spine-fallback-resource',
    12 => 'text-track-captions',
    13 => 'title-page-guide-media-metadata',
    14 => 'video-manifest-resource',
    15 => 'video-navigation',
    16 => 'xhtml-ruby-table-mark',
  ),
  'fixturesWithManifestFallbacks' =>
  array (
    0 => 'bindings-collections-sidecars',
    1 => 'manifest-fallback-chain',
    2 => 'manifest-fallback-style',
    3 => 'media-manifest-mix',
    4 => 'spine-fallback-resource',
  ),
  'fixturesWithResolvedManifestFallbacks' =>
  array (
    0 => 'bindings-collections-sidecars',
    1 => 'manifest-fallback-chain',
    2 => 'manifest-fallback-style',
    3 => 'media-manifest-mix',
    4 => 'spine-fallback-resource',
  ),
  'fixturesWithUsableManifestFallbacks' =>
  array (
    0 => 'bindings-collections-sidecars',
    1 => 'manifest-fallback-chain',
    2 => 'manifest-fallback-style',
    3 => 'media-manifest-mix',
    4 => 'spine-fallback-resource',
  ),
  'fixturesWithMissingManifestFallbacks' =>
  array (
    0 => 'accessibility-metadata-package',
    1 => 'manifest-href-encoding',
    2 => 'metadata-link-vocab-diagnostics',
    3 => 'metadata-record-remote-nav',
    4 => 'nav-ncx-linear-guide',
    5 => 'nested-path-media-metadata',
    6 => 'raw-media-source-spine',
    7 => 'text-track-captions',
    8 => 'title-page-guide-media-metadata',
    9 => 'video-manifest-resource',
    10 => 'video-navigation',
    11 => 'xhtml-ruby-table-mark',
  ),
  'fixturesWithMediaOverlays' =>
  array (
    0 => 'media-overlay-invalid-clips',
    1 => 'media-overlay-package',
    2 => 'missing-media-overlay',
  ),
  'fixturesWithResolvedMediaOverlays' =>
  array (
    0 => 'media-overlay-invalid-clips',
    1 => 'media-overlay-package',
  ),
  'fixturesWithMediaOverlayTextTargets' =>
  array (
    0 => 'media-overlay-invalid-clips',
    1 => 'media-overlay-package',
  ),
  'fixturesWithMediaOverlayAudioTargets' =>
  array (
    0 => 'media-overlay-invalid-clips',
    1 => 'media-overlay-package',
  ),
  'fixturesWithNonLinearSpineItems' =>
  array (
    0 => 'all-nonlinear-spine',
    1 => 'content-image-nav-media',
    2 => 'epub2_cover',
    3 => 'epub2_picture',
    4 => 'external-footnote-reference',
    5 => 'features',
    6 => 'formatting',
    7 => 'img',
    8 => 'img_no_cover',
    9 => 'manifest-href-encoding',
    10 => 'metadata-link-vocab-diagnostics',
    11 => 'multi-rootfile-nested-nav',
    12 => 'nav-ncx-linear-guide',
    13 => 'nested-path-media-metadata',
    14 => 'nested-rootfile-nonlinear-spine',
    15 => 'title-page-guide-media-metadata',
  ),
  'fixturesWithSpinePageSpreadItems' =>
  array (
    0 => 'metadata-link-vocab-diagnostics',
    1 => 'nested-path-media-metadata',
    2 => 'spine-page-spread',
    3 => 'xhtml-ruby-table-mark',
  ),
  'fixturesWithCollections' =>
  array (
    0 => 'bindings-collections-sidecars',
  ),
  'fixturesWithBindings' =>
  array (
    0 => 'bindings-collections-sidecars',
  ),
  'fixturesWithOcfSidecars' =>
  array (
    0 => 'bindings-collections-sidecars',
  ),
  'totals' =>
  array (
    'metadataCreators' => 68,
    'manifestItems' => 260,
    'readingOrderItems' => 108,
    'spinePageSpreadItems' => 6,
    'xhtmlAssets' => 172,
    'imageAssets' => 27,
    'stylesheetAssets' => 22,
    'navigationEntries' => 165,
    'landmarkEntries' => 24,
    'pageListEntries' => 16,
    'pageListCfiTargets' => 2,
    'auxiliaryNavigationEntries' => 8,
    'packageLinks' => 15,
    'packageLinkVocabularyRelTokens' => 25,
    'packageLinkVocabularyPropertyTokens' => 7,
    'packageLinkVocabularyResolvedTokens' => 5,
    'packageLinkVocabularyAbsoluteUrlTokens' => 2,
    'packageLinkVocabularyDuplicateTokens' => 2,
    'packageLinkVocabularyDiagnostics' => 6,
    'packageLinkMediaTypeItems' => 12,
    'packageLinkMediaTypeParameters' => 1,
    'linkHrefSuffixes' => 3,
    'linkHrefSuffixQueries' => 1,
    'linkHrefSuffixFragments' => 3,
    'guideReferences' => 23,
    'accessibilityEntries' => 7,
    'accessibilityLinkedRecords' => 1,
    'accessibilityAccessModes' => 2,
    'accessibilityFeatures' => 2,
    'accessibilityHazards' => 1,
    'accessibilityConformsTo' => 1,
    'remoteResourceManifestItems' => 4,
    'externalManifestItems' => 4,
    'missingLocalManifestItems' => 1,
    'manifestFallbackItems' => 19,
    'manifestFallbacks' => 6,
    'resolvedManifestFallbacks' => 6,
    'usableManifestFallbacks' => 6,
    'missingManifestFallbacks' => 13,
    'mediaOverlays' => 3,
    'resolvedMediaOverlays' => 2,
    'missingMediaOverlays' => 1,
    'mediaOverlayReferencedContentItems' => 3,
    'mediaOverlayTimelineItems' => 3,
    'mediaOverlayClipTimings' => 3,
    'mediaOverlayValidClipTimings' => 2,
    'mediaOverlayInvalidClipTimings' => 1,
    'mediaOverlayTextLocalTargets' => 3,
    'mediaOverlayAudioLocalTargets' => 2,
    'mediaOverlayDurations' => 5,
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
  ),
);

        $expectedPackageFeatureSignatureSha256 = '5a3753f72c22cbcd34bb3024bed0b648ece3ec5d366dde320f83d27e98800c9b';
        $expectedCurrentNativeAstSignatureSha256 = 'febb26d07147f5ed01e222cb31995c6b444e382d41fa8ab8bdb236ff2786eecf';
        $expectedCurrentNativeAstFixtures = array (
  0 => 'accessibility-metadata-package',
  1 => 'all-nonlinear-spine',
  2 => 'appendix-navigation-guide',
  3 => 'audio-navigation',
  4 => 'auxiliary-lot-guide-index',
  5 => 'bindings-collections-sidecars',
  6 => 'blockquote-list-spine',
  7 => 'code-block-spine',
  8 => 'content-image-nav-media',
  9 => 'cross-spine-internal-links',
  10 => 'definition-list-spine',
  11 => 'direct-image-spine',
  12 => 'direct-svg-spine',
  13 => 'duplicate-spine-idref',
  14 => 'encoded-image-media-bag',
  15 => 'epub2_cover',
  16 => 'epub2_no_cover',
  17 => 'epub2_picture',
  18 => 'epub3-ncx-toc-fallback',
  19 => 'external-footnote-reference',
  20 => 'features',
  21 => 'figure-caption-spine',
  22 => 'font-manifest-resource',
  23 => 'formatting',
  24 => 'fragment-nav-spine',
  25 => 'guide-bibliography-reference',
  26 => 'guide-glossary-reference',
  27 => 'guide-notes-reference',
  28 => 'guide-preface-reference',
  29 => 'img',
  30 => 'img_no_cover',
  31 => 'inline-abbr-subscript-superscript',
  32 => 'language-french-metadata',
  33 => 'main-section-spine',
  34 => 'manifest-fallback-chain',
  35 => 'manifest-fallback-style',
  36 => 'manifest-href-encoding',
  37 => 'mathml-spine',
  38 => 'measurement-inline-spine',
  39 => 'media-manifest-mix',
  40 => 'media-overlay-invalid-clips',
  41 => 'media-overlay-package',
  42 => 'metadata-link-page-list-image',
  43 => 'metadata-link-vocab-diagnostics',
  44 => 'metadata-record-remote-nav',
  45 => 'metadata-search-link-semantics',
  46 => 'missing-local-manifest-resource',
  47 => 'missing-media-overlay',
  48 => 'multi-rootfile-nested-nav',
  49 => 'nav-ncx-linear-guide',
  50 => 'nested-path-media-metadata',
  51 => 'nested-rootfile-nonlinear-spine',
  52 => 'package-spine-nav-media-metadata',
  53 => 'page-list-cfi-navigation',
  54 => 'page-list-navigation',
  55 => 'parent-relative-nav',
  56 => 'raw-media-source-spine',
  57 => 'remote-manifest-resource',
  58 => 'rendition-layout-property',
  59 => 'scripted-svg-manifest',
  60 => 'scripted-xhtml-resource',
  61 => 'spine-fallback-resource',
  62 => 'spine-page-spread',
  63 => 'standalone-footnote',
  64 => 'text-track-captions',
  65 => 'title-page-guide-media-metadata',
  66 => 'video-manifest-resource',
  67 => 'video-navigation',
  68 => 'wasteland',
  69 => 'xhtml-address-spine',
  70 => 'xhtml-chapter-section-spine',
  71 => 'xhtml-del-edit-mark-spine',
  72 => 'xhtml-details-summary-spine',
  73 => 'xhtml-kbd-samp-var-spine',
  74 => 'xhtml-ruby-table-mark',
  75 => 'xhtml-semantics-spine',
);


        $t->same(76, count($epubFiles), 'Checked-in EPUB fixture count changed');
        $t->same(76, count($nativeFiles), 'Checked-in native fixture count changed');

        $harness = new EpubNativeAstPackageComparisonHarness();
        $report = $harness->run($root);
        $text = $harness->formatReport($report);

        $t->same('completed', $report['status']);
        $t->same(76, $report['totalEpubCount']);
        $t->same(76, $report['comparedEpubCount']);
        $t->same(76, $report['packageParsedCount']);
        $t->same(76, $report['readerParsedCount']);
        $t->same(0, $report['packageParseFailureCount']);
        $t->same(0, $report['readerParseFailureCount']);
        $t->same(76, $report['totalPairCount']);
        $t->same(76, $report['comparedPairCount']);
        $t->same(76, $report['epubPairParsedCount']);
        $t->same(76, $report['nativeParsedCount']);
        $t->same(76, $report['bothParsedCount']);
        $t->same(0, $report['astParseFailureCount']);
        $t->same(0, $report['nativeParseFailureCount']);
        $t->same(76, $report['normalizedAstMatchCount']);
        $t->same(0, $report['normalizedAstMismatchCount']);
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredPackageParity($report, 76));
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredNativeReadiness($report, 76));
        $t->same(false, EpubNativeAstPackageComparisonHarness::hasRequiredMappedParity($report, 2));
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredMappedParity($report, 76));
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredFixtureIdentity($report));
        $t->same('valid-checked-in-current-epub-fixture-identity', $report['fixtureIdentity']['validation']['status']);
        $t->same([], $report['fixtureIdentity']['validation']['issues']);
        $t->same(152, $report['fixtureIdentity']['expectedFileCount']);
        $t->same(152, $report['fixtureIdentity']['observedFileCount']);
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
        $t->same('checked-in-current-upstream-epub-reader-76-fixture-snapshot', $report['packageFeatureSignature']['scope']);
        $t->same($expectedPackageFeatureSignatureSha256, $report['packageFeatureSignature']['sha256']);
        $t->same($expectedPackageFeatureSignatureSha256, $report['packageFeatureSignature']['expectedSha256']);
        $t->same(true, $report['packageFeatureSignature']['hashMatchesExpected']);
        $t->same(true, $report['packageFeatureSignature']['matchesExpected']);
        $t->same('valid-checked-in-current-epub-package-feature-signature', $report['packageFeatureSignature']['validation']['status']);
        $t->same([], $report['packageFeatureSignature']['validation']['issues']);
        $t->same(true, $report['packageFeatureSignature']['validation']['packageFeatureCoverageMatchesExpected']);
        $t->same('checked-in-current-epub-normalized-native-ast-signature', $report['currentNativeAstSignature']['kind']);
        $t->same('sha256-canonical-json-v1', $report['currentNativeAstSignature']['algorithm']);
        $t->same('checked-in-current-upstream-epub-reader-76-fixture-normalized-ast-snapshot', $report['currentNativeAstSignature']['scope']);
        $t->same(76, $report['currentNativeAstSignature']['fixtureCount']);
        $t->same(76, $report['currentNativeAstSignature']['expectedFixtureCount']);
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
        $t->same(76, count($report['packageFeatureCoverage']['fixtureFeatureSignatures']));
        $t->same(76, count($report['currentNativeAstSignature']['fixtureSignatures']));
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['appendix', 'toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'xhtml' => 2,
            ],
            'guideReferenceTypeCounts' => ['appendix' => 1],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['appendix-navigation-guide']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['appendix-navigation-guide']['normalizedAstMatches']);
        $t->same(
            'f18c7e5782a589d872f7f674527c119e658c4c84b3f6c62d59ad21694529033f',
            $report['currentNativeAstSignature']['fixtureSignatures']['appendix-navigation-guide']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['appendix-navigation-guide']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['appendix-navigation-guide']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['appendix-navigation-guide']['epubTopTypes']);
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
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['code-block-spine']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['code-block-spine']['normalizedAstMatches']);
        $t->same(
            'f007f06d06beb27543f03fd2371364e075997cebbdf8074e42fcd503a945f955',
            $report['currentNativeAstSignature']['fixtureSignatures']['code-block-spine']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['code-block-spine']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['code-block-spine']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph', 'heading', 'paragraph', 'code_block'], $report['currentNativeAstSignature']['fixtureSignatures']['code-block-spine']['epubTopTypes']);
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
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'xhtml' => 2,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['cross-spine-internal-links']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['cross-spine-internal-links']['normalizedAstMatches']);
        $t->same(
            '301ff5c1738c505c3a0bbe27c6fb167d2cfd9a0bc9178f285d1117b4f78cf245',
            $report['currentNativeAstSignature']['fixtureSignatures']['cross-spine-internal-links']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['cross-spine-internal-links']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['cross-spine-internal-links']['nativeNormalizedAstSha256']
        );
        $t->same(
            ['paragraph', 'heading', 'paragraph', 'paragraph', 'heading', 'paragraph'],
            $report['currentNativeAstSignature']['fixtureSignatures']['cross-spine-internal-links']['epubTopTypes']
        );
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
            'navigationSectionTypes' => ['landmarks', 'loi', 'page-list', 'toc'],
            'manifestResourceKindCounts' => [
                'asset' => 1,
                'navigation' => 1,
                'style' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => ['text' => 1],
            'packageLinkRelCounts' => [
                'alternate' => 1,
                'preview' => 1,
                'record' => 1,
            ],
            'packageLinkVocabularyRelCounts' => [
                'alternate' => 1,
                'preview' => 1,
                'record' => 1,
            ],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['metadata-record-remote-nav']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['metadata-record-remote-nav']['normalizedAstMatches']);
        $t->same(
            '6716e05903715d07c24abc686a7eb4bed7c6a59d00c98792386e2568268feb71',
            $report['currentNativeAstSignature']['fixtureSignatures']['metadata-record-remote-nav']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['metadata-record-remote-nav']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['metadata-record-remote-nav']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph', 'div'], $report['currentNativeAstSignature']['fixtureSignatures']['metadata-record-remote-nav']['epubTopTypes']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'xhtml' => 1,
            ],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [
                'record' => 1,
                'search' => 1,
            ],
            'packageLinkVocabularyRelCounts' => [
                'record' => 1,
                'search' => 1,
            ],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['metadata-search-link-semantics']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['metadata-search-link-semantics']['normalizedAstMatches']);
        $t->same(
            'cc0dd0c1e0505737820c63f2a042081912cd4cafecf08a7047e6c8b0e7e8e393',
            $report['currentNativeAstSignature']['fixtureSignatures']['metadata-search-link-semantics']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['metadata-search-link-semantics']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['metadata-search-link-semantics']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph', 'div'], $report['currentNativeAstSignature']['fixtureSignatures']['metadata-search-link-semantics']['epubTopTypes']);
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
            'packageLinkVocabularyRelCounts' => [
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
            'packageLinkVocabularyRelCounts' => ['preview' => 1],
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
            'navigationSectionTypes' => ['landmarks', 'page-list', 'toc'],
            'manifestResourceKindCounts' => [
                'navigation' => 1,
                'xhtml' => 3,
            ],
            'guideReferenceTypeCounts' => ['text' => 1],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['multi-rootfile-nested-nav']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['multi-rootfile-nested-nav']['normalizedAstMatches']);
        $t->same(
            '488c5f0379e742bedc284e89923c295dd861106ad5e1cbab89854b86f156c037',
            $report['currentNativeAstSignature']['fixtureSignatures']['multi-rootfile-nested-nav']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['multi-rootfile-nested-nav']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['multi-rootfile-nested-nav']['nativeNormalizedAstSha256']
        );
        $t->same(
            ['paragraph', 'heading', 'paragraph', 'heading', 'paragraph'],
            $report['currentNativeAstSignature']['fixtureSignatures']['multi-rootfile-nested-nav']['epubTopTypes']
        );
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
            'packageLinkVocabularyRelCounts' => ['record' => 1],
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
        $fallbackStylePackage = EpubPackage::fromPackage(ZipPackage::fromString((string) file_get_contents($root . '/manifest-fallback-style.epub')));
        $fallbackStyleReport = $fallbackStylePackage->manifestFallbacks();
        $fallbackStyleItem = $fallbackStyleReport['itemsById']['widget'];
        $t->same(1, $fallbackStyleReport['itemCount']);
        $t->same(1, $fallbackStyleReport['fallbackCount']);
        $t->same(1, $fallbackStyleReport['resolvedFallbackCount']);
        $t->same(1, $fallbackStyleReport['usableFallbackCount']);
        $t->same(1, $fallbackStyleReport['fallbackStyleCount']);
        $t->same(1, $fallbackStyleReport['resolvedFallbackStyleCount']);
        $t->same([], $fallbackStyleReport['diagnostics']);
        $t->same('fallback', $fallbackStyleItem['fallbackId']);
        $t->same('/EPUB/fallback.xhtml', $fallbackStyleItem['fallbackTerminalPartName']);
        $t->same('style', $fallbackStyleItem['fallbackStyleId']);
        $t->same(true, $fallbackStyleItem['fallbackStyleResolved']);
        $t->same(true, $fallbackStyleItem['fallbackStyleUsable']);
        $t->same(true, $fallbackStyleItem['fallbackStyleTerminalCssStyle']);
        $t->same('/EPUB/styles/fallback.css', $fallbackStyleItem['fallbackStyleTerminalPartName']);
        $t->same([], $fallbackStyleItem['fallbackStyleDiagnostics']);
        $t->same([
            'navigationType' => 'nav',
            'navigationSectionTypes' => ['toc'],
            'manifestResourceKindCounts' => [
                'asset' => 1,
                'navigation' => 1,
                'style' => 1,
                'xhtml' => 2,
            ],
            'guideReferenceTypeCounts' => ['text' => 1],
            'packageLinkRelCounts' => [],
            'coverImagePartPresent' => false,
        ], $report['packageFeatureCoverage']['fixtureFeatureSignatures']['manifest-fallback-style']);
        $t->same(true, $report['currentNativeAstSignature']['fixtureSignatures']['manifest-fallback-style']['normalizedAstMatches']);
        $t->same(
            '8806f3ca7ec9e25e5e6572bbf84f16e9f2baaaa43d6b02817dd5237e92a295d5',
            $report['currentNativeAstSignature']['fixtureSignatures']['manifest-fallback-style']['epubNormalizedAstSha256']
        );
        $t->same(
            $report['currentNativeAstSignature']['fixtureSignatures']['manifest-fallback-style']['epubNormalizedAstSha256'],
            $report['currentNativeAstSignature']['fixtureSignatures']['manifest-fallback-style']['nativeNormalizedAstSha256']
        );
        $t->same(['paragraph', 'heading', 'paragraph'], $report['currentNativeAstSignature']['fixtureSignatures']['manifest-fallback-style']['epubTopTypes']);
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
        $t->same(152, $report['runnerEvidence']['checkedInSnapshot']['expectedFileCount']);
        $t->same(76, $report['runnerEvidence']['checkedInSnapshot']['expectedPairCount']);
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
        $t->contains('packages: total=76 compared=76 packageParsed=76 readerParsed=76 packageFailures=0 readerFailures=0', $text);
        $t->contains('normalizedAst: matches=76 (100.00%) mismatches=0', $text);
        $t->contains('fixtureIdentity: status=valid-checked-in-current-epub-fixture-identity expected=152 observed=152', $text);
        $t->contains('packageFeatureCoverage: fixtures=76 nav=68 ncx=4 covers=6 landmarks=20 pageLists=11 pageListCfiFixtures=1 pageListCfiTargets=2 auxiliaryNav=8 metadataCreators=68 accessibilityFixtures=2 accessibilityEntries=7 accessibilityLinkedRecords=1 accessibilityProperties=accessMode:2,accessibilityFeature:2,accessibilityHazard:1,accessibilitySummary:1,conformsTo:1 manifestItems=260', $text);
        $t->contains('spineLinear=linear:92,non-linear:16 nonLinearSpineFixtures=16 spinePageSpread=left:2,right:4 pageSpreadFixtures=4', $text);
        $t->contains('packageFeatureSignature: status=valid-checked-in-current-epub-package-feature-signature matchesExpected=true sha256=' . $expectedPackageFeatureSignatureSha256, $text);
        $t->contains('currentNativeAstSignature: status=valid-checked-in-current-epub-normalized-native-ast-signature matchesExpected=true fixtures=76 sha256=' . $expectedCurrentNativeAstSignatureSha256, $text);
        $t->contains('runnerEvidence: status=not-run plan=planned-not-run executed=false', $text);
        $t->contains('resourceKinds=asset:14,audio:5,cover-image:4,font:1,image:21,media-overlay:2,navigation:74,script:1,style:25,svg:2,text-track:2,video:5,xhtml:104', $text);
        $t->contains('guideRefTypes=appendix:1,bibliography:1,cover:3,glossary:1,index:1,notes:1,preface:1,text:12,title-page:1,toc:1', $text);
        $t->contains('packageLinkRels=accessibility-summary:1,alternate:2,bad/token:1,cc:attributionURL:1,cc:license:2,https://example.invalid/link-rel#review:1,preview:3,record:11,schema:associatedMedia:1,search:1,unknown:missing:1', $text);
        $t->contains('packageLinkMediaTypes=application/json:8,application/ld+json:2,application/opensearchdescription+xml:1,text/html:1 packageLinkParamFixtures=1 packageLinkParams=1 packageLinkParamNames=profile:1 linkHrefSuffixFixtures=2 linkHrefSuffixes=3 linkHrefSuffixSources=collection-link:2,package-link:1 linkHrefQueries=1 linkHrefFragments=3', $text);
        $t->contains('remoteManifest=4 externalManifest=4 missingLocalManifest=1 manifestFallbackItems=17 manifestFallbacks=6 resolvedFallbacks=5 usableFallbacks=5 missingFallbacks=12', $text);
        $t->contains('mediaOverlayFixtures=3 resolvedMediaOverlayFixtures=2 mediaOverlays=3 resolvedMediaOverlays=2 mediaOverlayTimelineItems=3 mediaOverlayClipTimings=3 mediaOverlayValidClipTimings=2 mediaOverlayInvalidClipTimings=1 mediaOverlayTextTargets=3 mediaOverlayAudioTargets=2 mediaOverlayDurations=5', $text);
        $t->contains('encryptionFixtures=3 obfuscatedFontFixtures=3 blockedEncryptedByteExposureFixtures=3 encryptionItems=3 obfuscatedFonts=3 blockedEncryptedByteExposures=3 encryptionDiagnostics=6 encryptionRoles=font:3', $text);
        $t->contains('collectionFixtures=1 collections=2 collectionLinks=3 collectionRoles=index:1,role:primary:1,schema:hasPart:1 collectionLinkRels=contents:1,index:1,record:1', $text);
        $t->contains('bindingFixtures=1 bindings=1 bindingResolvedHandlers=1 bindingParams=1 bindingMediaTypes=application/x-bound-widget:1', $text);
        $t->contains('ocfSidecarFixtures=1 ocfSidecars=4 ocfSidecarKinds=manifest:1,metadata:1,rights:1,signatures:1', $text);
        $t->contains('opfParts=/EPUB/package.opf:59,/EPUB/wasteland.opf:1,/OEBPS/content.opf:3,/OPS/book/package.opf:4,/OPS/package.opf:9', $text);

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
            . ' --require-package-parity=76'
            . ' --require-native-readiness=76'
            . ' --require-mapped-parity=76';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same($root, $decoded['upstreamEpubDirectory']);
        $t->same(76, $decoded['packageParsedCount']);
        $t->same(76, $decoded['readerParsedCount']);
        $t->same(76, $decoded['nativeParsedCount']);
        $t->same(76, $decoded['normalizedAstMatchCount']);
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
            . ' --require-package-parity=76'
            . ' --require-native-readiness=76'
            . ' --require-mapped-parity=76'
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
        $t->same(76, $defaultFixtureIdentityDecoded['normalizedAstMatchCount']);
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
