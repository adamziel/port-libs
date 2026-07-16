<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataKeyOperandBoundaryPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Outline metadata key operand body) Tj ET';
    $rootPayload = '<outline-metadata>Root key-like operand payload must stay review only</outline-metadata>';
    $itemPayload = '<outline-metadata>Item key-like operand payload must stay review only</outline-metadata>';
    $rootStream = gzcompress($rootPayload);
    $itemStream = gzcompress($itemPayload);
    if (!is_string($rootStream) || !is_string($itemStream)) {
        throw new RuntimeException('Unable to compress outline metadata key-operand payloads.');
    }

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /Metadata 8 0 R /Private /A 20 0 R /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Key Operand Metadata Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 9 0 R /Private /AA 21 0 R /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Clean Key Operand Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [3 0 R /Fit] >>\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($rootStream) . " >>\nstream\n{$rootStream}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($itemStream) . " >>\nstream\n{$itemStream}\nendstream\nendobj\n"
        . "20 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden root key operand action'\\)) >>\nendobj\n"
        . "21 0 obj\n<< /S /URI /URI (https://example.com/hidden-item-key-operand) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $rootPayload, $itemPayload];
};

return [
    'rejects outline Metadata streams followed by malformed key-like operands' => static function (
        TestRunner $t
    ) use ($outlineMetadataKeyOperandBoundaryPdf): void {
        [$pdf, $rootPayload, $itemPayload] = $outlineMetadataKeyOperandBoundaryPdf();
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
        $t->same(7, $outline['last_item_object'] ?? null);
        $t->same(2, $outline['item_count'] ?? null);
        $t->same(2, $outline['resolved_destination_count'] ?? null);
        $t->same(['Key Operand Metadata Chapter', 'Clean Key Operand Appendix'], $outline['titles'] ?? []);

        $t->same('outline_root_metadata_stream', $rootReview['source'] ?? null);
        $t->same('rejected_malformed_outline_root_metadata_operand', $rootReview['status'] ?? null);
        $t->same(true, $rootReview['review_only'] ?? null);
        $t->same(false, $rootReview['payload_included'] ?? null);
        $t->same(false, $rootReview['visible_text_source'] ?? null);
        $t->same(false, $rootReview['accepted_as_document_xmp'] ?? null);
        $t->same(1, $rootReview['metadata_entry_count'] ?? null);
        $t->same(4, $rootReview['metadata_operand_count'] ?? null);
        $t->same(8, $rootReview['object_number'] ?? null);
        $t->same(0, $rootReview['object_generation'] ?? null);
        $t->same('indirect_reference', $rootReview['operand_shape'] ?? null);
        $t->same([20], $rootReview['trailing_reference_object_numbers'] ?? null);
        $t->same(['name', 'indirect_reference'], $rootReview['trailing_operand_shapes'] ?? null);
        $t->same(['Private', 'A'], $rootReview['trailing_operand_names'] ?? null);
        $t->true(!array_key_exists('bytes', $rootReview));
        $t->true(!array_key_exists('sha256', $rootReview));

        $t->same('outline_item_metadata_stream', $itemReview['source'] ?? null);
        $t->same('rejected_malformed_outline_item_metadata_operand', $itemReview['status'] ?? null);
        $t->same(true, $itemReview['review_only'] ?? null);
        $t->same(false, $itemReview['payload_included'] ?? null);
        $t->same(false, $itemReview['visible_text_source'] ?? null);
        $t->same(false, $itemReview['accepted_as_document_xmp'] ?? null);
        $t->same(1, $itemReview['metadata_entry_count'] ?? null);
        $t->same(4, $itemReview['metadata_operand_count'] ?? null);
        $t->same(9, $itemReview['object_number'] ?? null);
        $t->same(0, $itemReview['object_generation'] ?? null);
        $t->same('indirect_reference', $itemReview['operand_shape'] ?? null);
        $t->same([21], $itemReview['trailing_reference_object_numbers'] ?? null);
        $t->same(['name', 'indirect_reference'], $itemReview['trailing_operand_shapes'] ?? null);
        $t->same(['Private', 'AA'], $itemReview['trailing_operand_names'] ?? null);
        $t->true(!array_key_exists('bytes', $itemReview));
        $t->true(!array_key_exists('sha256', $itemReview));
        $t->true(!isset($items[1]['metadata_stream_review']));

        foreach ([$rootPayload, $itemPayload] as $payload) {
            $t->true(is_string($encoded) && !str_contains($encoded, $payload));
        }
        $t->true(is_string($encoded) && !str_contains($encoded, 'reviewed_outline_root_metadata_stream'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'reviewed_outline_item_metadata_stream'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'hidden root key operand action'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'hidden-item-key-operand'));
    },
    'keeps rejected key-operand outline Metadata out of navigation and WordPress text' => static function (
        TestRunner $t
    ) use ($outlineMetadataKeyOperandBoundaryPdf): void {
        [$pdf, $rootPayload, $itemPayload] = $outlineMetadataKeyOperandBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);
        $outlineRows = $navigation['outline'] ?? [];

        $t->same(['Key Operand Metadata Chapter', 'Clean Key Operand Appendix'], array_column($toc, 'title'));
        $t->same([0, 0], array_column($toc, 'page'));
        $t->same(['FitH', 'Fit'], array_column($toc, 'view_mode'));
        $t->same(['Key Operand Metadata Chapter', 'Clean Key Operand Appendix'], array_column($outlineRows, 'title'));
        $t->same('rejected_malformed_outline_item_metadata_operand', $outlineRows[0]['metadata_stream_review']['status'] ?? null);
        $t->same('Outline metadata key operand body', $plainText);
        foreach ([$rootPayload, $itemPayload] as $payload) {
            $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, $payload));
            $t->true(!str_contains($plainText, $payload));
        }
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'hidden root key operand action'));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, 'hidden-item-key-operand'));
        $t->true(!str_contains($plainText, 'Key Operand Metadata Chapter'));
        $t->true(!str_contains($plainText, 'Clean Key Operand Appendix'));
    },
];
