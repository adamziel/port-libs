<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataDuplicateNavKeyBoundaryPdf = static function (): string {
    $currentPageText = 'BT /F1 12 Tf 72 720 Td (Current duplicate nav-key page body) Tj ET';
    $actionPageText = 'BT /F1 12 Tf 72 720 Td (Action duplicate nav-key page body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Stale Duplicate Bookmark) /Parent 5 0 R /Dest /StaleDuplicateDest /Note (/Title (Nested title decoy) /Dest /NestedDest) /Title (Current Duplicate Bookmark) /Dest /CurrentDuplicateDest /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Duplicate Action Bookmark) /Parent 5 0 R /Prev 6 0 R /A 12 0 R /A 13 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI (https://example.com/stale-duplicate-outline-action) >>\nendobj\n"
        . "13 0 obj\n<< /S /GoTo /D /ActionDuplicateDest /Next 14 0 R >>\nendobj\n"
        . "14 0 obj\n<< /S /URI /URI (https://example.com/current-duplicate-outline-followup) >>\nendobj\n"
        . "20 0 obj\n<< /Names [(ActionDuplicateDest) [4 0 R /Fit] (CurrentDuplicateDest) [3 0 R /FitH 720]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($currentPageText) . " >>\nstream\n{$currentPageText}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($actionPageText) . " >>\nstream\n{$actionPageText}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'records duplicate outline Title Dest and action operands as review metadata' => static function (
        TestRunner $t
    ) use ($outlineMetadataDuplicateNavKeyBoundaryPdf): void {
        $pdf = $outlineMetadataDuplicateNavKeyBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(2, $outline['item_count'] ?? null);
        $t->same(2, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(2, $outline['duplicate_item_key_count'] ?? null);
        $t->same(['Title', 'Dest', 'A'], $outline['duplicate_item_keys'] ?? null);
        $t->same(true, $outline['duplicate_item_key_review_only'] ?? null);
        $t->same(false, $outline['duplicate_item_key_payload_included'] ?? null);

        $first = $items[0] ?? [];
        $firstReview = $first['duplicate_key_review'] ?? [];
        $t->same('Current Duplicate Bookmark', $first['title'] ?? null);
        $t->same(6, $first['outline_object'] ?? null);
        $t->same('CurrentDuplicateDest', $first['destination'] ?? null);
        $t->same(true, $first['destination_resolved'] ?? null);
        $t->same(0, $first['page'] ?? null);
        $t->same('FitH', $first['view_mode'] ?? null);
        $t->same(['top' => 720.0], $first['view_parameters'] ?? null);
        $t->same('outline_item_duplicate_keys', $firstReview['source'] ?? null);
        $t->same(true, $firstReview['review_only'] ?? null);
        $t->same(false, $firstReview['payload_included'] ?? null);
        $t->same(false, $firstReview['visible_text_source'] ?? null);
        $t->same('last_top_level_entry', $firstReview['selected_entry_policy'] ?? null);
        $t->same(['Title', 'Dest'], $firstReview['keys'] ?? null);
        $t->same(['Title' => 2, 'Dest' => 2], $firstReview['declared_entry_counts'] ?? null);
        $t->same(['Title' => 1, 'Dest' => 1], $firstReview['selected_entry_indexes'] ?? null);

        $second = $items[1] ?? [];
        $secondReview = $second['duplicate_key_review'] ?? [];
        $t->same('Duplicate Action Bookmark', $second['title'] ?? null);
        $t->same(7, $second['outline_object'] ?? null);
        $t->same('GoTo', $second['action_type'] ?? null);
        $t->same(13, $second['action_object'] ?? null);
        $t->same('ActionDuplicateDest', $second['destination'] ?? null);
        $t->same(true, $second['destination_resolved'] ?? null);
        $t->same(1, $second['page'] ?? null);
        $t->same(['GoTo', 'URI'], $second['action_chain_types'] ?? null);
        $t->same(true, $second['action_chain_has_next'] ?? null);
        $t->same(['A'], $secondReview['keys'] ?? null);
        $t->same(['A' => 2], $secondReview['declared_entry_counts'] ?? null);
        $t->same(['A' => 1], $secondReview['selected_entry_indexes'] ?? null);

        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Duplicate Bookmark'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'StaleDuplicateDest'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Nested title decoy'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'NestedDest'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-duplicate-outline-action'));
    },
    'keeps duplicate outline navigation-key decoys out of TOC and visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineMetadataDuplicateNavKeyBoundaryPdf): void {
        $pdf = $outlineMetadataDuplicateNavKeyBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['Current Duplicate Bookmark', 'Duplicate Action Bookmark'], array_column($toc, 'title'));
        $t->same(['CurrentDuplicateDest', 'ActionDuplicateDest'], array_column($toc, 'destination'));
        $t->same([0, 1], array_column($toc, 'page'));
        $t->same(['Current Duplicate Bookmark', 'Duplicate Action Bookmark'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same([0, 1], array_column($navigation['outline'] ?? [], 'page'));
        $t->same(['GoTo', 'URI'], array_column($navigation['outline_action_review_actions'] ?? [], 'action_type'));
        $t->same("Current duplicate nav-key page body\nAction duplicate nav-key page body", $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Duplicate Bookmark'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'StaleDuplicateDest'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'stale-duplicate-outline-action'));
        $t->true(!str_contains($plainText, 'Current Duplicate Bookmark'));
        $t->true(!str_contains($plainText, 'Duplicate Action Bookmark'));
        $t->true(!str_contains($plainText, 'Stale Duplicate Bookmark'));
        $t->true(!str_contains($plainText, 'StaleDuplicateDest'));
        $t->true(!str_contains($plainText, 'stale-duplicate-outline-action'));
    },
];
