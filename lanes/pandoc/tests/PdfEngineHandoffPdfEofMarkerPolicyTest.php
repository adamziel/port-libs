<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst PDF EOF marker policy packet keeps PDF boundary markers reviewable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'PDF EOF Marker Policy Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped pdf eof marker policy case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedPdfEofMarkerPolicyCases'] ?? null);
        $t->same(32, $manifest['pdfEofMarkerPolicyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedPdfEofMarkerPolicyCases'] ?? null);
        $t->same(32, $manifest['benchmarkDenominator']['breakdown']['pdfEofMarkerPolicyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedPdfEofMarkerPolicyCases'] ?? null);
        $t->same(32, $manifest['benchmarkDenominator']['inventory']['pdfEofMarkerPolicyAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedPdfEofMarkerPolicyCases'] ?? null);
        $t->same(32, $manifest['inventory']['pdfEofMarkerPolicyAssertions'] ?? null);
    },

    'records typst pdf eof marker policy without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'sourcePath' => 'workspace/main.typ',
            'outputPath' => 'workspace/build/eof-policy.pdf',
            'source' => '= PDF EOF Marker Policy',
            'engineOptions' => ['--root=workspace'],
        ]);
        $pdfBytes = implode("\n", [
            '%PDF-1.7',
            '% fake Typst PDF EOF marker policy packet',
            '%%EOF',
            '% appended incremental boundary',
            '%%EOF',
            '',
        ]);
        $badPdfBytes = "%PDF-1.7\n% fake Typst PDF EOF marker policy packet\n%%EOF\ntrailing bytes";

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'workspace/build/eof-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'workspace/build/eof-policy.pdf' => $pdfBytes,
            ],
        ]]);
        $badResult = $handoff->fakeRun($plan, [
            'files' => [
                'workspace/build/eof-policy.pdf' => $badPdfBytes,
            ],
        ]);

        $firstOffset = (int) strpos($pdfBytes, '%%EOF');
        $lastOffset = (int) strrpos($pdfBytes, '%%EOF');
        $expectedPolicy = [
            'reviewStatus' => 'review',
            'eofMarkerCount' => 2,
            'eofMarkerOffsets' => [$firstOffset, $lastOffset],
            'eofMarkerOffsetsTruncated' => false,
            'lastEofOffset' => $lastOffset,
            'trailingByteCount' => 1,
            'trailingNonWhitespaceByteCount' => 0,
            'completeTrailer' => true,
            'repeatedEofMarker' => true,
            'issues' => ['pdf-eof-marker-repeated'],
        ];
        $badLastOffset = (int) strrpos($badPdfBytes, '%%EOF');
        $badTrailingBytes = substr($badPdfBytes, $badLastOffset + strlen('%%EOF'));
        $badTrailingNonWhitespaceBytes = preg_replace('/\s+/', '', $badTrailingBytes);

        $t->same(true, $result['ok']);
        $t->same(true, $result['pdfTrailerComplete']);
        $t->same($expectedPolicy, $result['pdfEofMarkerPolicy']);
        $t->same($expectedPolicy, $result['artifactProvenanceReview']['pdfEofMarkerPolicy']);
        $t->same('review', $result['artifactProvenanceReview']['reviewStatus']);
        $t->contains('pdf-eof-marker-policy:review', implode(',', $result['artifactProvenanceReview']['issues']));
        $t->contains('pdf-byte-eof-marker-policy:review', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-eof-markers:2', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-eof-marker-policy-issue:pdf-eof-marker-repeated', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expectedPolicy, $sequence['finalPdfEofMarkerPolicy']);

        $t->same(false, $badResult['ok']);
        $t->same('truncated-pdf-output', $badResult['reason']);
        $t->same(false, $badResult['pdfEofMarkerPolicy']['completeTrailer']);
        $t->same('review', $badResult['pdfEofMarkerPolicy']['reviewStatus']);
        $t->same(1, $badResult['pdfEofMarkerPolicy']['eofMarkerCount']);
        $t->same([$badLastOffset], $badResult['pdfEofMarkerPolicy']['eofMarkerOffsets']);
        $t->same(strlen($badTrailingBytes), $badResult['pdfEofMarkerPolicy']['trailingByteCount']);
        $t->same(strlen(is_string($badTrailingNonWhitespaceBytes) ? $badTrailingNonWhitespaceBytes : ''), $badResult['pdfEofMarkerPolicy']['trailingNonWhitespaceByteCount']);
        $t->same(['pdf-eof-marker-not-final'], $badResult['pdfEofMarkerPolicy']['issues']);
        $t->contains('pdf-byte-eof-trailing-non-whitespace-bytes:', implode(',', $badResult['diagnostics']));
        $t->contains('pdf-byte-eof-marker-policy-issue:pdf-eof-marker-not-final', implode(',', $badResult['diagnostics']));
        $t->same('failed', $badResult['artifactProvenanceReview']['reviewStatus']);
        $t->contains('pdf-eof-marker-policy:review', implode(',', $badResult['artifactProvenanceReview']['issues']));
    },
];
