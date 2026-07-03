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
            $t->same([], $coverage['fixturesWithNonLinearSpineItems']);
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
                'manifestFallbackItems' => 1,
                'manifestFallbacks' => 0,
                'resolvedManifestFallbacks' => 0,
                'usableManifestFallbacks' => 0,
                'missingManifestFallbacks' => 1,
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
            'auxiliary-lot-guide-index.epub' => [
                'sha256' => '8581efb4630635b95af119442cb682181b0004b90d53c6c43dfa255fc1c5bb58',
                'bytes' => 1434,
            ],
            'auxiliary-lot-guide-index.native' => [
                'sha256' => '0cdecc48fd17c19b93fe001e19aac7fb7f4a09e04c80a4d833e55c1828485995',
                'bytes' => 211,
            ],
            'direct-image-spine.epub' => [
                'sha256' => '695bb5c110c2011b4567c6f4a62b5d3249e00be37cfaff92b965ce346b376cb7',
                'bytes' => 1355,
            ],
            'direct-image-spine.native' => [
                'sha256' => '8d430b8f87eee7fc5ced05f7c163b20486977f3d54c644b4ba913f00abde7f4c',
                'bytes' => 4110,
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
            'manifest-fallback-chain.epub' => [
                'sha256' => 'af579a53102ff39e74bf2f79df687384ba1897c961aba9be197ba575079e18a4',
                'bytes' => 1735,
            ],
            'manifest-fallback-chain.native' => [
                'sha256' => '54fe7e8b655152d47863121ec647bddd468e69bfab601a05af54fc00f07893d3',
                'bytes' => 180,
            ],
            'media-overlay-package.epub' => [
                'sha256' => '6af50dc4bf618cd964af7274a688aebcbd16da6804581325c00195b1721ed972',
                'bytes' => 1894,
            ],
            'media-overlay-package.native' => [
                'sha256' => '2083a3e8168ce9f47a3f6e8574fb8917a29b0760736a6123e238fc5681eef5e7',
                'bytes' => 192,
            ],
            'missing-local-manifest-resource.epub' => [
                'sha256' => '5ce06b74cde06eb0d06f1b41b73f99840983451abb9bb120e8206979ac16dca5',
                'bytes' => 1386,
            ],
            'missing-local-manifest-resource.native' => [
                'sha256' => '2eaad3b88904dc836c7d9993ccba2894946df1bb91d59524b63346c5ea24921c',
                'bytes' => 200,
            ],
            'nav-ncx-linear-guide.epub' => [
                'sha256' => '45b914d6e5ef83949c5432b7c523c383d323a3b9aa56499946155b88ace41f26',
                'bytes' => 2336,
            ],
            'nav-ncx-linear-guide.native' => [
                'sha256' => '0e44bc8507ce00254743af59dbdc8ab96508730543ae0fd19f8a1a26b97cc95f',
                'bytes' => 202,
            ],
            'page-list-navigation.epub' => [
                'sha256' => '449c6114a473e2db1df8cf69cd29fddaef4a14a160b65fd7fe30adf0c80b9365',
                'bytes' => 1394,
            ],
            'page-list-navigation.native' => [
                'sha256' => '3b5fb7863f0df2ba4875092b369aa2b5f8e6797ec0a1edc17232d594ee1047c6',
                'bytes' => 175,
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
            'video-manifest-resource.epub' => [
                'sha256' => '7db258c0f96c66dc1de9eeaa1fc75ca5e9fddf821b6f0783cd4b74f4f59013b5',
                'bytes' => 1508,
            ],
            'video-manifest-resource.native' => [
                'sha256' => '844b189a6f0de4d43e260e07766cdc0329db17c0963024d9fd866c80a73d2f6b',
                'bytes' => 179,
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
            'fixtureCount' => 24,
            'metadataLanguageCounts' => [
                'de-DE' => 3,
                'en' => 20,
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
                'nav' => 20,
                'ncx' => 3,
            ],
            'spineLinearStateCounts' => [
                'linear' => 34,
                'non-linear' => 7,
            ],
            'manifestMediaTypeCounts' => [
                'application/javascript' => 1,
                'application/json' => 1,
                'application/smil+xml' => 1,
                'application/x-dtbncx+xml' => 5,
                'application/x-fallback-demo' => 1,
                'application/xhtml+xml' => 55,
                'audio/mpeg' => 1,
                'font/woff2' => 1,
                'image/gif' => 4,
                'image/jpeg' => 6,
                'image/png' => 4,
                'image/svg+xml' => 1,
                'text/css' => 15,
                'video/mp4' => 1,
            ],
            'manifestPropertyCounts' => [
                'cover-image' => 2,
                'mathml' => 2,
                'nav' => 20,
                'remote-resources' => 1,
                'rendition:layout-pre-paginated' => 1,
                'scripted' => 2,
                'svg' => 2,
                'switch' => 1,
            ],
            'manifestResourceKindCounts' => [
                'asset' => 2,
                'audio' => 1,
                'cover-image' => 2,
                'font' => 1,
                'image' => 12,
                'media-overlay' => 1,
                'navigation' => 25,
                'script' => 1,
                'style' => 15,
                'svg' => 1,
                'video' => 1,
                'xhtml' => 35,
            ],
            'navigationSectionTypes' => [
                'landmarks',
                'loi',
                'lot',
                'page-list',
                'toc',
            ],
            'guideReferenceTypeCounts' => [
                'bibliography' => 1,
                'cover' => 2,
                'glossary' => 1,
                'index' => 1,
                'notes' => 1,
                'text' => 1,
                'toc' => 1,
            ],
            'fixturesWithGuideReferences' => [
                'auxiliary-lot-guide-index',
                'epub2_cover',
                'epub2_no_cover',
                'epub2_picture',
                'guide-bibliography-reference',
                'guide-glossary-reference',
                'guide-notes-reference',
                'nav-ncx-linear-guide',
            ],
            'fixturesWithPackageLinks' => [
                'nav-ncx-linear-guide',
                'wasteland',
            ],
            'packageLinkRelCounts' => [
                'cc:attributionURL' => 1,
                'cc:license' => 2,
                'record' => 1,
            ],
            'fixturesWithCoverImagePart' => [
                'epub2_cover',
                'epub2_picture',
                'img',
                'wasteland',
            ],
            'fixturesWithImages' => [
                'direct-image-spine',
                'epub2_cover',
                'epub2_picture',
                'formatting',
                'img',
                'img_no_cover',
                'scripted-svg-manifest',
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
                'missing-local-manifest-resource',
                'wasteland',
            ],
            'fixturesWithLandmarks' => [
                'features',
                'formatting',
                'img',
                'img_no_cover',
                'nav-ncx-linear-guide',
                'wasteland',
            ],
            'fixturesWithPageLists' => [
                'page-list-navigation',
            ],
            'fixturesWithAuxiliaryNavigation' => [
                'auxiliary-lot-guide-index',
                'page-list-navigation',
            ],
            'fixturesWithRemoteManifestResources' => [
                'remote-manifest-resource',
            ],
            'fixturesWithExternalManifestItems' => [
                'remote-manifest-resource',
            ],
            'fixturesWithMissingLocalManifestItems' => [
                'missing-local-manifest-resource',
            ],
            'fixturesWithManifestFallbackItems' => [
                'manifest-fallback-chain',
                'nav-ncx-linear-guide',
                'video-manifest-resource',
            ],
            'fixturesWithManifestFallbacks' => [
                'manifest-fallback-chain',
            ],
            'fixturesWithResolvedManifestFallbacks' => [
                'manifest-fallback-chain',
            ],
            'fixturesWithUsableManifestFallbacks' => [
                'manifest-fallback-chain',
            ],
            'fixturesWithMissingManifestFallbacks' => [
                'nav-ncx-linear-guide',
                'video-manifest-resource',
            ],
            'fixturesWithNonLinearSpineItems' => [
                'epub2_cover',
                'epub2_picture',
                'features',
                'formatting',
                'img',
                'img_no_cover',
                'nav-ncx-linear-guide',
            ],
            'totals' => [
                'metadataCreators' => 28,
                'manifestItems' => 97,
                'readingOrderItems' => 41,
                'xhtmlAssets' => 55,
                'imageAssets' => 15,
                'stylesheetAssets' => 14,
                'navigationEntries' => 105,
                'landmarkEntries' => 8,
                'pageListEntries' => 1,
                'auxiliaryNavigationEntries' => 2,
                'packageLinks' => 4,
                'guideReferences' => 8,
                'remoteResourceManifestItems' => 1,
                'externalManifestItems' => 1,
                'missingLocalManifestItems' => 1,
                'manifestFallbackItems' => 3,
                'manifestFallbacks' => 1,
                'resolvedManifestFallbacks' => 1,
                'usableManifestFallbacks' => 1,
                'missingManifestFallbacks' => 2,
            ],
        ];
        $expectedPackageFeatureSignatureSha256 = '8fef8a5de786ba46af53e378b9bb3fce8680e8d596a882fd2b00e7b772894edf';
        $expectedCurrentNativeAstSignatureSha256 = '7814ec1439b23843170aa562792e9e27020cb1dbfbc8664a7c020b5d2ddc5e38';
        $expectedCurrentNativeAstFixtures = [
            'auxiliary-lot-guide-index',
            'direct-image-spine',
            'epub2_cover',
            'epub2_no_cover',
            'epub2_picture',
            'features',
            'font-manifest-resource',
            'formatting',
            'guide-bibliography-reference',
            'guide-glossary-reference',
            'guide-notes-reference',
            'img',
            'img_no_cover',
            'manifest-fallback-chain',
            'media-overlay-package',
            'missing-local-manifest-resource',
            'nav-ncx-linear-guide',
            'page-list-navigation',
            'remote-manifest-resource',
            'rendition-layout-property',
            'scripted-svg-manifest',
            'scripted-xhtml-resource',
            'video-manifest-resource',
            'wasteland',
        ];

        $t->same(24, count($epubFiles), 'Checked-in EPUB fixture count changed');
        $t->same(24, count($nativeFiles), 'Checked-in native fixture count changed');

        $harness = new EpubNativeAstPackageComparisonHarness();
        $report = $harness->run($root);
        $text = $harness->formatReport($report);

        $t->same('completed', $report['status']);
        $t->same(24, $report['totalEpubCount']);
        $t->same(24, $report['comparedEpubCount']);
        $t->same(24, $report['packageParsedCount']);
        $t->same(24, $report['readerParsedCount']);
        $t->same(0, $report['packageParseFailureCount']);
        $t->same(0, $report['readerParseFailureCount']);
        $t->same(24, $report['totalPairCount']);
        $t->same(24, $report['comparedPairCount']);
        $t->same(24, $report['epubPairParsedCount']);
        $t->same(24, $report['nativeParsedCount']);
        $t->same(24, $report['bothParsedCount']);
        $t->same(0, $report['astParseFailureCount']);
        $t->same(0, $report['nativeParseFailureCount']);
        $t->same(24, $report['normalizedAstMatchCount']);
        $t->same(0, $report['normalizedAstMismatchCount']);
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredPackageParity($report, 24));
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredNativeReadiness($report, 24));
        $t->same(false, EpubNativeAstPackageComparisonHarness::hasRequiredMappedParity($report, 2));
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredMappedParity($report, 24));
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRequiredFixtureIdentity($report));
        $t->same('valid-checked-in-current-epub-fixture-identity', $report['fixtureIdentity']['validation']['status']);
        $t->same([], $report['fixtureIdentity']['validation']['issues']);
        $t->same(48, $report['fixtureIdentity']['expectedFileCount']);
        $t->same(48, $report['fixtureIdentity']['observedFileCount']);
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
        $t->same('checked-in-current-upstream-epub-reader-24-fixture-snapshot', $report['packageFeatureSignature']['scope']);
        $t->same($expectedPackageFeatureSignatureSha256, $report['packageFeatureSignature']['sha256']);
        $t->same($expectedPackageFeatureSignatureSha256, $report['packageFeatureSignature']['expectedSha256']);
        $t->same(true, $report['packageFeatureSignature']['hashMatchesExpected']);
        $t->same(true, $report['packageFeatureSignature']['matchesExpected']);
        $t->same('valid-checked-in-current-epub-package-feature-signature', $report['packageFeatureSignature']['validation']['status']);
        $t->same([], $report['packageFeatureSignature']['validation']['issues']);
        $t->same(true, $report['packageFeatureSignature']['validation']['packageFeatureCoverageMatchesExpected']);
        $t->same('checked-in-current-epub-normalized-native-ast-signature', $report['currentNativeAstSignature']['kind']);
        $t->same('sha256-canonical-json-v1', $report['currentNativeAstSignature']['algorithm']);
        $t->same('checked-in-current-upstream-epub-reader-24-fixture-normalized-ast-snapshot', $report['currentNativeAstSignature']['scope']);
        $t->same(24, $report['currentNativeAstSignature']['fixtureCount']);
        $t->same(24, $report['currentNativeAstSignature']['expectedFixtureCount']);
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
        $t->same(24, count($report['packageFeatureCoverage']['fixtureFeatureSignatures']));
        $t->same(24, count($report['currentNativeAstSignature']['fixtureSignatures']));
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
            ['paragraph', 'paragraph', 'paragraph'],
            $report['currentNativeAstSignature']['fixtureSignatures']['direct-image-spine']['epubTopTypes']
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
        $t->same(48, $report['runnerEvidence']['checkedInSnapshot']['expectedFileCount']);
        $t->same(24, $report['runnerEvidence']['checkedInSnapshot']['expectedPairCount']);
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
        $t->contains('packages: total=24 compared=24 packageParsed=24 readerParsed=24 packageFailures=0 readerFailures=0', $text);
        $t->contains('normalizedAst: matches=24 (100.00%) mismatches=0', $text);
        $t->contains('fixtureIdentity: status=valid-checked-in-current-epub-fixture-identity expected=48 observed=48', $text);
        $t->contains('packageFeatureCoverage: fixtures=24 nav=20 ncx=3 covers=4 landmarks=6 pageLists=1 auxiliaryNav=2 metadataCreators=28 manifestItems=97', $text);
        $t->contains('spineLinear=linear:34,non-linear:7 nonLinearSpineFixtures=7', $text);
        $t->contains('packageFeatureSignature: status=valid-checked-in-current-epub-package-feature-signature matchesExpected=true sha256=' . $expectedPackageFeatureSignatureSha256, $text);
        $t->contains('currentNativeAstSignature: status=valid-checked-in-current-epub-normalized-native-ast-signature matchesExpected=true fixtures=24 sha256=' . $expectedCurrentNativeAstSignatureSha256, $text);
        $t->contains('runnerEvidence: status=not-run plan=planned-not-run executed=false', $text);
        $t->contains('resourceKinds=asset:2,audio:1,cover-image:2,font:1,image:12,media-overlay:1,navigation:25,script:1,style:15,svg:1,video:1,xhtml:35', $text);
        $t->contains('guideRefTypes=bibliography:1,cover:2,glossary:1,index:1,notes:1,text:1,toc:1', $text);
        $t->contains('packageLinkRels=cc:attributionURL:1,cc:license:2,record:1', $text);
        $t->contains('remoteManifest=1 externalManifest=1 missingLocalManifest=1 manifestFallbackItems=3 manifestFallbacks=1 resolvedFallbacks=1 usableFallbacks=1 missingFallbacks=2', $text);

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
            . ' --require-package-parity=24'
            . ' --require-native-readiness=24'
            . ' --require-mapped-parity=24';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same($root, $decoded['upstreamEpubDirectory']);
        $t->same(24, $decoded['packageParsedCount']);
        $t->same(24, $decoded['readerParsedCount']);
        $t->same(24, $decoded['nativeParsedCount']);
        $t->same(24, $decoded['normalizedAstMatchCount']);
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
            . ' --require-package-parity=24'
            . ' --require-native-readiness=24'
            . ' --require-mapped-parity=24'
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
        $t->same(24, $defaultFixtureIdentityDecoded['normalizedAstMatchCount']);
        $t->same('valid-checked-in-current-epub-fixture-identity', $defaultFixtureIdentityDecoded['fixtureIdentity']['validation']['status']);
        $t->same($expectedPackageFeatureSignatureSha256, $defaultFixtureIdentityDecoded['packageFeatureSignature']['sha256']);
        $t->same(true, $defaultFixtureIdentityDecoded['packageFeatureSignature']['matchesExpected']);
        $t->same($expectedCurrentNativeAstSignatureSha256, $defaultFixtureIdentityDecoded['currentNativeAstSignature']['sha256']);
        $t->same(true, $defaultFixtureIdentityDecoded['currentNativeAstSignature']['matchesExpected']);
        $t->same($expectedCurrentNativeAstFixtures, $defaultFixtureIdentityDecoded['currentNativeAstSignature']['observedFixtures']);
        $t->same(true, EpubNativeAstPackageComparisonHarness::hasRunnerPlanEvidence($defaultFixtureIdentityDecoded));
        $t->same('exe:pandoc', $defaultFixtureIdentityDecoded['runnerEvidence']['target']['cabalTarget']);
        $t->same(['nav' => 20, 'ncx' => 3], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['navigationTypeCounts']);
        $t->same(['linear' => 34, 'non-linear' => 7], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['spineLinearStateCounts']);
        $t->same($expectedPackageFeatureCoverage['fixturesWithCreators'], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['fixturesWithCreators']);
        $t->same($expectedPackageFeatureCoverage['fixturesWithManifestFallbackItems'], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['fixturesWithManifestFallbackItems']);
        $t->same($expectedPackageFeatureCoverage['fixturesWithResolvedManifestFallbacks'], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['fixturesWithResolvedManifestFallbacks']);
        $t->same($expectedPackageFeatureCoverage['fixturesWithUsableManifestFallbacks'], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['fixturesWithUsableManifestFallbacks']);
        $t->same($expectedPackageFeatureCoverage['fixturesWithMissingManifestFallbacks'], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['fixturesWithMissingManifestFallbacks']);
        $t->same(28, $defaultFixtureIdentityDecoded['packageFeatureCoverage']['totals']['metadataCreators']);
        $t->same(['cover-image' => 2, 'mathml' => 2, 'nav' => 20, 'remote-resources' => 1, 'rendition:layout-pre-paginated' => 1, 'scripted' => 2, 'svg' => 2, 'switch' => 1], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['manifestPropertyCounts']);
        $t->same([
            'asset' => 2,
            'audio' => 1,
            'cover-image' => 2,
            'font' => 1,
            'image' => 12,
            'media-overlay' => 1,
            'navigation' => 25,
            'script' => 1,
            'style' => 15,
            'svg' => 1,
            'video' => 1,
            'xhtml' => 35,
        ], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['manifestResourceKindCounts']);
        $t->same(['bibliography' => 1, 'cover' => 2, 'glossary' => 1, 'index' => 1, 'notes' => 1, 'text' => 1, 'toc' => 1], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['guideReferenceTypeCounts']);
        $t->same([
            'cc:attributionURL' => 1,
            'cc:license' => 2,
            'record' => 1,
        ], $defaultFixtureIdentityDecoded['packageFeatureCoverage']['packageLinkRelCounts']);
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
