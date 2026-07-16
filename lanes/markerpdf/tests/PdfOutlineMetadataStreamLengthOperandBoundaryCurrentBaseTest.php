<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataLengthBoundaryXmp = static function (string $hiddenTitle): string {
    return '<?xpacket begin=""?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($hiddenTitle, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
};

$outlineItemMetadataLengthBoundaryPdf = static function () use ($outlineMetadataLengthBoundaryXmp): array {
    $hiddenTitle = 'Hidden Outline Length Operand Metadata Payload';
    $xmp = $outlineMetadataLengthBoundaryXmp($hiddenTitle);
    $compressedXmp = gzcompress($xmp);
    if (!is_string($compressedXmp)) {
        throw new RuntimeException('Unable to compress outline item length-boundary metadata payload.');
    }

    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Outline metadata length operand visible body) Tj ET';
    $declaredLength = strlen($compressedXmp);
    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Length Operand Boundary Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length 9 0 R >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
        . "9 0 obj\n{$declaredLength} 10 0 R\nendobj\n"
        . "10 0 obj\n<< /S /JavaScript /JS (app.alert\\('outline metadata length operand helper tail'\\)) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $xmp, $hiddenTitle, $declaredLength];
};

$outlineRootMetadataLengthBoundaryPdf = static function () use ($outlineMetadataLengthBoundaryXmp): array {
    $hiddenTitle = 'Hidden Outline Root Length Operand Metadata Payload';
    $xmp = $outlineMetadataLengthBoundaryXmp($hiddenTitle);
    $compressedXmp = gzcompress($xmp);
    if (!is_string($compressedXmp)) {
        throw new RuntimeException('Unable to compress outline root length-boundary metadata payload.');
    }

    $visibleContent = 'BT /F1 12 Tf 72 720 Td (Outline root metadata length operand visible body) Tj ET';
    $declaredLength = strlen($compressedXmp);
    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /Metadata 8 0 R /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title (Root Length Boundary Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] >>\nendobj\n"
        . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length {$declaredLength} 10 0 R >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
        . "10 0 obj\n<< /S /JavaScript /JS (app.alert\\('outline root metadata length operand tail'\\)) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $xmp, $hiddenTitle, $declaredLength];
};

