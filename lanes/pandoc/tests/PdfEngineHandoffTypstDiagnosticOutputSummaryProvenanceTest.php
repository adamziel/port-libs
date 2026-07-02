<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst diagnostic output summary keeps CLI review controls visible.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Diagnostic Output Summary Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped typst diagnostic output summary provenance case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedTypstDiagnosticOutputSummaryProvenanceCases'] ?? null);
        $t->same(43, $manifest['typstDiagnosticOutputSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstDiagnosticOutputSummaryProvenanceCases'] ?? null);
        $t->same(43, $manifest['benchmarkDenominator']['breakdown']['typstDiagnosticOutputSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstDiagnosticOutputSummaryProvenanceCases'] ?? null);
        $t->same(43, $manifest['benchmarkDenominator']['inventory']['typstDiagnosticOutputSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstDiagnosticOutputSummaryProvenanceCases'] ?? null);
        $t->same(43, $manifest['inventory']['typstDiagnosticOutputSummaryProvenanceAssertions'] ?? null);
    },

    'summarizes typst diagnostic output controls without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'outputPath' => 'build/diagnostic-output-summary.pdf',
            'source' => '= Typst Diagnostic Output Summary',
            'engineOptions' => [
                '--diagnostic-format=xml',
                '--diagnostic-format=json',
                '--color=rainbow',
                '--color=always',
            ],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst diagnostic output summary packet\n%%EOF\n";

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'build/diagnostic-output-summary.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'build/diagnostic-output-summary.pdf' => $pdfBytes,
            ],
        ]]);
        $summary = $plan['typstBoundarySummary'];
        $cases = [];
        foreach ($plan['typstBoundaryMatrix']['cases'] as $case) {
            $cases[$case['case']] = $case;
        }
        $diagnosticCase = $cases['diagnostic-output'];
        $expectedIssues = [
            'diagnostic-color-boundary-overridden',
            'diagnostic-color-invalid-boundary',
            'diagnostic-format-boundary-overridden',
            'diagnostic-format-invalid-boundary',
        ];

        $t->same('review', $summary['reviewStatus']);
        $t->same(true, $summary['diagnosticOutputPresent']);
        $t->same(2, $summary['diagnosticOutputControlCount']);
        $t->same('json', $summary['selectedDiagnosticFormat']);
        $t->same(true, $summary['selectedDiagnosticFormatMachineReadable']);
        $t->same('always', $summary['selectedDiagnosticColor']);
        $t->same('enabled', $summary['selectedDiagnosticAnsiColor']);
        $t->same(2, $summary['diagnosticFormatHistoryCount']);
        $t->same(2, $summary['diagnosticColorHistoryCount']);
        $t->same(2, $summary['diagnosticOutputOverrideCount']);
        $t->same(2, $summary['invalidDiagnosticOutputCount']);
        $t->same(2, $summary['diagnosticFormatIssueCount']);
        $t->same(2, $summary['diagnosticColorIssueCount']);
        $t->same(4, $summary['diagnosticOutputIssueCount']);
        $t->same($expectedIssues, $summary['diagnosticOutputIssues']);
        $t->contains('typst-boundary-summary-diagnostics:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-invalid-diagnostics:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-diagnostic-output-issues:4', implode(',', $plan['diagnostics']));
        $t->same('review', $diagnosticCase['reviewStatus']);
        $t->same('json', $diagnosticCase['details']['format']);
        $t->same('always', $diagnosticCase['details']['color']);
        $t->same(1, $diagnosticCase['details']['invalidFormatCount']);
        $t->same(1, $diagnosticCase['details']['invalidColorCount']);
        $t->same(2, $diagnosticCase['details']['overrideCount']);
        $t->same($expectedIssues, $diagnosticCase['issues']);
        $t->contains('diagnostic-output:diagnostic-format-invalid-boundary', implode(',', $plan['typstBoundaryMatrix']['issues']));
        $t->contains('diagnostic-output:diagnostic-color-invalid-boundary', implode(',', $plan['typstBoundaryMatrix']['issues']));
        $t->same(true, $result['ok']);
        $t->same($summary, $result['typstBoundarySummary']);
        $t->same($summary, $result['artifactProvenanceReview']['typstBoundarySummary']);
        $t->same($summary, $sequence['finalTypstBoundarySummary']);
        $t->same($plan['typstBoundaryMatrix'], $result['artifactProvenanceReview']['typstBoundaryMatrix']);
        $t->same($plan['typstBoundaryMatrix'], $sequence['finalTypstBoundaryMatrix']);
        $t->same('review', $result['artifactProvenanceReview']['reviewStatus']);
        $t->contains('typst-boundary-provenance:review', implode(',', $result['artifactProvenanceReview']['issues']));
    },
];
