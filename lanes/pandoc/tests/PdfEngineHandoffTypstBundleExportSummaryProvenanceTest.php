<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst bundle export summary packet keeps multi-file output boundaries reviewable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Bundle Export Summary Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped typst bundle export summary provenance case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstBundleExportSummaryProvenanceCases'] ?? null);
        $t->same(51, $manifest['typstBundleExportSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstBundleExportSummaryProvenanceCases'] ?? null);
        $t->same(51, $manifest['benchmarkDenominator']['breakdown']['typstBundleExportSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstBundleExportSummaryProvenanceCases'] ?? null);
        $t->same(51, $manifest['benchmarkDenominator']['inventory']['typstBundleExportSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstBundleExportSummaryProvenanceCases'] ?? null);
        $t->same(51, $manifest['inventory']['typstBundleExportSummaryProvenanceAssertions'] ?? null);
    },

    'summarizes typst bundle export boundaries without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $missingFeaturePlan = $handoff->plan($document(), [
            'engine' => 'typst',
            'outputPath' => 'build/bundle-summary-missing-feature.pdf',
            'source' => '= Typst Bundle Summary Missing Feature',
            'engineOptions' => ['--format=bundle'],
        ]);
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'outputPath' => 'build/bundle-summary.pdf',
            'source' => '= Typst Bundle Summary',
            'engineOptions' => [
                '--features=bundle,html',
                '--format=bundle',
            ],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst bundle summary packet\n%%EOF\n";

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'build/bundle-summary.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'build/bundle-summary.pdf' => $pdfBytes,
            ],
        ]]);
        $summary = $plan['typstBoundarySummary'];
        $cases = [];
        foreach ($plan['typstBoundaryMatrix']['cases'] as $case) {
            $cases[$case['case']] = $case;
        }
        $bundleDetails = $cases['bundle-export']['details'];

        $t->same('review', $summary['reviewStatus']);
        $t->same(1, $summary['outputFormatControlCount']);
        $t->same(1, $summary['outputFormatEntryCount']);
        $t->same(1, $summary['outputFormatIssueCount']);
        $t->same(1, $summary['bundleExportControlCount']);
        $t->same(true, $summary['bundleExportEnabled']);
        $t->same('bundle', $summary['bundleExportFormat']);
        $t->same(true, $summary['bundleExportFeatureEnabled']);
        $t->same('engine-option', $summary['bundleExportFeatureSource']);
        $t->same(2, $summary['bundleExportFeatureCount']);
        $t->same(['bundle', 'html'], $summary['bundleExportFeatures']);
        $t->same(true, $summary['bundleExportMultiFileOutput']);
        $t->same(true, $summary['bundleExportAssetOutputPossible']);
        $t->same(1, $summary['bundleExportIssueCount']);
        $t->same(['bundle-output-multi-file-boundary'], $summary['bundleExportIssues']);
        $t->same(2, $summary['issueCount']);
        $t->same(1, $missingFeaturePlan['typstBoundarySummary']['bundleExportControlCount']);
        $t->same(false, $missingFeaturePlan['typstBoundarySummary']['bundleExportFeatureEnabled']);
        $t->same(2, $missingFeaturePlan['typstBoundarySummary']['bundleExportIssueCount']);
        $t->contains('typst-boundary-summary-bundle-export-feature:missing', implode(',', $missingFeaturePlan['diagnostics']));
        $t->contains('typst-boundary-summary-bundle-export:1', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-bundle-export-format:bundle', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-bundle-export-feature:enabled', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-bundle-export-multi-file-output', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-bundle-export-issues:1', implode(',', $plan['diagnostics']));
        $t->contains('typst-bundle-export-issues:1', implode(',', $plan['diagnostics']));
        $t->same(true, $result['ok']);
        $t->same($summary, $result['typstBoundarySummary']);
        $t->same($summary, $result['artifactProvenanceReview']['typstBoundarySummary']);
        $t->same($summary, $sequence['finalTypstBoundarySummary']);
        $t->same(1, $cases['bundle-export']['observed']);
        $t->same($summary['bundleExportEnabled'], $bundleDetails['enabled']);
        $t->same($summary['bundleExportFormat'], $bundleDetails['format']);
        $t->same($summary['bundleExportFeatureEnabled'], $bundleDetails['featureEnabled']);
        $t->same($summary['bundleExportFeatureSource'], $bundleDetails['featureSource']);
        $t->same($summary['bundleExportFeatureCount'], $bundleDetails['featureCount']);
        $t->same($summary['bundleExportMultiFileOutput'], $bundleDetails['multiFileOutput']);
        $t->same($summary['bundleExportAssetOutputPossible'], $bundleDetails['assetOutputPossible']);
        $t->same($summary['bundleExportIssues'], $cases['bundle-export']['issues']);
        $t->contains('bundle-export:bundle-output-multi-file-boundary', implode(',', $plan['typstBoundaryMatrix']['issues']));
        $t->same('review', $result['artifactProvenanceReview']['reviewStatus']);
        $t->same($plan['typstBoundaryMatrix'], $result['artifactProvenanceReview']['typstBoundaryMatrix']);
        $t->same($plan['typstBoundaryMatrix'], $sequence['finalTypstBoundaryMatrix']);
    },
];
