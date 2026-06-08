<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineRootMetadataSummaryPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Outline root summary visible body) Tj ET';
    $rootPayload = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Hidden Root Summary XMP</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
    $itemPayload = str_replace('Hidden Root Summary XMP', 'Hidden Item Summary XMP', $rootPayload);

    $rootStream = gzcompress($rootPayload);
    $itemStream = gzcompress($itemPayload);
    if (!is_string($rootStream) || !is_string($itemStream)) {
        throw new RuntimeException('Unable to compress outline metadata summary fixture streams.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Metadata 8 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Title (Root Summary Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 9 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootStream) . " >>\nstream\n{$rootStream}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($itemStream) . " >>\nstream\n{$itemStream}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $rootPayload, $itemPayload];
};

$outlineRootSelectedDirectMetadataSummaryPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Selected direct root summary visible body) Tj ET';
    $staleRootPayload = '<outline-metadata role="root-stale">Stale root summary metadata stream must stay hidden</outline-metadata>';
    $staleRootStream = gzcompress($staleRootPayload);
    if (!is_string($staleRootStream)) {
        throw new RuntimeException('Unable to compress selected direct root metadata fixture stream.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Metadata 8 0 R /Metadata << /Type /Metadata /Subtype /XML >> >>\nendobj\n"
        . "6 0 obj\n<< /Title (Selected Direct Root Summary) /Parent 5 0 R /Dest [3 0 R /FitH 720] >>\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($staleRootStream) . " >>\nstream\n{$staleRootStream}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $staleRootPayload];
};

return [
    'summarizes valid outline root Metadata streams at the document outline boundary' => static function (
        TestRunner $t
    ) use ($outlineRootMetadataSummaryPdf): void {
        [$pdf, $rootPayload, $itemPayload] = $outlineRootMetadataSummaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $review = $outline['metadata_stream_review'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->true(!array_key_exists('title', $metadata), 'Outline root XMP must not become document XMP title.');
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(6, $outline['last_item_object'] ?? null);
        $t->same(['Root Summary Chapter'], $outline['titles'] ?? null);

        $t->same('outline_root_metadata_stream', $review['source'] ?? null);
        $t->same('reviewed_outline_root_metadata_stream', $review['status'] ?? null);
        $t->same(8, $review['object_number'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);

        $t->same(1, $outline['root_metadata_stream_count'] ?? null);
        $t->same(true, $outline['root_metadata_stream_review_only'] ?? null);
        $t->same(false, $outline['root_metadata_stream_payload_included'] ?? null);
        $t->same(false, $outline['root_metadata_stream_accepted_as_document_xmp'] ?? null);
        $t->same('reviewed_outline_root_metadata_stream', $outline['root_metadata_stream_status'] ?? null);
        $t->same(8, $outline['root_metadata_stream_object'] ?? null);
        $t->same(0, $outline['root_metadata_stream_object_generation'] ?? null);
        $t->same('Metadata', $outline['root_metadata_stream_type'] ?? null);
        $t->same('XML', $outline['root_metadata_stream_subtype'] ?? null);
        $t->same(['FlateDecode'], $outline['root_metadata_stream_filters'] ?? null);
        $t->same(1, $outline['item_metadata_stream_count'] ?? null);
        $t->same([9], $outline['item_metadata_stream_objects'] ?? null);

        foreach ([$rootPayload, $itemPayload] as $payload) {
            $t->true(is_string($encoded) && !str_contains($encoded, $payload));
        }
    },
    'summarizes selected direct outline root Metadata operands without stale stream provenance' => static function (
        TestRunner $t
    ) use ($outlineRootSelectedDirectMetadataSummaryPdf): void {
        [$pdf, $staleRootPayload] = $outlineRootSelectedDirectMetadataSummaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $review = $outline['metadata_stream_review'] ?? [];
        $navigationRootReview = $navigation['outline_root_review']['metadata_stream_review'] ?? [];
        $encoded = json_encode([$metadata, $navigation], JSON_UNESCAPED_SLASHES);

        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(['Selected Direct Root Summary'], $outline['titles'] ?? null);
        $t->same('outline_root_metadata_stream', $review['source'] ?? null);
        $t->same('rejected_non_indirect_metadata_reference', $review['status'] ?? null);
        $t->same(2, $review['declared_entry_count'] ?? null);
        $t->same(true, $review['duplicate_entries'] ?? null);
        $t->same(1, $review['selected_entry_index'] ?? null);
        $t->same('dictionary', $review['operand_shape'] ?? null);
        $t->same(true, $review['indirect_reference_required'] ?? null);

        $t->same(1, $outline['root_metadata_stream_count'] ?? null);
        $t->same('rejected_non_indirect_metadata_reference', $outline['root_metadata_stream_status'] ?? null);
        $t->same(2, $outline['root_metadata_stream_declared_entry_count'] ?? null);
        $t->same(true, $outline['root_metadata_stream_duplicate_entries'] ?? null);
        $t->same(1, $outline['root_metadata_stream_selected_entry_index'] ?? null);
        $t->same('dictionary', $outline['root_metadata_stream_operand_shape'] ?? null);
        $t->same(true, $outline['root_metadata_stream_indirect_reference_required'] ?? null);
        $t->same(false, $outline['root_metadata_stream_payload_included'] ?? null);
        $t->true(!array_key_exists('root_metadata_stream_object', $outline));
        $t->true(!array_key_exists('root_metadata_stream_filters', $outline));
        $t->true(!array_key_exists('item_metadata_stream_count', $outline));

        $t->same($review, $navigationRootReview);
        $t->same('Selected direct root summary visible body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, $staleRootPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, hash('sha256', $staleRootPayload)));
        $t->true(!str_contains($plainText, 'Selected Direct Root Summary'));
        $t->true(!str_contains($plainText, 'Stale root summary metadata stream must stay hidden'));
    },
];
