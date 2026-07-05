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
        $note = $readText('lanes/pandoc/notes/pandoc-epub-figure-caption-spine-20260705.md');
        $fixtureDirectory = $repoRoot . '/lanes/pandoc/fixtures/upstream-current-epub-reader/epub';
        $epubFiles = glob($fixtureDirectory . '/*.epub') ?: [];
        $nativeFiles = glob($fixtureDirectory . '/*.native') ?: [];
        $totalFiles = count($epubFiles) + count($nativeFiles);

        $t->same(72, count($epubFiles));
        $t->same(72, count($nativeFiles));
        $t->same(144, $totalFiles);

        foreach ([
            'benchmarkDenominator.breakdown' => $manifest['benchmarkDenominator']['breakdown'] ?? null,
            'inventory' => $manifest['inventory'] ?? null,
        ] as $label => $counters) {
            $t->true(is_array($counters), "{$label} EPUB counters must be structured");
            $observed = [
                'epubDirectoryArtifacts' => $counters['epubDirectoryArtifacts'] ?? null,
                'epubNativeExpectedArtifacts' => $counters['epubNativeExpectedArtifacts'] ?? null,
                'epubEpubInputArtifacts' => $counters['epubEpubInputArtifacts'] ?? null,
            ];
            $integrated = [
                'epubDirectoryArtifacts' => $totalFiles,
                'epubNativeExpectedArtifacts' => count($nativeFiles),
                'epubEpubInputArtifacts' => count($epubFiles),
            ];
            $t->same($integrated, $observed, "{$label} EPUB counters must match local checked-in fixture state");
        }

        $t->true(str_contains($note, '- Package/native fixture parity: 71/71 -> 72/72'));
        $t->true(str_contains($note, '- Checked-in EPUB/native identity files: 142 -> 144'));
        $t->true(str_contains($note, '- Package feature fixture count: 71 -> 72'));
        $t->true(str_contains($note, '- Normalized native AST matches: 71 -> 72'));
        $t->true(str_contains($note, 'figure-caption-spine.epub'));
        $t->true(str_contains($note, '--require-package-parity=72'));
        $t->true(str_contains($note, '--require-native-readiness=72'));
        $t->true(str_contains($note, '--require-mapped-parity=72'));
    },
];
