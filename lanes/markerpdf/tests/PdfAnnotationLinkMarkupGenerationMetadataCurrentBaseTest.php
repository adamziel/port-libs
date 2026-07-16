<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$markupGenerationMetadataPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Current generated link Current generated markup Stale generated markup) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 1 R 9 1 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 1 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 198 718] /Contents (Current generation link review) /A << /S /URI /URI (https://example.com/current-generated-link) >> >>\nendobj\n"
        . "9 1 obj\n<< /Type /Annot /Subtype /Highlight /Rect [210 700 362 718] /QuadPoints [210 718 362 718 210 700 362 700] /Contents (Current generation markup review) /T (Generation QA) /C [0.2 0.7 0.4] >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [372 700 530 718] /Contents (Stale generation link review) /A << /S /URI /URI (https://example.com/stale-generated-link) >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [372 700 530 718] /QuadPoints [372 718 530 718 372 700 530 700] /Contents (Stale generation markup review) /T (Stale QA) /C [1 0 0] >>\nendobj\n"
        . "%%EOF";
};

$markupGenerationMetadataPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 530.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 530.0, 718.0],
                'spans' => [
                    ['text' => 'Current generated link', 'bbox' => [72.0, 700.0, 198.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Current generated markup', 'bbox' => [210.0, 700.0, 362.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' Stale generated markup', 'bbox' => [372.0, 700.0, 530.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'preserves current markup annotation object generations through WordPress review spans' => static function (
        TestRunner $t
    ) use ($markupGenerationMetadataPdf, $markupGenerationMetadataPages): void {
        $pdf = $markupGenerationMetadataPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same([1, 1], array_column($annotations[0]['annotations'], 'annotation_generation'));
        $t->same(['Current generation link review', 'Current generation markup review'], array_column($annotations[0]['annotations'], 'contents'));
        $t->same(false, str_contains($encoded($annotations), 'Stale generation markup review'));

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, $links[0]['links'][0]['annotation_generation']);
        $t->same('https://example.com/current-generated-link', $links[0]['links'][0]['uri']);
        $t->same(false, str_contains($encoded($links), 'stale-generated-link'));

        $markupExtractor = new PdfMarkupAnnotationExtractor();
        $markups = $markupExtractor->extractPageMarkups($pdf);
        $t->same([9], array_column($markups[0]['markups'], 'annotation_object'));
        $t->same([1], array_column($markups[0]['markups'], 'annotation_generation'));
        $t->same('Current generation markup review', $markups[0]['markups'][0]['contents']);
        $t->same(false, str_contains($encoded($markups), 'Stale generation markup review'));

        $linkedPages = $linkExtractor->applyLinksToPages($markupGenerationMetadataPages(), $pdf);
        $reviewPages = $markupExtractor->applyMarkupsToPages($linkedPages, $pdf);
        $spans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same(1, $spans[0]['link_annotation_generation']);
        $t->same('https://example.com/current-generated-link', $spans[0]['link_uri']);
        $t->same(9, $spans[1]['review_annotations'][0]['annotation_object']);
        $t->same(1, $spans[1]['review_annotations'][0]['annotation_generation']);
        $t->same('Current generation markup review', $spans[1]['review_annotations'][0]['contents']);
        $t->same(false, isset($spans[2]['link_uri']));
        $t->same(false, isset($spans[2]['review_annotations']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
        $t->same('[Current generated link](https://example.com/current-generated-link) Current generated markup Stale generated markup', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Current generated link Current generated markup Stale generated markup', $plainText);
        foreach ([
            'current-generated-link',
            'stale-generated-link',
            'Current generation link review',
            'Current generation markup review',
            'Stale generation link review',
            'Stale generation markup review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
