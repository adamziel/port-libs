<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

$renderer = new PdfImageRenderer();
$extractor = new PdfTextExtractor();
$decodedImageBytes = 'ABC';
$compressedPayload = gzcompress($decodedImageBytes, 0);
if (!is_string($compressedPayload)) {
    throw new RuntimeException('Unable to build extra inline DecodeParms smoke fixture.');
}

$extraDictionary = '/W 3 /H 1 /CS /G /BPC 8 /F /Fl /DP [null << /Predictor 1 >>] /D [0 1]';
$cleanDictionary = '/W 3 /H 1 /CS /G /BPC 8 /F /Fl /DP << /Predictor 1 >> /D [0 1]';
$extraPlan = $renderer->inlineImageReviewPlan($extraDictionary, $compressedPayload);

$previewFailedClosed = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows($extraDictionary, $compressedPayload, [], 3);
} catch (InvalidArgumentException) {
    $previewFailedClosed = true;
}

$cleanPreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows($cleanDictionary, $compressedPayload, [], 3);
$content = "BT /F1 12 Tf 72 720 Td (Before Extra Inline DecodeParms) Tj ET\n"
    . "BI {$extraDictionary} ID\n"
    . "abc EI BT /F1 12 Tf 72 696 Td (Extra Inline DecodeParms Payload Noise) Tj ET rawtail\nEI\n"
    . "BT /F1 12 Tf 72 704 Td (After Extra Inline DecodeParms) Tj ET";
$lines = $extractor->extractTextLines($pdfWithContent($content));
$plainText = implode("\n", $lines);
$expectedLines = [
    'Before Extra Inline DecodeParms',
    'After Extra Inline DecodeParms',
];
$payloadExcluded = $lines === $expectedLines
    && !str_contains($plainText, 'Extra Inline DecodeParms Payload Noise')
    && !str_contains($plainText, 'rawtail')
    && !str_contains($plainText, 'abc EI');

$decodeParmsReview = $extraPlan['image_filter_details'][0]['decode_parms'] ?? [];
if (
    !$previewFailedClosed
    || !$payloadExcluded
    || ($extraPlan['image_filters'] ?? []) !== ['FlateDecode']
    || ($extraPlan['inline_image']['unsupported_filters'] ?? []) !== ['FlateDecode']
    || ($extraPlan['inline_image']['native_raster_decode'] ?? true) !== false
    || ($extraPlan['image_filter_boundary']['native_raster_decode'] ?? true) !== false
    || ($decodeParmsReview['decode_parms_review'] ?? null) !== 'unaligned_native_decodeparms_fail_closed'
    || ($decodeParmsReview['unapplied_decode_parms_slots'] ?? []) !== [1]
    || ($cleanPreview['image_stream']['decoded_preview_hex'] ?? null) !== '414243'
) {
    throw new RuntimeException('Extra inline DecodeParms smoke did not fail closed.');
}

$metadata = [
    'source' => 'native-pdf-inline-image-extra-decodeparms-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image',
    'extra_decodeparms_inline_filter_preview_failed_closed' => $previewFailedClosed,
    'extra_decodeparms_inline_filter_recorded' => $extraPlan['image_filters'],
    'extra_decodeparms_inline_filter_unsupported' => $extraPlan['inline_image']['unsupported_filters'],
    'extra_decodeparms_native_raster_decode' => $extraPlan['inline_image']['native_raster_decode'],
    'extra_decodeparms_boundary_native_raster_decode' => $extraPlan['image_filter_boundary']['native_raster_decode'],
    'extra_decodeparms_review' => $decodeParmsReview['decode_parms_review'],
    'extra_decodeparms_alignment' => $decodeParmsReview['decode_parms_alignment'],
    'extra_decodeparms_unapplied_slots' => $decodeParmsReview['unapplied_decode_parms_slots'],
    'clean_inline_preview_hex' => $cleanPreview['image_stream']['decoded_preview_hex'],
    'inline_payload_excluded_from_text' => $payloadExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-inline-image-extra-decodeparms-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
