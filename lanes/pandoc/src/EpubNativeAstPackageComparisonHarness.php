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
    private const PACKAGE_FEATURE_SIGNATURE_SCOPE = 'checked-in-current-upstream-epub-reader-52-fixture-snapshot';
    private const CHECKED_IN_CURRENT_PACKAGE_FEATURE_SIGNATURE_SHA256 = '9295fcd24b8c5f90ae5a713d06b89f795342d8b58eb59b9e9a3422ba34292ccd';
    private const CURRENT_NATIVE_AST_SIGNATURE_KIND = 'checked-in-current-epub-normalized-native-ast-signature';
    private const CURRENT_NATIVE_AST_SIGNATURE_ALGORITHM = 'sha256-canonical-json-v1';
    private const CURRENT_NATIVE_AST_SIGNATURE_SCOPE = 'checked-in-current-upstream-epub-reader-52-fixture-normalized-ast-snapshot';
    private const CHECKED_IN_CURRENT_NATIVE_AST_SIGNATURE_SHA256 = 'cdc2e1818a4ae5b069164130f90b1f4285ff3ca252ec12e0cdcf27444c17e1ae';
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
    private const RUNNER_RESULT_ARTIFACT_KIND = 'upstream-epub-native-package-runner-result-artifact';
    private const RUNNER_TRANSCRIPT_KIND = 'upstream-epub-native-package-runner-transcript';
    private const RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION = 2;

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
        'epubMediaResourcePolicy' => true,
        'epubMediaResourceDirectory' => true,
        'epubMediaResourceCount' => true,
        'epubMediaResourceDiagnostics' => true,
        'epubTocResources' => true,
        'epubTocEntryCount' => true,
        'epubTocEntries' => true,
        'epubLandmarkEntryCount' => true,
        'epubLandmarkEntries' => true,
        'captionBlocks' => true,
        'captionSource' => true,
        'columnSources' => true,
        'columnSpecs' => true,
        'header' => true,
        'rowHeadColumns' => true,
        'tableGeometry' => true,
        'sourceFormat' => true,
    ];

    /**
     * @var array<string, array{sha256: string, bytes: int}>
     */
    private const CHECKED_IN_CURRENT_FIXTURE_IDENTITIES = [
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
        'cross-spine-internal-links.epub' => [
            'sha256' => '10356bf8205f5eab35bb851bf155cdd279b23804740017cf59bb28d4f10a07e5',
            'bytes' => 1701,
        ],
        'cross-spine-internal-links.native' => [
            'sha256' => '55c6607d1baee634d2e06404a7c1b7c5271880b20d86c9ccf06d097045bb7f09',
            'bytes' => 743,
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
        'metadata-record-remote-nav.epub' => [
            'sha256' => '74f7d7ecaa89dea3d0085f1208a78abf951de22e057245d321036bcd4b35ffe8',
            'bytes' => 1944,
        ],
        'metadata-record-remote-nav.native' => [
            'sha256' => '9a0dffbca5d0b8a52ac7d12e570a0e671d6892f54298d396d808a49940f31bad',
            'bytes' => 844,
        ],
        'metadata-search-link-semantics.epub' => [
            'sha256' => '23de9c3376484fc45494dc5aee3c6da0dfc6e9b6ff8197933cec1e4d399434e4',
            'bytes' => 1868,
        ],
        'metadata-search-link-semantics.native' => [
            'sha256' => '8e78383af179a9392bdc99d397444133b2423163663cfdc41e4e24583c68cd48',
            'bytes' => 1861,
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
        'xhtml-ruby-table-mark.epub' => [
            'sha256' => '19e2ed10e4aeafe94970c38606939b9dfbd561f15c7f71e4ee904425f9b13b4d',
            'bytes' => 1876,
        ],
        'xhtml-ruby-table-mark.native' => [
            'sha256' => 'ec35ac3bda86e5242aa9ceb8b5614be45f689b643c2620508687f55daa68a4b8',
            'bytes' => 2302,
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


    /**
     * @var array<string, mixed>
     */
    private const CHECKED_IN_CURRENT_PACKAGE_FEATURE_COVERAGE = [
        'kind' => 'epub-package-feature-coverage',
        'fixtureCount' => 52,
        'opfPartNameCounts' => [
            '/EPUB/package.opf' => 39,
            '/EPUB/wasteland.opf' => 1,
            '/OEBPS/content.opf' => 3,
            '/OPS/book/package.opf' => 3,
            '/OPS/package.opf' => 6,
        ],
        'metadataLanguageCounts' => [
            'de-DE' => 3,
            'en' => 45,
            'en-GB' => 1,
            'en-US' => 2,
            'fr' => 1,
        ],
        'fixturesWithCreators' => [
            'bindings-collections-sidecars',
            'content-image-nav-media',
            'cross-spine-internal-links',
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
            'metadata-record-remote-nav',
            'metadata-search-link-semantics',
            'missing-media-overlay',
            'nested-path-media-metadata',
            'nested-rootfile-nonlinear-spine',
            'package-spine-nav-media-metadata',
            'parent-relative-nav',
            'spine-fallback-resource',
            'title-page-guide-media-metadata',
            'wasteland',
            'xhtml-ruby-table-mark',
            'xhtml-semantics-spine',
        ],
        'navigationTypeCounts' => [
            'nav' => 47,
            'ncx' => 4,
        ],
        'spineLinearStateCounts' => [
            'linear' => 68,
            'non-linear' => 14,
        ],
        'spinePageSpreadPlacementCounts' => [
            'left' => 2,
            'right' => 3,
        ],
        'manifestMediaTypeCounts' => [
            'application/javascript' => 1,
            'application/json' => 6,
            'application/octet-stream' => 1,
            'application/pdf' => 1,
            'application/smil+xml' => 1,
            'application/x-bound-widget' => 1,
            'application/x-dtbncx+xml' => 6,
            'application/x-fallback-demo' => 2,
            'application/xhtml+xml' => 123,
            'audio/mpeg' => 4,
            'font/woff2' => 1,
            'image/gif' => 5,
            'image/jpeg' => 7,
            'image/png' => 9,
            'image/svg+xml' => 1,
            'text/css' => 23,
            'video/mp4' => 2,
        ],
        'manifestPropertyCounts' => [
            'cover-image' => 3,
            'mathml' => 2,
            'nav' => 47,
            'remote-resources' => 4,
            'rendition:layout-pre-paginated' => 1,
            'scripted' => 2,
            'svg' => 2,
            'switch' => 1,
        ],
        'manifestResourceKindCounts' => [
            'asset' => 11,
            'audio' => 4,
            'cover-image' => 3,
            'font' => 1,
            'image' => 18,
            'media-overlay' => 1,
            'navigation' => 53,
            'script' => 1,
            'style' => 23,
            'svg' => 1,
            'video' => 2,
            'xhtml' => 76,
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
            'text' => 9,
            'title-page' => 1,
            'toc' => 1,
        ],
        'packageLinkRelCounts' => [
            'alternate' => 2,
            'cc:attributionURL' => 1,
            'cc:license' => 2,
            'preview' => 3,
            'record' => 8,
            'search' => 1,
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
            'cross-spine-internal-links' => [
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
            'metadata-record-remote-nav' => [
                'navigationType' => 'nav',
                'navigationSectionTypes' => [
                    'landmarks',
                    'loi',
                    'page-list',
                    'toc',
                ],
                'manifestResourceKindCounts' => [
                    'asset' => 1,
                    'navigation' => 1,
                    'style' => 1,
                    'xhtml' => 1,
                ],
                'guideReferenceTypeCounts' => [
                    'text' => 1,
                ],
                'packageLinkRelCounts' => [
                    'alternate' => 1,
                    'preview' => 1,
                    'record' => 1,
                ],
                'coverImagePartPresent' => false,
            ],
            'metadata-search-link-semantics' => [
                'navigationType' => 'nav',
                'navigationSectionTypes' => [
                    'toc',
                ],
                'manifestResourceKindCounts' => [
                    'navigation' => 1,
                    'xhtml' => 1,
                ],
                'guideReferenceTypeCounts' => [],
                'packageLinkRelCounts' => [
                    'record' => 1,
                    'search' => 1,
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
            'xhtml-ruby-table-mark' => [
                'navigationType' => 'nav',
                'navigationSectionTypes' => [
                    'landmarks',
                    'toc',
                ],
                'manifestResourceKindCounts' => [
                    'asset' => 1,
                    'navigation' => 1,
                    'xhtml' => 1,
                ],
                'guideReferenceTypeCounts' => [
                    'text' => 1,
                ],
                'packageLinkRelCounts' => [
                    'preview' => 1,
                    'record' => 1,
                ],
                'coverImagePartPresent' => false,
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
            'metadata-record-remote-nav',
            'nav-ncx-linear-guide',
            'nested-path-media-metadata',
            'nested-rootfile-nonlinear-spine',
            'parent-relative-nav',
            'title-page-guide-media-metadata',
            'xhtml-ruby-table-mark',
            'xhtml-semantics-spine',
        ],
        'fixturesWithPackageLinks' => [
            'bindings-collections-sidecars',
            'manifest-href-encoding',
            'metadata-link-page-list-image',
            'metadata-record-remote-nav',
            'metadata-search-link-semantics',
            'nav-ncx-linear-guide',
            'nested-path-media-metadata',
            'title-page-guide-media-metadata',
            'wasteland',
            'xhtml-ruby-table-mark',
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
            'metadata-record-remote-nav',
            'nav-ncx-linear-guide',
            'nested-path-media-metadata',
            'nested-rootfile-nonlinear-spine',
            'package-spine-nav-media-metadata',
            'parent-relative-nav',
            'spine-fallback-resource',
            'title-page-guide-media-metadata',
            'wasteland',
            'xhtml-ruby-table-mark',
            'xhtml-semantics-spine',
        ],
        'fixturesWithPageLists' => [
            'content-image-nav-media',
            'manifest-href-encoding',
            'metadata-link-page-list-image',
            'metadata-record-remote-nav',
            'nested-path-media-metadata',
            'package-spine-nav-media-metadata',
            'page-list-navigation',
            'spine-fallback-resource',
            'title-page-guide-media-metadata',
        ],
        'fixturesWithAuxiliaryNavigation' => [
            'audio-navigation',
            'auxiliary-lot-guide-index',
            'metadata-record-remote-nav',
            'nested-path-media-metadata',
            'page-list-navigation',
            'title-page-guide-media-metadata',
            'video-navigation',
        ],
        'fixturesWithRemoteManifestResources' => [
            'media-manifest-mix',
            'metadata-record-remote-nav',
            'nested-path-media-metadata',
            'remote-manifest-resource',
        ],
        'fixturesWithExternalManifestItems' => [
            'media-manifest-mix',
            'metadata-record-remote-nav',
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
            'metadata-record-remote-nav',
            'nav-ncx-linear-guide',
            'nested-path-media-metadata',
            'spine-fallback-resource',
            'title-page-guide-media-metadata',
            'video-manifest-resource',
            'video-navigation',
            'xhtml-ruby-table-mark',
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
            'metadata-record-remote-nav',
            'nav-ncx-linear-guide',
            'nested-path-media-metadata',
            'title-page-guide-media-metadata',
            'video-manifest-resource',
            'video-navigation',
            'xhtml-ruby-table-mark',
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
            'xhtml-ruby-table-mark',
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
            'metadataCreators' => 49,
            'manifestItems' => 194,
            'readingOrderItems' => 82,
            'spinePageSpreadItems' => 5,
            'xhtmlAssets' => 123,
            'imageAssets' => 22,
            'stylesheetAssets' => 20,
            'navigationEntries' => 140,
            'landmarkEntries' => 22,
            'pageListEntries' => 12,
            'auxiliaryNavigationEntries' => 7,
            'packageLinks' => 13,
            'guideReferences' => 19,
            'remoteResourceManifestItems' => 4,
            'externalManifestItems' => 4,
            'missingLocalManifestItems' => 1,
            'manifestFallbackItems' => 13,
            'manifestFallbacks' => 5,
            'resolvedManifestFallbacks' => 5,
            'usableManifestFallbacks' => 5,
            'missingManifestFallbacks' => 8,
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

    /**
     * @param array{limit?: int, maxExamples?: int, repoRoot?: string, runnerResultArtifact?: string} $options
     * @return array<string, mixed>
     */
    public function run(string $epubDirectory, array $options = []): array
    {
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $maxExamples = max(0, (int) ($options['maxExamples'] ?? self::DEFAULT_MAX_EXAMPLES));
        $repoRoot = is_string($options['repoRoot'] ?? null) && $options['repoRoot'] !== ''
            ? rtrim((string) $options['repoRoot'], DIRECTORY_SEPARATOR)
            : (getcwd() ?: '');
        $runnerResultArtifact = is_string($options['runnerResultArtifact'] ?? null)
            ? (string) $options['runnerResultArtifact']
            : null;
        if ($runnerResultArtifact === '') {
            throw new \InvalidArgumentException('Runner result artifact must not be empty');
        }

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
        $runnerEvidence = $runnerResultArtifact === null
            ? self::runnerNotRunEvidence()
            : $this->runnerResultArtifactEvidence($runnerResultArtifact, $repoRoot);
        $runnerResultCovered = self::runnerResultArtifactEvidenceIsValid($runnerEvidence);

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
            'runnerEvidence' => $runnerEvidence,
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
                $normalizedAstMismatchCount,
                $runnerResultCovered
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
                    self::formatBooleanFlag($runner['executed'] ?? null)
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
            $nonLinearSpineFixtures = is_array($featureCoverage['fixturesWithNonLinearSpineItems'] ?? null)
                ? $featureCoverage['fixturesWithNonLinearSpineItems']
                : [];
            $spinePageSpreadFixtures = is_array($featureCoverage['fixturesWithSpinePageSpreadItems'] ?? null)
                ? $featureCoverage['fixturesWithSpinePageSpreadItems']
                : [];
            $guideReferenceTypeCounts = is_array($featureCoverage['guideReferenceTypeCounts'] ?? null)
                ? $featureCoverage['guideReferenceTypeCounts']
                : [];
            $packageLinkRelCounts = is_array($featureCoverage['packageLinkRelCounts'] ?? null)
                ? $featureCoverage['packageLinkRelCounts']
                : [];
            $encryptionRoleCounts = is_array($featureCoverage['encryptionRoleCounts'] ?? null)
                ? $featureCoverage['encryptionRoleCounts']
                : [];
            $opfPartNameCounts = is_array($featureCoverage['opfPartNameCounts'] ?? null)
                ? $featureCoverage['opfPartNameCounts']
                : [];
            $encryptionFixtures = is_array($featureCoverage['fixturesWithEncryption'] ?? null)
                ? $featureCoverage['fixturesWithEncryption']
                : [];
            $obfuscatedFontFixtures = is_array($featureCoverage['fixturesWithObfuscatedFonts'] ?? null)
                ? $featureCoverage['fixturesWithObfuscatedFonts']
                : [];
            $blockedEncryptedByteFixtures = is_array($featureCoverage['fixturesWithBlockedEncryptedByteExposures'] ?? null)
                ? $featureCoverage['fixturesWithBlockedEncryptedByteExposures']
                : [];
            $manifestFallbackItemFixtures = is_array($featureCoverage['fixturesWithManifestFallbackItems'] ?? null)
                ? $featureCoverage['fixturesWithManifestFallbackItems']
                : [];
            $resolvedManifestFallbackFixtures = is_array($featureCoverage['fixturesWithResolvedManifestFallbacks'] ?? null)
                ? $featureCoverage['fixturesWithResolvedManifestFallbacks']
                : [];
            $usableManifestFallbackFixtures = is_array($featureCoverage['fixturesWithUsableManifestFallbacks'] ?? null)
                ? $featureCoverage['fixturesWithUsableManifestFallbacks']
                : [];
            $missingManifestFallbackFixtures = is_array($featureCoverage['fixturesWithMissingManifestFallbacks'] ?? null)
                ? $featureCoverage['fixturesWithMissingManifestFallbacks']
                : [];
            $mediaOverlayFixtures = is_array($featureCoverage['fixturesWithMediaOverlays'] ?? null)
                ? $featureCoverage['fixturesWithMediaOverlays']
                : [];
            $resolvedMediaOverlayFixtures = is_array($featureCoverage['fixturesWithResolvedMediaOverlays'] ?? null)
                ? $featureCoverage['fixturesWithResolvedMediaOverlays']
                : [];
            $collectionFixtures = is_array($featureCoverage['fixturesWithCollections'] ?? null)
                ? $featureCoverage['fixturesWithCollections']
                : [];
            $bindingFixtures = is_array($featureCoverage['fixturesWithBindings'] ?? null)
                ? $featureCoverage['fixturesWithBindings']
                : [];
            $ocfSidecarFixtures = is_array($featureCoverage['fixturesWithOcfSidecars'] ?? null)
                ? $featureCoverage['fixturesWithOcfSidecars']
                : [];
            $collectionRoleCounts = is_array($featureCoverage['collectionRoleCounts'] ?? null)
                ? $featureCoverage['collectionRoleCounts']
                : [];
            $collectionLinkRelCounts = is_array($featureCoverage['collectionLinkRelCounts'] ?? null)
                ? $featureCoverage['collectionLinkRelCounts']
                : [];
            $bindingMediaTypeCounts = is_array($featureCoverage['bindingMediaTypeCounts'] ?? null)
                ? $featureCoverage['bindingMediaTypeCounts']
                : [];
            $ocfSidecarKindCounts = is_array($featureCoverage['ocfSidecarKindCounts'] ?? null)
                ? $featureCoverage['ocfSidecarKindCounts']
                : [];
            $lines[] = sprintf(
                'packageFeatureCoverage: fixtures=%d nav=%d ncx=%d covers=%d landmarks=%d pageLists=%d auxiliaryNav=%d metadataCreators=%d manifestItems=%d readingOrderItems=%d spineLinear=%s nonLinearSpineFixtures=%d spinePageSpread=%s pageSpreadFixtures=%d imageAssets=%d stylesheetAssets=%d resourceKinds=%s guideRefTypes=%s packageLinkRels=%s remoteManifest=%d externalManifest=%d missingLocalManifest=%d manifestFallbackItems=%d manifestFallbacks=%d resolvedFallbacks=%d usableFallbacks=%d missingFallbacks=%d mediaOverlayFixtures=%d resolvedMediaOverlayFixtures=%d mediaOverlays=%d resolvedMediaOverlays=%d mediaOverlayTextTargets=%d mediaOverlayAudioTargets=%d mediaOverlayDurations=%d encryptionFixtures=%d obfuscatedFontFixtures=%d blockedEncryptedByteExposureFixtures=%d encryptionItems=%d obfuscatedFonts=%d blockedEncryptedByteExposures=%d encryptionDiagnostics=%d encryptionRoles=%s collectionFixtures=%d collections=%d collectionLinks=%d collectionRoles=%s collectionLinkRels=%s bindingFixtures=%d bindings=%d bindingResolvedHandlers=%d bindingParams=%d bindingMediaTypes=%s ocfSidecarFixtures=%d ocfSidecars=%d ocfSidecarKinds=%s opfParts=%s',
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
                self::formatCounts(is_array($featureCoverage['spineLinearStateCounts'] ?? null)
                    ? $featureCoverage['spineLinearStateCounts']
                    : []),
                count($nonLinearSpineFixtures),
                self::formatCounts(is_array($featureCoverage['spinePageSpreadPlacementCounts'] ?? null)
                    ? $featureCoverage['spinePageSpreadPlacementCounts']
                    : []),
                count($spinePageSpreadFixtures),
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
                count($manifestFallbackItemFixtures),
                (int) ($totals['manifestFallbacks'] ?? 0),
                count($resolvedManifestFallbackFixtures),
                count($usableManifestFallbackFixtures),
                count($missingManifestFallbackFixtures),
                count($mediaOverlayFixtures),
                count($resolvedMediaOverlayFixtures),
                (int) ($totals['mediaOverlays'] ?? 0),
                (int) ($totals['resolvedMediaOverlays'] ?? 0),
                (int) ($totals['mediaOverlayTextLocalTargets'] ?? 0),
                (int) ($totals['mediaOverlayAudioLocalTargets'] ?? 0),
                (int) ($totals['mediaOverlayDurations'] ?? 0),
                count($encryptionFixtures),
                count($obfuscatedFontFixtures),
                count($blockedEncryptedByteFixtures),
                (int) ($totals['encryptionItems'] ?? 0),
                (int) ($totals['obfuscatedFonts'] ?? 0),
                (int) ($totals['blockedEncryptedByteExposures'] ?? 0),
                (int) ($totals['encryptionDiagnostics'] ?? 0),
                self::formatCounts($encryptionRoleCounts),
                count($collectionFixtures),
                (int) ($totals['collections'] ?? 0),
                (int) ($totals['collectionLinks'] ?? 0),
                self::formatCounts($collectionRoleCounts),
                self::formatCounts($collectionLinkRelCounts),
                count($bindingFixtures),
                (int) ($totals['bindingItems'] ?? 0),
                (int) ($totals['bindingResolvedHandlers'] ?? 0),
                (int) ($totals['bindingMediaTypeParameters'] ?? 0),
                self::formatCounts($bindingMediaTypeCounts),
                count($ocfSidecarFixtures),
                (int) ($totals['ocfSidecars'] ?? 0),
                self::formatCounts($ocfSidecarKindCounts),
                self::formatCounts($opfPartNameCounts)
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
                self::formatBooleanFlag($runner['executed'] ?? null)
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
     * @param array<string, mixed> $report
     */
    public static function hasRunnerResultArtifactEvidence(array $report): bool
    {
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];
        $artifact = is_array($runner['resultArtifact'] ?? null) ? $runner['resultArtifact'] : [];
        $binding = is_array($runner['upstreamBinding'] ?? null) ? $runner['upstreamBinding'] : [];
        $target = is_array($runner['target'] ?? null) ? $runner['target'] : [];
        $snapshot = is_array($runner['checkedInSnapshot'] ?? null) ? $runner['checkedInSnapshot'] : [];
        $transcripts = is_array($runner['transcripts'] ?? null) ? $runner['transcripts'] : [];

        return self::runnerResultArtifactEvidenceIsValid($runner)
            && ($runner['scope'] ?? null) === 'upstream-haskell-runner'
            && ($runner['runner'] ?? null) === 'Cabal-built Pandoc EPUB to native executable'
            && is_array($runner['command'] ?? null)
            && self::canonicalValue($runner['command'] ?? null) === self::canonicalValue(self::runnerFutureCommands()[2])
            && ($artifact['kind'] ?? null) === self::RUNNER_RESULT_ARTIFACT_KIND
            && ($artifact['present'] ?? null) === true
            && is_string($artifact['sha256'] ?? null)
            && is_int($artifact['bytes'] ?? null)
            && ($binding['name'] ?? null) === 'jgm/pandoc'
            && ($binding['expectedCommit'] ?? null) === self::EXPECTED_UPSTREAM_COMMIT
            && ($binding['observedCommit'] ?? null) === self::EXPECTED_UPSTREAM_COMMIT
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
            && ($snapshot['packageFeatureSignature'] ?? null) === self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_SIGNATURE_SHA256
            && ($snapshot['nativeAstSignature'] ?? null) === self::CHECKED_IN_CURRENT_NATIVE_AST_SIGNATURE_SHA256
            && ($runner['futureCommands'] ?? null) === self::runnerFutureCommands()
            && ($runner['requiredTranscripts'] ?? null) === self::RUNNER_REQUIRED_TRANSCRIPTS
            && ($runner['requiredArtifacts'] ?? null) === self::RUNNER_REQUIRED_ARTIFACTS
            && count($transcripts) === count(self::RUNNER_REQUIRED_TRANSCRIPTS);
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
            'orderedRemainingGaps' => self::orderedRemainingGaps(false, 0, 0, 0, 0, 0, 0, 0, false),
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
    private function runnerResultArtifactEvidence(string $runnerResultArtifact, string $repoRoot): array
    {
        $path = self::absoluteRunnerResultArtifact($runnerResultArtifact, $repoRoot);
        $artifact = self::runnerResultArtifactFileEvidence($path, $repoRoot);
        $transcripts = self::runnerTranscriptFileEvidenceList($repoRoot);
        $issues = [];
        $payload = [];

        if (($artifact['present'] ?? false) !== true) {
            $issues[] = 'missing-runner-result-artifact';
        } else {
            try {
                $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) {
                    $issues[] = 'invalid-runner-result-artifact-json';
                } else {
                    $payload = $decoded;
                }
            } catch (\JsonException) {
                $issues[] = 'invalid-runner-result-artifact-json';
            }
        }

        $upstream = is_array($payload['upstream'] ?? null) ? $payload['upstream'] : [];
        $target = is_array($payload['target'] ?? null) ? $payload['target'] : [];
        $command = is_array($payload['command'] ?? null) ? $payload['command'] : null;
        $expectedCommand = self::runnerFutureCommands()[2];
        $expectedTarget = self::runnerTarget();
        $expectedFixtureBasenames = self::expectedCheckedInCurrentPairNames();
        $expectedGeneratedNativeManifest = self::expectedGeneratedNativeManifest();
        $observedFixtureBasenames = self::stringList($payload['fixtureBasenames'] ?? ($payload['fixtures'] ?? []));
        $observedGeneratedNativeManifest = self::runnerGeneratedNativeManifestRecords($payload['generatedNativeManifest'] ?? []);
        $observedTranscriptPaths = self::orderedStringList($payload['transcriptPaths'] ?? []);
        $observedTranscriptRecords = self::runnerTranscriptRecords($payload['transcripts'] ?? []);
        if ($observedTranscriptPaths === [] && $observedTranscriptRecords !== []) {
            $observedTranscriptPaths = self::runnerTranscriptRecordPaths($observedTranscriptRecords);
        }
        $runnerExecuted = ($payload['runnerExecuted'] ?? $payload['executed'] ?? null) === true;
        $exitCode = is_int($payload['exitCode'] ?? null) ? (int) $payload['exitCode'] : null;
        $fixtureCount = is_int($payload['fixtureCount'] ?? null) ? (int) $payload['fixtureCount'] : null;
        $generatedNativeCount = is_int($payload['generatedNativeCount'] ?? null) ? (int) $payload['generatedNativeCount'] : null;
        $failedCount = is_int($payload['failedCount'] ?? null) ? (int) $payload['failedCount'] : null;

        if ($payload !== []) {
            if (($payload['schemaVersion'] ?? null) !== self::RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION) {
                $issues[] = 'runner-result-schema-version-mismatch';
            }
            if (($payload['runner'] ?? null) !== 'Cabal-built Pandoc EPUB to native executable') {
                $issues[] = 'runner-result-runner-name-mismatch';
            }
            if (!$runnerExecuted) {
                $issues[] = 'runner-result-executed-flag-missing-or-false';
            }
            if (($upstream['name'] ?? null) !== 'jgm/pandoc' || ($upstream['commit'] ?? null) !== self::EXPECTED_UPSTREAM_COMMIT) {
                $issues[] = 'runner-result-upstream-commit-mismatch';
            }
            if (self::canonicalValue($target) !== self::canonicalValue($expectedTarget)) {
                $issues[] = 'runner-result-target-mismatch';
            }
            if (self::canonicalValue($command) !== self::canonicalValue($expectedCommand)) {
                $issues[] = 'runner-result-command-mismatch';
            }
            if ($exitCode !== 0) {
                $issues[] = 'runner-result-exit-code-nonzero';
            }
            if (
                $fixtureCount !== count($expectedFixtureBasenames)
                || $generatedNativeCount !== count($expectedGeneratedNativeManifest)
                || $failedCount !== 0
            ) {
                $issues[] = 'runner-result-counts-mismatch';
            }
            if ($observedFixtureBasenames !== $expectedFixtureBasenames) {
                $issues[] = 'runner-result-fixture-basenames-mismatch';
            }
            if ($observedGeneratedNativeManifest !== $expectedGeneratedNativeManifest) {
                $issues[] = 'runner-result-generated-native-manifest-mismatch';
            }
            if ($observedTranscriptPaths !== self::RUNNER_REQUIRED_TRANSCRIPTS) {
                $issues[] = 'runner-result-transcript-paths-mismatch';
            }
            foreach (self::runnerTranscriptValidationIssues($observedTranscriptRecords, $transcripts) as $issue) {
                $issues[] = $issue;
            }
        }

        $issues = array_values(array_unique($issues));

        return [
            'runner' => 'Cabal-built Pandoc EPUB to native executable',
            'scope' => 'upstream-haskell-runner',
            'status' => $issues === [] ? 'completed' : 'invalid',
            'executed' => $runnerExecuted,
            'command' => $command,
            'resultArtifact' => $artifact,
            'commandPlanStatus' => $issues === [] ? 'runner-result-artifact-validated' : 'runner-result-artifact-invalid',
            'upstreamBinding' => [
                'name' => 'jgm/pandoc',
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'observedCommit' => is_string($upstream['commit'] ?? null) ? $upstream['commit'] : null,
                'executableTarget' => self::RUNNER_CABAL_TARGET,
                'fixtureDirectory' => self::RUNNER_FIXTURE_DIRECTORY,
            ],
            'target' => [
                'cabalTarget' => is_string($target['cabalTarget'] ?? null) ? $target['cabalTarget'] : null,
                'inputFormat' => is_string($target['inputFormat'] ?? null) ? $target['inputFormat'] : null,
                'outputFormat' => is_string($target['outputFormat'] ?? null) ? $target['outputFormat'] : null,
                'fixtureDirectory' => is_string($target['fixtureDirectory'] ?? null) ? $target['fixtureDirectory'] : null,
                'fixtureBasenames' => self::stringList($target['fixtureBasenames'] ?? []),
            ],
            'checkedInSnapshot' => self::runnerCheckedInSnapshot(),
            'expected' => [
                'schemaVersion' => self::RUNNER_RESULT_ARTIFACT_SCHEMA_VERSION,
                'runner' => 'Cabal-built Pandoc EPUB to native executable',
                'fixtureCount' => count($expectedFixtureBasenames),
                'generatedNativeCount' => count($expectedGeneratedNativeManifest),
                'failedCount' => 0,
                'fixtureBasenames' => $expectedFixtureBasenames,
                'generatedNativeManifest' => $expectedGeneratedNativeManifest,
                'transcriptPaths' => self::RUNNER_REQUIRED_TRANSCRIPTS,
                'transcripts' => self::runnerTranscriptRecordsFromEvidence($transcripts),
                'command' => $expectedCommand,
            ],
            'observed' => [
                'schemaVersion' => $payload['schemaVersion'] ?? null,
                'runner' => $payload['runner'] ?? null,
                'exitCode' => $exitCode,
                'fixtureCount' => $fixtureCount,
                'generatedNativeCount' => $generatedNativeCount,
                'failedCount' => $failedCount,
                'fixtureBasenames' => $observedFixtureBasenames,
                'generatedNativeManifest' => $observedGeneratedNativeManifest,
                'transcriptPaths' => $observedTranscriptPaths,
                'transcripts' => $observedTranscriptRecords,
            ],
            'futureCommands' => self::runnerFutureCommands(),
            'requiredTranscripts' => self::RUNNER_REQUIRED_TRANSCRIPTS,
            'requiredArtifacts' => self::RUNNER_REQUIRED_ARTIFACTS,
            'transcripts' => $transcripts,
            'validation' => [
                'status' => $issues === []
                    ? 'valid-upstream-epub-native-package-runner-result-artifact'
                    : 'invalid-upstream-epub-native-package-runner-result-artifact',
                'issues' => $issues,
            ],
            'claim' => $issues === []
                ? 'A supplied upstream EPUB-to-native runner result artifact matches the pinned executable runner evidence contract.'
                : 'The supplied upstream EPUB-to-native runner result artifact did not satisfy the pinned executable runner evidence contract.',
        ];
    }

    /**
     * @return array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}
     */
    private static function runnerResultArtifactFileEvidence(string $path, string $repoRoot): array
    {
        $present = is_file($path);
        $sha256 = $present ? hash_file('sha256', $path) : null;
        $bytes = $present ? filesize($path) : null;

        return [
            'kind' => self::RUNNER_RESULT_ARTIFACT_KIND,
            'path' => self::displayRunnerPath($path, $repoRoot),
            'present' => $present,
            'sha256' => is_string($sha256) ? $sha256 : null,
            'bytes' => is_int($bytes) ? $bytes : null,
        ];
    }

    /**
     * @return list<array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}>
     */
    private static function runnerTranscriptFileEvidenceList(string $repoRoot): array
    {
        $files = [];
        foreach (self::RUNNER_REQUIRED_TRANSCRIPTS as $path) {
            $files[] = self::runnerTranscriptFileEvidence($repoRoot, $path);
        }

        return $files;
    }

    /**
     * @return array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}
     */
    private static function runnerTranscriptFileEvidence(string $repoRoot, string $relativePath): array
    {
        $path = self::absoluteRunnerTranscriptPath($repoRoot, $relativePath);
        $present = is_file($path);
        $sha256 = $present ? hash_file('sha256', $path) : null;
        $bytes = $present ? filesize($path) : null;

        return [
            'kind' => self::RUNNER_TRANSCRIPT_KIND,
            'path' => self::displayRunnerPath($path, $repoRoot),
            'present' => $present,
            'sha256' => is_string($sha256) ? $sha256 : null,
            'bytes' => is_int($bytes) ? $bytes : null,
        ];
    }

    private static function absoluteRunnerResultArtifact(string $path, string $repoRoot): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }
        if ($repoRoot === '') {
            return $path;
        }

        return $repoRoot . DIRECTORY_SEPARATOR . trim($path, DIRECTORY_SEPARATOR);
    }

    private static function absoluteRunnerTranscriptPath(string $repoRoot, string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }
        if ($repoRoot === '') {
            return str_replace('/', DIRECTORY_SEPARATOR, $path);
        }

        return $repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    private static function displayRunnerPath(string $path, string $repoRoot): string
    {
        if ($repoRoot !== '' && str_starts_with($path, $repoRoot . DIRECTORY_SEPARATOR)) {
            return substr($path, strlen($repoRoot) + 1);
        }

        return $path;
    }

    /**
     * @return list<array{fixture: string, path: string, sha256: ?string, bytes: ?int}>
     */
    private static function runnerGeneratedNativeManifestRecords(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $records = [];
        foreach ($value as $key => $item) {
            if (!is_array($item)) {
                continue;
            }
            $fixture = is_string($item['fixture'] ?? null)
                ? $item['fixture']
                : (is_string($key) ? $key : '');
            $records[] = [
                'fixture' => $fixture,
                'path' => is_string($item['path'] ?? null) ? $item['path'] : '',
                'sha256' => is_string($item['sha256'] ?? null) ? $item['sha256'] : null,
                'bytes' => is_int($item['bytes'] ?? null) ? $item['bytes'] : null,
            ];
        }
        usort(
            $records,
            static fn (array $left, array $right): int => ($left['fixture'] <=> $right['fixture'])
                ?: ($left['path'] <=> $right['path'])
        );

        return $records;
    }

    /**
     * @return list<array{path: string, sha256: ?string, bytes: ?int}>
     */
    private static function runnerTranscriptRecords(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $records = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $records[] = [
                'path' => is_string($item['path'] ?? null) ? $item['path'] : '',
                'sha256' => is_string($item['sha256'] ?? null) ? $item['sha256'] : null,
                'bytes' => is_int($item['bytes'] ?? null) ? $item['bytes'] : null,
            ];
        }

        return $records;
    }

    /**
     * @param list<array{path: string, sha256: ?string, bytes: ?int}> $records
     * @return list<string>
     */
    private static function runnerTranscriptRecordPaths(array $records): array
    {
        return array_map(
            static fn (array $record): string => $record['path'],
            $records
        );
    }

    /**
     * @param list<array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}> $files
     * @return list<array{path: string, sha256: ?string, bytes: ?int}>
     */
    private static function runnerTranscriptRecordsFromEvidence(array $files): array
    {
        $records = [];
        foreach ($files as $file) {
            $records[] = [
                'path' => $file['path'],
                'sha256' => $file['sha256'],
                'bytes' => $file['bytes'],
            ];
        }

        return $records;
    }

    /**
     * @param list<array{path: string, sha256: ?string, bytes: ?int}> $observedRecords
     * @param list<array{kind: string, path: string, present: bool, sha256: ?string, bytes: ?int}> $files
     * @return list<string>
     */
    private static function runnerTranscriptValidationIssues(array $observedRecords, array $files): array
    {
        $issues = [];
        if ($observedRecords === []) {
            $issues[] = 'runner-result-transcript-records-missing';
        }
        if (self::runnerTranscriptRecordPaths($observedRecords) !== self::RUNNER_REQUIRED_TRANSCRIPTS) {
            $issues[] = 'runner-result-transcript-record-paths-mismatch';
        }

        $recordsByPath = [];
        foreach ($observedRecords as $record) {
            if (isset($recordsByPath[$record['path']])) {
                $issues[] = 'runner-result-transcript-record-paths-not-unique';
                continue;
            }
            $recordsByPath[$record['path']] = $record;
        }

        $filesByPath = [];
        foreach ($files as $file) {
            $filesByPath[$file['path']] = $file;
        }

        foreach (self::RUNNER_REQUIRED_TRANSCRIPTS as $path) {
            $file = $filesByPath[$path] ?? null;
            if (!is_array($file) || ($file['present'] ?? null) !== true) {
                $issues[] = 'runner-result-transcript-file-missing';
                continue;
            }

            $record = $recordsByPath[$path] ?? null;
            if (!is_array($record)) {
                $issues[] = 'runner-result-transcript-record-missing';
                continue;
            }
            if (($record['sha256'] ?? null) !== $file['sha256']) {
                $issues[] = 'runner-result-transcript-sha256-mismatch';
            }
            if (($record['bytes'] ?? null) !== $file['bytes']) {
                $issues[] = 'runner-result-transcript-bytes-mismatch';
            }
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return list<array{fixture: string, path: string, sha256: string, bytes: int}>
     */
    private static function expectedGeneratedNativeManifest(): array
    {
        $records = [];
        foreach (self::expectedCheckedInCurrentPairNames() as $fixture) {
            $path = $fixture . '.native';
            $identity = self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES[$path] ?? null;
            if (!is_array($identity)) {
                continue;
            }
            $records[] = [
                'fixture' => $fixture,
                'path' => self::RUNNER_FIXTURE_DIRECTORY . '/' . $path,
                'sha256' => $identity['sha256'],
                'bytes' => $identity['bytes'],
            ];
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private static function runnerTarget(): array
    {
        return [
            'cabalTarget' => self::RUNNER_CABAL_TARGET,
            'inputFormat' => 'epub',
            'outputFormat' => self::RUNNER_OUTPUT_FORMAT,
            'fixtureDirectory' => self::RUNNER_FIXTURE_DIRECTORY,
            'fixtureBasenames' => self::expectedCheckedInCurrentPairNames(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function runnerCheckedInSnapshot(): array
    {
        return [
            'fixtureIdentityKind' => 'static-checked-in-current-epub-fixture-identity',
            'expectedFileCount' => count(self::CHECKED_IN_CURRENT_FIXTURE_IDENTITIES),
            'expectedPairCount' => count(self::expectedCheckedInCurrentPairNames()),
            'packageFeatureSignature' => self::CHECKED_IN_CURRENT_PACKAGE_FEATURE_SIGNATURE_SHA256,
            'nativeAstSignature' => self::CHECKED_IN_CURRENT_NATIVE_AST_SIGNATURE_SHA256,
        ];
    }

    /**
     * @param array<string, mixed> $runner
     */
    private static function runnerResultArtifactEvidenceIsValid(array $runner): bool
    {
        $validation = is_array($runner['validation'] ?? null) ? $runner['validation'] : [];

        return ($runner['status'] ?? null) === 'completed'
            && ($runner['executed'] ?? null) === true
            && ($runner['commandPlanStatus'] ?? null) === 'runner-result-artifact-validated'
            && ($validation['status'] ?? null) === 'valid-upstream-epub-native-package-runner-result-artifact'
            && ($validation['issues'] ?? null) === [];
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
            'target' => self::runnerTarget(),
            'checkedInSnapshot' => self::runnerCheckedInSnapshot(),
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
            'opfPartNameCounts' => [],
            'metadataLanguageCounts' => [],
            'fixturesWithCreators' => [],
            'navigationTypeCounts' => [],
            'spineLinearStateCounts' => [],
            'spinePageSpreadPlacementCounts' => [],
            'manifestMediaTypeCounts' => [],
            'manifestPropertyCounts' => [],
            'manifestResourceKindCounts' => [],
            'navigationSectionTypes' => [],
            'guideReferenceTypeCounts' => [],
            'packageLinkRelCounts' => [],
            'encryptionRoleCounts' => [],
            'collectionRoleCounts' => [],
            'collectionLinkRelCounts' => [],
            'bindingMediaTypeCounts' => [],
            'ocfSidecarKindCounts' => [],
            'fixtureFeatureSignatures' => [],
            'fixturesWithGuideReferences' => [],
            'fixturesWithPackageLinks' => [],
            'fixturesWithCoverImagePart' => [],
            'fixturesWithEncryption' => [],
            'fixturesWithObfuscatedFonts' => [],
            'fixturesWithBlockedEncryptedByteExposures' => [],
            'fixturesWithImages' => [],
            'fixturesWithStylesheets' => [],
            'fixturesWithLandmarks' => [],
            'fixturesWithPageLists' => [],
            'fixturesWithAuxiliaryNavigation' => [],
            'fixturesWithRemoteManifestResources' => [],
            'fixturesWithExternalManifestItems' => [],
            'fixturesWithMissingLocalManifestItems' => [],
            'fixturesWithManifestFallbackItems' => [],
            'fixturesWithManifestFallbacks' => [],
            'fixturesWithResolvedManifestFallbacks' => [],
            'fixturesWithUsableManifestFallbacks' => [],
            'fixturesWithMissingManifestFallbacks' => [],
            'fixturesWithMediaOverlays' => [],
            'fixturesWithResolvedMediaOverlays' => [],
            'fixturesWithMediaOverlayTextTargets' => [],
            'fixturesWithMediaOverlayAudioTargets' => [],
            'fixturesWithNonLinearSpineItems' => [],
            'fixturesWithSpinePageSpreadItems' => [],
            'fixturesWithCollections' => [],
            'fixturesWithBindings' => [],
            'fixturesWithOcfSidecars' => [],
            'totals' => [
                'metadataCreators' => 0,
                'manifestItems' => 0,
                'readingOrderItems' => 0,
                'spinePageSpreadItems' => 0,
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
        $spineItems = $package->readingOrder();
        $spineLinearStateCounts = [];
        $spinePageSpreadPlacementCounts = [];
        $spinePageSpreadItemCount = 0;
        foreach ($spineItems as $spineItem) {
            $state = (($spineItem['linear'] ?? true) === false) ? 'non-linear' : 'linear';
            $spineLinearStateCounts[$state] = (int) ($spineLinearStateCounts[$state] ?? 0) + 1;
            $pageSpread = is_string($spineItem['pageSpread'] ?? null) ? $spineItem['pageSpread'] : '';
            $pageSpreadProperties = is_array($spineItem['pageSpreadProperties'] ?? null)
                ? array_values($spineItem['pageSpreadProperties'])
                : [];
            if ($pageSpread !== '' || $pageSpreadProperties !== []) {
                ++$spinePageSpreadItemCount;
            }
            if ($pageSpread !== '') {
                $spinePageSpreadPlacementCounts[$pageSpread] = (int) ($spinePageSpreadPlacementCounts[$pageSpread] ?? 0) + 1;
            }
        }
        ksort($spineLinearStateCounts, SORT_STRING);
        ksort($spinePageSpreadPlacementCounts, SORT_STRING);
        $navigation = $package->navigation();
        $navigationSections = $package->navigationSections();
        $guideReferences = $package->guideReferences();
        $manifestFallbacks = $package->manifestFallbacks();
        $mediaOverlays = $package->mediaOverlays();
        $encryption = $package->encryption();
        $encryptionExposure = is_array($encryption['exposure'] ?? null) ? $encryption['exposure'] : [];
        $collections = self::collectionCoverageSummary($package->collections());
        $bindings = $package->bindings();
        $ocfSidecars = $package->ocfSidecars();
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
            'mediaOverlayCount' => (int) ($mediaOverlays['overlayCount'] ?? 0),
            'resolvedMediaOverlayCount' => (int) ($mediaOverlays['resolvedOverlayCount'] ?? 0),
            'missingMediaOverlayCount' => (int) ($mediaOverlays['missingOverlayCount'] ?? 0),
            'mediaOverlayReferencedContentItemCount' => (int) ($mediaOverlays['referencedContentItemCount'] ?? 0),
            'mediaOverlayTextLocalTargetCount' => (int) ($mediaOverlays['textLocalTargetCount'] ?? 0),
            'mediaOverlayAudioLocalTargetCount' => (int) ($mediaOverlays['audioLocalTargetCount'] ?? 0),
            'mediaOverlayDurationCount' => (int) ($mediaOverlays['durationCount'] ?? 0),
            'encryptionPresent' => ($encryption['present'] ?? false) === true,
            'encryptionItemCount' => is_array($encryption['items'] ?? null) ? count($encryption['items']) : 0,
            'obfuscatedFontCount' => is_array($encryption['obfuscatedFonts'] ?? null) ? count($encryption['obfuscatedFonts']) : 0,
            'blockedEncryptedByteExposureCount' => (int) ($encryptionExposure['blockedByteExposureCount'] ?? 0),
            'encryptionDiagnosticCount' => is_array($encryption['diagnostics'] ?? null) ? count($encryption['diagnostics']) : 0,
            'encryptionRoleCounts' => is_array($encryptionExposure['roleCounts'] ?? null) ? $encryptionExposure['roleCounts'] : [],
            'collectionCount' => $collections['collectionCount'],
            'collectionLinkCount' => $collections['collectionLinkCount'],
            'collectionRoleCounts' => $collections['collectionRoleCounts'],
            'collectionLinkRelCounts' => $collections['collectionLinkRelCounts'],
            'bindingCount' => (int) ($bindings['itemCount'] ?? 0),
            'bindingMediaTypeCounts' => self::bindingMediaTypeCounts($bindings),
            'bindingResolvedHandlerCount' => self::bindingResolvedHandlerCount($bindings),
            'bindingMediaTypeParameterCount' => (int) ($bindings['mediaTypeParameterCount'] ?? 0),
            'ocfSidecarCount' => (int) ($ocfSidecars['sidecarCount'] ?? 0),
            'ocfSidecarKindCounts' => self::ocfSidecarKindCounts($ocfSidecars),
            'readingOrderCount' => count($spineItems),
            'spineLinearStateCounts' => $spineLinearStateCounts,
            'spinePageSpreadItemCount' => $spinePageSpreadItemCount,
            'spinePageSpreadPlacementCounts' => $spinePageSpreadPlacementCounts,
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
     * @param list<array<string, mixed>> $collections
     * @return array{collectionCount: int, collectionLinkCount: int, collectionRoleCounts: array<string, int>, collectionLinkRelCounts: array<string, int>}
     */
    private static function collectionCoverageSummary(array $collections): array
    {
        $summary = [
            'collectionCount' => 0,
            'collectionLinkCount' => 0,
            'collectionRoleCounts' => [],
            'collectionLinkRelCounts' => [],
        ];
        self::appendCollectionCoverageSummary($collections, $summary);
        ksort($summary['collectionRoleCounts'], SORT_STRING);
        ksort($summary['collectionLinkRelCounts'], SORT_STRING);

        return $summary;
    }

    /**
     * @param list<array<string, mixed>> $collections
     * @param array{collectionCount: int, collectionLinkCount: int, collectionRoleCounts: array<string, int>, collectionLinkRelCounts: array<string, int>} $summary
     */
    private static function appendCollectionCoverageSummary(array $collections, array &$summary): void
    {
        foreach ($collections as $collection) {
            if (!is_array($collection)) {
                continue;
            }

            ++$summary['collectionCount'];
            foreach (is_array($collection['roleTokens'] ?? null) ? $collection['roleTokens'] : [] as $role) {
                if (is_string($role) && $role !== '') {
                    $summary['collectionRoleCounts'][$role] = (int) ($summary['collectionRoleCounts'][$role] ?? 0) + 1;
                }
            }

            foreach (is_array($collection['links'] ?? null) ? $collection['links'] : [] as $link) {
                if (!is_array($link)) {
                    continue;
                }

                ++$summary['collectionLinkCount'];
                foreach (is_array($link['rel'] ?? null) ? $link['rel'] : [] as $rel) {
                    if (is_string($rel) && $rel !== '') {
                        $summary['collectionLinkRelCounts'][$rel] = (int) ($summary['collectionLinkRelCounts'][$rel] ?? 0) + 1;
                    }
                }
            }

            self::appendCollectionCoverageSummary(
                is_array($collection['children'] ?? null) ? $collection['children'] : [],
                $summary
            );
        }
    }

    /**
     * @param array<string, mixed> $bindings
     * @return array<string, int>
     */
    private static function bindingMediaTypeCounts(array $bindings): array
    {
        $counts = [];
        foreach (is_array($bindings['items'] ?? null) ? $bindings['items'] : [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $mediaType = is_string($item['baseMediaType'] ?? null)
                ? $item['baseMediaType']
                : (is_string($item['mediaType'] ?? null) ? explode(';', $item['mediaType'], 2)[0] : '');
            $mediaType = strtolower(trim($mediaType));
            if ($mediaType !== '') {
                $counts[$mediaType] = (int) ($counts[$mediaType] ?? 0) + 1;
            }
        }
        ksort($counts, SORT_STRING);

        return $counts;
    }

    /**
     * @param array<string, mixed> $bindings
     */
    private static function bindingResolvedHandlerCount(array $bindings): int
    {
        $count = 0;
        foreach (is_array($bindings['items'] ?? null) ? $bindings['items'] : [] as $item) {
            if (is_array($item) && ($item['handlerExists'] ?? false) === true) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $ocfSidecars
     * @return array<string, int>
     */
    private static function ocfSidecarKindCounts(array $ocfSidecars): array
    {
        $counts = [];
        foreach (is_array($ocfSidecars['kinds'] ?? null) ? $ocfSidecars['kinds'] : [] as $kind) {
            if (is_string($kind) && $kind !== '') {
                $counts[$kind] = (int) ($counts[$kind] ?? 0) + 1;
            }
        }
        ksort($counts, SORT_STRING);

        return $counts;
    }

    /**
     * @param list<array<string, mixed>> $summaries
     * @return array<string, mixed>
     */
    private static function packageFeatureCoverage(array $summaries): array
    {
        $coverage = self::emptyPackageFeatureCoverage();
        $coverage['fixtureCount'] = count($summaries);
        $opfPartNameCounts = [];
        $metadataLanguageCounts = [];
        $navigationTypeCounts = [];
        $spineLinearStateCounts = [];
        $spinePageSpreadPlacementCounts = [];
        $manifestMediaTypeCounts = [];
        $manifestPropertyCounts = [];
        $manifestResourceKindCounts = [];
        $guideReferenceTypeCounts = [];
        $packageLinkRelCounts = [];
        $encryptionRoleCounts = [];
        $collectionRoleCounts = [];
        $collectionLinkRelCounts = [];
        $bindingMediaTypeCounts = [];
        $ocfSidecarKindCounts = [];
        $navigationSectionTypes = [];
        $fixtureFeatureSignatures = [];

        foreach ($summaries as $summary) {
            $fixture = is_string($summary['fixture'] ?? null) ? $summary['fixture'] : '';
            $opfPart = is_string($summary['opfPart'] ?? null) ? $summary['opfPart'] : '';
            if ($opfPart !== '') {
                $opfPartNameCounts[$opfPart] = (int) ($opfPartNameCounts[$opfPart] ?? 0) + 1;
            }

            $language = is_string($summary['metadataLanguage'] ?? null) ? $summary['metadataLanguage'] : '';
            if ($language !== '') {
                $metadataLanguageCounts[$language] = (int) ($metadataLanguageCounts[$language] ?? 0) + 1;
            }

            $navigationType = is_string($summary['navigationType'] ?? null) ? $summary['navigationType'] : '';
            if ($navigationType !== '') {
                $navigationTypeCounts[$navigationType] = (int) ($navigationTypeCounts[$navigationType] ?? 0) + 1;
            }

            foreach (is_array($summary['spineLinearStateCounts'] ?? null) ? $summary['spineLinearStateCounts'] : [] as $state => $count) {
                if (is_string($state) && $state !== '') {
                    $spineLinearStateCounts[$state] = (int) ($spineLinearStateCounts[$state] ?? 0) + (int) $count;
                }
            }
            foreach (is_array($summary['spinePageSpreadPlacementCounts'] ?? null) ? $summary['spinePageSpreadPlacementCounts'] : [] as $placement => $count) {
                if (is_string($placement) && $placement !== '') {
                    $spinePageSpreadPlacementCounts[$placement] = (int) ($spinePageSpreadPlacementCounts[$placement] ?? 0) + (int) $count;
                }
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
            foreach (is_array($summary['encryptionRoleCounts'] ?? null) ? $summary['encryptionRoleCounts'] : [] as $role => $count) {
                if (is_string($role) && $role !== '') {
                    $encryptionRoleCounts[$role] = (int) ($encryptionRoleCounts[$role] ?? 0) + (int) $count;
                }
            }
            foreach (is_array($summary['collectionRoleCounts'] ?? null) ? $summary['collectionRoleCounts'] : [] as $role => $count) {
                if (is_string($role) && $role !== '') {
                    $collectionRoleCounts[$role] = (int) ($collectionRoleCounts[$role] ?? 0) + (int) $count;
                }
            }
            foreach (is_array($summary['collectionLinkRelCounts'] ?? null) ? $summary['collectionLinkRelCounts'] : [] as $rel => $count) {
                if (is_string($rel) && $rel !== '') {
                    $collectionLinkRelCounts[$rel] = (int) ($collectionLinkRelCounts[$rel] ?? 0) + (int) $count;
                }
            }
            foreach (is_array($summary['bindingMediaTypeCounts'] ?? null) ? $summary['bindingMediaTypeCounts'] : [] as $mediaType => $count) {
                if (is_string($mediaType) && $mediaType !== '') {
                    $bindingMediaTypeCounts[$mediaType] = (int) ($bindingMediaTypeCounts[$mediaType] ?? 0) + (int) $count;
                }
            }
            foreach (is_array($summary['ocfSidecarKindCounts'] ?? null) ? $summary['ocfSidecarKindCounts'] : [] as $kind => $count) {
                if (is_string($kind) && $kind !== '') {
                    $ocfSidecarKindCounts[$kind] = (int) ($ocfSidecarKindCounts[$kind] ?? 0) + (int) $count;
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
            if ($fixture !== '' && (($summary['encryptionPresent'] ?? false) === true || (int) ($summary['encryptionItemCount'] ?? 0) > 0)) {
                $coverage['fixturesWithEncryption'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['obfuscatedFontCount'] ?? 0) > 0) {
                $coverage['fixturesWithObfuscatedFonts'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['blockedEncryptedByteExposureCount'] ?? 0) > 0) {
                $coverage['fixturesWithBlockedEncryptedByteExposures'][] = $fixture;
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
            if ($fixture !== '' && (int) ($summary['manifestFallbackItemCount'] ?? 0) > 0) {
                $coverage['fixturesWithManifestFallbackItems'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['manifestFallbackCount'] ?? 0) > 0) {
                $coverage['fixturesWithManifestFallbacks'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['resolvedManifestFallbackCount'] ?? 0) > 0) {
                $coverage['fixturesWithResolvedManifestFallbacks'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['usableManifestFallbackCount'] ?? 0) > 0) {
                $coverage['fixturesWithUsableManifestFallbacks'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['missingManifestFallbackCount'] ?? 0) > 0) {
                $coverage['fixturesWithMissingManifestFallbacks'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['mediaOverlayCount'] ?? 0) > 0) {
                $coverage['fixturesWithMediaOverlays'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['resolvedMediaOverlayCount'] ?? 0) > 0) {
                $coverage['fixturesWithResolvedMediaOverlays'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['mediaOverlayTextLocalTargetCount'] ?? 0) > 0) {
                $coverage['fixturesWithMediaOverlayTextTargets'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['mediaOverlayAudioLocalTargetCount'] ?? 0) > 0) {
                $coverage['fixturesWithMediaOverlayAudioTargets'][] = $fixture;
            }
            $spineLinearCounts = is_array($summary['spineLinearStateCounts'] ?? null) ? $summary['spineLinearStateCounts'] : [];
            if ($fixture !== '' && (int) ($spineLinearCounts['non-linear'] ?? 0) > 0) {
                $coverage['fixturesWithNonLinearSpineItems'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['spinePageSpreadItemCount'] ?? 0) > 0) {
                $coverage['fixturesWithSpinePageSpreadItems'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['collectionCount'] ?? 0) > 0) {
                $coverage['fixturesWithCollections'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['bindingCount'] ?? 0) > 0) {
                $coverage['fixturesWithBindings'][] = $fixture;
            }
            if ($fixture !== '' && (int) ($summary['ocfSidecarCount'] ?? 0) > 0) {
                $coverage['fixturesWithOcfSidecars'][] = $fixture;
            }

            $coverage['totals']['metadataCreators'] += (int) ($summary['metadataCreatorCount'] ?? 0);
            $coverage['totals']['manifestItems'] += (int) ($summary['manifestItemCount'] ?? 0);
            $coverage['totals']['readingOrderItems'] += (int) ($summary['readingOrderCount'] ?? 0);
            $coverage['totals']['spinePageSpreadItems'] += (int) ($summary['spinePageSpreadItemCount'] ?? 0);
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
            $coverage['totals']['mediaOverlays'] += (int) ($summary['mediaOverlayCount'] ?? 0);
            $coverage['totals']['resolvedMediaOverlays'] += (int) ($summary['resolvedMediaOverlayCount'] ?? 0);
            $coverage['totals']['missingMediaOverlays'] += (int) ($summary['missingMediaOverlayCount'] ?? 0);
            $coverage['totals']['mediaOverlayReferencedContentItems'] += (int) ($summary['mediaOverlayReferencedContentItemCount'] ?? 0);
            $coverage['totals']['mediaOverlayTextLocalTargets'] += (int) ($summary['mediaOverlayTextLocalTargetCount'] ?? 0);
            $coverage['totals']['mediaOverlayAudioLocalTargets'] += (int) ($summary['mediaOverlayAudioLocalTargetCount'] ?? 0);
            $coverage['totals']['mediaOverlayDurations'] += (int) ($summary['mediaOverlayDurationCount'] ?? 0);
            $coverage['totals']['encryptionItems'] += (int) ($summary['encryptionItemCount'] ?? 0);
            $coverage['totals']['obfuscatedFonts'] += (int) ($summary['obfuscatedFontCount'] ?? 0);
            $coverage['totals']['blockedEncryptedByteExposures'] += (int) ($summary['blockedEncryptedByteExposureCount'] ?? 0);
            $coverage['totals']['encryptionDiagnostics'] += (int) ($summary['encryptionDiagnosticCount'] ?? 0);
            $coverage['totals']['collections'] += (int) ($summary['collectionCount'] ?? 0);
            $coverage['totals']['collectionLinks'] += (int) ($summary['collectionLinkCount'] ?? 0);
            $coverage['totals']['bindingItems'] += (int) ($summary['bindingCount'] ?? 0);
            $coverage['totals']['bindingResolvedHandlers'] += (int) ($summary['bindingResolvedHandlerCount'] ?? 0);
            $coverage['totals']['bindingMediaTypeParameters'] += (int) ($summary['bindingMediaTypeParameterCount'] ?? 0);
            $coverage['totals']['ocfSidecars'] += (int) ($summary['ocfSidecarCount'] ?? 0);
        }

        ksort($metadataLanguageCounts, SORT_STRING);
        ksort($opfPartNameCounts, SORT_STRING);
        ksort($navigationTypeCounts, SORT_STRING);
        ksort($manifestMediaTypeCounts, SORT_STRING);
        ksort($manifestPropertyCounts, SORT_STRING);
        ksort($manifestResourceKindCounts, SORT_STRING);
        $coverage['opfPartNameCounts'] = $opfPartNameCounts;
        $coverage['metadataLanguageCounts'] = $metadataLanguageCounts;
        $coverage['navigationTypeCounts'] = $navigationTypeCounts;
        ksort($spineLinearStateCounts, SORT_STRING);
        $coverage['spineLinearStateCounts'] = $spineLinearStateCounts;
        ksort($spinePageSpreadPlacementCounts, SORT_STRING);
        $coverage['spinePageSpreadPlacementCounts'] = $spinePageSpreadPlacementCounts;
        $coverage['manifestMediaTypeCounts'] = $manifestMediaTypeCounts;
        $coverage['manifestPropertyCounts'] = $manifestPropertyCounts;
        $coverage['manifestResourceKindCounts'] = $manifestResourceKindCounts;
        ksort($guideReferenceTypeCounts, SORT_STRING);
        $coverage['guideReferenceTypeCounts'] = $guideReferenceTypeCounts;
        ksort($packageLinkRelCounts, SORT_STRING);
        $coverage['packageLinkRelCounts'] = $packageLinkRelCounts;
        ksort($encryptionRoleCounts, SORT_STRING);
        $coverage['encryptionRoleCounts'] = $encryptionRoleCounts;
        ksort($collectionRoleCounts, SORT_STRING);
        $coverage['collectionRoleCounts'] = $collectionRoleCounts;
        ksort($collectionLinkRelCounts, SORT_STRING);
        $coverage['collectionLinkRelCounts'] = $collectionLinkRelCounts;
        ksort($bindingMediaTypeCounts, SORT_STRING);
        $coverage['bindingMediaTypeCounts'] = $bindingMediaTypeCounts;
        ksort($ocfSidecarKindCounts, SORT_STRING);
        $coverage['ocfSidecarKindCounts'] = $ocfSidecarKindCounts;
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
     * @return list<string>
     */
    private static function orderedStringList(mixed $value): array
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

        return $items;
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
            'children' => $node->type === 'table_cell'
                ? $this->normalizedTableCellChildren($node->children)
                : $this->normalizedChildren($node->children),
        ];
    }

    /**
     * @param list<AstNode> $children
     * @return list<array<string, mixed>>
     */
    private function normalizedTableCellChildren(array $children): array
    {
        $normalized = $this->normalizedChildren($children);
        if (
            count($normalized) === 1
            && in_array($normalized[0]['type'] ?? null, ['plain', 'paragraph'], true)
            && ($normalized[0]['attrs'] ?? null) === []
            && is_array($normalized[0]['children'] ?? null)
        ) {
            return $normalized[0]['children'];
        }

        return $normalized;
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
        int $astMismatchCount,
        bool $runnerResultCovered = false
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
                'status' => $runnerResultCovered ? 'covered-by-supplied-runner-result-artifact' : 'open',
                'currentEvidence' => $runnerResultCovered
                    ? 'Supplied Cabal exe:pandoc EPUB-to-native runner result artifact validated against the pinned fixture and transcript contract.'
                    : 'Structured planned-not-run Cabal exe:pandoc command evidence is present; this harness does not run the upstream Haskell process itself.',
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

    private static function formatBooleanFlag(mixed $value): string
    {
        if ($value === true) {
            return 'true';
        }
        if ($value === false) {
            return 'false';
        }

        return 'unknown';
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
