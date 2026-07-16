<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataStreamGenerationBoundaryPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Outline metadata generation boundary body) Tj ET';
    $currentPayload = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Outline Metadata Generation Payload</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
    $stalePayload = str_replace('Current Outline Metadata Generation Payload', 'Stale Outline Metadata Generation Payload', $currentPayload);
    $currentStream = gzcompress($currentPayload);
    $staleStream = gzcompress($stalePayload);
    if (!is_string($currentStream) || !is_string($staleStream)) {
        throw new RuntimeException('Unable to compress outline metadata generation streams.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Exact Generation Metadata Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 9 1 R /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Stale Generation Metadata Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [3 0 R /FitH 640] /Metadata 9 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($staleStream) . " >>\nstream\n{$staleStream}\nendstream\nendobj\n"
        . "9 1 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($currentStream) . " >>\nstream\n{$currentStream}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $currentPayload, $stalePayload];
};

return [
    'records exact outline Metadata stream generation and rejects stale-generation references' => static function (
        TestRunner $t
    ) use ($outlineMetadataStreamGenerationBoundaryPdf): void {
        [$pdf, $currentPayload, $stalePayload] = $outlineMetadataStreamGenerationBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $items = $metadata['document_outline']['items'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $metadata['document_outline']['source'] ?? null);
        $t->same(2, $metadata['document_outline']['item_count'] ?? null);
        $t->same(2, $metadata['document_outline']['resolved_destination_count'] ?? null);
        $t->same([
            'Exact Generation Metadata Chapter',
            'Stale Generation Metadata Appendix',
        ], array_column($items, 'title'));
        $t->same([0, 0], array_column($items, 'page'));
        $t->same(['FitH', 'FitH'], array_column($items, 'view_mode'));

        $currentReview = $items[0]['metadata_stream_review'] ?? [];
        $t->same('outline_item_metadata_stream', $currentReview['source'] ?? null);
        $t->same('reviewed_outline_item_metadata_stream', $currentReview['status'] ?? null);
        $t->same(9, $currentReview['object_number'] ?? null);
        $t->same(1, $currentReview['object_generation'] ?? null);
        $t->same('Metadata', $currentReview['type'] ?? null);
        $t->same('XML', $currentReview['subtype'] ?? null);
        $t->same(['FlateDecode'], $currentReview['filters'] ?? null);
        $t->same(strlen($currentPayload), $currentReview['bytes'] ?? null);
        $t->same(hash('sha256', $currentPayload), $currentReview['sha256'] ?? null);
        $t->same(['title'], $currentReview['xmp_summary']['field_names'] ?? null);
        $t->same(true, $currentReview['review_only'] ?? null);
        $t->same(false, $currentReview['payload_included'] ?? null);
        $t->same(false, $currentReview['accepted_as_document_xmp'] ?? null);

        $staleReview = $items[1]['metadata_stream_review'] ?? [];
        $t->same('outline_item_metadata_stream', $staleReview['source'] ?? null);
        $t->same('unresolved_metadata_reference', $staleReview['status'] ?? null);
        $t->same(9, $staleReview['object_number'] ?? null);
        $t->same(0, $staleReview['object_generation'] ?? null);
        $t->same(false, $staleReview['metadata_reference_resolved'] ?? null);
        $t->same(true, $staleReview['indirect_reference_required'] ?? null);
        $t->same(true, $staleReview['review_only'] ?? null);
        $t->same(false, $staleReview['payload_included'] ?? null);
        $t->same(false, $staleReview['accepted_as_document_xmp'] ?? null);

        $t->true(is_string($encoded) && !str_contains($encoded, $currentPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $stalePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Current Outline Metadata Generation Payload'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Outline Metadata Generation Payload'));
    },
    'keeps outline Metadata generation payloads out of TOC navigation and visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineMetadataStreamGenerationBoundaryPdf): void {
        [$pdf] = $outlineMetadataStreamGenerationBoundaryPdf();

        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $expectedTitles = [
            'Exact Generation Metadata Chapter',
            'Stale Generation Metadata Appendix',
        ];
        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same([0, 0], array_column($toc, 'page'));
        $t->same(['FitH', 'FitH'], array_column($toc, 'view_mode'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same([6, 7], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same('Outline metadata generation boundary body', $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Current Outline Metadata Generation Payload'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Stale Outline Metadata Generation Payload'));
        $t->true(!str_contains($plainText, 'Exact Generation Metadata Chapter'));
        $t->true(!str_contains($plainText, 'Stale Generation Metadata Appendix'));
        $t->true(!str_contains($plainText, 'Current Outline Metadata Generation Payload'));
        $t->true(!str_contains($plainText, 'Stale Outline Metadata Generation Payload'));
    },
];
