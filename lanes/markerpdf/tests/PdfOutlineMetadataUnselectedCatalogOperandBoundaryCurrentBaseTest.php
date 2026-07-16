<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineUnselectedCatalogOperandBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Selected duplicate catalog operand intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Selected duplicate catalog operand appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 8 0 R 99 0 R /PageMode /UseOutlines /Outlines 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Selected Catalog Operand Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Selected Catalog Operand Appendix) /Parent 5 0 R /Prev 6 0 R /A 12 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Outlines /First 9 0 R /Last 9 0 R /Count 1 >>\nendobj\n"
        . "9 0 obj\n<< /Title (Unselected Malformed Catalog Operand Outline) /Parent 8 0 R /Dest [4 0 R /Fit] /A 99 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /GoTo /D [4 0 R /FitR 10 20 300 700] /Next 13 0 R >>\nendobj\n"
        . "13 0 obj\n<< /S /URI /URI (https://example.com/selected-catalog-operand-review) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "99 0 obj\n<< /S /URI /URI (https://example.com/unselected-catalog-outline-operand) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'uses selected duplicate catalog Outlines after an unselected malformed operand' => static function (
        TestRunner $t
    ) use ($outlineUnselectedCatalogOperandBoundaryPdf): void {
        $pdf = $outlineUnselectedCatalogOperandBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $review = $outline['outline_root_duplicate_key_review'] ?? [];
        $entries = $review['entries'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->true(!array_key_exists('document_outline_boundary_review', $metadata));
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(7, $outline['last_item_object'] ?? null);
        $t->same(2, $outline['declared_visible_count'] ?? null);
        $t->same(2, $outline['item_count'] ?? null);
        $t->same(2, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(['Selected Catalog Operand Chapter', 'Selected Catalog Operand Appendix'], $outline['titles'] ?? []);

        $t->same(2, $outline['duplicate_outline_root_entry_count'] ?? null);
        $t->same([8, 5], $outline['duplicate_outline_root_objects'] ?? null);
        $t->same(5, $outline['duplicate_outline_root_selected_object'] ?? null);
        $t->same(1, $outline['duplicate_outline_root_selected_entry_index'] ?? null);
        $t->same('catalog_outline_root_duplicate_key', $review['source'] ?? null);
        $t->same('last_top_level_entry', $review['selected_entry_policy'] ?? null);
        $t->same(2, $review['declared_entry_count'] ?? null);
        $t->same(1, $review['selected_entry_index'] ?? null);
        $t->same(5, $review['selected_object_number'] ?? null);
        $t->same([8, 5], $review['candidate_object_numbers'] ?? null);
        $t->same(8, $entries[0]['object_number'] ?? null);
        $t->same(5, $entries[1]['object_number'] ?? null);

        $t->true(is_string($encoded) && !str_contains($encoded, 'Unselected Malformed Catalog Operand Outline'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'unselected-catalog-outline-operand'));
        $t->true(is_string($encoded) && str_contains($encoded, 'Selected Catalog Operand Appendix'));
    },
    'keeps unselected malformed catalog Outlines operands out of navigation and visible text' => static function (
        TestRunner $t
    ) use ($outlineUnselectedCatalogOperandBoundaryPdf): void {
        $pdf = $outlineUnselectedCatalogOperandBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $textExtractor = new PdfTextExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = $textExtractor->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $lightweightEncoded = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

        $expectedTitles = ['Selected Catalog Operand Chapter', 'Selected Catalog Operand Appendix'];
        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same($expectedTitles, array_column($lightweight['pdf_toc'] ?? [], 'title'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same([0, 1], array_column($toc, 'page'));
        $t->same(['FitH', 'FitR'], array_column($toc, 'view_mode'));
        $t->same(['Selected Catalog Operand Appendix', 'Selected Catalog Operand Appendix'], array_column($navigation['outline_action_review_actions'] ?? [], 'outline_title'));
        $t->same(['GoTo', 'URI'], array_column($navigation['outline_action_review_actions'] ?? [], 'action_type'));
        $t->same("Selected duplicate catalog operand intro body\nSelected duplicate catalog operand appendix body", $plainText);
        foreach ([$navigationEncoded, $lightweightEncoded] as $encoded) {
            $t->true(is_string($encoded) && !str_contains($encoded, 'Unselected Malformed Catalog Operand Outline'));
            $t->true(is_string($encoded) && !str_contains($encoded, 'unselected-catalog-outline-operand'));
        }
        $t->true(is_string($navigationEncoded) && str_contains($navigationEncoded, 'selected-catalog-operand-review'));
        $t->true(!str_contains($plainText, 'Selected Catalog Operand Chapter'));
        $t->true(!str_contains($plainText, 'Selected Catalog Operand Appendix'));
        $t->true(!str_contains($plainText, 'Unselected Malformed Catalog Operand Outline'));
        $t->true(!str_contains($plainText, 'unselected-catalog-outline-operand'));
    },
];
