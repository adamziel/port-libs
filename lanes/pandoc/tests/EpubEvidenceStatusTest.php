<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__, 3);

$readText = static function (string $relativePath) use ($repoRoot): string {
    $path = $repoRoot . DIRECTORY_SEPARATOR . $relativePath;
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Unable to read {$relativePath}");
    }

    return $contents;
};

$readJson = static function (string $relativePath) use ($readText): array {
    $decoded = json_decode($readText($relativePath), true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Unable to decode {$relativePath}: " . json_last_error_msg());
    }

    return $decoded;
};

return [
    'keeps EPUB current fixture status counters in sync' => static function (TestRunner $t) use ($repoRoot, $readText, $readJson): void {
        $manifest = $readJson('lanes/pandoc/UPSTREAM_TEST_MANIFEST.json');
        $note = $readText('lanes/pandoc/notes/epub-reader-upstream-current-fixture-gate-20260702.md');
        $fixtureDirectory = $repoRoot . '/lanes/pandoc/fixtures/upstream-current-epub-reader/epub';
        $epubFiles = glob($fixtureDirectory . '/*.epub') ?: [];
        $nativeFiles = glob($fixtureDirectory . '/*.native') ?: [];
        $totalFiles = count($epubFiles) + count($nativeFiles);

        $t->same(36, count($epubFiles));
        $t->same(36, count($nativeFiles));
        $t->same(72, $totalFiles);

        foreach ([
            'benchmarkDenominator.breakdown' => $manifest['benchmarkDenominator']['breakdown'] ?? null,
            'inventory' => $manifest['inventory'] ?? null,
        ] as $label => $counters) {
            $t->true(is_array($counters), "{$label} EPUB counters must be structured");
            $t->same($totalFiles, $counters['epubDirectoryArtifacts'] ?? null);
            $t->same(count($nativeFiles), $counters['epubNativeExpectedArtifacts'] ?? null);
            $t->same(count($epubFiles), $counters['epubEpubInputArtifacts'] ?? null);
        }

        $t->contains('- 36 EPUB package inputs', $note);
        $t->contains('- 36 same-directory `.native` goldens', $note);
        $t->contains('--require-package-parity=36', $note);
        $t->contains('--require-native-readiness=36', $note);
        $t->contains('--require-mapped-parity=36', $note);
    },
];
