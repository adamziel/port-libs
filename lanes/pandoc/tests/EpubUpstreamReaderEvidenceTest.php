<?php

declare(strict_types=1);

use PortLibs\Pandoc\EpubUpstreamReaderEvidence;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-epub-reader-evidence-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary EPUB evidence directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary EPUB evidence directory {$base}");
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
    file_put_contents($path, $contents);
};

$repoRoot = static fn (): string => dirname(__DIR__, 3);
$checkedInFixtureRoot = static fn (): string => 'lanes/pandoc/fixtures/upstream-current-epub-reader';
$expectedCurrentReaderStaticSignatureSha256 = 'c3551818c84a8100f79266b00b653f14baa5d4ee4ae1d22db36eb8c19844e22c';
$expectedReferencedFixtureIdentity = [
    'epub/epub2_cover.epub' => [
        'sha256' => '4af73a135aa632cbf0c00b2889a5fc1d39a59a77fa294fdeff5ede72ff6ffed1',
        'bytes' => 11794,
    ],
    'epub/epub2_no_cover.epub' => [
        'sha256' => '8369dbe5cf315f1fe00f9dd1bf7c500cc663d7648edbf0d7b6a9b4d785fedf4e',
        'bytes' => 3584,
    ],
    'epub/epub2_picture.epub' => [
        'sha256' => '6049dde9e1d0ebcd175a8c5b937984f349af996e293310eafbce09e4c7384495',
        'bytes' => 11742,
    ],
    'epub/img.epub' => [
        'sha256' => 'f2c25e0e0612b7ac33a8d6a1c9719a86e7d2a0290472fc7d8b5068de781a822f',
        'bytes' => 20478,
    ],
    'epub/img_no_cover.epub' => [
        'sha256' => '3063f5e9b9610df1ddcc682ce49c293bcf681f1958700a5b6c3eda344383cf2a',
        'bytes' => 10602,
    ],
    'epub/wasteland.epub' => [
        'sha256' => '151ec5dbca33e39a4e3f6894e92fa5a101290bdeaaa792e0700595971456a278',
        'bytes' => 25840,
    ],
];

$writeEpubEvidenceTree = static function (string $root) use ($writeFile): void {
    $writeFile($root, 'test/Tests/Readers/EPUB.hs', <<<'HS'
module Tests.Readers.EPUB (tests) where

featuresBag :: [(String, String, Int)]
featuresBag = [("img/check.gif","image/gif",1340)
              ,("img/check.png","image/png",2815)
              ]

emptyBag :: [(String, String, Int)]
emptyBag = []

tests :: [TestTree]
tests =
  [ testGroup "EPUB Mediabag"
    [ testCase "features bag"
      (testMediaBag "epub/img.epub" featuresBag),
      testCase "empty bag"
      (testMediaBag "epub/empty.epub" emptyBag)
    ]
  ]
HS);
    $writeFile($root, 'test/epub/img.epub', 'epub bytes');
    $writeFile($root, 'test/epub/empty.epub', 'empty epub bytes');
    $writeFile($root, 'src/Text/Pandoc/Readers/EPUB.hs', "module Text.Pandoc.Readers.EPUB where\n");
};

