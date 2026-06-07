<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$imageXObjectMetadataFilterOperandPdf = static function (
    string $filterHelperBody,
    string $bodyText
): array {
    $imagePayload = 'BT /F1 12 Tf 72 720 Td (Image metadata filter operand image payload noise) Tj ET';
    $metadataPayload = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Image XObject Hidden Metadata Title</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Image-local metadata filter helpers stay review-only.</rdf:li></rdf:Alt></dc:description>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
    $compressedImagePayload = gzcompress($imagePayload);
    $compressedMetadataPayload = gzcompress($metadataPayload);
    if (!is_string($compressedImagePayload) || !is_string($compressedMetadataPayload)) {
        throw new RuntimeException('Unable to compress image metadata filter operand fixture.');
    }

    $pageContent = "BT /F1 12 Tf 72 720 Td (Before {$bodyText}) Tj ET\n"
        . "q 24 0 0 12 72 690 cm /Meta#20Image Do Q\n"
        . "BT /F1 12 Tf 72 660 Td (After {$bodyText}) Tj ET";

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Meta#20Image 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Metadata 6 0 R /Filter /FlateDecode /Length " . strlen($compressedImagePayload) . " >>\nstream\n{$compressedImagePayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Metadata /Subtype /XML /Filter 7 0 R /Length " . strlen($compressedMetadataPayload) . " >>\nstream\n{$compressedMetadataPayload}\nendstream\nendobj\n"
        . "7 0 obj\n{$filterHelperBody}\nendobj\n"
        . "8 0 obj\n<< /S /JavaScript /JS (app.alert\\('image metadata filter operand tail'\\)) >>\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $metadataPayload, $imagePayload];
};

return [
    'rejects image XObject metadata streams with indirect Filter helpers that carry extra operands' => static function (
        TestRunner $t
    ) use ($imageXObjectMetadataFilterOperandPdf): void {
        [$pdf, $metadataPayload, $imagePayload] = $imageXObjectMetadataFilterOperandPdf(
            "/FlateDecode /Crypt 8 0 R\n",
            'image metadata filter operand'
        );

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);
        $entry = $review['entries'][0];
        $metadata = $entry['metadata_stream'] ?? [];
        $filterOperand = $metadata['filter_operands'][0] ?? [];
        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(1, $review['image_xobject_count']);
        $t->same('Meta Image', $entry['resource_name']);
        $t->same(true, $entry['invoked']);
        $t->same('rejected_malformed_image_xobject_metadata_stream_filter_operand', $metadata['status'] ?? null);
        $t->same('reject_malformed_filter_operands', $metadata['filter_operand_policy'] ?? null);
        $t->same(['FlateDecode'], $metadata['filters'] ?? null);
        $t->same([], $metadata['preview_only_filters'] ?? null);
        $t->same(1, $metadata['invalid_filter_operand_count'] ?? null);
        $t->same(1, $metadata['malformed_filter_operand_count'] ?? null);
        $t->same(0, $metadata['dictionary_filter_operand_count'] ?? null);
        $t->same(0, $metadata['unresolved_filter_operand_count'] ?? null);
        $t->same(false, $metadata['decoded_with_current_filters'] ?? null);
        $t->same(null, $metadata['decoded_length'] ?? null);
        $t->same(null, $metadata['decoded_sha256'] ?? null);
        $t->same(false, $metadata['payload_in_visible_text'] ?? null);
        $t->same(true, $metadata['review_only'] ?? null);
        $t->same(1, is_countable($metadata['filter_operands'] ?? null) ? count($metadata['filter_operands']) : null);
        $t->same('Filter', $filterOperand['name'] ?? null);
        $t->same('indirect', $filterOperand['kind'] ?? null);
        $t->same(7, $filterOperand['object_number'] ?? null);
        $t->same(0, $filterOperand['generation'] ?? null);
        $t->same(true, $filterOperand['resolved'] ?? null);
        $t->same('/FlateDecode /Crypt 8 0 R', $filterOperand['value_preview'] ?? null);
        $t->same('name', $filterOperand['token_type'] ?? null);
        $t->same('FlateDecode', $filterOperand['value'] ?? null);
        $t->same(false, $filterOperand['valid_filter_operand'] ?? null);
        $t->same(false, $filterOperand['dictionary_filter_operand'] ?? null);
        $t->same(true, $filterOperand['extra_filter_operand'] ?? null);
        $t->same('name', $filterOperand['extra_filter_operand_type'] ?? null);
        $t->same('/Crypt', $filterOperand['extra_filter_operand_preview'] ?? null);
        $t->same(true, $filterOperand['extra_filter_name_operand'] ?? null);
        $t->same('Crypt', $filterOperand['extra_filter_name'] ?? null);
        $t->same("Before image metadata filter operand\nAfter image metadata filter operand", $plainText);
        $t->true(!str_contains($plainText, 'Image XObject Hidden Metadata Title'));
        $t->true(!str_contains($plainText, 'image metadata filter operand tail'));
        $t->true(!str_contains($encoded, $metadataPayload));
        $t->true(!str_contains($encoded, $imagePayload));
        $t->true(!str_contains($encoded, 'image metadata filter operand tail'));
    },
    'accepts image XObject metadata streams with single-name indirect Filter helpers' => static function (
        TestRunner $t
    ) use ($imageXObjectMetadataFilterOperandPdf): void {
        [$pdf, $metadataPayload] = $imageXObjectMetadataFilterOperandPdf(
            "/FlateDecode\n",
            'valid image metadata filter operand'
        );

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $metadata = $review['entries'][0]['metadata_stream'] ?? [];

        $t->same(6, $metadata['object_number'] ?? null);
        $t->same(0, $metadata['object_generation'] ?? null);
        $t->same('XML', $metadata['subtype'] ?? null);
        $t->same(['FlateDecode'], $metadata['filters'] ?? null);
        $t->same([], $metadata['preview_only_filters'] ?? null);
        $t->same(true, $metadata['decoded_with_current_filters'] ?? null);
        $t->same(strlen($metadataPayload), $metadata['decoded_length'] ?? null);
        $t->same(hash('sha256', $metadataPayload), $metadata['decoded_sha256'] ?? null);
        $t->same(false, isset($metadata['status']));
        $t->same(false, isset($metadata['filter_operand_policy']));
        $t->same(false, isset($metadata['filter_operands']));
    },
];
