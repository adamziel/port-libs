<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineStyleOperandBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline style operand boundary intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline style operand boundary appendix body) Tj ET';
    $reviewContent = 'BT /F1 12 Tf 72 720 Td (Outline style operand boundary review body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 10 0 R] /Count 3 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 8 0 R /Count 3 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Valid Style Operand Boundary Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R /C [0 .25 .5] /F 3 >>\nendobj\n"
        . "7 0 obj\n<< /Title (Malformed Style Flag Operand) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget /Next 8 0 R /F 2 12 0 R /C [0 .5 1] >>\nendobj\n"
        . "8 0 obj\n<< /Title (Malformed Color Top Operand) /Parent 5 0 R /Prev 7 0 R /Dest /ReviewTarget /C [1 .5 0] 13 0 R /F 1 >>\nendobj\n"
        . "10 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 32 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI (https://example.com/tailed-style-operand) >>\nendobj\n"
        . "13 0 obj\n<< /S /URI /URI (https://example.com/tailed-color-operand) >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (ReviewTarget) [10 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Length " . strlen($reviewContent) . " >>\nstream\n{$reviewContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Title (Outline Style Operand Info) /Author (Current Outline Style Team) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 40 0 R >>\n%%EOF";
};

return [
    'rejects outline F and C values with trailing top-level operands in document metadata' => static function (
        TestRunner $t
    ) use ($outlineStyleOperandBoundaryPdf): void {
        $pdf = $outlineStyleOperandBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(3, $outline['item_count'] ?? null);
        $t->same(3, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same([
            'Valid Style Operand Boundary Chapter',
            'Malformed Style Flag Operand',
            'Malformed Color Top Operand',
        ], $outline['titles'] ?? []);
        $t->same([6, 7, 8], array_column($items, 'outline_object'));
        $t->same([0, 1, 2], array_column($items, 'page'));

        $t->same(3, $items[0]['style_flags'] ?? null);
        $t->same(true, $items[0]['is_bold'] ?? null);
        $t->same(true, $items[0]['is_italic'] ?? null);
        $t->same('#004080', $items[0]['text_color_hex'] ?? null);

        $t->true(!array_key_exists('style_flags', $items[1] ?? []));
        $t->true(!array_key_exists('is_bold', $items[1] ?? []));
        $t->true(!array_key_exists('is_italic', $items[1] ?? []));
        $t->same('#0080ff', $items[1]['text_color_hex'] ?? null);
        $t->true(!array_key_exists('text_color_hex', $items[2] ?? []));
        $t->true(!array_key_exists('text_color_rgb', $items[2] ?? []));
        $t->same(1, $items[2]['style_flags'] ?? null);

        $t->true(is_string($encoded) && !str_contains($encoded, 'tailed-style-operand'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'tailed-color-operand'));
    },
    'applies outline F and C operand boundaries to navigation review and visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineStyleOperandBoundaryPdf): void {
        $pdf = $outlineStyleOperandBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $expectedTitles = [
            'Valid Style Operand Boundary Chapter',
            'Malformed Style Flag Operand',
            'Malformed Color Top Operand',
        ];

        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same([0, 1, 2], array_column($toc, 'page'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same(3, $navigation['outline'][0]['style_flags'] ?? null);
        $t->same(true, $navigation['outline'][0]['is_bold'] ?? null);
        $t->same('#004080', $navigation['outline'][0]['text_color_hex'] ?? null);
        $t->true(!array_key_exists('style_flags', $navigation['outline'][1] ?? []));
        $t->true(!array_key_exists('is_bold', $navigation['outline'][1] ?? []));
        $t->same('#0080ff', $navigation['outline'][1]['text_color_hex'] ?? null);
        $t->true(!array_key_exists('text_color_hex', $navigation['outline'][2] ?? []));
        $t->true(!array_key_exists('text_color_rgb', $navigation['outline'][2] ?? []));
        $t->same(1, $navigation['outline'][2]['style_flags'] ?? null);
        $t->same("Outline style operand boundary intro body\nOutline style operand boundary appendix body\nOutline style operand boundary review body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'tailed-style-operand'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'tailed-color-operand'));
        $t->true(!str_contains($plainText, 'Valid Style Operand Boundary Chapter'));
        $t->true(!str_contains($plainText, 'Malformed Style Flag Operand'));
        $t->true(!str_contains($plainText, 'Malformed Color Top Operand'));
        $t->true(!str_contains($plainText, 'tailed-style-operand'));
        $t->true(!str_contains($plainText, 'tailed-color-operand'));
    },
];
