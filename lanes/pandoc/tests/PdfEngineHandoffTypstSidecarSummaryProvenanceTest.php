<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst sidecar summary packet keeps dependency and timing outputs reviewable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Sidecar Summary Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped typst sidecar summary provenance case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstSidecarSummaryProvenanceCases'] ?? null);
        $t->same(47, $manifest['typstSidecarSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstSidecarSummaryProvenanceCases'] ?? null);
        $t->same(47, $manifest['benchmarkDenominator']['breakdown']['typstSidecarSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstSidecarSummaryProvenanceCases'] ?? null);
        $t->same(47, $manifest['benchmarkDenominator']['inventory']['typstSidecarSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstSidecarSummaryProvenanceCases'] ?? null);
        $t->same(47, $manifest['inventory']['typstSidecarSummaryProvenanceAssertions'] ?? null);
    },

    'summarizes typst sidecar history and selected outputs without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'sourcePath' => 'workspace/sidecar-summary.typ',
            'outputPath' => 'build/sidecar-summary.pdf',
            'source' => '= Typst Sidecar Summary',
            'engineOptions' => [
                '--deps=-',
                '--deps=build/sidecar-summary.d',
                '--deps-format=bad format',
                '--deps-format=json',
                '--timings=https://trace.example.invalid/timings.json',
                '--timings=build/sidecar-summary-timings.json',
            ],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst sidecar summary packet\n%%EOF\n";
        $depfile = "build/sidecar-summary.pdf: workspace/sidecar-summary.typ\n";
        $timingsBytes = '{"traceEvents":[{"name":"compile","args":{"file":"workspace/sidecar-summary.typ"}}]}';

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'build/sidecar-summary.d' => $depfile,
                'build/sidecar-summary-timings.json' => $timingsBytes,
                'build/sidecar-summary.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'build/sidecar-summary.d' => $depfile,
                'build/sidecar-summary-timings.json' => $timingsBytes,
                'build/sidecar-summary.pdf' => $pdfBytes,
            ],
        ]]);

        $summary = $plan['typstBoundarySummary'];

        $t->same(['build/sidecar-summary.d', 'build/sidecar-summary-timings.json'], $plan['expectedEngineArtifacts']);
        $t->same('review', $summary['reviewStatus']);
        $t->same(2, $summary['sidecarOutputCount']);
        $t->same(true, $summary['dependencyOutputPresent']);
        $t->same('build/sidecar-summary.d', $summary['dependencyOutputPath']);
        $t->same('relative', $summary['dependencyOutputKind']);
        $t->same(true, $summary['dependencyOutputSafe']);
        $t->same(2, $summary['dependencyOutputHistoryCount']);
        $t->same(1, $summary['dependencyOutputOverrideCount']);
        $t->same('json', $summary['dependencyFormat']);
        $t->same(false, $summary['dependencyFormatMakeCompatible']);
        $t->same(true, $summary['dependencyFormatMachineReadable']);
        $t->same(2, $summary['dependencyFormatHistoryCount']);
        $t->same(1, $summary['dependencyFormatOverrideCount']);
        $t->same(true, $summary['timingsOutputPresent']);
        $t->same('build/sidecar-summary-timings.json', $summary['timingsOutputPath']);
        $t->same('relative', $summary['timingsOutputKind']);
        $t->same(true, $summary['timingsOutputSafe']);
        $t->same(2, $summary['timingsOutputHistoryCount']);
        $t->same(1, $summary['timingsOutputOverrideCount']);
        $t->same(3, $summary['sidecarOutputOverrideCount']);
        $t->same(1, $summary['invalidDependencyOutputCount']);
        $t->same(1, $summary['invalidDependencyFormatCount']);
        $t->same(1, $summary['invalidTimingsOutputCount']);
        $t->same(3, $summary['invalidSidecarOutputCount']);
        $t->same(6, $summary['sidecarOutputIssueCount']);
        $t->contains('typst-boundary-summary-dependency-format:json', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-sidecar-overrides:3', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-invalid-sidecars:3', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-sidecar-issues:6', implode(',', $plan['diagnostics']));
        $t->same(true, $result['ok']);
        $t->same($summary, $result['typstBoundarySummary']);
        $t->same($summary, $result['artifactProvenanceReview']['typstBoundarySummary']);
        $t->same($summary, $sequence['finalTypstBoundarySummary']);
        $t->same('review', $result['artifactProvenanceReview']['reviewStatus']);
        $t->contains('typst-boundary-provenance:review', implode(',', $result['artifactProvenanceReview']['issues']));
        $t->same(hash('sha256', $depfile), $result['producedArtifactsSha256']['build/sidecar-summary.d']);
        $t->same(hash('sha256', $timingsBytes), $result['producedArtifactsSha256']['build/sidecar-summary-timings.json']);
        $t->same(6, $result['typstBoundaryMatrix']['caseIssueCounts']['sidecar-outputs']);
    },
];
