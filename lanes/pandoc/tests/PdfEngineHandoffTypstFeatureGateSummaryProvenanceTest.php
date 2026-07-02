<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst feature gate summary packet keeps CLI and environment feature boundaries reviewable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Feature Gate Summary Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped typst feature gate summary provenance case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstFeatureGateSummaryProvenanceCases'] ?? null);
        $t->same(44, $manifest['typstFeatureGateSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstFeatureGateSummaryProvenanceCases'] ?? null);
        $t->same(44, $manifest['benchmarkDenominator']['breakdown']['typstFeatureGateSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstFeatureGateSummaryProvenanceCases'] ?? null);
        $t->same(44, $manifest['benchmarkDenominator']['inventory']['typstFeatureGateSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstFeatureGateSummaryProvenanceCases'] ?? null);
        $t->same(44, $manifest['inventory']['typstFeatureGateSummaryProvenanceAssertions'] ?? null);
    },

    'summarizes typst feature gates and shadowed environment features without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'outputPath' => 'build/feature-gate-summary.pdf',
            'source' => '= Typst Feature Gate Summary',
            'engineOptions' => [
                '--features=html',
                '--features=packages,a11y',
            ],
            'engineEnvironment' => [
                'TYPST_FEATURES' => 'legacy-html,unsafe feature,',
            ],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst feature gate summary packet\n%%EOF\n";

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'build/feature-gate-summary.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'build/feature-gate-summary.pdf' => $pdfBytes,
            ],
        ]]);
        $summary = $plan['typstBoundarySummary'];
        $cases = [];
        foreach ($plan['typstBoundaryMatrix']['cases'] as $case) {
            $cases[$case['case']] = $case;
        }
        $featureDetails = $cases['feature-gates']['details'];

        $t->same('review', $summary['reviewStatus']);
        $t->same(2, $summary['featureGateCount']);
        $t->same(['packages', 'a11y'], $summary['featureGateFeatures']);
        $t->same('packages,a11y', $summary['featureGateValue']);
        $t->same(2, $summary['featureGateHistoryCount']);
        $t->same(0, $summary['invalidFeatureGateHistoryCount']);
        $t->same(1, $summary['featureGateOverrideCount']);
        $t->same(1, $summary['featureGateEnvironmentFeatureCount']);
        $t->same(['legacy-html'], $summary['featureGateEnvironmentFeatures']);
        $t->same('TYPST_FEATURES', $summary['featureGateEnvironmentVariable']);
        $t->same(true, $summary['featureGateEnvironmentShadowed']);
        $t->same('packages,a11y', $summary['featureGateEnvironmentSelected']);
        $t->same(4, $summary['featureGateIssueCount']);
        $t->same([
            'features-boundary-overridden',
            'features-empty-token-boundary',
            'features-environment-shadowed',
            'features-invalid-token-boundary:unsafe feature',
        ], $summary['featureGateIssues']);
        $t->contains('typst-boundary-summary-feature-gates:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-feature-gate-history:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-feature-gate-environment:1', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-feature-gate-environment-shadowed', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-feature-gate-issues:4', implode(',', $plan['diagnostics']));
        $t->same(true, $result['ok']);
        $t->same($summary, $result['typstBoundarySummary']);
        $t->same($summary, $result['artifactProvenanceReview']['typstBoundarySummary']);
        $t->same($summary, $sequence['finalTypstBoundarySummary']);
        $t->same(3, $cases['feature-gates']['observed']);
        $t->same($summary['featureGateCount'], $featureDetails['featureCount']);
        $t->same($summary['featureGateFeatures'], $featureDetails['features']);
        $t->same($summary['featureGateEnvironmentFeatureCount'], $featureDetails['environmentFeatureCount']);
        $t->same($summary['featureGateEnvironmentFeatures'], $featureDetails['environmentFeatures']);
        $t->same($summary['featureGateEnvironmentShadowed'], $featureDetails['environmentShadowed']);
        $t->same($summary['featureGateEnvironmentSelected'], $featureDetails['selected']);
        $t->same($summary['featureGateHistoryCount'], $featureDetails['historyEntryCount']);
        $t->same($summary['featureGateOverrideCount'], $featureDetails['overrideCount']);
        $t->contains('feature-gates:features-environment-shadowed', implode(',', $plan['typstBoundaryMatrix']['issues']));
        $t->contains('boundary-overrides:features-boundary-overridden', implode(',', $plan['typstBoundaryMatrix']['issues']));
        $t->same($plan['typstBoundaryMatrix'], $result['artifactProvenanceReview']['typstBoundaryMatrix']);
        $t->same($plan['typstBoundaryMatrix'], $sequence['finalTypstBoundaryMatrix']);
    },
];
