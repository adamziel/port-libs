<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataOperandBoundaryPdf = static function (): array {
    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Outline metadata operand boundary body) Tj ET';
    $arrayPayload = '<outline-metadata>Array operand payload should stay review only</outline-metadata>';
    $unusedPayload = '<outline-metadata>Unused array metadata stream should stay hidden</outline-metadata>';
    $arrayStream = gzcompress($arrayPayload);
    $unusedStream = gzcompress($unusedPayload);
    if (!is_string($arrayStream) || !is_string($unusedStream)) {
        throw new RuntimeException('Unable to compress outline metadata operand boundary payloads.');
    }

    $directPayload = 'Direct outline Metadata dictionary payload should stay review only';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Array Metadata Operand Chapter) /Parent 5 0 R /Dest [3 0 R /Fit] /Metadata [8 0 R 9 0 R] /Next 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Title (Dictionary Metadata Operand Appendix) /Parent 5 0 R /Prev 6 0 R /Dest [3 0 R /FitH 640] /Metadata << /Type /Metadata /Subtype /XML /Note ({$directPayload}) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($arrayStream) . " >>\nstream\n{$arrayStream}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($unusedStream) . " >>\nstream\n{$unusedStream}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $arrayPayload, $unusedPayload, $directPayload];
};

return [
    'rejects non-scalar outline Metadata operands before document metadata promotion' => static function (
        TestRunner $t
    ) use ($outlineMetadataOperandBoundaryPdf): void {
        [$pdf, $arrayPayload, $unusedPayload, $directPayload] = $outlineMetadataOperandBoundaryPdf();
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
        $t->same(['Array Metadata Operand Chapter', 'Dictionary Metadata Operand Appendix'], $outline['titles'] ?? []);

        $arrayReview = $items[0]['metadata_stream_review'] ?? [];
        $t->same('outline_item_metadata_stream', $arrayReview['source'] ?? null);
        $t->same('rejected_non_indirect_metadata_reference', $arrayReview['status'] ?? null);
        $t->same('array', $arrayReview['operand_shape'] ?? null);
        $t->same(true, $arrayReview['indirect_reference_required'] ?? null);
        $t->same(true, $arrayReview['review_only'] ?? null);
        $t->same(false, $arrayReview['payload_included'] ?? null);
        $t->same(false, $arrayReview['visible_text_source'] ?? null);
        $t->same(false, $arrayReview['accepted_as_document_xmp'] ?? null);
        $t->true(!array_key_exists('object_number', $arrayReview));

        $dictionaryReview = $items[1]['metadata_stream_review'] ?? [];
        $t->same('outline_item_metadata_stream', $dictionaryReview['source'] ?? null);
        $t->same('rejected_non_indirect_metadata_reference', $dictionaryReview['status'] ?? null);
        $t->same('dictionary', $dictionaryReview['operand_shape'] ?? null);
        $t->same(true, $dictionaryReview['indirect_reference_required'] ?? null);
        $t->same(true, $dictionaryReview['review_only'] ?? null);
        $t->same(false, $dictionaryReview['payload_included'] ?? null);
        $t->same(false, $dictionaryReview['visible_text_source'] ?? null);
        $t->same(false, $dictionaryReview['accepted_as_document_xmp'] ?? null);
        $t->true(!array_key_exists('object_number', $dictionaryReview));

        $t->true(is_string($encoded) && !str_contains($encoded, $arrayPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $unusedPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $directPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Array operand payload should stay review only'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Unused array metadata stream should stay hidden'));
    },
    'keeps rejected outline Metadata operands out of TOC navigation and visible WordPress text' => static function (
        TestRunner $t
    ) use ($outlineMetadataOperandBoundaryPdf): void {
        [$pdf, $arrayPayload, $unusedPayload, $directPayload] = $outlineMetadataOperandBoundaryPdf();
        $outlineExtractor = new PdfOutlineExtractor();
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $expectedTitles = ['Array Metadata Operand Chapter', 'Dictionary Metadata Operand Appendix'];
        $t->same($expectedTitles, array_column($toc, 'title'));
        $t->same($expectedTitles, array_column($navigation['outline'] ?? [], 'title'));
        $t->same([0, 0], array_column($toc, 'page'));
        $t->same(['Fit', 'FitH'], array_column($toc, 'view_mode'));
        $t->same([], $navigation['outline_action_review_actions']);
        $t->same('Outline metadata operand boundary body', $plainText);
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, $arrayPayload));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, $unusedPayload));
        $t->true(is_string($navigationEncoded) && !str_contains($navigationEncoded, $directPayload));
        $t->true(!str_contains($plainText, 'Array Metadata Operand Chapter'));
        $t->true(!str_contains($plainText, 'Dictionary Metadata Operand Appendix'));
        $t->true(!str_contains($plainText, 'Array operand payload should stay review only'));
        $t->true(!str_contains($plainText, 'Unused array metadata stream should stay hidden'));
        $t->true(!str_contains($plainText, $directPayload));
    },
];
