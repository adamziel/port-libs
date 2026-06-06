<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineCatalogDuplicateRootBoundaryPdf = static function (): string {
    $coverContent = 'BT /F1 12 Tf 72 720 Td (Duplicate catalog outline root cover body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Duplicate catalog outline root appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Out#6Cines 8 0 R /PageMode /UseOutlines /Outlines 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Selected Duplicate Root Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Selected Duplicate Root Appendix) /Parent 5 0 R /Prev 6 0 R /A 11 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Outlines /First 9 0 R /Last 9 0 R /Count 1 >>\nendobj\n"
        . "9 0 obj\n<< /Title (Stale Escaped Duplicate Root Outline) /Parent 8 0 R /Dest [4 0 R /Fit] /A 12 0 R >>\nendobj\n"
        . "11 0 obj\n<< /S /GoTo /D [4 0 R /FitR 10 20 300 700] >>\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI (https://example.com/stale-duplicate-root-outline) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($coverContent) . " >>\nstream\n{$coverContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'records duplicate catalog Outlines root selection as review-only document metadata' => static function (
        TestRunner $t
    ) use ($outlineCatalogDuplicateRootBoundaryPdf): void {
        $pdf = $outlineCatalogDuplicateRootBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $review = $outline['outline_root_duplicate_key_review'] ?? [];
        $entries = $review['entries'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(7, $outline['last_item_object'] ?? null);
        $t->same(2, $outline['item_count'] ?? null);
        $t->same(2, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(['Selected Duplicate Root Chapter', 'Selected Duplicate Root Appendix'], $outline['titles'] ?? []);

        $t->same(2, $outline['duplicate_outline_root_entry_count'] ?? null);
        $t->same([8, 5], $outline['duplicate_outline_root_objects'] ?? null);
        $t->same(5, $outline['duplicate_outline_root_selected_object'] ?? null);
        $t->same(1, $outline['duplicate_outline_root_selected_entry_index'] ?? null);
        $t->same(true, $outline['duplicate_outline_root_review_only'] ?? null);
        $t->same(false, $outline['duplicate_outline_root_payload_included'] ?? null);

        $t->same('catalog_outline_root_duplicate_key', $review['source'] ?? null);
        $t->same(true, $review['review_only'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(false, $review['visible_text_source'] ?? null);
        $t->same('last_top_level_entry', $review['selected_entry_policy'] ?? null);
        $t->same(2, $review['declared_entry_count'] ?? null);
        $t->same(true, $review['duplicate_entries'] ?? null);
        $t->same(1, $review['selected_entry_index'] ?? null);
        $t->same(5, $review['selected_object_number'] ?? null);
        $t->same([8, 5], $review['candidate_object_numbers'] ?? null);

        $t->same('indirect_reference', $entries[0]['kind'] ?? null);
        $t->same(8, $entries[0]['object_number'] ?? null);
        $t->same('Outlines', $entries[0]['type'] ?? null);
        $t->same(true, $entries[0]['is_outline_root'] ?? null);
        $t->same(9, $entries[0]['first_item_object'] ?? null);
        $t->same(9, $entries[0]['last_item_object'] ?? null);
        $t->same(1, $entries[0]['outline_count'] ?? null);

        $t->same('indirect_reference', $entries[1]['kind'] ?? null);
        $t->same(5, $entries[1]['object_number'] ?? null);
        $t->same('Outlines', $entries[1]['type'] ?? null);
        $t->same(true, $entries[1]['is_outline_root'] ?? null);
        $t->same(6, $entries[1]['first_item_object'] ?? null);
        $t->same(7, $entries[1]['last_item_object'] ?? null);
        $t->same(2, $entries[1]['outline_count'] ?? null);

        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Escaped Duplicate Root Outline'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-duplicate-root-outline'));
    },
    'keeps unselected duplicate catalog Outlines roots out of navigation and visible text' => static function (
        TestRunner $t
    ) use ($outlineCatalogDuplicateRootBoundaryPdf): void {
        $pdf = $outlineCatalogDuplicateRootBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $lightweightToc = $outlineExtractor->getPdfToc($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $expectedTitles = ['Selected Duplicate Root Chapter', 'Selected Duplicate Root Appendix'];
        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same($expectedTitles, array_column($lightweightToc, 'title'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same([0, 1], array_column($toc, 'page'));
        $t->same(['FitH', 'FitR'], array_column($toc, 'view_mode'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same("Duplicate catalog outline root cover body\nDuplicate catalog outline root appendix body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Escaped Duplicate Root Outline'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'stale-duplicate-root-outline'));
        $t->true(!str_contains($plainText, 'Selected Duplicate Root Chapter'));
        $t->true(!str_contains($plainText, 'Selected Duplicate Root Appendix'));
        $t->true(!str_contains($plainText, 'Stale Escaped Duplicate Root Outline'));
        $t->true(!str_contains($plainText, 'stale-duplicate-root-outline'));
    },
];
