<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$ccittFaxFilterBoundaryPdf = static function (): array {
    $content = "BT /F1 12 Tf 72 720 Td (Before CCITT XObject) Tj ET\n"
        . "q 172.8 0 0 0.2 72 700 cm /Fax#20Scan Do Q\n"
        . "q 16 0 0 1 72 680 cm /AliasFax Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After CCITT XObject) Tj ET';
    $faxPayload = 'BT /F1 12 Tf 72 720 Td (CCITT Fax Payload Noise) Tj ET';
    $aliasPayload = 'BT /F1 12 Tf 72 700 Td (CCF Alias Payload Noise) Tj ET';

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Fax#20Scan 5 0 R /AliasFax 6 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1728 /Height 2 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms 8 0 R /Decode [1 0] /Length " . strlen($faxPayload) . " >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 1 /Filter [null /CCF] /DecodeParms [null << /K 0 /Columns 16 /Rows 1 /BlackIs1 false /EndOfLine false /EndOfBlock true >>] /Length " . strlen($aliasPayload) . " >>\nstream\n{$aliasPayload}\nendstream\nendobj\n"
        . "8 0 obj\n<< /K -1 /Columns 1728 /Rows 2 /BlackIs1 true /EncodedByteAlign true /EndOfLine true /EndOfBlock false /DamagedRowsBeforeError 3 >>\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $faxPayload, $aliasPayload];
};

return [
    'records CCITT Fax image DecodeParms without rasterizing or leaking payload text' => static function (TestRunner $t) use ($ccittFaxFilterBoundaryPdf): void {
        [$pdf, $faxPayload, $aliasPayload] = $ccittFaxFilterBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(true, $review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['page_count']);
        $t->same(2, $review['image_xobject_count']);
        $t->same(2, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $fax = $review['entries'][0];
        $t->same('Fax Scan', $fax['resource_name']);
        $t->same(5, $fax['object_number']);
        $t->same(true, $fax['invoked']);
        $t->same(1, $fax['invocation_count']);
        $t->same(1728, $fax['width']);
        $t->same(2, $fax['height']);
        $t->same(true, $fax['image_mask']);
        $t->same(1, $fax['bits_per_component']);
        $t->same(['CCITTFaxDecode'], $fax['filters']);
        $t->same(['CCITTFaxDecode'], $fax['preview_only_filters']);
        $t->same(false, $fax['native_raster_decode']);
        $t->same(false, $fax['decoded_with_current_filters']);
        $t->same(null, $fax['decoded_length']);
        $t->same(false, $fax['payload_in_visible_text']);
        $t->same([
            [
                'filter' => 'CCITTFaxDecode',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'CCITTFaxDecode',
                    'k' => -1,
                    'columns' => 1728,
                    'rows' => 2,
                    'black_is_1' => true,
                    'encoded_byte_align' => true,
                    'end_of_line' => true,
                    'end_of_block' => false,
                    'damaged_rows_before_error' => 3,
                ],
            ],
        ], $fax['filter_details']);

        $alias = $review['entries'][1];
        $t->same('AliasFax', $alias['resource_name']);
        $t->same(6, $alias['object_number']);
        $t->same(false, $alias['image_mask']);
        $t->same('DeviceGray', $alias['color_space']);
        $t->same(['CCF'], $alias['filters']);
        $t->same(['CCF'], $alias['preview_only_filters']);
        $t->same(false, $alias['native_raster_decode']);
        $t->same(false, $alias['decoded_with_current_filters']);
        $t->same([
            [
                'filter' => 'CCF',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'CCITTFaxDecode',
                    'k' => 0,
                    'columns' => 16,
                    'rows' => 1,
                    'black_is_1' => false,
                    'encoded_byte_align' => null,
                    'end_of_line' => false,
                    'end_of_block' => true,
                    'damaged_rows_before_error' => null,
                ],
            ],
        ], $alias['filter_details']);

        $t->same(['Before CCITT XObject', 'After CCITT XObject'], $extractor->extractTextLines($pdf));
        $t->same("Before CCITT XObject\nAfter CCITT XObject", $plainText);
        $t->same("Before CCITT XObject\nAfter CCITT XObject\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'CCITT Fax Payload Noise'));
        $t->true(!str_contains($plainText, 'CCF Alias Payload Noise'));
        $encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encodedReview, $faxPayload));
        $t->true(!str_contains($encodedReview, $aliasPayload));
    },
];
