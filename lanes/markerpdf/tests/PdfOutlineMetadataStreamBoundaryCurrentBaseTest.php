<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataStreamBoundaryPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Lightweight outline metadata stream visible body) Tj ET';
    $metadataPayload = 'BT /F1 12 Tf 72 720 Td (Outline item metadata stream should stay review only) Tj ET';
    $metadataStream = gzcompress($metadataPayload);
    if (!is_string($metadataStream)) {
        throw new RuntimeException('Unable to compress outline item metadata stream payload.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Outline Metadata Stream Boundary) /Parent 5 0 R /Metadata 8 0 R /C [0 .2 .4] /F 1 >>\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataStream) . " >>\nstream\n{$metadataStream}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $metadataPayload];
};

return [
    'summarizes outline item Metadata streams without promoting payloads to document metadata' => static function (
        TestRunner $t
    ) use ($outlineMetadataStreamBoundaryPdf): void {
        [$pdf, $metadataPayload] = $outlineMetadataStreamBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $item = $outline['items'][0] ?? [];
        $review = $item['metadata_stream_review'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->true(!array_key_exists('title', $metadata));
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(0, $outline['resolved_destination_count'] ?? null);
        $t->same(1, $outline['unresolved_destination_count'] ?? null);
        $t->same(['Outline Metadata Stream Boundary'], $outline['titles'] ?? []);
        $t->same('Outline Metadata Stream Boundary', $item['title'] ?? null);
        $t->same(6, $item['outline_object'] ?? null);
        $t->same('#003366', $item['text_color_hex'] ?? null);
        $t->same('outline_item_metadata_stream', $review['source'] ?? null);
        $t->same(true, $review['review_only'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(false, $review['visible_text_source'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same('reviewed_outline_item_metadata_stream', $review['status'] ?? null);
        $t->same(8, $review['object_number'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataPayload), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataPayload), $review['sha256'] ?? null);
        $t->same(false, $item['destination_resolved'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, $metadataPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Outline item metadata stream should stay review only'));
    },
    'excludes outline item Metadata streams from lightweight fallback WordPress text' => static function (
        TestRunner $t
    ) use ($outlineMetadataStreamBoundaryPdf): void {
        [$pdf] = $outlineMetadataStreamBoundaryPdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same('Lightweight outline metadata stream visible body', $plainText);
        $t->true(!str_contains($plainText, 'Outline Metadata Stream Boundary'));
        $t->true(!str_contains($plainText, 'Outline item metadata stream should stay review only'));
    },
];
