<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfImageRenderer;

$ccittFaxFilterBoundaryZlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('Focused CCITT Flate-prefix fixture must fit one deflate stored block.');
    }

    $s1 = 1;
    $s2 = 0;
    for ($index = 0; $index < $length; $index++) {
        $s1 = ($s1 + ord($bytes[$index])) % 65521;
        $s2 = ($s2 + $s1) % 65521;
    }

    return "\x78\x01"
        . "\x01"
        . pack('v', $length)
        . pack('v', (~$length) & 0xffff)
        . $bytes
        . pack('N', ($s2 << 16) | $s1);
};

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

$ccittFaxInvalidDecodeParmsPdf = static function (): array {
    $content = "BT /F1 12 Tf 72 720 Td (Before invalid CCITT review) Tj ET\n"
        . "q 20 0 0 2 72 700 cm /BadFax Do Q\n"
        . "q 10 0 0 1 72 680 cm /BadAlias Do Q\n"
        . 'BT /F1 12 Tf 72 660 Td (After invalid CCITT review) Tj ET';
    $faxPayload = 'BT /F1 12 Tf 72 720 Td (Invalid CCITT Fax Payload Noise) Tj ET';
    $aliasPayload = strtoupper(bin2hex('BT /F1 12 Tf 72 700 Td (Invalid CCF Alias Payload Noise) Tj ET')) . '>';

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /BadFax 5 0 R /BadAlias 6 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 20 /Height 2 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K /TwoD /Columns -4 /Rows 99 0 R /BlackIs1 /Maybe /EndOfBlock true /DamagedRowsBeforeError -1 >> /Length " . strlen($faxPayload) . " >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 10 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 1 /Filter [/ASCIIHexDecode /CCF] /DecodeParms [null << /Columns /Wide /Rows -1 /EndOfLine /No >>] /Length " . strlen($aliasPayload) . " >>\nstream\n{$aliasPayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $faxPayload, $aliasPayload];
};

