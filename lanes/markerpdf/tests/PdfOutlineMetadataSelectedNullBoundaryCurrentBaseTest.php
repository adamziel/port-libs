<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineSelectedNullMetadataBoundaryPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Selected null outline metadata body) Tj ET';
    $rootPayload = '<outline-metadata role="root-stale">Stale root metadata stream ignored by selected null</outline-metadata>';
    $itemPayload = '<outline-metadata role="item-stale">Stale item metadata stream ignored by selected null</outline-metadata>';

    $rootStream = gzcompress($rootPayload);
    $itemStream = gzcompress($itemPayload);
    if (!is_string($rootStream) || !is_string($itemStream)) {
        throw new RuntimeException('Unable to compress selected-null outline metadata fixture streams.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Metadata 8 0 R /Metadata null >>\nendobj\n"
        . "6 0 obj\n<< /Title (Selected Null Metadata Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 9 0 R /Metadata null >>\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootStream) . " >>\nstream\n{$rootStream}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($itemStream) . " >>\nstream\n{$itemStream}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $rootPayload, $itemPayload];
};

return [
    'records selected null outline Metadata entries without stale stream hashes' => static function (
        TestRunner $t
    ) use ($outlineSelectedNullMetadataBoundaryPdf): void {
        [$pdf, $rootPayload, $itemPayload] = $outlineSelectedNullMetadataBoundaryPdf();

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
        $t->same(['Selected Null Metadata Chapter'], $outline['titles'] ?? []);

        $t->same('outline_root_metadata_stream', $rootReview['source'] ?? null);
        $t->same('selected_null_outline_root_metadata_reference', $rootReview['status'] ?? null);
        $t->same(2, $rootReview['declared_entry_count'] ?? null);
        $t->same(true, $rootReview['duplicate_entries'] ?? null);
        $t->same(1, $rootReview['selected_entry_index'] ?? null);
        $t->same('keyword', $rootReview['operand_shape'] ?? null);
        $t->same(true, $rootReview['selected_null_entry'] ?? null);
        $t->same(true, $rootReview['indirect_reference_required'] ?? null);
        $t->same(true, $rootReview['review_only'] ?? null);
        $t->same(false, $rootReview['payload_included'] ?? null);
        $t->same(false, $rootReview['accepted_as_document_xmp'] ?? null);
        $t->true(!array_key_exists('object_number', $rootReview));
        $t->true(!array_key_exists('bytes', $rootReview));
        $t->true(!array_key_exists('sha256', $rootReview));

        $t->same('outline_item_metadata_stream', $itemReview['source'] ?? null);
        $t->same('selected_null_outline_item_metadata_reference', $itemReview['status'] ?? null);
        $t->same(2, $itemReview['declared_entry_count'] ?? null);
        $t->same(true, $itemReview['duplicate_entries'] ?? null);
        $t->same(1, $itemReview['selected_entry_index'] ?? null);
        $t->same('keyword', $itemReview['operand_shape'] ?? null);
        $t->same(true, $itemReview['selected_null_entry'] ?? null);
        $t->same(true, $itemReview['indirect_reference_required'] ?? null);
        $t->same(false, $itemReview['payload_included'] ?? null);
        $t->true(!array_key_exists('object_number', $itemReview));
        $t->true(!array_key_exists('bytes', $itemReview));
        $t->true(!array_key_exists('sha256', $itemReview));

        $duplicateKeyReview = $item['duplicate_key_review'] ?? [];
        $t->same(['Metadata'], $duplicateKeyReview['keys'] ?? null);
        $t->same(['Metadata' => 2], $duplicateKeyReview['declared_entry_counts'] ?? null);
        $t->same(['Metadata' => 1], $duplicateKeyReview['selected_entry_indexes'] ?? null);
        $t->same(['Metadata'], $outline['duplicate_item_keys'] ?? null);

        foreach ([$rootPayload, $itemPayload] as $payload) {
            $t->true(is_string($encoded) && !str_contains($encoded, $payload));
            $t->true(is_string($encoded) && !str_contains($encoded, hash('sha256', $payload)));
        }
    },
    'carries selected null outline Metadata review into navigation without visible text leakage' => static function (
        TestRunner $t
    ) use ($outlineSelectedNullMetadataBoundaryPdf): void {
        [$pdf, $rootPayload, $itemPayload] = $outlineSelectedNullMetadataBoundaryPdf();

        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $outlineRows = $navigation['outline'] ?? [];
        $navigationReview = $outlineRows[0]['metadata_stream_review'] ?? [];

        $t->same(['Selected Null Metadata Chapter'], array_column($toc, 'title'));
        $t->same([0], array_column($toc, 'page'));
        $t->same(['FitH'], array_column($toc, 'view_mode'));
        $t->same(['Selected Null Metadata Chapter'], array_column($outlineRows, 'title'));
        $t->same('selected_null_outline_item_metadata_reference', $navigationReview['status'] ?? null);
        $t->same(2, $navigationReview['declared_entry_count'] ?? null);
        $t->same(true, $navigationReview['selected_null_entry'] ?? null);
        $t->same(false, $navigationReview['payload_included'] ?? null);
        $t->same(false, $navigationReview['visible_text_source'] ?? null);
        $t->same('Selected null outline metadata body', $plainText);
        foreach ([$rootPayload, $itemPayload] as $payload) {
            $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, $payload));
            $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, hash('sha256', $payload)));
            $t->true(!str_contains($plainText, $payload));
        }
        $t->true(!str_contains($plainText, 'Selected Null Metadata Chapter'));
    },
];
