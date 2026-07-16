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
$decodedImageBytes = 'ABCDEFGH';
$compressedPayload = gzcompress($decodedImageBytes, 0);
if (!is_string($compressedPayload)) {
    throw new RuntimeException('Unable to build duplicate inline DecodeParms smoke fixture.');
}

$duplicateDictionary = '/W 8 /H 1 /CS /G /BPC 8 /F /Fl /DP << /Predictor 1 >> /DecodeParms << /Predictor 12 /Columns 0 >> /D [0 1]';
$cleanDictionary = '/W 8 /H 1 /CS /G /BPC 8 /F /Fl /DP << /Predictor 1 >> /D [0 1]';
$payload = 'abc EI BT /F1 12 Tf 72 690 Td (Duplicate Inline DecodeParms WordPress Noise) Tj ET rawtail';
$content = "BT /F1 12 Tf 72 720 Td (Before Duplicate DecodeParms Inline) Tj ET\n"
    . "BI {$duplicateDictionary} ID\n"
    . $payload . "\nEI\n"
    . "BT /F1 12 Tf 72 704 Td (After Duplicate DecodeParms Inline) Tj ET";

$lines = $extractor->extractTextLines($pdfWithContent($content));
$plainText = implode("\n", $lines);
$duplicateReview = $renderer->inlineImageReviewPlan($duplicateDictionary, $compressedPayload);
$duplicateDecodeParms = $duplicateReview['image_filter_details'][0]['decode_parms'] ?? null;

$duplicatePreviewFailedClosed = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows($duplicateDictionary, $compressedPayload, [], 8);
} catch (InvalidArgumentException) {
    $duplicatePreviewFailedClosed = true;
}

$cleanPreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows($cleanDictionary, $compressedPayload, [], 8);
$payloadExcluded = $lines === ['Before Duplicate DecodeParms Inline', 'After Duplicate DecodeParms Inline']
    && !str_contains($plainText, 'Duplicate Inline DecodeParms WordPress Noise')
    && !str_contains($plainText, 'rawtail')
    && !str_contains($plainText, 'abc EI');

if (
    !is_array($duplicateDecodeParms)
    || ($duplicateDecodeParms['decode_parms_review'] ?? null) !== 'duplicate_native_decodeparms_declaration_fail_closed'
    || ($duplicateDecodeParms['valid_decode_parms'] ?? true) !== false
    || ($duplicateReview['inline_image']['unsupported_filters'] ?? []) !== ['FlateDecode']
    || ($duplicateReview['inline_image']['native_raster_decode'] ?? true) !== false
    || !$duplicatePreviewFailedClosed
    || !$payloadExcluded
    || ($cleanPreview['image_stream']['decoded_sha256'] ?? null) !== hash('sha256', $decodedImageBytes)
) {
    throw new RuntimeException('Duplicate inline DecodeParms boundary did not fail closed before WordPress import.');
}

echo '<!-- markerpdf:pdf-inline-image-duplicate-decodeparms-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-inline-image-duplicate-decodeparms-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image',
    'filters' => $duplicateReview['image_filters'],
    'unsupported_filters' => $duplicateReview['inline_image']['unsupported_filters'],
    'decode_parms_review' => $duplicateDecodeParms['decode_parms_review'] ?? null,
    'duplicate_decode_parms_declaration_count' => $duplicateDecodeParms['duplicate_decode_parms_declaration_count'] ?? null,
    'native_raster_decode' => $duplicateReview['inline_image']['native_raster_decode'],
    'duplicate_preview_failed_closed' => $duplicatePreviewFailedClosed,
    'clean_decodeparms_preview_decoded_sha256' => $cleanPreview['image_stream']['decoded_sha256'] ?? null,
    'inline_payload_excluded_from_text' => $payloadExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
