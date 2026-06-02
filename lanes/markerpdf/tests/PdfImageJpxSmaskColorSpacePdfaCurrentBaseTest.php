<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$jpxPdfaFixture = static function (): array {
    $profileBytes = 'Current JPX PDF/A RGB OutputIntent profile bytes';
    $compressedProfile = gzcompress($profileBytes);
    if (!is_string($compressedProfile)) {
        throw new RuntimeException('Unable to compress PDF/A profile fixture.');
    }

    $jpxPayload = "\xff\x4fJPX current PDF/A image opacity payload stays review-only\xff\xd9";
    $staleJpxPayload = "\xff\x4fStale JPX payload must not affect current metadata\xff\xd9";
    $maskBytes = "\x00\x80\xff";
    $content = 'BT /F1 12 Tf 72 720 Td (Current JPX PDF/A body) Tj ET q /ImJPX Do Q';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale JPX PDF/A body) Tj ET';

    $objects = [
        6 => "<< /Subtype /Image /Filter /JPXDecode /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMaskInData 1 /SMask 12 0 R /Length " . strlen($jpxPayload) . " >>\nstream\n{$jpxPayload}\nendstream",
        7 => "<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($compressedProfile) . " >>\nstream\n{$compressedProfile}\nendstream",
        9 => '<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Current JPX RGB PDF/A) /Info (Current JPX root RGB profile) /DestOutputProfile 7 0 R >>',
        12 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode [1 0] /Length " . strlen($maskBytes) . " >>\nstream\n{$maskBytes}\nendstream",
    ];

    $pdf = "%PDF-2.0\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /OutputIntents [9 0 R] >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /XObject << /ImJPX 6 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, $objects[6]);
    $addObject(7, $objects[7]);
    $addObject(9, $objects[9]);
    $addObject(12, $objects[12]);
    $addObject(60, '<< /Title (Current JPX PDF/A Title) /Producer (Current JPX PDF/A Producer) >>');

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber < 91; $objectNumber++) {
        if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 90)) {
            $rows .= pack('CNn', 0, 0, $objectNumber === 0 ? 65535 : 0);
            continue;
        }

        $rows .= pack('CNn', 1, $objectNumber === 90 ? $xrefOffset : $offsets[$objectNumber], 0);
    }
    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress current JPX PDF/A xref stream.');
    }

    $pdf .= "90 0 obj\n"
        . '<< /Type /XRef /Size 91 /Root 1 0 R /Info 60 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OutputIntents [19 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Subtype /Image /Filter /JPXDecode /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length " . strlen($staleJpxPayload) . " >>\nstream\n{$staleJpxPayload}\nendstream\nendobj\n"
        . "19 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Stale JPX PDF/A) /Info (Stale profile) /DestOutputProfile 7 0 R >>\nendobj\n"
        . "60 0 obj\n<< /Title (Stale JPX PDF/A Title) /Producer (Stale Producer) >>\nendobj\n";

    return [$pdf, $objects, $jpxPayload, $profileBytes, $maskBytes];
};

