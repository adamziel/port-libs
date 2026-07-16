<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataDuplicateStructureKeyBoundaryPdf = static function (): string {
    $chapterText = 'BT /F1 12 Tf 72 720 Td (Duplicate structure key chapter body) Tj ET';
    $appendixText = 'BT /F1 12 Tf 72 720 Td (Duplicate structure key appendix body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 8 0 R /Count 3 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Duplicate Structure Parent) /Parent 5 0 R /Dest [3 0 R /Fit] /Count 0 /First 10 0 R /Last 10 0 R /Count -1 /First 7 0 R /Last 7 0 R /Next 8 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Selected Structure Child) /Parent 6 0 R /Dest [4 0 R /FitH 640] >>\nendobj\n"
        . "8 0 obj\n<< /Title (Duplicate Style Sibling) /Parent 5 0 R /Prev 99 0 R /Prev 6 0 R /Dest [4 0 R /Fit] /F 1 /C [1 0 0] /F 2 /C [0 .25 .5] >>\nendobj\n"
        . "10 0 obj\n<< /Title (Stale Duplicate Structure Child) /Parent 6 0 R /Dest [4 0 R /FitR 1 2 3 4] /A 12 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI (https://example.com/stale-duplicate-structure-child) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($chapterText) . " >>\nstream\n{$chapterText}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($appendixText) . " >>\nstream\n{$appendixText}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'records duplicate outline structure and style keys as review metadata' => static function (
        TestRunner $t
    ) use ($outlineMetadataDuplicateStructureKeyBoundaryPdf): void {
        $pdf = $outlineMetadataDuplicateStructureKeyBoundaryPdf();
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
        $t->same(2, $outline['duplicate_item_key_count'] ?? null);
        $t->same(['Count', 'First', 'Last', 'Prev', 'F', 'C'], $outline['duplicate_item_keys'] ?? null);
        $t->same(true, $outline['duplicate_item_key_review_only'] ?? null);
        $t->same(false, $outline['duplicate_item_key_payload_included'] ?? null);

        $parent = $items[0] ?? [];
        $parentReview = $parent['duplicate_key_review'] ?? [];
        $t->same('Duplicate Structure Parent', $parent['title'] ?? null);
        $t->same(6, $parent['outline_object'] ?? null);
        $t->same(7, $parent['first_child_object'] ?? null);
        $t->same(7, $parent['last_child_object'] ?? null);
        $t->same(-1, $parent['outline_count'] ?? null);
        $t->same(1, $parent['descendant_count'] ?? null);
        $t->same(true, $parent['is_collapsed'] ?? null);
        $t->same('collapsed', $parent['structure_state'] ?? null);
        $t->same(['Count', 'First', 'Last'], $parentReview['keys'] ?? null);
        $t->same(['Count' => 2, 'First' => 2, 'Last' => 2], $parentReview['declared_entry_counts'] ?? null);
        $t->same(['Count' => 1, 'First' => 1, 'Last' => 1], $parentReview['selected_entry_indexes'] ?? null);

        $child = $items[1] ?? [];
        $t->same('Selected Structure Child', $child['title'] ?? null);
        $t->same(7, $child['outline_object'] ?? null);
        $t->same(6, $child['parent_object'] ?? null);
        $t->same('FitH', $child['view_mode'] ?? null);
        $t->same(['top' => 640.0], $child['view_parameters'] ?? null);

        $sibling = $items[2] ?? [];
        $siblingReview = $sibling['duplicate_key_review'] ?? [];
        $t->same('Duplicate Style Sibling', $sibling['title'] ?? null);
        $t->same(8, $sibling['outline_object'] ?? null);
        $t->same(6, $sibling['previous_object'] ?? null);
        $t->same(2, $sibling['style_flags'] ?? null);
        $t->same(true, $sibling['is_bold'] ?? null);
        $t->same(false, $sibling['is_italic'] ?? null);
        $t->same([0.0, 0.25, 0.5], $sibling['text_color_rgb'] ?? null);
        $t->same('#004080', $sibling['text_color_hex'] ?? null);
        $t->same(['Prev', 'F', 'C'], $siblingReview['keys'] ?? null);
        $t->same(['Prev' => 2, 'F' => 2, 'C' => 2], $siblingReview['declared_entry_counts'] ?? null);
        $t->same(['Prev' => 1, 'F' => 1, 'C' => 1], $siblingReview['selected_entry_indexes'] ?? null);

        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Duplicate Structure Child'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-duplicate-structure-child'));
    },
    'keeps duplicate structure-key decoys out of TOC navigation and visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineMetadataDuplicateStructureKeyBoundaryPdf): void {
        $pdf = $outlineMetadataDuplicateStructureKeyBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $expectedTitles = ['Duplicate Structure Parent', 'Selected Structure Child', 'Duplicate Style Sibling'];
        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same([0, 1, 1], array_column($toc, 'page'));
        $t->same(['Fit', 'FitH', 'Fit'], array_column($toc, 'view_mode'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same("Duplicate structure key chapter body\nDuplicate structure key appendix body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Duplicate Structure Child'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'stale-duplicate-structure-child'));
        $t->true(!str_contains($plainText, 'Duplicate Structure Parent'));
        $t->true(!str_contains($plainText, 'Selected Structure Child'));
        $t->true(!str_contains($plainText, 'Duplicate Style Sibling'));
        $t->true(!str_contains($plainText, 'Stale Duplicate Structure Child'));
        $t->true(!str_contains($plainText, 'stale-duplicate-structure-child'));
    },
];
