<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst PDF standard-only controls stay grouped for provenance review.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'Typst PDF Standard Export Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'groups typst pdf standard-only controls into pdf export provenance packet' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'outputPath' => 'build/pdf-standard-export-packet.pdf',
            'source' => '= Typst PDF Standard Export Packet',
            'engineOptions' => [
                '--pdf-standard=1.7',
                '--pdf-standard=a-2b',
            ],
        ]);
        $pdfBytes = "%PDF-1.7\n% fake Typst PDF standard export packet\n%%EOF\n";

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'build/pdf-standard-export-packet.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'build/pdf-standard-export-packet.pdf' => $pdfBytes,
            ],
        ]]);

        $pdfExport = $plan['typstBoundaryProvenance']['pdfExport'];
        $summary = $plan['typstBoundarySummary'];

        $t->same(null, $pdfExport['pageSelection']);
        $t->same(['pdf-standard-boundary-overridden'], $pdfExport['issues']);
        $t->same('a-2b', $pdfExport['pdfStandard']['raw']);
        $t->same(['a-2b'], $pdfExport['pdfStandard']['standards']);
        $t->same(1, $summary['pdfExportControlCount']);
        $t->same(1, $summary['pdfExportPdfStandardCount']);
        $t->same(['a-2b'], $summary['pdfExportPdfStandards']);
        $t->same(2, $summary['pdfExportPdfStandardHistoryCount']);
        $t->same(1, $summary['pdfExportOverrideCount']);
        $t->same(1, $summary['pdfExportIssueCount']);
        $t->same(['pdf-standard-boundary-overridden'], $summary['pdfExportIssues']);
        $t->contains('typst-pdf-export-issues:1', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-pdf-export:1', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-pdf-standards:1', implode(',', $plan['diagnostics']));
        $t->contains('typst-boundary-summary-pdf-export-issues:1', implode(',', $plan['diagnostics']));
        $t->same(true, $result['ok']);
        $t->same($pdfExport, $result['typstBoundaryProvenance']['pdfExport']);
        $t->same($pdfExport, $result['artifactProvenanceReview']['typstBoundaryProvenance']['pdfExport']);
        $t->same($summary, $result['typstBoundarySummary']);
        $t->same($summary, $result['artifactProvenanceReview']['typstBoundarySummary']);
        $t->same($pdfExport, $sequence['finalTypstBoundaryProvenance']['pdfExport']);
        $t->same($summary, $sequence['finalTypstBoundarySummary']);
    },
];
