<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataDuplicateKeyBoundaryPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Duplicate outline metadata boundary body) Tj ET';
    $unselectedPayload = '<outline-metadata review="unselected">Unselected outline metadata stream should stay review only</outline-metadata>';
    $selectedPayload = '<outline-metadata review="selected">Selected outline metadata stream boundary</outline-metadata>';
    $decoyPayload = '<outline-metadata review="nested-decoy">Nested outline metadata decoy</outline-metadata>';

    $unselectedStream = gzcompress($unselectedPayload);
    $selectedStream = gzcompress($selectedPayload);
    $decoyStream = gzcompress($decoyPayload);
    if (!is_string($unselectedStream) || !is_string($selectedStream) || !is_string($decoyStream)) {
        throw new RuntimeException('Unable to compress duplicate outline metadata streams.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Duplicate Metadata Boundary Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Note (/Metadata 10 0 R literal decoy) /Private << /Metadata 10 0 R >> /Metadata 8 0 R /C [0 .4 .8] /Metadata 9 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($unselectedStream) . " >>\nstream\n{$unselectedStream}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($selectedStream) . " >>\nstream\n{$selectedStream}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($decoyStream) . " >>\nstream\n{$decoyStream}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $selectedPayload, $unselectedPayload, $decoyPayload];
};

return [
    'records selected outline Metadata duplicate-key provenance without exposing payloads' => static function (
        TestRunner $t
    ) use ($outlineMetadataDuplicateKeyBoundaryPdf): void {
        [$pdf, $selectedPayload, $unselectedPayload, $decoyPayload] = $outlineMetadataDuplicateKeyBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $item = $outline['items'][0] ?? [];
        $review = $item['metadata_stream_review'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(0, $outline['unresolved_destination_count'] ?? null);
        $t->same(['Duplicate Metadata Boundary Chapter'], $outline['titles'] ?? []);

        $t->same('Duplicate Metadata Boundary Chapter', $item['title'] ?? null);
        $t->same(6, $item['outline_object'] ?? null);
        $t->same(0, $item['page'] ?? null);
        $t->same(3, $item['page_object'] ?? null);
        $t->same('FitH', $item['view_mode'] ?? null);
        $t->same('#0066cc', $item['text_color_hex'] ?? null);

        $t->same('outline_item_metadata_stream', $review['source'] ?? null);
        $t->same(true, $review['review_only'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(false, $review['visible_text_source'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same('reviewed_outline_item_metadata_stream', $review['status'] ?? null);
        $t->same(2, $review['declared_entry_count'] ?? null);
        $t->same(true, $review['duplicate_entries'] ?? null);
        $t->same(1, $review['selected_entry_index'] ?? null);
        $t->same(9, $review['object_number'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($selectedPayload), $review['bytes'] ?? null);
        $t->same(hash('sha256', $selectedPayload), $review['sha256'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, $selectedPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $unselectedPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $decoyPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Unselected outline metadata stream should stay review only'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Nested outline metadata decoy'));
    },
    'keeps duplicate outline Metadata streams out of TOC navigation and visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineMetadataDuplicateKeyBoundaryPdf): void {
        [$pdf] = $outlineMetadataDuplicateKeyBoundaryPdf();

        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['Duplicate Metadata Boundary Chapter'], array_column($toc, 'title'));
        $t->same([0], array_column($toc, 'page'));
        $t->same(['FitH'], array_column($toc, 'view_mode'));
        $t->same(['Duplicate Metadata Boundary Chapter'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same([6], array_column($navigation['outline'] ?? [], 'outline_object'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same('Duplicate outline metadata boundary body', $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Selected outline metadata stream boundary'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Unselected outline metadata stream should stay review only'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'Nested outline metadata decoy'));
        $t->true(!str_contains($plainText, 'Duplicate Metadata Boundary Chapter'));
        $t->true(!str_contains($plainText, 'Selected outline metadata stream boundary'));
        $t->true(!str_contains($plainText, 'Unselected outline metadata stream should stay review only'));
        $t->true(!str_contains($plainText, 'Nested outline metadata decoy'));
    },
];
