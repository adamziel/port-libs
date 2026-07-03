<?php

declare(strict_types=1);

use PortLibs\Pandoc\EpubMediaBagComparisonHarness;
use PortLibs\Pandoc\EpubUpstreamReaderEvidence;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-epub-media-bag-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary EPUB media-bag directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary EPUB media-bag directory {$base}");
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

$fixtureRoot = static fn (): string => dirname(__DIR__) . '/fixtures/upstream-current-epub-reader';

$currentEpubReaderSource = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-current-epub-reader/test/Tests/Readers/EPUB.hs'
);

$currentReaderCases = static fn (): array => EpubUpstreamReaderEvidence::parseReaderCasesFromSource($currentEpubReaderSource());

$currentMediaBagSignatures = static fn (): array => [
    [
        'case' => 'features bag',
        'fixture' => 'epub/img.epub',
        'expectedItemCount' => 4,
        'actualItemCount' => 4,
        'matchesExpected' => true,
        'expectedBag' => [
            ['path' => 'img/check.gif', 'mime' => 'image/gif', 'size' => 1340],
            ['path' => 'img/check.jpg', 'mime' => 'image/jpeg', 'size' => 2661],
            ['path' => 'img/check.png', 'mime' => 'image/png', 'size' => 2815],
            ['path' => 'img/multiscripts_and_greek_alphabet.png', 'mime' => 'image/png', 'size' => 10060],
        ],
        'actualBag' => [
            ['path' => 'img/check.gif', 'mime' => 'image/gif', 'size' => 1340],
            ['path' => 'img/check.jpg', 'mime' => 'image/jpeg', 'size' => 2661],
            ['path' => 'img/check.png', 'mime' => 'image/png', 'size' => 2815],
            ['path' => 'img/multiscripts_and_greek_alphabet.png', 'mime' => 'image/png', 'size' => 10060],
        ],
    ],
    [
        'case' => 'EPUB3 cover bag',
        'fixture' => 'epub/wasteland.epub',
        'expectedItemCount' => 1,
        'actualItemCount' => 1,
        'matchesExpected' => true,
        'expectedBag' => [
            ['path' => 'wasteland-cover.jpg', 'mime' => 'image/jpeg', 'size' => 16586],
        ],
        'actualBag' => [
            ['path' => 'wasteland-cover.jpg', 'mime' => 'image/jpeg', 'size' => 16586],
        ],
    ],
    [
        'case' => 'EPUB3 no cover bag',
        'fixture' => 'epub/img_no_cover.epub',
        'expectedItemCount' => 3,
        'actualItemCount' => 3,
        'matchesExpected' => true,
        'expectedBag' => [
            ['path' => 'img/check.gif', 'mime' => 'image/gif', 'size' => 1340],
            ['path' => 'img/check.jpg', 'mime' => 'image/jpeg', 'size' => 2661],
            ['path' => 'img/check.png', 'mime' => 'image/png', 'size' => 2815],
        ],
        'actualBag' => [
            ['path' => 'img/check.gif', 'mime' => 'image/gif', 'size' => 1340],
            ['path' => 'img/check.jpg', 'mime' => 'image/jpeg', 'size' => 2661],
            ['path' => 'img/check.png', 'mime' => 'image/png', 'size' => 2815],
        ],
    ],
    [
        'case' => 'EPUB2 picture bag',
        'fixture' => 'epub/epub2_picture.epub',
        'expectedItemCount' => 1,
        'actualItemCount' => 1,
        'matchesExpected' => true,
        'expectedBag' => [
            ['path' => 'image/image.jpg', 'mime' => 'image/jpeg', 'size' => 9713],
        ],
        'actualBag' => [
            ['path' => 'image/image.jpg', 'mime' => 'image/jpeg', 'size' => 9713],
        ],
    ],
    [
        'case' => 'EPUB2 cover bag',
        'fixture' => 'epub/epub2_cover.epub',
        'expectedItemCount' => 1,
        'actualItemCount' => 1,
        'matchesExpected' => true,
        'expectedBag' => [
            ['path' => 'image/cover.jpg', 'mime' => 'image/jpeg', 'size' => 9713],
        ],
        'actualBag' => [
            ['path' => 'image/cover.jpg', 'mime' => 'image/jpeg', 'size' => 9713],
        ],
    ],
    [
        'case' => 'EPUB2 no cover bag',
        'fixture' => 'epub/epub2_no_cover.epub',
        'expectedItemCount' => 0,
        'actualItemCount' => 0,
        'matchesExpected' => true,
        'expectedBag' => [],
        'actualBag' => [],
    ],
];

