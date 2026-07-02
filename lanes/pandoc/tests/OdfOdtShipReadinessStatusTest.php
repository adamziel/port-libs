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
    'keeps ODF/ODT ship-ready evidence internally consistent' => static function (TestRunner $t) use ($repoRoot, $readText, $readJson): void {
        $status = $readJson('lanes/pandoc/lane-status.json');
        $manifest = $readJson('lanes/pandoc/UPSTREAM_TEST_MANIFEST.json');
        $progress = $readText('progress.md');

        $readiness = $status['odfOdtShipReadiness'] ?? null;
        $t->true(is_array($readiness), 'lane-status.json must carry odfOdtShipReadiness evidence');

        $upstreamCases = $readiness['upstreamFormatCases'] ?? null;
        $upstreamAssertions = $readiness['upstreamFormatAssertions'] ?? null;
        $localCases = $readiness['localMappedCases'] ?? null;
        $localAssertions = $readiness['localFocusedAssertions'] ?? null;

        $t->same('ship-ready', $readiness['verdict'] ?? null);
        $t->same(20, $upstreamCases);
        $t->same(575, $upstreamAssertions);
        $t->true(is_int($localCases) && $localCases >= 50, 'ODF/ODT local mapped cases must not regress below the ship-ready snapshot');
        $t->true(is_int($localAssertions) && $localAssertions >= 1546, 'ODF/ODT focused assertion evidence must not regress below the ship-ready snapshot');
        $t->same([], $readiness['remainingCriticalGaps'] ?? null);
        $t->same([], $readiness['uncoveredUpstreamCriticalTests'] ?? null);
        $t->same(round($localCases / $upstreamCases * 100, 1), (float) ($readiness['caseCoveragePercent'] ?? 0));
        $t->same(round($localAssertions / $upstreamAssertions * 100, 1), (float) ($readiness['assertionCoveragePercent'] ?? 0));

        $focusedFiles = $readiness['localFocusedTestFiles'] ?? [];
        $t->true(is_array($focusedFiles), 'ODF/ODT focused test file list must be structured');
        foreach ([
            'lanes/pandoc/tests/OdfReaderTest.php',
            'lanes/pandoc/tests/OdtReaderTest.php',
            'lanes/pandoc/tests/OpenDocumentReaderTest.php',
            'lanes/pandoc/tests/OpenDocumentPackageTest.php',
        ] as $focusedFile) {
            $t->true(in_array($focusedFile, $focusedFiles, true), "{$focusedFile} must remain part of ODF/ODT readiness evidence");
        }

        $evidenceText = implode("\n", array_map(
            static fn (mixed $item): string => is_string($item) ? $item : '',
            $readiness['evidence'] ?? []
        ));
        $t->contains('custom attributes', $evidenceText);
        $t->contains('No external Pandoc', $evidenceText);

        $t->true(
            isset($manifest['mappedOdfManifestMissingMediaTypeCases'], $manifest['odfManifestMissingMediaTypeAssertions']),
            'UPSTREAM_TEST_MANIFEST.json must retain ODF/ODT mapped evidence counters'
        );
        $t->same(1, $manifest['mappedOdfManifestMissingMediaTypeCases']);
        $t->same(22, $manifest['odfManifestMissingMediaTypeAssertions']);

        $t->same(false, is_file($repoRoot . DIRECTORY_SEPARATOR . 'PANDOC_STATUS.md'), 'PANDOC_STATUS.md must stay deleted; ODF/ODT readiness evidence lives in JSON and progress.md');
        $t->same(0, count($readiness['remainingCriticalGaps'] ?? []));
        $t->contains(
            "| ODF/ODT/OpenDocument | `odt` | ship-ready | {$localCases} | {$upstreamCases} | 0 critical gaps",
            $progress
        );
        $t->contains('Ship verdict | Shippable for native PHP ODT package import', $progress);
    },
];
