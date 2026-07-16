<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineRootMetadataStreamBoundaryPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Outline root metadata stream visible body) Tj ET';
    $rootMetadataPayload = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Hidden Outline Root Metadata Payload</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
    $rootMetadataStream = gzcompress($rootMetadataPayload);
    if (!is_string($rootMetadataStream)) {
        throw new RuntimeException('Unable to compress outline root metadata stream payload.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Metadata 8 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Title (Root Metadata Stream Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] >>\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootMetadataStream) . " >>\nstream\n{$rootMetadataStream}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $rootMetadataPayload];
};

$outlineRootMetadataFallbackBoundaryPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Lightweight root metadata fallback visible body) Tj ET';
    $metadataPayload = 'BT /F1 12 Tf 72 720 Td (Outline root metadata fallback payload must stay hidden) Tj ET';
    $metadataStream = gzcompress($metadataPayload);
    if (!is_string($metadataStream)) {
        throw new RuntimeException('Unable to compress lightweight outline root metadata stream payload.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Metadata 8 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Title (Lightweight Root Metadata Chapter) /Parent 5 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataStream) . " >>\nstream\n{$metadataStream}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $metadataPayload];
};

$outlineRootDuplicateMetadataFallbackBoundaryPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Duplicate root metadata fallback visible body) Tj ET';
    $firstMetadataPayload = 'BT /F1 12 Tf 72 720 Td (Unselected duplicate outline root metadata payload must stay hidden) Tj ET';
    $selectedMetadataPayload = 'BT /F1 12 Tf 72 720 Td (Selected duplicate outline root metadata payload must stay hidden) Tj ET';
    $firstMetadataStream = gzcompress($firstMetadataPayload);
    $selectedMetadataStream = gzcompress($selectedMetadataPayload);
    if (!is_string($firstMetadataStream) || !is_string($selectedMetadataStream)) {
        throw new RuntimeException('Unable to compress duplicate outline root metadata stream payloads.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Metadata 8 0 R /Metadata 9 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Title (Duplicate Root Metadata Fallback) /Parent 5 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($firstMetadataStream) . " >>\nstream\n{$firstMetadataStream}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($selectedMetadataStream) . " >>\nstream\n{$selectedMetadataStream}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $firstMetadataPayload, $selectedMetadataPayload];
};

return [
    'records outline root Metadata streams as review-only document outline metadata' => static function (
        TestRunner $t
    ) use ($outlineRootMetadataStreamBoundaryPdf): void {
        [$pdf, $rootMetadataPayload] = $outlineRootMetadataStreamBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $review = $outline['metadata_stream_review'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->true(!array_key_exists('title', $metadata), 'Outline-root metadata must not be promoted to document XMP title.');
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(6, $outline['last_item_object'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(['Root Metadata Stream Chapter'], $outline['titles'] ?? []);

        $t->same('outline_root_metadata_stream', $review['source'] ?? null);
        $t->same(true, $review['review_only'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(false, $review['visible_text_source'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same('reviewed_outline_root_metadata_stream', $review['status'] ?? null);
        $t->same(8, $review['object_number'] ?? null);
        $t->same(0, $review['object_generation'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($rootMetadataPayload), $review['bytes'] ?? null);
        $t->same(hash('sha256', $rootMetadataPayload), $review['sha256'] ?? null);
        $t->same(['title'], $review['xmp_summary']['field_names'] ?? null);
        $t->same(true, $review['xmp_summary']['text_values_redacted'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, $rootMetadataPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Hidden Outline Root Metadata Payload'));
    },
    'keeps outline root Metadata streams out of navigation rows and visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineRootMetadataStreamBoundaryPdf): void {
        [$pdf, $rootMetadataPayload] = $outlineRootMetadataStreamBoundaryPdf();

        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['Root Metadata Stream Chapter'], array_column($toc, 'title'));
        $t->same([0], array_column($toc, 'page'));
        $t->same(['Root Metadata Stream Chapter'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same('Outline root metadata stream visible body', $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, $rootMetadataPayload));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Hidden Outline Root Metadata Payload'));
        $t->true(!str_contains($plainText, 'Root Metadata Stream Chapter'));
        $t->true(!str_contains($plainText, 'Hidden Outline Root Metadata Payload'));
    },
    'excludes outline root Metadata streams from lightweight fallback WordPress text' => static function (
        TestRunner $t
    ) use ($outlineRootMetadataFallbackBoundaryPdf): void {
        [$pdf, $metadataPayload] = $outlineRootMetadataFallbackBoundaryPdf();

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $lightweight = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
        $encoded = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

        $t->same('Lightweight root metadata fallback visible body', $plainText);
        $t->same([], array_column($lightweight['pdf_toc'] ?? [], 'title'));
        $t->true(is_string($encoded) && !str_contains($encoded, $metadataPayload));
        $t->true(!str_contains($plainText, 'Lightweight Root Metadata Chapter'));
        $t->true(!str_contains($plainText, 'Outline root metadata fallback payload must stay hidden'));
    },
    'excludes every duplicate outline root Metadata stream from lightweight fallback WordPress text' => static function (
        TestRunner $t
    ) use ($outlineRootDuplicateMetadataFallbackBoundaryPdf): void {
        [$pdf, $firstMetadataPayload, $selectedMetadataPayload] = $outlineRootDuplicateMetadataFallbackBoundaryPdf();

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $lightweight = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $review = $outline['metadata_stream_review'] ?? [];
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

        $t->same('Duplicate root metadata fallback visible body', $plainText);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(['Duplicate Root Metadata Fallback'], $outline['titles'] ?? []);
        $t->same('outline_root_metadata_stream', $review['source'] ?? null);
        $t->same('reviewed_outline_root_metadata_stream', $review['status'] ?? null);
        $t->same(2, $review['declared_entry_count'] ?? null);
        $t->same(true, $review['duplicate_entries'] ?? null);
        $t->same(1, $review['selected_entry_index'] ?? null);
        $t->same(9, $review['object_number'] ?? null);
        $t->same(0, $review['object_generation'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($selectedMetadataPayload), $review['bytes'] ?? null);
        $t->same(hash('sha256', $selectedMetadataPayload), $review['sha256'] ?? null);
        $t->same([], array_column($lightweight['pdf_toc'] ?? [], 'title'));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $firstMetadataPayload));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $selectedMetadataPayload));
        $t->true(is_string($encodedLightweight) && !str_contains($encodedLightweight, $firstMetadataPayload));
        $t->true(is_string($encodedLightweight) && !str_contains($encodedLightweight, $selectedMetadataPayload));
        $t->true(!str_contains($plainText, 'Duplicate Root Metadata Fallback'));
        $t->true(!str_contains($plainText, 'Unselected duplicate outline root metadata payload must stay hidden'));
        $t->true(!str_contains($plainText, 'Selected duplicate outline root metadata payload must stay hidden'));
    },
];
