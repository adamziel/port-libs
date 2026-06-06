<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataStreamTypeBoundaryPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Outline metadata stream type boundary body) Tj ET';
    $embeddedPayload = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title><rdf:Alt><rdf:li xml:lang="x-default">Rejected Embedded Outline Metadata Payload</rdf:li></rdf:Alt></dc:title></rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
    $malformedPayload = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title><rdf:Alt><rdf:li xml:lang="x-default">Rejected Malformed Outline Metadata Payload</rdf:li></rdf:Alt></dc:title></rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
    $embeddedStream = gzcompress($embeddedPayload);
    $malformedStream = gzcompress($malformedPayload);
    if (!is_string($embeddedStream) || !is_string($malformedStream)) {
        throw new RuntimeException('Unable to compress outline metadata stream type boundary payloads.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Rejected Non Metadata Stream Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 9 0 R /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Rejected Malformed Metadata Stream Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [3 0 R /Fit] /Metadata 10 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Filter /FlateDecode /Length " . strlen($embeddedStream) . " >>\nstream\n{$embeddedStream}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($malformedStream) . " >>\nstream\n{$malformedStream}\nendstream /A 12 0 R\nendobj\n"
        . "12 0 obj\n<< /S /JavaScript /JS (app.alert\\('malformed outline metadata tail action'\\)) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $embeddedPayload, $malformedPayload];
};

return [
    'rejects non-metadata and malformed outline Metadata streams as review-only boundary rows' => static function (
        TestRunner $t
    ) use ($outlineMetadataStreamTypeBoundaryPdf): void {
        [$pdf, $embeddedPayload, $malformedPayload] = $outlineMetadataStreamTypeBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
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
        $t->same([
            'Rejected Non Metadata Stream Chapter',
            'Rejected Malformed Metadata Stream Appendix',
        ], $outline['titles'] ?? []);
        $t->same([6, 7], array_column($items, 'outline_object'));
        $t->same([0, 0], array_column($items, 'page'));
        $t->same(['FitH', 'Fit'], array_column($items, 'view_mode'));

        $embeddedReview = $items[0]['metadata_stream_review'] ?? [];
        $t->same('outline_item_metadata_stream', $embeddedReview['source'] ?? null);
        $t->same(true, $embeddedReview['review_only'] ?? null);
        $t->same(false, $embeddedReview['payload_included'] ?? null);
        $t->same(false, $embeddedReview['visible_text_source'] ?? null);
        $t->same(false, $embeddedReview['accepted_as_document_xmp'] ?? null);
        $t->same('rejected_non_metadata_outline_item_stream', $embeddedReview['status'] ?? null);
        $t->same(9, $embeddedReview['object_number'] ?? null);
        $t->same('EmbeddedFile', $embeddedReview['type'] ?? null);
        $t->same('text/xml', $embeddedReview['subtype'] ?? null);
        $t->same(['FlateDecode'], $embeddedReview['filters'] ?? null);
        $t->same(strlen($embeddedPayload), $embeddedReview['bytes'] ?? null);
        $t->same(hash('sha256', $embeddedPayload), $embeddedReview['sha256'] ?? null);
        $t->same(['title'], $embeddedReview['xmp_summary']['field_names'] ?? null);
        $t->same(true, $embeddedReview['xmp_summary']['text_values_redacted'] ?? null);

        $malformedReview = $items[1]['metadata_stream_review'] ?? [];
        $t->same('outline_item_metadata_stream', $malformedReview['source'] ?? null);
        $t->same('rejected_malformed_outline_item_metadata_stream', $malformedReview['status'] ?? null);
        $t->same(10, $malformedReview['object_number'] ?? null);
        $t->same('Metadata', $malformedReview['type'] ?? null);
        $t->same('XML', $malformedReview['subtype'] ?? null);
        $t->same(['FlateDecode'], $malformedReview['filters'] ?? null);
        $t->same(strlen($malformedPayload), $malformedReview['bytes'] ?? null);
        $t->same(hash('sha256', $malformedPayload), $malformedReview['sha256'] ?? null);
        $t->same(['title'], $malformedReview['xmp_summary']['field_names'] ?? null);
        $t->same(true, $malformedReview['xmp_summary']['text_values_redacted'] ?? null);

        $t->true(is_string($encoded) && !str_contains($encoded, $embeddedPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $malformedPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Embedded Outline Metadata Payload'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Rejected Malformed Outline Metadata Payload'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'malformed outline metadata tail action'));
    },
    'keeps rejected outline Metadata stream payloads out of TOC navigation and visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineMetadataStreamTypeBoundaryPdf): void {
        [$pdf] = $outlineMetadataStreamTypeBoundaryPdf();

        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $expectedTitles = [
            'Rejected Non Metadata Stream Chapter',
            'Rejected Malformed Metadata Stream Appendix',
        ];
        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same([0, 0], array_column($toc, 'page'));
        $t->same(['FitH', 'Fit'], array_column($toc, 'view_mode'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same('Outline metadata stream type boundary body', $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Rejected Embedded Outline Metadata Payload'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Rejected Malformed Outline Metadata Payload'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'malformed outline metadata tail action'));
        $t->true(!str_contains($plainText, 'Rejected Non Metadata Stream Chapter'));
        $t->true(!str_contains($plainText, 'Rejected Malformed Metadata Stream Appendix'));
        $t->true(!str_contains($plainText, 'Rejected Embedded Outline Metadata Payload'));
        $t->true(!str_contains($plainText, 'Rejected Malformed Outline Metadata Payload'));
    },
];
