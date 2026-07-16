<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineRootTraversalDuplicateKeyBoundaryPdf = static function (): string {
    $introContent = 'BT /F1 12 Tf 72 720 Td (Outline root duplicate traversal intro body) Tj ET';
    $appendixContent = 'BT /F1 12 Tf 72 720 Td (Outline root duplicate traversal appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 8 0 R /Last 8 0 R /Count 1 /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Selected Root Duplicate Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Selected Root Duplicate Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /FitR 20 40 420 760] >>\nendobj\n"
        . "8 0 obj\n<< /Title (Stale Root Duplicate First Item) /Parent 5 0 R /Dest [4 0 R /Fit] /A 12 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI (https://example.com/stale-root-duplicate-first) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixContent) . " >>\nstream\n{$appendixContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'records duplicate selected outline-root traversal keys as review metadata' => static function (
        TestRunner $t
    ) use ($outlineRootTraversalDuplicateKeyBoundaryPdf): void {
        $pdf = $outlineRootTraversalDuplicateKeyBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $review = $outline['outline_root_traversal_duplicate_key_review'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(7, $outline['last_item_object'] ?? null);
        $t->same(2, $outline['outline_count'] ?? null);
        $t->same(2, $outline['item_count'] ?? null);
        $t->same(2, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(['Selected Root Duplicate Chapter', 'Selected Root Duplicate Appendix'], $outline['titles'] ?? []);

        $t->same(3, $outline['duplicate_outline_root_traversal_key_count'] ?? null);
        $t->same(['First', 'Last', 'Count'], $outline['duplicate_outline_root_traversal_keys'] ?? null);
        $t->same(true, $outline['duplicate_outline_root_traversal_key_review_only'] ?? null);
        $t->same(false, $outline['duplicate_outline_root_traversal_key_payload_included'] ?? null);
        $t->same('outline_root_traversal_duplicate_keys', $review['source'] ?? null);
        $t->same(true, $review['review_only'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(false, $review['visible_text_source'] ?? null);
        $t->same('last_top_level_entry', $review['selected_entry_policy'] ?? null);
        $t->same(['First', 'Last', 'Count'], $review['keys'] ?? null);
        $t->same(['First' => 2, 'Last' => 2, 'Count' => 2], $review['declared_entry_counts'] ?? null);
        $t->same(['First' => 1, 'Last' => 1, 'Count' => 1], $review['selected_entry_indexes'] ?? null);

        $t->same([6, 7], array_column($items, 'outline_object'));
        $t->same([5, 5], array_column($items, 'parent_object'));
        $t->same([0, 1], array_column($items, 'page'));
        $t->same(['FitH', 'FitR'], array_column($items, 'view_mode'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Root Duplicate First Item'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-root-duplicate-first'));
    },
    'keeps unselected duplicate outline-root traversal operands out of navigation and visible text' => static function (
        TestRunner $t
    ) use ($outlineRootTraversalDuplicateKeyBoundaryPdf): void {
        $pdf = $outlineRootTraversalDuplicateKeyBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $lightweightToc = $outlineExtractor->getPdfToc($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $expectedTitles = ['Selected Root Duplicate Chapter', 'Selected Root Duplicate Appendix'];
        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same($expectedTitles, array_column($lightweightToc, 'title'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same([0, 1], array_column($toc, 'page'));
        $t->same(['FitH', 'FitR'], array_column($toc, 'view_mode'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same("Outline root duplicate traversal intro body\nOutline root duplicate traversal appendix body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Root Duplicate First Item'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'stale-root-duplicate-first'));
        $t->true(!str_contains($plainText, 'Selected Root Duplicate Chapter'));
        $t->true(!str_contains($plainText, 'Selected Root Duplicate Appendix'));
        $t->true(!str_contains($plainText, 'Stale Root Duplicate First Item'));
        $t->true(!str_contains($plainText, 'stale-root-duplicate-first'));
    },
];
