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
        $note = $readText('lanes/pandoc/notes/pandoc-epub-page-list-cfi-navigation-20260704.md');
        $fixtureDirectory = $repoRoot . '/lanes/pandoc/fixtures/upstream-current-epub-reader/epub';
        $epubFiles = glob($fixtureDirectory . '/*.epub') ?: [];
        $nativeFiles = glob($fixtureDirectory . '/*.native') ?: [];
        $totalFiles = count($epubFiles) + count($nativeFiles);

        $t->same(55, count($epubFiles));
        $t->same(55, count($nativeFiles));
        $t->same(110, $totalFiles);

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
            $previousSharedManifest = [
                'epubDirectoryArtifacts' => 108,
                'epubNativeExpectedArtifacts' => 54,
                'epubEpubInputArtifacts' => 54,
            ];
            $olderSharedManifest = [
                'epubDirectoryArtifacts' => 106,
                'epubNativeExpectedArtifacts' => 53,
                'epubEpubInputArtifacts' => 53,
            ];
            $preIntegration = [
                'epubDirectoryArtifacts' => 98,
                'epubNativeExpectedArtifacts' => 49,
                'epubEpubInputArtifacts' => 49,
            ];

            $t->true(
                $observed === $integrated
                    || $observed === $previousSharedManifest
                    || $observed === $olderSharedManifest
                    || $observed === $preIntegration,
                "{$label} EPUB counters must match either local fixture state or the shared manifest state awaiting integration"
            );
        }

        $t->true(str_contains($note, '- Checked-in EPUB package inputs: `54 -> 55`.'));
        $t->true(str_contains($note, '- Checked-in same-basename `.native` goldens: `54 -> 55`.'));
        $t->true(str_contains($note, '- Checked-in fixture identity files: `108 -> 110`.'));
        $t->true(str_contains($note, '--require-package-parity=55'));
        $t->true(str_contains($note, '--require-native-readiness=55'));
        $t->true(str_contains($note, '--require-mapped-parity=55'));
    },
];
