<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\PdfEngineHandoff;

$document = static function (): AstNode {
    $parsed = (new MarkdownReader())->read('Typst PDF header policy packet keeps PDF boundary headers reviewable.');

    return new AstNode('document', [
        'meta' => [
            'title' => 'PDF Header Policy Packet',
            'author' => 'PDF Review Desk',
        ],
    ], $parsed->children);
};

return [
    'records mapped pdf header policy case count' => static function (TestRunner $t): void {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $manifest['mappedPdfHeaderPolicyCases'] ?? null);
        $t->same(38, $manifest['pdfHeaderPolicyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['breakdown']['mappedPdfHeaderPolicyCases'] ?? null);
        $t->same(38, $manifest['benchmarkDenominator']['breakdown']['pdfHeaderPolicyAssertions'] ?? null);
        $t->same(1, $manifest['benchmarkDenominator']['inventory']['mappedPdfHeaderPolicyCases'] ?? null);
        $t->same(38, $manifest['benchmarkDenominator']['inventory']['pdfHeaderPolicyAssertions'] ?? null);
        $t->same(1, $manifest['inventory']['mappedPdfHeaderPolicyCases'] ?? null);
        $t->same(38, $manifest['inventory']['pdfHeaderPolicyAssertions'] ?? null);
    },

    'records typst pdf header policy without executing' => static function (TestRunner $t) use ($document): void {
        $handoff = new PdfEngineHandoff();
        $plan = $handoff->plan($document(), [
            'engine' => 'typst',
            'sourcePath' => 'workspace/main.typ',
            'outputPath' => 'workspace/build/header-policy.pdf',
            'source' => '= PDF Header Policy',
            'engineOptions' => ['--root=workspace'],
        ]);
        $binaryComment = "%\xE2\xE3\xCF\xD3";
        $pdfBytes = "%PDF-1.7\r\n" . $binaryComment . "\r\n" . implode("\n", [
            '1 0 obj',
            '<< /Type /Catalog >>',
            'endobj',
            '%%EOF',
            '',
        ]);
        $badPdfBytes = "%PDF-9.9 draft\n%%EOF\n";
        $missingVersionPdfBytes = "%PDF-X.Y\n%%EOF\n";

        $result = $handoff->fakeRun($plan, [
            'files' => [
                'workspace/build/header-policy.pdf' => $pdfBytes,
            ],
        ]);
        $sequence = $handoff->fakeRunSequence($plan, [[
            'files' => [
                'workspace/build/header-policy.pdf' => $pdfBytes,
            ],
        ]]);
        $badResult = $handoff->fakeRun($plan, [
            'files' => [
                'workspace/build/header-policy.pdf' => $badPdfBytes,
            ],
        ]);
        $missingVersionResult = $handoff->fakeRun($plan, [
            'files' => [
                'workspace/build/header-policy.pdf' => $missingVersionPdfBytes,
            ],
        ]);

        $expectedPolicy = [
            'reviewStatus' => 'ok',
            'headerOffset' => 0,
            'headerLineBytes' => strlen('%PDF-1.7'),
            'headerLineTruncated' => false,
            'headerVersion' => '1.7',
            'knownVersion' => true,
            'lineEnding' => 'CRLF',
            'binaryCommentPresent' => true,
            'binaryCommentOffset' => (int) strpos($pdfBytes, $binaryComment),
            'binaryCommentBytes' => strlen($binaryComment),
            'firstBodyOffset' => (int) strpos($pdfBytes, '1 0 obj'),
            'issues' => [],
        ];

        $t->same(true, $result['ok']);
        $t->same('1.7', $result['pdfHeaderVersion']);
        $t->same($expectedPolicy, $result['pdfHeaderPolicy']);
        $t->same($expectedPolicy, $result['artifactProvenanceReview']['pdfHeaderPolicy']);
        $t->same('ok', $result['artifactProvenanceReview']['reviewStatus']);
        $t->contains('pdf-byte-header-policy:ok', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-header-policy-version:1.7', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-header-line-ending:CRLF', implode(',', $result['diagnostics']));
        $t->contains('pdf-byte-header-binary-comment', implode(',', $result['diagnostics']));
        $t->same(true, $sequence['ok']);
        $t->same($expectedPolicy, $sequence['finalPdfHeaderPolicy']);

        $badPolicy = $badResult['pdfHeaderPolicy'];
        $t->same(true, $badResult['ok']);
        $t->same('9.9', $badResult['pdfHeaderVersion']);
        $t->same('review', $badPolicy['reviewStatus']);
        $t->same(false, $badPolicy['knownVersion']);
        $t->same(['pdf-header-line-extra-bytes', 'pdf-header-version-unusual'], $badPolicy['issues']);
        $t->same($badPolicy, $badResult['artifactProvenanceReview']['pdfHeaderPolicy']);
        $t->same('review', $badResult['artifactProvenanceReview']['reviewStatus']);
        $t->contains('pdf-header-policy:review', implode(',', $badResult['artifactProvenanceReview']['issues']));
        $t->contains('pdf-byte-header-policy:review', implode(',', $badResult['diagnostics']));
        $t->contains('pdf-byte-header-policy-version:9.9', implode(',', $badResult['diagnostics']));
        $t->contains('pdf-byte-header-policy-issue:pdf-header-line-extra-bytes', implode(',', $badResult['diagnostics']));
        $t->contains('pdf-byte-header-policy-issue:pdf-header-version-unusual', implode(',', $badResult['diagnostics']));

        $t->same(true, $missingVersionResult['ok']);
        $t->same(null, $missingVersionResult['pdfHeaderVersion']);
        $t->same('review', $missingVersionResult['pdfHeaderPolicy']['reviewStatus']);
        $t->same(['pdf-header-version-missing'], $missingVersionResult['pdfHeaderPolicy']['issues']);
        $t->contains('pdf-byte-header-policy-issue:pdf-header-version-missing', implode(',', $missingVersionResult['diagnostics']));
        $t->same('review', $missingVersionResult['artifactProvenanceReview']['reviewStatus']);
        $t->contains('pdf-header-policy:review', implode(',', $missingVersionResult['artifactProvenanceReview']['issues']));
    },
];
