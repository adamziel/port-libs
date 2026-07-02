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

$currentEpubReaderSource = static function (): string {
    return <<<'HS'
featuresBag :: [(String, String, Int)]
featuresBag = [("img/check.gif","image/gif",1340)
              ,("img/check.jpg","image/jpeg",2661)
              ,("img/check.png","image/png",2815)
              ,("img/multiscripts_and_greek_alphabet.png","image/png",10060)
              ]

epub3CoverBag :: [(String, String, Int)]
epub3CoverBag = [("wasteland-cover.jpg","image/jpeg", 16586)]

epub3NoCoverBag :: [(String, String, Int)]
epub3NoCoverBag = [("img/check.gif","image/gif",1340)
                  ,("img/check.jpg","image/jpeg",2661)
                  ,("img/check.png","image/png",2815)
                  ]

epub2PictureBag :: [(String, String, Int)]
epub2PictureBag = [("image/image.jpg","image/jpeg",9713)]

epub2CoverBag :: [(String, String, Int)]
epub2CoverBag = [("image/cover.jpg","image/jpeg",9713)]

epub2NoCoverBag :: [(String, String, Int)]
epub2NoCoverBag = []

tests :: [TestTree]
tests =
  [ testGroup "EPUB Mediabag"
    [ testCase "features bag"
      (testMediaBag "epub/img.epub" featuresBag),
      testCase "EPUB3 cover bag"
      (testMediaBag "epub/wasteland.epub" epub3CoverBag),
      testCase "EPUB3 no cover bag"
      (testMediaBag "epub/img_no_cover.epub" epub3NoCoverBag),
      testCase "EPUB2 picture bag"
      (testMediaBag "epub/epub2_picture.epub" epub2PictureBag),
      testCase "EPUB2 cover bag"
      (testMediaBag "epub/epub2_cover.epub" epub2CoverBag),
      testCase "EPUB2 no cover bag"
      (testMediaBag "epub/epub2_no_cover.epub" epub2NoCoverBag)
    ]
  ]
HS;
};

$currentReaderCases = static fn (): array => EpubUpstreamReaderEvidence::parseReaderCasesFromSource($currentEpubReaderSource());

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
            $t->same('upstream-epub-mediabag-equality', $report['orderedRemainingGaps'][0]['id']);
            $t->same('not-evaluated', $report['orderedRemainingGaps'][0]['status']);
            $t->contains('Pandoc EPUB media-bag comparison: skipped', $text);
            $t->contains('orderedRemainingGaps:', $text);
        } finally {
            $removeTree($root);
        }
    },

    'matches checked-in current upstream epub media bag fixtures' => static function (TestRunner $t) use ($fixtureRoot, $currentReaderCases): void {
        $report = (new EpubMediaBagComparisonHarness())->run($fixtureRoot(), [
            'fixtureBase' => $fixtureRoot(),
            'readerCases' => $currentReaderCases(),
        ]);

        $t->same('completed', $report['status']);
        $t->same(false, $report['skipped']);
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
        $t->same('covered-by-current-media-bag-evidence', $report['orderedRemainingGaps'][0]['status']);
        $t->same(true, EpubMediaBagComparisonHarness::hasRequiredMediaBagParity($report, 6));
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
                . ' --require-media-bag-parity=6';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same('completed', $decoded['status']);
            $t->same(6, $decoded['mediaBagMatchCount']);
            $t->same(0, $decoded['mediaBagMismatchCount']);
            $t->same(true, EpubMediaBagComparisonHarness::hasRequiredMediaBagParity($decoded, 6));

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
