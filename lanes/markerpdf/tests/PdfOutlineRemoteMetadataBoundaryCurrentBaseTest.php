<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineRemoteMetadataBoundaryPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Remote outline metadata boundary body) Tj ET';
    $metadataPayload = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Hidden Remote Outline Metadata XMP</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
    $metadataStream = gzcompress($metadataPayload);
    if (!is_string($metadataStream)) {
        throw new RuntimeException('Unable to compress remote outline metadata stream payload.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Remote Metadata GoToR Review) /Parent 5 0 R /A 9 0 R /Metadata 8 0 R /C [0 .1 .7] /F 2 >>\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataStream) . " >>\nstream\n{$metadataStream}\nendstream\nendobj\n"
        . "9 0 obj\n<< /S /GoToR /F (remote-outline-review.pdf) /D (RemoteChapter) /NewWindow false >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $metadataPayload];
};

return [
    'keeps remote outline Metadata streams review-only in document metadata' => static function (
        TestRunner $t
    ) use ($outlineRemoteMetadataBoundaryPdf): void {
        [$pdf, $metadataPayload] = $outlineRemoteMetadataBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $item = $outline['items'][0] ?? [];
        $review = $item['metadata_stream_review'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->true(!array_key_exists('title', $metadata), 'Outline-item metadata must not become document XMP title.');
        $t->same('UseOutlines', $metadata['page_mode'] ?? null);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(['Remote Metadata GoToR Review'], $outline['titles'] ?? []);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(0, $outline['resolved_destination_count'] ?? null);
        $t->same(1, $outline['unresolved_destination_count'] ?? null);
        $t->same('Remote Metadata GoToR Review', $item['title'] ?? null);
        $t->same(6, $item['outline_object'] ?? null);
        $t->same('GoToR', $item['action_type'] ?? null);
        $t->same(9, $item['action_object'] ?? null);
        $t->same('#001ab3', $item['text_color_hex'] ?? null);

        $t->same('outline_item_metadata_stream', $review['source'] ?? null);
        $t->same('reviewed_outline_item_metadata_stream', $review['status'] ?? null);
        $t->same(true, $review['review_only'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(false, $review['visible_text_source'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(8, $review['object_number'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($metadataPayload), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataPayload), $review['sha256'] ?? null);
        $t->same(['title'], $review['xmp_summary']['field_names'] ?? null);
        $t->same(true, $review['xmp_summary']['text_values_redacted'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, $metadataPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Hidden Remote Outline Metadata XMP'));
    },
    'carries outline Metadata review onto remote GoToR rows without leaking payload text' => static function (
        TestRunner $t
    ) use ($outlineRemoteMetadataBoundaryPdf): void {
        [$pdf, $metadataPayload] = $outlineRemoteMetadataBoundaryPdf();

        $outlineExtractor = new PdfOutlineExtractor();
        $remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $remoteEncoded = json_encode($remoteActions, JSON_UNESCAPED_SLASHES);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(1, count($remoteActions));
        $t->same('Remote Metadata GoToR Review', $remoteActions[0]['title'] ?? null);
        $t->same(1, $remoteActions[0]['level'] ?? null);
        $t->same(6, $remoteActions[0]['outline_object'] ?? null);
        $t->same('remote-outline-review.pdf', $remoteActions[0]['file'] ?? null);
        $t->same('RemoteChapter', $remoteActions[0]['destination'] ?? null);
        $t->same(null, $remoteActions[0]['page'] ?? null);
        $t->same(false, $remoteActions[0]['new_window'] ?? null);

        $review = $remoteActions[0]['metadata_stream_review'] ?? [];
        $t->same('outline_item_metadata_stream', $review['source'] ?? null);
        $t->same('reviewed_outline_item_metadata_stream', $review['status'] ?? null);
        $t->same(8, $review['object_number'] ?? null);
        $t->same(strlen($metadataPayload), $review['bytes'] ?? null);
        $t->same(hash('sha256', $metadataPayload), $review['sha256'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);

        $actions = $navigation['outline_action_review_actions'] ?? [];
        $t->same(1, count($actions));
        $t->same('GoToR', $actions[0]['action_type'] ?? null);
        $t->same('remote-document-review', $actions[0]['safety'] ?? null);
        $t->same(8, $actions[0]['outline_metadata_stream_review']['object_number'] ?? null);
        $t->same('Remote outline metadata boundary body', $plainText);
        $t->true(is_string($remoteEncoded) && !str_contains($remoteEncoded, $metadataPayload));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, $metadataPayload));
        $t->true(!str_contains($plainText, 'Remote Metadata GoToR Review'));
        $t->true(!str_contains($plainText, 'remote-outline-review.pdf'));
        $t->true(!str_contains($plainText, 'Hidden Remote Outline Metadata XMP'));
    },
];
