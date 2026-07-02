<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst diagnostic output summary packet keeps reviewer policy visible.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst Diagnostic Output Summary Packet',
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
            'source' => '= Typst Diagnostic Output Summary Packet',
            'engineOptions' => [
                '--diagnostic-format=xml',
                '--diagnostic-format=json',
                '--color=rainbow',
                '--color=never',
            ],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst diagnostic output summary packet\n%%EOF\n";
        $summary = $plan['typstBoundarySummary'];
        $expectedIssues = [
            'diagnostic-color-boundary-overridden',
            'diagnostic-color-invalid-boundary',
            'diagnostic-format-boundary-overridden',
            'diagnostic-format-invalid-boundary',
        ];

        $cases = [];
        foreach ($plan['typstBoundaryMatrix']['cases'] as $case) {
            $cases[$case['case']] = $case;
        }

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

        $t->same(true, $summary['diagnosticOutputPresent']);
        $t->same(2, $summary['diagnosticOutputControlCount']);
        $t->same('json', $summary['diagnosticFormat']);
        $t->same(true, $summary['diagnosticFormatMachineReadable']);
        $t->same(true, $summary['diagnosticFormatSafe']);
        $t->same('never', $summary['diagnosticColor']);
        $t->same('disabled', $summary['diagnosticAnsiColor']);
        $t->same(true, $summary['diagnosticColorSafe']);
        $t->same(2, $summary['diagnosticFormatHistoryCount']);
        $t->same(2, $summary['diagnosticColorHistoryCount']);
        $t->same(2, $summary['diagnosticOutputOverrideCount']);
        $t->same(1, $summary['diagnosticFormatOverrideCount']);
        $t->same(1, $summary['diagnosticColorOverrideCount']);
        $t->same(2, $summary['invalidDiagnosticOutputCount']);
        $t->same(4, $summary['diagnosticOutputIssueCount']);
        $t->same($expectedIssues, $summary['diagnosticOutputIssues']);
        $t->contains('typst-boundary-summary-diagnostics:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-invalid-diagnostics:2', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-diagnostic-issues:4', implode(',', $plan['diagnostics']));
        $t->same($expectedIssues, $cases['diagnostic-output']['issues']);
        $t->same(true, $result['ok']);
        $t->same($summary, $result['typstBoundarySummary']);
        $t->same($summary, $result['artifactProvenanceReview']['typstBoundarySummary']);
        $t->same($summary, $sequence['finalTypstBoundarySummary']);
    },
];
