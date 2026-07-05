<?php

declare(strict_types=1);

use PortLibs\Pandoc\HtmlUpstreamReaderEvidence;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-html-evidence-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary HTML evidence directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary HTML evidence directory {$base}");
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

$writeHtmlEvidenceTree = static function (string $upstreamRoot) use ($writeFile): void {
    $writeFile($upstreamRoot, 'test/Tests/Readers/HTML.hs', "module Tests.Readers.HTML where\n");
    $writeFile($upstreamRoot, 'src/Text/Pandoc/Readers/HTML.hs', "module Text.Pandoc.Readers.HTML where\n");
};

$writeGitHead = static function (string $upstreamRoot, string $commit): void {
    $gitDirectory = $upstreamRoot . DIRECTORY_SEPARATOR . '.git';
    if (!is_dir($gitDirectory) && !mkdir($gitDirectory, 0777, true) && !is_dir($gitDirectory)) {
        throw new RuntimeException("Unable to create git directory {$gitDirectory}");
    }

    file_put_contents($gitDirectory . DIRECTORY_SEPARATOR . 'HEAD', $commit . "\n");
};

$writeRunnerTranscripts = static function (string $root, array $paths, array $testNames = []) use ($writeFile): array {
    $records = [];
    foreach (array_values($paths) as $index => $path) {
        $contents = 'html runner transcript ' . (string) ($index + 1) . "\n" . $path . "\n";
        if (str_ends_with($path, '-targeted-list-tests.txt')) {
            $contents .= implode("\n", $testNames) . "\n";
        }
        if (str_ends_with($path, '-targeted-run.txt')) {
            $contents .= implode("\n", array_map(static fn (string $name): string => $name . ': OK', $testNames)) . "\n";
        }
        $contents .= "exitCode: 0\n";
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

return [
    'reports skipped html reader evidence when upstream root is absent' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $report = (new HtmlUpstreamReaderEvidence($repoRoot, 'missing-upstream-root-for-static-html-gate'))->report();
        $text = HtmlUpstreamReaderEvidence::formatTextReport($report);

        $t->same(1, $report['schemaVersion']);
        $t->same(HtmlUpstreamReaderEvidence::TOOL_NAME, $report['tool']);
        $t->same(HtmlUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
        $t->same('not-evaluated-missing-upstream-root', $report['validation']['status']);
        $t->same(['missing-upstream-root'], $report['validation']['issues']);
        $t->same('valid-checked-in-current-html-reader-evidence', $report['staticCurrentEvidence']['validation']['status']);
        $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredSelectedFixtureCount($report, 117));
        $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
        $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredNativeMappedParity($report, 117));
        $t->same(true, HtmlUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
        $t->same(true, HtmlUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
        $t->same(false, HtmlUpstreamReaderEvidence::hasNoValidationIssues($report));
        $t->same('planned-not-run', $report['runnerEvidence']['commandPlanStatus']);
        $t->same('test:test-pandoc', $report['runnerEvidence']['target']['testSuite']);
        $t->same(['Readers', 'HTML'], $report['runnerEvidence']['target']['tastyGroupPath']);
        $t->same('$2 == "Readers" && $3 == "HTML"', $report['runnerEvidence']['target']['tastyPattern']);
        $t->same('$2 == "Readers" && $3 == "HTML"', $report['runnerEvidence']['futureCommands'][1]['arguments'][8]);
        $t->same('$2 == "Readers" && $3 == "HTML"', $report['runnerEvidence']['futureCommands'][2]['arguments'][7]);
        $t->true(in_array('.port-libs/pandoc-runner/logs/html-targeted-run.txt', $report['runnerEvidence']['requiredTranscripts'], true));
        $t->true(in_array('.port-libs/pandoc-runner/artifacts/html-targeted-run/result.json', $report['runnerEvidence']['requiredArtifacts'], true));
        $t->contains('Pandoc HTML reader evidence', $text);
        $t->contains('Static current evidence: valid-checked-in-current-html-reader-evidence checkedInFixtures=117 nativePairs=117', $text);
        $t->contains('Native AST mapped parity: 117/117', $text);
        $t->contains('Native AST fixture inventory: html=117 native=117 paired=117 unpairedHtml=0 unpairedNative=0', $text);
        $t->contains('Runner plan: planned-not-run', $text);
    },

    'reports checked-in current html fixture static evidence' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $evidence = HtmlUpstreamReaderEvidence::checkedInCurrentEvidence($repoRoot);

        $t->same('static-checked-in-current-upstream-html-reader-fixture-evidence', $evidence['kind']);
        $t->same(HtmlUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $evidence['upstream']['commit']);
        $t->same(117, $evidence['readerDenominator']['selectedFixtureCount']);
        $t->same('selected checked-in upstream-derived and generated-current HTML reader fixtures', $evidence['readerDenominator']['fixtureScope']);
        $t->same(117, $evidence['readerDenominator']['nativeMappedPairCount']);
        $t->same(0, $evidence['readerDenominator']['nativeMappedPairExclusionCount']);
        $t->same([], $evidence['readerDenominator']['nativeMappedPairExclusions']);
        $t->same(117, $evidence['checkedInFixtureCount']);
        $t->same(117, $evidence['checkedInNativePairCount']);
        $t->same('upstream-html-anchor-image-attrs.html', $evidence['checkedInFixtures'][0]['name']);
        $t->same('27073f93fc90c5a85361723faad6fa6e1e44a891b344680476c41f9a4df3be74', $evidence['checkedInFixtures'][0]['checkedInFile']['sha256']);
        $t->same(363, $evidence['checkedInFixtures'][0]['checkedInFile']['bytes']);
        $t->same('lanes/pandoc/fixtures/upstream-html-anchor-image-attrs.native', $evidence['checkedInFixtures'][0]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][0]['checkedInNativePairFile']['present']);
        $t->same('7436ab45de8ec3a0738919f71e675412964bdf2dcf6aa60e56fc1fe4d5fffc6a', $evidence['checkedInFixtures'][0]['checkedInNativePairFile']['sha256']);
        $t->same(533, $evidence['checkedInFixtures'][0]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][0]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-base-absolute-image.html', $evidence['checkedInFixtures'][1]['name']);
        $t->same('f1ddb1f06c2b15d5667621c3c16b173d9afef19a7d5146bc017db44eba454e95', $evidence['checkedInFixtures'][1]['checkedInFile']['sha256']);
        $t->same(239, $evidence['checkedInFixtures'][1]['checkedInFile']['bytes']);
        $t->same('lanes/pandoc/fixtures/upstream-html-base-absolute-image.native', $evidence['checkedInFixtures'][1]['checkedInNativePairFile']['path']);
        $t->same('d4b2b819e8f822057a0a5f864d1113bb086ed55c1545726809e4b0243a68855f', $evidence['checkedInFixtures'][1]['checkedInNativePairFile']['sha256']);
        $t->same(139, $evidence['checkedInFixtures'][1]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][1]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-base-relative-image.html', $evidence['checkedInFixtures'][3]['name']);
        $t->same('1ed7cb61b59720c413b955c2046759ee9f5c4113329e9c9e43cbf21e4b9abd0a', $evidence['checkedInFixtures'][3]['checkedInFile']['sha256']);
        $t->same(116, $evidence['checkedInFixtures'][3]['checkedInFile']['bytes']);
        $t->same('lanes/pandoc/fixtures/upstream-html-base-relative-image.native', $evidence['checkedInFixtures'][3]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][3]['checkedInNativePairFile']['present']);
        $t->same('3294c0692387e57779fa6fb3b7290fdff8ac86463c2818fc8c15384af16bbc2a', $evidence['checkedInFixtures'][3]['checkedInNativePairFile']['sha256']);
        $t->same(144, $evidence['checkedInFixtures'][3]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][3]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-blockquote.html', $evidence['checkedInFixtures'][5]['name']);
        $t->same('7c1e8ba1dcde81e031bed35a0d75fad7dba0bf13ddbeef6188d38ae5cae82678', $evidence['checkedInFixtures'][5]['checkedInFile']['sha256']);
        $t->same(193, $evidence['checkedInFixtures'][5]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][5]['sourceKind']);
        $t->true($evidence['checkedInFixtures'][5]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-definition-list.html', $evidence['checkedInFixtures'][8]['name']);
        $t->same('b90033d358361a2fbb664565e8a16ba7b7b474b54ce6c694ce7866bbcf805fcf', $evidence['checkedInFixtures'][8]['checkedInFile']['sha256']);
        $t->same(248, $evidence['checkedInFixtures'][8]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][8]['sourceKind']);
        $t->true($evidence['checkedInFixtures'][8]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-details-summary-raw-block.html', $evidence['checkedInFixtures'][9]['name']);
        $t->same('f711f5a1e931605d4c6a6f17ec3ee863c12bdcacfbbf6291fa352305ebf049ca', $evidence['checkedInFixtures'][9]['checkedInFile']['sha256']);
        $t->same(268, $evidence['checkedInFixtures'][9]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][9]['sourceKind']);
        $t->true($evidence['checkedInFixtures'][9]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-inline-quote-cite-base.html', $evidence['checkedInFixtures'][17]['name']);
        $t->same('58ab514d8472421c2abb19720e4debd695d5eb6f4f8dfea431081129eadad0b3', $evidence['checkedInFixtures'][17]['checkedInFile']['sha256']);
        $t->same(307, $evidence['checkedInFixtures'][17]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][17]['sourceKind']);
        $t->true($evidence['checkedInFixtures'][17]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-sup-sub-inline.html', $evidence['checkedInFixtures'][48]['name']);
        $t->same('dbe1779879889257c5c569412522975181231391267bade40b84280943bdf2a0', $evidence['checkedInFixtures'][48]['checkedInFile']['sha256']);
        $t->same(173, $evidence['checkedInFixtures'][48]['checkedInFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][48]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-table-foot.html', $evidence['checkedInFixtures'][53]['name']);
        $t->same('250bdb2dfa7b027925bf2b2b858555085c27116f6c3326339a7fc6275326b159', $evidence['checkedInFixtures'][53]['checkedInFile']['sha256']);
        $t->same(382, $evidence['checkedInFixtures'][53]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][53]['sourceKind']);
        $t->true($evidence['checkedInFixtures'][53]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-thematic-break.html', $evidence['checkedInFixtures'][55]['name']);
        $t->same('beb96f5bb964a06db042cb6e9b8b52f187c0701216e5b27a3a381d9d4cf536ea', $evidence['checkedInFixtures'][55]['checkedInFile']['sha256']);
        $t->same(205, $evidence['checkedInFixtures'][55]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][55]['sourceKind']);
        $t->true($evidence['checkedInFixtures'][55]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-ruby-annotation.html', $evidence['checkedInFixtures'][56]['name']);
        $t->same('a0a8c53ca2264dcded30176307d7094145ccb337bfcabfae87a5d880074a0d60', $evidence['checkedInFixtures'][56]['checkedInFile']['sha256']);
        $t->same(203, $evidence['checkedInFixtures'][56]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][56]['sourceKind']);
        $t->true($evidence['checkedInFixtures'][56]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-address-block.html', $evidence['checkedInFixtures'][57]['name']);
        $t->same('8108f7532e80e46c24c47d11ea212837f7cc3123d5dfbafa03af9187a0b50fcf', $evidence['checkedInFixtures'][57]['checkedInFile']['sha256']);
        $t->same(284, $evidence['checkedInFixtures'][57]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][57]['sourceKind']);
        $t->true($evidence['checkedInFixtures'][57]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-xml-lang-metadata.html', $evidence['checkedInFixtures'][58]['name']);
        $t->same('ee6034835ca62d6e63472a2aa6a27c506f4c015369f8114409246357c2333596', $evidence['checkedInFixtures'][58]['checkedInFile']['sha256']);
        $t->same(95, $evidence['checkedInFixtures'][58]['checkedInFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][58]['localTestReferenceCount'] >= 1);
        $t->same('upstream-native-html-row-header-table.html', $evidence['checkedInFixtures'][59]['name']);
        $t->same('5f59ee99b16a90f6da337f94dd75c239cefb4ff7073c21e516077773892a332d', $evidence['checkedInFixtures'][59]['checkedInFile']['sha256']);
        $t->same(288, $evidence['checkedInFixtures'][59]['checkedInFile']['bytes']);
        $t->same('upstream-html-multi-tbody-row-header-table.html', $evidence['checkedInFixtures'][60]['name']);
        $t->same('2b89dfbad53b5f34a1d4f9ffc48d3450d0641ac762c6324a265f7061ab889b22', $evidence['checkedInFixtures'][60]['checkedInFile']['sha256']);
        $t->same(391, $evidence['checkedInFixtures'][60]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][60]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-multi-tbody-row-header-table.native', $evidence['checkedInFixtures'][60]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][60]['checkedInNativePairFile']['present']);
        $t->same('bbfb47f816f76c3dc4560673a0bcb8a2cf7bc7aa11c7c81a3d634212beaa6807', $evidence['checkedInFixtures'][60]['checkedInNativePairFile']['sha256']);
        $t->same(3940, $evidence['checkedInFixtures'][60]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][60]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-kbd-samp-var-inline.html', $evidence['checkedInFixtures'][61]['name']);
        $t->same('1b768d1b3867b74ff56121a1b6c5bfcd52c9725a3c0febfb872abfb0f95717b5', $evidence['checkedInFixtures'][61]['checkedInFile']['sha256']);
        $t->same(205, $evidence['checkedInFixtures'][61]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][61]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-kbd-samp-var-inline.native', $evidence['checkedInFixtures'][61]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][61]['checkedInNativePairFile']['present']);
        $t->same('b243a6998368bc21f51b3a33ac4367cdb37cacb5806472e97c912b69d6cfed75', $evidence['checkedInFixtures'][61]['checkedInNativePairFile']['sha256']);
        $t->same(412, $evidence['checkedInFixtures'][61]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][61]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-form-controls.html', $evidence['checkedInFixtures'][62]['name']);
        $t->same('283067b6426ef087c9f9fa1cc7267969d589b0f88c4bca24e1d697b93f768a6e', $evidence['checkedInFixtures'][62]['checkedInFile']['sha256']);
        $t->same(319, $evidence['checkedInFixtures'][62]['checkedInFile']['bytes']);
        $t->same('generated-current-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][62]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-form-controls.native', $evidence['checkedInFixtures'][62]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][62]['checkedInNativePairFile']['present']);
        $t->same('33193a2c260261b4a203b31e9740b5c8ca276eea51d79a70ba05753b51a4d728', $evidence['checkedInFixtures'][62]['checkedInNativePairFile']['sha256']);
        $t->same(98, $evidence['checkedInFixtures'][62]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][62]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-standalone-image-data-external.html', $evidence['checkedInFixtures'][63]['name']);
        $t->same('f7d25720e4bb8a3f4166a26c9cd0ffe12f24feb578a2000d5bdba3acaf7cad64', $evidence['checkedInFixtures'][63]['checkedInFile']['sha256']);
        $t->same(62, $evidence['checkedInFixtures'][63]['checkedInFile']['bytes']);
        $t->same('lanes/pandoc/fixtures/upstream-html-standalone-image-data-external.native', $evidence['checkedInFixtures'][63]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][63]['checkedInNativePairFile']['present']);
        $t->same('a084ba4ddd5859f842809341d61e897910241a00876d92a8ae95d5e564bc1bad', $evidence['checkedInFixtures'][63]['checkedInNativePairFile']['sha256']);
        $t->same(137, $evidence['checkedInFixtures'][63]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][63]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-standalone-emph-strong-inline.html', $evidence['checkedInFixtures'][64]['name']);
        $t->same('30ce545fa7dad51eef264c260ccc456fbfe636ae42d4bc7f860698d1aa32c43b', $evidence['checkedInFixtures'][64]['checkedInFile']['sha256']);
        $t->same(58, $evidence['checkedInFixtures'][64]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][64]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-standalone-emph-strong-inline.native', $evidence['checkedInFixtures'][64]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][64]['checkedInNativePairFile']['present']);
        $t->same('0dddeb555a58d4d04237fa919cc48211b1ad9960685ce4ba4e0249efbf24be57', $evidence['checkedInFixtures'][64]['checkedInNativePairFile']['sha256']);
        $t->same(114, $evidence['checkedInFixtures'][64]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][64]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-main-inline-plain.html', $evidence['checkedInFixtures'][65]['name']);
        $t->same('7c05eccdc284eb2cdbcf6dfb5a7275eb3036926cc91ede2412eacb0cb0d2d414', $evidence['checkedInFixtures'][65]['checkedInFile']['sha256']);
        $t->same(92, $evidence['checkedInFixtures'][65]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][65]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-main-inline-plain.native', $evidence['checkedInFixtures'][65]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][65]['checkedInNativePairFile']['present']);
        $t->same('9f5edf4b7851ef00b3b62ca6fa5081aadb5c87fa4cd2796cac6952cf8166270c', $evidence['checkedInFixtures'][65]['checkedInNativePairFile']['sha256']);
        $t->same(26, $evidence['checkedInFixtures'][65]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][65]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-transparent-inline-fragment.html', $evidence['checkedInFixtures'][66]['name']);
        $t->same('dc2051302c2f3d87ec33d57f516b827e6f81a7509d1950c0cd774dca84da6380', $evidence['checkedInFixtures'][66]['checkedInFile']['sha256']);
        $t->same(123, $evidence['checkedInFixtures'][66]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][66]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-transparent-inline-fragment.native', $evidence['checkedInFixtures'][66]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][66]['checkedInNativePairFile']['present']);
        $t->same('e4d9a499fff3996e2c72956d4647d4b6d01e26c813512fd283937f90d338a441', $evidence['checkedInFixtures'][66]['checkedInNativePairFile']['sha256']);
        $t->same(183, $evidence['checkedInFixtures'][66]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][66]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-standalone-sup-sub-inline.html', $evidence['checkedInFixtures'][67]['name']);
        $t->same('ad549a16c064f08763a22fdd6bb5184799fc3470779d3c627b423f891cdbe3dd', $evidence['checkedInFixtures'][67]['checkedInFile']['sha256']);
        $t->same(35, $evidence['checkedInFixtures'][67]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][67]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-standalone-sup-sub-inline.native', $evidence['checkedInFixtures'][67]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][67]['checkedInNativePairFile']['present']);
        $t->same('18120bc4db449efb03f6c9bbfcda020155ffcaaeb501101e19d963ddf48cb07d', $evidence['checkedInFixtures'][67]['checkedInNativePairFile']['sha256']);
        $t->same(119, $evidence['checkedInFixtures'][67]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][67]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-standalone-time-inline.html', $evidence['checkedInFixtures'][68]['name']);
        $t->same('68ca00e5e22b94dd429ba6dc13106c0caf8a75f1598a9ec609795997f197d4a8', $evidence['checkedInFixtures'][68]['checkedInFile']['sha256']);
        $t->same(64, $evidence['checkedInFixtures'][68]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][68]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-standalone-time-inline.native', $evidence['checkedInFixtures'][68]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][68]['checkedInNativePairFile']['present']);
        $t->same('d3f08c2cb5b14d6ebc5898ed31924305324711bd5f06d9bbc688b9ceafd68563', $evidence['checkedInFixtures'][68]['checkedInNativePairFile']['sha256']);
        $t->same(59, $evidence['checkedInFixtures'][68]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][68]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-standalone-kbd-inline.html', $evidence['checkedInFixtures'][69]['name']);
        $t->same('2b542c385308a675fd1a084f018d30297c553bcd1749b6e632fdcf3d3c4bc860', $evidence['checkedInFixtures'][69]['checkedInFile']['sha256']);
        $t->same(15, $evidence['checkedInFixtures'][69]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][69]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-standalone-kbd-inline.native', $evidence['checkedInFixtures'][69]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][69]['checkedInNativePairFile']['present']);
        $t->same('25fcc3840563f13578d4706eb5e810737dc55a1e7a06d538a0c6e1c9cf529e53', $evidence['checkedInFixtures'][69]['checkedInNativePairFile']['sha256']);
        $t->same(57, $evidence['checkedInFixtures'][69]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][69]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-standalone-bdo-mark-q-inline.html', $evidence['checkedInFixtures'][70]['name']);
        $t->same('0c295703f1457595e8a9cf1e5fda4e1a4a807e48ad81f5d3694542b9ef519e88', $evidence['checkedInFixtures'][70]['checkedInFile']['sha256']);
        $t->same(67, $evidence['checkedInFixtures'][70]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][70]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-standalone-bdo-mark-q-inline.native', $evidence['checkedInFixtures'][70]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][70]['checkedInNativePairFile']['present']);
        $t->same('08c8a92a73cd9309832a6a7db802fc0f98eb762478ac8247412012d5de4afb75', $evidence['checkedInFixtures'][70]['checkedInNativePairFile']['sha256']);
        $t->same(253, $evidence['checkedInFixtures'][70]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][70]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-progress-in-paragraph.html', $evidence['checkedInFixtures'][71]['name']);
        $t->same('570ccc646ee217b1726dc356ef6c7795e752a31a31fafde43201e2df11af030d', $evidence['checkedInFixtures'][71]['checkedInFile']['sha256']);
        $t->same(69, $evidence['checkedInFixtures'][71]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][71]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-progress-in-paragraph.native', $evidence['checkedInFixtures'][71]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][71]['checkedInNativePairFile']['present']);
        $t->same('535faafe838384418209c653fdcd594468c239e68d794cb1c25c529787b6f2f3', $evidence['checkedInFixtures'][71]['checkedInNativePairFile']['sha256']);
        $t->same(97, $evidence['checkedInFixtures'][71]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][71]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-standalone-underline-inline.html', $evidence['checkedInFixtures'][72]['name']);
        $t->same('fd33f08488ef2a161dd581ab75355eae7fc48feaf15d561939763d3b6bd7832e', $evidence['checkedInFixtures'][72]['checkedInFile']['sha256']);
        $t->same(37, $evidence['checkedInFixtures'][72]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][72]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-standalone-underline-inline.native', $evidence['checkedInFixtures'][72]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][72]['checkedInNativePairFile']['present']);
        $t->same('3bd1a5f9e5736778bcc4ff3706c3973e6f6904134672f30266a9904814cc205c', $evidence['checkedInFixtures'][72]['checkedInNativePairFile']['sha256']);
        $t->same(90, $evidence['checkedInFixtures'][72]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][72]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-standalone-abbr-dfn-inline.html', $evidence['checkedInFixtures'][73]['name']);
        $t->same('d4032c96239a8a49f9ab6185a22a6052438f7dd9cd6fc6f18e4e92dfa80537a4', $evidence['checkedInFixtures'][73]['checkedInFile']['sha256']);
        $t->same(56, $evidence['checkedInFixtures'][73]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][73]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-standalone-abbr-dfn-inline.native', $evidence['checkedInFixtures'][73]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][73]['checkedInNativePairFile']['present']);
        $t->same('871aadd1cd14a9fcad027116b721868f6990ceeb4c2a83177d9378022f48b8ad', $evidence['checkedInFixtures'][73]['checkedInNativePairFile']['sha256']);
        $t->same(200, $evidence['checkedInFixtures'][73]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][73]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-data-value-inline.html', $evidence['checkedInFixtures'][74]['name']);
        $t->same('2bff4d9f60fe92319105056c1cdebd7a0802dee1088bad3bbfe104144768af63', $evidence['checkedInFixtures'][74]['checkedInFile']['sha256']);
        $t->same(66, $evidence['checkedInFixtures'][74]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][74]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-data-value-inline.native', $evidence['checkedInFixtures'][74]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][74]['checkedInNativePairFile']['present']);
        $t->same('20eceb73c668cd426b22f2ce6ba4233c207e14f306f880137a34e1608474d909', $evidence['checkedInFixtures'][74]['checkedInNativePairFile']['sha256']);
        $t->same(109, $evidence['checkedInFixtures'][74]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][74]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-meter-inline.html', $evidence['checkedInFixtures'][75]['name']);
        $t->same('1f5d55e79a85dfc7d95a6e9dd28e448ba41f3af6a4f797a12c21e420e59c5ac6', $evidence['checkedInFixtures'][75]['checkedInFile']['sha256']);
        $t->same(97, $evidence['checkedInFixtures'][75]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][75]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-meter-inline.native', $evidence['checkedInFixtures'][75]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][75]['checkedInNativePairFile']['present']);
        $t->same('1e34575ad25875ab1c2ce96fc8aed8aa5c964416829f792839e1b86537676abf', $evidence['checkedInFixtures'][75]['checkedInNativePairFile']['sha256']);
        $t->same(131, $evidence['checkedInFixtures'][75]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][75]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-standalone-bdi-inline.html', $evidence['checkedInFixtures'][76]['name']);
        $t->same('ea4774da33a60ee93b6e0ca24cc86b39785e46cb2a202750cd350141e05c8b2f', $evidence['checkedInFixtures'][76]['checkedInFile']['sha256']);
        $t->same(45, $evidence['checkedInFixtures'][76]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][76]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-standalone-bdi-inline.native', $evidence['checkedInFixtures'][76]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][76]['checkedInNativePairFile']['present']);
        $t->same('3e7cb2bfee0c15b68bc397e5ca471fd13f47b91355f487f063c0ebad7808e012', $evidence['checkedInFixtures'][76]['checkedInNativePairFile']['sha256']);
        $t->same(69, $evidence['checkedInFixtures'][76]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][76]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-optional-list-item-tree-construction.html', $evidence['checkedInFixtures'][77]['name']);
        $t->same('ad087956d31cf2b3ba9b2212a55fcd6e8c6835f891dec7bfdff92b021fd18628', $evidence['checkedInFixtures'][77]['checkedInFile']['sha256']);
        $t->same(53, $evidence['checkedInFixtures'][77]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][77]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-optional-list-item-tree-construction.native', $evidence['checkedInFixtures'][77]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][77]['checkedInNativePairFile']['present']);
        $t->same('b7ae1b00347960e6c0a59ca1e757b53f8d69120ebab46b9ed067c9e0ea538325', $evidence['checkedInFixtures'][77]['checkedInNativePairFile']['sha256']);
        $t->same(127, $evidence['checkedInFixtures'][77]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][77]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-implicit-tbody-table.html', $evidence['checkedInFixtures'][78]['name']);
        $t->same('8fee8c5db5323415652fcd9f6b1a136c447db09f10f1eb3a2b9fb7730c9da79b', $evidence['checkedInFixtures'][78]['checkedInFile']['sha256']);
        $t->same(52, $evidence['checkedInFixtures'][78]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][78]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-implicit-tbody-table.native', $evidence['checkedInFixtures'][78]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][78]['checkedInNativePairFile']['present']);
        $t->same('e514a9b0dbdff3823236142bff864b62a511f95fc906ccdae0a339826eb08c8b', $evidence['checkedInFixtures'][78]['checkedInNativePairFile']['sha256']);
        $t->same(725, $evidence['checkedInFixtures'][78]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][78]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-table-row-col-span.html', $evidence['checkedInFixtures'][79]['name']);
        $t->same('eedcf367f273f27df44c2ff75ba1762dc7735353512a8d38963fca10c0fb74fe', $evidence['checkedInFixtures'][79]['checkedInFile']['sha256']);
        $t->same(214, $evidence['checkedInFixtures'][79]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][79]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-table-row-col-span.native', $evidence['checkedInFixtures'][79]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][79]['checkedInNativePairFile']['present']);
        $t->same('d154f335dffc5b3708ca023d251703b734b8dbc2c43cfa2d5f84d1258fecd048', $evidence['checkedInFixtures'][79]['checkedInNativePairFile']['sha256']);
        $t->same(1815, $evidence['checkedInFixtures'][79]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][79]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-meta-refresh-boundary.html', $evidence['checkedInFixtures'][80]['name']);
        $t->same('ba5be3a810cc2e1092befdc2df2c99ea3fc50b2ae688f9a95ef4ea02e0643af1', $evidence['checkedInFixtures'][80]['checkedInFile']['sha256']);
        $t->same(218, $evidence['checkedInFixtures'][80]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][80]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-meta-refresh-boundary.native', $evidence['checkedInFixtures'][80]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][80]['checkedInNativePairFile']['present']);
        $t->same('6b44a6fddef192b2be6c7a7a3693d14ac646f30f26b21bcbcb869dcc70b6378a', $evidence['checkedInFixtures'][80]['checkedInNativePairFile']['sha256']);
        $t->same(28, $evidence['checkedInFixtures'][80]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][80]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-invalid-table-children.html', $evidence['checkedInFixtures'][81]['name']);
        $t->same('71537e89ccb404aa52cb698063ced36b2e796a5dc81b8d5dc1468881a43d0de8', $evidence['checkedInFixtures'][81]['checkedInFile']['sha256']);
        $t->same(82, $evidence['checkedInFixtures'][81]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][81]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-invalid-table-children.native', $evidence['checkedInFixtures'][81]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][81]['checkedInNativePairFile']['present']);
        $t->same('b0f7bcd2137700356befa3e1a7945e5c7b98ad862d8f506dffb11f57d5820446', $evidence['checkedInFixtures'][81]['checkedInNativePairFile']['sha256']);
        $t->same(108, $evidence['checkedInFixtures'][81]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][81]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-paragraph-table-tree-construction.html', $evidence['checkedInFixtures'][82]['name']);
        $t->same('98a40e8da15d9893bc3a03d4c7ff692260ff941bf3162ad23c8e2bb15775bc1c', $evidence['checkedInFixtures'][82]['checkedInFile']['sha256']);
        $t->same(56, $evidence['checkedInFixtures'][82]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][82]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-paragraph-table-tree-construction.native', $evidence['checkedInFixtures'][82]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][82]['checkedInNativePairFile']['present']);
        $t->same('62604c019bf702de77d6d1530b55ed88d17668e74f04def90c1049f80ac548ee', $evidence['checkedInFixtures'][82]['checkedInNativePairFile']['sha256']);
        $t->same(548, $evidence['checkedInFixtures'][82]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][82]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-paragraph-hr-tree-construction.html', $evidence['checkedInFixtures'][83]['name']);
        $t->same('0fc42b6a1ffbbb922f3c2bd736f2ee60d4bbf888f4f2a6a2762ae624cd1983bd', $evidence['checkedInFixtures'][83]['checkedInFile']['sha256']);
        $t->same(23, $evidence['checkedInFixtures'][83]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][83]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-paragraph-hr-tree-construction.native', $evidence['checkedInFixtures'][83]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][83]['checkedInNativePairFile']['present']);
        $t->same('a2a22e502a765a9e67581ba29935ea8560856d3030c24df16ea6a1c9e717beff', $evidence['checkedInFixtures'][83]['checkedInNativePairFile']['sha256']);
        $t->same(66, $evidence['checkedInFixtures'][83]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][83]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-colgroup-width-table.html', $evidence['checkedInFixtures'][84]['name']);
        $t->same('1d8f37ed10a3f790b6937485c58a13e2289f50fafc24abef907714aac7f71768', $evidence['checkedInFixtures'][84]['checkedInFile']['sha256']);
        $t->same(249, $evidence['checkedInFixtures'][84]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][84]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-colgroup-width-table.native', $evidence['checkedInFixtures'][84]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][84]['checkedInNativePairFile']['present']);
        $t->same('8a098d99e4460dfdaf7fd5c9a5535580cc21631d2199d8b14a120b02b7e76ae8', $evidence['checkedInFixtures'][84]['checkedInNativePairFile']['sha256']);
        $t->same(1219, $evidence['checkedInFixtures'][84]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][84]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-template-raw-boundary.html', $evidence['checkedInFixtures'][85]['name']);
        $t->same('91b8f7fbae20491a4d3967987170fe30867ed7c8cc4531512628aab3d3e3726f', $evidence['checkedInFixtures'][85]['checkedInFile']['sha256']);
        $t->same(85, $evidence['checkedInFixtures'][85]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][85]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-template-raw-boundary.native', $evidence['checkedInFixtures'][85]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][85]['checkedInNativePairFile']['present']);
        $t->same('962efbf473e3752b6b3222c2eb4bff31ca9b9fe1ef202cb1c7fe349a29432fec', $evidence['checkedInFixtures'][85]['checkedInNativePairFile']['sha256']);
        $t->same(112, $evidence['checkedInFixtures'][85]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][85]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-standalone-s-inline.html', $evidence['checkedInFixtures'][86]['name']);
        $t->same('2629f102923aa9d840693ff041a207fde3e62d0d7fbd4cdf5d87d6849dba2a91', $evidence['checkedInFixtures'][86]['checkedInFile']['sha256']);
        $t->same(37, $evidence['checkedInFixtures'][86]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][86]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-standalone-s-inline.native', $evidence['checkedInFixtures'][86]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][86]['checkedInNativePairFile']['present']);
        $t->same('75c63ded737d1433c09575605967271e5365c3a0e4056f334bc45943341b8e2f', $evidence['checkedInFixtures'][86]['checkedInNativePairFile']['sha256']);
        $t->same(112, $evidence['checkedInFixtures'][86]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][86]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-anchor-without-href.html', $evidence['checkedInFixtures'][87]['name']);
        $t->same('914c6cde89ab5f70447b7062514f5df224094f7ba8b98a957ed3733c36c36b04', $evidence['checkedInFixtures'][87]['checkedInFile']['sha256']);
        $t->same(19, $evidence['checkedInFixtures'][87]['checkedInFile']['bytes']);
        $t->same('selected-upstream-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][87]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-anchor-without-href.native', $evidence['checkedInFixtures'][87]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][87]['checkedInNativePairFile']['present']);
        $t->same('773d8c4241e158620563cf7ae77134e4223e4145575f45fc50dad5829104a4cb', $evidence['checkedInFixtures'][87]['checkedInNativePairFile']['sha256']);
        $t->same(45, $evidence['checkedInFixtures'][87]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][87]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-main-role-native-divs.html', $evidence['checkedInFixtures'][88]['name']);
        $t->same('61e961ece998487900942f5b55713075aaeda0d93f1de312121cd994e45b8666', $evidence['checkedInFixtures'][88]['checkedInFile']['sha256']);
        $t->same(31, $evidence['checkedInFixtures'][88]['checkedInFile']['bytes']);
        $t->same('selected-upstream-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][88]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-main-role-native-divs.native', $evidence['checkedInFixtures'][88]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][88]['checkedInNativePairFile']['present']);
        $t->same('980e2496cd9104018060b7d379136cdf5c1a10692d63f45ce6354d11f9a0db6c', $evidence['checkedInFixtures'][88]['checkedInNativePairFile']['sha256']);
        $t->same(82, $evidence['checkedInFixtures'][88]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][88]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-ordered-list-type-start.html', $evidence['checkedInFixtures'][89]['name']);
        $t->same('0e2c27f56de97f2ee0f1ebc9064440f91fac47426c068d94de5cc87c69bd9969', $evidence['checkedInFixtures'][89]['checkedInFile']['sha256']);
        $t->same(73, $evidence['checkedInFixtures'][89]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][89]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-ordered-list-type-start.native', $evidence['checkedInFixtures'][89]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][89]['checkedInNativePairFile']['present']);
        $t->same('b6593594e0bd94efb11f084fe1a02975d20a2ae1d061b60299cdf18b25e92ea4', $evidence['checkedInFixtures'][89]['checkedInNativePairFile']['sha256']);
        $t->same(134, $evidence['checkedInFixtures'][89]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][89]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-base-root-relative-image.html', $evidence['checkedInFixtures'][90]['name']);
        $t->same('c4b5fe671b13a730ebcaf3d49e6a883a80001cc99208d48ef350d43ae0d3cd74', $evidence['checkedInFixtures'][90]['checkedInFile']['sha256']);
        $t->same(114, $evidence['checkedInFixtures'][90]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][90]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-base-root-relative-image.native', $evidence['checkedInFixtures'][90]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][90]['checkedInNativePairFile']['present']);
        $t->same('25cd17ba55eef7bd3d64c97cee53d23d1d1f4338eadac1f852063fce11396d6b', $evidence['checkedInFixtures'][90]['checkedInNativePairFile']['sha256']);
        $t->same(137, $evidence['checkedInFixtures'][90]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][90]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-standalone-image-title.html', $evidence['checkedInFixtures'][91]['name']);
        $t->same('1fa56d948da7f1cf3ce88bb75685dcb9c74a17fad609f09cd4dfca059b5e0752', $evidence['checkedInFixtures'][91]['checkedInFile']['sha256']);
        $t->same(62, $evidence['checkedInFixtures'][91]['checkedInFile']['bytes']);
        $t->same('selected-upstream-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][91]['sourceKind']);
        $t->same('lanes/pandoc/fixtures/upstream-html-standalone-image-title.native', $evidence['checkedInFixtures'][91]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][91]['checkedInNativePairFile']['present']);
        $t->same('743a0626651a9bb3eb4337adc269171a90d3367513fccd87f4664b4b1e986bb4', $evidence['checkedInFixtures'][91]['checkedInNativePairFile']['sha256']);
        $t->same(124, $evidence['checkedInFixtures'][91]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][91]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-omitted-heading-end-tags.html', $evidence['checkedInFixtures'][92]['name']);
        $t->same('0dd6169d31f8a90d90840c94d72d4c70af0e03b99291a95b6747bc1ced9b2eb5', $evidence['checkedInFixtures'][92]['checkedInFile']['sha256']);
        $t->same(54, $evidence['checkedInFixtures'][92]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][92]['sourceKind']);
        $t->same('block-structure', $evidence['readerDenominator']['selectedFixtures'][92]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-omitted-heading-end-tags.native', $evidence['checkedInFixtures'][92]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][92]['checkedInNativePairFile']['present']);
        $t->same('e5b126a270238c897410a13c07942d996f701e4d07da13c1c9ab7b7d3a0b327f', $evidence['checkedInFixtures'][92]['checkedInNativePairFile']['sha256']);
        $t->same(174, $evidence['checkedInFixtures'][92]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][92]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-base-trailing-slash-image.html', $evidence['checkedInFixtures'][93]['name']);
        $t->same('02aa50f4dd62dcf7bfa9b57cdaf2756717d1b5209b08f0a4bed6ad739284c2f4', $evidence['checkedInFixtures'][93]['checkedInFile']['sha256']);
        $t->same(113, $evidence['checkedInFixtures'][93]['checkedInFile']['bytes']);
        $t->same('selected-upstream-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][93]['sourceKind']);
        $t->same('inline-and-metadata', $evidence['readerDenominator']['selectedFixtures'][93]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-base-trailing-slash-image.native', $evidence['checkedInFixtures'][93]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][93]['checkedInNativePairFile']['present']);
        $t->same('3294c0692387e57779fa6fb3b7290fdff8ac86463c2818fc8c15384af16bbc2a', $evidence['checkedInFixtures'][93]['checkedInNativePairFile']['sha256']);
        $t->same(144, $evidence['checkedInFixtures'][93]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][93]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-main-closes-paragraph.html', $evidence['checkedInFixtures'][94]['name']);
        $t->same('03f9df87c568970e2ce343aeb9cc8b752cd75c55d1a99b6c33e18eca6d0e24bd', $evidence['checkedInFixtures'][94]['checkedInFile']['sha256']);
        $t->same(34, $evidence['checkedInFixtures'][94]['checkedInFile']['bytes']);
        $t->same('selected-upstream-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][94]['sourceKind']);
        $t->same('inline-and-metadata', $evidence['readerDenominator']['selectedFixtures'][94]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-main-closes-paragraph.native', $evidence['checkedInFixtures'][94]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][94]['checkedInNativePairFile']['present']);
        $t->same('3b8ea5f29d4ae790d08b1061a822ccd49ecc1ece8082774ea8b57e53f132f7df', $evidence['checkedInFixtures'][94]['checkedInNativePairFile']['sha256']);
        $t->same(49, $evidence['checkedInFixtures'][94]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][94]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-orphan-table-fragment-tree-construction.html', $evidence['checkedInFixtures'][95]['name']);
        $t->same('b2cfadf098850c5125949f44c3cc01e41a7fbaaf098cf382b9b806399e8d3b2e', $evidence['checkedInFixtures'][95]['checkedInFile']['sha256']);
        $t->same(66, $evidence['checkedInFixtures'][95]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][95]['sourceKind']);
        $t->same('block-structure', $evidence['readerDenominator']['selectedFixtures'][95]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-orphan-table-fragment-tree-construction.native', $evidence['checkedInFixtures'][95]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][95]['checkedInNativePairFile']['present']);
        $t->same('988cb1cfeb32c7e0b00d41041ac5fe5ee0e042d4ab46bb4188dffef4ee7240bb', $evidence['checkedInFixtures'][95]['checkedInNativePairFile']['sha256']);
        $t->same(82, $evidence['checkedInFixtures'][95]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][95]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-optional-definition-list-tree-construction.html', $evidence['checkedInFixtures'][96]['name']);
        $t->same('387b58f4b56a792dafc194c0029ac4b0fc1c99419dde5c201501901dd8ac96ab', $evidence['checkedInFixtures'][96]['checkedInFile']['sha256']);
        $t->same(243, $evidence['checkedInFixtures'][96]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][96]['sourceKind']);
        $t->same('block-structure', $evidence['readerDenominator']['selectedFixtures'][96]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-optional-definition-list-tree-construction.native', $evidence['checkedInFixtures'][96]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][96]['checkedInNativePairFile']['present']);
        $t->same('b0e7fa04063312d5e79a02b99afa070984163e963f56e2620d04f6de328f5989', $evidence['checkedInFixtures'][96]['checkedInNativePairFile']['sha256']);
        $t->same(354, $evidence['checkedInFixtures'][96]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][96]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-paragraph-block-tree-construction.html', $evidence['checkedInFixtures'][97]['name']);
        $t->same('332ef58c13dc629c9af634d6096041416ba1857379b305aa38e4ec1f8673ac8d', $evidence['checkedInFixtures'][97]['checkedInFile']['sha256']);
        $t->same(59, $evidence['checkedInFixtures'][97]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][97]['sourceKind']);
        $t->same('block-structure', $evidence['readerDenominator']['selectedFixtures'][97]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-paragraph-block-tree-construction.native', $evidence['checkedInFixtures'][97]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][97]['checkedInNativePairFile']['present']);
        $t->same('e9eb597d09e741532568ccd33d9cffe94821b55d21558ab968df41fc4689b823', $evidence['checkedInFixtures'][97]['checkedInNativePairFile']['sha256']);
        $t->same(139, $evidence['checkedInFixtures'][97]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][97]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-multi-term-definition-list.html', $evidence['checkedInFixtures'][98]['name']);
        $t->same('3a9072350c433235c5c142f1d82d247dbbc356b2e8fff0bd186ffa748f7d6fdc', $evidence['checkedInFixtures'][98]['checkedInFile']['sha256']);
        $t->same(284, $evidence['checkedInFixtures'][98]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][98]['sourceKind']);
        $t->same('block-structure', $evidence['readerDenominator']['selectedFixtures'][98]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-multi-term-definition-list.native', $evidence['checkedInFixtures'][98]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][98]['checkedInNativePairFile']['present']);
        $t->same('7979e740740cb30a19fe91975409e68db7536cb398e90dfc71505fd4298a7216', $evidence['checkedInFixtures'][98]['checkedInNativePairFile']['sha256']);
        $t->same(611, $evidence['checkedInFixtures'][98]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][98]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-paragraph-transparent-block-tree-construction.html', $evidence['checkedInFixtures'][99]['name']);
        $t->same('0193bc1f8f94e1c1303576891c9949276967d7c418a6c204a6305f2ca01c39bd', $evidence['checkedInFixtures'][99]['checkedInFile']['sha256']);
        $t->same(108, $evidence['checkedInFixtures'][99]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][99]['sourceKind']);
        $t->same('block-structure', $evidence['readerDenominator']['selectedFixtures'][99]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-paragraph-transparent-block-tree-construction.native', $evidence['checkedInFixtures'][99]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][99]['checkedInNativePairFile']['present']);
        $t->same('32c504669d4d693d3b0d98603f67ac0ce738c97615dd4a32ae89ee86d1a5d2ef', $evidence['checkedInFixtures'][99]['checkedInNativePairFile']['sha256']);
        $t->same(176, $evidence['checkedInFixtures'][99]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][99]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-picture-fallback-image.html', $evidence['checkedInFixtures'][100]['name']);
        $t->same('1e1396000b0b78527486b6f9bd6d4cb8027510273edeb1b6c536d030ac89b8de', $evidence['checkedInFixtures'][100]['checkedInFile']['sha256']);
        $t->same(339, $evidence['checkedInFixtures'][100]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][100]['sourceKind']);
        $t->same('inline-and-metadata', $evidence['readerDenominator']['selectedFixtures'][100]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-picture-fallback-image.native', $evidence['checkedInFixtures'][100]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][100]['checkedInNativePairFile']['present']);
        $t->same('03c41adc9159d1de49c78e9843f3aafa5bd650aa2a84b3cb7e18b6784e6de950', $evidence['checkedInFixtures'][100]['checkedInNativePairFile']['sha256']);
        $t->same(256, $evidence['checkedInFixtures'][100]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][100]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-standalone-select-optgroup-inline.html', $evidence['checkedInFixtures'][101]['name']);
        $t->same('c58cb6e52ceeba2c5e7e5e51760dc3e0dfd2ba58a13e7ddaf55b95ca3d695187', $evidence['checkedInFixtures'][101]['checkedInFile']['sha256']);
        $t->same(113, $evidence['checkedInFixtures'][101]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][101]['sourceKind']);
        $t->same('standalone-inline-html', $evidence['readerDenominator']['selectedFixtures'][101]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-standalone-select-optgroup-inline.native', $evidence['checkedInFixtures'][101]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][101]['checkedInNativePairFile']['present']);
        $t->same('870e737bcda23ac3bceef5daf4d2a5a141e478efa9b9b543233b8690e5d913a0', $evidence['checkedInFixtures'][101]['checkedInNativePairFile']['sha256']);
        $t->same(52, $evidence['checkedInFixtures'][101]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][101]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-button-scope-tree-construction.html', $evidence['checkedInFixtures'][103]['name']);
        $t->same('a2ee7dd6a95ed8f4e3282aa9f8e620b3de8b390aad3e5ef7257bb503b4d57186', $evidence['checkedInFixtures'][103]['checkedInFile']['sha256']);
        $t->same(35, $evidence['checkedInFixtures'][103]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][103]['sourceKind']);
        $t->same('block-structure', $evidence['readerDenominator']['selectedFixtures'][103]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-button-scope-tree-construction.native', $evidence['checkedInFixtures'][103]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][103]['checkedInNativePairFile']['present']);
        $t->same('b85b3b0aa77d951365aa4ae1833761408fdeda477f63d7333f8df2a1504efa29', $evidence['checkedInFixtures'][103]['checkedInNativePairFile']['sha256']);
        $t->same(49, $evidence['checkedInFixtures'][103]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][103]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-paragraph-pre-tree-construction.html', $evidence['checkedInFixtures'][104]['name']);
        $t->same('8eceeeecbd143851572f8ed7c7ad5dd26ae61c60ebea20b08242d3770d344698', $evidence['checkedInFixtures'][104]['checkedInFile']['sha256']);
        $t->same(35, $evidence['checkedInFixtures'][104]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][104]['sourceKind']);
        $t->same('block-structure', $evidence['readerDenominator']['selectedFixtures'][104]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-paragraph-pre-tree-construction.native', $evidence['checkedInFixtures'][104]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][104]['checkedInNativePairFile']['present']);
        $t->same('bbedf6769c500dcde2eeb8358b3bcd901caf26fa14b7b79295f18635fe56f8d6', $evidence['checkedInFixtures'][104]['checkedInNativePairFile']['sha256']);
        $t->same(85, $evidence['checkedInFixtures'][104]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][104]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-standalone-output-inline.html', $evidence['checkedInFixtures'][105]['name']);
        $t->same('b7cdf8a4d74cdf334c8ed55dec1e96adb2af9b9f680d72b2ab33a9f2d603dba1', $evidence['checkedInFixtures'][105]['checkedInFile']['sha256']);
        $t->same(47, $evidence['checkedInFixtures'][105]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][105]['sourceKind']);
        $t->same('standalone-inline-html', $evidence['readerDenominator']['selectedFixtures'][105]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-standalone-output-inline.native', $evidence['checkedInFixtures'][105]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][105]['checkedInNativePairFile']['present']);
        $t->same('41edab594ea41c523be7a9f20ddde84892e3fe0da6d7e93a5eb8b29388827568', $evidence['checkedInFixtures'][105]['checkedInNativePairFile']['sha256']);
        $t->same(60, $evidence['checkedInFixtures'][105]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][105]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-standalone-cite-inline.html', $evidence['checkedInFixtures'][106]['name']);
        $t->same('4ec967a951f951b47c64d1d8a500750b2a37d2e341ecaf7f89f43af1826996c5', $evidence['checkedInFixtures'][106]['checkedInFile']['sha256']);
        $t->same(18, $evidence['checkedInFixtures'][106]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][106]['sourceKind']);
        $t->same('standalone-inline-html', $evidence['readerDenominator']['selectedFixtures'][106]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-standalone-cite-inline.native', $evidence['checkedInFixtures'][106]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][106]['checkedInNativePairFile']['present']);
        $t->same('12664bb917f3c7e267ef9046dac2d9075b8cb58c05184d0189f9c088fc44b9ca', $evidence['checkedInFixtures'][106]['checkedInNativePairFile']['sha256']);
        $t->same(25, $evidence['checkedInFixtures'][106]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][106]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-paragraph-nav-tree-construction.html', $evidence['checkedInFixtures'][107]['name']);
        $t->same('5fcd29ba0fc60c738e55a2dcf28733fd75777816ccfc68d3541eb7c0a8669813', $evidence['checkedInFixtures'][107]['checkedInFile']['sha256']);
        $t->same(38, $evidence['checkedInFixtures'][107]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][107]['sourceKind']);
        $t->same('block-structure', $evidence['readerDenominator']['selectedFixtures'][107]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-paragraph-nav-tree-construction.native', $evidence['checkedInFixtures'][107]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][107]['checkedInNativePairFile']['present']);
        $t->same('31f593476fb9fd8593042ee52390953dae64bab7657b6ee44b1750f043739693', $evidence['checkedInFixtures'][107]['checkedInNativePairFile']['sha256']);
        $t->same(72, $evidence['checkedInFixtures'][107]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][107]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-paragraph-blockquote-tree-construction.html', $evidence['checkedInFixtures'][108]['name']);
        $t->same('bfbe9978a048677c0c48347d3940525725a20ef3516be4fe568cbad495c6edd4', $evidence['checkedInFixtures'][108]['checkedInFile']['sha256']);
        $t->same(53, $evidence['checkedInFixtures'][108]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][108]['sourceKind']);
        $t->same('block-structure', $evidence['readerDenominator']['selectedFixtures'][108]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-paragraph-blockquote-tree-construction.native', $evidence['checkedInFixtures'][108]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][108]['checkedInNativePairFile']['present']);
        $t->same('bcf0f70f53f22592168712d7856b7f807371270a19e42b147cf6d17d736e122d', $evidence['checkedInFixtures'][108]['checkedInNativePairFile']['sha256']);
        $t->same(88, $evidence['checkedInFixtures'][108]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][108]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-optional-option-tree-construction.html', $evidence['checkedInFixtures'][109]['name']);
        $t->same('df80d126a1a670f800dd1f6b25e8bf30441234999f3646aabce566bd6e5ae8f5', $evidence['checkedInFixtures'][109]['checkedInFile']['sha256']);
        $t->same(66, $evidence['checkedInFixtures'][109]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][109]['sourceKind']);
        $t->same('inline-and-metadata', $evidence['readerDenominator']['selectedFixtures'][109]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-optional-option-tree-construction.native', $evidence['checkedInFixtures'][109]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][109]['checkedInNativePairFile']['present']);
        $t->same('d8d44ded5b2a305d3cdf544a94befd9ec77100ef8899a15189e43cb5284d5cee', $evidence['checkedInFixtures'][109]['checkedInNativePairFile']['sha256']);
        $t->same(92, $evidence['checkedInFixtures'][109]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][109]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-form-in-form-tree-construction.html', $evidence['checkedInFixtures'][110]['name']);
        $t->same('de14a5e938bbf2a0637dad444c7f219b5e630f848de93bf194364781ce7d9397', $evidence['checkedInFixtures'][110]['checkedInFile']['sha256']);
        $t->same(47, $evidence['checkedInFixtures'][110]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][110]['sourceKind']);
        $t->same('block-structure', $evidence['readerDenominator']['selectedFixtures'][110]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-form-in-form-tree-construction.native', $evidence['checkedInFixtures'][110]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][110]['checkedInNativePairFile']['present']);
        $t->same('f051b411ddffd107c15e40450fd146482d588496c6e9ef5891edd226c21ea848', $evidence['checkedInFixtures'][110]['checkedInNativePairFile']['sha256']);
        $t->same(67, $evidence['checkedInFixtures'][110]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][110]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-paragraph-aside-tree-construction.html', $evidence['checkedInFixtures'][111]['name']);
        $t->same('41a787852aa67f877da07fbfbfd8d6d7b8d93e0a7f66af041085aa06349025f1', $evidence['checkedInFixtures'][111]['checkedInFile']['sha256']);
        $t->same(109, $evidence['checkedInFixtures'][111]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][111]['sourceKind']);
        $t->same('block-structure', $evidence['readerDenominator']['selectedFixtures'][111]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-paragraph-aside-tree-construction.native', $evidence['checkedInFixtures'][111]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][111]['checkedInNativePairFile']['present']);
        $t->same('258568122cc50aea63fd1aeb09a9844ca5928869c53ec70bd1255e22a915afb9', $evidence['checkedInFixtures'][111]['checkedInNativePairFile']['sha256']);
        $t->same(147, $evidence['checkedInFixtures'][111]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][111]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-main-followed-by-text.html', $evidence['checkedInFixtures'][112]['name']);
        $t->same('347f48d34d40488fa5a4167149c855fa2b5d1c6be5380216588237fa6077a2ca', $evidence['checkedInFixtures'][112]['checkedInFile']['sha256']);
        $t->same(42, $evidence['checkedInFixtures'][112]['checkedInFile']['bytes']);
        $t->same('selected-upstream-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][112]['sourceKind']);
        $t->same('inline-and-metadata', $evidence['readerDenominator']['selectedFixtures'][112]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-main-followed-by-text.native', $evidence['checkedInFixtures'][112]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][112]['checkedInNativePairFile']['present']);
        $t->same('729dc04964094411a3064bdd4d544375c95a285218afb1f60f9113d0179d049a', $evidence['checkedInFixtures'][112]['checkedInNativePairFile']['sha256']);
        $t->same(49, $evidence['checkedInFixtures'][112]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][112]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-paragraph-article-tree-construction.html', $evidence['checkedInFixtures'][113]['name']);
        $t->same('ddb92874e0d138288a79fdcaef96c22bcf7f22d11af9009e4abd30e97207465f', $evidence['checkedInFixtures'][113]['checkedInFile']['sha256']);
        $t->same(113, $evidence['checkedInFixtures'][113]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][113]['sourceKind']);
        $t->same('block-structure', $evidence['readerDenominator']['selectedFixtures'][113]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-paragraph-article-tree-construction.native', $evidence['checkedInFixtures'][113]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][113]['checkedInNativePairFile']['present']);
        $t->same('b3be40b63cdccee749d97cdb9ddd08e6746cf5cdb369c016fb8bf669b8818e53', $evidence['checkedInFixtures'][113]['checkedInNativePairFile']['sha256']);
        $t->same(108, $evidence['checkedInFixtures'][113]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][113]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-empty-tables.html', $evidence['checkedInFixtures'][114]['name']);
        $t->same('cbf99b248f5c21a807bf9f98481a0291ef4e946f66311a4e2769a23dde5d92c7', $evidence['checkedInFixtures'][114]['checkedInFile']['sha256']);
        $t->same(118, $evidence['checkedInFixtures'][114]['checkedInFile']['bytes']);
        $t->same('selected-upstream-html-reader-fixture', $evidence['readerDenominator']['selectedFixtures'][114]['sourceKind']);
        $t->same('block-structure', $evidence['readerDenominator']['selectedFixtures'][114]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-empty-tables.native', $evidence['checkedInFixtures'][114]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][114]['checkedInNativePairFile']['present']);
        $t->same('9acd2caf45db2c07d5f2a074a6a8b5d2a7a1aa0b79e5ac10339c9284ec3aa4b6', $evidence['checkedInFixtures'][114]['checkedInNativePairFile']['sha256']);
        $t->same(244, $evidence['checkedInFixtures'][114]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][114]['localTestReferenceCount'] >= 1);
        $t->same('upstream-html-standalone-code-aliases-inline.html', $evidence['checkedInFixtures'][116]['name']);
        $t->same('2f77158fc9d147540d829e3414b049c9f754db38291ff523fc73dbfc3a852d9f', $evidence['checkedInFixtures'][116]['checkedInFile']['sha256']);
        $t->same(93, $evidence['checkedInFixtures'][116]['checkedInFile']['bytes']);
        $t->same('direct-pandoc-3.10-native-probe', $evidence['readerDenominator']['selectedFixtures'][116]['sourceKind']);
        $t->same('standalone-inline-html', $evidence['readerDenominator']['selectedFixtures'][116]['category']);
        $t->same('lanes/pandoc/fixtures/upstream-html-standalone-code-aliases-inline.native', $evidence['checkedInFixtures'][116]['checkedInNativePairFile']['path']);
        $t->same(true, $evidence['checkedInFixtures'][116]['checkedInNativePairFile']['present']);
        $t->same('1f0f5399da8103a65c9c6a76869a1b1eebb5da1fd76d936f519e756f6875db6c', $evidence['checkedInFixtures'][116]['checkedInNativePairFile']['sha256']);
        $t->same(208, $evidence['checkedInFixtures'][116]['checkedInNativePairFile']['bytes']);
        $t->true($evidence['checkedInFixtures'][116]['localTestReferenceCount'] >= 1);
        $t->same('valid-checked-in-current-html-reader-evidence', $evidence['validation']['status']);
        $t->same([], $evidence['validation']['issues']);
        $t->true(in_array('each pinned HTML fixture has a same-basename checked-in native expectation file', $evidence['claimBoundaries']['doesAssert'], true));
        $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $evidence['claimBoundaries']['doesNotAssert'], true));
    },

    'rejects hydrated upstream html reader source evidence without pinned git head' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeHtmlEvidenceTree): void {
        $repoRoot = dirname(__DIR__, 3);
        $root = $makeTempDir();
        try {
            $writeHtmlEvidenceTree($root);
            $report = (new HtmlUpstreamReaderEvidence($repoRoot, $root))->report();

            $t->same(HtmlUpstreamReaderEvidence::STATUS_COMPLETED, $report['status']);
            $t->same(null, $report['upstream']['commit']);
            $t->same('invalid-upstream-html-reader-evidence', $report['validation']['status']);
            $t->same(['upstream-html-reader-commit-mismatch'], $report['validation']['issues']);
            $t->same(117, $report['denominator']['selectedFixtureCount']);
            $t->same(2, $report['sourceInventory']['presentFileCount']);
            $t->same(0, $report['sourceInventory']['missingFileCount']);
            $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
            $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredNativeMappedParity($report, 117));
            $t->same(true, HtmlUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same(true, HtmlUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
            $t->same(false, HtmlUpstreamReaderEvidence::hasNoValidationIssues($report));
        } finally {
            $removeTree($root);
        }
    },

    'validates hydrated upstream html reader source evidence at expected commit' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeHtmlEvidenceTree, $writeGitHead): void {
        $repoRoot = dirname(__DIR__, 3);
        $root = $makeTempDir();
        try {
            $writeHtmlEvidenceTree($root);
            $writeGitHead($root, HtmlUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT);
            $report = (new HtmlUpstreamReaderEvidence($repoRoot, $root))->report();

            $t->same(HtmlUpstreamReaderEvidence::STATUS_COMPLETED, $report['status']);
            $t->same(HtmlUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $report['upstream']['commit']);
            $t->same('valid-upstream-html-reader-evidence', $report['validation']['status']);
            $t->same([], $report['validation']['issues']);
            $t->same(117, $report['denominator']['selectedFixtureCount']);
            $t->same(2, $report['sourceInventory']['presentFileCount']);
            $t->same(0, $report['sourceInventory']['missingFileCount']);
            $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
            $t->same(true, HtmlUpstreamReaderEvidence::hasRequiredNativeMappedParity($report, 117));
            $t->same(true, HtmlUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same(true, HtmlUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
            $t->same(true, HtmlUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->true(in_array('full upstream Tests.Readers.HTML runner parity', $report['claimBoundaries']['doesNotAssert'], true));
            $t->true(in_array('the future upstream runner command plan targets test:test-pandoc Readers/HTML at the pinned upstream commit without execution', $report['claimBoundaries']['doesAssert'], true));
        } finally {
            $removeTree($root);
        }
    },

    'validates supplied html reader upstream runner result artifact' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writeHtmlEvidenceTree, $writeRunnerTranscripts): void {
        $root = $makeTempDir();
        try {
            $writeHtmlEvidenceTree($root);
            $baseReport = (new HtmlUpstreamReaderEvidence($root, $root))->report();
            $runnerPlan = $baseReport['runnerEvidence'];
            $transcripts = $writeRunnerTranscripts($root, $runnerPlan['requiredTranscripts']);
            $testNames = array_map(
                static fn (array $fixture): string => $fixture['name'],
                $baseReport['denominator']['selectedFixtures']
            );
            $payload = [
                'schemaVersion' => 2,
                'runner' => 'Cabal/Tasty Pandoc HTML reader suite',
                'runnerExecuted' => true,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'commit' => HtmlUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT,
                ],
                'target' => $runnerPlan['target'],
                'command' => $runnerPlan['futureCommands'][2],
                'exitCode' => 0,
                'testCount' => count($testNames),
                'passedCount' => count($testNames),
                'failedCount' => 0,
                'skippedCount' => 0,
                'testNames' => $testNames,
                'transcriptPaths' => $runnerPlan['requiredTranscripts'],
                'transcripts' => $transcripts,
            ];
            $validPayload = $payload;
            $writeFile($root, 'result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $artifactPath = $root . '/result.json';
            $report = (new HtmlUpstreamReaderEvidence($root, $root, $artifactPath))->report();
            $text = HtmlUpstreamReaderEvidence::formatTextReport($report);

            $t->same('completed', $report['runnerEvidence']['status']);
            $t->same(true, $report['runnerEvidence']['executed']);
            $t->same('runner-result-artifact-validated', $report['runnerEvidence']['commandPlanStatus']);
            $t->same('valid-upstream-html-reader-runner-result-artifact', $report['runnerEvidence']['validation']['status']);
            $t->same([], $report['runnerEvidence']['validation']['issues']);
            $t->same('upstream-html-reader-runner-result-artifact', $report['runnerEvidence']['resultArtifact']['kind']);
            $t->same(true, $report['runnerEvidence']['resultArtifact']['present']);
            $t->same(hash_file('sha256', $artifactPath), $report['runnerEvidence']['resultArtifact']['sha256']);
            $t->same(filesize($artifactPath), $report['runnerEvidence']['resultArtifact']['bytes']);
            $t->same(HtmlUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $report['runnerEvidence']['upstreamBinding']['observedCommit']);
            $t->same($runnerPlan['target'], $report['runnerEvidence']['target']);
            $t->same($runnerPlan['futureCommands'][2], $report['runnerEvidence']['command']);
            $t->same($testNames, $report['runnerEvidence']['observed']['testNames']);
            $t->same($runnerPlan['requiredTranscripts'], $report['runnerEvidence']['observed']['transcriptPaths']);
            $t->same($transcripts, $report['runnerEvidence']['observed']['transcripts']);
            $t->same($transcripts, $report['runnerEvidence']['expected']['transcripts']);
            $t->same('upstream-html-reader-runner-transcript', $report['runnerEvidence']['transcripts'][0]['kind']);
            $t->same(true, $report['runnerEvidence']['transcripts'][0]['present']);
            $t->same(true, HtmlUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($report));
            $t->same(false, HtmlUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
            $t->same(false, HtmlUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
            $t->contains('Runner status: completed', $text);
            $t->contains('Runner plan: runner-result-artifact-validated', $text);
            $t->contains('Runner result artifact: valid-upstream-html-reader-runner-result-artifact', $text);
            $t->contains('Supplied upstream Haskell/Cabal runner result artifact is validated', $text);

            $payload = $validPayload;
            $payload['failedCount'] = 1;
            $payload['exitCode'] = 1;
            $writeFile($root, 'bad-result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $badReport = (new HtmlUpstreamReaderEvidence($root, $root, $root . '/bad-result.json'))->report();

            $t->same('invalid', $badReport['runnerEvidence']['status']);
            $t->same('invalid-upstream-html-reader-runner-result-artifact', $badReport['runnerEvidence']['validation']['status']);
            $t->true(in_array('runner-result-exit-code-nonzero', $badReport['runnerEvidence']['validation']['issues'], true));
            $t->true(in_array('runner-result-counts-mismatch', $badReport['runnerEvidence']['validation']['issues'], true));
            $t->same(false, HtmlUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($badReport));

            $badTranscriptPayload = $validPayload;
            $badTranscriptPayload['transcripts'][0]['bytes'] = 0;
            $writeFile($root, 'bad-transcript-result.json', json_encode($badTranscriptPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $badTranscriptReport = (new HtmlUpstreamReaderEvidence($root, $root, $root . '/bad-transcript-result.json'))->report();

            $t->same('invalid', $badTranscriptReport['runnerEvidence']['status']);
            $t->true(in_array('runner-result-transcript-bytes-mismatch', $badTranscriptReport['runnerEvidence']['validation']['issues'], true));
            $t->same(false, HtmlUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($badTranscriptReport));
        } finally {
            $removeTree($root);
        }
    },

    'cli gates checked-in current html fixture evidence without hydrated upstream cache' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($repoRoot . '/tools/pandoc-html-reader-evidence.php')
            . ' --repo-root=' . escapeshellarg($repoRoot)
            . ' --checked-in-fixtures'
            . ' --json'
            . ' --require-selected-fixture-count=117'
            . ' --require-static-current-evidence'
            . ' --require-native-mapped-parity=117'
            . ' --require-runner-not-run'
            . ' --require-runner-plan';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(HtmlUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $decoded['status']);
        $t->same(117, $decoded['staticCurrentEvidence']['readerDenominator']['selectedFixtureCount']);
        $t->same('valid-checked-in-current-html-reader-evidence', $decoded['staticCurrentEvidence']['validation']['status']);
        $t->same(117, $decoded['staticCurrentEvidence']['checkedInNativePairCount']);
        $t->same(117, $decoded['nativeAstEvidence']['normalizedAstMatchCount']);
        $t->same(0, $decoded['nativeAstEvidence']['excludedMappedPairCount']);
        $t->same('not-run', $decoded['runnerEvidence']['status']);
        $t->same('planned-not-run', $decoded['runnerEvidence']['commandPlanStatus']);
        $t->same('$2 == "Readers" && $3 == "HTML"', $decoded['runnerEvidence']['target']['tastyPattern']);

        $failingCommand = str_replace('--require-selected-fixture-count=117', '--require-selected-fixture-count=85', $command) . ' 2>/dev/null';
        $failingOutput = [];
        $failingExitCode = 0;
        exec($failingCommand, $failingOutput, $failingExitCode);

        $t->same(1, $failingExitCode);

        $conflictingCommand = str_replace('--checked-in-fixtures', '--checked-in-fixtures --upstream-root=missing-upstream-root-for-static-html-gate', $command) . ' 2>/dev/null';
        $conflictingOutput = [];
        $conflictingExitCode = 0;
        exec($conflictingCommand, $conflictingOutput, $conflictingExitCode);

        $t->same(2, $conflictingExitCode);
    },

    'cli gates supplied html reader upstream runner result artifact' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writeHtmlEvidenceTree, $writeRunnerTranscripts): void {
        $root = $makeTempDir();
        try {
            $writeHtmlEvidenceTree($root);
            $baseReport = (new HtmlUpstreamReaderEvidence($root, $root))->report();
            $runnerPlan = $baseReport['runnerEvidence'];
            $transcripts = $writeRunnerTranscripts($root, $runnerPlan['requiredTranscripts']);
            $testNames = array_map(
                static fn (array $fixture): string => $fixture['name'],
                $baseReport['denominator']['selectedFixtures']
            );
            $payload = [
                'schemaVersion' => 2,
                'runner' => 'Cabal/Tasty Pandoc HTML reader suite',
                'runnerExecuted' => true,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'commit' => HtmlUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT,
                ],
                'target' => $runnerPlan['target'],
                'command' => $runnerPlan['futureCommands'][2],
                'exitCode' => 0,
                'testCount' => count($testNames),
                'passedCount' => count($testNames),
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
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-html-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg($root)
                . ' --upstream-root=' . escapeshellarg($root)
                . ' --runner-result-artifact=' . escapeshellarg($root . '/result.json')
                . ' --json'
                . ' --require-runner-result-artifact';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same('completed', $decoded['runnerEvidence']['status']);
            $t->same('valid-upstream-html-reader-runner-result-artifact', $decoded['runnerEvidence']['validation']['status']);
            $t->same(true, HtmlUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($decoded));

            $payload = $validPayload;
            $payload['target']['tastyPattern'] = '$2 == "Readers" && $3 == "Markdown"';
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

    'generic reader runner artifact tool writes and validates html result artifact' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeHtmlEvidenceTree, $writeRunnerTranscripts): void {
        $root = $makeTempDir();
        try {
            $writeHtmlEvidenceTree($root);
            $baseReport = (new HtmlUpstreamReaderEvidence($root, $root))->report();
            $runnerPlan = $baseReport['runnerEvidence'];
            $testNames = array_map(
                static fn (array $fixture): string => $fixture['name'],
                $baseReport['denominator']['selectedFixtures']
            );
            $writeRunnerTranscripts($root, $runnerPlan['requiredTranscripts'], $testNames);

            $artifactPath = '.port-libs/pandoc-runner/artifacts/html-targeted-run/result.json';
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-reader-runner-artifact.php')
                . ' --repo-root=' . escapeshellarg($root)
                . ' --upstream-root=' . escapeshellarg($root)
                . ' --format=html'
                . ' --write-result-artifact=' . escapeshellarg($artifactPath)
                . ' --result-started-at-utc=2026-07-05T00:00:00Z'
                . ' --result-finished-at-utc=2026-07-05T00:00:01Z'
                . ' --require-valid-result-artifact'
                . ' --json';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
            $writtenArtifact = $root . '/' . $artifactPath;
            $payload = json_decode((string) file_get_contents($writtenArtifact), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same('pandoc-reader-runner-artifact', $decoded['tool']);
            $t->same('html', $decoded['format']);
            $t->same('runner-result-artifact-valid', $decoded['status']);
            $t->same('valid-upstream-html-reader-runner-result-artifact', $decoded['validation']['status']);
            $t->same(true, $decoded['resultArtifact']['written']);
            $t->same($artifactPath, $decoded['resultArtifact']['path']);
            $t->same($testNames, $decoded['expectedTestNames']);
            $t->same(2, $payload['schemaVersion']);
            $t->same('Cabal/Tasty Pandoc HTML reader suite', $payload['runner']);
            $t->same(true, $payload['runnerExecuted']);
            $t->same(count($testNames), $payload['testCount']);
            $t->same(count($testNames), $payload['passedCount']);
            $t->same(0, $payload['failedCount']);
            $t->same('valid-targeted-runner-transcripts', $payload['transcriptEvidence']['status']);
            $t->same(count($testNames), $decoded['resultArtifact']['payload']['testCount']);
            $t->same(count($testNames), $decoded['resultArtifact']['payload']['passedCount']);
            $t->same(0, $decoded['resultArtifact']['payload']['failedCount']);
            $t->same($runnerPlan['futureCommands'][2], $payload['command']);
            $t->same($runnerPlan['requiredTranscripts'], $payload['transcriptPaths']);
        } finally {
            $removeTree($root);
        }
    },

    'workflow gate keeps html fixture denominator wiring' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $workflow = file_get_contents($repoRoot . '/.github/workflows/pandoc-html-delimited.yml');
        if ($workflow === false) {
            throw new RuntimeException('Unable to read pandoc-html-delimited workflow');
        }

        $t->contains('--require-selected-fixture-count=117', $workflow);
        $t->contains('--require-native-mapped-parity=117', $workflow);
        $t->contains('--require-mapped-parity=117', $workflow);
        $flags = [
            '--require-selected-fixture-count=',
            '--require-native-mapped-parity=',
            '--require-mapped-parity=',
        ];
        foreach ([114, 112, 107, 103, 102, 88, 62] as $staleCount) {
            foreach ($flags as $flag) {
                $t->true(!str_contains($workflow, $flag . (string) $staleCount));
            }
        }
    },

    'cli rejects hydrated html source evidence without expected upstream commit' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeHtmlEvidenceTree): void {
        $repoRoot = dirname(__DIR__, 3);
        $root = $makeTempDir();
        try {
            $writeHtmlEvidenceTree($root);
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg($repoRoot . '/tools/pandoc-html-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg($repoRoot)
                . ' --upstream-root=' . escapeshellarg($root)
                . ' --json'
                . ' --require-no-validation-issues'
                . ' 2>/dev/null';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(1, $exitCode);
            $t->same('invalid-upstream-html-reader-evidence', $decoded['validation']['status']);
            $t->same(['upstream-html-reader-commit-mismatch'], $decoded['validation']['issues']);
        } finally {
            $removeTree($root);
        }
    },
];
