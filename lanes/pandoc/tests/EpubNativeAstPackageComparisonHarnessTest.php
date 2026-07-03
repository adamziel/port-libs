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
            $t->same('nav', $summary['navigationType']);
            $t->same(1, $summary['navigationEntryCount']);
            $t->same(4, $summary['navigationSectionCount']);
            $t->same(['landmarks', 'loi', 'page-list', 'toc'], $summary['navigationSectionTypes']);
            $t->same(1, $summary['landmarkEntryCount']);
            $t->same(1, $summary['pageListEntryCount']);
            $t->same(1, $summary['auxiliaryNavigationEntryCount']);
            $t->same(1, $coverage['fixtureCount']);
            $t->same(['en' => 1], $coverage['metadataLanguageCounts']);
            $t->same(['nav' => 1], $coverage['navigationTypeCounts']);
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
            $t->same([], $coverage['fixturesWithCoverImagePart']);
            $t->same([], $coverage['fixturesWithImages']);
            $t->same([], $coverage['fixturesWithStylesheets']);
            $t->same(['generated-navigation'], $coverage['fixturesWithLandmarks']);
            $t->same(['generated-navigation'], $coverage['fixturesWithPageLists']);
            $t->same(['generated-navigation'], $coverage['fixturesWithAuxiliaryNavigation']);
            $t->same(['generated-navigation'], $coverage['fixturesWithRemoteManifestResources']);
            $t->same(['generated-navigation'], $coverage['fixturesWithExternalManifestItems']);
            $t->same([], $coverage['fixturesWithMissingLocalManifestItems']);
            $t->same([
                'metadataCreators' => 1,
                'manifestItems' => 4,
                'readingOrderItems' => 1,
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
            $t->same(['nav' => 1], $decoded['packageFeatureCoverage']['navigationTypeCounts']);
            $t->same(['nav' => 1, 'remote-resources' => 1], $decoded['packageFeatureCoverage']['manifestPropertyCounts']);
            $t->same([
                'asset' => 1,
                'navigation' => 1,
                'style' => 1,
                'xhtml' => 1,
            ], $decoded['packageFeatureCoverage']['manifestResourceKindCounts']);
            $t->same(['text' => 1], $decoded['packageFeatureCoverage']['guideReferenceTypeCounts']);
            $t->same(['record' => 1], $decoded['packageFeatureCoverage']['packageLinkRelCounts']);
            $t->same(
                $coverage['fixtureFeatureSignatures'],
                $decoded['packageFeatureCoverage']['fixtureFeatureSignatures']
            );
            $t->same(['generated-navigation'], $decoded['packageFeatureCoverage']['fixturesWithCreators']);
            $t->same(1, $decoded['packageFeatureCoverage']['totals']['metadataCreators']);
        } finally {
            $removeTree($root);
        }
    },

    'checked-in upstream current epub fixtures satisfy strict package and native ast gates' => static function (TestRunner $t) use ($fixtureRoot): void {
        $root = $fixtureRoot();
        $epubFiles = glob($root . '/*.epub') ?: [];
        $nativeFiles = glob($root . '/*.native') ?: [];
        $expectedFixtureIdentity = [
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
            'features.epub' => [
                'sha256' => '6bf9a102249d58b32f14b39dfbc966bdecadff68a3fb707cb3ca62334734358a',
                'bytes' => 8970,
            ],
            'features.native' => [
                'sha256' => 'c384a314081ecc860bb0f8a9ffb5273976ed56341e4f16e05dd448126e85c41f',
                'bytes' => 48453,
            ],
            'formatting.epub' => [
                'sha256' => '491fc57ec384449a23c4f2abdcfe91be9ab2a07f50f466fb8d80775b89bf3965',
                'bytes' => 14022,
            ],
            'formatting.native' => [
                'sha256' => '9041b6aa23827579a4db45074bd9b26077337defc26ec62ab3b57f676f4eeb21',
                'bytes' => 172999,
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
            'wasteland.epub' => [
                'sha256' => '151ec5dbca33e39a4e3f6894e92fa5a101290bdeaaa792e0700595971456a278',
                'bytes' => 25840,
            ],
            'wasteland.native' => [
                'sha256' => '0a268af28518f063604659adb2ff27b123c771f8312b60fb40445bb2c551bbac',
                'bytes' => 150477,
            ],
        ];
        $expectedPackageFeatureCoverage = [
            'fixtureCount' => 8,
            'metadataLanguageCounts' => [
                'de-DE' => 3,
                'en' => 4,
                'en-US' => 1,
            ],
            'fixturesWithCreators' => [
                'epub2_cover',
                'epub2_no_cover',
                'epub2_picture',
                'features',
                'formatting',
                'img',
                'img_no_cover',
                'wasteland',
            ],
            'navigationTypeCounts' => [
                'nav' => 5,
                'ncx' => 3,
            ],
            'manifestMediaTypeCounts' => [
                'application/x-dtbncx+xml' => 4,
                'application/xhtml+xml' => 23,
                'image/gif' => 3,
                'image/jpeg' => 5,
                'image/png' => 3,
                'text/css' => 13,
            ],
            'manifestPropertyCounts' => [
                'cover-image' => 2,
                'mathml' => 2,
                'nav' => 5,
                'switch' => 1,
            ],
            'manifestResourceKindCounts' => [
                'cover-image' => 2,
                'image' => 9,
                'navigation' => 9,
                'style' => 13,
                'xhtml' => 18,
            ],
            'navigationSectionTypes' => [
                'landmarks',
                'toc',
            ],
            'guideReferenceTypeCounts' => [
                'cover' => 2,
                'toc' => 1,
            ],
            'fixturesWithGuideReferences' => [
                'epub2_cover',
                'epub2_no_cover',
                'epub2_picture',
            ],
            'fixturesWithPackageLinks' => [
                'wasteland',
            ],
            'packageLinkRelCounts' => [
                'cc:attributionURL' => 1,
                'cc:license' => 2,
            ],
            'fixturesWithCoverImagePart' => [
                'epub2_cover',
                'epub2_picture',
                'img',
                'wasteland',
            ],
            'fixturesWithImages' => [
                'epub2_cover',
                'epub2_picture',
                'formatting',
                'img',
                'img_no_cover',
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
                'wasteland',
            ],
            'fixturesWithLandmarks' => [
                'features',
                'formatting',
                'img',
                'img_no_cover',
                'wasteland',
            ],
            'fixturesWithPageLists' => [],
            'fixturesWithAuxiliaryNavigation' => [],
            'fixturesWithRemoteManifestResources' => [],
            'fixturesWithExternalManifestItems' => [],
            'fixturesWithMissingLocalManifestItems' => [],
            'totals' => [
                'metadataCreators' => 28,
                'manifestItems' => 51,
                'readingOrderItems' => 22,
                'xhtmlAssets' => 23,
                'imageAssets' => 11,
                'stylesheetAssets' => 13,
                'navigationEntries' => 90,
                'landmarkEntries' => 7,
                'pageListEntries' => 0,
                'auxiliaryNavigationEntries' => 0,
                'packageLinks' => 3,
                'guideReferences' => 3,
                'remoteResourceManifestItems' => 0,
                'externalManifestItems' => 0,
                'missingLocalManifestItems' => 0,
            ],
        ];
        $expectedPackageFeatureSignatureSha256 = '4bd4dc92125c30c361010936e6a2ca7bc8da3e2efe6ad5096681065deefde3c3';

        $t->same(8, count($epubFiles), 'Checked-in EPUB fixture count changed');
        $t->same(8, count($nativeFiles), 'Checked-in native fixture count changed');

        $harness = new EpubNativeAstPackageComparisonHarness();
        $report = $harness->run($root);
        $text = $harness->formatReport($report);

        $t->same('completed', $report['status']);
        $t->same(8, $report['totalEpubCount']);
        $t->same(8, $report['comparedEpubCount']);
        $t->same(8, $report['packageParsedCount']);
        $t->same(8, $report['readerParsedCount']);
        $t->same(0, $report['packageParseFailureCount']);
        $t->same(0, $report['readerParseFailureCount']);
        $t->same(8, $report['totalPairCount']);
        $t->same(8, $report['comparedPairCount']);
        $t->same(8, $report['epubPairParsedCount']);
        $t->same(8, $report['nativeParsedCount']);
        $t->same(8, $report['bothParsedCount']);
        $t->same(0, $report['astParseFailureCount']);
        $t->same(0, $report['nativeParseFailureCount']);
        $t->same(8, $report['normalizedAstMatchCount']);
        $t->same(0, $report['normalizedAstMismatchCount']);
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredPackageParity($report, 8));
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredNativeReadiness($report, 8));
        $t->same(false, EpubNativeAstPackageComparisonHarness::hasRequiredMappedParity($report, 2));
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredMappedParity($report, 8));
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredFixtureIdentity($report));
        $t->same('valid-checked-in-current-epub-fixture-identity', $report['fixtureIdentity']['validation']['status']);
        $t->same([], $report['fixtureIdentity']['validation']['issues']);
        $t->same(16, $report['fixtureIdentity']['expectedFileCount']);
        $t->same(16, $report['fixtureIdentity']['observedFileCount']);
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
        $t->same('checked-in-current-upstream-epub-reader-8-fixture-snapshot', $report['packageFeatureSignature']['scope']);
        $t->same($expectedPackageFeatureSignatureSha256, $report['packageFeatureSignature']['sha256']);
        $t->same($expectedPackageFeatureSignatureSha256, $report['packageFeatureSignature']['expectedSha256']);
        $t->same(true, $report['packageFeatureSignature']['hashMatchesExpected']);
        $t->same(true, $report['packageFeatureSignature']['matchesExpected']);
        $t->same('valid-checked-in-current-epub-package-feature-signature', $report['packageFeatureSignature']['validation']['status']);
        $t->same([], $report['packageFeatureSignature']['validation']['issues']);
        $t->same(true, $report['packageFeatureSignature']['validation']['packageFeatureCoverageMatchesExpected']);
        $t->same(8, count($report['packageFeatureCoverage']['fixtureFeatureSignatures']));
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
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredCurrentPackageFeatureCoverage($report));
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredCurrentPackageFeatureSignature($report));
        $t->same('covered-by-current-package-evidence', $report['orderedRemainingGaps'][0]['status']);
        $t->same('covered-by-current-normalized-ast-evidence', $report['orderedRemainingGaps'][1]['status']);
        $t->contains('packages: total=8 compared=8 packageParsed=8 readerParsed=8 packageFailures=0 readerFailures=0', $text);
        $t->contains('normalizedAst: matches=8 (100.00%) mismatches=0', $text);
        $t->contains('fixtureIdentity: status=valid-checked-in-current-epub-fixture-identity expected=16 observed=16', $text);
        $t->contains('packageFeatureCoverage: fixtures=8 nav=5 ncx=3 covers=4 landmarks=5 pageLists=0 auxiliaryNav=0 metadataCreators=28 manifestItems=51', $text);
        $t->contains('packageFeatureSignature: status=valid-checked-in-current-epub-package-feature-signature matchesExpected=true sha256=' . $expectedPackageFeatureSignatureSha256, $text);
        $t->contains('resourceKinds=cover-image:2,image:9,navigation:9,style:13,xhtml:18', $text);
        $t->contains('guideRefTypes=cover:2,toc:1', $text);
        $t->contains('packageLinkRels=cc:attributionURL:1,cc:license:2', $text);
        $t->contains('remoteManifest=0 externalManifest=0 missingLocalManifest=0', $text);

        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-native-ast-package.php')
            . ' --checked-in-fixtures'
            . ' --json'
            . ' summary'
            . ' --require-fixture-identity'
            . ' --require-current-package-feature-coverage'
            . ' --require-current-package-feature-signature'
            . ' --require-package-parity=8'
            . ' --require-native-readiness=8'
            . ' --require-mapped-parity=8';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same($root, $decoded['upstreamEpubDirectory']);
        $t->same(8, $decoded['packageParsedCount']);
        $t->same(8, $decoded['readerParsedCount']);
        $t->same(8, $decoded['nativeParsedCount']);
        $t->same(8, $decoded['normalizedAstMatchCount']);
        $t->same(0, $decoded['normalizedAstMismatchCount']);
        $t->same('valid-checked-in-current-epub-fixture-identity', $decoded['fixtureIdentity']['validation']['status']);
        foreach ($expectedPackageFeatureCoverage as $key => $expected) {
            $t->same($expected, $decoded['packageFeatureCoverage'][$key]);
        }
        $t->same($expectedPackageFeatureSignatureSha256, $decoded['packageFeatureSignature']['sha256']);
        $t->same(true, $decoded['packageFeatureSignature']['matchesExpected']);
        $t->same('valid-checked-in-current-epub-package-feature-signature', $decoded['packageFeatureSignature']['validation']['status']);
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredCurrentPackageFeatureSignature($decoded));
        $t->same(
            $report['packageFeatureCoverage']['fixtureFeatureSignatures'],
            $decoded['packageFeatureCoverage']['fixtureFeatureSignatures']
        );

        $defaultFixtureIdentityCommand = 'env -u PANDOC_UPSTREAM_EPUB_DIR '
            . escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-native-ast-package.php')
            . ' --json'
            . ' summary'
            . ' --require-package-parity=8'
            . ' --require-native-readiness=8'
            . ' --require-mapped-parity=8'
            . ' --require-fixture-identity'
            . ' --require-current-package-feature-coverage'
            . ' --require-current-package-feature-signature';
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
        $t->same(8, $defaultFixtureIdentityDecoded['normalizedAstMatchCount']);
        $t->same('valid-checked-in-current-epub-fixture-identity', $defaultFixtureIdentityDecoded['fixtureIdentity']['validation']['status']);
        $t->same($expectedPackageFeatureSignatureSha256, $defaultFixtureIdentityDecoded['packageFeatureSignature']['sha256']);
        $t->same(true, $defaultFixtureIdentityDecoded['packageFeatureSignature']['matchesExpected']);
        $t->same(['nav' => 5, 'ncx' => 3], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['navigationTypeCounts']);
        $t->same($expectedPackageFeatureCoverage['fixturesWithCreators'], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['fixturesWithCreators']);
        $t->same(28, $defaultFixtureIdentityDecoded['packageFeatureCoverage']['totals']['metadataCreators']);
        $t->same(['cover-image' => 2, 'mathml' => 2, 'nav' => 5, 'switch' => 1], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['manifestPropertyCounts']);
        $t->same([
            'cover-image' => 2,
            'image' => 9,
            'navigation' => 9,
            'style' => 13,
            'xhtml' => 18,
        ], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['manifestResourceKindCounts']);
        $t->same(['cover' => 2, 'toc' => 1], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['guideReferenceTypeCounts']);
        $t->same([
            'cc:attributionURL' => 1,
            'cc:license' => 2,
        ], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['packageLinkRelCounts']);
        $t->same(
            $report['packageFeatureCoverage']['fixtureFeatureSignatures']['wasteland'],
            $defaultFixtureIdentityDecoded['packageFeatureCoverage']['fixtureFeatureSignatures']['wasteland']
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
