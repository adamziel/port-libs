<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineRootMetadataDuplicateTypeBoundaryPacket = static function (): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Hidden Outline Root Duplicate Type XMP Title</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Outline Root Metadata Editor</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>outline-root-duplicate-type-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Outline Root Metadata Boundary Producer</pdf:Producer>'
        . '<xmp:CreateDate>2026-06-08T21:25:54Z</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$outlineRootMetadataDuplicateTypeBoundaryPdf = static function () use ($outlineRootMetadataDuplicateTypeBoundaryPacket): array {
    $xmp = $outlineRootMetadataDuplicateTypeBoundaryPacket();
    $compressedXmp = gzcompress($xmp);
    if (!is_string($compressedXmp)) {
        throw new RuntimeException('Unable to compress outline root duplicate Type XMP fixture.');
    }

    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Outline root metadata duplicate Type visible body) Tj ET';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Metadata 8 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Title (Root Duplicate Type Metadata Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] >>\nendobj\n"
        . "8 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\n"
        . "stream\n{$compressedXmp}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $xmp, $compressedXmp];
};

return [
    'records outline root Metadata stream duplicate role keys as root-local review metadata' => static function (
        TestRunner $t
    ) use ($outlineRootMetadataDuplicateTypeBoundaryPdf): void {
        [$pdf, $xmp, $compressedXmp] = $outlineRootMetadataDuplicateTypeBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $review = $outline['metadata_stream_review'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->true(!array_key_exists('title', $metadata));
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(6, $outline['last_item_object'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(['Root Duplicate Type Metadata Chapter'], $outline['titles'] ?? []);

        $t->same('outline_root_metadata_stream', $review['source'] ?? null);
        $t->same('root', $review['outline_metadata_scope'] ?? null);
        $t->same('Outlines', $review['outline_metadata_scope_object'] ?? null);
        $t->same('outline_root_review_only', $review['document_xmp_promotion_boundary'] ?? null);
        $t->same(true, $review['root_metadata_stream_local_to_outline'] ?? null);
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
        $t->same(['title', 'producer', 'created_at', 'authors', 'keywords'], $review['xmp_summary']['field_names'] ?? null);
        $t->same(true, $review['xmp_summary']['text_values_redacted'] ?? null);
        $t->same('2026-06-08T21:25:54Z', $review['xmp_summary']['dates_utc']['created_at'] ?? null);
        $t->same('root', $outline['root_metadata_stream_scope'] ?? null);
        $t->same('Outlines', $outline['root_metadata_stream_scope_object'] ?? null);
        $t->same('outline_root_review_only', $outline['root_metadata_stream_document_xmp_promotion_boundary'] ?? null);
        $t->same(true, $outline['root_metadata_stream_local_to_outline'] ?? null);
        $t->same('rejected_duplicate_metadata_stream_type_keys', $outline['root_metadata_stream_status'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Hidden Outline Root Duplicate Type XMP Title'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'outline-root-duplicate-type-boundary'));
    },
    'keeps rejected outline root Metadata stream payloads out of navigation rows and visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineRootMetadataDuplicateTypeBoundaryPdf): void {
        [$pdf, $xmp] = $outlineRootMetadataDuplicateTypeBoundaryPdf();

        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $rootReview = $navigation['outline_root_review']['metadata_stream_review'] ?? [];

        $t->same(['Root Duplicate Type Metadata Chapter'], array_column($toc, 'title'));
        $t->same([0], array_column($toc, 'page'));
        $t->same(['FitH'], array_column($toc, 'view_mode'));
        $t->same(['Root Duplicate Type Metadata Chapter'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same('outline_root_metadata_stream', $rootReview['source'] ?? null);
        $t->same('root', $rootReview['outline_metadata_scope'] ?? null);
        $t->same('rejected_duplicate_metadata_stream_type_keys', $rootReview['status'] ?? null);
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same('Outline root metadata duplicate Type visible body', $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, $xmp));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Hidden Outline Root Duplicate Type XMP Title'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'outline-root-duplicate-type-boundary'));
        $t->true(!str_contains($plainText, 'Root Duplicate Type Metadata Chapter'));
        $t->true(!str_contains($plainText, 'Hidden Outline Root Duplicate Type XMP Title'));
        $t->true(!str_contains($plainText, 'outline-root-duplicate-type-boundary'));
    },
];