return [
    'rejects outline item Metadata streams with indirect Length helpers that carry extra operands' => static function (
        TestRunner $t
    ) use ($outlineItemMetadataLengthBoundaryPdf): void {
        [$pdf, $xmp, $hiddenTitle, $declaredLength] = $outlineItemMetadataLengthBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $outlineExtractor = new PdfOutlineExtractor();
        $navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
        $toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $item = $outline['items'][0] ?? [];
        $review = $item['metadata_stream_review'] ?? [];
        $navigationReview = $navigation['outline'][0]['metadata_stream_review'] ?? [];
        $lengthOperand = $review['length_operand'] ?? [];
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(['Length Operand Boundary Chapter'], $outline['titles'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same(['Length Operand Boundary Chapter'], array_column($toc, 'title'));
        $t->same(['Length Operand Boundary Chapter'], array_column($navigation['outline'] ?? [], 'title'));
        $t->same(true, $item['destination_resolved'] ?? null);
        $t->same('outline_item_metadata_stream', $review['source'] ?? null);
        $t->same('rejected_malformed_metadata_stream_length_operand', $review['status'] ?? null);
        $t->same('single_non_negative_integer', $review['length_operand_boundary'] ?? null);
        $t->same(true, $review['length_operand_boundary_rejected'] ?? null);
        $t->same('reject_malformed_length_operands', $review['length_operand_policy'] ?? null);
        $t->same(1, $review['invalid_length_operand_count'] ?? null);
        $t->same(1, $review['extra_length_operand_count'] ?? null);
        $t->same(0, $review['malformed_length_operand_count'] ?? null);
        $t->same(0, $review['dictionary_length_operand_count'] ?? null);
        $t->same(0, $review['unresolved_length_operand_count'] ?? null);
        $t->same(0, $review['negative_length_operand_count'] ?? null);
        $t->same(true, $review['review_only'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(false, $review['visible_text_source'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(true, $review['metadata_reference_resolved'] ?? null);
        $t->same(true, $review['has_stream'] ?? null);
        $t->same(false, $review['native_metadata_decode'] ?? null);
        $t->same(8, $review['object_number'] ?? null);
        $t->same(0, $review['object_generation'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same('Length', $lengthOperand['name'] ?? null);
        $t->same('indirect', $lengthOperand['kind'] ?? null);
        $t->same(9, $lengthOperand['object_number'] ?? null);
        $t->same(0, $lengthOperand['generation'] ?? null);
        $t->same(true, $lengthOperand['resolved'] ?? null);
        $t->same($declaredLength . ' 10 0 R', $lengthOperand['value_preview'] ?? null);
        $t->same($declaredLength, $lengthOperand['length'] ?? null);
        $t->same(false, $lengthOperand['valid_length_operand'] ?? null);
        $t->same(true, $lengthOperand['extra_length_operand'] ?? null);
        $t->same('indirect_reference', $lengthOperand['extra_length_operand_type'] ?? null);
        $t->same('10 0 R', $lengthOperand['extra_length_operand_preview'] ?? null);
        $t->same($review['status'] ?? null, $navigationReview['status'] ?? null);
        $t->same($review['length_operand_boundary'] ?? null, $navigationReview['length_operand_boundary'] ?? null);
        $t->true(!array_key_exists('bytes', $review));
        $t->true(!array_key_exists('sha256', $review));
        $t->same('Outline metadata length operand visible body', $plainText);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $xmp));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $hiddenTitle));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'outline metadata length operand helper tail'));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $xmp));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $hiddenTitle));
        $t->true(!str_contains($plainText, $hiddenTitle));
        $t->true(!str_contains($plainText, 'outline metadata length operand helper tail'));
    },
    'rejects outline root Metadata streams with direct Length operands that carry trailing references' => static function (
        TestRunner $t
    ) use ($outlineRootMetadataLengthBoundaryPdf): void {
        [$pdf, $xmp, $hiddenTitle, $declaredLength] = $outlineRootMetadataLengthBoundaryPdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $review = $outline['metadata_stream_review'] ?? [];
        $navigationRoot = $navigation['outline_root_review'] ?? [];
        $navigationReview = $navigationRoot['metadata_stream_review'] ?? [];
        $lengthOperand = $review['length_operand'] ?? [];
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(['Root Length Boundary Chapter'], $outline['titles'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->true(in_array('outline_root_review', $navigation['source'] ?? [], true));
        $t->same('outline_root_review', $navigationRoot['source'] ?? null);
        $t->same(5, $navigationRoot['outline_root_object'] ?? null);
        $t->same('outline_root_metadata_stream', $review['source'] ?? null);
        $t->same('rejected_malformed_metadata_stream_length_operand', $review['status'] ?? null);
        $t->same('single_non_negative_integer', $review['length_operand_boundary'] ?? null);
        $t->same(true, $review['length_operand_boundary_rejected'] ?? null);
        $t->same('reject_malformed_length_operands', $review['length_operand_policy'] ?? null);
        $t->same(1, $review['invalid_length_operand_count'] ?? null);
        $t->same(1, $review['extra_length_operand_count'] ?? null);
        $t->same(0, $review['malformed_length_operand_count'] ?? null);
        $t->same(0, $review['dictionary_length_operand_count'] ?? null);
        $t->same(0, $review['unresolved_length_operand_count'] ?? null);
        $t->same(0, $review['negative_length_operand_count'] ?? null);
        $t->same(true, $review['review_only'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(false, $review['visible_text_source'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(true, $review['metadata_reference_resolved'] ?? null);
        $t->same(true, $review['has_stream'] ?? null);
        $t->same(false, $review['native_metadata_decode'] ?? null);
        $t->same(8, $review['object_number'] ?? null);
        $t->same(0, $review['object_generation'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same('Length', $lengthOperand['name'] ?? null);
        $t->same('direct', $lengthOperand['kind'] ?? null);
        $t->same($declaredLength, $lengthOperand['value'] ?? null);
        $t->same($declaredLength, $lengthOperand['length'] ?? null);
        $t->same(false, $lengthOperand['valid_length_operand'] ?? null);
        $t->same(true, $lengthOperand['extra_length_operand'] ?? null);
        $t->same('indirect_reference', $lengthOperand['extra_length_operand_type'] ?? null);
        $t->same('10 0 R', $lengthOperand['extra_length_operand_preview'] ?? null);
        $t->same($review['source'] ?? null, $navigationReview['source'] ?? null);
        $t->same($review['status'] ?? null, $navigationReview['status'] ?? null);
        $t->same($review['length_operand_boundary'] ?? null, $navigationReview['length_operand_boundary'] ?? null);
        $t->true(!array_key_exists('bytes', $review));
        $t->true(!array_key_exists('sha256', $review));
        $t->true(!array_key_exists('bytes', $navigationReview));
        $t->true(!array_key_exists('sha256', $navigationReview));
        $t->same('Outline root metadata length operand visible body', $plainText);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $xmp));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $hiddenTitle));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'outline root metadata length operand tail'));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $xmp));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $hiddenTitle));
        $t->true(!str_contains($plainText, $hiddenTitle));
        $t->true(!str_contains($plainText, 'outline root metadata length operand tail'));
    },
];
