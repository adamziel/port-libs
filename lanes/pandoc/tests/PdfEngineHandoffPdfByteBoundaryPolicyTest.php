<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst PDF byte boundary policy packet keeps boundary checks reviewable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'PDF Byte Boundary Policy Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'summarizes typst pdf byte boundary policies without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'sourcePath' => 'workspace/main.typ',
            'outputPath' => 'workspace/build/byte-boundary-policy.pdf',
            'source' => '= PDF Byte Boundary Policy',
            'engineOptions' => ['--root=workspace'],
        ]);

        $body = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n" . implode("\n", [
            '1 0 obj',
            '<< /Type /Catalog >>',
            'endobj',
            '',
        ]);
        $xrefOffset = strlen($body);
        $pdfBytes = $body . implode("\n", [
            'xref',
            '0 2',
            '0000000000 65535 f ',
            '0000000009 00000 n ',
            'trailer',
            '<< /Size 2 /Root 1 0 R >>',
            'startxref',
            (string) $xrefOffset,
            '%%EOF',
            '',
        ]);
        $badPdfBytes = implode("\n", [
            '%PDF-9.9 draft',
            '1 0 obj',
            '<< /Type /Catalog >>',
            'endobj',
            'trailer',
            '<< /Size 2 /Root 1 0 R >>',
            'startxref',
            '999999',
            '%%EOF',
            'trailing bytes',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'workspace/build/byte-boundary-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'workspace/build/byte-boundary-policy.pdf' => $pdfBytes,
            ],
        ]]);
        $badResult = $handoff->fakeRun($plan, [
            'files' => [
                'workspace/build/byte-boundary-policy.pdf' => $badPdfBytes,
            ],
        ]);

        $expectedPolicy = [
            'reviewStatus' => 'ok',
            'policyCount' => 3,
            'presentPolicyCount' => 3,
            'missingPolicyCount' => 0,
            'reviewPolicyCount' => 0,
            'policyStatuses' => [
                'eof-marker' => 'ok',
                'header' => 'ok',
                'startxref' => 'ok',
            ],
            'policyStatusCounts' => [
                'ok' => 3,
            ],
            'issueCount' => 0,
            'issueCounts' => [],
            'issues' => [],
        ];

        $t->same(true, $result['ok']);
        $t->same($expectedPolicy, $result['pdfByteBoundaryPolicy']);
        $t->same($expectedPolicy, $result['artifactProvenanceReview']['pdfByteBoundaryPolicy']);
        $t->same('ok', $result['artifactProvenanceReview']['reviewStatus']);
        $t->contains('pdf-byte-boundary-policy:ok', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-boundary-policies:3', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expectedPolicy, $sequence['finalPdfByteBoundaryPolicy']);

        $expectedReviewPolicy = [
            'reviewStatus' => 'review',
            'policyCount' => 3,
            'presentPolicyCount' => 3,
            'missingPolicyCount' => 0,
            'reviewPolicyCount' => 3,
            'policyStatuses' => [
                'eof-marker' => 'review',
                'header' => 'review',
                'startxref' => 'review',
            ],
            'policyStatusCounts' => [
                'review' => 3,
            ],
            'issueCount' => 4,
            'issueCounts' => [
                'eof-marker:pdf-eof-marker-not-final' => 1,
                'header:pdf-header-line-extra-bytes' => 1,
                'header:pdf-header-version-unusual' => 1,
                'startxref:startxref-out-of-bounds' => 1,
            ],
            'issues' => [
                'eof-marker:pdf-eof-marker-not-final',
                'header:pdf-header-line-extra-bytes',
                'header:pdf-header-version-unusual',
                'startxref:startxref-out-of-bounds',
            ],
        ];

        $t->same(false, $badResult['ok']);
        $t->same('truncated-pdf-output', $badResult['reason']);
        $t->same($expectedReviewPolicy, $badResult['pdfByteBoundaryPolicy']);
        $t->same($expectedReviewPolicy, $badResult['artifactProvenanceReview']['pdfByteBoundaryPolicy']);
        $t->same('failed', $badResult['artifactProvenanceReview']['reviewStatus']);
        $t->contains('pdf-byte-boundary-policy:review', implode(',', $badResult['artifactProvenanceReview']['issues']));
        $t->contains('pdf-byte-boundary-policy:review', implode(',', $badResult['diagnostics']));
        $t->contains('pdf-byte-boundary-review-policies:3', implode(',', $badResult['diagnostics']));
        $t->contains('pdf-byte-boundary-policy-issue:header:pdf-header-version-unusual:1', implode(',', $badResult['diagnostics']));
        $t->contains('pdf-byte-boundary-policy-issue:eof-marker:pdf-eof-marker-not-final:1', implode(',', $badResult['diagnostics']));
        $t->contains('pdf-byte-boundary-policy-issue:startxref:startxref-out-of-bounds:1', implode(',', $badResult['diagnostics']));
    },
];
