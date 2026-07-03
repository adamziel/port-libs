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
            $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $report['claimBoundaries']['doesNotAssert'], true));
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

        $t->same(EpubUpstreamReaderEvidence::STATUS_COMPLETED, $report['status']);
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
            'epub/auxiliary-lot-guide-index.epub',
            'epub/direct-image-spine.epub',
            'epub/features.epub',
            'epub/font-manifest-resource.epub',
            'epub/formatting.epub',
            'epub/guide-bibliography-reference.epub',
            'epub/guide-glossary-reference.epub',
            'epub/guide-notes-reference.epub',
            'epub/guide-preface-reference.epub',
            'epub/manifest-fallback-chain.epub',
            'epub/media-manifest-mix.epub',
            'epub/media-overlay-package.epub',
            'epub/missing-local-manifest-resource.epub',
            'epub/missing-media-overlay.epub',
            'epub/nav-ncx-linear-guide.epub',
            'epub/nested-rootfile-nonlinear-spine.epub',
            'epub/page-list-navigation.epub',
            'epub/remote-manifest-resource.epub',
            'epub/rendition-layout-property.epub',
            'epub/scripted-svg-manifest.epub',
            'epub/scripted-xhtml-resource.epub',
            'epub/video-manifest-resource.epub',
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
        $t->same(true, EpubUpstreamReaderEvidence::hasNoValidationIssues($report));
        $t->same(true, EpubUpstreamReaderEvidence::hasRunnerNotRunEvidence($report));
        $t->same(true, EpubUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
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

    'cli gates checked-in current epub reader evidence through checked-in fixtures mode' => static function (TestRunner $t) use ($repoRoot, $checkedInFixtureRoot, $expectedCurrentReaderStaticSignatureSha256, $expectedReferencedFixtureIdentity): void {
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-reader-evidence.php')
            . ' --repo-root=' . escapeshellarg($repoRoot())
            . ' --checked-in-fixtures'
            . ' --json'
            . ' --require-test-count=6'
            . ' --require-fixture-reference-count=6'
            . ' --require-expected-media-item-count=10'
            . ' --require-referenced-fixture-identity'
            . ' --require-static-current-signature'
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
        $t->same(true, EpubUpstreamReaderEvidence::hasRunnerNotRunEvidence($decoded));
        $t->same(true, EpubUpstreamReaderEvidence::hasRunnerPlanEvidence($decoded));
        $t->same('not-run', $decoded['runnerEvidence']['status']);
        $t->same('$2 == "Readers" && $3 == "EPUB" && $4 == "EPUB Mediabag"', $decoded['runnerEvidence']['target']['tastyPattern']);
        $t->true(in_array('.port-libs/pandoc-runner/logs/epub-targeted-list-tests.txt', $decoded['runnerEvidence']['requiredTranscripts'], true));
        $t->true(in_array('full EPUB feature parity beyond the upstream reader media-bag tests', $decoded['claimBoundaries']['doesNotAssert'], true));
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
