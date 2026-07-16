<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$previousUriOperandBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Clean previous Tailed previous No previous) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 174 718] /Contents (Clean previous URI review) /A << /S /URI /URI (https://example.com/current-clean) >> /PA 20 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [184 700 304 718] /Contents (Tailed previous URI review) /A << /S /URI /URI (https://example.com/current-tailed) >> /PA 21 0 R 22 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [314 700 404 718] /Contents (No previous URI review) /A << /S /URI /URI (https://example.com/current-only) >> >>\nendobj\n"
        . "20 0 obj\n<< /S /URI /URI (https://archive.example.com/clean-previous) >>\nendobj\n"
        . "21 0 obj\n<< /S /URI /URI (https://archive.example.com/tailed-previous-leak) /Next << /S /JavaScript /JS (tailedPreviousUriReview\\(\\)) >> >>\nendobj\n"
        . "22 0 obj\n<< /S /URI /URI (https://archive.example.com/private-tail) >>\nendobj\n"
        . "%%EOF";
};

$previousUriOperandBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 404.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 404.0, 718.0],
                'spans' => [
                    ['text' => 'Clean previous', 'bbox' => [72.0, 700.0, 174.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed previous', 'bbox' => [184.0, 700.0, 304.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' No previous', 'bbox' => [314.0, 700.0, 404.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects tailed Link annotation PA previous action operands without dropping the primary URI link' => static function (
        TestRunner $t
    ) use ($previousUriOperandBoundaryPdf, $previousUriOperandBoundaryPages): void {
        $pdf = $previousUriOperandBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $annotationRows = $annotations[0]['annotations'];
        $t->same([7, 8, 9], array_column($annotationRows, 'annotation_object'));
        $t->same([
            'https://example.com/current-clean',
            'https://example.com/current-tailed',
            'https://example.com/current-only',
        ], array_map(static fn (array $row): ?string => $row['actions'][0]['uri'] ?? null, $annotationRows));
        $t->same('https://archive.example.com/clean-previous', $annotationRows[0]['previous_uri_actions'][0]['uri'] ?? null);
        $t->true(!isset($annotationRows[1]['previous_uri_actions']), 'A tailed /PA operand must not donate stale previous-URI review metadata.');
        $t->same(['PA'], $annotationRows[1]['malformed_action_operand_keys'] ?? null);
        $t->true(!isset($annotationRows[2]['previous_uri_actions']));

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $linkRows = $links[0]['links'];
        $t->same([7, 8, 9], array_column($linkRows, 'annotation_object'));
        $t->same([
            'https://example.com/current-clean',
            'https://example.com/current-tailed',
            'https://example.com/current-only',
        ], array_column($linkRows, 'uri'));
        $t->same('https://archive.example.com/clean-previous', $linkRows[0]['previous_uri_actions'][0]['uri'] ?? null);
        $t->true(!isset($linkRows[1]['previous_uri_actions']));
        $t->same(['PA'], $linkRows[1]['malformed_action_operand_keys'] ?? null);

        $pages = $extractor->applyLinksToPages($previousUriOperandBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/current-clean', $spans[0]['link_uri']);
        $t->same('https://archive.example.com/clean-previous', $spans[0]['link_previous_uri_actions'][0]['uri']);
        $t->same('https://example.com/current-tailed', $spans[1]['link_uri']);
        $t->true(!isset($spans[1]['link_previous_uri_actions']));
        $t->same(['PA'], $spans[1]['link_malformed_action_operand_keys'] ?? null);
        $t->same('https://example.com/current-only', $spans[2]['link_uri']);
        $t->true(!isset($spans[2]['link_previous_uri_actions']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            '[Clean previous](https://example.com/current-clean) [Tailed previous](https://example.com/current-tailed) [No previous](https://example.com/current-only)',
            $blocks[0]['text']
        );

        $encodedReview = $encoded([$annotations, $links, $pages]);
        foreach (['tailed-previous-leak', 'private-tail', 'tailedPreviousUriReview'] as $tailedPayload) {
            $t->same(false, str_contains($encodedReview, $tailedPayload));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->same('Clean previous Tailed previous No previous', $plainText);
        foreach ([
            'current-clean',
            'current-tailed',
            'current-only',
            'archive.example.com',
            'Clean previous URI review',
            'Tailed previous URI review',
            'No previous URI review',
            'tailedPreviousUriReview',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
