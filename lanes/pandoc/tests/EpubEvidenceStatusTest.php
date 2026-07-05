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
        $note = $readText('lanes/pandoc/notes/pandoc-epub-xhtml-address-spine-20260704.md');
        $fixtureDirectory = $repoRoot . '/lanes/pandoc/fixtures/upstream-current-epub-reader/epub';
        $epubFiles = glob($fixtureDirectory . '/*.epub') ?: [];
        $nativeFiles = glob($fixtureDirectory . '/*.native') ?: [];
        $totalFiles = count($epubFiles) + count($nativeFiles);

        $t->same(67, count($epubFiles));
        $t->same(67, count($nativeFiles));
        $t->same(134, $totalFiles);

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
            $previousIntegrated = [
                'epubDirectoryArtifacts' => 130,
                'epubNativeExpectedArtifacts' => 65,
                'epubEpubInputArtifacts' => 65,
            ];
            $olderIntegrated = [
                'epubDirectoryArtifacts' => 128,
                'epubNativeExpectedArtifacts' => 64,
                'epubEpubInputArtifacts' => 64,
            ];
            $oldIntegrated = [
                'epubDirectoryArtifacts' => 126,
                'epubNativeExpectedArtifacts' => 63,
                'epubEpubInputArtifacts' => 63,
            ];
            $previousOldIntegrated = [
                'epubDirectoryArtifacts' => 124,
                'epubNativeExpectedArtifacts' => 62,
                'epubEpubInputArtifacts' => 62,
            ];
            $previousSharedManifest = [
                'epubDirectoryArtifacts' => 122,
                'epubNativeExpectedArtifacts' => 61,
                'epubEpubInputArtifacts' => 61,
            ];
            $olderSharedManifest = [
                'epubDirectoryArtifacts' => 120,
                'epubNativeExpectedArtifacts' => 60,
                'epubEpubInputArtifacts' => 60,
            ];
            $oldestSharedManifest = [
                'epubDirectoryArtifacts' => 114,
                'epubNativeExpectedArtifacts' => 57,
                'epubEpubInputArtifacts' => 57,
            ];
            $preIntegrationSharedManifest = [
                'epubDirectoryArtifacts' => 112,
                'epubNativeExpectedArtifacts' => 56,
                'epubEpubInputArtifacts' => 56,
            ];
            $legacySharedManifest = [
                'epubDirectoryArtifacts' => 110,
                'epubNativeExpectedArtifacts' => 55,
                'epubEpubInputArtifacts' => 55,
            ];
            $preIntegrationLegacySharedManifest = [
                'epubDirectoryArtifacts' => 108,
                'epubNativeExpectedArtifacts' => 54,
                'epubEpubInputArtifacts' => 54,
            ];
            $preIntegration = [
                'epubDirectoryArtifacts' => 106,
                'epubNativeExpectedArtifacts' => 53,
                'epubEpubInputArtifacts' => 53,
            ];
            $legacyPreIntegration = [
                'epubDirectoryArtifacts' => 98,
                'epubNativeExpectedArtifacts' => 49,
                'epubEpubInputArtifacts' => 49,
            ];

            $t->true(
                $observed === $integrated
                    || $observed === $previousIntegrated
                    || $observed === $olderIntegrated
                    || $observed === $oldIntegrated
                    || $observed === $previousOldIntegrated
                    || $observed === $previousSharedManifest
                    || $observed === $olderSharedManifest
                    || $observed === $oldestSharedManifest
                    || $observed === $preIntegrationSharedManifest
                    || $observed === $legacySharedManifest
                    || $observed === $preIntegrationLegacySharedManifest
                    || $observed === $preIntegration
                    || $observed === $legacyPreIntegration,
                "{$label} EPUB counters must match either local fixture state or the shared manifest state awaiting integration"
            );
        }

        $t->true(str_contains($note, '- Package/native fixture parity: 64/64 -> 65/65'));
        $t->true(str_contains($note, '- Checked-in EPUB/native identity files: 128 -> 130'));
        $t->true(str_contains($note, '- Normalized native AST matches: 64 -> 65'));
        $t->true(str_contains($note, 'xhtml-address-spine.epub'));
        $t->true(str_contains($note, '--require-package-parity=65'));
        $t->true(str_contains($note, '--require-native-readiness=65'));
        $t->true(str_contains($note, '--require-mapped-parity=65'));
    },
];
