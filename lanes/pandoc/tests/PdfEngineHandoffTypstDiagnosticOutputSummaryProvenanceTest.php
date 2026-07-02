<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst diagnostic output summary packet keeps selected format and color reviewable.');

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
        $t->same(46, $manifest['typstDiagnosticOutputSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedTypstDiagnosticOutputSummaryProvenanceCases'] ?? null);
        $t->same(46, $manifest['benchmarkDenominator']['breakdown']['typstDiagnosticOutputSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedTypstDiagnosticOutputSummaryProvenanceCases'] ?? null);
        $t->same(46, $manifest['benchmarkDenominator']['inventory']['typstDiagnosticOutputSummaryProvenanceAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedTypstDiagnosticOutputSummaryProvenanceCases'] ?? null);
        $t->same(46, $manifest['inventory']['typstDiagnosticOutputSummaryProvenanceAssertions'] ?? null);
    },

    'summarizes typst diagnostic output selections without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'outputPath' => 'build/diagnostic-output-summary.pdf',
            'source' => '= Typst Diagnostic Output Summary',
            'engineOptions' => [
                '--diagnostic-format=xml',
                '--diagnostic-format=short',
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
        $details = $cases['diagnostic-output']['details'];

        $t->same('review', $summary['reviewStatus']);
        $t->same(true, $summary['diagnosticOutputPresent']);
        $t->same(2, $summary['diagnosticOutputControlCount']);
        $t->same('json', $summary['diagnosticFormat']);
        $t->same(true, $summary['diagnosticFormatMachineReadable']);
        $t->same(true, $summary['diagnosticFormatSafe']);
        $t->same(3, $summary['diagnosticFormatHistoryCount']);
        $t->same(1, $summary['diagnosticFormatOverrideCount']);
        $t->same('always', $summary['diagnosticColor']);
        $t->same('enabled', $summary['diagnosticColorAnsi']);
        $t->same(true, $summary['diagnosticColorSafe']);
        $t->same(2, $summary['diagnosticColorHistoryCount']);
        $t->same(1, $summary['diagnosticColorOverrideCount']);
        $t->same(2, $summary['diagnosticOutputOverrideCount']);
        $t->same(2, $summary['invalidDiagnosticOutputCount']);
        $t->same(4, $summary['diagnosticOutputIssueCount']);
        $t->same([
            'diagnostic-color-boundary-overridden',
            'diagnostic-color-invalid-boundary',
            'diagnostic-format-boundary-overridden',
            'diagnostic-format-invalid-boundary',
        ], $summary['diagnosticOutputIssues']);
        $t->contains('typst-boundary-summary-diagnostic-format:json', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-diagnostic-color:always', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-diagnostics:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-invalid-diagnostics:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-diagnostic-issues:4', implode(',', $plan['diagnostics']));
        $t->same(true, $result['ok']);
        $t->same($summary, $result['typstBoundarySummary']);
        $t->same($summary, $result['artifactProvenanceReview']['typstBoundarySummary']);
        $t->same($summary, $sequence['finalTypstBoundarySummary']);
        $t->same($summary['diagnosticFormat'], $details['format']);
        $t->same($summary['diagnosticFormatMachineReadable'], $details['machineReadable']);
        $t->same($summary['diagnosticFormatSafe'], $details['formatSafe']);
        $t->same($summary['diagnosticColor'], $details['color']);
        $t->same($summary['diagnosticColorAnsi'], $details['ansiColor']);
        $t->same($summary['diagnosticColorSafe'], $details['colorSafe']);
        $t->same(1, $details['invalidFormatCount']);
        $t->same(1, $details['invalidColorCount']);
        $t->same($summary['diagnosticOutputOverrideCount'], $details['overrideCount']);
        $t->same(4, $plan['typstBoundaryMatrix']['caseIssueCounts']['diagnostic-output']);
        $t->same('review', $result['artifactProvenanceReview']['reviewStatus']);
        $t->contains('typst-boundary-provenance:review', implode(',', $result['artifactProvenanceReview']['issues']));
    },
];