$writeRunnerTranscripts = static function (string $root, array $paths, string $label = 'epub') use ($writeFile): array {
    $records = [];
    foreach (array_values($paths) as $index => $path) {
        $contents = $label . " runner transcript " . (string) ($index + 1) . "\n" . $path . "\n";
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

$writeFakePandoc = static function (string $path, string $version = 'pandoc fake 3.10'): void {
    file_put_contents($path, <<<'SH'
#!/bin/sh
if [ "$1" = "--version" ]; then
    echo "__VERSION__"
    exit 0
fi
last=""
for arg do
    last="$arg"
done
native="${last%.*}.native"
cat "$native"
SH);
    $script = (string) file_get_contents($path);
    file_put_contents($path, str_replace('__VERSION__', $version, $script));
    chmod($path, 0755);
};

return [
    'reports skipped epub reader evidence when upstream root is absent' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $report = (new EpubUpstreamReaderEvidence($root, 'missing'))->report();
            $text = EpubUpstreamReaderEvidence::formatTextReport($report);

            $t->same(1, $report['schemaVersion']);
            $t->same(EpubUpstreamReaderEvidence::TOOL_NAME, $report['tool']);
            $t->same(EpubUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
            $t->same(0, $report['denominator']['mediaBagTestCount']);
            $t->same(0, $report['denominator']['fixtureReferenceCount']);
            $t->same('not-evaluated-missing-upstream-root', $report['validation']['status']);
            $t->same('not-evaluated-source-directory-unavailable', $report['referencedFixtureIdentity']['validation']['status']);
            $t->same(false, EpubUpstreamReaderEvidence::hasRequiredReferencedFixtureIdentity($report));
            $t->same('not-evaluated-source-directory-unavailable', $report['currentReaderStaticSignature']['validation']['status']);
            $t->same(false, EpubUpstreamReaderEvidence::hasRequiredStaticCurrentSignature($report));
            $t->same('skipped', $report['nativeAstPackageParity']['status']);
            $t->same('not-evaluated-source-directory-unavailable', $report['nativeAstPackageParity']['astParityStatus']);
            $t->same(false, EpubUpstreamReaderEvidence::hasRequiredNativeAstPackageParity($report));
            $t->same('not-evaluated', $report['executableNativeAstParity']['status']);
            $t->same('not-requested', $report['executableNativeAstParity']['reason']);
            $t->same('not-evaluated-not-requested', $report['executableNativeAstParity']['astParityStatus']);
            $t->same(false, EpubUpstreamReaderEvidence::hasRequiredExecutableNativeAstParity($report));
            $t->same(false, EpubUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->same(true, EpubUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same(true, EpubUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
            $t->same('not-run', $report['runnerEvidence']['status']);
            $t->same(false, $report['runnerEvidence']['executed']);
            $t->same(null, $report['runnerEvidence']['command']);
            $t->same(null, $report['runnerEvidence']['resultArtifact']);
            $t->same('planned-not-run', $report['runnerEvidence']['commandPlanStatus']);
            $t->same(EpubUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $report['runnerEvidence']['upstreamBinding']['expectedCommit']);
            $t->same('$2 == "Readers" && $3 == "EPUB" && $4 == "EPUB Mediabag"', $report['runnerEvidence']['target']['tastyPattern']);
            $t->contains('Pandoc EPUB reader evidence', $text);
            $t->contains('Referenced fixture identity: not-evaluated-source-directory-unavailable', $text);
            $t->contains('Static current signature: not-evaluated-source-directory-unavailable', $text);
            $t->contains('Native/package parity: package=0/56 nativeAst=0/56 status=not-evaluated-source-directory-unavailable', $text);
            $t->contains('Executable/native parity: localPandoc=0/56 checkedNative=0/56 status=not-evaluated-not-requested version=not-evaluated', $text);
            $t->contains('Runner status: not-run', $text);
            $t->contains('Runner plan: planned-not-run', $text);
        } finally {
            $removeTree($root);
        }
    },

    'parses upstream epub reader mediabag denominator and expected tuples' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeEpubEvidenceTree): void {
        $root = $makeTempDir();
        try {
            $writeEpubEvidenceTree($root);
            $report = (new EpubUpstreamReaderEvidence($root, '.'))->report();

            $t->same(EpubUpstreamReaderEvidence::STATUS_COMPLETED, $report['status']);
            $t->same('valid-upstream-epub-reader-mediabag-denominator', $report['validation']['status']);
            $t->same([], $report['validation']['issues']);
            $t->same(2, $report['denominator']['mediaBagTestCount']);
            $t->same(2, $report['denominator']['fixtureReferenceCount']);
            $t->same(2, $report['denominator']['expectedMediaItemCount']);
            $t->same('features bag', $report['denominator']['readerCases'][0]['name']);
            $t->same('epub/img.epub', $report['denominator']['readerCases'][0]['epub']);
            $t->same('featuresBag', $report['denominator']['readerCases'][0]['bagName']);
            $t->same('img/check.gif', $report['denominator']['readerCases'][0]['expectedBag'][0]['path']);
            $t->same('image/gif', $report['denominator']['readerCases'][0]['expectedBag'][0]['mime']);
            $t->same(1340, $report['denominator']['readerCases'][0]['expectedBag'][0]['size']);
            $t->same([], $report['denominator']['missingReferencedFiles']);
            $t->same([], $report['denominator']['unreferencedEpubFixtures']);
            $t->same(2, $report['sourceInventory']['presentFileCount']);
            $t->same(0, $report['sourceInventory']['missingFileCount']);
            $t->same(true, EpubUpstreamReaderEvidence::hasRequiredMediaBagTestCount($report, 2));
            $t->same(true, EpubUpstreamReaderEvidence::hasRequiredFixtureReferenceCount($report, 2));
            $t->same(true, EpubUpstreamReaderEvidence::hasRequiredExpectedMediaItemCount($report, 2));
            $t->same('invalid-checked-in-current-epub-reader-referenced-fixture-identity', $report['referencedFixtureIdentity']['validation']['status']);
            $t->true(in_array('referenced-fixture-paths-do-not-match-expected-snapshot', $report['referencedFixtureIdentity']['validation']['issues'], true));
            $t->true(in_array('referenced-fixture-identity-content-does-not-match-expected-snapshot', $report['referencedFixtureIdentity']['validation']['issues'], true));
            $t->same(false, EpubUpstreamReaderEvidence::hasRequiredReferencedFixtureIdentity($report));
            $t->same('invalid-checked-in-current-epub-reader-static-signature', $report['currentReaderStaticSignature']['validation']['status']);
            $t->true(in_array('reader-static-denominator-counts-do-not-match-expected-snapshot', $report['currentReaderStaticSignature']['validation']['issues'], true));
            $t->true(in_array('reader-static-referenced-fixture-identity-does-not-match-expected-snapshot', $report['currentReaderStaticSignature']['validation']['issues'], true));
            $t->same(false, EpubUpstreamReaderEvidence::hasRequiredStaticCurrentSignature($report));
            $t->same(true, EpubUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->same(true, EpubUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same(true, EpubUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
            $t->same('upstream-haskell-runner', $report['runnerEvidence']['scope']);
            $t->same(['Readers', 'EPUB', 'EPUB Mediabag'], $report['runnerEvidence']['target']['tastyGroupPath']);
            $t->same('$2 == "Readers" && $3 == "EPUB" && $4 == "EPUB Mediabag"', $report['runnerEvidence']['futureCommands'][1]['arguments'][8]);
            $t->same('$2 == "Readers" && $3 == "EPUB" && $4 == "EPUB Mediabag"', $report['runnerEvidence']['futureCommands'][2]['arguments'][7]);
            $t->true(in_array('.port-libs/pandoc-runner/logs/epub-targeted-run.txt', $report['runnerEvidence']['requiredTranscripts'], true));
            $t->true(in_array('.port-libs/pandoc-runner/artifacts/epub-targeted-run/result.json', $report['runnerEvidence']['requiredArtifacts'], true));
            $mutatedReport = $report;
            $mutatedReport['runnerEvidence']['target']['tastyPattern'] = '$2 == "Readers" && $3 == "HTML"';
            $t->same(false, EpubUpstreamReaderEvidence::hasRunnerPlanEvidence($mutatedReport));
            $t->true(in_array('that this PHP evidence command executed upstream Haskell/Cabal/Tasty tests', $report['claimBoundaries']['doesNotAssert'], true));
            $t->true(in_array('that upstream Haskell runner evidence is explicitly not-run', $report['claimBoundaries']['doesAssert'], true));
            $t->true(in_array('the future upstream runner command plan targets test:test-pandoc Readers/EPUB/EPUB Mediabag at the pinned upstream commit without execution', $report['claimBoundaries']['doesAssert'], true));
        } finally {
            $removeTree($root);
        }
    },

    'validates checked-in current epub reader evidence through fixture base' => static function (TestRunner $t) use ($repoRoot, $checkedInFixtureRoot, $expectedCurrentReaderStaticSignatureSha256, $expectedReferencedFixtureIdentity): void {
        $report = (new EpubUpstreamReaderEvidence(
            $repoRoot(),
            'lanes/pandoc/fixtures/upstream-current-epub-reader',
            'lanes/pandoc/fixtures/upstream-current-epub-reader'
        ))->report();
        $text = EpubUpstreamReaderEvidence::formatTextReport($report);
        $nativePackageParity = $report['nativeAstPackageParity'];

        $t->same(EpubUpstreamReaderEvidence::STATUS_COMPLETED, $report['status']);
        $t->same(EpubUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $report['upstream']['commit']);
        $t->same('checked-in-current-fixture-snapshot', $report['upstream']['commitSource']);
        $t->same('checked-in-current-fixture-snapshot', $report['upstream']['provenanceMode']);
        $t->same('valid-upstream-epub-reader-mediabag-denominator', $report['validation']['status']);
        $t->same([], $report['validation']['issues']);
        $t->same($checkedInFixtureRoot(), $report['upstream']['resolvedFixtureBase']);
        $t->same('epub', $report['upstream']['resolvedFixtureDirectory']);
        $t->same(false, $report['upstream']['readerSourceRequired']);
        $t->same(6, $report['denominator']['mediaBagTestCount']);
        $t->same(6, $report['denominator']['fixtureReferenceCount']);
        $t->same(10, $report['denominator']['expectedMediaItemCount']);
        $t->same([], $report['denominator']['missingReferencedFiles']);
        $t->same([
            'epub/accessibility-metadata-package.epub',
            'epub/all-nonlinear-spine.epub',
            'epub/audio-navigation.epub',
            'epub/auxiliary-lot-guide-index.epub',
            'epub/bindings-collections-sidecars.epub',
            'epub/content-image-nav-media.epub',
            'epub/cross-spine-internal-links.epub',
            'epub/direct-image-spine.epub',
            'epub/duplicate-spine-idref.epub',
            'epub/epub3-ncx-toc-fallback.epub',
            'epub/external-footnote-reference.epub',
            'epub/features.epub',
            'epub/font-manifest-resource.epub',
            'epub/formatting.epub',
            'epub/fragment-nav-spine.epub',
            'epub/guide-bibliography-reference.epub',
            'epub/guide-glossary-reference.epub',
            'epub/guide-notes-reference.epub',
            'epub/guide-preface-reference.epub',
            'epub/language-french-metadata.epub',
            'epub/manifest-fallback-chain.epub',
            'epub/manifest-href-encoding.epub',
            'epub/media-manifest-mix.epub',
            'epub/media-overlay-package.epub',
            'epub/metadata-link-page-list-image.epub',
            'epub/metadata-record-remote-nav.epub',
            'epub/metadata-search-link-semantics.epub',
            'epub/missing-local-manifest-resource.epub',
            'epub/missing-media-overlay.epub',
            'epub/multi-rootfile-nested-nav.epub',
            'epub/nav-ncx-linear-guide.epub',
            'epub/nested-path-media-metadata.epub',
            'epub/nested-rootfile-nonlinear-spine.epub',
            'epub/package-spine-nav-media-metadata.epub',
            'epub/page-list-cfi-navigation.epub',
            'epub/page-list-navigation.epub',
            'epub/parent-relative-nav.epub',
            'epub/remote-manifest-resource.epub',
            'epub/rendition-layout-property.epub',
            'epub/scripted-svg-manifest.epub',
            'epub/scripted-xhtml-resource.epub',
            'epub/spine-fallback-resource.epub',
            'epub/spine-page-spread.epub',
            'epub/standalone-footnote.epub',
            'epub/text-track-captions.epub',
            'epub/title-page-guide-media-metadata.epub',
            'epub/video-manifest-resource.epub',
            'epub/video-navigation.epub',
            'epub/xhtml-ruby-table-mark.epub',
            'epub/xhtml-semantics-spine.epub',
        ], $report['denominator']['unreferencedEpubFixtures']);
        $t->same(false, $report['sourceInventory']['readerSourceRequired']);
        $t->same(1, $report['sourceInventory']['presentFileCount']);
        $t->same(1, $report['sourceInventory']['missingFileCount']);
        $t->same('checked-in-current-epub-reader-referenced-fixture-identity', $report['referencedFixtureIdentity']['kind']);
        $t->same('checked-in-current-upstream-epub-reader-6-referenced-epub-fixture-snapshot', $report['referencedFixtureIdentity']['scope']);
        $t->same('sha256', $report['referencedFixtureIdentity']['hashAlgorithm']);
        $t->same(6, $report['referencedFixtureIdentity']['expectedFileCount']);
        $t->same(6, $report['referencedFixtureIdentity']['observedFileCount']);
        $t->same(6, $report['referencedFixtureIdentity']['presentFileCount']);
        $t->same(0, $report['referencedFixtureIdentity']['missingFileCount']);
        $t->same(array_keys($expectedReferencedFixtureIdentity), $report['referencedFixtureIdentity']['expectedPaths']);
        $t->same(array_keys($expectedReferencedFixtureIdentity), $report['referencedFixtureIdentity']['observedPaths']);
        $t->same([], $report['referencedFixtureIdentity']['missingExpectedPaths']);
        $t->same([], $report['referencedFixtureIdentity']['unexpectedObservedPaths']);
        $t->same(84040, $report['referencedFixtureIdentity']['totalBytes']);
        $t->same(84040, $report['referencedFixtureIdentity']['expectedTotalBytes']);
        $observedReferencedFixtureIdentity = [];
        foreach ($report['referencedFixtureIdentity']['files'] as $file) {
            $observedReferencedFixtureIdentity[$file['path']] = [
                'sha256' => $file['sha256'],
                'bytes' => $file['bytes'],
            ];
            $t->same(true, $file['present']);
            $t->same(true, $file['matchesExpected']);
        }
        $t->same($expectedReferencedFixtureIdentity, $observedReferencedFixtureIdentity);
        $t->same(true, $report['referencedFixtureIdentity']['matchesExpected']);
        $t->same('valid-checked-in-current-epub-reader-referenced-fixture-identity', $report['referencedFixtureIdentity']['validation']['status']);
        $t->same([], $report['referencedFixtureIdentity']['validation']['issues']);
        $t->same(true, EpubUpstreamReaderEvidence::hasRequiredReferencedFixtureIdentity($report));
        $t->same('checked-in-current-epub-reader-static-signature', $report['currentReaderStaticSignature']['kind']);
        $t->same('sha256-canonical-json-v1', $report['currentReaderStaticSignature']['algorithm']);
        $t->same('checked-in-current-upstream-epub-reader-static-6-case-media-expectation-and-fixture-identity-snapshot', $report['currentReaderStaticSignature']['scope']);
        $t->same($expectedCurrentReaderStaticSignatureSha256, $report['currentReaderStaticSignature']['sha256']);
        $t->same($expectedCurrentReaderStaticSignatureSha256, $report['currentReaderStaticSignature']['expectedSha256']);
        $t->same(true, $report['currentReaderStaticSignature']['hashMatchesExpected']);
        $t->same(true, $report['currentReaderStaticSignature']['matchesExpected']);
        $t->same('valid-checked-in-current-epub-reader-static-signature', $report['currentReaderStaticSignature']['validation']['status']);
        $t->same([], $report['currentReaderStaticSignature']['validation']['issues']);
        $t->same(true, $report['currentReaderStaticSignature']['validation']['referencedFixtureIdentityMatchesExpected']);
        $t->same(true, EpubUpstreamReaderEvidence::hasRequiredStaticCurrentSignature($report));
        $t->same('checked-in-current-epub-native-ast-package-parity', $nativePackageParity['kind']);
        $t->same('completed', $nativePackageParity['status']);
        $t->same(false, $nativePackageParity['skipped']);
        $t->same(56, $nativePackageParity['requiredEpubCount']);
        $t->same(56, $nativePackageParity['requiredPairCount']);
        $t->same(56, $nativePackageParity['totalEpubCount']);
        $t->same(56, $nativePackageParity['comparedEpubCount']);
        $t->same(56, $nativePackageParity['packageParsedCount']);
        $t->same(56, $nativePackageParity['readerParsedCount']);
        $t->same(0, $nativePackageParity['packageParseFailureCount']);
        $t->same(0, $nativePackageParity['readerParseFailureCount']);
        $t->same('package-and-reader-acceptance-observed-not-full-epub-parity', $nativePackageParity['packageAcceptanceStatus']);
        $t->same(56, $nativePackageParity['totalPairCount']);
        $t->same(56, $nativePackageParity['comparedPairCount']);
        $t->same(56, $nativePackageParity['bothParsedCount']);
        $t->same(0, $nativePackageParity['astParseFailureCount']);
        $t->same(0, $nativePackageParity['nativeParseFailureCount']);
        $t->same(56, $nativePackageParity['normalizedAstMatchCount']);
        $t->same(0, $nativePackageParity['normalizedAstMismatchCount']);
        $t->same('normalized-ast-equality-observed-not-runner-parity', $nativePackageParity['astParityStatus']);
        $t->same('valid-checked-in-current-epub-fixture-identity', $nativePackageParity['fixtureIdentityStatus']);
        $t->same('valid-checked-in-current-epub-package-feature-signature', $nativePackageParity['packageFeatureSignatureStatus']);
        $t->same('valid-checked-in-current-epub-normalized-native-ast-signature', $nativePackageParity['currentNativeAstSignatureStatus']);
        $t->same(true, $nativePackageParity['hasRequiredPackageParity']);
        $t->same(true, $nativePackageParity['hasRequiredNativeReadiness']);
        $t->same(true, $nativePackageParity['hasRequiredMappedParity']);
        $t->same(true, $nativePackageParity['hasRequiredFixtureIdentity']);
        $t->same(true, $nativePackageParity['hasRequiredCurrentPackageFeatureCoverage']);
        $t->same(true, $nativePackageParity['hasRequiredCurrentPackageFeatureSignature']);
        $t->same(true, $nativePackageParity['hasRequiredCurrentNativeAstSignature']);
        $t->same(true, $nativePackageParity['hasRunnerPlanEvidence']);
        $t->same(true, EpubUpstreamReaderEvidence::hasRequiredNativeAstPackageParity($report));
        $t->contains('Native/package parity: package=56/56 nativeAst=56/56 status=normalized-ast-equality-observed-not-runner-parity', $text);
        $t->same('not-evaluated', $report['executableNativeAstParity']['status']);
        $t->same('not-requested', $report['executableNativeAstParity']['reason']);
        $t->same(false, EpubUpstreamReaderEvidence::hasRequiredExecutableNativeAstParity($report));
        $t->same(true, EpubUpstreamReaderEvidence::hasNoValidationIssues($report));
        $t->same(true, EpubUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
        $t->same(true, EpubUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
    },

    'validates checked-in current epub executable native ast parity with supplied pandoc' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $repoRoot, $checkedInFixtureRoot, $writeFakePandoc): void {
        $root = $makeTempDir();
        try {
            $fakePandoc = $root . '/pandoc';
            $writeFakePandoc($fakePandoc);
            $report = (new EpubUpstreamReaderEvidence(
                $repoRoot(),
                $checkedInFixtureRoot(),
                $checkedInFixtureRoot(),
                null,
                $fakePandoc
            ))->report();
            $text = EpubUpstreamReaderEvidence::formatTextReport($report);
            $executableParity = $report['executableNativeAstParity'];

            $t->same('checked-in-current-epub-pandoc-executable-native-ast-parity', $executableParity['kind']);
            $t->same('completed', $executableParity['status']);
            $t->same(false, $executableParity['skipped']);
            $t->same(56, $executableParity['requiredEpubCount']);
            $t->same(56, $executableParity['totalEpubCount']);
            $t->same(56, $executableParity['comparedEpubCount']);
            $t->same(56, $executableParity['localParsedCount']);
            $t->same(56, $executableParity['pandocParsedCount']);
            $t->same(56, $executableParity['nativeFixtureParsedCount']);
            $t->same(56, $executableParity['bothParsedCount']);
            $t->same(0, $executableParity['parseFailureCount']);
            $t->same(56, $executableParity['normalizedAstMatchCount']);
            $t->same(0, $executableParity['normalizedAstMismatchCount']);
            $t->same(56, $executableParity['pandocNativeFixtureComparedCount']);
            $t->same(56, $executableParity['pandocNativeFixtureMatchCount']);
            $t->same(0, $executableParity['pandocNativeFixtureMismatchCount']);
            $t->same(56, $executableParity['pandocNativeFixtureByteComparedCount']);
            $t->same(56, $executableParity['pandocNativeFixtureByteMatchCount']);
            $t->same(0, $executableParity['pandocNativeFixtureByteMismatchCount']);
            $t->same('normalized-ast-equality-observed-against-pandoc-executable', $executableParity['astParityStatus']);
            $t->same('pandoc fake 3.10', $executableParity['pandocVersion']);
            $t->same(true, $executableParity['hasRequiredExecutableParity']);
            $t->same([], $executableParity['byteMismatchExamples']);
            $t->same(true, EpubUpstreamReaderEvidence::hasRequiredExecutableNativeAstParity($report));
            $t->same(true, EpubUpstreamReaderEvidence::hasRequiredExecutableNativeAstParity($report, 'pandoc fake 3.10'));
            $t->same(false, EpubUpstreamReaderEvidence::hasRequiredExecutableNativeAstParity($report, 'pandoc fake 3.9'));
            $t->contains('Executable/native parity: localPandoc=56/56 checkedNative=56/56 status=normalized-ast-equality-observed-against-pandoc-executable version=pandoc fake 3.10', $text);
            $t->true(in_array('the checked-in current EPUB local pandoc executable/native AST parity snapshot when explicitly requested and gated', $report['claimBoundaries']['doesAssert'], true));
            $t->true(in_array('that local pandoc executable evidence was evaluated unless explicitly requested or a pandoc binary was supplied', $report['claimBoundaries']['doesNotAssert'], true));
        } finally {
            $removeTree($root);
        }
    },

    'validates supplied epub reader upstream runner result artifact' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writeRunnerTranscripts, $repoRoot): void {
        $root = $makeTempDir();
        try {
            $fixtureRoot = $repoRoot() . '/lanes/pandoc/fixtures/upstream-current-epub-reader';
            $baseReport = (new EpubUpstreamReaderEvidence(
                $root,
                $fixtureRoot,
                $fixtureRoot
            ))->report();
            $runnerPlan = $baseReport['runnerEvidence'];
            $transcripts = $writeRunnerTranscripts($root, $runnerPlan['requiredTranscripts']);
            $testNames = array_map(
                static fn (array $case): string => $case['name'],
                $baseReport['denominator']['readerCases']
            );
            $payload = [
                'schemaVersion' => 2,
                'runner' => 'Cabal/Tasty Pandoc EPUB reader suite',
                'runnerExecuted' => true,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'commit' => EpubUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT,
                ],
                'target' => $runnerPlan['target'],
                'command' => $runnerPlan['futureCommands'][2],
                'exitCode' => 0,
                'testCount' => 6,
                'passedCount' => 6,
                'failedCount' => 0,
                'skippedCount' => 0,
                'testNames' => $testNames,
                'transcriptPaths' => $runnerPlan['requiredTranscripts'],
                'transcripts' => $transcripts,
            ];
            $validPayload = $payload;
            $writeFile($root, 'result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $artifactPath = $root . '/result.json';
            $report = (new EpubUpstreamReaderEvidence(
                $root,
                $fixtureRoot,
                $fixtureRoot,
                $artifactPath
            ))->report();
            $text = EpubUpstreamReaderEvidence::formatTextReport($report);

            $t->same('completed', $report['runnerEvidence']['status']);
            $t->same(true, $report['runnerEvidence']['executed']);
            $t->same('runner-result-artifact-validated', $report['runnerEvidence']['commandPlanStatus']);
            $t->same('valid-upstream-epub-reader-runner-result-artifact', $report['runnerEvidence']['validation']['status']);
            $t->same([], $report['runnerEvidence']['validation']['issues']);
            $t->same('upstream-epub-reader-runner-result-artifact', $report['runnerEvidence']['resultArtifact']['kind']);
            $t->same(true, $report['runnerEvidence']['resultArtifact']['present']);
            $t->same(hash_file('sha256', $artifactPath), $report['runnerEvidence']['resultArtifact']['sha256']);
            $t->same(filesize($artifactPath), $report['runnerEvidence']['resultArtifact']['bytes']);
            $t->same(EpubUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $report['runnerEvidence']['upstreamBinding']['observedCommit']);
            $t->same($runnerPlan['target'], $report['runnerEvidence']['target']);
            $t->same($runnerPlan['futureCommands'][2], $report['runnerEvidence']['command']);
            $t->same($testNames, $report['runnerEvidence']['observed']['testNames']);
            $t->same($runnerPlan['requiredTranscripts'], $report['runnerEvidence']['observed']['transcriptPaths']);
            $t->same($transcripts, $report['runnerEvidence']['observed']['transcripts']);
            $t->same($transcripts, $report['runnerEvidence']['expected']['transcripts']);
            $t->same('upstream-epub-reader-runner-transcript', $report['runnerEvidence']['transcripts'][0]['kind']);
            $t->same(true, $report['runnerEvidence']['transcripts'][0]['present']);
            $t->same(true, EpubUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($report));
            $t->same(false, EpubUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same(false, EpubUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
            $t->same(true, EpubUpstreamReaderEvidence::hasRequiredReferencedFixtureIdentity($report));
            $t->same(true, EpubUpstreamReaderEvidence::hasRequiredStaticCurrentSignature($report));
            $t->contains('Runner status: completed', $text);
            $t->contains('Runner plan: runner-result-artifact-validated', $text);
            $t->contains('Runner result artifact: valid-upstream-epub-reader-runner-result-artifact', $text);
            $t->contains('Supplied upstream Haskell/Cabal runner result artifact is validated', $text);

            $payload = $validPayload;
            $payload['failedCount'] = 1;
            $payload['exitCode'] = 1;
            $writeFile($root, 'bad-result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $badReport = (new EpubUpstreamReaderEvidence(
                $root,
                $fixtureRoot,
                $fixtureRoot,
                $root . '/bad-result.json'
            ))->report();

            $t->same('invalid', $badReport['runnerEvidence']['status']);
            $t->same('invalid-upstream-epub-reader-runner-result-artifact', $badReport['runnerEvidence']['validation']['status']);
            $t->true(in_array('runner-result-exit-code-nonzero', $badReport['runnerEvidence']['validation']['issues'], true));
            $t->true(in_array('runner-result-counts-mismatch', $badReport['runnerEvidence']['validation']['issues'], true));
            $t->same(false, EpubUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($badReport));

            $badTranscriptPayload = $validPayload;
            $badTranscriptPayload['transcripts'][0]['bytes'] = 0;
            $writeFile($root, 'bad-transcript-result.json', json_encode($badTranscriptPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $badTranscriptReport = (new EpubUpstreamReaderEvidence(
                $root,
                $fixtureRoot,
                $fixtureRoot,
                $root . '/bad-transcript-result.json'
            ))->report();

            $t->same('invalid', $badTranscriptReport['runnerEvidence']['status']);
            $t->true(in_array('runner-result-transcript-bytes-mismatch', $badTranscriptReport['runnerEvidence']['validation']['issues'], true));
            $t->same(false, EpubUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($badTranscriptReport));
        } finally {
            $removeTree($root);
        }
    },

    'reports invalid epub reader evidence for missing fixture and bag definition' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile): void {
        $root = $makeTempDir();
        try {
            $writeFile($root, 'test/Tests/Readers/EPUB.hs', <<<'HS'
tests = [ testCase "missing bag" (testMediaBag "epub/missing.epub" missingBag) ]
HS);
            $writeFile($root, 'src/Text/Pandoc/Readers/EPUB.hs', "module Stub where\n");

            $report = (new EpubUpstreamReaderEvidence($root, '.'))->report();

            $t->same('invalid-upstream-epub-reader-mediabag-denominator', $report['validation']['status']);
            $t->true(in_array('missing-expected-mediabag-definition', $report['validation']['issues'], true));
            $t->true(in_array('missing-referenced-fixture-files', $report['validation']['issues'], true));
            $t->same(1, count($report['denominator']['missingReferencedFiles']));
            $t->same('test/epub/missing.epub', $report['denominator']['missingReferencedFiles'][0]['path']);
            $t->same(false, EpubUpstreamReaderEvidence::hasNoValidationIssues($report));
        } finally {
            $removeTree($root);
        }
    },

    'cli gates checked-in current epub reader evidence through checked-in fixtures mode' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $repoRoot, $checkedInFixtureRoot, $expectedCurrentReaderStaticSignatureSha256, $expectedReferencedFixtureIdentity, $writeFakePandoc): void {
        $root = $makeTempDir();
        try {
            $fakePandoc = $root . '/pandoc';
            $writeFakePandoc($fakePandoc);

            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg($repoRoot())
                . ' --checked-in-fixtures'
                . ' --pandoc-bin=' . escapeshellarg($fakePandoc)
                . ' --json'
                . ' --require-test-count=6'
                . ' --require-fixture-reference-count=6'
                . ' --require-expected-media-item-count=10'
                . ' --require-referenced-fixture-identity'
                . ' --require-static-current-signature'
                . ' --require-native-ast-package-parity'
                . ' --require-executable-native-ast-parity'
                . ' --require-pandoc-version=' . escapeshellarg('pandoc fake 3.10')
                . ' --require-runner-not-run'
                . ' --require-runner-plan'
                . ' --require-no-validation-issues';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same(6, $decoded['denominator']['mediaBagTestCount']);
            $t->same(6, $decoded['denominator']['fixtureReferenceCount']);
            $t->same(10, $decoded['denominator']['expectedMediaItemCount']);
            $t->same([], $decoded['denominator']['missingReferencedFiles']);
            $t->same('valid-upstream-epub-reader-mediabag-denominator', $decoded['validation']['status']);
            $t->same($checkedInFixtureRoot(), $decoded['upstream']['root']);
            $t->same(EpubUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $decoded['upstream']['commit']);
            $t->same('checked-in-current-fixture-snapshot', $decoded['upstream']['commitSource']);
            $t->same('checked-in-current-fixture-snapshot', $decoded['upstream']['provenanceMode']);
            $t->same($checkedInFixtureRoot(), $decoded['upstream']['resolvedFixtureBase']);
            $t->same('epub', $decoded['upstream']['resolvedFixtureDirectory']);
            $t->same(false, $decoded['upstream']['readerSourceRequired']);
            $t->same('valid-checked-in-current-epub-reader-referenced-fixture-identity', $decoded['referencedFixtureIdentity']['validation']['status']);
            $t->same(true, $decoded['referencedFixtureIdentity']['matchesExpected']);
            $t->same(84040, $decoded['referencedFixtureIdentity']['totalBytes']);
            $decodedReferencedFixtureIdentity = [];
            foreach ($decoded['referencedFixtureIdentity']['files'] as $file) {
                $decodedReferencedFixtureIdentity[$file['path']] = [
                    'sha256' => $file['sha256'],
                    'bytes' => $file['bytes'],
                ];
            }
            $t->same($expectedReferencedFixtureIdentity, $decodedReferencedFixtureIdentity);
            $t->same(true, EpubUpstreamReaderEvidence::hasRequiredReferencedFixtureIdentity($decoded));
            $t->same($expectedCurrentReaderStaticSignatureSha256, $decoded['currentReaderStaticSignature']['sha256']);
            $t->same(true, $decoded['currentReaderStaticSignature']['hashMatchesExpected']);
            $t->same(true, $decoded['currentReaderStaticSignature']['matchesExpected']);
            $t->same('valid-checked-in-current-epub-reader-static-signature', $decoded['currentReaderStaticSignature']['validation']['status']);
            $t->same(true, EpubUpstreamReaderEvidence::hasRequiredStaticCurrentSignature($decoded));
            $t->same('normalized-ast-equality-observed-not-runner-parity', $decoded['nativeAstPackageParity']['astParityStatus']);
            $t->same(56, $decoded['nativeAstPackageParity']['normalizedAstMatchCount']);
            $t->same(true, EpubUpstreamReaderEvidence::hasRequiredNativeAstPackageParity($decoded));
            $t->same('normalized-ast-equality-observed-against-pandoc-executable', $decoded['executableNativeAstParity']['astParityStatus']);
            $t->same(56, $decoded['executableNativeAstParity']['normalizedAstMatchCount']);
            $t->same(56, $decoded['executableNativeAstParity']['pandocNativeFixtureMatchCount']);
            $t->same(0, $decoded['executableNativeAstParity']['pandocNativeFixtureMismatchCount']);
            $t->same('pandoc fake 3.10', $decoded['executableNativeAstParity']['pandocVersion']);
            $t->same(true, EpubUpstreamReaderEvidence::hasRequiredExecutableNativeAstParity($decoded, 'pandoc fake 3.10'));
            $t->same(true, EpubUpstreamReaderEvidence::hasRunnerNotRunEvidence($decoded));
            $t->same(true, EpubUpstreamReaderEvidence::hasRunnerPlanEvidence($decoded));
            $t->same('not-run', $decoded['runnerEvidence']['status']);
            $t->same('$2 == "Readers" && $3 == "EPUB" && $4 == "EPUB Mediabag"', $decoded['runnerEvidence']['target']['tastyPattern']);
            $t->true(in_array('.port-libs/pandoc-runner/logs/epub-targeted-list-tests.txt', $decoded['runnerEvidence']['requiredTranscripts'], true));
            $t->true(in_array('full EPUB feature parity beyond the upstream reader media-bag tests', $decoded['claimBoundaries']['doesNotAssert'], true));
        } finally {
            $removeTree($root);
        }
    },

    'cli gates supplied epub reader upstream runner result artifact' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writeRunnerTranscripts, $repoRoot): void {
        $root = $makeTempDir();
        try {
            $fixtureRoot = $repoRoot() . '/lanes/pandoc/fixtures/upstream-current-epub-reader';
            $baseReport = (new EpubUpstreamReaderEvidence(
                $root,
                $fixtureRoot,
                $fixtureRoot
            ))->report();
            $runnerPlan = $baseReport['runnerEvidence'];
            $transcripts = $writeRunnerTranscripts($root, $runnerPlan['requiredTranscripts']);
            $testNames = array_map(
                static fn (array $case): string => $case['name'],
                $baseReport['denominator']['readerCases']
            );
            $payload = [
                'schemaVersion' => 2,
                'runner' => 'Cabal/Tasty Pandoc EPUB reader suite',
                'runnerExecuted' => true,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'commit' => EpubUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT,
                ],
                'target' => $runnerPlan['target'],
                'command' => $runnerPlan['futureCommands'][2],
                'exitCode' => 0,
                'testCount' => 6,
                'passedCount' => 6,
                'failedCount' => 0,
                'skippedCount' => 0,
                'testNames' => $testNames,
                'transcriptPaths' => $runnerPlan['requiredTranscripts'],
                'transcripts' => $transcripts,
            ];
            $validPayload = $payload;
            $writeFile($root, 'result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg($root)
                . ' --upstream-root=' . escapeshellarg($fixtureRoot)
                . ' --fixture-base=' . escapeshellarg($fixtureRoot)
                . ' --runner-result-artifact=' . escapeshellarg($root . '/result.json')
                . ' --json'
                . ' --require-test-count=6'
                . ' --require-referenced-fixture-identity'
                . ' --require-static-current-signature'
                . ' --require-runner-result-artifact'
                . ' --require-no-validation-issues';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same('completed', $decoded['runnerEvidence']['status']);
            $t->same('valid-upstream-epub-reader-runner-result-artifact', $decoded['runnerEvidence']['validation']['status']);
            $t->same(true, EpubUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($decoded));
            $t->same(true, EpubUpstreamReaderEvidence::hasRequiredStaticCurrentSignature($decoded));

            $payload = $validPayload;
            $payload['target']['tastyPattern'] = '$2 == "Readers" && $3 == "HTML"';
            $writeFile($root, 'bad-result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $failingCommand = str_replace('result.json', 'bad-result.json', $command) . ' 2>/dev/null';
            $failingOutput = [];
            $failingExitCode = 0;
            exec($failingCommand, $failingOutput, $failingExitCode);

            $t->same(1, $failingExitCode);
        } finally {
            $removeTree($root);
        }
    },

    'cli gates epub reader evidence counts and validation issues' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeEpubEvidenceTree): void {
        $root = $makeTempDir();
        try {
            $writeEpubEvidenceTree($root);
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg(dirname($root))
                . ' --upstream-root=' . escapeshellarg($root)
                . ' --json'
                . ' --require-test-count=2'
                . ' --require-fixture-reference-count=2'
                . ' --require-expected-media-item-count=2'
                . ' --require-runner-plan'
                . ' --require-no-validation-issues';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same(2, $decoded['denominator']['mediaBagTestCount']);
            $t->same(2, $decoded['denominator']['fixtureReferenceCount']);
            $t->same(2, $decoded['denominator']['expectedMediaItemCount']);
            $t->same('valid-upstream-epub-reader-mediabag-denominator', $decoded['validation']['status']);
            $t->same(true, EpubUpstreamReaderEvidence::hasRunnerPlanEvidence($decoded));

            $failingCommand = str_replace('--require-test-count=2', '--require-test-count=3', $command) . ' 2>/dev/null';
            $failingOutput = [];
            $failingExitCode = 0;
            exec($failingCommand, $failingOutput, $failingExitCode);

            $t->same(1, $failingExitCode);

            $signatureFailingCommand = $command . ' --require-static-current-signature 2>/dev/null';
            $signatureFailingOutput = [];
            $signatureFailingExitCode = 0;
            exec($signatureFailingCommand, $signatureFailingOutput, $signatureFailingExitCode);

            $t->same(1, $signatureFailingExitCode);

            $identityFailingCommand = $command . ' --require-referenced-fixture-identity 2>/dev/null';
            $identityFailingOutput = [];
            $identityFailingExitCode = 0;
            exec($identityFailingCommand, $identityFailingOutput, $identityFailingExitCode);

            $t->same(1, $identityFailingExitCode);
        } finally {
            $removeTree($root);
        }
    },
];
