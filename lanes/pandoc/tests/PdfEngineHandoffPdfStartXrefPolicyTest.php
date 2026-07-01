<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst PDF startxref policy packet keeps xref offsets reviewable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'PDF Startxref Policy Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

$previewAt = static function (string $bytes, int $offset): string {
    $preview = preg_replace('/\s+/', ' ', substr($bytes, $offset, 64));

    return trim(is_string($preview) ? $preview : '');
};

return [
    'records mapped pdf startxref policy case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedPdfStartXrefPolicyCases'] ?? null);
        $t->same(36, $manifest['pdfStartXrefPolicyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedPdfStartXrefPolicyCases'] ?? null);
        $t->same(36, $manifest['benchmarkDenominator']['breakdown']['pdfStartXrefPolicyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedPdfStartXrefPolicyCases'] ?? null);
        $t->same(36, $manifest['benchmarkDenominator']['inventory']['pdfStartXrefPolicyAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedPdfStartXrefPolicyCases'] ?? null);
        $t->same(36, $manifest['inventory']['pdfStartXrefPolicyAssertions'] ?? null);
    },

    'records typst pdf startxref policy without executing' => static function (TestRunner $t) use ($document, $previewAt): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'sourcePath' => 'workspace/main.typ',
            'outputPath' => 'workspace/build/startxref-policy.pdf',
            'source' => '= PDF Startxref Policy',
            'engineOptions' => ['--root=workspace'],
        ]);

        $body = implode("\n", [
            '%PDF-1.7',
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
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog >>',
            'endobj',
            'trailer',
            '<< /Size 2 /Root 1 0 R >>',
            'startxref',
            '999999',
            '%%EOF',
            '',
        ]);
        $missingPdfBytes = implode("\n", [
            '%PDF-1.7',
            '1 0 obj',
            '<< /Type /Catalog >>',
            'endobj',
            'trailer',
            '<< /Size 2 /Root 1 0 R >>',
            '%%EOF',
            '',
        ]);

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'workspace/build/startxref-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'workspace/build/startxref-policy.pdf' => $pdfBytes,
            ],
        ]]);
        $badResult = $handoff->fakeRun($plan, [
            'files' => [
                'workspace/build/startxref-policy.pdf' => $badPdfBytes,
            ],
        ]);
        $missingResult = $handoff->fakeRun($plan, [
            'files' => [
                'workspace/build/startxref-policy.pdf' => $missingPdfBytes,
            ],
        ]);

        $expectedPolicy = [
            'reviewStatus' => 'ok',
            'revisionCount' => 1,
            'startXrefCount' => 1,
            'missingStartXrefCount' => 0,
            'outOfBoundsCount' => 0,
            'duplicateOffsetCount' => 0,
            'latestStartXref' => $xrefOffset,
            'targetKindCounts' => ['xref-table' => 1],
            'targets' => [[
                'revision' => 1,
                'startxref' => $xrefOffset,
                'prev' => null,
                'inBounds' => true,
                'targetKind' => 'xref-table',
                'targetPreview' => $previewAt($pdfBytes, $xrefOffset),
            ]],
            'issues' => [],
        ];

        $t->same(true, $result['ok']);
        $t->same(true, $result['pdfTrailerComplete']);
        $t->same([$xrefOffset], $result['pdfStartXrefOffsets']);
        $t->same($expectedPolicy, $result['pdfStartXrefPolicy']);
        $t->same($expectedPolicy, $result['artifactProvenanceReview']['pdfStartXrefPolicy']);
        $t->contains('pdf-byte-startxref:1', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-startxref-policy:ok', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-startxref-target:xref-table:1', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expectedPolicy, $sequence['finalPdfStartXrefPolicy']);

        $t->same(true, $badResult['ok']);
        $t->same('review', $badResult['pdfStartXrefPolicy']['reviewStatus']);
        $t->same(1, $badResult['pdfStartXrefPolicy']['outOfBoundsCount']);
        $t->same(999999, $badResult['pdfStartXrefPolicy']['latestStartXref']);
        $t->same([], $badResult['pdfStartXrefPolicy']['targetKindCounts']);
        $t->same([
            [
                'revision' => 1,
                'startxref' => 999999,
                'prev' => null,
                'inBounds' => false,
                'targetKind' => null,
                'targetPreview' => null,
            ],
        ], $badResult['pdfStartXrefPolicy']['targets']);
        $t->same(['startxref-out-of-bounds'], $badResult['pdfStartXrefPolicy']['issues']);
        $t->contains('pdf-byte-startxref-policy:review', implode(',', $badResult['diagnostics']));
        $t->contains('pdf-byte-startxref-out-of-bounds:1', implode(',', $badResult['diagnostics']));
        $t->contains('pdf-byte-startxref-policy-issue:startxref-out-of-bounds', implode(',', $badResult['diagnostics']));

        $t->same(true, $missingResult['ok']);
        $t->same('review', $missingResult['pdfStartXrefPolicy']['reviewStatus']);
        $t->same(0, $missingResult['pdfStartXrefPolicy']['startXrefCount']);
        $t->same(1, $missingResult['pdfStartXrefPolicy']['missingStartXrefCount']);
        $t->same(null, $missingResult['pdfStartXrefPolicy']['latestStartXref']);
        $t->same(['startxref-missing'], $missingResult['pdfStartXrefPolicy']['issues']);
        $t->contains('pdf-byte-startxref-missing:1', implode(',', $missingResult['diagnostics']));
        $t->contains('pdf-byte-startxref-policy-issue:startxref-missing', implode(',', $missingResult['diagnostics']));
    },
];
