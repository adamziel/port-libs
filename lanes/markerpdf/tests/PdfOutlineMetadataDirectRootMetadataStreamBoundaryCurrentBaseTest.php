<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineDirectRootMetadataStreamBoundaryPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Direct root metadata fallback visible body) Tj ET';
    $metadataPayload = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Hidden Direct Root Outline Metadata</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
    $metadataStream = gzcompress($metadataPayload);
    if (!is_string($metadataStream)) {
        throw new RuntimeException('Unable to compress direct outline-root metadata stream payload.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Outlines << /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Metadata 8 0 R >> /PageMode /UseOutlines >>\nendobj\n"
        . "6 0 obj\n<< /Title (Direct Root Metadata Fallback Chapter) >>\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataStream) . " >>\nstream\n{$metadataStream}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $metadataPayload];
};

return [
    'records direct catalog outline-root Metadata as review-only document outline metadata' => static function (
        TestRunner $t
    ) use ($outlineDirectRootMetadataStreamBoundaryPdf): void {
        [$pdf, $metadataPayload] = $outlineDirectRootMetadataStreamBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $review = $outline['metadata_stream_review'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->true(!array_key_exists('title', $metadata), 'Direct outline-root metadata must not become document XMP.');
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(null, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(6, $outline['last_item_object'] ?? null);
        $t->same(1, $outline['declared_visible_count'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(0, $outline['resolved_destination_count'] ?? null);
        $t->same(1, $outline['unresolved_destination_count'] ?? null);
        $t->same(['Direct Root Metadata Fallback Chapter'], $outline['titles'] ?? []);

        $t->same('outline_root_metadata_stream', $review['source'] ?? null);
        $t->same('reviewed_outline_root_metadata_stream', $review['status'] ?? null);
        $t->same(true, $review['review_only'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(false, $review['visible_text_source'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(1, $review['declared_entry_count'] ?? null);
        $t->same(false, $review['duplicate_entries'] ?? null);
        $t->same(0, $review['selected_entry_index'] ?? null);
        $t->same(8, $review['object_number'] ?? null);
        $t->same(0, $review['object_generation'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataPayload), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataPayload), $review['sha256'] ?? null);
        $t->same(['title'], $review['xmp_summary']['field_names'] ?? null);
        $t->same(true, $review['xmp_summary']['text_values_redacted'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, $metadataPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Hidden Direct Root Outline Metadata'));
    },
    'excludes direct outline-root Metadata stream bytes from fallback WordPress text' => static function (
        TestRunner $t
    ) use ($outlineDirectRootMetadataStreamBoundaryPdf): void {
        [$pdf, $metadataPayload] = $outlineDirectRootMetadataStreamBoundaryPdf();

        $textExtractor = new PdfTextExtractor();
        $plainText = $textExtractor->extractPlainText($pdf);
        $lightweight = $textExtractor->extractOutlineMetadata($pdf);
        $encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

        $t->same('Direct root metadata fallback visible body', $plainText);
        $t->same([], $lightweight['pdf_toc'] ?? null);
        $t->same(0, $lightweight['pages'] ?? null);
        $t->true(is_string($encodedLightweight) && !str_contains($encodedLightweight, $metadataPayload));
        $t->true(!str_contains($plainText, 'Direct Root Metadata Fallback Chapter'));
        $t->true(!str_contains($plainText, 'Hidden Direct Root Outline Metadata'));
        $t->true(!str_contains($plainText, $metadataPayload));
    },
];
