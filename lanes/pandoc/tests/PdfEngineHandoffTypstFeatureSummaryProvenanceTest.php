<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst feature summary packet keeps selected and shadowed feature provenance reviewable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Feature Summary Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped typst feature summary provenance case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstFeatureSummaryProvenanceCases'] ?? null);
        $t->same(56, $manifest['typstFeatureSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstFeatureSummaryProvenanceCases'] ?? null);
        $t->same(56, $manifest['benchmarkDenominator']['breakdown']['typstFeatureSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstFeatureSummaryProvenanceCases'] ?? null);
        $t->same(56, $manifest['benchmarkDenominator']['inventory']['typstFeatureSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstFeatureSummaryProvenanceCases'] ?? null);
        $t->same(56, $manifest['inventory']['typstFeatureSummaryProvenanceAssertions'] ?? null);
    },

    'summarizes typst feature gate selections and environment shadows without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'outputPath' => 'build/feature-summary.pdf',
            'source' => '= Typst Feature Summary',
            'engineOptions' => [
                '--features=html,unsafe feature,',
                '--features=html,a11y',
            ],
            'engineEnvironment' => [
                'TYPST_FEATURES' => 'legacy-html,packages',
            ],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst feature summary packet\n%%EOF\n";
        $expectedIssues = [
            'features-boundary-overridden',
            'features-empty-token-boundary',
            'features-environment-shadowed',
            'features-invalid-token-boundary:unsafe feature',
        ];
        $expectedHistory = [
            [
                'raw' => 'html,unsafe feature,',
                'value' => 'html,unsafe feature,',
                'features' => ['html'],
                'featureCount' => 1,
                'safe' => false,
                'issues' => [
                    'features-invalid-token-boundary:unsafe feature',
                    'features-empty-token-boundary',
                ],
            ],
            [
                'raw' => 'html,a11y',
                'value' => 'html,a11y',
                'features' => ['html', 'a11y'],
                'featureCount' => 2,
                'safe' => true,
                'issues' => [],
            ],
        ];

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'build/feature-summary.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'build/feature-summary.pdf' => $pdfBytes,
            ],
        ]]);
        $summary = $plan['typstBoundarySummary'];
        $cases = [];
        foreach ($plan['typstBoundaryMatrix']['cases'] as $case) {
            $cases[$case['case']] = $case;
        }
        $featureCase = $cases['feature-gates'];

        $t->same('review', $summary['reviewStatus']);
        $t->same(2, $summary['featureGateCount']);
        $t->same(2, $summary['selectedFeatureGateCount']);
        $t->same(['html', 'a11y'], $summary['selectedFeatureGates']);
        $t->same('html,a11y', $summary['selectedFeatureGateValue']);
        $t->same(null, $summary['selectedFeatureGateSource']);
        $t->same(2, $summary['featureGateHistoryCount']);
        $t->same(1, $summary['invalidFeatureGateHistoryCount']);
        $t->same(1, $summary['featureGateOverrideCount']);
        $t->same(2, $summary['environmentFeatureGateCount']);
        $t->same(['legacy-html', 'packages'], $summary['environmentFeatureGates']);
        $t->same(true, $summary['featureGateEnvironmentPresent']);
        $t->same(true, $summary['featureGateEnvironmentShadowed']);
        $t->same('TYPST_FEATURES', $summary['featureGateEnvironmentVariable']);
        $t->same(4, $summary['featureGateIssueCount']);
        $t->same($expectedIssues, $summary['featureGateIssues']);
        $t->contains('typst-boundary-summary-feature-gates:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-feature-gate-history:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-feature-gate-shadowed', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-feature-gate-issues:4', implode(',', $plan['diagnostics']));
        $t->same($expectedHistory, $plan['typstBoundaryProvenance']['featureGateHistory']);
        $t->same('html,a11y', $plan['typstBoundaryProvenance']['featureGateEnvironment']['selected']);
        $t->same(['environment-shadows', 'boundary-overrides', 'feature-gates', 'output-format'], array_column($plan['typstBoundaryMatrix']['cases'], 'case'));
        $t->same(4, $plan['typstBoundaryMatrix']['caseCount']);
        $t->same(3, $plan['typstBoundaryMatrix']['reviewCaseCount']);
        $t->same(4, $featureCase['observed']);
        $t->same('review', $featureCase['reviewStatus']);
        $t->same(2, $featureCase['details']['featureCount']);
        $t->same(2, $featureCase['details']['environmentFeatureCount']);
        $t->same(['html', 'a11y'], $featureCase['details']['features']);
        $t->same(['legacy-html', 'packages'], $featureCase['details']['environmentFeatures']);
        $t->same('TYPST_FEATURES', $featureCase['details']['environmentVariable']);
        $t->same(true, $featureCase['details']['environmentShadowed']);
        $t->same('html,a11y', $featureCase['details']['selected']);
        $t->same(2, $featureCase['details']['historyEntryCount']);
        $t->same(1, $featureCase['details']['invalidFeatureHistoryCount']);
        $t->same(1, $featureCase['details']['overrideCount']);
        $t->same($expectedIssues, $featureCase['issues']);
        $t->contains('environment-shadows:features-environment-shadowed', implode(',', $plan['typstBoundaryMatrix']['issues']));
        $t->contains('feature-gates:features-environment-shadowed', implode(',', $plan['typstBoundaryMatrix']['issues']));
        $t->same(true, $result['ok']);
        $t->same($summary, $result['typstBoundarySummary']);
        $t->same($summary, $result['artifactProvenanceReview']['typstBoundarySummary']);
        $t->same($summary, $sequence['finalTypstBoundarySummary']);
        $t->same($plan['typstBoundaryMatrix'], $result['typstBoundaryMatrix']);
        $t->same($result['typstBoundaryMatrix'], $result['artifactProvenanceReview']['typstBoundaryMatrix']);
        $t->same($result['typstBoundaryMatrix'], $sequence['finalTypstBoundaryMatrix']);
        $t->same($expectedIssues, $sequence['finalTypstBoundarySummary']['featureGateIssues']);
    },
];