$ccittFaxDirectEndBlockBoundaryPdf = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before direct CCITT EOB) Tj ET';
    $between = 'BT /F1 12 Tf 72 690 Td (Between direct CCITT EOB) Tj ET';
    $after = 'BT /F1 12 Tf 72 660 Td (After direct CCITT EOB) Tj ET';
    $fakeG4Object = 'BT /F1 12 Tf 72 700 Td (Fake direct CCITT G4 owner leak) Tj ET';
    $fakeRtcObject = 'BT /F1 12 Tf 72 680 Td (Fake direct CCITT RTC owner leak) Tj ET';
    $eofb = "\x00\x10\x01";
    $rtc = $eofb . $eofb . $eofb;
    $g4Payload = "\x11\x22\n"
        . "endstream\nendobj\n"
        . "50 0 obj\n<< /Length " . strlen($fakeG4Object) . " >>\nstream\n{$fakeG4Object}\nendstream\nendobj\n"
        . "\x33\x44{$eofb}";
    $rtcPayload = "\x55\x66\n"
        . "endstream\nendobj\n"
        . "51 0 obj\n<< /Length " . strlen($fakeRtcObject) . " >>\nstream\n{$fakeRtcObject}\nendstream\nendobj\n"
        . "\x77\x88{$rtc}";
    $rtcStaleLength = strpos($rtcPayload, "\nendstream\n");
    if ($rtcStaleLength === false) {
        throw new RuntimeException('Focused RTC CCITT fixture must expose a stale endstream marker.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxG4 5 0 R /FaxRtc 6 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 7 0 R 8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K -1 /Columns 16 /Rows 0 /EndOfBlock true >> >>\nstream\n{$g4Payload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /ImageMask true /BitsPerComponent 1 /Filter /CCF /DecodeParms << /K 0 /Columns 16 /Rows 0 /EndOfBlock true >> /Length {$rtcStaleLength} >>\nstream\n{$rtcPayload}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($between) . " >>\nstream\n{$between}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $g4Payload, $rtcPayload];
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
        $t->same([
            'filter' => 'CCITTFaxDecode',
            'review_only' => true,
            'native_raster_decode' => false,
            'image_mask' => true,
            'black_is_1' => true,
            'black_sample_value' => 1,
            'white_sample_value' => 0,
            'image_mask_decode_source' => 'explicit',
            'decode_inverts_stencil' => true,
            'black_sample_opacity' => 0.0,
            'white_sample_opacity' => 1.0,
            'black_sample_is_visible' => false,
            'white_sample_is_visible' => true,
        ], $fax['ccitt_fax_imagemask_polarity_boundary']);

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
    'marks malformed CCITT Fax DecodeParms fail closed without treating them as defaults' => static function (TestRunner $t) use ($ccittFaxInvalidDecodeParmsPdf): void {
        [$pdf, $faxPayload, $aliasPayload] = $ccittFaxInvalidDecodeParmsPdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(2, $review['image_xobject_count']);
        $t->same(2, $review['invoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $fax = $review['entries'][0];
        $t->same('BadFax', $fax['resource_name']);
        $t->same(['CCITTFaxDecode'], $fax['filters']);
        $t->same(['CCITTFaxDecode'], $fax['preview_only_filters']);
        $t->same(false, $fax['native_raster_decode']);
        $t->same(false, $fax['decoded_with_current_filters']);
        $t->same([
            [
                'filter' => 'CCITTFaxDecode',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'CCITTFaxDecode',
                    'k' => null,
                    'columns' => -4,
                    'rows' => null,
                    'black_is_1' => null,
                    'encoded_byte_align' => null,
                    'end_of_line' => null,
                    'end_of_block' => true,
                    'damaged_rows_before_error' => -1,
                    'valid_decode_parms' => false,
                    'invalid_decode_parms_fields' => [
                        'k',
                        'columns',
                        'rows',
                        'black_is_1',
                        'damaged_rows_before_error',
                    ],
                    'decode_parms_review' => 'invalid_ccitt_decodeparms_fail_closed',
                ],
            ],
        ], $fax['filter_details']);

        $alias = $review['entries'][1];
        $t->same('BadAlias', $alias['resource_name']);
        $t->same(['ASCIIHexDecode', 'CCF'], $alias['filters']);
        $t->same(['CCF'], $alias['preview_only_filters']);
        $t->same(false, $alias['native_raster_decode']);
        $t->same(false, $alias['decoded_with_current_filters']);
        $t->same([
            [
                'filter' => 'ASCIIHexDecode',
                'preview_only' => false,
                'decode_parms' => null,
            ],
            [
                'filter' => 'CCF',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'CCITTFaxDecode',
                    'k' => null,
                    'columns' => null,
                    'rows' => -1,
                    'black_is_1' => null,
                    'encoded_byte_align' => null,
                    'end_of_line' => null,
                    'end_of_block' => null,
                    'damaged_rows_before_error' => null,
                    'valid_decode_parms' => false,
                    'invalid_decode_parms_fields' => [
                        'columns',
                        'rows',
                        'end_of_line',
                    ],
                    'decode_parms_review' => 'invalid_ccitt_decodeparms_fail_closed',
                ],
            ],
        ], $alias['filter_details']);

        $t->same(['Before invalid CCITT review', 'After invalid CCITT review'], $extractor->extractTextLines($pdf));
        $t->same("Before invalid CCITT review\nAfter invalid CCITT review", $plainText);
        $t->same("Before invalid CCITT review\nAfter invalid CCITT review\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Invalid CCITT Fax Payload Noise'));
        $t->true(!str_contains($plainText, 'Invalid CCF Alias Payload Noise'));
        $encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encodedReview, $faxPayload));
        $t->true(!str_contains($encodedReview, $aliasPayload));
    },
    'marks unresolved CCITT Fax DecodeParms operands fail closed instead of applying defaults' => static function (TestRunner $t): void {
        $extractor = new PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before unresolved CCITT DecodeParms) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After unresolved CCITT DecodeParms) Tj ET';
        $faxPayload = 'BT /F1 12 Tf 72 700 Td (Unresolved CCITT DecodeParms Payload Noise) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxMissingParms 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms 99 0 R /Length " . strlen($faxPayload) . " >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $decodeParms = $entry['filter_details'][0]['decode_parms'] ?? [];
        $boundary = $entry['ccitt_fax_decode_boundary'] ?? [];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Before unresolved CCITT DecodeParms', 'After unresolved CCITT DecodeParms'], $extractor->extractTextLines($pdf));
        $t->same("Before unresolved CCITT DecodeParms\nAfter unresolved CCITT DecodeParms", $plainText);
        $t->true(!str_contains($plainText, 'Unresolved CCITT DecodeParms Payload Noise'));
        $t->same([
            'type' => 'CCITTFaxDecode',
            'k' => null,
            'columns' => null,
            'rows' => null,
            'black_is_1' => null,
            'encoded_byte_align' => null,
            'end_of_line' => null,
            'end_of_block' => null,
            'damaged_rows_before_error' => null,
            'valid_decode_parms' => false,
            'invalid_decode_parms_fields' => ['decode_parms_operand'],
            'decode_parms_review' => 'unresolved_ccitt_decodeparms_fail_closed',
            'decode_parms_operand' => 'unresolved_reference',
        ], $decodeParms);
        $t->same(true, $boundary['decode_parms_present'] ?? null);
        $t->same(true, $boundary['invalid_decode_parms'] ?? null);
        $t->same(['decode_parms_operand'], $boundary['invalid_decode_parms_fields'] ?? null);
        $t->same([
            'k' => 0,
            'columns' => 1728,
            'rows' => 0,
            'black_is_1' => false,
            'encoded_byte_align' => false,
            'end_of_line' => false,
            'end_of_block' => true,
            'damaged_rows_before_error' => 0,
        ], $boundary['effective_decode_parms'] ?? null);
        $t->same([
            'k',
            'columns',
            'rows',
            'black_is_1',
            'encoded_byte_align',
            'end_of_line',
            'end_of_block',
            'damaged_rows_before_error',
        ], $boundary['defaults_applied'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->true(!str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $faxPayload));

        $renderer = new PdfImageRenderer();
        $inlinePayload = 'fax bytes EI BT /F1 12 Tf 72 640 Td (Inline unresolved CCITT DecodeParms payload noise) Tj ET final';
        $inlinePlan = $renderer->inlineImageReviewPlan(
            '/W 16 /H 1 /IM true /F /CCF /DP 99 0 R /D [1 0]',
            $inlinePayload
        );
        $inlineParms = $inlinePlan['image_filter_details'][0]['decode_parms'] ?? [];
        $inlineBoundary = $inlinePlan['ccitt_fax_decode_boundary'] ?? [];

        $t->same($decodeParms, $inlineParms);
        $t->same(true, $inlineBoundary['decode_parms_present'] ?? null);
        $t->same(true, $inlineBoundary['invalid_decode_parms'] ?? null);
        $t->same(['decode_parms_operand'], $inlineBoundary['invalid_decode_parms_fields'] ?? null);
        $t->same(['CCITTFaxDecode'], $inlinePlan['image_filters']);
        $t->same(['CCITTFaxDecode'], $inlinePlan['inline_image']['review_only_filters'] ?? null);
        $t->same(false, $inlinePlan['inline_image']['native_raster_decode'] ?? null);
        $t->same(true, $inlinePlan['inline_image_payload_excluded_from_text']);
        $t->contains('inline_ccitt_fax_image_filter_review_only', implode(',', $inlinePlan['notes']));
        $t->true(!str_contains(json_encode($inlinePlan, JSON_UNESCAPED_SLASHES) ?: '', $inlinePayload));

        $nullOperandPdf = str_replace('/DecodeParms 99 0 R', '/DecodeParms null', $pdf);
        $nullReview = $extractor->extractImageXObjectBoundaryReview($nullOperandPdf);
        $nullEntry = $nullReview['entries'][0] ?? [];
        $nullBoundary = $nullEntry['ccitt_fax_decode_boundary'] ?? [];
        $t->same(null, $nullEntry['filter_details'][0]['decode_parms'] ?? null);
        $t->same(false, $nullBoundary['decode_parms_present'] ?? null);
        $t->same(false, $nullBoundary['invalid_decode_parms'] ?? null);
        $t->same([
            'k' => 0,
            'columns' => 1728,
            'rows' => 0,
            'black_is_1' => false,
            'encoded_byte_align' => false,
            'end_of_line' => false,
            'end_of_block' => true,
            'damaged_rows_before_error' => 0,
        ], $nullBoundary['effective_decode_parms'] ?? null);
        $t->same(false, $nullEntry['payload_in_visible_text'] ?? null);
    },
    'marks resolved malformed indirect CCITT Fax DecodeParms operands fail closed' => static function (TestRunner $t): void {
        $extractor = new PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before malformed indirect CCITT DecodeParms) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After malformed indirect CCITT DecodeParms) Tj ET';
        $faxPayload = 'BT /F1 12 Tf 72 700 Td (Malformed indirect CCITT DecodeParms Payload Noise) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxMalformedParms 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms 11 0 R /Length " . strlen($faxPayload) . " >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "11 0 obj\n/NotADictionary\nendobj\n%%EOF";

        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $decodeParms = $entry['filter_details'][0]['decode_parms'] ?? [];
        $boundary = $entry['ccitt_fax_decode_boundary'] ?? [];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Before malformed indirect CCITT DecodeParms', 'After malformed indirect CCITT DecodeParms'], $extractor->extractTextLines($pdf));
        $t->same("Before malformed indirect CCITT DecodeParms\nAfter malformed indirect CCITT DecodeParms", $plainText);
        $t->true(!str_contains($plainText, 'Malformed indirect CCITT DecodeParms Payload Noise'));
        $t->same([
            'type' => 'CCITTFaxDecode',
            'k' => null,
            'columns' => null,
            'rows' => null,
            'black_is_1' => null,
            'encoded_byte_align' => null,
            'end_of_line' => null,
            'end_of_block' => null,
            'damaged_rows_before_error' => null,
            'valid_decode_parms' => false,
            'invalid_decode_parms_fields' => ['decode_parms_operand'],
            'decode_parms_review' => 'malformed_ccitt_decodeparms_fail_closed',
            'decode_parms_operand' => 'malformed_operand',
        ], $decodeParms);
        $t->same(true, $boundary['decode_parms_present'] ?? null);
        $t->same(true, $boundary['invalid_decode_parms'] ?? null);
        $t->same(['decode_parms_operand'], $boundary['invalid_decode_parms_fields'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->true(!str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $faxPayload));
    },
    'keeps malformed CCITT Fax DecodeParms stream owners closed before nested fake objects' => static function (TestRunner $t): void {
        $extractor = new PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before invalid owner CCITT) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After invalid owner CCITT) Tj ET';
        $fakeObjectText = 'BT /F1 12 Tf 72 700 Td (Fake invalid owner CCITT leak) Tj ET';
        $eofb = "\x00\x10\x01";
        $rtc = $eofb . $eofb . $eofb;
        $faxPayload = "\x01\x02\n"
            . "endstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($fakeObjectText) . " >>\nstream\n{$fakeObjectText}\nendstream\nendobj\n"
            . "\x03{$rtc}";
        $staleLength = strpos($faxPayload, "\nendstream\n");
        if ($staleLength === false) {
            throw new RuntimeException('Focused malformed CCITT owner fixture must expose a stale endstream marker.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxInvalidOwner 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 9 0 R 6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K /Bad /Columns 16 /Rows 0 /EndOfBlock true >> /Length {$staleLength} >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Before invalid owner CCITT', 'After invalid owner CCITT'], $extractor->extractTextLines($pdf));
        $t->same("Before invalid owner CCITT\nAfter invalid owner CCITT", $plainText);
        $t->true(!str_contains($plainText, 'Fake invalid owner CCITT leak'));
        $t->same(['CCITTFaxDecode'], $entry['filters'] ?? null);
        $t->same(['CCITTFaxDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(strlen($faxPayload), $entry['raw_length'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same([
            'type' => 'CCITTFaxDecode',
            'k' => null,
            'columns' => 16,
            'rows' => 0,
            'black_is_1' => null,
            'encoded_byte_align' => null,
            'end_of_line' => null,
            'end_of_block' => true,
            'damaged_rows_before_error' => null,
            'valid_decode_parms' => false,
            'invalid_decode_parms_fields' => ['k'],
            'decode_parms_review' => 'invalid_ccitt_decodeparms_fail_closed',
        ], $entry['filter_details'][0]['decode_parms'] ?? null);
        $t->same(true, $entry['ccitt_fax_decode_boundary']['invalid_decode_parms'] ?? null);
        $t->same(['k'], $entry['ccitt_fax_decode_boundary']['invalid_decode_parms_fields'] ?? null);
        $encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encodedReview, 'Fake invalid owner CCITT leak'));
        $t->true(!str_contains($encodedReview, $faxPayload));
    },
    'marks inline CCITT Fax image filters review-only before WordPress image preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $payload = "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline CCITT fax payload noise) Tj ET final";
        $plan = $renderer->inlineImageReviewPlan(
            '/W 16 /H 1 /IM true /F /CCF /DP << /K 0 /Columns 16 /Rows 1 /BlackIs1 false /EncodedByteAlign true /EndOfLine false /EndOfBlock true /DamagedRowsBeforeError 2 >> /D [1 0]',
            $payload
        );

        $t->same(['CCITTFaxDecode'], $plan['image_filters']);
        $t->same([
            [
                'filter' => 'CCITTFaxDecode',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'CCITTFaxDecode',
                    'k' => 0,
                    'columns' => 16,
                    'rows' => 1,
                    'black_is_1' => false,
                    'encoded_byte_align' => true,
                    'end_of_line' => false,
                    'end_of_block' => true,
                    'damaged_rows_before_error' => 2,
                ],
            ],
        ], $plan['image_filter_details']);
        $t->same([
            'preview_only_filters' => ['CCITTFaxDecode'],
            'jbig2_globals_present' => false,
            'native_raster_decode' => false,
        ], $plan['image_filter_boundary']);
        $t->same(true, $plan['inline_image_review_only']);
        $t->same(['CCITTFaxDecode'], $plan['inline_image']['review_only_filters']);
        $t->same(false, $plan['inline_image']['native_raster_decode']);
        $t->same(true, $plan['inline_image_payload_excluded_from_text']);
        $t->same(true, $plan['inline_image_abbreviations_expanded']);
        $t->same([
            'filter' => 'CCITTFaxDecode',
            'review_only' => true,
            'native_raster_decode' => false,
            'image_mask' => true,
            'black_is_1' => false,
            'black_sample_value' => 0,
            'white_sample_value' => 1,
            'image_mask_decode_source' => 'explicit',
            'decode_inverts_stencil' => true,
            'black_sample_opacity' => 1.0,
            'white_sample_opacity' => 0.0,
            'black_sample_is_visible' => true,
            'white_sample_is_visible' => false,
        ], $plan['ccitt_fax_imagemask_polarity_boundary']);
        $t->contains('ccitt_fax_image_filter_review_only', implode(',', $plan['notes']));
        $t->contains('ccitt_fax_imagemask_polarity_review_before_rgb_conversion', implode(',', $plan['notes']));
        $t->contains('inline_ccitt_fax_image_filter_review_only', implode(',', $plan['notes']));
        $t->contains('inline_ccitt_fax_imagemask_polarity_review_before_rgb_conversion', implode(',', $plan['notes']));
        $t->true(!str_contains(json_encode($plan, JSON_UNESCAPED_SLASHES) ?: '', 'Inline CCITT fax payload noise'));
    },
    'marks malformed inline CCITT Fax DecodeParms fail closed before RGB preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $payload = "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline invalid CCITT fax payload noise) Tj ET final";
        $plan = $renderer->inlineImageReviewPlan(
            '/W 8 /H 1 /IM true /F /CCF /DP << /K /TwoD /Columns 0 /Rows -1 /BlackIs1 /Maybe /EncodedByteAlign true /EndOfLine /No /EndOfBlock true /DamagedRowsBeforeError -2 >> /D [1 0]',
            $payload
        );

        $t->same(['CCITTFaxDecode'], $plan['image_filters']);
        $t->same([
            [
                'filter' => 'CCITTFaxDecode',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'CCITTFaxDecode',
                    'k' => null,
                    'columns' => 0,
                    'rows' => -1,
                    'black_is_1' => null,
                    'encoded_byte_align' => true,
                    'end_of_line' => null,
                    'end_of_block' => true,
                    'damaged_rows_before_error' => -2,
                    'valid_decode_parms' => false,
                    'invalid_decode_parms_fields' => [
                        'k',
                        'columns',
                        'rows',
                        'black_is_1',
                        'end_of_line',
                        'damaged_rows_before_error',
                    ],
                    'decode_parms_review' => 'invalid_ccitt_decodeparms_fail_closed',
                ],
            ],
        ], $plan['image_filter_details']);
        $t->same([
            'preview_only_filters' => ['CCITTFaxDecode'],
            'jbig2_globals_present' => false,
            'native_raster_decode' => false,
        ], $plan['image_filter_boundary']);
        $t->same(true, $plan['inline_image_review_only']);
        $t->same(false, $plan['inline_image']['native_raster_decode']);
        $t->same(true, $plan['inline_image_payload_excluded_from_text']);
        $t->contains('inline_ccitt_fax_image_filter_review_only', implode(',', $plan['notes']));
        $t->true(!str_contains(json_encode($plan, JSON_UNESCAPED_SLASHES) ?: '', 'Inline invalid CCITT fax payload noise'));
    },
    'resolves escaped CCITT Fax DecodeParms keys while ignoring nested decoys before RGB preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $payload = "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline escaped CCITT fax payload noise) Tj ET final";
        $plan = $renderer->inlineImageReviewPlan(
            '/W 8 /H 3 /IM true /F /CCF /DP << /Decoy << /Columns 4 /Rows 1 /BlackIs1 false /EndOfBlock true >> /#4B -1 /Colu#6Dns 32 /Ro#77s 3 /Black#49s1 true /EncodedByte#41lign true /EndOf#4cine true /EndOf#42lock false /DamagedRowsBefore#45rror 5 >> /D [1 0]',
            $payload
        );

        $t->same(['CCITTFaxDecode'], $plan['image_filters']);
        $t->same([
            [
                'filter' => 'CCITTFaxDecode',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'CCITTFaxDecode',
                    'k' => -1,
                    'columns' => 32,
                    'rows' => 3,
                    'black_is_1' => true,
                    'encoded_byte_align' => true,
                    'end_of_line' => true,
                    'end_of_block' => false,
                    'damaged_rows_before_error' => 5,
                ],
            ],
        ], $plan['image_filter_details']);
        $t->same([
            'filter' => 'CCITTFaxDecode',
            'review_only' => true,
            'native_raster_decode' => false,
            'decode_parms_present' => true,
            'invalid_decode_parms' => false,
            'invalid_decode_parms_fields' => [],
            'effective_decode_parms' => [
                'k' => -1,
                'columns' => 32,
                'rows' => 3,
                'black_is_1' => true,
                'encoded_byte_align' => true,
                'end_of_line' => true,
                'end_of_block' => false,
                'damaged_rows_before_error' => 5,
            ],
            'defaults_applied' => [],
            'dictionary_width' => 8,
            'dictionary_height' => 3,
            'effective_width' => 8,
            'effective_height' => 3,
            'width_source' => 'image_dictionary',
            'height_source' => 'image_dictionary',
            'columns_match_width' => false,
            'rows_match_height' => true,
            'dimension_mismatch' => true,
        ], $plan['ccitt_fax_decode_boundary']);
        $t->same(true, $plan['inline_image_review_only']);
        $t->same(false, $plan['inline_image']['native_raster_decode']);
        $t->same(true, $plan['inline_image_payload_excluded_from_text']);
        $t->contains('inline_ccitt_fax_image_filter_review_only', implode(',', $plan['notes']));
        $t->true(!str_contains(json_encode($plan, JSON_UNESCAPED_SLASHES) ?: '', 'Inline escaped CCITT fax payload noise'));
    },
    'aligns CCITT Fax DecodeParms arrays after null filter entries before RGB preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $payload = "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline null-filter CCITT payload noise) Tj ET final";
        $plan = $renderer->inlineImageReviewPlan(
            '/W 16 /H 2 /IM true /F [null /CCF] /DP [null << /K -1 /Columns 16 /Rows 2 /BlackIs1 true /EndOfBlock false >>] /D [1 0]',
            $payload
        );

        $t->same(['CCITTFaxDecode'], $plan['image_filters']);
        $t->same([
            [
                'filter' => 'CCITTFaxDecode',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'CCITTFaxDecode',
                    'k' => -1,
                    'columns' => 16,
                    'rows' => 2,
                    'black_is_1' => true,
                    'encoded_byte_align' => null,
                    'end_of_line' => null,
                    'end_of_block' => false,
                    'damaged_rows_before_error' => null,
                ],
            ],
        ], $plan['image_filter_details']);
        $t->same([
            'filter' => 'CCITTFaxDecode',
            'review_only' => true,
            'native_raster_decode' => false,
            'decode_parms_present' => true,
            'invalid_decode_parms' => false,
            'invalid_decode_parms_fields' => [],
            'effective_decode_parms' => [
                'k' => -1,
                'columns' => 16,
                'rows' => 2,
                'black_is_1' => true,
                'encoded_byte_align' => false,
                'end_of_line' => false,
                'end_of_block' => false,
                'damaged_rows_before_error' => 0,
            ],
            'defaults_applied' => [
                'encoded_byte_align',
                'end_of_line',
                'damaged_rows_before_error',
            ],
            'dictionary_width' => 16,
            'dictionary_height' => 2,
            'effective_width' => 16,
            'effective_height' => 2,
            'width_source' => 'image_dictionary',
            'height_source' => 'image_dictionary',
            'columns_match_width' => true,
            'rows_match_height' => true,
            'dimension_mismatch' => false,
        ], $plan['ccitt_fax_decode_boundary']);
        $t->same([
            'preview_only_filters' => ['CCITTFaxDecode'],
            'jbig2_globals_present' => false,
            'native_raster_decode' => false,
        ], $plan['image_filter_boundary']);
        $t->same(true, $plan['inline_image_payload_excluded_from_text']);
        $t->contains('inline_ccitt_fax_image_filter_review_only', implode(',', $plan['notes']));
        $t->true(!str_contains(json_encode($plan, JSON_UNESCAPED_SLASHES) ?: '', 'Inline null-filter CCITT payload noise'));
    },
    'aligns XObject CCITT Fax DecodeParms arrays after null filter entries before WordPress review' => static function (TestRunner $t): void {
        $extractor = new PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before compact CCITT XObject) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After compact CCITT XObject) Tj ET';
        $faxPayload = 'BT /F1 12 Tf 72 700 Td (Compact CCITT DecodeParms Payload Noise) Tj ET';
        $encodedFaxPayload = strtoupper(bin2hex($faxPayload)) . '>';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /CompactFax 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 24 /Height 2 /ImageMask true /BitsPerComponent 1 /Filter [null /ASCIIHexDecode /CCF] /DecodeParms [null << /K -1 /Columns 24 /Rows 2 /BlackIs1 true /EncodedByteAlign true /EndOfLine true /EndOfBlock false /DamagedRowsBeforeError 1 >>] /Length " . strlen($encodedFaxPayload) . " >>\nstream\n{$encodedFaxPayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? [];

        $t->same(['Before compact CCITT XObject', 'After compact CCITT XObject'], $extractor->extractTextLines($pdf));
        $t->same("Before compact CCITT XObject\nAfter compact CCITT XObject", $extractor->extractPlainText($pdf));
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'Compact CCITT DecodeParms Payload Noise'));
        $t->same(['ASCIIHexDecode', 'CCF'], $entry['filters'] ?? null);
        $t->same(['CCF'], $entry['preview_only_filters'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same([
            [
                'filter' => 'ASCIIHexDecode',
                'preview_only' => false,
                'decode_parms' => null,
            ],
            [
                'filter' => 'CCF',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'CCITTFaxDecode',
                    'k' => -1,
                    'columns' => 24,
                    'rows' => 2,
                    'black_is_1' => true,
                    'encoded_byte_align' => true,
                    'end_of_line' => true,
                    'end_of_block' => false,
                    'damaged_rows_before_error' => 1,
                ],
            ],
        ], $entry['filter_details'] ?? null);
        $t->same([
            'filter' => 'CCF',
            'review_only' => true,
            'native_raster_decode' => false,
            'decode_parms_present' => true,
            'invalid_decode_parms' => false,
            'invalid_decode_parms_fields' => [],
            'effective_decode_parms' => [
                'k' => -1,
                'columns' => 24,
                'rows' => 2,
                'black_is_1' => true,
                'encoded_byte_align' => true,
                'end_of_line' => true,
                'end_of_block' => false,
                'damaged_rows_before_error' => 1,
            ],
            'defaults_applied' => [],
            'dictionary_width' => 24,
            'dictionary_height' => 2,
            'effective_width' => 24,
            'effective_height' => 2,
            'width_source' => 'image_dictionary',
            'height_source' => 'image_dictionary',
            'columns_match_width' => true,
            'rows_match_height' => true,
            'dimension_mismatch' => false,
        ], $entry['ccitt_fax_decode_boundary'] ?? null);
        $t->same([
            'filter' => 'CCF',
            'review_only' => true,
            'native_raster_decode' => false,
            'image_mask' => true,
            'black_is_1' => true,
            'black_sample_value' => 1,
            'white_sample_value' => 0,
            'image_mask_decode_source' => 'default',
            'decode_inverts_stencil' => false,
            'black_sample_opacity' => 1.0,
            'white_sample_opacity' => 0.0,
            'black_sample_is_visible' => true,
            'white_sample_is_visible' => false,
        ], $entry['ccitt_fax_imagemask_polarity_boundary'] ?? null);
        $encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encodedReview, $faxPayload));
        $t->true(!str_contains($encodedReview, $encodedFaxPayload));
    },
    'marks unaligned inline CCITT Fax DecodeParms arrays fail closed before RGB preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $payload = "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline unaligned CCITT payload noise) Tj ET final";
        $plan = $renderer->inlineImageReviewPlan(
            '/W 16 /H 1 /IM true /F [/ASCIIHexDecode /CCF] /DP [<< /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock false >>] /D [1 0]',
            $payload
        );

        $t->same(['ASCIIHexDecode', 'CCITTFaxDecode'], $plan['image_filters']);
        $t->same([
            [
                'filter' => 'ASCIIHexDecode',
                'preview_only' => false,
                'decode_parms' => ['type' => 'ASCIIHexDecode'],
            ],
            [
                'filter' => 'CCITTFaxDecode',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'CCITTFaxDecode',
                    'k' => null,
                    'columns' => null,
                    'rows' => null,
                    'black_is_1' => null,
                    'encoded_byte_align' => null,
                    'end_of_line' => null,
                    'end_of_block' => null,
                    'damaged_rows_before_error' => null,
                    'valid_decode_parms' => false,
                    'invalid_decode_parms_fields' => ['decode_parms_alignment'],
                    'decode_parms_review' => 'unaligned_ccitt_decodeparms_fail_closed',
                    'decode_parms_alignment' => 'missing_filter_slot',
                    'filter_slot_count' => 2,
                    'decode_parms_slot_count' => 1,
                ],
            ],
        ], $plan['image_filter_details']);
        $t->same(true, $plan['ccitt_fax_decode_boundary']['decode_parms_present'] ?? null);
        $t->same(true, $plan['ccitt_fax_decode_boundary']['invalid_decode_parms'] ?? null);
        $t->same(['decode_parms_alignment'], $plan['ccitt_fax_decode_boundary']['invalid_decode_parms_fields'] ?? null);
        $t->same([
            'k',
            'columns',
            'rows',
            'black_is_1',
            'encoded_byte_align',
            'end_of_line',
            'end_of_block',
            'damaged_rows_before_error',
        ], $plan['ccitt_fax_decode_boundary']['defaults_applied'] ?? null);
        $t->same(['CCITTFaxDecode'], $plan['image_filter_boundary']['preview_only_filters'] ?? null);
        $t->same(false, $plan['image_filter_boundary']['native_raster_decode'] ?? null);
        $t->same(true, $plan['inline_image_payload_excluded_from_text']);
        $t->contains('inline_ccitt_fax_image_filter_review_only', implode(',', $plan['notes']));
        $t->true(!str_contains(json_encode($plan, JSON_UNESCAPED_SLASHES) ?: '', 'Inline unaligned CCITT payload noise'));
    },
    'marks unaligned XObject CCITT Fax DecodeParms arrays fail closed before WordPress review' => static function (TestRunner $t): void {
        $extractor = new PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before unaligned CCITT XObject) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After unaligned CCITT XObject) Tj ET';
        $faxPayload = 'BT /F1 12 Tf 72 700 Td (Unaligned CCITT DecodeParms Payload Noise) Tj ET';
        $encodedFaxPayload = strtoupper(bin2hex($faxPayload)) . '>';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /UnalignedFax 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter [/ASCIIHexDecode /CCF] /DecodeParms [<< /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock false >>] /Length " . strlen($encodedFaxPayload) . " >>\nstream\n{$encodedFaxPayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Before unaligned CCITT XObject', 'After unaligned CCITT XObject'], $extractor->extractTextLines($pdf));
        $t->same("Before unaligned CCITT XObject\nAfter unaligned CCITT XObject", $plainText);
        $t->same(['ASCIIHexDecode', 'CCF'], $entry['filters'] ?? null);
        $t->same(['CCF'], $entry['preview_only_filters'] ?? null);
        $t->same([
            [
                'filter' => 'ASCIIHexDecode',
                'preview_only' => false,
                'decode_parms' => ['type' => 'ASCIIHexDecode'],
            ],
            [
                'filter' => 'CCF',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'CCITTFaxDecode',
                    'k' => null,
                    'columns' => null,
                    'rows' => null,
                    'black_is_1' => null,
                    'encoded_byte_align' => null,
                    'end_of_line' => null,
                    'end_of_block' => null,
                    'damaged_rows_before_error' => null,
                    'valid_decode_parms' => false,
                    'invalid_decode_parms_fields' => ['decode_parms_alignment'],
                    'decode_parms_review' => 'unaligned_ccitt_decodeparms_fail_closed',
                    'decode_parms_alignment' => 'missing_filter_slot',
                    'filter_slot_count' => 2,
                    'decode_parms_slot_count' => 1,
                ],
            ],
        ], $entry['filter_details'] ?? null);
        $t->same(true, $entry['ccitt_fax_decode_boundary']['decode_parms_present'] ?? null);
        $t->same(true, $entry['ccitt_fax_decode_boundary']['invalid_decode_parms'] ?? null);
        $t->same(['decode_parms_alignment'], $entry['ccitt_fax_decode_boundary']['invalid_decode_parms_fields'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->true(!str_contains($plainText, 'Unaligned CCITT DecodeParms Payload Noise'));
        $encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encodedReview, $faxPayload));
        $t->true(!str_contains($encodedReview, $encodedFaxPayload));
    },
    'keeps Flate-wrapped CCITT Fax endstream decoys inside image payload boundaries' => static function (TestRunner $t) use ($ccittFaxFilterBoundaryZlibStored): void {
        $extractor = new PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before Flate CCITT stream) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After Flate CCITT stream) Tj ET';
        $fakeObject = 'BT /F1 12 Tf 72 700 Td (Fake Flate CCITT prefix leak) Tj ET';
        $faxPayload = "\x00\x11\x22\x33\n"
            . "endstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
            . "\x44\x55\x66";
        $compressedPayload = $ccittFaxFilterBoundaryZlibStored($faxPayload);
        $fakeTerminatorOffset = strpos($compressedPayload, "\nendstream\n");
        if ($fakeTerminatorOffset === false) {
            throw new RuntimeException('Focused Flate-wrapped CCITT fixture must expose a raw fake endstream marker.');
        }

        $buildPdf = static function (?int $declaredLength) use ($before, $after, $compressedPayload): string {
            $lengthOperand = $declaredLength === null ? '' : " /Length {$declaredLength}";

            return "%PDF-1.4\n"
                . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
                . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Fax#20Flate 5 0 R >> >> >>\nendobj\n"
                . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
                . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
                . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter [/FlateDecode /CCITTFaxDecode] /DecodeParms [null << /K 0 /Columns 16 /Rows 1 /EndOfBlock false >>]{$lengthOperand} >>\nstream\n{$compressedPayload}\nendstream\nendobj\n"
                . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";
        };

        foreach ([$buildPdf(null), $buildPdf($fakeTerminatorOffset)] as $pdf) {
            $review = $extractor->extractImageXObjectBoundaryReview($pdf);
            $plainText = $extractor->extractPlainText($pdf);
            $entry = $review['entries'][0] ?? [];

            $t->same(['Before Flate CCITT stream', 'After Flate CCITT stream'], $extractor->extractTextLines($pdf));
            $t->same("Before Flate CCITT stream\nAfter Flate CCITT stream", $plainText);
            $t->true(!str_contains($plainText, 'Fake Flate CCITT prefix leak'));
            $t->true(!str_contains($plainText, 'endstream'));
            $t->same(['FlateDecode', 'CCITTFaxDecode'], $entry['filters'] ?? null);
            $t->same(['CCITTFaxDecode'], $entry['preview_only_filters'] ?? null);
            $t->same(false, $entry['decoded_with_current_filters'] ?? null);
            $t->same(strlen($compressedPayload), $entry['raw_length'] ?? null);
            $t->same(false, $entry['payload_in_visible_text'] ?? null);
        }
    },
    'requires CCITT end-of-block markers after identity Crypt prefixes before fake stream owners' => static function (TestRunner $t): void {
        $extractor = new PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before Crypt CCITT stream) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After Crypt CCITT stream) Tj ET';
        $fakeObject = 'BT /F1 12 Tf 72 700 Td (Fake Crypt CCITT owner leak) Tj ET';
        $eofb = "\x00\x10\x01";
        $faxPayload = "\x01\x02\x03\n"
            . "endstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
            . "\x04\x05{$eofb}";
        $fakeTerminatorOffset = strpos($faxPayload, "\nendstream\n");
        if ($fakeTerminatorOffset === false) {
            throw new RuntimeException('Focused Crypt-wrapped CCITT fixture must expose a raw fake endstream marker.');
        }

        $buildPdf = static function (?int $declaredLength) use ($before, $after, $faxPayload): string {
            $lengthOperand = $declaredLength === null ? '' : " /Length {$declaredLength}";

            return "%PDF-1.4\n"
                . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
                . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Fax#20Crypt 5 0 R >> >> >>\nendobj\n"
                . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
                . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
                . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ImageMask true /BitsPerComponent 1 /Filter [/Crypt /CCITTFaxDecode] /DecodeParms [<< /Name /Identity >> << /K -1 /Columns 16 /Rows 0 /EndOfBlock true >>]{$lengthOperand} >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
                . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";
        };

        foreach ([$buildPdf(null), $buildPdf($fakeTerminatorOffset)] as $pdf) {
            $review = $extractor->extractImageXObjectBoundaryReview($pdf);
            $plainText = $extractor->extractPlainText($pdf);
            $entry = $review['entries'][0] ?? [];

            $t->same(['Before Crypt CCITT stream', 'After Crypt CCITT stream'], $extractor->extractTextLines($pdf));
            $t->same("Before Crypt CCITT stream\nAfter Crypt CCITT stream", $plainText);
            $t->true(!str_contains($plainText, 'Fake Crypt CCITT owner leak'));
            $t->true(!str_contains($plainText, 'endstream'));
            $t->same(['Crypt', 'CCITTFaxDecode'], $entry['filters'] ?? null);
            $t->same(['CCITTFaxDecode'], $entry['preview_only_filters'] ?? null);
            $t->same(strlen($faxPayload), $entry['raw_length'] ?? null);
            $t->same(false, $entry['decoded_with_current_filters'] ?? null);
            $t->same(false, $entry['payload_in_visible_text'] ?? null);
            $t->same(-1, $entry['ccitt_fax_decode_boundary']['effective_decode_parms']['k'] ?? null);
            $t->same(true, $entry['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_block'] ?? null);
        }
    },
    'uses direct CCITT EOFB and RTC markers before accepting fake endstream owners' => static function (TestRunner $t) use ($ccittFaxDirectEndBlockBoundaryPdf): void {
        [$pdf, $g4Payload, $rtcPayload] = $ccittFaxDirectEndBlockBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);
        $g4 = $review['entries'][0] ?? [];
        $rtc = $review['entries'][1] ?? [];

        $t->same(['Before direct CCITT EOB', 'Between direct CCITT EOB', 'After direct CCITT EOB'], $extractor->extractTextLines($pdf));
        $t->same("Before direct CCITT EOB\nBetween direct CCITT EOB\nAfter direct CCITT EOB", $plainText);
        $t->same("Before direct CCITT EOB\nBetween direct CCITT EOB\nAfter direct CCITT EOB\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'Fake direct CCITT G4 owner leak'));
        $t->true(!str_contains($plainText, 'Fake direct CCITT RTC owner leak'));
        $t->true(!str_contains($plainText, 'endstream'));
        $t->same(['CCITTFaxDecode'], $g4['filters'] ?? null);
        $t->same(['CCITTFaxDecode'], $g4['preview_only_filters'] ?? null);
        $t->same(strlen($g4Payload), $g4['raw_length'] ?? null);
        $t->same(false, $g4['decoded_with_current_filters'] ?? null);
        $t->same(false, $g4['payload_in_visible_text'] ?? null);
        $t->same(-1, $g4['ccitt_fax_decode_boundary']['effective_decode_parms']['k'] ?? null);
        $t->same(true, $g4['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_block'] ?? null);
        $t->same(['CCF'], $rtc['filters'] ?? null);
        $t->same(['CCF'], $rtc['preview_only_filters'] ?? null);
        $t->same(strlen($rtcPayload), $rtc['raw_length'] ?? null);
        $t->same(false, $rtc['decoded_with_current_filters'] ?? null);
        $t->same(false, $rtc['payload_in_visible_text'] ?? null);
        $t->same(0, $rtc['ccitt_fax_decode_boundary']['effective_decode_parms']['k'] ?? null);
        $t->same(true, $rtc['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_block'] ?? null);
        $encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encodedReview, 'Fake direct CCITT G4 owner leak'));
        $t->true(!str_contains($encodedReview, 'Fake direct CCITT RTC owner leak'));
    },
    'uses CCITT row EOL markers for EndOfBlock false stream ownership' => static function (TestRunner $t): void {
        $extractor = new PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before CCITT row EOL) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After CCITT row EOL) Tj ET';
        $fakeObject = 'BT /F1 12 Tf 72 700 Td (Fake CCITT row EOL owner leak) Tj ET';
        $eol = "\x00\x10\x01";
        $faxPayload = "\x01\x02\n"
            . "endstream\nendobj\n"
            . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
            . "\x03{$eol}";
        $staleLength = strpos($faxPayload, "\nendstream\n");
        if ($staleLength === false) {
            throw new RuntimeException('Focused CCITT row EOL fixture must expose a stale endstream marker.');
        }

        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxRowEol 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 9 0 R 6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K 0 /Columns 16 /Rows 1 /EndOfLine true /EndOfBlock false >> /Length {$staleLength} >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Before CCITT row EOL', 'After CCITT row EOL'], $extractor->extractTextLines($pdf));
        $t->same("Before CCITT row EOL\nAfter CCITT row EOL", $plainText);
        $t->true(!str_contains($plainText, 'Fake CCITT row EOL owner leak'));
        $t->true(!str_contains($plainText, 'endstream'));
        $t->same(['CCITTFaxDecode'], $entry['filters'] ?? null);
        $t->same(['CCITTFaxDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(strlen($faxPayload), $entry['raw_length'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(true, $entry['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_line'] ?? null);
        $t->same(false, $entry['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_block'] ?? null);
        $t->same('group3_one_dimensional', $entry['ccitt_fax_coding_boundary']['coding_mode'] ?? null);
        $t->same(false, $entry['ccitt_fax_coding_boundary']['end_of_block'] ?? null);
        $t->same(null, $entry['ccitt_fax_coding_boundary']['end_of_block_marker'] ?? null);
        $encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encodedReview, 'Fake CCITT row EOL owner leak'));
        $t->true(!str_contains($encodedReview, $faxPayload));
    },
    'records effective CCITT Fax DecodeParms defaults and geometry boundaries before RGB preview' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $inlinePayload = "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline CCITT default payload noise) Tj ET final";
        $inlinePlan = $renderer->inlineImageReviewPlan(
            '/W 8 /H 3 /IM true /F /CCF /DP << /Columns 16 /Rows 4 /BlackIs1 true >>',
            $inlinePayload
        );

        $t->same([
            'filter' => 'CCITTFaxDecode',
            'review_only' => true,
            'native_raster_decode' => false,
            'decode_parms_present' => true,
            'invalid_decode_parms' => false,
            'invalid_decode_parms_fields' => [],
            'effective_decode_parms' => [
                'k' => 0,
                'columns' => 16,
                'rows' => 4,
                'black_is_1' => true,
                'encoded_byte_align' => false,
                'end_of_line' => false,
                'end_of_block' => true,
                'damaged_rows_before_error' => 0,
            ],
            'defaults_applied' => [
                'k',
                'encoded_byte_align',
                'end_of_line',
                'end_of_block',
                'damaged_rows_before_error',
            ],
            'dictionary_width' => 8,
            'dictionary_height' => 3,
            'effective_width' => 8,
            'effective_height' => 3,
            'width_source' => 'image_dictionary',
            'height_source' => 'image_dictionary',
            'columns_match_width' => false,
            'rows_match_height' => false,
            'dimension_mismatch' => true,
        ], $inlinePlan['ccitt_fax_decode_boundary']);
        $t->true(!str_contains(json_encode($inlinePlan, JSON_UNESCAPED_SLASHES) ?: '', 'Inline CCITT default payload noise'));

        $before = 'BT /F1 12 Tf 72 720 Td (Before CCITT geometry) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After CCITT geometry) Tj ET';
        $faxPayload = 'BT /F1 12 Tf 72 700 Td (Geometry fallback fax payload noise) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxGeometry 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /Columns 16 /Rows 4 /BlackIs1 true >> /Length " . strlen($faxPayload) . " >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? [];

        $t->same(true, array_key_exists('width', $entry));
        $t->same(true, array_key_exists('height', $entry));
        $t->same(null, $entry['width']);
        $t->same(null, $entry['height']);
        $t->same([
            'filter' => 'CCITTFaxDecode',
            'review_only' => true,
            'native_raster_decode' => false,
            'decode_parms_present' => true,
            'invalid_decode_parms' => false,
            'invalid_decode_parms_fields' => [],
            'effective_decode_parms' => [
                'k' => 0,
                'columns' => 16,
                'rows' => 4,
                'black_is_1' => true,
                'encoded_byte_align' => false,
                'end_of_line' => false,
                'end_of_block' => true,
                'damaged_rows_before_error' => 0,
            ],
            'defaults_applied' => [
                'k',
                'encoded_byte_align',
                'end_of_line',
                'end_of_block',
                'damaged_rows_before_error',
            ],
            'dictionary_width' => null,
            'dictionary_height' => null,
            'effective_width' => 16,
            'effective_height' => 4,
            'width_source' => 'decodeparms_columns',
            'height_source' => 'decodeparms_rows',
            'columns_match_width' => null,
            'rows_match_height' => null,
            'dimension_mismatch' => false,
        ], $entry['ccitt_fax_decode_boundary']);
        $t->same(['Before CCITT geometry', 'After CCITT geometry'], $extractor->extractTextLines($pdf));
        $t->true(!str_contains($extractor->extractPlainText($pdf), 'Geometry fallback fax payload noise'));
    },
    'records nested CCITT Fax soft mask explicit mask and alternate image boundaries before WordPress review' => static function (TestRunner $t): void {
        $extractor = new PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before nested CCITT masks) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After nested CCITT masks) Tj ET';
        $basePayload = "\x00\x01\x02";
        $softPayload = 'BT /F1 12 Tf 72 700 Td (Nested SMask CCITT Payload Noise) Tj ET';
        $maskPayload = 'BT /F1 12 Tf 72 700 Td (Nested Mask CCITT Payload Noise) Tj ET';
        $alternatePayload = 'BT /F1 12 Tf 72 700 Td (Nested Alternate CCITT Payload Noise) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /BaseImage 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMask 7 0 R /Mask 8 0 R /Alternates [<< /Image 9 0 R /DefaultForPrinting true >>] /Length " . strlen($basePayload) . " >>\nstream\n{$basePayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 2 /ColorSpace /DeviceGray /BitsPerComponent 1 /Filter /CCF /DecodeParms << /K -1 /Columns 16 /Rows 2 /BlackIs1 true /EndOfBlock true >> /Length " . strlen($softPayload) . " >>\nstream\n{$softPayload}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 8 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K 0 /Columns 8 /Rows 1 /EndOfBlock true >> /Decode [1 0] /Length " . strlen($maskPayload) . " >>\nstream\n{$maskPayload}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Type /XObject /Subtype /Image /Width 12 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCF /DecodeParms << /K 0 /Columns 12 /Rows 1 /EncodedByteAlign true /EndOfBlock true >> /Length " . strlen($alternatePayload) . " >>\nstream\n{$alternatePayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $softMask = $entry['soft_mask_review'] ?? [];
        $explicitMask = $entry['mask_review'] ?? [];
        $alternate = $entry['alternate_images'][0] ?? [];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Before nested CCITT masks', 'After nested CCITT masks'], $extractor->extractTextLines($pdf));
        $t->same("Before nested CCITT masks\nAfter nested CCITT masks", $plainText);
        $t->same(false, str_contains($plainText, 'Nested SMask CCITT Payload Noise'));
        $t->same(false, str_contains($plainText, 'Nested Mask CCITT Payload Noise'));
        $t->same(false, str_contains($plainText, 'Nested Alternate CCITT Payload Noise'));
        $t->same(['CCF'], $softMask['preview_only_filters'] ?? null);
        $t->same([
            [
                'filter' => 'CCF',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'CCITTFaxDecode',
                    'k' => -1,
                    'columns' => 16,
                    'rows' => 2,
                    'black_is_1' => true,
                    'encoded_byte_align' => null,
                    'end_of_line' => null,
                    'end_of_block' => true,
                    'damaged_rows_before_error' => null,
                ],
            ],
        ], $softMask['filter_details'] ?? null);
        $t->same([
            'filter' => 'CCF',
            'review_only' => true,
            'native_raster_decode' => false,
            'decode_parms_present' => true,
            'invalid_decode_parms' => false,
            'invalid_decode_parms_fields' => [],
            'effective_decode_parms' => [
                'k' => -1,
                'columns' => 16,
                'rows' => 2,
                'black_is_1' => true,
                'encoded_byte_align' => false,
                'end_of_line' => false,
                'end_of_block' => true,
                'damaged_rows_before_error' => 0,
            ],
            'defaults_applied' => [
                'encoded_byte_align',
                'end_of_line',
                'damaged_rows_before_error',
            ],
            'dictionary_width' => 16,
            'dictionary_height' => 2,
            'effective_width' => 16,
            'effective_height' => 2,
            'width_source' => 'image_dictionary',
            'height_source' => 'image_dictionary',
            'columns_match_width' => true,
            'rows_match_height' => true,
            'dimension_mismatch' => false,
        ], $softMask['ccitt_fax_decode_boundary'] ?? null);
        $t->same(['CCITTFaxDecode'], $explicitMask['preview_only_filters'] ?? null);
        $t->same(0, $explicitMask['ccitt_fax_decode_boundary']['effective_decode_parms']['k'] ?? null);
        $t->same(8, $explicitMask['ccitt_fax_decode_boundary']['effective_width'] ?? null);
        $t->same(['CCF'], $alternate['preview_only_filters'] ?? null);
        $t->same(true, $alternate['default_for_printing'] ?? null);
        $t->same(true, $alternate['ccitt_fax_decode_boundary']['effective_decode_parms']['encoded_byte_align'] ?? null);
        $t->same(12, $alternate['ccitt_fax_decode_boundary']['effective_width'] ?? null);
        $encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encodedReview, $softPayload));
        $t->same(false, str_contains($encodedReview, $maskPayload));
        $t->same(false, str_contains($encodedReview, $alternatePayload));
    },
    'records nested CCITT Fax ImageMask polarity for explicit masks and alternates' => static function (TestRunner $t): void {
        $extractor = new PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before nested polarity masks) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After nested polarity masks) Tj ET';
        $basePayload = "\x00\x01\x02";
        $softPayload = 'BT /F1 12 Tf 72 700 Td (Nested polarity SMask CCITT Payload Noise) Tj ET';
        $maskPayload = 'BT /F1 12 Tf 72 700 Td (Nested polarity Mask CCITT Payload Noise) Tj ET';
        $alternatePayload = 'BT /F1 12 Tf 72 700 Td (Nested polarity Alternate CCITT Payload Noise) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /BaseImage 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMask 7 0 R /Mask 8 0 R /Alternates [<< /Image 9 0 R /DefaultForPrinting true >>] /Length " . strlen($basePayload) . " >>\nstream\n{$basePayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 2 /ColorSpace /DeviceGray /BitsPerComponent 1 /Filter /CCF /DecodeParms << /K -1 /Columns 16 /Rows 2 /BlackIs1 true /EndOfBlock true >> /Length " . strlen($softPayload) . " >>\nstream\n{$softPayload}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 8 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K 0 /Columns 8 /Rows 1 /BlackIs1 true /EndOfBlock true >> /Decode [1 0] /Length " . strlen($maskPayload) . " >>\nstream\n{$maskPayload}\nendstream\nendobj\n"
            . "9 0 obj\n<< /Type /XObject /Subtype /Image /Width 12 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCF /DecodeParms << /K 0 /Columns 12 /Rows 1 /BlackIs1 false /EndOfBlock true >> /Length " . strlen($alternatePayload) . " >>\nstream\n{$alternatePayload}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $softMask = $entry['soft_mask_review'] ?? [];
        $explicitMask = $entry['mask_review'] ?? [];
        $alternate = $entry['alternate_images'][0] ?? [];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Before nested polarity masks', 'After nested polarity masks'], $extractor->extractTextLines($pdf));
        $t->same("Before nested polarity masks\nAfter nested polarity masks", $plainText);
        $t->same(false, str_contains($plainText, 'Nested polarity SMask CCITT Payload Noise'));
        $t->same(false, str_contains($plainText, 'Nested polarity Mask CCITT Payload Noise'));
        $t->same(false, str_contains($plainText, 'Nested polarity Alternate CCITT Payload Noise'));
        $t->same(false, array_key_exists('ccitt_fax_imagemask_polarity_boundary', $softMask));
        $t->same([
            'filter' => 'CCITTFaxDecode',
            'review_only' => true,
            'native_raster_decode' => false,
            'image_mask' => true,
            'black_is_1' => true,
            'black_sample_value' => 1,
            'white_sample_value' => 0,
            'image_mask_decode_source' => 'explicit',
            'decode_inverts_stencil' => true,
            'black_sample_opacity' => 0.0,
            'white_sample_opacity' => 1.0,
            'black_sample_is_visible' => false,
            'white_sample_is_visible' => true,
        ], $explicitMask['ccitt_fax_imagemask_polarity_boundary'] ?? null);
        $t->same([
            'filter' => 'CCF',
            'review_only' => true,
            'native_raster_decode' => false,
            'image_mask' => true,
            'black_is_1' => false,
            'black_sample_value' => 0,
            'white_sample_value' => 1,
            'image_mask_decode_source' => 'default',
            'decode_inverts_stencil' => false,
            'black_sample_opacity' => 0.0,
            'white_sample_opacity' => 1.0,
            'black_sample_is_visible' => false,
            'white_sample_is_visible' => true,
        ], $alternate['ccitt_fax_imagemask_polarity_boundary'] ?? null);
        $encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encodedReview, $softPayload));
        $t->same(false, str_contains($encodedReview, $maskPayload));
        $t->same(false, str_contains($encodedReview, $alternatePayload));
    },
    'records CCITT Fax coding mode and terminal marker boundaries without raster decode' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $defaultPlan = $renderer->inlineImageReviewPlan(
            '/W 1728 /H 1 /IM true /F /CCF',
            "\x00\x10\x01\x00\x10\x01\x00\x10\x01"
        );
        $g4Plan = $renderer->inlineImageReviewPlan(
            '/W 16 /H 0 /IM true /F /CCF /DP << /K -1 /EndOfBlock true >>',
            "\x00\x10\x01"
        );
        $mixedPlan = $renderer->inlineImageReviewPlan(
            '/W 24 /H 4 /IM true /F /CCF /DP << /K 4 /EndOfBlock false >>',
            "\x80\x40\x20\x10"
        );

        $t->same([
            'filter' => 'CCITTFaxDecode',
            'review_only' => true,
            'native_raster_decode' => false,
            'decode_parms_present' => false,
            'invalid_decode_parms' => false,
            'effective_k' => 0,
            'coding_mode' => 'group3_one_dimensional',
            'uses_two_dimensional_coding' => false,
            'two_dimensional_line_interval' => null,
            'end_of_block' => true,
            'end_of_block_marker' => 'rtc',
        ], $defaultPlan['ccitt_fax_coding_boundary'] ?? null);
        $t->same([
            'filter' => 'CCITTFaxDecode',
            'review_only' => true,
            'native_raster_decode' => false,
            'decode_parms_present' => true,
            'invalid_decode_parms' => false,
            'effective_k' => -1,
            'coding_mode' => 'group4_two_dimensional',
            'uses_two_dimensional_coding' => true,
            'two_dimensional_line_interval' => null,
            'end_of_block' => true,
            'end_of_block_marker' => 'eofb',
        ], $g4Plan['ccitt_fax_coding_boundary'] ?? null);
        $t->same([
            'filter' => 'CCITTFaxDecode',
            'review_only' => true,
            'native_raster_decode' => false,
            'decode_parms_present' => true,
            'invalid_decode_parms' => false,
            'effective_k' => 4,
            'coding_mode' => 'group3_mixed_two_dimensional',
            'uses_two_dimensional_coding' => true,
            'two_dimensional_line_interval' => 4,
            'end_of_block' => false,
            'end_of_block_marker' => null,
        ], $mixedPlan['ccitt_fax_coding_boundary'] ?? null);

        $extractor = new PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before coding mode CCITT) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After coding mode CCITT) Tj ET';
        $g4Payload = "\x11\x22\x00\x10\x01";
        $mixedPayload = "\x80\x40\x20\x10";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxG4 5 0 R /FaxMixed 6 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 7 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K -1 /Columns 16 /Rows 0 /EndOfBlock true >> /Length " . strlen($g4Payload) . " >>\nstream\n{$g4Payload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 24 /Height 4 /ImageMask true /BitsPerComponent 1 /Filter /CCF /DecodeParms << /K 4 /Columns 24 /Rows 4 /EndOfBlock false >> /Length " . strlen($mixedPayload) . " >>\nstream\n{$mixedPayload}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $g4 = $review['entries'][0] ?? [];
        $mixed = $review['entries'][1] ?? [];

        $t->same(['Before coding mode CCITT', 'After coding mode CCITT'], $extractor->extractTextLines($pdf));
        $t->same('group4_two_dimensional', $g4['ccitt_fax_coding_boundary']['coding_mode'] ?? null);
        $t->same('eofb', $g4['ccitt_fax_coding_boundary']['end_of_block_marker'] ?? null);
        $t->same('group3_mixed_two_dimensional', $mixed['ccitt_fax_coding_boundary']['coding_mode'] ?? null);
        $t->same(4, $mixed['ccitt_fax_coding_boundary']['two_dimensional_line_interval'] ?? null);
        $t->same(null, $mixed['ccitt_fax_coding_boundary']['end_of_block_marker'] ?? null);
        $t->same(false, $g4['decoded_with_current_filters'] ?? null);
        $t->same(false, $mixed['decoded_with_current_filters'] ?? null);
    },
    'uses inline CCITT Fax EOFB and EOL markers before tokenizer fallback boundaries' => static function (TestRunner $t): void {
        $extractor = new PdfTextExtractor();
        $g4Marker = "\x00\x10\x01";
        $rowEolMarker = "\x00\x10\x01";
        $firstPayload = "\x11\x22{$g4Marker}";
        $secondPayload = "\x33\x44{$rowEolMarker}";
        $rawPayload = "\xff";
        $content = "BT /F1 12 Tf 72 720 Td (Before inline CCITT markers) Tj ET\n"
            . "BI /W 1728 /H 0 /IM true /F /CCF /DP << /K -1 /Columns 1728 /Rows 0 /EndOfBlock true >> ID\n"
            . "{$firstPayload}\nEI\n"
            . "BT /F1 12 Tf 72 700 Td (Between inline CCITT markers) Tj ET\n"
            . "BI /W 1728 /H 1 /IM true /F /CCITTFaxDecode /DP << /K 0 /Columns 1728 /Rows 1 /EndOfLine true /EndOfBlock false >> ID\n"
            . "{$secondPayload}\nEI\n"
            . "BT /F1 12 Tf 72 680 Td (After inline CCITT markers) Tj ET\n"
            . "BI /W 1 /H 1 /CS /G /BPC 8 ID{$rawPayload}EI\n"
            . "BT /F1 12 Tf 72 660 Td (After raw inline image) Tj ET";
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            . "%%EOF";

        $expected = [
            'Before inline CCITT markers',
            'Between inline CCITT markers',
            'After inline CCITT markers',
            'After raw inline image',
        ];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, $firstPayload));
        $t->true(!str_contains($plainText, $secondPayload));
        $t->true(!str_contains($plainText, 'CCITTFaxDecode'));
        $t->true(!str_contains($plainText, 'CCF'));
    },
    'decodes escaped CCITT Fax filter and DecodeParms keys for renderer review metadata' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 16 /Height 2 /ImageMask true /BitsPerComponent 1 /Decode [1 0] '
            . '/Nested << /Filter /FlateDecode /DecodeParms << /Columns 1 >> >> '
            . '/Fil#74er /CCITT#46axDecode '
            . '/Decode#50arms << /K -1 /Columns 16 /Rows 2 /Black#49s1 true /EncodedByte#41lign true /EndOf#4cine true /EndOf#42lock false /DamagedRowsBefore#45rror 2 >> >>'
        );

        $t->same(['CCITTFaxDecode'], $plan['image_filters']);
        $t->same([
            [
                'filter' => 'CCITTFaxDecode',
                'preview_only' => true,
                'decode_parms' => [
                    'type' => 'CCITTFaxDecode',
                    'k' => -1,
                    'columns' => 16,
                    'rows' => 2,
                    'black_is_1' => true,
                    'encoded_byte_align' => true,
                    'end_of_line' => true,
                    'end_of_block' => false,
                    'damaged_rows_before_error' => 2,
                ],
            ],
        ], $plan['image_filter_details']);
        $t->same([
            'preview_only_filters' => ['CCITTFaxDecode'],
            'jbig2_globals_present' => false,
            'native_raster_decode' => false,
        ], $plan['image_filter_boundary']);
        $t->same([
            'filter' => 'CCITTFaxDecode',
            'review_only' => true,
            'native_raster_decode' => false,
            'decode_parms_present' => true,
            'invalid_decode_parms' => false,
            'invalid_decode_parms_fields' => [],
            'effective_decode_parms' => [
                'k' => -1,
                'columns' => 16,
                'rows' => 2,
                'black_is_1' => true,
                'encoded_byte_align' => true,
                'end_of_line' => true,
                'end_of_block' => false,
                'damaged_rows_before_error' => 2,
            ],
            'defaults_applied' => [],
            'dictionary_width' => 16,
            'dictionary_height' => 2,
            'effective_width' => 16,
            'effective_height' => 2,
            'width_source' => 'image_dictionary',
            'height_source' => 'image_dictionary',
            'columns_match_width' => true,
            'rows_match_height' => true,
            'dimension_mismatch' => false,
        ], $plan['ccitt_fax_decode_boundary']);
        $t->same('group4_two_dimensional', $plan['ccitt_fax_coding_boundary']['coding_mode'] ?? null);
        $t->same(null, $plan['ccitt_fax_coding_boundary']['end_of_block_marker'] ?? null);
        $t->same([
            'filter' => 'CCITTFaxDecode',
            'review_only' => true,
            'native_raster_decode' => false,
            'image_mask' => true,
            'black_is_1' => true,
            'black_sample_value' => 1,
            'white_sample_value' => 0,
            'image_mask_decode_source' => 'explicit',
            'decode_inverts_stencil' => true,
            'black_sample_opacity' => 0.0,
            'white_sample_opacity' => 1.0,
            'black_sample_is_visible' => false,
            'white_sample_is_visible' => true,
        ], $plan['ccitt_fax_imagemask_polarity_boundary']);
        $t->contains('ccitt_fax_image_filter_review_only', implode(',', $plan['notes']));

        $before = 'BT /F1 12 Tf 72 720 Td (Before escaped CCITT filter) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After escaped CCITT filter) Tj ET';
        $payload = 'BT /F1 12 Tf 72 700 Td (Escaped CCITT payload noise) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /EscapedFax 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 2 /ImageMask true /BitsPerComponent 1 /Fil#74er /CCITT#46axDecode /Decode#50arms << /K -1 /Columns 16 /Rows 2 /Black#49s1 true /EndOf#42lock false >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Before escaped CCITT filter', 'After escaped CCITT filter'], $extractor->extractTextLines($pdf));
        $t->same("Before escaped CCITT filter\nAfter escaped CCITT filter", $plainText);
        $t->same(['CCITTFaxDecode'], $entry['filters'] ?? null);
        $t->same(['CCITTFaxDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(-1, $entry['ccitt_fax_decode_boundary']['effective_decode_parms']['k'] ?? null);
        $t->same(16, $entry['ccitt_fax_decode_boundary']['effective_width'] ?? null);
        $t->same(2, $entry['ccitt_fax_decode_boundary']['effective_height'] ?? null);
        $t->true(!str_contains($plainText, 'Escaped CCITT payload noise'));
    },
    'preserves declared CCF aliases while exposing canonical CCITT filter metadata' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 16 /Height 2 /ImageMask true /BitsPerComponent 1 '
            . '/Filter [/ASCIIHexDecode /CCF] '
            . '/DecodeParms [null << /K -1 /Columns 16 /Rows 2 /BlackIs1 true /EndOfBlock false >>] >>'
        );

        $t->same(['ASCIIHexDecode', 'CCF'], $plan['image_filters']);
        $t->same(['CCF'], $plan['image_filter_boundary']['preview_only_filters']);
        $t->same([
            'declared_filter' => 'CCF',
            'canonical_filter' => 'CCITTFaxDecode',
            'alias_used' => true,
            'non_null_filter_index' => 1,
            'filters_before_ccitt' => ['ASCIIHexDecode'],
            'native_prefix_filters' => ['ASCIIHexDecode'],
            'preview_only_filters_before_ccitt' => [],
            'filters_after_ccitt' => [],
            'native_filters_after_ccitt' => [],
            'preview_only_filters_after_ccitt' => [],
            'ccitt_is_terminal_filter' => true,
            'post_ccitt_filters_present' => false,
            'post_ccitt_filters_block_native_decode' => false,
            'source_filter_preserved' => true,
            'review_only' => true,
            'native_raster_decode' => false,
        ], $plan['ccitt_fax_filter_boundary']);
        $t->same('CCF', $plan['ccitt_fax_decode_boundary']['filter'] ?? null);
        $t->same('CCF', $plan['ccitt_fax_coding_boundary']['filter'] ?? null);
        $t->same('group4_two_dimensional', $plan['ccitt_fax_coding_boundary']['coding_mode'] ?? null);
        $t->same(false, $plan['ccitt_fax_coding_boundary']['end_of_block'] ?? null);

        $extractor = new PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before CCF alias review) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After CCF alias review) Tj ET';
        $payload = 'BT /F1 12 Tf 72 700 Td (CCF alias payload noise) Tj ET';
        $encodedPayload = strtoupper(bin2hex($payload)) . '>';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /AliasFax 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 2 /ImageMask true /BitsPerComponent 1 /Filter [/ASCIIHexDecode /CCF] /DecodeParms [null << /K -1 /Columns 16 /Rows 2 /BlackIs1 true /EndOfBlock false >>] /Length " . strlen($encodedPayload) . " >>\nstream\n{$encodedPayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Before CCF alias review', 'After CCF alias review'], $extractor->extractTextLines($pdf));
        $t->same("Before CCF alias review\nAfter CCF alias review", $plainText);
        $t->true(!str_contains($plainText, 'CCF alias payload noise'));
        $t->same(['ASCIIHexDecode', 'CCF'], $entry['filters'] ?? null);
        $t->same(['CCF'], $entry['preview_only_filters'] ?? null);
        $t->same($plan['ccitt_fax_filter_boundary'], $entry['ccitt_fax_filter_boundary'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encodedReview, $payload));
        $t->true(!str_contains($encodedReview, $encodedPayload));
    },
    'marks filters declared after preview-only CCITT Fax as unreachable native stages' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 '
            . '/Filter [/CCF /ASCIIHexDecode /FlateDecode /DCTDecode] '
            . '/DecodeParms [<< /K 0 /Columns 16 /Rows 1 /EndOfBlock true >> null null null] >>'
        );

        $t->same(['CCF', 'ASCIIHexDecode', 'FlateDecode', 'DCTDecode'], $plan['image_filters']);
        $t->same(['CCF', 'DCTDecode'], $plan['image_filter_boundary']['preview_only_filters']);
        $t->same([
            'declared_filter' => 'CCF',
            'canonical_filter' => 'CCITTFaxDecode',
            'alias_used' => true,
            'non_null_filter_index' => 0,
            'filters_before_ccitt' => [],
            'native_prefix_filters' => [],
            'preview_only_filters_before_ccitt' => [],
            'filters_after_ccitt' => ['ASCIIHexDecode', 'FlateDecode', 'DCTDecode'],
            'native_filters_after_ccitt' => ['ASCIIHexDecode', 'FlateDecode'],
            'preview_only_filters_after_ccitt' => ['DCTDecode'],
            'ccitt_is_terminal_filter' => false,
            'post_ccitt_filters_present' => true,
            'post_ccitt_filters_block_native_decode' => true,
            'source_filter_preserved' => true,
            'review_only' => true,
            'native_raster_decode' => false,
        ], $plan['ccitt_fax_filter_boundary']);
        $t->same(false, $plan['ccitt_fax_decode_boundary']['native_raster_decode'] ?? null);

        $extractor = new PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before post CCITT filters) Tj ET';
        $after = 'BT /F1 12 Tf 72 680 Td (After post CCITT filters) Tj ET';
        $payload = 'BT /F1 12 Tf 72 700 Td (Post CCITT filter payload noise) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /PostFax 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter [/CCF /ASCIIHexDecode /FlateDecode /DCTDecode] /DecodeParms [<< /K 0 /Columns 16 /Rows 1 /EndOfBlock true >> null null null] /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Before post CCITT filters', 'After post CCITT filters'], $extractor->extractTextLines($pdf));
        $t->same("Before post CCITT filters\nAfter post CCITT filters", $plainText);
        $t->true(!str_contains($plainText, 'Post CCITT filter payload noise'));
        $t->same($plan['ccitt_fax_filter_boundary'], $entry['ccitt_fax_filter_boundary'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->true(!str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $payload));
    },
    'records native prefix decoded bytes before CCITT Fax soft-mask review handoff' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $faxBytes = "\x00\x10\x01";
        $encodedFaxBytes = strtoupper(bin2hex($faxBytes)) . '>';
        $plan = $renderer->imageColorSpaceSoftMaskPlan(
            '<< /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMask 41 0 R >>',
            [
                41 => "<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ColorSpace /DeviceGray /BitsPerComponent 1 /Filter [/ASCIIHexDecode /CCF] /DecodeParms [null << /K -1 /Columns 16 /Rows 0 /EndOfBlock true >>] /Length " . strlen($encodedFaxBytes) . " >>\nstream\n{$encodedFaxBytes}\nendstream",
            ]
        );

        $boundary = $plan['soft_mask_filter_boundary'];

        $t->same(true, $plan['soft_mask']['present'] ?? null);
        $t->same(['ASCIIHexDecode', 'CCF'], $boundary['filters']);
        $t->same(['CCF'], $boundary['preview_only_filters']);
        $t->same(['CCF'], $boundary['unsupported_filters']);
        $t->same(strlen($encodedFaxBytes), $boundary['raw_length']);
        $t->same(null, $boundary['decoded_length']);
        $t->same(null, $boundary['decoded_sha256']);
        $t->same(null, $boundary['decoded_preview_hex']);
        $t->same([], $boundary['decoded_sample_bytes']);
        $t->same(false, $boundary['decoded_with_current_filters']);
        $t->same(false, $boundary['decode_failed']);
        $t->same(true, $boundary['uses_current_object_map']);
        $t->same(true, $boundary['native_prefix_decoded']);
        $t->same(strlen($faxBytes), $boundary['native_prefix_decoded_length']);
        $t->same(hash('sha256', $faxBytes), $boundary['native_prefix_decoded_sha256']);
        $t->same(strtoupper(bin2hex($faxBytes)), $boundary['native_prefix_decoded_preview_hex']);
        $t->same('CCF', $boundary['stopped_before_filter']);
        $t->contains('soft_mask_stream_filter_preview_only', implode(',', $plan['notes']));
        $t->contains('soft_mask_stream_native_prefix_decoded_before_preview_only', implode(',', $plan['notes']));
        $t->true(!str_contains(json_encode($plan, JSON_UNESCAPED_SLASHES) ?: '', $faxBytes));
    },
    'classifies escaped ImageMask CCITT XObjects without Subtype before WordPress review' => static function (TestRunner $t): void {
        $extractor = new PdfTextExtractor();
        $before = 'BT /F1 12 Tf 72 720 Td (Before escaped ImageMask CCITT) Tj ET';
        $paint = 'q 16 0 0 1 72 700 cm /FaxMask Do Q';
        $after = 'BT /F1 12 Tf 72 680 Td (After escaped ImageMask CCITT) Tj ET';
        $payload = 'BT /F1 12 Tf 72 700 Td (Escaped ImageMask CCITT payload noise) Tj ET';
        $pdf = "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxMask 5 0 R >> >> >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 7 0 R 6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "5 0 obj\n<< /Type /XObject /Width 16 /Height 1 /Image#4Dask true /Filter /CCF /DecodeParms << /K 0 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock true >> /Decode [1 0] /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Length " . strlen($paint) . " >>\nstream\n{$paint}\nendstream\nendobj\n"
            . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Before escaped ImageMask CCITT', 'After escaped ImageMask CCITT'], $extractor->extractTextLines($pdf));
        $t->same("Before escaped ImageMask CCITT\nAfter escaped ImageMask CCITT", $plainText);
        $t->true(!str_contains($plainText, 'Escaped ImageMask CCITT payload noise'));
        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same('FaxMask', $entry['resource_name'] ?? null);
        $t->same(true, $entry['invoked'] ?? null);
        $t->same(true, $entry['image_mask'] ?? null);
        $t->same(16, $entry['width'] ?? null);
        $t->same(1, $entry['height'] ?? null);
        $t->same(1, $entry['bits_per_component'] ?? null);
        $t->same(['CCF'], $entry['filters'] ?? null);
        $t->same(['CCF'], $entry['preview_only_filters'] ?? null);
        $t->same([
            'declared_filter' => 'CCF',
            'canonical_filter' => 'CCITTFaxDecode',
            'alias_used' => true,
            'non_null_filter_index' => 0,
            'filters_before_ccitt' => [],
            'native_prefix_filters' => [],
            'preview_only_filters_before_ccitt' => [],
            'filters_after_ccitt' => [],
            'native_filters_after_ccitt' => [],
            'preview_only_filters_after_ccitt' => [],
            'ccitt_is_terminal_filter' => true,
            'post_ccitt_filters_present' => false,
            'post_ccitt_filters_block_native_decode' => false,
            'source_filter_preserved' => true,
            'review_only' => true,
            'native_raster_decode' => false,
        ], $entry['ccitt_fax_filter_boundary'] ?? null);
        $t->same([
            'filter' => 'CCF',
            'review_only' => true,
            'native_raster_decode' => false,
            'decode_parms_present' => true,
            'invalid_decode_parms' => false,
            'invalid_decode_parms_fields' => [],
            'effective_decode_parms' => [
                'k' => 0,
                'columns' => 16,
                'rows' => 1,
                'black_is_1' => true,
                'encoded_byte_align' => false,
                'end_of_line' => false,
                'end_of_block' => true,
                'damaged_rows_before_error' => 0,
            ],
            'defaults_applied' => [
                'encoded_byte_align',
                'end_of_line',
                'damaged_rows_before_error',
            ],
            'dictionary_width' => 16,
            'dictionary_height' => 1,
            'effective_width' => 16,
            'effective_height' => 1,
            'width_source' => 'image_dictionary',
            'height_source' => 'image_dictionary',
            'columns_match_width' => true,
            'rows_match_height' => true,
            'dimension_mismatch' => false,
        ], $entry['ccitt_fax_decode_boundary'] ?? null);
        $t->same([
            'filter' => 'CCF',
            'review_only' => true,
            'native_raster_decode' => false,
            'image_mask' => true,
            'black_is_1' => true,
            'black_sample_value' => 1,
            'white_sample_value' => 0,
            'image_mask_decode_source' => 'explicit',
            'decode_inverts_stencil' => true,
            'black_sample_opacity' => 0.0,
            'white_sample_opacity' => 1.0,
            'black_sample_is_visible' => false,
            'white_sample_is_visible' => true,
        ], $entry['ccitt_fax_imagemask_polarity_boundary'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encodedReview, $payload));
    },
];
