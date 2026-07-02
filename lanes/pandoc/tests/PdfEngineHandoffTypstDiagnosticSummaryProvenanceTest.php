<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst diagnostic summary packet keeps selected output controls reviewable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Diagnostic Summary Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped typst diagnostic summary provenance case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstDiagnosticSummaryProvenanceCases'] ?? null);
        $t->same(40, $manifest['typstDiagnosticSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstDiagnosticSummaryProvenanceCases'] ?? null);
        $t->same(40, $manifest['benchmarkDenominator']['breakdown']['typstDiagnosticSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstDiagnosticSummaryProvenanceCases'] ?? null);
        $t->same(40, $manifest['benchmarkDenominator']['inventory']['typstDiagnosticSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstDiagnosticSummaryProvenanceCases'] ?? null);
        $t->same(40, $manifest['inventory']['typstDiagnosticSummaryProvenanceAssertions'] ?? null);
    },

    'summarizes typst diagnostic output controls without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'outputPath' => 'build/diagnostic-summary.pdf',
            'source' => '= Typst Diagnostic Summary',
            'engineOptions' => [
                '--diagnostic-format=xml',
                '--diagnostic-format',
                'short',
                '--diagnostic-format=json',
                '--color=rainbow',
                '--color',
                'always',
            ],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst diagnostic summary packet\n%%EOF\n";

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'build/diagnostic-summary.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'build/diagnostic-summary.pdf' => $pdfBytes,
            ],
        ]]);
        $summary = $plan['typstBoundarySummary'];
        $matrix = $plan['typstBoundaryMatrix'];
        $cases = [];
        foreach ($matrix['cases'] as $case) {
            $cases[$case['case']] = $case;
        }
        $expectedDiagnostic = [
            'case' => 'diagnostic-output',
            'reviewStatus' => 'review',
            'observed' => 2,
            'details' => [
                'format' => 'json',
                'machineReadable' => true,
                'formatSafe' => true,
                'color' => 'always',
                'ansiColor' => 'enabled',
                'colorSafe' => true,
                'formatHistoryCount' => 3,
                'formatOverrideCount' => 1,
                'colorHistoryCount' => 2,
                'colorOverrideCount' => 1,
                'overrideCount' => 2,
                'invalidFormatCount' => 1,
                'invalidColorCount' => 1,
            ],
            'issues' => [
                'diagnostic-color-boundary-overridden',
                'diagnostic-color-invalid-boundary',
                'diagnostic-format-boundary-overridden',
                'diagnostic-format-invalid-boundary',
            ],
        ];

        $t->same('review', $summary['reviewStatus']);
        $t->same(true, $summary['diagnosticOutputPresent']);
        $t->same(2, $summary['diagnosticOutputControlCount']);
        $t->same(3, $summary['diagnosticFormatHistoryCount']);
        $t->same(2, $summary['diagnosticColorHistoryCount']);
        $t->same(2, $summary['diagnosticOutputOverrideCount']);
        $t->same(2, $summary['invalidDiagnosticOutputCount']);
        $t->same(4, $summary['issueCount']);
        $t->contains('typst-diagnostics-format:json', implode(',', $plan['diagnostics']));
        $t->contains('typst-diagnostics-color:always', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-diagnostics:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-invalid-diagnostics:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-overrides:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-issues:4', implode(',', $plan['diagnostics']));
        $t->same(['boundary-overrides', 'output-format', 'diagnostic-output'], array_column($matrix['cases'], 'case'));
        $t->same('review', $matrix['reviewStatus']);
        $t->same(3, $matrix['caseCount']);
        $t->same(2, $matrix['reviewCaseCount']);
        $t->same(6, $matrix['issueCount']);
        $t->same([
            'diagnosticColor' => 'always',
            'diagnosticFormat' => 'json',
        ], $cases['boundary-overrides']['details']['selectedByOption']);
        $t->same($expectedDiagnostic, $cases['diagnostic-output']);
        $t->contains('diagnostic-output:diagnostic-format-invalid-boundary', implode(',', $matrix['issues']));
        $t->contains('diagnostic-output:diagnostic-color-invalid-boundary', implode(',', $matrix['issues']));
        $t->same(true, $result['ok']);
        $t->same($summary, $result['typstBoundarySummary']);
        $t->same($summary, $result['artifactProvenanceReview']['typstBoundarySummary']);
        $t->same($summary, $sequence['finalTypstBoundarySummary']);
        $t->same($matrix, $result['typstBoundaryMatrix']);
        $t->same($matrix, $result['artifactProvenanceReview']['typstBoundaryMatrix']);
        $t->same($matrix, $sequence['finalTypstBoundaryMatrix']);
        $t->same('review', $result['artifactProvenanceReview']['reviewStatus']);
        $t->contains('typst-boundary-provenance:review', implode(',', $result['artifactProvenanceReview']['issues']));
    },
];
