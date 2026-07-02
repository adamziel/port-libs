<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('PDF destination policy packet keeps navigation provenance visible.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'PDF Destination Policy Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped pdf destination policy case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedPdfDestinationPolicyCases'] ?? null);
        $t->same(24, $manifest['pdfDestinationPolicyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedPdfDestinationPolicyCases'] ?? null);
        $t->same(24, $manifest['benchmarkDenominator']['breakdown']['pdfDestinationPolicyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedPdfDestinationPolicyCases'] ?? null);
        $t->same(24, $manifest['benchmarkDenominator']['inventory']['pdfDestinationPolicyAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedPdfDestinationPolicyCases'] ?? null);
        $t->same(24, $manifest['inventory']['pdfDestinationPolicyAssertions'] ?? null);
    },

    'summarizes pdf destination policy from produced bytes without executing engines' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), ['engine' => 'xelatex', 'outputPath' => 'packets/destination-policy.pdf']);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog /Pages 2 0 R /OpenAction [3 0 R /XYZ 72 720 1.25] /Outlines 9 0 R /Names << /Dests 8 0 R >> /Dests << /legacy [4 0 R /FitR 10 20 300 500] >> >>',
            'endobj',
            '2 0 obj',
            '<< /Type /Pages /Count 2 /Kids [3 0 R 4 0 R] >>',
            'endobj',
            '3 0 obj',
            '<< /Type /Page /Parent 2 0 R /Annots [6 0 R] >>',
            'endobj',
            '4 0 obj',
            '<< /Type /Page /Parent 2 0 R >>',
            'endobj',
            '6 0 obj',
            '<< /Type /Annot /Subtype /Link /Rect [72 640 360 672] /Dest [4 0 R /FitBH 640] >>',
            'endobj',
            '8 0 obj',
            '<< /Names [(intro) [3 0 R /FitH 700] (named) (chapter-two) (zoomed) 11 0 R] >>',
            'endobj',
            '9 0 obj',
            '<< /Type /Outlines /First 10 0 R /Last 10 0 R /Count 1 >>',
            'endobj',
            '10 0 obj',
            '<< /Title (Appendix) /Parent 9 0 R /Dest [4 0 R /FitV 144] >>',
            'endobj',
            '11 0 obj',
            '<< /D [4 0 R /XYZ null 512 2] >>',
            'endobj',
            'trailer',
            '<< /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);
        $expectedPolicy = [
            'reviewStatus' => 'review',
            'namedDestinationCount' => 4,
            'destinationOptionCount' => 7,
            'pageDestinationCount' => 6,
            'namedTargetCount' => 1,
            'unresolvedNamedTargetCount' => 1,
            'fitArgumentDestinationCount' => 6,
            'coordinateDestinationCount' => 6,
            'zoomDestinationCount' => 2,
            'fits' => [
                'FitBH' => 1,
                'FitH' => 1,
                'FitR' => 1,
                'FitV' => 1,
                'XYZ' => 2,
                'named' => 1,
            ],
            'destinationNames' => ['intro', 'legacy', 'named', 'zoomed'],
            'targetNames' => ['chapter-two'],
            'unresolvedNamedTargets' => ['chapter-two'],
            'issues' => [
                'destination-zoom-boundary',
                'explicit-destination-coordinate',
                'unresolved-named-destination-target',
            ],
        ];

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'packets/destination-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'packets/destination-policy.pdf' => $pdfBytes,
            ],
        ]]);
        $diagnostics = implode(',', $result['diagnostics']);

        $t->same(true, $result['ok']);
        $t->same($expectedPolicy, $result['pdfDestinationPolicy']);
        $t->same($expectedPolicy, $result['artifactProvenanceReview']['pdfDestinationPolicy']);
        $t->contains('pdf-byte-destination-policy:review', $diagnostics);
        $t->contains('pdf-byte-destination-policy-named:4', $diagnostics);
        $t->contains('pdf-byte-destination-policy-options:7', $diagnostics);
        $t->contains('pdf-byte-destination-policy-unresolved-targets:1', $diagnostics);
        $t->contains('pdf-byte-destination-policy-coordinates:6', $diagnostics);
        $t->contains('pdf-byte-destination-policy-zoom:2', $diagnostics);
        $t->contains('pdf-byte-destination-policy-fit:XYZ:2', $diagnostics);
        $t->contains('pdf-byte-destination-policy-issues:3', $diagnostics);
        $t->contains('pdf-byte-destination-policy-issue:explicit-destination-coordinate:1', $diagnostics);
        $t->contains('pdf-byte-destination-policy-issue:destination-zoom-boundary:1', $diagnostics);
        $t->contains('pdf-byte-destination-policy-issue:unresolved-named-destination-target:1', $diagnostics);
        $t->same(true, $sequence['ok']);
        $t->same($expectedPolicy, $sequence['finalPdfDestinationPolicy']);
    },
];
