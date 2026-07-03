<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubNativeAstPackageComparisonHarness
{
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';

    private const DEFAULT_MAX_EXAMPLES = 12;
    private const VERDICT = 'epub-native-ast-package-comparison-not-full-epub-parity';
    private const CLAIM = 'Compares local PHP EPUB package parsing and reader output with a supplied checked-in current EPUB fixture directory and same-basename .native goldens. Package parsing/reader acceptance, fixture identity, package feature coverage, and native AST equality are reported separately; no upstream Haskell runner, writer parity, or full EPUB feature parity is asserted.';
    private const PACKAGE_FEATURE_SIGNATURE_KIND = 'checked-in-current-epub-package-feature-signature';
    private const PACKAGE_FEATURE_SIGNATURE_ALGORITHM = 'sha256-canonical-json-v1';
    private const PACKAGE_FEATURE_SIGNATURE_SCOPE = 'checked-in-current-upstream-epub-reader-18-fixture-snapshot';
    private const CHECKED_IN_CURRENT_PACKAGE_FEATURE_SIGNATURE_SHA256 = 'a1aa4179909c270b18290f64a9f80a6e8d9e6cae756e7a66e278b7682a92be95';
    private const CURRENT_NATIVE_AST_SIGNATURE_KIND = 'checked-in-current-epub-normalized-native-ast-signature';
    private const CURRENT_NATIVE_AST_SIGNATURE_ALGORITHM = 'sha256-canonical-json-v1';
    private const CURRENT_NATIVE_AST_SIGNATURE_SCOPE = 'checked-in-current-upstream-epub-reader-18-fixture-normalized-ast-snapshot';
    private const CHECKED_IN_CURRENT_NATIVE_AST_SIGNATURE_SHA256 = 'ac08cdb9b41941281c9fa39b18fe2869ae9f73144c93b55bc81614e290f78cf2';
    private const RUNNER_CABAL_TARGET = 'exe:pandoc';
    private const RUNNER_BUILD_DIR = '.port-libs/pandoc-runner/cabal-build/epub-native-package-run';
    private const RUNNER_FIXTURE_DIRECTORY = 'test/epub';
    private const RUNNER_OUTPUT_FORMAT = 'native';
    private const RUNNER_REQUIRED_TRANSCRIPTS = [
        '.port-libs/pandoc-runner/logs/epub-native-package-runner-dependencies.txt',
        '.port-libs/pandoc-runner/logs/epub-native-package-fixture-inventory.txt',
        '.port-libs/pandoc-runner/logs/epub-native-package-native-generation.txt',
    ];
    private const RUNNER_REQUIRED_ARTIFACTS = [
        '.port-libs/pandoc-runner/artifacts/epub-native-package/result.json',
        '.port-libs/pandoc-runner/artifacts/epub-native-package/generated-native-manifest.json',
    ];

    /** @var array<string, true> */
    private const IGNORED_ATTRS = [
        'epubRootfile' => true,
        'epubManifestItemCount' => true,
        'epubManifestItems' => true,
        'epubSpineItems' => true,
        'epubSpineItemRefs' => true,
        'epubReadableResources' => true,
        'epubReferencedResources' => true,
        'epubImageResources' => true,
        'epubMediaBagResources' => true,
        'epubTocResources' => true,
        'epubTocEntryCount' => true,
        'epubTocEntries' => true,
        'epubLandmarkEntryCount' => true,
        'epubLandmarkEntries' => true,
        'sourceFormat' => true,
    ];

    /**
     * @var array<string, array{sha256: string, bytes: int}>
     */
    private const CHECKED_IN_CURRENT_FIXTURE_IDENTITIES = [
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
        'guide-glossary-reference.epub' => [
            'sha256' => '699550c8c91e9f11cb430c24e2e157a1f6dfb4f11cff2b98f5ad3cce72b6141d',
            'bytes' => 1386,
        ],
        'guide-glossary-reference.native' => [
            'sha256' => 'bd285d34bd9a24f860fb1f398ad291957f68189468858f15192d9823b6f06279',
            'bytes' => 181,
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
        'scripted-svg-manifest.epub' => [
            'sha256' => '8845d9a35825bdf882b5d2239b60c1e7fd0f9589c8d06f5be74f0565fc56bb1b',
            'bytes' => 1577,
        ],
        'scripted-svg-manifest.native' => [
            'sha256' => 'c4c89cc198ed6aab17f1f6c417e9b4bb919ba704af09eb508f5805d2077c193e',
            'bytes' => 180,
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

    /**
     * @var array<string, mixed>
     */
    private const CHECKED_IN_CURRENT_PACKAGE_FEATURE_COVERAGE = [
        'fixtureCount' => 18,
        'metadataLanguageCounts' => [
            'de-DE' => 3,
            'en' => 14,
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
            'nav' => 14,
            'ncx' => 3,
        ],
        'manifestMediaTypeCounts' => [
            'application/json' => 1,
            'application/smil+xml' => 1,
            'application/x-dtbncx+xml' => 5,
            'application/x-fallback-demo' => 1,
            'application/xhtml+xml' => 43,
            'audio/mpeg' => 1,
            'font/woff2' => 1,
            'image/gif' => 4,
            'image/jpeg' => 6,
            'image/png' => 4,
            'image/svg+xml' => 1,
            'text/css' => 15,
        ],
        'manifestPropertyCounts' => [
            'cover-image' => 2,
            'mathml' => 2,
            'nav' => 14,
            'remote-resources' => 1,
            'scripted' => 1,
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
            'navigation' => 19,
            'style' => 15,
            'svg' => 1,
            'xhtml' => 29,
        ],
        'navigationSectionTypes' => [
            'landmarks',
            'loi',
            'page-list',
            'toc',
        ],
        'guideReferenceTypeCounts' => [
            'cover' => 2,
            'glossary' => 1,
            'text' => 1,
            'toc' => 1,
        ],
        'fixturesWithGuideReferences' => [
            'epub2_cover',
            'epub2_no_cover',
            'epub2_picture',
            'guide-glossary-reference',
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
        'fixtureFeatureSignatures' => [
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
            'epub2_cover' => [
                'navigationType' => 'ncx',
                'navigationSectionTypes' => ['toc'],
                'manifestResourceKindCounts' => [
                    'image' => 1,
                    'navigation' => 1,
                    'style' => 1,
                    'xhtml' => 2,
                ],
                'guideReferenceTypeCounts' => ['cover' => 1],
                'packageLinkRelCounts' => [],
                'coverImagePartPresent' => true,
            ],
            'epub2_no_cover' => [
                'navigationType' => 'ncx',
                'navigationSectionTypes' => ['toc'],
                'manifestResourceKindCounts' => [
                    'navigation' => 1,
                    'style' => 1,
                    'xhtml' => 1,
                ],
                'guideReferenceTypeCounts' => ['toc' => 1],
                'packageLinkRelCounts' => [],
                'coverImagePartPresent' => false,
            ],
            'epub2_picture' => [
                'navigationType' => 'ncx',
                'navigationSectionTypes' => ['toc'],
                'manifestResourceKindCounts' => [
                    'image' => 1,
                    'navigation' => 1,
                    'style' => 1,
                    'xhtml' => 2,
                ],
                'guideReferenceTypeCounts' => ['cover' => 1],
                'packageLinkRelCounts' => [],
                'coverImagePartPresent' => true,
            ],
            'features' => [
                'navigationType' => 'nav',
                'navigationSectionTypes' => ['landmarks', 'toc'],
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
                'navigationSectionTypes' => ['toc'],
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
                'navigationSectionTypes' => ['landmarks', 'toc'],
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
            'guide-glossary-reference' => [
                'navigationType' => 'nav',
                'navigationSectionTypes' => ['toc'],
                'manifestResourceKindCounts' => [
                    'navigation' => 1,
                    'xhtml' => 1,
                ],
                'guideReferenceTypeCounts' => ['glossary' => 1],
                'packageLinkRelCounts' => [],
                'coverImagePartPresent' => false,
            ],
            'img' => [
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
            ],
            'img_no_cover' => [
                'navigationType' => 'nav',
                'navigationSectionTypes' => ['landmarks', 'toc'],
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
            'manifest-fallback-chain' => [
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
            ],
            'media-overlay-package' => [
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
            ],
            'missing-local-manifest-resource' => [
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
            ],
            'nav-ncx-linear-guide' => [
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
            ],
            'page-list-navigation' => [
                'navigationType' => 'nav',
                'navigationSectionTypes' => ['loi', 'page-list', 'toc'],
                'manifestResourceKindCounts' => [
                    'navigation' => 1,
                    'xhtml' => 1,
                ],
                'guideReferenceTypeCounts' => [],
                'packageLinkRelCounts' => [],
                'coverImagePartPresent' => false,
            ],
            'remote-manifest-resource' => [
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
            ],
            'scripted-svg-manifest' => [
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
            ],
            'wasteland' => [
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
            ],
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
        'fixturesWithManifestFallbacks' => [
            'manifest-fallback-chain',
        ],
        'totals' => [
            'metadataCreators' => 28,
            'manifestItems' => 83,
            'readingOrderItems' => 35,
            'xhtmlAssets' => 43,
            'imageAssets' => 15,
            'stylesheetAssets' => 14,
            'navigationEntries' => 99,
            'landmarkEntries' => 8,
            'pageListEntries' => 1,
            'auxiliaryNavigationEntries' => 1,
            'packageLinks' => 4,
            'guideReferences' => 5,
            'remoteResourceManifestItems' => 1,
            'externalManifestItems' => 1,
            'missingLocalManifestItems' => 1,
            'manifestFallbackItems' => 2,
            'manifestFallbacks' => 1,
            'resolvedManifestFallbacks' => 1,
            'usableManifestFallbacks' => 1,
            'missingManifestFallbacks' => 1,
        ],
    ];

    /**
     * @param array{limit?: int, maxExamples?: int} $options
     * @return array<string, mixed>
     */
    public function run(string $epubDirectory, array $options = []): array
    {
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $maxExamples = max(0, (int) ($options['maxExamples'] ?? self::DEFAULT_MAX_EXAMPLES));

        if (!is_dir($epubDirectory)) {
            return $this->skippedReport($epubDirectory, 'upstream-cache-missing');
        }

        $fixtureIdentity = $this->fixtureIdentity($epubDirectory);
        $epubFiles = $this->filesByBasename($epubDirectory, 'epub');
        $nativeFiles = $this->filesByBasename($epubDirectory, 'native');
        $epubNames = array_keys($epubFiles);
        sort($epubNames, SORT_STRING);
        $pairNames = array_values(array_intersect($epubNames, array_keys($nativeFiles)));
        sort($pairNames, SORT_STRING);

        $totalEpubCount = count($epubNames);
        $totalPairCount = count($pairNames);
        if ($limit > 0) {
            $epubNames = array_slice($epubNames, 0, $limit);
            $pairNames = array_values(array_intersect($pairNames, $epubNames));
        }

        $packageParsedCount = 0;
        $readerParsedCount = 0;
        $packageParseFailures = [];
        $readerParseFailures = [];
        $packageSummaries = [];
        $packageFeatureSummaries = [];
        $readerDocuments = [];
        $categoryCounts = [];

        foreach ($epubNames as $epubName) {
            $path = $epubFiles[$epubName];
            $packageResult = $this->readPackage($path);
            if ($packageResult['ok']) {
                ++$packageParsedCount;
                /** @var EpubPackage $package */
                $package = $packageResult['package'];
                $summary = $this->packageSummary($epubName, $package);
                $packageFeatureSummaries[] = $summary;
                if (count($packageSummaries) < $maxExamples) {
                    $packageSummaries[] = $summary;
                }
            } else {
                $packageParseFailures[] = [
                    'fixture' => $epubName,
                    'error' => $packageResult['error'],
                ];
                $this->addCategory($categoryCounts, 'package-parse-failure', $epubName, $maxExamples);
            }

            $readerResult = $this->readEpub($path);
            if ($readerResult['ok']) {
                ++$readerParsedCount;
                $readerDocuments[$epubName] = $readerResult['document'];
            } else {
                $readerParseFailures[] = [
                    'fixture' => $epubName,
                    'error' => $readerResult['error'],
                ];
                $this->addCategory($categoryCounts, 'reader-parse-failure', $epubName, $maxExamples);
            }
        }

        $epubPairParsedCount = 0;
        $nativeParsedCount = 0;
        $bothParsedCount = 0;
        $normalizedAstMatchCount = 0;
        $nativeParseFailures = [];
        $astParseFailures = [];
        $mismatches = [];
        $nativeAstSignatureFixtures = [];
        $nativeAstSignaturePayloadFixtures = [];

        foreach ($pairNames as $pairName) {
            $epubDocument = $readerDocuments[$pairName] ?? null;
            if ($epubDocument instanceof AstNode) {
                ++$epubPairParsedCount;
            }

            $nativeResult = $this->readNative($nativeFiles[$pairName]);
            if ($nativeResult['ok']) {
                ++$nativeParsedCount;
            } else {
                $nativeParseFailures[] = [
                    'fixture' => $pairName,
                    'error' => $nativeResult['error'],
                ];
                $this->addCategory($categoryCounts, 'native-parse-failure', $pairName, $maxExamples);
            }

            if (!$epubDocument instanceof AstNode || !$nativeResult['ok']) {
                $astParseFailures[] = [
                    'fixture' => $pairName,
                    'epubError' => $epubDocument instanceof AstNode ? null : 'EPUB reader did not parse fixture',
                    'nativeError' => $nativeResult['error'],
                ];
                continue;
            }

            /** @var AstNode $nativeDocument */
            $nativeDocument = $nativeResult['document'];
            ++$bothParsedCount;

            $epubAst = $this->normalizedNode($epubDocument);
            $nativeAst = $this->normalizedNode($nativeDocument);
            $epubAstSha256 = hash('sha256', self::canonicalJson($epubAst));
            $nativeAstSha256 = hash('sha256', self::canonicalJson($nativeAst));
            $normalizedAstMatches = $epubAst === $nativeAst;
            $nativeAstSignatureFixtures[$pairName] = [
                'fixture' => $pairName,
                'epubNormalizedAstSha256' => $epubAstSha256,
                'nativeNormalizedAstSha256' => $nativeAstSha256,
                'normalizedAstMatches' => $normalizedAstMatches,
                'epubTopTypes' => $this->topTypeSequence($epubDocument),
                'nativeTopTypes' => $this->topTypeSequence($nativeDocument),
            ];
            $nativeAstSignaturePayloadFixtures[$pairName] = [
                'fixture' => $pairName,
                'epubNormalizedAst' => $epubAst,
                'nativeNormalizedAst' => $nativeAst,
            ];
            if ($normalizedAstMatches) {
                ++$normalizedAstMatchCount;
                continue;
            }

            $difference = $this->firstDifference($epubAst, $nativeAst) ?? 'unknown-normalized-ast-difference';
            $categories = $this->mismatchCategories($difference);
            foreach ($categories as $category) {
                $this->addCategory($categoryCounts, $category, $pairName, $maxExamples);
            }
            if (count($mismatches) < $maxExamples) {
                $mismatches[] = [
                    'fixture' => $pairName,
                    'firstDifference' => $difference,
                    'categories' => $categories,
                    'epubTopTypes' => $this->topTypeSequence($epubDocument),
                    'nativeTopTypes' => $this->topTypeSequence($nativeDocument),
                ];
            }
        }

        ksort($categoryCounts);
        $comparedEpubCount = count($epubNames);
        $comparedPairCount = count($pairNames);
        $packageParseFailureCount = count($packageParseFailures);
        $readerParseFailureCount = count($readerParseFailures);
        $astParseFailureCount = count($astParseFailures);
        $normalizedAstMismatchCount = $bothParsedCount - $normalizedAstMatchCount;

        $packageFeatureCoverage = self::packageFeatureCoverage($packageFeatureSummaries);
        $currentNativeAstSignature = self::currentNativeAstSignature(
            $fixtureIdentity,
            $nativeAstSignatureFixtures,
            $nativeAstSignaturePayloadFixtures,
            $comparedPairCount,
            $astParseFailureCount,
            $normalizedAstMismatchCount
        );

        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-epub-native-ast-package',
            'status' => 'completed',
            'skipped' => false,
            'reason' => null,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'epub-native-ast-package-comparison',
            'upstreamEpubDirectory' => $epubDirectory,
            'fixtureIdentity' => $fixtureIdentity,
            'packageFeatureCoverage' => $packageFeatureCoverage,
            'packageFeatureSignature' => self::packageFeatureSignature($fixtureIdentity, $packageFeatureCoverage),
            'currentNativeAstSignature' => $currentNativeAstSignature,
            'runnerEvidence' => self::runnerNotRunEvidence(),
            'normalizationPolicy' => self::normalizationPolicy(),
            'totalEpubCount' => $totalEpubCount,
            'comparedEpubCount' => $comparedEpubCount,
            'packageParsedCount' => $packageParsedCount,
            'readerParsedCount' => $readerParsedCount,
            'packageParseFailureCount' => $packageParseFailureCount,
            'readerParseFailureCount' => $readerParseFailureCount,
            'packageAcceptanceStatus' => self::packageAcceptanceStatus($comparedEpubCount, $packageParseFailureCount, $readerParseFailureCount),
            'totalPairCount' => $totalPairCount,
            'comparedPairCount' => $comparedPairCount,
            'epubPairParsedCount' => $epubPairParsedCount,
            'nativeParsedCount' => $nativeParsedCount,
            'bothParsedCount' => $bothParsedCount,
            'astParseFailureCount' => $astParseFailureCount,
            'nativeParseFailureCount' => count($nativeParseFailures),
            'normalizedAstMatchCount' => $normalizedAstMatchCount,
            'normalizedAstMismatchCount' => $normalizedAstMismatchCount,
            'normalizedAstMatchPercent' => self::percent($normalizedAstMatchCount, $comparedPairCount),
            'astParityStatus' => self::astParityStatus($comparedPairCount, $astParseFailureCount, $normalizedAstMismatchCount),
            'packageSummaries' => $packageSummaries,
            'packageParseFailures' => array_slice($packageParseFailures, 0, $maxExamples),
            'readerParseFailures' => array_slice($readerParseFailures, 0, $maxExamples),
            'nativeParseFailures' => array_slice($nativeParseFailures, 0, $maxExamples),
            'astParseFailures' => array_slice($astParseFailures, 0, $maxExamples),
            'mismatchComparisons' => $mismatches,
            'mismatchCategories' => array_values($categoryCounts),
            'orderedRemainingGaps' => self::orderedRemainingGaps(
                true,
                $comparedEpubCount,
                $packageParseFailureCount,
                $readerParseFailureCount,
                $comparedPairCount,
                $astParseFailureCount,
                $normalizedAstMatchCount,
                $normalizedAstMismatchCount
            ),
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public function formatReport(array $report): string
    {
        $lines = [
            'Pandoc EPUB native/package comparison: ' . (string) ($report['status'] ?? 'unknown'),
            'Verdict: ' . (string) ($report['verdict'] ?? self::VERDICT),
            'Claim: ' . (string) ($report['claim'] ?? self::CLAIM),
            'upstreamEpubDirectory=' . (string) ($report['upstreamEpubDirectory'] ?? ''),
        ];

        if (($report['skipped'] ?? false) === true) {
            $lines[] = 'reason=' . (string) ($report['reason'] ?? 'unknown');
            $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
            if ($runner !== []) {
                $lines[] = sprintf(
                    'runnerEvidence: status=%s plan=%s executed=%s',
                    (string) ($runner['status'] ?? 'unknown'),
                    (string) ($runner['commandPlanStatus'] ?? 'unknown'),
                    (($runner['executed'] ?? null) === false) ? 'false' : 'unknown'
                );
            }
            $lines = self::appendOrderedRemainingGaps($lines, $report);

            return implode(PHP_EOL, $lines) . PHP_EOL;
        }

        $lines[] = sprintf(
            'packages: total=%d compared=%d packageParsed=%d readerParsed=%d packageFailures=%d readerFailures=%d status=%s',
            (int) ($report['totalEpubCount'] ?? 0),
            (int) ($report['comparedEpubCount'] ?? 0),
            (int) ($report['packageParsedCount'] ?? 0),
            (int) ($report['readerParsedCount'] ?? 0),
            (int) ($report['packageParseFailureCount'] ?? 0),
            (int) ($report['readerParseFailureCount'] ?? 0),
            (string) ($report['packageAcceptanceStatus'] ?? 'unknown')
        );
        $lines[] = sprintf(
            'nativePairs: total=%d compared=%d parsedBoth=%d parseFailures=%d nativeFailures=%d',
            (int) ($report['totalPairCount'] ?? 0),
            (int) ($report['comparedPairCount'] ?? 0),
            (int) ($report['bothParsedCount'] ?? 0),
            (int) ($report['astParseFailureCount'] ?? 0),
            (int) ($report['nativeParseFailureCount'] ?? 0)
        );
        $lines[] = sprintf(
            'normalizedAst: matches=%d (%s) mismatches=%d status=%s',
            (int) ($report['normalizedAstMatchCount'] ?? 0),
            self::formatPercent($report['normalizedAstMatchPercent'] ?? null),
            (int) ($report['normalizedAstMismatchCount'] ?? 0),
            (string) ($report['astParityStatus'] ?? 'unknown')
        );
        $fixtureIdentity = is_array($report['fixtureIdentity'] ?? null) ? $report['fixtureIdentity'] : [];
        $fixtureValidation = is_array($fixtureIdentity['validation'] ?? null) ? $fixtureIdentity['validation'] : [];
        if ($fixtureIdentity !== []) {
            $lines[] = sprintf(
                'fixtureIdentity: status=%s expected=%d observed=%d',
                (string) ($fixtureValidation['status'] ?? 'unknown'),
                (int) ($fixtureIdentity['expectedFileCount'] ?? 0),
                (int) ($fixtureIdentity['observedFileCount'] ?? 0)
            );
        }
        $featureCoverage = is_array($report['packageFeatureCoverage'] ?? null) ? $report['packageFeatureCoverage'] : [];
        if ($featureCoverage !== []) {
            $navigationTypeCounts = is_array($featureCoverage['navigationTypeCounts'] ?? null)
                ? $featureCoverage['navigationTypeCounts']
                : [];
            $totals = is_array($featureCoverage['totals'] ?? null) ? $featureCoverage['totals'] : [];
            $coverFixtures = is_array($featureCoverage['fixturesWithCoverImagePart'] ?? null)
                ? $featureCoverage['fixturesWithCoverImagePart']
                : [];
            $landmarkFixtures = is_array($featureCoverage['fixturesWithLandmarks'] ?? null)
                ? $featureCoverage['fixturesWithLandmarks']
                : [];
            $pageListFixtures = is_array($featureCoverage['fixturesWithPageLists'] ?? null)
                ? $featureCoverage['fixturesWithPageLists']
                : [];
            $auxiliaryNavigationFixtures = is_array($featureCoverage['fixturesWithAuxiliaryNavigation'] ?? null)
                ? $featureCoverage['fixturesWithAuxiliaryNavigation']
                : [];
            $guideReferenceTypeCounts = is_array($featureCoverage['guideReferenceTypeCounts'] ?? null)
                ? $featureCoverage['guideReferenceTypeCounts']
                : [];
            $packageLinkRelCounts = is_array($featureCoverage['packageLinkRelCounts'] ?? null)
                ? $featureCoverage['packageLinkRelCounts']
                : [];
            $lines[] = sprintf(
                'packageFeatureCoverage: fixtures=%d nav=%d ncx=%d covers=%d landmarks=%d pageLists=%d auxiliaryNav=%d metadataCreators=%d manifestItems=%d readingOrderItems=%d imageAssets=%d stylesheetAssets=%d resourceKinds=%s guideRefTypes=%s packageLinkRels=%s remoteManifest=%d externalManifest=%d missingLocalManifest=%d manifestFallbacks=%d',
                (int) ($featureCoverage['fixtureCount'] ?? 0),
                (int) ($navigationTypeCounts['nav'] ?? 0),
                (int) ($navigationTypeCounts['ncx'] ?? 0),
                count($coverFixtures),
                count($landmarkFixtures),
                count($pageListFixtures),
                count($auxiliaryNavigationFixtures),
                (int) ($totals['metadataCreators'] ?? 0),
                (int) ($totals['manifestItems'] ?? 0),
                (int) ($totals['readingOrderItems'] ?? 0),
                (int) ($totals['imageAssets'] ?? 0),
                (int) ($totals['stylesheetAssets'] ?? 0),
                self::formatCounts(is_array($featureCoverage['manifestResourceKindCounts'] ?? null)
                    ? $featureCoverage['manifestResourceKindCounts']
                    : []),
                self::formatCounts($guideReferenceTypeCounts),
                self::formatCounts($packageLinkRelCounts),
                (int) ($totals['remoteResourceManifestItems'] ?? 0),
                (int) ($totals['externalManifestItems'] ?? 0),
                (int) ($totals['missingLocalManifestItems'] ?? 0),
                (int) ($totals['manifestFallbacks'] ?? 0)
            );
        }
        $featureSignature = is_array($report['packageFeatureSignature'] ?? null) ? $report['packageFeatureSignature'] : [];
        if ($featureSignature !== []) {
            $signatureValidation = is_array($featureSignature['validation'] ?? null) ? $featureSignature['validation'] : [];
            $lines[] = sprintf(
                'packageFeatureSignature: status=%s matchesExpected=%s sha256=%s expected=%s',
                (string) ($signatureValidation['status'] ?? 'unknown'),
                (($featureSignature['matchesExpected'] ?? false) === true) ? 'true' : 'false',
                (string) ($featureSignature['sha256'] ?? ''),
                (string) ($featureSignature['expectedSha256'] ?? '')
            );
        }
        $nativeAstSignature = is_array($report['currentNativeAstSignature'] ?? null) ? $report['currentNativeAstSignature'] : [];
        if ($nativeAstSignature !== []) {
            $signatureValidation = is_array($nativeAstSignature['validation'] ?? null) ? $nativeAstSignature['validation'] : [];
            $lines[] = sprintf(
                'currentNativeAstSignature: status=%s matchesExpected=%s fixtures=%d sha256=%s expected=%s',
                (string) ($signatureValidation['status'] ?? 'unknown'),
                (($nativeAstSignature['matchesExpected'] ?? false) === true) ? 'true' : 'false',
                (int) ($nativeAstSignature['fixtureCount'] ?? 0),
                (string) ($nativeAstSignature['sha256'] ?? ''),
                (string) ($nativeAstSignature['expectedSha256'] ?? '')
            );
        }
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
        if ($runner !== []) {
            $lines[] = sprintf(
                'runnerEvidence: status=%s plan=%s executed=%s',
                (string) ($runner['status'] ?? 'unknown'),
                (string) ($runner['commandPlanStatus'] ?? 'unknown'),
                (($runner['executed'] ?? null) === false) ? 'false' : 'unknown'
            );
        }

        $mismatches = $report['mismatchComparisons'] ?? [];
        if (is_array($mismatches) && $mismatches !== []) {
            $lines[] = 'mismatchExamples:';
            foreach ($mismatches as $mismatch) {
                if (!is_array($mismatch)) {
                    continue;
                }
                $lines[] = '- ' . (string) ($mismatch['fixture'] ?? 'unknown')
                    . ': ' . (string) ($mismatch['firstDifference'] ?? 'unknown');
            }
        }

        $categories = $report['mismatchCategories'] ?? [];
        if (is_array($categories) && $categories !== []) {
            $lines[] = 'mismatchCategories:';
            foreach ($categories as $category) {
                if (!is_array($category)) {
                    continue;
                }
                $examples = $category['examples'] ?? [];
                $exampleText = is_array($examples) && $examples !== []
                    ? ' examples=' . implode(',', array_map('strval', $examples))
                    : '';
                $lines[] = sprintf(
                    '- %s count=%d%s',
                    (string) ($category['category'] ?? 'unknown'),
                    (int) ($category['count'] ?? 0),
                    $exampleText
                );
            }
        }

        $lines = self::appendOrderedRemainingGaps($lines, $report);

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredPackageParity(array $report, int $requiredEpubCount): bool
    {
        if ($requiredEpubCount < 0) {
            throw new \InvalidArgumentException('Required EPUB package count must not be negative');
        }

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && (int) ($report['totalEpubCount'] ?? -1) === $requiredEpubCount
            && (int) ($report['comparedEpubCount'] ?? -1) === $requiredEpubCount
            && (int) ($report['packageParsedCount'] ?? -1) === $requiredEpubCount
            && (int) ($report['readerParsedCount'] ?? -1) === $requiredEpubCount
            && (int) ($report['packageParseFailureCount'] ?? -1) === 0
            && (int) ($report['readerParseFailureCount'] ?? -1) === 0
            && ($report['packageAcceptanceStatus'] ?? null) === 'package-and-reader-acceptance-observed-not-full-epub-parity';
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredNativeReadiness(array $report, int $requiredPairCount): bool
    {
        if ($requiredPairCount < 0) {
            throw new \InvalidArgumentException('Required EPUB native pair count must not be negative');
        }

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && (int) ($report['totalPairCount'] ?? -1) === $requiredPairCount
            && (int) ($report['comparedPairCount'] ?? -1) === $requiredPairCount
            && (int) ($report['epubPairParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['nativeParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['bothParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['astParseFailureCount'] ?? -1) === 0
            && (int) ($report['nativeParseFailureCount'] ?? -1) === 0;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredMappedParity(array $report, int $requiredPairCount): bool
    {
        if ($requiredPairCount < 0) {
            throw new \InvalidArgumentException('Required EPUB mapped parity count must not be negative');
        }

        return self::hasRequiredNativeReadiness($report, $requiredPairCount)
            && (int) ($report['normalizedAstMatchCount'] ?? -1) === $requiredPairCount
            && (int) ($report['normalizedAstMismatchCount'] ?? -1) === 0
            && ($report['astParityStatus'] ?? null) === 'normalized-ast-equality-observed-not-runner-parity';
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredFixtureIdentity(array $report): bool
    {
        $identity = is_array($report['fixtureIdentity'] ?? null) ? $report['fixtureIdentity'] : [];
        $validation = is_array($identity['validation'] ?? null) ? $identity['validation'] : [];

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && (int) ($identity['expectedFileCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES)
            && (int) ($identity['observedFileCount'] ?? -1) === count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES)
            && ($validation['status'] ?? null) === 'valid-checked-in-current-epub-fixture-identity'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredCurrentPackageFeatureCoverage(array $report): bool
    {
        $coverage = is_array($report['packageFeatureCoverage'] ?? null) ? $report['packageFeatureCoverage'] : [];
        if (($report['skipped'] ?? false) !== false || ($report['status'] ?? null) !== 'completed') {
            return false;
        }

        return self::packageFeatureCoverageMatchesExpected($coverage);
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredCurrentPackageFeatureSignature(array $report): bool
    {
        $signature = is_array($report['packageFeatureSignature'] ?? null) ? $report['packageFeatureSignature'] : [];
        $validation = is_array($signature['validation'] ?? null) ? $signature['validation'] : [];

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && self::hasRequiredFixtureIdentity($report)
            && self::hasRequiredCurrentPackageFeatureCoverage($report)
            && ($signature['kind'] ?? null) === self::PACKAGE_FEATURE_SIGNATURE_KIND
            && ($signature['algorithm'] ?? null) === self::PACKAGE_FEATURE_SIGNATURE_ALGORITHM
            && ($signature['scope'] ?? null) === self::PACKAGE_FEATURE_SIGNATURE_SCOPE
            && ($signature['sha256'] ?? null) === self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_SIGNATURE_SHA256
            && ($signature['expectedSha256'] ?? null) === self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_SIGNATURE_SHA256
            && ($signature['hashMatchesExpected'] ?? null) === true
            && ($signature['matchesExpected'] ?? null) === true
            && ($validation['status'] ?? null) === 'valid-checked-in-current-epub-package-feature-signature'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredCurrentNativeAstSignature(array $report): bool
    {
        $signature = is_array($report['currentNativeAstSignature'] ?? null) ? $report['currentNativeAstSignature'] : [];
        $validation = is_array($signature['validation'] ?? null) ? $signature['validation'] : [];
        $expectedPairCount = count(self::expectedCheckedInCurrentPairNames());

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && self::hasRequiredFixtureIdentity($report)
            && (int) ($report['totalPairCount'] ?? -1) === $expectedPairCount
            && (int) ($report['comparedPairCount'] ?? -1) === $expectedPairCount
            && (int) ($report['bothParsedCount'] ?? -1) === $expectedPairCount
            && (int) ($report['astParseFailureCount'] ?? -1) === 0
            && (int) ($report['nativeParseFailureCount'] ?? -1) === 0
            && (int) ($report['normalizedAstMatchCount'] ?? -1) === $expectedPairCount
            && (int) ($report['normalizedAstMismatchCount'] ?? -1) === 0
            && ($signature['kind'] ?? null) === self::CURRENT_NATIVE_AST_SIGNATURE_KIND
            && ($signature['algorithm'] ?? null) === self::CURRENT_NATIVE_AST_SIGNATURE_ALGORITHM
            && ($signature['scope'] ?? null) === self::CURRENT_NATIVE_AST_SIGNATURE_SCOPE
            && ($signature['sha256'] ?? null) === self::CHECKED_IN_CURRENT_NATIVE_AST_SIGNATURE_SHA256
            && ($signature['expectedSha256'] ?? null) === self::CHECKED_IN_CURRENT_NATIVE_AST_SIGNATURE_SHA256
            && ($signature['hashMatchesExpected'] ?? null) === true
            && ($signature['matchesExpected'] ?? null) === true
            && ($validation['status'] ?? null) === 'valid-checked-in-current-epub-normalized-native-ast-signature'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRunnerNotRunEvidence(array $report): bool
    {
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];

        return ($runner['status'] ?? null) === 'not-run'
            && ($runner['executed'] ?? null) === false
            && array_key_exists('command', $runner)
            && $runner['command'] === null
            && array_key_exists('resultArtifact', $runner)
            && $runner['resultArtifact'] === null;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRunnerPlanEvidence(array $report): bool
    {
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
        $binding = is_array($runner['upstreamBinding'] ?? null) ? $runner['upstreamBinding'] : [];
        $target = is_array($runner['target'] ?? null) ? $runner['target'] : [];
        $snapshot = is_array($runner['checkedInSnapshot'] ?? null) ? $runner['checkedInSnapshot'] : [];

        return self::hasRunnerNotRunEvidence($report)
            && ($runner['commandPlanStatus'] ?? null) === 'planned-not-run'
            && ($binding['name'] ?? null) === 'jgm/pandoc'
            && ($binding['expectedCommit'] ?? null) === self::EXPECTED_UPSTREAM_COMMIT
            && ($binding['executableTarget'] ?? null) === self::RUNNER_CABAL_TARGET
            && ($binding['fixtureDirectory'] ?? null) === self::RUNNER_FIXTURE_DIRECTORY
            && ($target['cabalTarget'] ?? null) === self::RUNNER_CABAL_TARGET
            && ($target['inputFormat'] ?? null) === 'epub'
            && ($target['outputFormat'] ?? null) === self::RUNNER_OUTPUT_FORMAT
            && ($target['fixtureDirectory'] ?? null) === self::RUNNER_FIXTURE_DIRECTORY
            && ($target['fixtureBasenames'] ?? null) === self::expectedCheckedInCurrentPairNames()
            && ($snapshot['fixtureIdentityKind'] ?? null) === 'static-checked-in-current-epub-fixture-identity'
            && ($snapshot['expectedFileCount'] ?? null) === count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES)
            && ($snapshot['expectedPairCount'] ?? null) === count(self::expectedCheckedInCurrentPairNames())
            && ($runner['futureCommands'] ?? null) === self::runnerFutureCommands()
            && ($runner['requiredTranscripts'] ?? null) === self::RUNNER_REQUIRED_TRANSCRIPTS
            && ($runner['requiredArtifacts'] ?? null) === self::RUNNER_REQUIRED_ARTIFACTS;
    }

    /**
     * @return array<string, mixed>
     */
    private function skippedReport(string $epubDirectory, string $reason): array
    {
        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-epub-native-ast-package',
            'status' => 'skipped',
            'skipped' => true,
            'reason' => $reason,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'epub-native-ast-package-comparison',
            'upstreamEpubDirectory' => $epubDirectory,
            'fixtureIdentity' => self::notEvaluatedFixtureIdentity(),
            'packageFeatureCoverage' => self::emptyPackageFeatureCoverage(),
            'packageFeatureSignature' => self::notEvaluatedPackageFeatureSignature($reason),
            'currentNativeAstSignature' => self::notEvaluatedCurrentNativeAstSignature($reason),
            'runnerEvidence' => self::runnerNotRunEvidence(),
            'normalizationPolicy' => self::normalizationPolicy(),
            'totalEpubCount' => 0,
            'comparedEpubCount' => 0,
            'packageParsedCount' => 0,
            'readerParsedCount' => 0,
            'packageParseFailureCount' => 0,
            'readerParseFailureCount' => 0,
            'packageAcceptanceStatus' => 'not-evaluated-source-directory-unavailable',
            'totalPairCount' => 0,
            'comparedPairCount' => 0,
            'epubPairParsedCount' => 0,
            'nativeParsedCount' => 0,
            'bothParsedCount' => 0,
            'astParseFailureCount' => 0,
            'nativeParseFailureCount' => 0,
            'normalizedAstMatchCount' => 0,
            'normalizedAstMismatchCount' => 0,
            'normalizedAstMatchPercent' => null,
            'astParityStatus' => 'not-evaluated-source-directory-unavailable',
            'packageSummaries' => [],
            'packageParseFailures' => [],
            'readerParseFailures' => [],
            'nativeParseFailures' => [],
            'astParseFailures' => [],
            'mismatchComparisons' => [],
            'mismatchCategories' => [],
            'orderedRemainingGaps' => self::orderedRemainingGaps(false, 0, 0, 0, 0, 0, 0, 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizationPolicy(): array
    {
        return [
            'compares' => [
                'node type',
                'non-provenance node attributes',
                'child order and child count',
                'visible inline and block AST shape after adjacent text nodes are coalesced',
            ],
            'excludes' => [
                'document metadata and local EPUB package provenance attrs',
                'derived text attrs on plain, paragraph, heading, table_cell, and term nodes',
                'reader-specific adjacent Str/Space text-node segmentation',
            ],
            'doesNotAssert' => [
                'upstream Haskell/Cabal runner execution',
                'EPUB writer parity',
                'byte-level EPUB package equality',
                'full EPUB feature parity beyond audited package and native fixtures',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function runnerNotRunEvidence(): array
    {
        return [
            'runner' => 'Cabal-built Pandoc EPUB to native executable plan',
            'scope' => 'upstream-haskell-runner',
            'status' => 'not-run',
            'executed' => false,
            'command' => null,
            'resultArtifact' => null,
            'commandPlanStatus' => 'planned-not-run',
            'upstreamBinding' => [
                'name' => 'jgm/pandoc',
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'executableTarget' => self::RUNNER_CABAL_TARGET,
                'fixtureDirectory' => self::RUNNER_FIXTURE_DIRECTORY,
            ],
            'target' => [
                'cabalTarget' => self::RUNNER_CABAL_TARGET,
                'inputFormat' => 'epub',
                'outputFormat' => self::RUNNER_OUTPUT_FORMAT,
                'fixtureDirectory' => self::RUNNER_FIXTURE_DIRECTORY,
                'fixtureBasenames' => self::expectedCheckedInCurrentPairNames(),
            ],
            'checkedInSnapshot' => [
                'fixtureIdentityKind' => 'static-checked-in-current-epub-fixture-identity',
                'expectedFileCount' => count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES),
                'expectedPairCount' => count(self::expectedCheckedInCurrentPairNames()),
                'packageFeatureSignature' => self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_SIGNATURE_SHA256,
                'nativeAstSignature' => self::CHECKED_IN_CURRENT_NATIVE_AST_SIGNATURE_SHA256,
            ],
            'blockers' => [
                'no committed upstream pandoc executable transcript or generated native manifest is present',
                'this PHP evidence gate intentionally does not invoke Cabal or run the upstream Pandoc executable',
                'a future runner claim must be bound to the pinned upstream commit and checked-in EPUB/native fixture snapshot',
            ],
            'futureCommands' => self::runnerFutureCommands(),
            'requiredTranscripts' => self::RUNNER_REQUIRED_TRANSCRIPTS,
            'requiredArtifacts' => self::RUNNER_REQUIRED_ARTIFACTS,
            'reason' => 'This PHP package/native evidence packet is generated without executing the upstream Haskell runner.',
            'claim' => 'No upstream Haskell runner or executable native generation parity is claimed.',
        ];
    }

    /**
     * @return list<array{purpose: string, program: string, arguments: list<string>}>
     */
    private static function runnerFutureCommands(): array
    {
        return [
            [
                'purpose' => 'prepare the upstream pandoc executable dependencies in an isolated build directory',
                'program' => 'cabal',
                'arguments' => [
                    'v2-build',
                    '--offline',
                    '--dry-run',
                    '--only-dependencies',
                    '--project-dir=.',
                    '--builddir=' . self::RUNNER_BUILD_DIR,
                    self::RUNNER_CABAL_TARGET,
                ],
            ],
            [
                'purpose' => 'build the upstream pandoc executable for EPUB to native fixture generation',
                'program' => 'cabal',
                'arguments' => [
                    'v2-build',
                    '--offline',
                    '--project-dir=.',
                    '--builddir=' . self::RUNNER_BUILD_DIR,
                    self::RUNNER_CABAL_TARGET,
                ],
            ],
            [
                'purpose' => 'generate native AST goldens for the checked-in current EPUB fixture set',
                'program' => 'cabal',
                'arguments' => [
                    'v2-run',
                    '--offline',
                    '--project-dir=.',
                    '--builddir=' . self::RUNNER_BUILD_DIR,
                    self::RUNNER_CABAL_TARGET,
                    '--',
                    '--from',
                    'epub',
                    '--to',
                    self::RUNNER_OUTPUT_FORMAT,
                    self::RUNNER_FIXTURE_DIRECTORY . '/{fixture}.epub',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyPackageFeatureCoverage(): array
    {
        return [
            'kind' => 'epub-package-feature-coverage',
            'fixtureCount' => 0,
            'metadataLanguageCounts' => [],
            'fixturesWithCreators' => [],
            'navigationTypeCounts' => [],
            'manifestMediaTypeCounts' => [],
            'manifestPropertyCounts' => [],
            'manifestResourceKindCounts' => [],
            'navigationSectionTypes' => [],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'fixtureFeatureSignatures' => [],
            'fixturesWithGuideReferences' => [],
            'fixturesWithPackageLinks' => [],
            'fixturesWithCoverImagePart' => [],
            'fixturesWithImages' => [],
            'fixturesWithStylesheets' => [],
            'fixturesWithLandmarks' => [],
            'fixturesWithPageLists' => [],
            'fixturesWithAuxiliaryNavigation' => [],
            'fixturesWithRemoteManifestResources' => [],
            'fixturesWithExternalManifestItems' => [],
            'fixturesWithMissingLocalManifestItems' => [],
            'fixturesWithManifestFallbacks' => [],
            'totals' => [
                'metadataCreators' => 0,
                'manifestItems' => 0,
                'readingOrderItems' => 0,
                'xhtmlAssets' => 0,
                'imageAssets' => 0,
                'stylesheetAssets' => 0,
                'navigationEntries' => 0,
                'landmarkEntries' => 0,
                'pageListEntries' => 0,
                'auxiliaryNavigationEntries' => 0,
                'packageLinks' => 0,
                'guideReferences' => 0,
                'remoteResourceManifestItems' => 0,
                'externalManifestItems' => 0,
                'missingLocalManifestItems' => 0,
                'manifestFallbackItems' => 0,
                'manifestFallbacks' => 0,
                'resolvedManifestFallbacks' => 0,
                'usableManifestFallbacks' => 0,
                'missingManifestFallbacks' => 0,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $coverage
     */
    private static function packageFeatureCoverageMatchesExpected(array $coverage): bool
    {
        foreach (self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_COVERAGE as $key => $expected) {
            if (($coverage[$key] ?? null) !== $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $fixtureIdentity
     * @param array<string, mixed> $coverage
     * @return array<string, mixed>
     */
    private static function packageFeatureSignature(array $fixtureIdentity, array $coverage): array
    {
        $payload = self::packageFeatureSignaturePayload($fixtureIdentity, $coverage);
        $sha256 = hash('sha256', self::canonicalJson($payload));
        $fixtureValidation = is_array($fixtureIdentity['validation'] ?? null) ? $fixtureIdentity['validation'] : [];
        $fixtureIdentityMatchesExpected = ($fixtureValidation['status'] ?? null) === 'valid-checked-in-current-epub-fixture-identity'
            && ($fixtureValidation['issues'] ?? null) === [];
        $coverageMatchesExpected = self::packageFeatureCoverageMatchesExpected($coverage);
        $hashMatchesExpected = $sha256 === self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_SIGNATURE_SHA256;
        $issues = [];
        if (!$fixtureIdentityMatchesExpected) {
            $issues[] = 'fixture-identity-does-not-match-expected-snapshot';
        }
        if (!$coverageMatchesExpected) {
            $issues[] = 'package-feature-coverage-does-not-match-expected-snapshot';
        }
        if (!$hashMatchesExpected) {
            $issues[] = 'package-feature-signature-sha256-mismatch';
        }

        return [
            'kind' => self::PACKAGE_FEATURE_SIGNATURE_KIND,
            'algorithm' => self::PACKAGE_FEATURE_SIGNATURE_ALGORITHM,
            'scope' => self::PACKAGE_FEATURE_SIGNATURE_SCOPE,
            'snapshotSchemaVersion' => 1,
            'coverageKeys' => array_keys(self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_COVERAGE),
            'sha256' => $sha256,
            'expectedSha256' => self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_SIGNATURE_SHA256,
            'hashMatchesExpected' => $hashMatchesExpected,
            'matchesExpected' => $issues === [],
            'validation' => [
                'status' => $issues === []
                    ? 'valid-checked-in-current-epub-package-feature-signature'
                    : 'invalid-checked-in-current-epub-package-feature-signature',
                'issues' => $issues,
                'fixtureIdentityStatus' => (string) ($fixtureValidation['status'] ?? 'unknown'),
                'packageFeatureCoverageMatchesExpected' => $coverageMatchesExpected,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function notEvaluatedPackageFeatureSignature(string $reason): array
    {
        return [
            'kind' => self::PACKAGE_FEATURE_SIGNATURE_KIND,
            'algorithm' => self::PACKAGE_FEATURE_SIGNATURE_ALGORITHM,
            'scope' => self::PACKAGE_FEATURE_SIGNATURE_SCOPE,
            'snapshotSchemaVersion' => 1,
            'coverageKeys' => array_keys(self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_COVERAGE),
            'sha256' => null,
            'expectedSha256' => self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_SIGNATURE_SHA256,
            'hashMatchesExpected' => false,
            'matchesExpected' => false,
            'validation' => [
                'status' => 'not-evaluated-source-directory-unavailable',
                'issues' => [$reason],
                'fixtureIdentityStatus' => 'not-evaluated-source-directory-unavailable',
                'packageFeatureCoverageMatchesExpected' => false,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $fixtureIdentity
     * @param array<string, array<string, mixed>> $fixtureSignatures
     * @param array<string, array<string, mixed>> $payloadFixtures
     * @return array<string, mixed>
     */
    private static function currentNativeAstSignature(
        array $fixtureIdentity,
        array $fixtureSignatures,
        array $payloadFixtures,
        int $comparedPairCount,
        int $astParseFailureCount,
        int $normalizedAstMismatchCount
    ): array {
        ksort($fixtureSignatures, SORT_STRING);
        ksort($payloadFixtures, SORT_STRING);
        $expectedFixtures = self::expectedCheckedInCurrentPairNames();
        $observedFixtures = array_keys($fixtureSignatures);
        sort($observedFixtures, SORT_STRING);

        $payload = self::currentNativeAstSignaturePayload($fixtureIdentity, $payloadFixtures, $expectedFixtures);
        $sha256 = hash('sha256', self::canonicalJson($payload));
        $fixtureValidation = is_array($fixtureIdentity['validation'] ?? null) ? $fixtureIdentity['validation'] : [];
        $fixtureIdentityMatchesExpected = ($fixtureValidation['status'] ?? null) === 'valid-checked-in-current-epub-fixture-identity'
            && ($fixtureValidation['issues'] ?? null) === [];
        $fixturesMatchExpected = $observedFixtures === $expectedFixtures;
        $astComparisonMatchesExpected = $fixturesMatchExpected
            && $comparedPairCount === count($expectedFixtures)
            && $astParseFailureCount === 0
            && $normalizedAstMismatchCount === 0
            && self::nativeAstFixtureHashesMatch($fixtureSignatures);
        $hashMatchesExpected = $sha256 === self::CHECKED_IN_CURRENT_NATIVE_AST_SIGNATURE_SHA256;
        $issues = [];
        if (!$fixtureIdentityMatchesExpected) {
            $issues[] = 'fixture-identity-does-not-match-expected-snapshot';
        }
        if (!$fixturesMatchExpected) {
            $issues[] = 'normalized-native-ast-fixtures-do-not-match-expected-snapshot';
        }
        if (!$astComparisonMatchesExpected) {
            $issues[] = 'normalized-native-ast-comparison-does-not-match-expected-snapshot';
        }
        if (!$hashMatchesExpected) {
            $issues[] = 'normalized-native-ast-signature-sha256-mismatch';
        }

        return [
            'kind' => self::CURRENT_NATIVE_AST_SIGNATURE_KIND,
            'algorithm' => self::CURRENT_NATIVE_AST_SIGNATURE_ALGORITHM,
            'scope' => self::CURRENT_NATIVE_AST_SIGNATURE_SCOPE,
            'snapshotSchemaVersion' => 1,
            'fixtureCount' => count($fixtureSignatures),
            'expectedFixtureCount' => count($expectedFixtures),
            'expectedFixtures' => $expectedFixtures,
            'observedFixtures' => $observedFixtures,
            'fixtureSignatures' => $fixtureSignatures,
            'sha256' => $sha256,
            'expectedSha256' => self::CHECKED_IN_CURRENT_NATIVE_AST_SIGNATURE_SHA256,
            'hashMatchesExpected' => $hashMatchesExpected,
            'matchesExpected' => $issues === [],
            'validation' => [
                'status' => $issues === []
                    ? 'valid-checked-in-current-epub-normalized-native-ast-signature'
                    : 'invalid-checked-in-current-epub-normalized-native-ast-signature',
                'issues' => $issues,
                'fixtureIdentityStatus' => (string) ($fixtureValidation['status'] ?? 'unknown'),
                'fixturesMatchExpected' => $fixturesMatchExpected,
                'normalizedAstComparisonMatchesExpected' => $astComparisonMatchesExpected,
                'comparedPairCount' => $comparedPairCount,
                'astParseFailureCount' => $astParseFailureCount,
                'normalizedAstMismatchCount' => $normalizedAstMismatchCount,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function notEvaluatedCurrentNativeAstSignature(string $reason): array
    {
        return [
            'kind' => self::CURRENT_NATIVE_AST_SIGNATURE_KIND,
            'algorithm' => self::CURRENT_NATIVE_AST_SIGNATURE_ALGORITHM,
            'scope' => self::CURRENT_NATIVE_AST_SIGNATURE_SCOPE,
            'snapshotSchemaVersion' => 1,
            'fixtureCount' => 0,
            'expectedFixtureCount' => count(self::expectedCheckedInCurrentPairNames()),
            'expectedFixtures' => self::expectedCheckedInCurrentPairNames(),
            'observedFixtures' => [],
            'fixtureSignatures' => [],
            'sha256' => null,
            'expectedSha256' => self::CHECKED_IN_CURRENT_NATIVE_AST_SIGNATURE_SHA256,
            'hashMatchesExpected' => false,
            'matchesExpected' => false,
            'validation' => [
                'status' => 'not-evaluated-source-directory-unavailable',
                'issues' => [$reason],
                'fixtureIdentityStatus' => 'not-evaluated-source-directory-unavailable',
                'fixturesMatchExpected' => false,
                'normalizedAstComparisonMatchesExpected' => false,
                'comparedPairCount' => 0,
                'astParseFailureCount' => 0,
                'normalizedAstMismatchCount' => 0,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $fixtureIdentity
     * @param array<string, array<string, mixed>> $payloadFixtures
     * @param list<string> $expectedFixtures
     * @return array<string, mixed>
     */
    private static function currentNativeAstSignaturePayload(array $fixtureIdentity, array $payloadFixtures, array $expectedFixtures): array
    {
        $files = [];
        foreach (is_array($fixtureIdentity['files'] ?? null) ? $fixtureIdentity['files'] : [] as $file) {
            if (!is_array($file)) {
                continue;
            }
            $bytes = $file['bytes'] ?? null;
            $files[] = [
                'path' => (string) ($file['path'] ?? ''),
                'sha256' => is_string($file['sha256'] ?? null) ? $file['sha256'] : null,
                'bytes' => is_int($bytes) ? $bytes : (is_numeric($bytes) ? (int) $bytes : null),
            ];
        }
        usort(
            $files,
            static fn (array $left, array $right): int => (string) ($left['path'] ?? '') <=> (string) ($right['path'] ?? '')
        );

        $fixtures = [];
        foreach ($payloadFixtures as $fixture => $payloadFixture) {
            $fixtures[] = [
                'fixture' => is_string($payloadFixture['fixture'] ?? null) ? $payloadFixture['fixture'] : (string) $fixture,
                'epubNormalizedAst' => $payloadFixture['epubNormalizedAst'] ?? null,
                'nativeNormalizedAst' => $payloadFixture['nativeNormalizedAst'] ?? null,
            ];
        }
        usort(
            $fixtures,
            static fn (array $left, array $right): int => (string) ($left['fixture'] ?? '') <=> (string) ($right['fixture'] ?? '')
        );

        return [
            'schemaVersion' => 1,
            'fixtureIdentity' => [
                'kind' => (string) ($fixtureIdentity['kind'] ?? ''),
                'expectedFileCount' => (int) ($fixtureIdentity['expectedFileCount'] ?? 0),
                'observedFileCount' => (int) ($fixtureIdentity['observedFileCount'] ?? 0),
                'expectedFiles' => self::stringList($fixtureIdentity['expectedFiles'] ?? []),
                'observedFiles' => self::stringList($fixtureIdentity['observedFiles'] ?? []),
                'files' => $files,
            ],
            'expectedFixtures' => $expectedFixtures,
            'normalizedNativeAstFixtures' => $fixtures,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $fixtureSignatures
     */
    private static function nativeAstFixtureHashesMatch(array $fixtureSignatures): bool
    {
        if ($fixtureSignatures === []) {
            return false;
        }

        foreach ($fixtureSignatures as $signature) {
            if (($signature['normalizedAstMatches'] ?? null) !== true) {
                return false;
            }
            if (
                !is_string($signature['epubNormalizedAstSha256'] ?? null)
                || !is_string($signature['nativeNormalizedAstSha256'] ?? null)
                || $signature['epubNormalizedAstSha256'] !== $signature['nativeNormalizedAstSha256']
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function expectedCheckedInCurrentPairNames(): array
    {
        $epubFixtures = [];
        $nativeFixtures = [];
        foreach (array_keys(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES) as $path) {
            if (str_ends_with($path, '.epub')) {
                $epubFixtures[basename($path, '.epub')] = true;
            } elseif (str_ends_with($path, '.native')) {
                $nativeFixtures[basename($path, '.native')] = true;
            }
        }

        $pairs = array_values(array_intersect(array_keys($epubFixtures), array_keys($nativeFixtures)));
        sort($pairs, SORT_STRING);

        return $pairs;
    }

    /**
     * @param array<string, mixed> $fixtureIdentity
     * @param array<string, mixed> $coverage
     * @return array<string, mixed>
     */
    private static function packageFeatureSignaturePayload(array $fixtureIdentity, array $coverage): array
    {
        $files = [];
        foreach (is_array($fixtureIdentity['files'] ?? null) ? $fixtureIdentity['files'] : [] as $file) {
            if (!is_array($file)) {
                continue;
            }
            $bytes = $file['bytes'] ?? null;
            $files[] = [
                'path' => (string) ($file['path'] ?? ''),
                'sha256' => is_string($file['sha256'] ?? null) ? $file['sha256'] : null,
                'bytes' => is_int($bytes) ? $bytes : (is_numeric($bytes) ? (int) $bytes : null),
            ];
        }
        usort(
            $files,
            static fn (array $left, array $right): int => (string) ($left['path'] ?? '') <=> (string) ($right['path'] ?? '')
        );

        return [
            'schemaVersion' => 1,
            'fixtureIdentity' => [
                'kind' => (string) ($fixtureIdentity['kind'] ?? ''),
                'expectedFileCount' => (int) ($fixtureIdentity['expectedFileCount'] ?? 0),
                'observedFileCount' => (int) ($fixtureIdentity['observedFileCount'] ?? 0),
                'expectedFiles' => self::stringList($fixtureIdentity['expectedFiles'] ?? []),
                'observedFiles' => self::stringList($fixtureIdentity['observedFiles'] ?? []),
                'files' => $files,
            ],
            'packageFeatureCoverage' => self::packageFeatureCoverageSignatureSnapshot($coverage),
        ];
    }

    /**
     * @param array<string, mixed> $coverage
     * @return array<string, mixed>
     */
    private static function packageFeatureCoverageSignatureSnapshot(array $coverage): array
    {
        $snapshot = [
            'kind' => (string) ($coverage['kind'] ?? ''),
        ];
        foreach (self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_COVERAGE as $key => $_expected) {
            $snapshot[$key] = $coverage[$key] ?? null;
        }

        return $snapshot;
    }

    private static function canonicalJson(mixed $value): string
    {
        $json = json_encode(
            self::canonicalValue($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
        if (!is_string($json)) {
            throw new \RuntimeException('Unable to encode package feature signature payload.');
        }

        return $json;
    }

    private static function canonicalValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(static fn (mixed $item): mixed => self::canonicalValue($item), $value);
        }

        $normalized = [];
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        foreach ($keys as $key) {
            $normalized[(string) $key] = self::canonicalValue($value[$key]);
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function filesByBasename(string $directory, string $extension): array
    {
        $files = [];
        foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.' . $extension) ?: [] as $path) {
            $files[basename($path, '.' . $extension)] = $path;
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function fixtureIdentity(string $directory): array
    {
        $observedFiles = [];
        foreach (['epub', 'native'] as $extension) {
            foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.' . $extension) ?: [] as $path) {
                $observedFiles[basename($path)] = $path;
            }
        }
        ksort($observedFiles, SORT_STRING);

        $files = [];
        $missingFiles = [];
        $changedFiles = [];
        foreach (self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES as $relativePath => $expected) {
            $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath;
            $present = is_file($path);
            $actualSha256 = $present ? hash_file('sha256', $path) : false;
            $size = $present ? filesize($path) : false;
            $actualBytes = is_int($size) ? $size : null;
            $sha256 = is_string($actualSha256) ? $actualSha256 : null;
            $matches = $present
                && $sha256 === $expected['sha256']
                && $actualBytes === $expected['bytes'];

            if (!$present) {
                $missingFiles[] = $relativePath;
            } elseif (!$matches) {
                $changedFiles[] = $relativePath;
            }

            $files[] = [
                'path' => $relativePath,
                'present' => $present,
                'sha256' => $sha256,
                'expectedSha256' => $expected['sha256'],
                'bytes' => $actualBytes,
                'expectedBytes' => $expected['bytes'],
                'matchesExpected' => $matches,
            ];
        }

        $unexpectedFiles = array_values(array_diff(array_keys($observedFiles), array_keys(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES)));
        sort($unexpectedFiles, SORT_STRING);

        $issues = [];
        if (count($observedFiles) !== count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES)) {
            $issues[] = 'fixture-file-count-does-not-match-expected-snapshot';
        }
        if ($missingFiles !== []) {
            $issues[] = 'missing-expected-fixture-files';
        }
        if ($unexpectedFiles !== []) {
            $issues[] = 'unexpected-fixture-files';
        }
        if ($changedFiles !== []) {
            $issues[] = 'fixture-hash-or-byte-count-mismatch';
        }

        return [
            'kind' => 'static-checked-in-current-epub-fixture-identity',
            'expectedFileCount' => count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES),
            'observedFileCount' => count($observedFiles),
            'expectedFiles' => array_keys(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES),
            'observedFiles' => array_keys($observedFiles),
            'files' => $files,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-epub-fixture-identity' : 'invalid-checked-in-current-epub-fixture-identity',
                'issues' => $issues,
                'missingFiles' => $missingFiles,
                'unexpectedFiles' => $unexpectedFiles,
                'changedFiles' => $changedFiles,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function notEvaluatedFixtureIdentity(): array
    {
        return [
            'kind' => 'static-checked-in-current-epub-fixture-identity',
            'expectedFileCount' => count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES),
            'observedFileCount' => 0,
            'expectedFiles' => array_keys(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES),
            'observedFiles' => [],
            'files' => [],
            'validation' => [
                'status' => 'not-evaluated-source-directory-unavailable',
                'issues' => ['source-directory-unavailable'],
                'missingFiles' => [],
                'unexpectedFiles' => [],
                'changedFiles' => [],
            ],
        ];
    }

    /**
     * @return array{ok: bool, package: ?EpubPackage, error: ?string}
     */
    private function readPackage(string $path): array
    {
        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new \RuntimeException("Unable to read EPUB fixture '{$path}'.");
            }

            return ['ok' => true, 'package' => EpubPackage::fromString($bytes), 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'package' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function packageSummary(string $fixture, EpubPackage $package): array
    {
        $assets = $package->assetSummary();
        $metadata = $package->metadata();
        $manifestItems = $package->manifestItems();
        $manifestCoverage = self::manifestItemCoverageSummary($manifestItems);
        $resourceKinds = $package->manifestResourceKinds();
        $navigation = $package->navigation();
        $navigationSections = $package->navigationSections();
        $guideReferences = $package->guideReferences();
        $manifestFallbacks = $package->manifestFallbacks();
        $navigationSectionTypes = [];
        $landmarkEntryCount = 0;
        $pageListEntryCount = 0;
        $auxiliaryNavigationEntryCount = 0;

        foreach ($navigationSections as $section) {
            $types = is_array($section['types'] ?? null) ? $section['types'] : [];
            $entries = is_array($section['entries'] ?? null) ? $section['entries'] : [];
            foreach ($types as $type) {
                if (!is_string($type) || $type === '') {
                    continue;
                }
                $navigationSectionTypes[$type] = true;
                if ($type === 'landmarks') {
                    $landmarkEntryCount += count($entries);
                } elseif ($type === 'page-list') {
                    $pageListEntryCount += count($entries);
                } elseif ($type !== 'toc') {
                    $auxiliaryNavigationEntryCount += count($entries);
                }
            }
        }
        $navigationSectionTypes = array_keys($navigationSectionTypes);
        sort($navigationSectionTypes, SORT_STRING);

        return [
            'fixture' => $fixture,
            'opfPart' => $package->opfPartName(),
            'metadataTitle' => is_string($metadata['title'] ?? null) ? $metadata['title'] : '',
            'metadataLanguage' => is_string($metadata['language'] ?? null) ? $metadata['language'] : '',
            'metadataCreatorCount' => is_array($metadata['creators'] ?? null) ? count($metadata['creators']) : 0,
            'packageLinkCount' => count($package->packageLinks()),
            'packageLinkRelCounts' => self::packageLinkRelCounts($package->packageLinks()),
            'guideReferenceCount' => count($guideReferences),
            'guideReferenceTypeCounts' => self::guideReferenceTypeCounts($guideReferences),
            'manifestItemCount' => count($manifestItems),
            'manifestMediaTypeCounts' => $manifestCoverage['mediaTypeCounts'],
            'manifestPropertyCounts' => $manifestCoverage['propertyCounts'],
            'manifestResourceKindCounts' => is_array($resourceKinds['kindCounts'] ?? null) ? $resourceKinds['kindCounts'] : [],
            'remoteResourceManifestItemCount' => $manifestCoverage['remoteResourceItemCount'],
            'externalManifestItemCount' => $manifestCoverage['externalItemCount'],
            'missingLocalManifestItemCount' => $manifestCoverage['missingLocalItemCount'],
            'manifestFallbackItemCount' => (int) ($manifestFallbacks['itemCount'] ?? 0),
            'manifestFallbackCount' => (int) ($manifestFallbacks['fallbackCount'] ?? 0),
            'resolvedManifestFallbackCount' => (int) ($manifestFallbacks['resolvedFallbackCount'] ?? 0),
            'usableManifestFallbackCount' => (int) ($manifestFallbacks['usableFallbackCount'] ?? 0),
            'missingManifestFallbackCount' => (int) ($manifestFallbacks['missingFallbackCount'] ?? 0),
            'readingOrderCount' => count($package->readingOrder()),
            'xhtmlAssetCount' => count($assets['xhtmlParts']),
            'imageAssetCount' => count($assets['imageParts']),
            'stylesheetAssetCount' => count($assets['stylesheetParts']),
            'navigationType' => is_array($navigation) ? (string) ($navigation['type'] ?? '') : null,
            'navigationEntryCount' => is_array($navigation) && is_array($navigation['entries'] ?? null) ? count($navigation['entries']) : 0,
            'navigationSectionCount' => count($navigationSections),
            'navigationSectionTypes' => $navigationSectionTypes,
            'landmarkEntryCount' => $landmarkEntryCount,
            'pageListEntryCount' => $pageListEntryCount,
            'auxiliaryNavigationEntryCount' => $auxiliaryNavigationEntryCount,
            'coverImagePart' => $assets['coverImagePart'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $summaries
     * @return array<string, mixed>
     */
    private static function packageFeatureCoverage(array $summaries): array
    {
        $coverage = self::emptyPackageFeatureCoverage();
        $coverage['fixtureCount'] = count($summaries);
        $metadataLanguageCounts = [];
        $navigationTypeCounts = [];
        $manifestMediaTypeCounts = [];
        $manifestPropertyCounts = [];
        $manifestResourceKindCounts = [];
        $guideReferenceTypeCounts = [];
        $packageLinkRelCounts = [];
        $navigationSectionTypes = [];
        $fixtureFeatureSignatures = [];

        foreach ($summaries as $summary) {
            $fixture = is_string($summary['fixture'] ?? null) ? $summary['fixture'] : '';
            $language = is_string($summary['metadataLanguage'] ?? null) ? $summary['metadataLanguage'] : '';
            if ($language !== '') {
                $metadataLanguageCounts[$language] = (int) ($metadataLanguageCounts[$language] ?? 0) + 1;
            }

            $navigationType = is_string($summary['navigationType'] ?? null) ? $summary['navigationType'] : '';
            if ($navigationType !== '') {
                $navigationTypeCounts[$navigationType] = (int) ($navigationTypeCounts[$navigationType] ?? 0) + 1;
            }

            foreach (is_array($summary['manifestMediaTypeCounts'] ?? null) ? $summary['manifestMediaTypeCounts'] : [] as $mediaType => $count) {
                if (is_string($mediaType) && $mediaType !== '') {
                    $manifestMediaTypeCounts[$mediaType] = (int) ($manifestMediaTypeCounts[$mediaType] ?? 0) + (int) $count;
                }
            }

            foreach (is_array($summary['manifestPropertyCounts'] ?? null) ? $summary['manifestPropertyCounts'] : [] as $property => $count) {
                if (is_string($property) && $property !== '') {
                    $manifestPropertyCounts[$property] = (int) ($manifestPropertyCounts[$property] ?? 0) + (int) $count;
                }
            }

            foreach (is_array($summary['manifestResourceKindCounts'] ?? null) ? $summary['manifestResourceKindCounts'] : [] as $kind => $count) {
                if (is_string($kind) && $kind !== '') {
                    $manifestResourceKindCounts[$kind] = (int) ($manifestResourceKindCounts[$kind] ?? 0) + (int) $count;
                }
            }

            foreach (is_array($summary['navigationSectionTypes'] ?? null) ? $summary['navigationSectionTypes'] : [] as $type) {
                if (is_string($type) && $type !== '') {
                    $navigationSectionTypes[$type] = true;
                }
            }

            foreach (is_array($summary['guideReferenceTypeCounts'] ?? null) ? $summary['guideReferenceTypeCounts'] : [] as $type => $count) {
                if (is_string($type) && $type !== '') {
                    $guideReferenceTypeCounts[$type] = (int) ($guideReferenceTypeCounts[$type] ?? 0) + (int) $count;
                }
            }

            foreach (is_array($summary['packageLinkRelCounts'] ?? null) ? $summary['packageLinkRelCounts'] : [] as $rel => $count) {
                if (is_string($rel) && $rel !== '') {
                    $packageLinkRelCounts[$rel] = (int) ($packageLinkRelCounts[$rel] ?? 0) + (int) $count;
                }
            }

            if ($fixture !== '') {
                $fixtureFeatureSignatures[$fixture] = self::fixtureFeatureSignature($summary);
            }
            if ($fixture !== '' && (int) ($summary['guideReferenceCount'] ?? 0) > 0) {
                $coverage['fixturesWithGuideReferences'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['packageLinkCount'] ?? 0) > 0) {
                $coverage['fixturesWithPackageLinks'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['metadataCreatorCount'] ?? 0) > 0) {
                $coverage['fixturesWithCreators'][] = $fixture;
            }
            if ($fixture !== '' && is_string($summary['coverImagePart'] ?? null) && (string) $summary['coverImagePart'] !== '') {
                $coverage['fixturesWithCoverImagePart'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['imageAssetCount'] ?? 0) > 0) {
                $coverage['fixturesWithImages'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['stylesheetAssetCount'] ?? 0) > 0) {
                $coverage['fixturesWithStylesheets'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['landmarkEntryCount'] ?? 0) > 0) {
                $coverage['fixturesWithLandmarks'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['pageListEntryCount'] ?? 0) > 0) {
                $coverage['fixturesWithPageLists'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['auxiliaryNavigationEntryCount'] ?? 0) > 0) {
                $coverage['fixturesWithAuxiliaryNavigation'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['remoteResourceManifestItemCount'] ?? 0) > 0) {
                $coverage['fixturesWithRemoteManifestResources'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['externalManifestItemCount'] ?? 0) > 0) {
                $coverage['fixturesWithExternalManifestItems'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['missingLocalManifestItemCount'] ?? 0) > 0) {
                $coverage['fixturesWithMissingLocalManifestItems'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['manifestFallbackCount'] ?? 0) > 0) {
                $coverage['fixturesWithManifestFallbacks'][] = $fixture;
            }

            $coverage['totals']['metadataCreators'] += (int) ($summary['metadataCreatorCount'] ?? 0);
            $coverage['totals']['manifestItems'] += (int) ($summary['manifestItemCount'] ?? 0);
            $coverage['totals']['readingOrderItems'] += (int) ($summary['readingOrderCount'] ?? 0);
            $coverage['totals']['xhtmlAssets'] += (int) ($summary['xhtmlAssetCount'] ?? 0);
            $coverage['totals']['imageAssets'] += (int) ($summary['imageAssetCount'] ?? 0);
            $coverage['totals']['stylesheetAssets'] += (int) ($summary['stylesheetAssetCount'] ?? 0);
            $coverage['totals']['navigationEntries'] += (int) ($summary['navigationEntryCount'] ?? 0);
            $coverage['totals']['landmarkEntries'] += (int) ($summary['landmarkEntryCount'] ?? 0);
            $coverage['totals']['pageListEntries'] += (int) ($summary['pageListEntryCount'] ?? 0);
            $coverage['totals']['auxiliaryNavigationEntries'] += (int) ($summary['auxiliaryNavigationEntryCount'] ?? 0);
            $coverage['totals']['packageLinks'] += (int) ($summary['packageLinkCount'] ?? 0);
            $coverage['totals']['guideReferences'] += (int) ($summary['guideReferenceCount'] ?? 0);
            $coverage['totals']['remoteResourceManifestItems'] += (int) ($summary['remoteResourceManifestItemCount'] ?? 0);
            $coverage['totals']['externalManifestItems'] += (int) ($summary['externalManifestItemCount'] ?? 0);
            $coverage['totals']['missingLocalManifestItems'] += (int) ($summary['missingLocalManifestItemCount'] ?? 0);
            $coverage['totals']['manifestFallbackItems'] += (int) ($summary['manifestFallbackItemCount'] ?? 0);
            $coverage['totals']['manifestFallbacks'] += (int) ($summary['manifestFallbackCount'] ?? 0);
            $coverage['totals']['resolvedManifestFallbacks'] += (int) ($summary['resolvedManifestFallbackCount'] ?? 0);
            $coverage['totals']['usableManifestFallbacks'] += (int) ($summary['usableManifestFallbackCount'] ?? 0);
            $coverage['totals']['missingManifestFallbacks'] += (int) ($summary['missingManifestFallbackCount'] ?? 0);
        }

        ksort($metadataLanguageCounts, SORT_STRING);
        ksort($navigationTypeCounts, SORT_STRING);
        ksort($manifestMediaTypeCounts, SORT_STRING);
        ksort($manifestPropertyCounts, SORT_STRING);
        ksort($manifestResourceKindCounts, SORT_STRING);
        $coverage['metadataLanguageCounts'] = $metadataLanguageCounts;
        $coverage['navigationTypeCounts'] = $navigationTypeCounts;
        $coverage['manifestMediaTypeCounts'] = $manifestMediaTypeCounts;
        $coverage['manifestPropertyCounts'] = $manifestPropertyCounts;
        $coverage['manifestResourceKindCounts'] = $manifestResourceKindCounts;
        ksort($guideReferenceTypeCounts, SORT_STRING);
        $coverage['guideReferenceTypeCounts'] = $guideReferenceTypeCounts;
        ksort($packageLinkRelCounts, SORT_STRING);
        $coverage['packageLinkRelCounts'] = $packageLinkRelCounts;
        $coverage['navigationSectionTypes'] = array_keys($navigationSectionTypes);
        sort($coverage['navigationSectionTypes'], SORT_STRING);
        ksort($fixtureFeatureSignatures, SORT_STRING);
        $coverage['fixtureFeatureSignatures'] = $fixtureFeatureSignatures;

        return $coverage;
    }

    /**
     * @param array<string, mixed> $summary
     * @return array{navigationType: string, navigationSectionTypes: list<string>, manifestResourceKindCounts: array<string, int>, guideReferenceTypeCounts: array<string, int>, packageLinkRelCounts: array<string, int>, coverImagePartPresent: bool}
     */
    private static function fixtureFeatureSignature(array $summary): array
    {
        return [
            'navigationType' => is_string($summary['navigationType'] ?? null) ? $summary['navigationType'] : '',
            'navigationSectionTypes' => self::stringList($summary['navigationSectionTypes'] ?? []),
            'manifestResourceKindCounts' => self::intCountMap($summary['manifestResourceKindCounts'] ?? []),
            'guideReferenceTypeCounts' => self::intCountMap($summary['guideReferenceTypeCounts'] ?? []),
            'packageLinkRelCounts' => self::intCountMap($summary['packageLinkRelCounts'] ?? []),
            'coverImagePartPresent' => is_string($summary['coverImagePart'] ?? null) && $summary['coverImagePart'] !== '',
        ];
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $items[] = $item;
            }
        }
        sort($items, SORT_STRING);

        return array_values(array_unique($items));
    }

    /**
     * @return array<string, int>
     */
    private static function intCountMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $counts = [];
        foreach ($value as $key => $count) {
            if (is_string($key) && $key !== '') {
                $counts[$key] = (int) $count;
            }
        }
        ksort($counts, SORT_STRING);

        return $counts;
    }

    /**
     * @param list<array<string, mixed>> $links
     * @return array<string, int>
     */
    private static function packageLinkRelCounts(array $links): array
    {
        $counts = [];
        foreach ($links as $link) {
            foreach (is_array($link['rel'] ?? null) ? $link['rel'] : [] as $rel) {
                if (!is_string($rel) || $rel === '') {
                    continue;
                }
                $counts[$rel] = (int) ($counts[$rel] ?? 0) + 1;
            }
        }
        ksort($counts, SORT_STRING);

        return $counts;
    }

    /**
     * @param list<array<string, mixed>> $references
     * @return array<string, int>
     */
    private static function guideReferenceTypeCounts(array $references): array
    {
        $counts = [];
        foreach ($references as $reference) {
            $type = is_string($reference['type'] ?? null) ? strtolower(trim($reference['type'])) : '';
            if ($type === '') {
                continue;
            }
            $counts[$type] = (int) ($counts[$type] ?? 0) + 1;
        }
        ksort($counts, SORT_STRING);

        return $counts;
    }

    /**
     * @param array<string, mixed> $counts
     */
    private static function formatCounts(array $counts): string
    {
        if ($counts === []) {
            return 'none';
        }

        ksort($counts, SORT_STRING);
        $parts = [];
        foreach ($counts as $key => $count) {
            if (is_string($key) && $key !== '') {
                $parts[] = $key . ':' . (int) $count;
            }
        }

        return $parts === [] ? 'none' : implode(',', $parts);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array{mediaTypeCounts: array<string, int>, propertyCounts: array<string, int>, remoteResourceItemCount: int, externalItemCount: int, missingLocalItemCount: int}
     */
    private static function manifestItemCoverageSummary(array $items): array
    {
        $mediaTypeCounts = [];
        $propertyCounts = [];
        $remoteResourceItemCount = 0;
        $externalItemCount = 0;
        $missingLocalItemCount = 0;

        foreach ($items as $item) {
            $mediaType = self::manifestItemMediaType($item);
            if ($mediaType !== '') {
                $mediaTypeCounts[$mediaType] = (int) ($mediaTypeCounts[$mediaType] ?? 0) + 1;
            }

            foreach (is_array($item['properties'] ?? null) ? $item['properties'] : [] as $property) {
                if (!is_string($property) || $property === '') {
                    continue;
                }
                $property = strtolower($property);
                $propertyCounts[$property] = (int) ($propertyCounts[$property] ?? 0) + 1;
                if ($property === 'remote-resources') {
                    ++$remoteResourceItemCount;
                }
            }

            $external = ($item['external'] ?? false) === true;
            if ($external) {
                ++$externalItemCount;
            }
            if (!$external && ($item['exists'] ?? true) !== true) {
                ++$missingLocalItemCount;
            }
        }

        ksort($mediaTypeCounts, SORT_STRING);
        ksort($propertyCounts, SORT_STRING);

        return [
            'mediaTypeCounts' => $mediaTypeCounts,
            'propertyCounts' => $propertyCounts,
            'remoteResourceItemCount' => $remoteResourceItemCount,
            'externalItemCount' => $externalItemCount,
            'missingLocalItemCount' => $missingLocalItemCount,
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function manifestItemMediaType(array $item): string
    {
        $mediaType = is_string($item['mediaTypeBase'] ?? null)
            ? $item['mediaTypeBase']
            : (is_string($item['mediaType'] ?? null) ? $item['mediaType'] : '');

        return strtolower(trim(explode(';', $mediaType, 2)[0]));
    }

    /**
     * @return array{ok: bool, document: ?AstNode, error: ?string}
     */
    private function readEpub(string $path): array
    {
        try {
            return ['ok' => true, 'document' => (new EpubReader())->readEpubFile($path), 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'document' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, document: ?AstNode, error: ?string}
     */
    private function readNative(string $path): array
    {
        try {
            $native = file_get_contents($path);
            if (!is_string($native)) {
                throw new \RuntimeException("Unable to read native fixture '{$path}'.");
            }

            return ['ok' => true, 'document' => (new NativeReader())->read($native), 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'document' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
    }

    /**
     * @return array{type:string, attrs:array<string, mixed>, children:list<array<string, mixed>>}
     */
    public function normalizedDocument(AstNode $document): array
    {
        return $this->normalizedNode($document);
    }

    /**
     * @return array{type:string, attrs:array<string, mixed>, children:list<array<string, mixed>>}
     */
    private function normalizedNode(AstNode $node): array
    {
        $attrs = [];
        foreach ($node->attrs as $key => $value) {
            $key = (string) $key;
            if ($node->type === 'document' && $key === 'meta') {
                continue;
            }
            if (self::isIgnoredAttrKey($key)) {
                continue;
            }
            if ($key === 'text' && in_array($node->type, ['plain', 'paragraph', 'heading', 'table_cell', 'term'], true)) {
                continue;
            }
            if ($key === 'attributes' && $value === []) {
                continue;
            }

            $normalizedValue = $this->normalizedValue($value);
            if (in_array($key, ['attributes', 'htmlAttributes'], true) && $normalizedValue === []) {
                continue;
            }
            if ($normalizedValue === [] || $normalizedValue === null || $normalizedValue === '') {
                continue;
            }
            $attrs[$key] = $normalizedValue;
        }
        ksort($attrs, SORT_STRING);

        return [
            'type' => $node->type,
            'attrs' => $attrs,
            'children' => $this->normalizedChildren($node->children),
        ];
    }

    /**
     * @param list<AstNode> $children
     * @return list<array<string, mixed>>
     */
    private function normalizedChildren(array $children): array
    {
        $normalized = [];
        foreach ($children as $child) {
            $node = $this->normalizedNode($child);
            if ($this->isEmptyTableFootNode($node)) {
                continue;
            }
            $this->appendNormalizedChild($normalized, $node);
        }

        return $this->trimBoundaryWhitespaceText($normalized);
    }

    /**
     * @param list<array<string, mixed>> $normalized
     * @param array<string, mixed> $node
     */
    private function appendNormalizedChild(array &$normalized, array $node): void
    {
        $lastIndex = count($normalized) - 1;
        if ($lastIndex >= 0 && $this->isPlainTextNode($normalized[$lastIndex]) && $this->isPlainTextNode($node)) {
            $normalized[$lastIndex]['attrs']['text'] .= $node['attrs']['text'];
            return;
        }

        $normalized[] = $node;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<array<string, mixed>>
     */
    private function trimBoundaryWhitespaceText(array $nodes): array
    {
        while ($nodes !== [] && $this->isWhitespaceOnlyPlainTextNode($nodes[0])) {
            array_shift($nodes);
        }

        while ($nodes !== [] && $this->isWhitespaceOnlyPlainTextNode($nodes[count($nodes) - 1])) {
            array_pop($nodes);
        }

        return array_values($nodes);
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isPlainTextNode(array $node): bool
    {
        $attrs = $node['attrs'] ?? null;

        return ($node['type'] ?? null) === 'text'
            && is_array($attrs)
            && array_keys($attrs) === ['text']
            && is_string($attrs['text'])
            && ($node['children'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isWhitespaceOnlyPlainTextNode(array $node): bool
    {
        if (!$this->isPlainTextNode($node)) {
            return false;
        }

        return trim((string) $node['attrs']['text']) === '';
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isEmptyTableFootNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'table_foot'
            && ($node['attrs'] ?? null) === []
            && ($node['children'] ?? null) === [];
    }

    private function normalizedValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace("\t", ' ', $value);
        }
        if (is_float($value)) {
            return round($value, 12);
        }
        if ($value instanceof AstNode) {
            return $this->normalizedNode($value);
        }
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            if ($this->isAstNodeList($value)) {
                return $this->normalizedChildren($value);
            }

            return array_map(fn (mixed $item): mixed => $this->normalizedValue($item), $value);
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (self::isIgnoredAttrKey((string) $key)) {
                continue;
            }
            $normalized[(string) $key] = $this->normalizedValue($item);
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param list<mixed> $value
     */
    private function isAstNodeList(array $value): bool
    {
        foreach ($value as $item) {
            if (!$item instanceof AstNode) {
                return false;
            }
        }

        return $value !== [];
    }

    private static function isIgnoredAttrKey(string $key): bool
    {
        return isset(self::IGNORED_ATTRS[$key])
            || str_starts_with($key, 'epub')
            || self::isNativeProvenanceAttrKey($key);
    }

    private static function isNativeProvenanceAttrKey(string $key): bool
    {
        return str_starts_with($key, 'native')
            || str_ends_with($key, 'Native')
            || str_ends_with($key, 'Natives')
            || str_ends_with($key, 'Constructor')
            || str_ends_with($key, 'Constructors')
            || in_array($key, ['constructor', 'pandocApiVersion'], true);
    }

    private function firstDifference(mixed $epub, mixed $native, string $path = 'root'): ?string
    {
        if (gettype($epub) !== gettype($native)) {
            return "{$path} type " . gettype($epub) . ' vs ' . gettype($native);
        }
        if (!is_array($epub)) {
            return $epub === $native ? null : "{$path} value " . self::shortJson($epub) . ' vs ' . self::shortJson($native);
        }

        $epubKeys = array_keys($epub);
        $nativeKeys = array_keys($native);
        if ($epubKeys !== $nativeKeys) {
            return "{$path} keys " . self::shortJson($epubKeys) . ' vs ' . self::shortJson($nativeKeys);
        }

        foreach ($epubKeys as $key) {
            $difference = $this->firstDifference($epub[$key], $native[$key], $path . '.' . $key);
            if ($difference !== null) {
                return $difference;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function mismatchCategories(string $difference): array
    {
        $lower = strtolower($difference);
        $categories = [];
        if (str_contains($lower, '.children keys')) {
            $categories[] = 'child-count-or-inline-granularity';
        }
        if (str_contains($lower, '.attrs keys') || str_contains($lower, '.attrs.')) {
            $categories[] = 'attribute-shape';
        }
        if (str_contains($lower, 'raw_html') || str_contains($lower, 'div') || str_contains($lower, 'span')) {
            $categories[] = 'xhtml-structure-shape';
        }
        if (str_contains($lower, 'image') || str_contains($lower, 'url') || str_contains($lower, 'alt')) {
            $categories[] = 'image-shape';
        }
        if (str_contains($lower, 'table') || str_contains($lower, 'row') || str_contains($lower, 'cell')) {
            $categories[] = 'table-shape';
        }
        if (str_contains($lower, '.type')) {
            $categories[] = 'node-type';
        }
        if (str_contains($lower, ' value ')) {
            $categories[] = 'scalar-value';
        }
        if ($categories === []) {
            $categories[] = 'uncategorized-normalized-ast-drift';
        }

        return array_values(array_unique($categories));
    }

    /**
     * @return list<string>
     */
    private function topTypeSequence(AstNode $document): array
    {
        return array_map(static fn (AstNode $child): string => $child->type, $document->children);
    }

    /**
     * @param array<string, array{category: string, count: int, examples: list<string>}> $categoryCounts
     */
    private function addCategory(array &$categoryCounts, string $category, string $fixture, int $maxExamples): void
    {
        if (!isset($categoryCounts[$category])) {
            $categoryCounts[$category] = ['category' => $category, 'count' => 0, 'examples' => []];
        }

        ++$categoryCounts[$category]['count'];
        if (count($categoryCounts[$category]['examples']) < $maxExamples) {
            $categoryCounts[$category]['examples'][] = $fixture;
        }
    }

    private static function packageAcceptanceStatus(int $comparedEpubCount, int $packageParseFailureCount, int $readerParseFailureCount): string
    {
        if ($comparedEpubCount === 0) {
            return 'not-evaluated-no-epub-files';
        }
        if ($packageParseFailureCount > 0 || $readerParseFailureCount > 0) {
            return 'blocked-by-package-or-reader-parse-failures';
        }

        return 'package-and-reader-acceptance-observed-not-full-epub-parity';
    }

    private static function astParityStatus(int $comparedPairCount, int $parseFailureCount, int $mismatchCount): string
    {
        if ($comparedPairCount === 0) {
            return 'not-evaluated-no-native-pairs';
        }
        if ($parseFailureCount > 0) {
            return 'blocked-by-parse-failures';
        }
        if ($mismatchCount > 0) {
            return 'normalized-ast-mismatches-observed';
        }

        return 'normalized-ast-equality-observed-not-runner-parity';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function orderedRemainingGaps(
        bool $directoryPresent,
        int $comparedEpubCount,
        int $packageParseFailureCount,
        int $readerParseFailureCount,
        int $comparedPairCount,
        int $astParseFailureCount,
        int $astMatchCount,
        int $astMismatchCount
    ): array {
        if (!$directoryPresent) {
            $packageEvidence = 'EPUB directory absent; package comparison did not run';
            $astEvidence = 'EPUB directory absent; native AST comparison did not run';
        } else {
            $packageEvidence = "compared epubs={$comparedEpubCount}; package failures={$packageParseFailureCount}; reader failures={$readerParseFailureCount}";
            $astEvidence = "native pairs={$comparedPairCount}; parse failures={$astParseFailureCount}; normalized matches={$astMatchCount}; normalized mismatches={$astMismatchCount}";
        }

        $packageCovered = $directoryPresent
            && $comparedEpubCount > 0
            && $packageParseFailureCount === 0
            && $readerParseFailureCount === 0;
        $astCovered = $directoryPresent
            && $comparedPairCount > 0
            && $astParseFailureCount === 0
            && $astMismatchCount === 0
            && $astMatchCount === $comparedPairCount;

        return [
            [
                'rank' => 1,
                'id' => 'upstream-epub-package-and-reader-acceptance',
                'status' => !$directoryPresent ? 'not-evaluated' : ($packageCovered ? 'covered-by-current-package-evidence' : 'open'),
                'currentEvidence' => $packageEvidence,
                'evidenceRequired' => 'Parse every upstream EPUB package with both the package preflight reader and local EPUB reader, keeping package and reader failures at zero.',
            ],
            [
                'rank' => 2,
                'id' => 'upstream-epub-native-ast-equality',
                'status' => !$directoryPresent ? 'not-evaluated' : ($astCovered ? 'covered-by-current-normalized-ast-evidence' : 'open'),
                'currentEvidence' => $astEvidence,
                'evidenceRequired' => 'Compare local PHP EPUB reader output against same-basename upstream .native goldens, keeping parse failures and normalized AST mismatches at zero.',
            ],
            [
                'rank' => 3,
                'id' => 'upstream-haskell-epub-reader-runner-results',
                'status' => 'open',
                'currentEvidence' => 'Structured planned-not-run Cabal exe:pandoc command evidence is present; this harness does not run the upstream Haskell process itself.',
                'evidenceRequired' => 'Record reproducible upstream Tests.Readers.EPUB runner results when a Haskell runner is available.',
            ],
        ];
    }

    /**
     * @param list<string> $lines
     * @param array<string, mixed> $report
     * @return list<string>
     */
    private static function appendOrderedRemainingGaps(array $lines, array $report): array
    {
        $gaps = $report['orderedRemainingGaps'] ?? [];
        if (!is_array($gaps) || $gaps === []) {
            return $lines;
        }

        $lines[] = 'orderedRemainingGaps:';
        foreach ($gaps as $gap) {
            if (!is_array($gap)) {
                continue;
            }
            $lines[] = sprintf(
                '%d. %s [%s]: %s',
                (int) ($gap['rank'] ?? 0),
                (string) ($gap['id'] ?? 'unknown'),
                (string) ($gap['status'] ?? 'unknown'),
                (string) ($gap['currentEvidence'] ?? '')
            );
        }

        return $lines;
    }

    private static function percent(int $count, int $total): ?float
    {
        return $total === 0 ? null : round(($count / $total) * 100, 2);
    }

    private static function formatPercent(mixed $percent): string
    {
        return is_int($percent) || is_float($percent) ? number_format((float) $percent, 2) . '%' : 'n/a';
    }

    private static function shortJson(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return gettype($value);
        }

        return strlen($json) > 240 ? substr($json, 0, 237) . '...' : $json;
    }
}
