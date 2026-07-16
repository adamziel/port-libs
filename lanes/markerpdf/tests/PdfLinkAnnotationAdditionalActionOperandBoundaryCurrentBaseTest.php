<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$additionalActionOperandBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Clean hover Tailed hover Indirect tailed hover) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 156 718] /Contents (Clean hover review) /A << /S /URI /URI (https://example.com/clean-hover) >> /AA << /E 20 0 R /U 23 0 R >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [166 700 260 718] /Contents (Tailed hover review) /A << /S /URI /URI (https://example.com/tailed-hover) >> /AA << /E 21 0 R 22 0 R /U 23 0 R >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 408 718] /Contents (Indirect tailed hover review) /A << /S /URI /URI (https://example.com/indirect-tailed-hover) >> /AA 24 0 R >>\nendobj\n"
        . "20 0 obj\n<< /S /URI /URI (https://review.example.com/clean-enter) >>\nendobj\n"
        . "21 0 obj\n<< /S /URI /URI (https://review.example.com/tailed-enter-leak) /Next << /S /JavaScript /JS (tailedEnterReview\\(\\)) >> >>\nendobj\n"
        . "22 0 obj\n<< /S /URI /URI (https://review.example.com/private-tail) >>\nendobj\n"
        . "23 0 obj\n<< /S /URI /URI (mailto:review@example.com) >>\nendobj\n"
        . "24 0 obj\n<< /E 25 0 R 26 0 R /U 23 0 R >>\nendobj\n"
        . "25 0 obj\n<< /S /URI /URI (https://review.example.com/indirect-tailed-enter-leak) /Next << /S /JavaScript /JS (indirectTailedEnterReview\\(\\)) >> >>\nendobj\n"
        . "26 0 obj\n<< /S /Launch /F (review-helper.exe) >>\nendobj\n"
        . "%%EOF";
};

$additionalActionOperandBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 408.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 408.0, 718.0],
                'spans' => [
                    ['text' => 'Clean hover', 'bbox' => [72.0, 700.0, 156.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Tailed hover', 'bbox' => [166.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Indirect tailed hover', 'bbox' => [270.0, 700.0, 408.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'rejects malformed Link annotation AA event operands without dropping primary URI links' => static function (
        TestRunner $t
    ) use ($additionalActionOperandBoundaryPdf, $additionalActionOperandBoundaryPages): void {
        $pdf = $additionalActionOperandBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $annotationRows = $annotations[0]['annotations'];
        $t->same([7, 8, 9], array_column($annotationRows, 'annotation_object'));
        $t->same([
            'https://example.com/clean-hover',
            'https://example.com/tailed-hover',
            'https://example.com/indirect-tailed-hover',
        ], array_map(static fn (array $row): ?string => $row['actions'][0]['uri'] ?? null, $annotationRows));
        $t->same(['review-uri', 'review-uri'], array_column($annotationRows[0]['additional_actions'], 'safety'));
        $t->same(['cursor_enter', 'mouse_up'], array_column($annotationRows[0]['additional_actions'], 'event_label'));
        $t->same('https://review.example.com/clean-enter', $annotationRows[0]['additional_actions'][0]['uri']);
        $t->same(['malformed-action-dictionary', 'review-uri'], array_column($annotationRows[1]['additional_actions'], 'safety'));
        $t->same(['E'], $annotationRows[1]['additional_actions'][0]['malformed_action_operand_keys'] ?? null);
        $t->same(null, $annotationRows[1]['additional_actions'][0]['uri'] ?? null);
        $t->same('mailto:review@example.com', $annotationRows[1]['additional_actions'][1]['uri']);
        $t->same(['malformed-action-dictionary', 'review-uri'], array_column($annotationRows[2]['additional_actions'], 'safety'));
        $t->same(['E'], $annotationRows[2]['additional_actions'][0]['malformed_action_operand_keys'] ?? null);
        $t->same(null, $annotationRows[2]['additional_actions'][0]['uri'] ?? null);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $linkRows = $links[0]['links'];
        $t->same([7, 8, 9], array_column($linkRows, 'annotation_object'));
        $t->same([
            'https://example.com/clean-hover',
            'https://example.com/tailed-hover',
            'https://example.com/indirect-tailed-hover',
        ], array_column($linkRows, 'uri'));
        $t->same(['malformed-action-dictionary', 'review-uri'], array_column($linkRows[1]['additional_actions'], 'safety'));
        $t->same(['E'], $linkRows[1]['additional_actions'][0]['malformed_action_operand_keys'] ?? null);
        $t->same(['malformed-action-dictionary', 'review-uri'], array_column($linkRows[2]['additional_actions'], 'safety'));
        $t->same(['E'], $linkRows[2]['additional_actions'][0]['malformed_action_operand_keys'] ?? null);

        $pages = $extractor->applyLinksToPages($additionalActionOperandBoundaryPages(), $pdf);
        $spans = $pages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/clean-hover', $spans[0]['link_uri']);
        $t->same('https://review.example.com/clean-enter', $spans[0]['link_additional_actions_review'][0]['uri']);
        $t->same('https://example.com/tailed-hover', $spans[1]['link_uri']);
        $t->same(['malformed-action-dictionary', 'review-uri'], array_column($spans[1]['link_additional_actions_review'], 'safety'));
        $t->same(['E'], $spans[1]['link_additional_actions_review'][0]['malformed_action_operand_keys'] ?? null);
        $t->same('https://example.com/indirect-tailed-hover', $spans[2]['link_uri']);
        $t->same(['malformed-action-dictionary', 'review-uri'], array_column($spans[2]['link_additional_actions_review'], 'safety'));
        $t->same(['E'], $spans[2]['link_additional_actions_review'][0]['malformed_action_operand_keys'] ?? null);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($pages));
        $t->same(
            '[Clean hover](https://example.com/clean-hover) [Tailed hover](https://example.com/tailed-hover) [Indirect tailed hover](https://example.com/indirect-tailed-hover)',
            $blocks[0]['text']
        );

        $encodedReview = $encoded([$annotations, $links, $pages]);
        foreach ([
            'tailed-enter-leak',
            'private-tail',
            'indirect-tailed-enter-leak',
            'review-helper.exe',
            'tailedEnterReview',
            'indirectTailedEnterReview',
        ] as $tailedPayload) {
            $t->same(false, str_contains($encodedReview, $tailedPayload));
        }

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->same('Clean hover Tailed hover Indirect tailed hover', $plainText);
        foreach ([
            'clean-hover',
            'tailed-hover',
            'indirect-tailed-hover',
            'review.example.com',
            'review-helper.exe',
            'Clean hover review',
            'Tailed hover review',
            'Indirect tailed hover review',
            'tailedEnterReview',
            'indirectTailedEnterReview',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
