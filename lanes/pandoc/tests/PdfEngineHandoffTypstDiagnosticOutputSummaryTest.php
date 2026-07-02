<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst diagnostic output controls stay visible in boundary summaries.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Diagnostic Output Summary',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'summarizes typst diagnostic output controls without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'outputPath' => 'build/diagnostic-output-summary.pdf',
            'source' => '= Typst Diagnostic Output Summary',
            'engineOptions' => [
                '--diagnostic-format=human',
                '--diagnostic-format=bad format',
                '--color=always',
                '--color=rainbow',
                '--color=never',
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

        $t->same('review', $summary['reviewStatus']);
        $t->same(true, $summary['diagnosticOutputPresent']);
        $t->same(2, $summary['diagnosticOutputControlCount']);
        $t->same(null, $summary['diagnosticFormat']);
        $t->same('bad format', $summary['diagnosticFormatValue']);
        $t->same(false, $summary['diagnosticFormatMachineReadable']);
        $t->same(false, $summary['diagnosticFormatSafe']);
        $t->same(2, $summary['diagnosticFormatHistoryCount']);
        $t->same(1, $summary['diagnosticFormatOverrideCount']);
        $t->same(1, $summary['invalidDiagnosticFormatCount']);
        $t->same('never', $summary['diagnosticColor']);
        $t->same('never', $summary['diagnosticColorValue']);
        $t->same('disabled', $summary['diagnosticAnsiColor']);
        $t->same(true, $summary['diagnosticColorSafe']);
        $t->same(3, $summary['diagnosticColorHistoryCount']);
        $t->same(1, $summary['diagnosticColorOverrideCount']);
        $t->same(1, $summary['invalidDiagnosticColorCount']);
        $t->same(2, $summary['diagnosticOutputOverrideCount']);
        $t->same(2, $summary['invalidDiagnosticOutputCount']);
        $t->same(4, $summary['diagnosticOutputIssueCount']);
        $t->same([
            'diagnostic-color-boundary-overridden',
            'diagnostic-color-invalid-boundary',
            'diagnostic-format-boundary-overridden',
            'diagnostic-format-invalid-boundary',
        ], $summary['diagnosticOutputIssues']);
        $t->contains('typst-diagnostics-format:invalid', implode(',', $plan['diagnostics']));
        $t->contains('typst-diagnostics-color:never', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-diagnostic-color:never', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-invalid-diagnostics:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-diagnostic-issues:4', implode(',', $plan['diagnostics']));

        $t->same('review', $diagnosticCase['reviewStatus']);
        $t->same(2, $diagnosticCase['observed']);
        $t->same(null, $diagnosticCase['details']['format']);
        $t->same(false, $diagnosticCase['details']['formatSafe']);
        $t->same('never', $diagnosticCase['details']['color']);
        $t->same('disabled', $diagnosticCase['details']['ansiColor']);
        $t->same(2, $diagnosticCase['details']['formatHistoryCount']);
        $t->same(3, $diagnosticCase['details']['colorHistoryCount']);
        $t->same(2, $diagnosticCase['details']['overrideCount']);
        $t->same(1, $diagnosticCase['details']['invalidFormatCount']);
        $t->same(1, $diagnosticCase['details']['invalidColorCount']);
        $t->same($summary, $result['typstBoundarySummary']);
        $t->same($summary, $result['artifactProvenanceReview']['typstBoundarySummary']);
        $t->same($plan['typstBoundaryMatrix'], $result['artifactProvenanceReview']['typstBoundaryMatrix']);
        $t->same($summary, $sequence['finalTypstBoundarySummary']);
        $t->same($plan['typstBoundaryMatrix'], $sequence['finalTypstBoundaryMatrix']);
    },
];
