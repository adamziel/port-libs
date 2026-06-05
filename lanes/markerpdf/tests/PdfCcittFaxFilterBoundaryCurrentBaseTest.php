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
        $t->contains('ccitt_fax_image_filter_review_only', implode(',', $plan['notes']));
        $t->contains('inline_ccitt_fax_image_filter_review_only', implode(',', $plan['notes']));
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
];