$currentMediaBagSignatureSha256 = static fn (): string => '48e9d4d6c7478aa213f3d75fc4cd1a2be58e2617d468d30d9027728d0258ce9d';

$assertCurrentMediaBagSignature = static function (TestRunner $t, array $signature) use ($currentMediaBagSignatureSha256): void {
    $t->same('checked-in-current-epub-media-bag-signature', $signature['kind']);
    $t->same('sha256-canonical-json-v1', $signature['algorithm']);
    $t->same('checked-in-current-upstream-epub-reader-6-case-media-bag-snapshot', $signature['scope']);
    $t->same(1, $signature['snapshotSchemaVersion']);
    $t->same(6, $signature['caseCount']);
    $t->same(6, $signature['expectedCaseCount']);
    $t->same(10, $signature['expectedMediaItemCount']);
    $t->same(10, $signature['actualMediaItemCount']);
    $t->same(6, $signature['mediaBagMatchCount']);
    $t->same(0, $signature['mediaBagMismatchCount']);
    $t->same($currentMediaBagSignatureSha256(), $signature['sha256']);
    $t->same($currentMediaBagSignatureSha256(), $signature['expectedSha256']);
    $t->same(true, $signature['hashMatchesExpected']);
    $t->same(true, $signature['matchesExpected']);
    $t->same('valid-checked-in-current-epub-media-bag-signature', $signature['validation']['status']);
    $t->same([], $signature['validation']['issues']);
    $t->same(true, $signature['validation']['caseSignaturesMatchExpected']);
    $t->same(true, $signature['validation']['countsMatchExpected']);
};

$writeCurrentFixtureTree = static function (string $root) use ($writeFile, $fixtureRoot, $currentEpubReaderSource): void {
    $writeFile($root, 'test/Tests/Readers/EPUB.hs', $currentEpubReaderSource());
    $writeFile($root, 'src/Text/Pandoc/Readers/EPUB.hs', "module Text.Pandoc.Readers.EPUB where\n");
    $sourceDir = $fixtureRoot() . '/epub';
    foreach ([
        'img.epub',
        'wasteland.epub',
        'img_no_cover.epub',
        'epub2_picture.epub',
        'epub2_cover.epub',
        'epub2_no_cover.epub',
    ] as $file) {
        $target = $root . '/test/epub/' . $file;
        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0777, true) && !is_dir(dirname($target))) {
            throw new RuntimeException('Unable to create EPUB fixture tree');
        }
        copy($sourceDir . '/' . $file, $target);
    }
};

