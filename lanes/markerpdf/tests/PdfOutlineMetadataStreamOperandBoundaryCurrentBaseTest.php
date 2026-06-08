<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$outlineMetadataStreamOperandBoundaryXmp = static function (string $hiddenTitle): string {
    return '<?xpacket begin=""?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($hiddenTitle, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
};

$outlineMetadataStreamOperandBoundaryPdf = static function (
    string $title,
    string $bodyText,
    string $xmp,
    string $metadataDictionary,
    string $helperObject
): array {
    $compressedXmp = gzcompress($xmp);
    if (!is_string($compressedXmp)) {
        throw new RuntimeException('Unable to compress outline metadata stream operand fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';
    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
        . "6 0 obj\n<< /Title ({$title}) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Metadata 8 0 R /C [0 .2 .4] /F 2 >>\nendobj\n"
        . "8 0 obj\n{$metadataDictionary} /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
        . "9 0 obj\n{$helperObject}\nendobj\n"
        . "10 0 obj\n<< /S /JavaScript /JS (app.alert\\('outline metadata operand helper tail'\\)) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, strlen($compressedXmp)];
};

return [
    'rejects outline item Metadata streams with indirect Filter helpers that carry extra operands' => static function (
        TestRunner $t
    ) use ($outlineMetadataStreamOperandBoundaryXmp, $outlineMetadataStreamOperandBoundaryPdf): void {
        $hiddenTitle = 'Hidden Outline Filter Operand Metadata Payload';
        $xmp = $outlineMetadataStreamOperandBoundaryXmp($hiddenTitle);
        [$pdf, $declaredLength] = $outlineMetadataStreamOperandBoundaryPdf(
            'Filter Operand Boundary Chapter',
            'Outline metadata filter operand visible body',
            $xmp,
            '<< /Type /Metadata /Subtype /XML /Filter 9 0 R',
            "/FlateDecode /Crypt 10 0 R\n"
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $item = $outline['items'][0] ?? [];
        $review = $item['metadata_stream_review'] ?? [];
        $filterOperand = $review['filter_operands'][0] ?? [];
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

        $t->same(['catalog'], $metadata['source']);
        $t->same('catalog_outlines', $outline['source'] ?? null);
        $t->same(['Filter Operand Boundary Chapter'], $outline['titles'] ?? null);
        $t->same(1, $outline['item_count'] ?? null);
        $t->same(1, $outline['resolved_destination_count'] ?? null);
        $t->same('Filter Operand Boundary Chapter', $item['title'] ?? null);
        $t->same('#003366', $item['text_color_hex'] ?? null);
        $t->same(true, $item['destination_resolved'] ?? null);
        $t->same('outline_item_metadata_stream', $review['source'] ?? null);
        $t->same('rejected_malformed_metadata_stream_filter_operand', $review['status'] ?? null);
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
        $t->same($declaredLength, $review['declared_length'] ?? null);
        $t->same('reject_malformed_filter_operands', $review['filter_operand_policy'] ?? null);
        $t->same(1, $review['invalid_filter_operand_count'] ?? null);
        $t->same(1, $review['malformed_filter_operand_count'] ?? null);
        $t->same(0, $review['dictionary_filter_operand_count'] ?? null);
        $t->same(0, $review['unresolved_filter_operand_count'] ?? null);
        $t->same(1, is_countable($review['filter_operands'] ?? null) ? count($review['filter_operands']) : null);
        $t->same('Filter', $filterOperand['name'] ?? null);
        $t->same('indirect', $filterOperand['kind'] ?? null);
        $t->same(9, $filterOperand['object_number'] ?? null);
        $t->same(true, $filterOperand['resolved'] ?? null);
        $t->same('/FlateDecode /Crypt 10 0 R', $filterOperand['value_preview'] ?? null);
        $t->same(false, $filterOperand['valid_filter_operand'] ?? null);
        $t->same(true, $filterOperand['extra_filter_operand'] ?? null);
        $t->same('Crypt', $filterOperand['extra_filter_name'] ?? null);
        $t->true(!array_key_exists('bytes', $review));
        $t->true(!array_key_exists('sha256', $review));
        $t->same('Outline metadata filter operand visible body', $plainText);
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $xmp));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, $hiddenTitle));
        $t->true(is_string($encodedMetadata) && !str_contains($encodedMetadata, 'outline metadata operand helper tail'));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $xmp));
        $t->true(is_string($encodedNavigation) && !str_contains($encodedNavigation, $hiddenTitle));
        $t->true(!str_contains($plainText, $hiddenTitle));
        $t->true(!str_contains($plainText, 'outline metadata operand helper tail'));
    },
    'rejects outline item Metadata streams with indirect DecodeParms helpers that carry extra operands' => static function (
        TestRunner $t
    ) use ($outlineMetadataStreamOperandBoundaryXmp, $outlineMetadataStreamOperandBoundaryPdf): void {
        $hiddenTitle = 'Hidden Outline DecodeParms Operand Metadata Payload';
        $xmp = $outlineMetadataStreamOperandBoundaryXmp($hiddenTitle);
        [$pdf, $declaredLength] = $outlineMetadataStreamOperandBoundaryPdf(
            'DecodeParms Operand Boundary Chapter',
            'Outline metadata decodeparms operand visible body',
            $xmp,
            '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /DecodeParms 9 0 R',
            "<< /Predictor 1 /Columns 1 >> /Crypt 10 0 R\n"
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $item = $outline['items'][0] ?? [];
        $review = $item['metadata_stream_review'] ?? [];
        $decodeParmsOperand = $review['decodeparms_operands'][0] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['DecodeParms Operand Boundary Chapter'], $outline['titles'] ?? null);
        $t->same(true, $item['destination_resolved'] ?? null);
        $t->same('outline_item_metadata_stream', $review['source'] ?? null);
        $t->same('rejected_malformed_metadata_stream_decodeparms_operand', $review['status'] ?? null);
        $t->same(true, $review['review_only'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->same(false, $review['visible_text_source'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(true, $review['metadata_reference_resolved'] ?? null);
        $t->same(true, $review['has_stream'] ?? null);
        $t->same(false, $review['native_metadata_decode'] ?? null);
        $t->same(8, $review['object_number'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same($declaredLength, $review['declared_length'] ?? null);
        $t->same('reject_malformed_decodeparms_operands', $review['decodeparms_operand_policy'] ?? null);
        $t->same(1, $review['invalid_decodeparms_operand_count'] ?? null);
        $t->same(1, $review['malformed_decodeparms_operand_count'] ?? null);
        $t->same(0, $review['non_dictionary_decodeparms_operand_count'] ?? null);
        $t->same(0, $review['unresolved_decodeparms_operand_count'] ?? null);
        $t->same(1, is_countable($review['decodeparms_operands'] ?? null) ? count($review['decodeparms_operands']) : null);
        $t->same('DecodeParms', $decodeParmsOperand['name'] ?? null);
        $t->same('indirect', $decodeParmsOperand['kind'] ?? null);
        $t->same(9, $decodeParmsOperand['object_number'] ?? null);
        $t->same(true, $decodeParmsOperand['resolved'] ?? null);
        $t->same('<< /Predictor 1 /Columns 1 >> /Crypt 10 0 R', $decodeParmsOperand['value_preview'] ?? null);
        $t->same(false, $decodeParmsOperand['valid_decodeparms_operand'] ?? null);
        $t->same(true, $decodeParmsOperand['dictionary_decodeparms_operand'] ?? null);
        $t->same(true, $decodeParmsOperand['extra_decodeparms_operand'] ?? null);
        $t->same('Crypt', $decodeParmsOperand['extra_decodeparms_name'] ?? null);
        $t->true(!array_key_exists('bytes', $review));
        $t->true(!array_key_exists('sha256', $review));
        $t->same('Outline metadata decodeparms operand visible body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, $xmp));
        $t->true(is_string($encoded) && !str_contains($encoded, $hiddenTitle));
        $t->true(!str_contains($plainText, $hiddenTitle));
    },
    'keeps valid outline item Metadata streams review-only after stream operand checks' => static function (
        TestRunner $t
    ) use ($outlineMetadataStreamOperandBoundaryXmp, $outlineMetadataStreamOperandBoundaryPdf): void {
        $hiddenTitle = 'Valid Outline Operand Metadata Payload';
        $xmp = $outlineMetadataStreamOperandBoundaryXmp($hiddenTitle);
        [$pdf] = $outlineMetadataStreamOperandBoundaryPdf(
            'Valid Operand Boundary Chapter',
            'Outline metadata valid operand visible body',
            $xmp,
            '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /DecodeParms << /Predictor 1 /Columns 1 >>',
            "/FlateDecode\n"
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $outline = $metadata['document_outline'] ?? [];
        $review = $outline['items'][0]['metadata_stream_review'] ?? [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['Valid Operand Boundary Chapter'], $outline['titles'] ?? null);
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
        $t->same(strlen($xmp), $review['bytes'] ?? null);
        $t->same(hash('sha256', $xmp), $review['sha256'] ?? null);
        $t->same(['title'], $review['xmp_summary']['field_names'] ?? null);
        $t->same(true, $review['xmp_summary']['text_values_redacted'] ?? null);
        $t->same('Outline metadata valid operand visible body', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, $xmp));
        $t->true(is_string($encoded) && !str_contains($encoded, $hiddenTitle));
    },
];
