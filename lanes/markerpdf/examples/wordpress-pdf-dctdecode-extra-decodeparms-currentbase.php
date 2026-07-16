<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before Extra DCT DecodeParms Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Extra DCT DecodeParms Import) Tj ET';
$payloadText = 'BT /F1 12 Tf 72 700 Td (WordPress Extra DCT DecodeParms Leak) Tj ET';
$jpegPayload = "\xff\xd8"
    . "\xff\xc0\x00\x11\x08\x00\x01\x00\x01\x04"
    . "\x01\x11\x00\x02\x11\x00\x03\x11\x00\x04\x11\x00"
    . $payloadText
    . "\xff\xd9";
$decodeParms = '[<< /ColorTransform 1 >> << /ColorTransform 2 >>]';
$imageDictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter [/DCTDecode] /DecodeParms ' . $decodeParms . ' /Length ' . strlen($jpegPayload) . ' >>';
$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n{$imageDictionary}\nstream\n{$jpegPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$photoReview = $review['entries'][0] ?? [];
$softMaskPlan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary);
$colorPlan = $renderer->dctDecodeImageColorPlan($imageDictionary, $jpegPayload);
$expected = ['Before Extra DCT DecodeParms Import', 'After Extra DCT DecodeParms Import'];
$rendererDecodeParms = $softMaskPlan['image_filter_details'][0]['decode_parms'] ?? [];
$extractorDecodeParms = $photoReview['filter_details'][0]['decode_parms'] ?? [];
$payloadExcluded = !str_contains($plainText, 'WordPress Extra DCT DecodeParms Leak')
    && !str_contains($plainText, 'endstream');
$failClosed = ($rendererDecodeParms['decode_parms_review'] ?? null) === 'unaligned_dctdecode_decodeparms_fail_closed'
    && ($rendererDecodeParms['decode_parms_alignment'] ?? null) === 'unapplied_filter_slot'
    && ($rendererDecodeParms['valid_color_transform'] ?? null) === false
    && ($rendererDecodeParms['unapplied_decode_parms_slots'] ?? null) === [1]
    && $rendererDecodeParms === $extractorDecodeParms
    && ($colorPlan['decode_parms_color_transform_valid'] ?? null) === false
    && ($colorPlan['effective_color_transform'] ?? null) === 0;

if ($lines !== $expected || !$payloadExcluded || !$failClosed) {
    throw new RuntimeException('DCTDecode extra DecodeParms slot was not failed closed before WordPress import handoff.');
}

echo '<!-- markerpdf:pdf-dctdecode-extra-decodeparms-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-extra-decodeparms-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image',
    'stream_filters' => ['DCTDecode'],
    'decode_parms_alignment' => $rendererDecodeParms['decode_parms_alignment'] ?? null,
    'decode_parms_review' => $rendererDecodeParms['decode_parms_review'] ?? null,
    'filter_slot_count' => $rendererDecodeParms['filter_slot_count'] ?? null,
    'decode_parms_slot_count' => $rendererDecodeParms['decode_parms_slot_count'] ?? null,
    'unapplied_decode_parms_slots' => $rendererDecodeParms['unapplied_decode_parms_slots'] ?? [],
    'decode_parms_color_transform_valid' => $colorPlan['decode_parms_color_transform_valid'] ?? null,
    'effective_color_transform' => $colorPlan['effective_color_transform'] ?? null,
    'uses_ycck_transform' => $colorPlan['uses_ycck_transform'] ?? null,
    'dctdecode_image_payload_excluded_from_text' => $payloadExcluded,
    'xobject_preview_only_filters' => $photoReview['preview_only_filters'] ?? [],
    'xobject_native_raster_decode' => $photoReview['native_raster_decode'] ?? null,
    'xobject_decoded_with_current_filters' => $photoReview['decoded_with_current_filters'] ?? null,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
