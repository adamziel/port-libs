<?php

declare(strict_types=1);

use PortLibs\Pandoc\EpubExecutableNativeAstComparisonHarness;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-epub-exec-ast-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary EPUB executable AST directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary EPUB executable AST directory {$base}");
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

$writeFakePandoc = static function (string $path, string $nativePath): void {
    file_put_contents($path, "#!/bin/sh\nif [ \"\$1\" = \"--version\" ]; then echo \"pandoc fake 1.0\"; exit 0; fi\ncat " . escapeshellarg($nativePath) . "\n");
    chmod($path, 0755);
};

$copyFixture = static function (string $root, string $name = 'audio-navigation'): void {
    $fixtureRoot = dirname(__DIR__) . '/fixtures/upstream-current-epub-reader/epub';
    copy($fixtureRoot . '/' . $name . '.epub', $root . '/' . $name . '.epub');
    copy($fixtureRoot . '/' . $name . '.native', $root . '/' . $name . '.native');
};

return [
    'skips epub executable comparison when pandoc is absent' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $copyFixture): void {
        $root = $makeTempDir();
        try {
            $copyFixture($root);
            $report = (new EpubExecutableNativeAstComparisonHarness())->run($root, [
                'pandocBin' => $root . '/missing-pandoc',
            ]);
            $text = (new EpubExecutableNativeAstComparisonHarness())->formatReport($report);

            $t->same('skipped', $report['status']);
            $t->same(true, $report['skipped']);
            $t->same('pandoc-executable-missing', $report['reason']);
            $t->same(1, $report['totalEpubCount']);
            $t->same(0, $report['comparedEpubCount']);
            $t->same(0, $report['nativeFixtureParsedCount']);
            $t->same(0, $report['pandocNativeFixtureMatchCount']);
            $t->same(0, $report['pandocNativeFixtureByteComparedCount']);
            $t->same(0, $report['pandocNativeFixtureByteMatchCount']);
            $t->same(0, $report['pandocNativeFixtureByteMismatchCount']);
            $t->same('not-evaluated-pandoc-executable-missing', $report['astParityStatus']);
            $t->same('not-evaluated', $report['orderedRemainingGaps'][0]['status']);
            $t->contains('Pandoc EPUB executable/native AST comparison: skipped', $text);
        } finally {
            $removeTree($root);
        }
    },

    'matches local epub reader output against fake pandoc native output' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $copyFixture, $writeFakePandoc): void {
        $root = $makeTempDir();
        try {
            $copyFixture($root);
            $fakePandoc = $root . '/pandoc';
            $writeFakePandoc($fakePandoc, $root . '/audio-navigation.native');

            $report = (new EpubExecutableNativeAstComparisonHarness())->run($root, [
                'pandocBin' => $fakePandoc,
            ]);

            $t->same('completed', $report['status']);
            $t->same(false, $report['skipped']);
            $t->same(1, $report['totalEpubCount']);
            $t->same(1, $report['comparedEpubCount']);
            $t->same(1, $report['localParsedCount']);
            $t->same(1, $report['pandocParsedCount']);
            $t->same(1, $report['nativeFixtureParsedCount']);
            $t->same(0, $report['parseFailureCount']);
            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(0, $report['normalizedAstMismatchCount']);
            $t->same(1, $report['pandocNativeFixtureComparedCount']);
            $t->same(1, $report['pandocNativeFixtureMatchCount']);
            $t->same(0, $report['pandocNativeFixtureMismatchCount']);
            $t->same(1, $report['pandocNativeFixtureByteComparedCount']);
            $t->same(1, $report['pandocNativeFixtureByteMatchCount']);
            $t->same(0, $report['pandocNativeFixtureByteMismatchCount']);
            $t->same('pandoc fake 1.0', $report['pandocVersion']);
            $t->same('normalized-ast-equality-observed-against-pandoc-executable', $report['astParityStatus']);
            $t->same(true, EpubExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($report, 1));
            $t->same(true, EpubExecutableNativeAstComparisonHarness::hasRequiredPandocVersion($report, 'pandoc fake 1.0'));
            $t->same(false, EpubExecutableNativeAstComparisonHarness::hasRequiredPandocVersion($report, 'pandoc fake 2.0'));
        } finally {
            $removeTree($root);
        }
    },

    'reports executable native byte drift without failing normalized ast parity' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $copyFixture, $writeFakePandoc): void {
        $root = $makeTempDir();
        try {
            $copyFixture($root);
            $checkedInNative = (string) file_get_contents($root . '/audio-navigation.native');
            file_put_contents($root . '/byte-drift.native', $checkedInNative . "\n");
            $fakePandoc = $root . '/pandoc';
            $writeFakePandoc($fakePandoc, $root . '/byte-drift.native');

            $harness = new EpubExecutableNativeAstComparisonHarness();
            $report = $harness->run($root, [
                'pandocBin' => $fakePandoc,
            ]);
            $text = $harness->formatReport($report);

            $t->same('completed', $report['status']);
            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(0, $report['normalizedAstMismatchCount']);
            $t->same(1, $report['pandocNativeFixtureComparedCount']);
            $t->same(1, $report['pandocNativeFixtureMatchCount']);
            $t->same(0, $report['pandocNativeFixtureMismatchCount']);
            $t->same(1, $report['pandocNativeFixtureByteComparedCount']);
            $t->same(0, $report['pandocNativeFixtureByteMatchCount']);
            $t->same(1, $report['pandocNativeFixtureByteMismatchCount']);
            $t->same('audio-navigation', $report['pandocNativeFixtureByteMismatchComparisons'][0]['fixture']);
            $t->same(strlen($checkedInNative), $report['pandocNativeFixtureByteMismatchComparisons'][0]['nativeFixtureBytes']);
            $t->same(strlen($checkedInNative) + 1, $report['pandocNativeFixtureByteMismatchComparisons'][0]['pandocNativeBytes']);
            $t->same('normalized-ast-equality-observed-against-pandoc-executable', $report['astParityStatus']);
            $t->same(true, EpubExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($report, 1));
            $t->contains('checkedInNativeFixtureBytes: pandocCompared=1 pandocByteMatches=0 (0.00%) pandocByteMismatches=1', $text);
            $t->contains('executable/checked-in-native byte mismatches=1', $report['orderedRemainingGaps'][0]['currentEvidence']);
        } finally {
            $removeTree($root);
        }
    },

    'reports executable native ast mismatches without claiming parity' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $copyFixture, $writeFakePandoc): void {
        $root = $makeTempDir();
        try {
            $copyFixture($root);
            file_put_contents($root . '/mismatch.native', '[Para [Str "different"]]');
            $fakePandoc = $root . '/pandoc';
            $writeFakePandoc($fakePandoc, $root . '/mismatch.native');

            $report = (new EpubExecutableNativeAstComparisonHarness())->run($root, [
                'pandocBin' => $fakePandoc,
            ]);

            $t->same('completed', $report['status']);
            $t->same(1, $report['normalizedAstMismatchCount']);
            $t->same(1, $report['pandocNativeFixtureComparedCount']);
            $t->same(0, $report['pandocNativeFixtureMatchCount']);
            $t->same(1, $report['pandocNativeFixtureMismatchCount']);
            $t->same(1, $report['pandocNativeFixtureByteComparedCount']);
            $t->same(0, $report['pandocNativeFixtureByteMatchCount']);
            $t->same(1, $report['pandocNativeFixtureByteMismatchCount']);
            $t->same('normalized-ast-mismatches-observed', $report['astParityStatus']);
            $t->same('audio-navigation', $report['mismatchComparisons'][0]['fixture']);
            $t->contains('root.children keys', $report['mismatchComparisons'][0]['firstDifference']);
            $t->same('audio-navigation', $report['pandocNativeFixtureMismatchComparisons'][0]['fixture']);
            $t->contains('root.children keys', $report['pandocNativeFixtureMismatchComparisons'][0]['firstDifference']);
            $t->same('audio-navigation', $report['pandocNativeFixtureByteMismatchComparisons'][0]['fixture']);
            $t->same(false, EpubExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($report, 1));
        } finally {
            $removeTree($root);
        }
    },

    'requires paired checked-in native fixtures for executable parity' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFakePandoc): void {
        $root = $makeTempDir();
        try {
            $fixtureRoot = dirname(__DIR__) . '/fixtures/upstream-current-epub-reader/epub';
            copy($fixtureRoot . '/audio-navigation.epub', $root . '/audio-navigation.epub');
            copy($fixtureRoot . '/audio-navigation.native', $root . '/expected.native');
            $fakePandoc = $root . '/pandoc';
            $writeFakePandoc($fakePandoc, $root . '/expected.native');

            $report = (new EpubExecutableNativeAstComparisonHarness())->run($root, [
                'pandocBin' => $fakePandoc,
            ]);

            $t->same('completed', $report['status']);
            $t->same(1, $report['localParsedCount']);
            $t->same(1, $report['pandocParsedCount']);
            $t->same(0, $report['nativeFixtureParsedCount']);
            $t->same(1, $report['bothParsedCount']);
            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(0, $report['pandocNativeFixtureComparedCount']);
            $t->same(0, $report['pandocNativeFixtureByteComparedCount']);
            $t->same(0, $report['pandocNativeFixtureByteMatchCount']);
            $t->same(0, $report['pandocNativeFixtureByteMismatchCount']);
            $t->same(1, $report['parseFailureCount']);
            $t->same('missing paired .native fixture', $report['parseFailures'][0]['nativeFixtureError']);
            $t->same('blocked-by-parse-failures', $report['astParityStatus']);
            $t->same(false, EpubExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($report, 1));
        } finally {
            $removeTree($root);
        }
    },

    'cli gates required executable epub parity with fake pandoc' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $copyFixture, $writeFakePandoc): void {
        $root = $makeTempDir();
        try {
            $copyFixture($root);
            $fakePandoc = $root . '/pandoc';
            $writeFakePandoc($fakePandoc, $root . '/audio-navigation.native');
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-epub-executable-native-ast.php')
                . ' --epub-dir=' . escapeshellarg($root)
                . ' --pandoc-bin=' . escapeshellarg($fakePandoc)
                . ' --json'
                . ' summary'
                . ' --require-executable-parity=1'
                . ' --require-pandoc-version=' . escapeshellarg('pandoc fake 1.0');
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same(1, $decoded['normalizedAstMatchCount']);
            $t->same(0, $decoded['normalizedAstMismatchCount']);
            $t->same(1, $decoded['pandocNativeFixtureComparedCount']);
            $t->same(1, $decoded['pandocNativeFixtureMatchCount']);
            $t->same(0, $decoded['pandocNativeFixtureMismatchCount']);
            $t->same(1, $decoded['pandocNativeFixtureByteComparedCount']);
            $t->same(1, $decoded['pandocNativeFixtureByteMatchCount']);
            $t->same(0, $decoded['pandocNativeFixtureByteMismatchCount']);
            $t->same(true, EpubExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($decoded, 1));
            $t->same(true, EpubExecutableNativeAstComparisonHarness::hasRequiredPandocVersion($decoded, 'pandoc fake 1.0'));

            $missingCommand = str_replace('--pandoc-bin=' . escapeshellarg($fakePandoc), '--pandoc-bin=' . escapeshellarg($root . '/missing'), $command) . ' 2>/dev/null';
            $missingOutput = [];
            $missingExitCode = 0;
            exec($missingCommand, $missingOutput, $missingExitCode);

            $t->same(1, $missingExitCode);

            $versionMismatchCommand = str_replace(
                '--require-pandoc-version=' . escapeshellarg('pandoc fake 1.0'),
                '--require-pandoc-version=' . escapeshellarg('pandoc fake 2.0'),
                $command
            ) . ' 2>/dev/null';
            $versionMismatchOutput = [];
            $versionMismatchExitCode = 0;
            exec($versionMismatchCommand, $versionMismatchOutput, $versionMismatchExitCode);

            $t->same(1, $versionMismatchExitCode);
        } finally {
            $removeTree($root);
        }
    },
];
