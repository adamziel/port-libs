<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineColorBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline color boundary intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline color boundary appendix body) Tj ET';
    $reviewContent = 'BT /F1 12 Tf 72 720 Td (Outline color boundary review body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 10 0 R] /Count 3 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 8 0 R /Count 3 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Valid Color Boundary Chapter) /Parent 5 0 R /Dest /ChapterStart /Next 7 0 R /C [0 .25 .5] /F 2 >>\nendobj\n"
        . "7 0 obj\n<< /Title (Malformed Extra Color Operand) /Parent 5 0 R /Prev 6 0 R /Dest /AppendixTarget /Next 8 0 R /C [0 .25 .5 (hidden extra color operand)] /F 1 >>\nendobj\n"
        . "8 0 obj\n<< /Title (Indirect Extra Color Operand) /Parent 5 0 R /Prev 7 0 R /Dest /ReviewTarget /C 21 0 R /F 3 >>\nendobj\n"
        . "10 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 32 0 R >>\nendobj\n"
        . "20 0 obj\n<< /Names [(AppendixTarget) [4 0 R /Fit] (ChapterStart) [3 0 R /FitH 720] (ReviewTarget) [10 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "21 0 obj\n[0 .5 1 99]\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Length " . strlen($reviewContent) . " >>\nstream\n{$reviewContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'rejects outline text color arrays with extra operands in document metadata' => static function (
        TestRunner $t
    ) use ($outlineColorBoundaryPdf): void {
        $pdf = $outlineColorBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(3, $outline['item_count'] ?? null);
        $t->same(3, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same([
            'Valid Color Boundary Chapter',
            'Malformed Extra Color Operand',
            'Indirect Extra Color Operand',
        ], $outline['titles'] ?? []);
        $t->same([6, 7, 8], array_column($items, 'outline_object'));
        $t->same([0, 1, 2], array_column($items, 'page'));
        $t->same('#004080', $items[0]['text_color_hex'] ?? null);
        $t->same([0.0, 0.25, 0.5], $items[0]['text_color_rgb'] ?? null);
        $t->true(!array_key_exists('text_color_hex', $items[1] ?? []));
        $t->true(!array_key_exists('text_color_rgb', $items[1] ?? []));
        $t->true(!array_key_exists('text_color_hex', $items[2] ?? []));
        $t->true(!array_key_exists('text_color_rgb', $items[2] ?? []));
        $t->true(is_string($encoded) && !str_contains($encoded, 'hidden extra color operand'));
        $t->true(is_string($encoded) && !str_contains($encoded, '[0,0.5,1]'));
    },
    'applies outline color operand boundaries to navigation review and visible text' => static function (
        TestRunner $t
    ) use ($outlineColorBoundaryPdf): void {
        $pdf = $outlineColorBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $expectedTitles = [
            'Valid Color Boundary Chapter',
            'Malformed Extra Color Operand',
            'Indirect Extra Color Operand',
        ];

        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same([0, 1, 2], array_column($toc, 'page'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same('#004080', $navigation['outline'][0]['text_color_hex'] ?? null);
        $t->true(!array_key_exists('text_color_hex', $navigation['outline'][1] ?? []));
        $t->true(!array_key_exists('text_color_rgb', $navigation['outline'][1] ?? []));
        $t->true(!array_key_exists('text_color_hex', $navigation['outline'][2] ?? []));
        $t->true(!array_key_exists('text_color_rgb', $navigation['outline'][2] ?? []));
        $t->same("Outline color boundary intro body\nOutline color boundary appendix body\nOutline color boundary review body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'hidden extra color operand'));
        $t->true(!str_contains($plainText, 'Valid Color Boundary Chapter'));
        $t->true(!str_contains($plainText, 'Malformed Extra Color Operand'));
        $t->true(!str_contains($plainText, 'Indirect Extra Color Operand'));
        $t->true(!str_contains($plainText, 'hidden extra color operand'));
    },
];
