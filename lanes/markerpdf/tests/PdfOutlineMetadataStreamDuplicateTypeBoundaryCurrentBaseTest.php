<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataStreamDuplicateTypeBoundaryPacket = static function (): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Hidden Outline Duplicate Type XMP Title</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Outline Metadata Editor</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>outline-duplicate-type-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Outline Metadata Boundary Producer</pdf:Producer>'
        . '<xmp:CreateDate>2026-06-06T10:47:11Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$outlineMetadataStreamDuplicateTypeBoundaryPdf = static function () use ($outlineMetadataStreamDuplicateTypeBoundaryPacket): array {
    $xmp = $outlineMetadataStreamDuplicateTypeBoundaryPacket();
    $compressedXmp = gzcompress($xmp);
    if (!is_string($compressedXmp)) {
        throw new RuntimeException('Unable to compress outline duplicate Type XMP fixture.');
    }

    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Outline metadata duplicate Type boundary body) Tj ET';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Duplicate Type Metadata Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\n"
        . "stream\n{$compressedXmp}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $xmp, $compressedXmp];
};

return [
    'rejects outline Metadata streams with duplicate Type and Subtype dictionary keys' => static function (
        TestRunner $t
    ) use ($outlineMetadataStreamDuplicateTypeBoundaryPdf): void {
        [$pdf, $xmp, $compressedXmp] = $outlineMetadataStreamDuplicateTypeBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $item = $outline['items'][0] ?? [];
        $review = $item['metadata_stream_review'] ?? [];
        $summary = $review['xmp_summary'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(['Duplicate Type Metadata Chapter'], $outline['titles'] ?? []);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same('Duplicate Type Metadata Chapter', $item['title'] ?? null);
        $t->same(6, $item['outline_object'] ?? null);
        $t->same(0, $item['page'] ?? null);
        $t->same('FitH', $item['view_mode'] ?? null);

        $t->same('outline_item_metadata_stream', $review['source'] ?? null);
        $t->same('rejected_duplicate_metadata_stream_type_keys', $review['status'] ?? null);
        $t->same(true, $review['review_only'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(false, $review['visible_text_source'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(8, $review['object_number'] ?? null);
        $t->same(0, $review['object_generation'] ?? null);
        $t->same(['Type', 'Subtype'], $review['duplicate_keys'] ?? null);
        $t->same(2, $review['type_entry_count'] ?? null);
        $t->same(2, $review['subtype_entry_count'] ?? null);
        $t->same(['EmbeddedFile', 'Metadata'], $review['type_values'] ?? null);
        $t->same(['text/xml', 'XML'], $review['subtype_values'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($compressedXmp), $review['declared_length'] ?? null);
        $t->same(strlen($xmp), $review['bytes'] ?? null);
        $t->same(hash('sha256', $xmp), $review['sha256'] ?? null);
        $t->same(false, $summary['payload_included'] ?? null);
        $t->same(true, $summary['text_values_redacted'] ?? null);
        $t->true(in_array('title', $summary['field_names'] ?? [], true));
        $t->same('2026-06-06T10:47:11Z', $summary['dates_utc']['created_at'] ?? null);

        $t->true(is_string($encoded) && !str_contains($encoded, 'reviewed_outline_item_metadata_stream'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Hidden Outline Duplicate Type XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'outline-duplicate-type-boundary'));
    },
    'keeps duplicate Type outline Metadata payloads out of TOC navigation and visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineMetadataStreamDuplicateTypeBoundaryPdf): void {
        [$pdf] = $outlineMetadataStreamDuplicateTypeBoundaryPdf();

        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['Duplicate Type Metadata Chapter'], array_column($toc, 'title'));
        $t->same([0], array_column($toc, 'page'));
        $t->same(['FitH'], array_column($toc, 'view_mode'));
        $t->same(['Duplicate Type Metadata Chapter'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same('Outline metadata duplicate Type boundary body', $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Hidden Outline Duplicate Type XMP Title'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'outline-duplicate-type-boundary'));
        $t->true(!str_contains($plainText, 'Duplicate Type Metadata Chapter'));
        $t->true(!str_contains($plainText, 'Hidden Outline Duplicate Type XMP Title'));
        $t->true(!str_contains($plainText, 'outline-duplicate-type-boundary'));
    },
];