$writeRunnerTranscripts = static function (string $root, array $paths) use ($writeFile): array {
    $records = [];
    foreach (array_values($paths) as $index => $path) {
        $contents = 'epub media bag runner transcript ' . (string) ($index + 1) . "\n" . $path . "\n";
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

$writeScopedMediaBagEpub = static function (string $path): array {
    $usedImage = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
    $unusedImage = base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==');
    if (!is_string($usedImage) || !is_string($unusedImage)) {
        throw new RuntimeException('Unable to decode scoped media-bag fixture images');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create scoped media-bag EPUB package');
    }
    $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
    $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-scoped-media-bag</dc:identifier>
    <dc:title>Scoped Media Bag</dc:title>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="used" href="images/used.png" media-type="image/png"/>
    <item id="unused" href="images/unused.gif" media-type="image/gif"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
    $zip->addFromString('OPS/chapter.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body><p><img src="images/used.png" alt="Used"/></p></body>
</html>
HTML);
    $zip->addFromString('OPS/images/used.png', $usedImage);
    $zip->addFromString('OPS/images/unused.gif', $unusedImage);
    $zip->close();

    return [
        'usedImageBytes' => strlen($usedImage),
        'unusedImageBytes' => strlen($unusedImage),
    ];
};

return [
    'skips epub media bag comparison when cache is absent' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        $missing = $root . '/missing';

        try {
            $harness = new EpubMediaBagComparisonHarness();
            $report = $harness->run($missing);
            $text = $harness->formatReport($report);

            $t->same(1, $report['schemaVersion']);
            $t->same('pandoc-epub-media-bag', $report['tool']);
            $t->same('skipped', $report['status']);
            $t->same(true, $report['skipped']);
            $t->same('upstream-cache-missing', $report['reason']);
            $t->same('media-bag-comparison-not-full-epub-parity', $report['verdict']);
            $t->same(0, $report['comparedCaseCount']);
            $t->same(0, $report['mediaBagMatchCount']);
            $t->same('not-evaluated-source-directory-unavailable', $report['mediaBagParityStatus']);
            $t->same([], $report['mediaBagSignatures']);
            $t->same('not-evaluated-source-directory-unavailable', $report['currentMediaBagSignature']['validation']['status']);
            $t->same(null, $report['currentMediaBagSignature']['sha256']);
            $t->same(false, EpubMediaBagComparisonHarness::hasRequiredCurrentMediaBagSignature($report));
            $t->same(true, EpubMediaBagComparisonHarness::hasRunnerNotRunEvidence($report));
            $t->same(true, EpubMediaBagComparisonHarness::hasRunnerPlanEvidence($report));
            $t->same('not-run', $report['runnerEvidence']['status']);
            $t->same(false, $report['runnerEvidence']['executed']);
            $t->same(null, $report['runnerEvidence']['command']);
            $t->same(null, $report['runnerEvidence']['result']);
            $t->same(null, $report['runnerEvidence']['resultArtifact']);
            $t->same('planned-not-run', $report['runnerEvidence']['commandPlanStatus']);
            $t->same(EpubMediaBagComparisonHarness::EXPECTED_UPSTREAM_COMMIT, $report['runnerEvidence']['upstreamBinding']['expectedCommit']);
            $t->same(['Readers', 'EPUB', 'EPUB Mediabag'], $report['runnerEvidence']['target']['tastyGroupPath']);
            $t->same('upstream-epub-mediabag-equality', $report['orderedRemainingGaps'][0]['id']);
            $t->same('not-evaluated', $report['orderedRemainingGaps'][0]['status']);
            $t->contains('Pandoc EPUB media-bag comparison: skipped', $text);
            $t->contains('runnerEvidence: status=not-run plan=planned-not-run executed=false', $text);
            $t->contains('orderedRemainingGaps:', $text);
        } finally {
            $removeTree($root);
        }
    },

    'matches checked-in current upstream epub media bag fixtures' => static function (TestRunner $t) use ($fixtureRoot, $currentMediaBagSignatures, $assertCurrentMediaBagSignature): void {
        $report = (new EpubMediaBagComparisonHarness())->run($fixtureRoot(), [
            'fixtureBase' => $fixtureRoot(),
        ]);

        $t->same('completed', $report['status']);
        $t->same(false, $report['skipped']);
        $t->same($fixtureRoot(), $report['upstreamRoot']);
        $t->same($fixtureRoot(), $report['fixtureBase']);
        $t->same(6, $report['totalCaseCount']);
        $t->same(6, $report['comparedCaseCount']);
        $t->same(6, $report['epubParsedCount']);
        $t->same(0, $report['parseFailureCount']);
        $t->same(10, $report['expectedMediaItemCount']);
        $t->same(10, $report['actualMediaItemCount']);
        $t->same(6, $report['mediaBagMatchCount']);
        $t->same(0, $report['mediaBagMismatchCount']);
        $t->same(100.0, $report['mediaBagMatchPercent']);
        $t->same('media-bag-equality-observed-not-runner-parity', $report['mediaBagParityStatus']);
        $t->same($currentMediaBagSignatures(), $report['mediaBagSignatures']);
        $assertCurrentMediaBagSignature($t, $report['currentMediaBagSignature']);
        $t->same(true, EpubMediaBagComparisonHarness::hasRunnerNotRunEvidence($report));
        $t->same(true, EpubMediaBagComparisonHarness::hasRunnerPlanEvidence($report));
        $t->same('test:test-pandoc', $report['runnerEvidence']['target']['testSuite']);
        $t->same('$2 == "Readers" && $3 == "EPUB" && $4 == "EPUB Mediabag"', $report['runnerEvidence']['target']['tastyPattern']);
        $t->same('covered-by-current-media-bag-evidence', $report['orderedRemainingGaps'][0]['status']);
        $t->same(true, EpubMediaBagComparisonHarness::hasRequiredMediaBagParity($report, 6));
        $t->same(true, EpubMediaBagComparisonHarness::hasRequiredMediaBagItemCount($report, 10));
        $t->same(false, EpubMediaBagComparisonHarness::hasRequiredMediaBagItemCount($report, 9));
        $t->same(true, EpubMediaBagComparisonHarness::hasRequiredCurrentMediaBagSignature($report));
        $t->same(true, EpubMediaBagComparisonHarness::hasRequiredCurrentMediaBagSignatures($report));
    },

    'validates supplied epub media bag upstream runner result artifact' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writeCurrentFixtureTree, $writeRunnerTranscripts): void {
        $root = $makeTempDir();
        try {
            $writeCurrentFixtureTree($root);
            $baseReport = (new EpubMediaBagComparisonHarness())->run($root, [
                'fixtureBase' => $root,
                'repoRoot' => $root,
            ]);
            $runnerPlan = $baseReport['runnerEvidence'];
            $transcripts = $writeRunnerTranscripts($root, $runnerPlan['requiredTranscripts']);
            $testNames = array_column($baseReport['mediaBagSignatures'], 'case');
            $payload = [
                'schemaVersion' => 2,
                'runner' => 'Cabal/Tasty Pandoc EPUB reader media-bag suite',
                'runnerExecuted' => true,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'commit' => EpubMediaBagComparisonHarness::EXPECTED_UPSTREAM_COMMIT,
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
            $report = (new EpubMediaBagComparisonHarness())->run($root, [
                'fixtureBase' => $root,
                'repoRoot' => $root,
                'runnerResultArtifact' => $artifactPath,
            ]);
            $text = (new EpubMediaBagComparisonHarness())->formatReport($report);

            $t->same('completed', $report['runnerEvidence']['status']);
            $t->same(true, $report['runnerEvidence']['executed']);
            $t->same('runner-result-artifact-validated', $report['runnerEvidence']['commandPlanStatus']);
            $t->same('valid-upstream-epub-media-bag-runner-result-artifact', $report['runnerEvidence']['validation']['status']);
            $t->same([], $report['runnerEvidence']['validation']['issues']);
            $t->same('upstream-epub-media-bag-runner-result-artifact', $report['runnerEvidence']['resultArtifact']['kind']);
            $t->same(hash_file('sha256', $artifactPath), $report['runnerEvidence']['resultArtifact']['sha256']);
            $t->same(filesize($artifactPath), $report['runnerEvidence']['resultArtifact']['bytes']);
            $t->same(EpubMediaBagComparisonHarness::EXPECTED_UPSTREAM_COMMIT, $report['runnerEvidence']['upstreamBinding']['observedCommit']);
            $t->same($runnerPlan['target'], $report['runnerEvidence']['target']);
            $t->same($runnerPlan['futureCommands'][2], $report['runnerEvidence']['command']);
            $t->same($testNames, $report['runnerEvidence']['observed']['testNames']);
            $t->same($runnerPlan['requiredTranscripts'], $report['runnerEvidence']['observed']['transcriptPaths']);
            $t->same($transcripts, $report['runnerEvidence']['observed']['transcripts']);
            $t->same($transcripts, $report['runnerEvidence']['expected']['transcripts']);
            $t->same('upstream-epub-media-bag-runner-transcript', $report['runnerEvidence']['transcripts'][0]['kind']);
            $t->same(true, EpubMediaBagComparisonHarness::hasRunnerResultArtifactEvidence($report));
            $t->same(false, EpubMediaBagComparisonHarness::hasRunnerPlanEvidence($report));
            $t->same('covered-by-validated-runner-result-artifact', $report['orderedRemainingGaps'][1]['status']);
            $t->contains('runnerEvidence: status=completed plan=runner-result-artifact-validated executed=true', $text);

            $payload = $validPayload;
            $payload['failedCount'] = 1;
            $payload['exitCode'] = 1;
            $writeFile($root, 'bad-result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $badReport = (new EpubMediaBagComparisonHarness())->run($root, [
                'fixtureBase' => $root,
                'repoRoot' => $root,
                'runnerResultArtifact' => $root . '/bad-result.json',
            ]);

            $t->same('invalid', $badReport['runnerEvidence']['status']);
            $t->same('invalid-upstream-epub-media-bag-runner-result-artifact', $badReport['runnerEvidence']['validation']['status']);
            $t->true(in_array('runner-result-exit-code-nonzero', $badReport['runnerEvidence']['validation']['issues'], true));
            $t->true(in_array('runner-result-counts-mismatch', $badReport['runnerEvidence']['validation']['issues'], true));
            $t->same(false, EpubMediaBagComparisonHarness::hasRunnerResultArtifactEvidence($badReport));

            $badTranscriptPayload = $validPayload;
            $badTranscriptPayload['transcripts'][0]['sha256'] = str_repeat('0', 64);
            $writeFile($root, 'bad-transcript-result.json', json_encode($badTranscriptPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $badTranscriptReport = (new EpubMediaBagComparisonHarness())->run($root, [
                'fixtureBase' => $root,
                'repoRoot' => $root,
                'runnerResultArtifact' => $root . '/bad-transcript-result.json',
            ]);

            $t->same('invalid', $badTranscriptReport['runnerEvidence']['status']);
            $t->true(in_array('runner-result-transcript-sha256-mismatch', $badTranscriptReport['runnerEvidence']['validation']['issues'], true));
            $t->same(false, EpubMediaBagComparisonHarness::hasRunnerResultArtifactEvidence($badTranscriptReport));
        } finally {
            $removeTree($root);
        }
    },

    'cli gates checked-in current epub media bag fixtures through checked-in selector' => static function (TestRunner $t) use ($fixtureRoot, $currentMediaBagSignatures, $assertCurrentMediaBagSignature): void {
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-media-bag.php')
            . ' --checked-in-fixtures'
            . ' --json'
            . ' summary'
            . ' --require-media-bag-parity=6'
            . ' --require-media-bag-item-count=10'
            . ' --require-current-media-bag-signatures'
            . ' --require-runner-plan';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same('completed', $decoded['status']);
        $t->same($fixtureRoot(), $decoded['upstreamRoot']);
        $t->same($fixtureRoot(), $decoded['fixtureBase']);
        $t->same('media-bag-comparison-not-full-epub-parity', $decoded['verdict']);
        $t->same(6, $decoded['comparedCaseCount']);
        $t->same(6, $decoded['mediaBagMatchCount']);
        $t->same(0, $decoded['mediaBagMismatchCount']);
        $t->same(10, $decoded['expectedMediaItemCount']);
        $t->same(10, $decoded['actualMediaItemCount']);
        $t->same('media-bag-equality-observed-not-runner-parity', $decoded['mediaBagParityStatus']);
        $t->same($currentMediaBagSignatures(), $decoded['mediaBagSignatures']);
        $assertCurrentMediaBagSignature($t, $decoded['currentMediaBagSignature']);
        $t->same(true, EpubMediaBagComparisonHarness::hasRunnerNotRunEvidence($decoded));
        $t->same(true, EpubMediaBagComparisonHarness::hasRunnerPlanEvidence($decoded));
        $t->same(null, $decoded['runnerEvidence']['command']);
        $t->same(null, $decoded['runnerEvidence']['result']);
        $t->same(null, $decoded['runnerEvidence']['resultArtifact']);
        $t->same('open', $decoded['orderedRemainingGaps'][1]['status']);
        $t->same('open', $decoded['orderedRemainingGaps'][2]['status']);
        $t->same(true, EpubMediaBagComparisonHarness::hasRequiredMediaBagParity($decoded, 6));
        $t->same(true, EpubMediaBagComparisonHarness::hasRequiredMediaBagItemCount($decoded, 10));
        $t->same(true, EpubMediaBagComparisonHarness::hasRequiredCurrentMediaBagSignature($decoded));
        $t->same(true, EpubMediaBagComparisonHarness::hasRequiredCurrentMediaBagSignatures($decoded));

        $defaultSignatureCommand = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-media-bag.php')
            . ' --json'
            . ' summary'
            . ' --require-current-media-bag-signatures';
        $defaultSignatureOutput = [];
        $defaultSignatureExitCode = 0;
        exec($defaultSignatureCommand, $defaultSignatureOutput, $defaultSignatureExitCode);
        $defaultSignatureDecoded = json_decode(implode("\n", $defaultSignatureOutput), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $defaultSignatureExitCode);
        $t->same($fixtureRoot(), $defaultSignatureDecoded['upstreamRoot']);
        $t->same($fixtureRoot(), $defaultSignatureDecoded['fixtureBase']);
        $t->same($currentMediaBagSignatures(), $defaultSignatureDecoded['mediaBagSignatures']);
        $assertCurrentMediaBagSignature($t, $defaultSignatureDecoded['currentMediaBagSignature']);

        $failingParityCommand = str_replace('--require-media-bag-parity=6', '--require-media-bag-parity=7', $command) . ' 2>/dev/null';
        $failingParityOutput = [];
        $failingParityExitCode = 0;
        exec($failingParityCommand, $failingParityOutput, $failingParityExitCode);

        $failingItemCountCommand = str_replace('--require-media-bag-item-count=10', '--require-media-bag-item-count=11', $command) . ' 2>/dev/null';
        $failingItemCountOutput = [];
        $failingItemCountExitCode = 0;
        exec($failingItemCountCommand, $failingItemCountOutput, $failingItemCountExitCode);

        $failingSignatureCommand = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-media-bag.php')
            . ' --checked-in-fixtures'
            . ' --json'
            . ' summary'
            . ' --limit=5'
            . ' --require-current-media-bag-signatures'
            . ' 2>/dev/null';
        $failingSignatureOutput = [];
        $failingSignatureExitCode = 0;
        exec($failingSignatureCommand, $failingSignatureOutput, $failingSignatureExitCode);

        $t->same(1, $failingParityExitCode);
        $t->same(1, $failingItemCountExitCode);
        $t->same(1, $failingSignatureExitCode);
    },

    'cli gates supplied epub media bag upstream runner result artifact' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writeCurrentFixtureTree, $writeRunnerTranscripts): void {
        $root = $makeTempDir();
        try {
            $writeCurrentFixtureTree($root);
            $baseReport = (new EpubMediaBagComparisonHarness())->run($root, [
                'fixtureBase' => $root,
                'repoRoot' => $root,
            ]);
            $runnerPlan = $baseReport['runnerEvidence'];
            $transcripts = $writeRunnerTranscripts($root, $runnerPlan['requiredTranscripts']);
            $testNames = array_column($baseReport['mediaBagSignatures'], 'case');
            $payload = [
                'schemaVersion' => 2,
                'runner' => 'Cabal/Tasty Pandoc EPUB reader media-bag suite',
                'runnerExecuted' => true,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'commit' => EpubMediaBagComparisonHarness::EXPECTED_UPSTREAM_COMMIT,
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
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-media-bag.php')
                . ' --repo-root=' . escapeshellarg($root)
                . ' --upstream-root=' . escapeshellarg($root)
                . ' --fixture-base=' . escapeshellarg($root)
                . ' --runner-result-artifact=' . escapeshellarg($root . '/result.json')
                . ' --json'
                . ' summary'
                . ' --require-runner-result-artifact';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same('completed', $decoded['runnerEvidence']['status']);
            $t->same('valid-upstream-epub-media-bag-runner-result-artifact', $decoded['runnerEvidence']['validation']['status']);
            $t->same(true, EpubMediaBagComparisonHarness::hasRunnerResultArtifactEvidence($decoded));
            $t->same('covered-by-validated-runner-result-artifact', $decoded['orderedRemainingGaps'][1]['status']);

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

    'compares emitted image media bag without counting unused manifest images' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writeScopedMediaBagEpub): void {
        $root = $makeTempDir();
        try {
            $epubPath = $root . '/test/epub/scoped-media-bag.epub';
            if (!is_dir(dirname($epubPath)) && !mkdir(dirname($epubPath), 0777, true) && !is_dir(dirname($epubPath))) {
                throw new RuntimeException('Unable to create scoped media-bag fixture tree');
            }
            $fixture = $writeScopedMediaBagEpub($epubPath);
            $writeFile($root, 'test/Tests/Readers/EPUB.hs', sprintf(
                <<<'HS'
scopedMediaBag = [("images/used.png","image/png",%d)]

tests = [ testCase "scoped media bag"
          (testMediaBag "epub/scoped-media-bag.epub" scopedMediaBag) ]
HS,
                $fixture['usedImageBytes']
            ));
            $writeFile($root, 'src/Text/Pandoc/Readers/EPUB.hs', "module Text.Pandoc.Readers.EPUB where\n");

            $report = (new EpubMediaBagComparisonHarness())->run($root);

            $t->same('completed', $report['status']);
            $t->same(1, $report['comparedCaseCount']);
            $t->same(1, $report['epubParsedCount']);
            $t->same(0, $report['parseFailureCount']);
            $t->same(1, $report['expectedMediaItemCount']);
            $t->same(1, $report['actualMediaItemCount']);
            $t->same(1, $report['mediaBagMatchCount']);
            $t->same(0, $report['mediaBagMismatchCount']);
            $t->same('media-bag-equality-observed-not-runner-parity', $report['mediaBagParityStatus']);
        } finally {
            $removeTree($root);
        }
    },

    'reports epub media bag mismatches without claiming full parity' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $fixtureRoot): void {
        $root = $makeTempDir();
        try {
            $writeFile($root, 'test/Tests/Readers/EPUB.hs', <<<'HS'
wrongBag :: [(String, String, Int)]
wrongBag = [("img/check.gif","image/gif",1)]

tests = [ testCase "wrong size" (testMediaBag "epub/img.epub" wrongBag) ]
HS);
            $writeFile($root, 'src/Text/Pandoc/Readers/EPUB.hs', "module Stub where\n");
            if (!is_dir($root . '/test/epub') && !mkdir($root . '/test/epub', 0777, true) && !is_dir($root . '/test/epub')) {
                throw new RuntimeException('Unable to create EPUB fixture tree');
            }
            copy($fixtureRoot() . '/epub/img.epub', $root . '/test/epub/img.epub');

            $harness = new EpubMediaBagComparisonHarness();
            $report = $harness->run($root);
            $text = $harness->formatReport($report);

            $t->same('completed', $report['status']);
            $t->same(1, $report['comparedCaseCount']);
            $t->same(1, $report['epubParsedCount']);
            $t->same(0, $report['parseFailureCount']);
            $t->same(0, $report['mediaBagMatchCount']);
            $t->same(1, $report['mediaBagMismatchCount']);
            $t->same('media-bag-mismatches-observed', $report['mediaBagParityStatus']);
            $t->same('wrong size', $report['mismatchComparisons'][0]['case']);
            $t->contains('media item count expected=1 actual=4', $report['mismatchComparisons'][0]['firstDifference']);
            $t->same('open', $report['orderedRemainingGaps'][0]['status']);
            $t->contains('mediaBag: matches=0 (0.00%) mismatches=1', $text);
            $t->same(false, EpubMediaBagComparisonHarness::hasRequiredMediaBagParity($report, 1));
            $t->same(false, EpubMediaBagComparisonHarness::hasRequiredCurrentMediaBagSignature($report));
            $t->same(false, EpubMediaBagComparisonHarness::hasRequiredCurrentMediaBagSignatures($report));
        } finally {
            $removeTree($root);
        }
    },

    'cli gates required epub media bag parity from upstream fixture tree' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeCurrentFixtureTree): void {
        $root = $makeTempDir();
        try {
            $writeCurrentFixtureTree($root);
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-media-bag.php')
                . ' --upstream-root=' . escapeshellarg($root)
                . ' --json'
                . ' summary'
                . ' --require-media-bag-parity=6'
                . ' --require-runner-plan';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same('completed', $decoded['status']);
            $t->same(6, $decoded['mediaBagMatchCount']);
            $t->same(0, $decoded['mediaBagMismatchCount']);
            $t->same(true, EpubMediaBagComparisonHarness::hasRequiredMediaBagParity($decoded, 6));
            $t->same(true, EpubMediaBagComparisonHarness::hasRequiredMediaBagItemCount($decoded, 10));
            $t->same(true, EpubMediaBagComparisonHarness::hasRunnerPlanEvidence($decoded));

            $failingCommand = str_replace('--require-media-bag-parity=6', '--require-media-bag-parity=7', $command) . ' 2>/dev/null';
            $failingOutput = [];
            $failingExitCode = 0;
            exec($failingCommand, $failingOutput, $failingExitCode);

            $t->same(1, $failingExitCode);
        } finally {
            $removeTree($root);
        }
    },
];