return [
    'preserves PDF/A OutputIntent color context for JPX SMaskInData review on current xref metadata' => static function (TestRunner $t) use ($jpxPdfaFixture): void {
        [$pdf, $objects, $jpxPayload, $profileBytes, $maskBytes] = $jpxPdfaFixture();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $renderer = new PdfImageRenderer();

        $plan = $renderer->jpxSoftMaskColorSpacePdfaReviewPlan($objects[6], $objects, $metadata);

        $t->same('Current JPX PDF/A body', $plainText);
        $t->same('Current JPX PDF/A Title', $metadata['title']);
        $t->same(['Current JPX RGB PDF/A'], $metadata['pdfa']['output_condition_identifiers']);
        $t->same([hash('sha256', $profileBytes)], $metadata['pdfa']['profile_sha256']);
        $t->true(!str_contains(json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '', 'Stale JPX PDF/A'));

        $t->same('DeviceRGB', $plan['source_color_space']);
        $t->same(['JPXDecode'], $plan['image_filters']);
        $t->same([
            'filters' => ['JPXDecode'],
            'preview_only_filters' => ['JPXDecode'],
            'unsupported_filters' => ['JPXDecode'],
            'raw_length' => strlen($jpxPayload),
            'decoded_length' => null,
            'decoded_sha256' => null,
            'decoded_preview_hex' => null,
            'decoded_with_current_filters' => false,
            'decode_failed' => false,
        ], $plan['image_stream']);
        $t->same(true, $plan['review_only_image_stream']);
        $t->same(false, $plan['native_jpx_raster_decode']);
        $t->same([
            'present' => true,
            'value' => 1,
            'valid_value' => true,
            'filter_is_jpx' => true,
            'uses_embedded_soft_mask' => true,
            'encoded_soft_mask_values' => true,
            'preblended_with_matte' => false,
            'external_soft_mask_present' => true,
            'external_soft_mask_ignored' => true,
            'ignored_without_jpx' => false,
            'review_only' => true,
        ], $plan['jpx_soft_mask_in_data']);
        $t->same(null, $plan['soft_mask']);
        $t->same(null, $plan['soft_mask_filter_boundary']);
        $t->same('jpx_embedded_soft_mask_review_only_rgb_preview', $plan['alpha_output_mode']);
        $t->same([
            'present' => true,
            'source' => 'document_metadata_pdfa_output_intents',
            'output_condition_identifiers' => ['Current JPX RGB PDF/A'],
            'profile_sha256' => [hash('sha256', $profileBytes)],
            'profile_count' => 1,
            'review_only' => true,
            'payload_included' => false,
        ], $plan['pdfa_output_intent']);
        $t->same([
            'source_color_space' => 'DeviceRGB',
            'profile_source' => 'pdfa_output_intent',
            'pdfa_output_intent_present' => true,
            'pdfa_output_intent_applies_to_rgb_preview' => true,
            'image_uses_icc_profile' => false,
            'image_uses_calibrated_color_space' => false,
            'image_uses_alternate_color_space' => false,
            'image_uses_indexed_color_space' => false,
            'output_condition_identifiers' => ['Current JPX RGB PDF/A'],
            'profile_sha256' => [hash('sha256', $profileBytes)],
            'review_only' => true,
        ], $plan['color_management']);
        $t->same(true, $plan['pdfa_output_intent_applies_before_rgb']);

        $encodedPlan = json_encode($plan, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encodedPlan, $jpxPayload));
        $t->true(!str_contains($encodedPlan, $maskBytes));
        $notes = implode(',', $plan['notes']);
        $t->contains('jpx_embedded_soft_mask_review_before_rgb_conversion', $notes);
        $t->contains('jpx_smaskindata_ignores_external_smask', $notes);
        $t->contains('pdfa_output_intent_review_before_rgb_conversion', $notes);
        $t->contains('pdfa_output_intent_supplies_device_color_profile', $notes);
        $t->contains('jpx_embedded_soft_mask_preserved_with_pdfa_output_intent', $notes);
        $t->contains('jpx_pdfa_image_stream_review_only_before_rgb_conversion', $notes);
    },
    'keeps image ICCBased color space authoritative while preserving PDF/A context for JPX external SMask' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();
        $imageProfile = 'Image-local RGB ICC profile bytes';
        $documentProfileHash = hash('sha256', 'Document PDF/A profile bytes');
        $maskBytes = "\x00\x80\xff";
        $compressedMask = gzcompress($maskBytes);
        if (!is_string($compressedMask)) {
            throw new RuntimeException('Unable to compress JPX PDF/A mask fixture.');
        }

        $jpxPayload = "\xff\x4fICCBased JPX raster bytes stay review-only\xff\xd9";
        $objects = [
            30 => "<< /N 3 /Alternate /DeviceRGB /Length " . strlen($imageProfile) . " >>\nstream\n{$imageProfile}\nendstream",
            31 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0] /Length " . strlen($compressedMask) . " >>\nstream\n{$compressedMask}\nendstream",
        ];
        $imageObject = "<< /Subtype /Image /Filter /JPXDecode /Width 3 /Height 1 /ColorSpace [/ICCBased 30 0 R] /BitsPerComponent 8 /SMask 31 0 R /Length " . strlen($jpxPayload) . " >>\nstream\n{$jpxPayload}\nendstream";

        $plan = $renderer->jpxSoftMaskColorSpacePdfaReviewPlan($imageObject, $objects, [
            'pdfa' => [
                'has_output_intent' => true,
                'output_condition_identifiers' => ['Document PDF/A RGB'],
                'profile_sha256' => [$documentProfileHash],
            ],
        ]);

        $t->same('ICCBased', $plan['source_color_space']);
        $t->same(true, $plan['uses_icc_profile']);
        $t->same([
            'components' => 3,
            'alternate_color_space' => 'DeviceRGB',
            'range' => [],
            'length' => strlen($imageProfile),
        ], $plan['icc_profile']);
        $t->same([
            'present' => true,
            'source' => 'document_metadata_pdfa_output_intents',
            'output_condition_identifiers' => ['Document PDF/A RGB'],
            'profile_sha256' => [$documentProfileHash],
            'profile_count' => 1,
            'review_only' => true,
            'payload_included' => false,
        ], $plan['pdfa_output_intent']);
        $t->same('image_icc_profile', $plan['color_management']['profile_source']);
        $t->same(false, $plan['color_management']['pdfa_output_intent_applies_to_rgb_preview']);
        $t->same(false, $plan['pdfa_output_intent_applies_before_rgb']);
        $t->same([
            'present' => true,
            'subtype' => 'Image',
            'width' => 3,
            'height' => 1,
            'color_space' => 'DeviceGray',
            'components' => 1,
            'bits_per_component' => 8,
            'decode' => [
                'ranges' => [
                    ['min' => 1.0, 'max' => 0.0],
                ],
                'component_count' => 1,
                'expected_components' => 1,
                'valid_for_components' => true,
                'identity' => false,
                'inverted_components' => [0],
                'source' => 'explicit',
            ],
            'opacity_for_zero' => 1.0,
            'opacity_for_max' => 0.0,
            'decode_inverted' => true,
            'decode_component_mismatch' => false,
            'matte' => null,
            'interpolate' => null,
        ], $plan['soft_mask']);
        $t->same([
            'present' => true,
            'source_object' => 31,
            'filters' => ['FlateDecode'],
            'preview_only_filters' => [],
            'unsupported_filters' => [],
            'raw_length' => strlen($compressedMask),
            'decoded_length' => strlen($maskBytes),
            'decoded_sha256' => hash('sha256', $maskBytes),
            'decoded_preview_hex' => strtoupper(bin2hex($maskBytes)),
            'decoded_sample_bytes' => [0, 128, 255],
            'decoded_with_current_filters' => true,
            'decode_failed' => false,
            'uses_current_object_map' => true,
        ], $plan['soft_mask_filter_boundary']);

        $notes = implode(',', $plan['notes']);
        $t->contains('icc_profile_color_space', $notes);
        $t->contains('soft_mask_stream_filters_decoded_before_rgb_conversion', $notes);
        $t->contains('image_icc_profile_precedes_pdfa_output_intent_for_preview', $notes);
        $t->contains('external_soft_mask_preserved_with_pdfa_output_intent', $notes);
        $t->contains('pdfa_output_intent_preserved_as_document_color_context', $notes);
    },
    'rejects non-JPX image streams for the PDF/A JPX review boundary' => static function (TestRunner $t): void {
        $renderer = new PdfImageRenderer();

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $renderer->jpxSoftMaskColorSpacePdfaReviewPlan(
                "<< /Subtype /Image /Filter /FlateDecode /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length 1 >>\nstream\nX\nendstream",
                [],
                ['pdfa' => ['has_output_intent' => true]]
            )
        );
    },
];
