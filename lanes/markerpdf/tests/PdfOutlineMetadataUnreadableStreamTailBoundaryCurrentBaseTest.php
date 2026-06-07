<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineUnreadableMetadataStreamTailBoundaryPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Outline unreadable metadata stream tail body) Tj ET';
    $rootPayload = '<?x:xmpmeta>Unreadable outline root metadata tail payload must stay hidden</x:xmpmeta>';
    $itemPayload = '<?x:xmpmeta>Unreadable outline item metadata tail payload must stay hidden</x:xmpmeta>';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Metadata 8 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Title (Unreadable Metadata Stream Tail Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 9 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootPayload) . " >>\nstream\n{$rootPayload}\nendstream /A 12 0 R\nendobj\n"
        . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($itemPayload) . " >>\nstream\n{$itemPayload}\nendstream /A 13 0 R\nendobj\n"
        . "12 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden root metadata stream tail action'\\)) >>\nendobj\n"
        . "13 0 obj\n<< /S /URI /URI (https://example.com/hidden-item-metadata-stream-tail) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $rootPayload, $itemPayload];
};

return [
    'rejects unreadable outline Metadata streams with trailing top-level operands' => static function (
        TestRunner $t
    ) use ($outlineUnreadableMetadataStreamTailBoundaryPdf): void {
        [$pdf, $rootPayload, $itemPayload] = $outlineUnreadableMetadataStreamTailBoundaryPdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $items = $outline['items'] ?? [];
        $rootReview = $outline['metadata_stream_review'] ?? [];
        $itemReview = $items[0]['metadata_stream_review'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(5, $outline['outline_root_object'] ?? null);
        $t->same(6, $outline['first_item_object'] ?? null);
        $t->same(6, $outline['last_item_object'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(['Unreadable Metadata Stream Tail Chapter'], $outline['titles'] ?? []);

        $t->same('outline_root_metadata_stream', $rootReview['source'] ?? null);
        $t->same('rejected_malformed_outline_root_metadata_stream', $rootReview['status'] ?? null);
        $t->same(true, $rootReview['review_only'] ?? null);
        $t->same(false, $rootReview['payload_included'] ?? null);
        $t->same(false, $rootReview['visible_text_source'] ?? null);
        $t->same(false, $rootReview['accepted_as_document_xmp'] ?? null);
        $t->same(8, $rootReview['object_number'] ?? null);
        $t->same(0, $rootReview['object_generation'] ?? null);
        $t->same('Metadata', $rootReview['type'] ?? null);
        $t->same('XML', $rootReview['subtype'] ?? null);
        $t->same(['FlateDecode'], $rootReview['filters'] ?? null);
        $t->same(strlen($rootPayload), $rootReview['declared_length'] ?? null);
        $t->same(true, $rootReview['metadata_reference_resolved'] ?? null);
        $t->same(true, $rootReview['has_stream'] ?? null);
        $t->same(true, $rootReview['stream_tail_operand_rejected'] ?? null);
        $t->same(false, $rootReview['native_metadata_decode'] ?? null);
        $t->true(!array_key_exists('bytes', $rootReview));
        $t->true(!array_key_exists('sha256', $rootReview));

        $t->same('outline_item_metadata_stream', $itemReview['source'] ?? null);
        $t->same('rejected_malformed_outline_item_metadata_stream', $itemReview['status'] ?? null);
        $t->same(true, $itemReview['review_only'] ?? null);
        $t->same(false, $itemReview['payload_included'] ?? null);
        $t->same(false, $itemReview['visible_text_source'] ?? null);
        $t->same(false, $itemReview['accepted_as_document_xmp'] ?? null);
        $t->same(9, $itemReview['object_number'] ?? null);
        $t->same(0, $itemReview['object_generation'] ?? null);
        $t->same('Metadata', $itemReview['type'] ?? null);
        $t->same('XML', $itemReview['subtype'] ?? null);
        $t->same(['FlateDecode'], $itemReview['filters'] ?? null);
        $t->same(strlen($itemPayload), $itemReview['declared_length'] ?? null);
        $t->same(true, $itemReview['metadata_reference_resolved'] ?? null);
        $t->same(true, $itemReview['has_stream'] ?? null);
        $t->same(true, $itemReview['stream_tail_operand_rejected'] ?? null);
        $t->same(false, $itemReview['native_metadata_decode'] ?? null);
        $t->true(!array_key_exists('bytes', $itemReview));
        $t->true(!array_key_exists('sha256', $itemReview));

        foreach ([$rootPayload, $itemPayload] as $payload) {
            $t->true(is_string($encoded) && !str_contains($encoded, $payload));
        }
        $t->true(is_string($encoded) && !str_contains($encoded, 'hidden root metadata stream tail action'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'hidden-item-metadata-stream-tail'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'unreadable_metadata_stream'));
    },
    'keeps unreadable tailed outline Metadata stream payloads out of navigation and text' => static function (
        TestRunner $t
    ) use ($outlineUnreadableMetadataStreamTailBoundaryPdf): void {
        [$pdf, $rootPayload, $itemPayload] = $outlineUnreadableMetadataStreamTailBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $outlineRows = $navigation['outline'] ?? [];

        $t->same(['Unreadable Metadata Stream Tail Chapter'], array_column($toc, 'title'));
        $t->same([0], array_column($toc, 'page'));
        $t->same(['FitH'], array_column($toc, 'view_mode'));
        $t->same(['Unreadable Metadata Stream Tail Chapter'], array_column($outlineRows, 'title'));
        $t->same(
            'rejected_malformed_outline_item_metadata_stream',
            $outlineRows[0]['metadata_stream_review']['status'] ?? null
        );
        $t->same('Outline unreadable metadata stream tail body', $plainText);
        foreach ([$rootPayload, $itemPayload] as $payload) {
            $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, $payload));
            $t->true(!str_contains($plainText, $payload));
        }
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'hidden root metadata stream tail action'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'hidden-item-metadata-stream-tail'));
        $t->true(!str_contains($plainText, 'Unreadable Metadata Stream Tail Chapter'));
        $t->true(!str_contains($plainText, 'hidden root metadata stream tail action'));
        $t->true(!str_contains($plainText, 'hidden-item-metadata-stream-tail'));
    },
];
