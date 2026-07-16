<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineSelectedDuplicateMetadataOperandBoundaryPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Selected duplicate outline metadata operand body) Tj ET';
    $staleRootPayload = '<outline-metadata role="stale-root">Stale malformed outline root metadata operand payload</outline-metadata>';
    $currentRootPayload = '<outline-metadata role="current-root">Current selected outline root metadata operand payload</outline-metadata>';
    $staleItemPayload = '<outline-metadata role="stale-item">Stale malformed outline item metadata operand payload</outline-metadata>';
    $currentItemPayload = '<outline-metadata role="current-item">Current selected outline item metadata operand payload</outline-metadata>';

    $staleRootStream = gzcompress($staleRootPayload);
    $currentRootStream = gzcompress($currentRootPayload);
    $staleItemStream = gzcompress($staleItemPayload);
    $currentItemStream = gzcompress($currentItemPayload);
    if (
        !is_string($staleRootStream)
        || !is_string($currentRootStream)
        || !is_string($staleItemStream)
        || !is_string($currentItemStream)
    ) {
        throw new RuntimeException('Unable to compress selected duplicate outline metadata fixture streams.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Metadata 8 0 R 88 0 R /Metadata 9 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Title (Selected Duplicate Metadata Operand Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 10 0 R 89 0 R /Metadata 11 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($staleRootStream) . " >>\nstream\n{$staleRootStream}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($currentRootStream) . " >>\nstream\n{$currentRootStream}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($staleItemStream) . " >>\nstream\n{$staleItemStream}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($currentItemStream) . " >>\nstream\n{$currentItemStream}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $staleRootPayload,
        $currentRootPayload,
        $staleItemPayload,
        $currentItemPayload,
    ];
};

return [
    'uses selected duplicate outline Metadata entries after stale malformed operands' => static function (
        TestRunner $t
    ) use ($outlineSelectedDuplicateMetadataOperandBoundaryPdf): void {
        [$pdf, $staleRootPayload, $currentRootPayload, $staleItemPayload, $currentItemPayload] = $outlineSelectedDuplicateMetadataOperandBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $item = $outline['items'][0] ?? [];
        $rootReview = $outline['metadata_stream_review'] ?? [];
        $itemReview = $item['metadata_stream_review'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(6, $outline['last_item_object'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(['Selected Duplicate Metadata Operand Chapter'], $outline['titles'] ?? []);

        $t->same('outline_root_metadata_stream', $rootReview['source'] ?? null);
        $t->same('reviewed_outline_root_metadata_stream', $rootReview['status'] ?? null);
        $t->same(2, $rootReview['declared_entry_count'] ?? null);
        $t->same(true, $rootReview['duplicate_entries'] ?? null);
        $t->same(1, $rootReview['selected_entry_index'] ?? null);
        $t->same(9, $rootReview['object_number'] ?? null);
        $t->same(0, $rootReview['object_generation'] ?? null);
        $t->same('Metadata', $rootReview['type'] ?? null);
        $t->same('XML', $rootReview['subtype'] ?? null);
        $t->same(['FlateDecode'], $rootReview['filters'] ?? null);
        $t->same(strlen($currentRootPayload), $rootReview['bytes'] ?? null);
        $t->same(hash('sha256', $currentRootPayload), $rootReview['sha256'] ?? null);
        $t->same(true, $rootReview['review_only'] ?? null);
        $t->same(false, $rootReview['payload_included'] ?? null);
        $t->same(false, $rootReview['accepted_as_document_xmp'] ?? null);

        $t->same('outline_item_metadata_stream', $itemReview['source'] ?? null);
        $t->same('reviewed_outline_item_metadata_stream', $itemReview['status'] ?? null);
        $t->same(2, $itemReview['declared_entry_count'] ?? null);
        $t->same(true, $itemReview['duplicate_entries'] ?? null);
        $t->same(1, $itemReview['selected_entry_index'] ?? null);
        $t->same(11, $itemReview['object_number'] ?? null);
        $t->same(0, $itemReview['object_generation'] ?? null);
        $t->same('Metadata', $itemReview['type'] ?? null);
        $t->same('XML', $itemReview['subtype'] ?? null);
        $t->same(['FlateDecode'], $itemReview['filters'] ?? null);
        $t->same(strlen($currentItemPayload), $itemReview['bytes'] ?? null);
        $t->same(hash('sha256', $currentItemPayload), $itemReview['sha256'] ?? null);
        $t->same(true, $itemReview['review_only'] ?? null);
        $t->same(false, $itemReview['payload_included'] ?? null);
        $t->same(false, $itemReview['accepted_as_document_xmp'] ?? null);

        $duplicateKeyReview = $item['duplicate_key_review'] ?? [];
        $t->same(['Metadata'], $duplicateKeyReview['keys'] ?? null);
        $t->same(['Metadata' => 2], $duplicateKeyReview['declared_entry_counts'] ?? null);
        $t->same(['Metadata' => 1], $duplicateKeyReview['selected_entry_indexes'] ?? null);
        $t->same(['Metadata'], $outline['duplicate_item_keys'] ?? null);

        foreach ([$staleRootPayload, $currentRootPayload, $staleItemPayload, $currentItemPayload] as $payload) {
            $t->true(is_string($encoded) && !str_contains($encoded, $payload));
        }
        $t->true(is_string($encoded) && !str_contains($encoded, 'rejected_malformed_outline_root_metadata_operand'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'rejected_malformed_outline_item_metadata_operand'));
        $t->true(is_string($encoded) && !str_contains($encoded, '88 0 R'));
        $t->true(is_string($encoded) && !str_contains($encoded, '89 0 R'));
    },
    'keeps selected duplicate outline Metadata payloads out of TOC navigation and visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineSelectedDuplicateMetadataOperandBoundaryPdf): void {
        [$pdf, $staleRootPayload, $currentRootPayload, $staleItemPayload, $currentItemPayload] = $outlineSelectedDuplicateMetadataOperandBoundaryPdf();

        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['Selected Duplicate Metadata Operand Chapter'], array_column($toc, 'title'));
        $t->same([0], array_column($toc, 'page'));
        $t->same(['FitH'], array_column($toc, 'view_mode'));
        $t->same(['Selected Duplicate Metadata Operand Chapter'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same(11, $navigation['outline'][0]['metadata_stream_review']['object_number'] ?? null);
        $t->same('reviewed_outline_item_metadata_stream', $navigation['outline'][0]['metadata_stream_review']['status'] ?? null);
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same('Selected duplicate outline metadata operand body', $plainText);
        foreach ([$staleRootPayload, $currentRootPayload, $staleItemPayload, $currentItemPayload] as $payload) {
            $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, $payload));
            $t->true(!str_contains($plainText, $payload));
        }
        $t->true(!str_contains($plainText, 'Selected Duplicate Metadata Operand Chapter'));
    },
];
