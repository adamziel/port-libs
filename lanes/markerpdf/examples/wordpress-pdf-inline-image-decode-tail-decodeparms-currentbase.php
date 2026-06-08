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
$sample = 'Z';
$compressedSample = gzcompress($sample, 0);
if (!is_string($compressedSample)) {
    throw new RuntimeException('Unable to build inline Decode tail smoke fixture.');
}

$dictionary = '/W 1 /H 1 /CS /G /BPC 8 /F /Fl /D [1 0] 99 0 R /DP << /Predictor 1 >>';
$payload = $compressedSample
    . 'ZZ EI BT /F1 12 Tf 72 690 Td (Decode Tail DecodeParms Inline Noise) Tj ET rawtail';
$content = "BT /F1 12 Tf 72 720 Td (Before Decode Tail Inline Image) Tj ET\n"
    . "BI {$dictionary} ID\n{$payload}\nEI\n"
    . "BT /F1 12 Tf 72 704 Td (After Decode Tail Inline Image) Tj ET";
$lines = $extractor->extractTextLines($pdfWithContent($content));
$plainText = implode("\n", $lines);
$review = $renderer->inlineImageReviewPlan($dictionary, $compressedSample);

$previewFailedClosed = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $compressedSample, [], 1);
} catch (InvalidArgumentException) {
    $previewFailedClosed = true;
}

$decodeParms = $review['image_filter_details'][0]['decode_parms'] ?? [];
$payloadExcluded = $lines === ['Before Decode Tail Inline Image', 'After Decode Tail Inline Image']
    && !str_contains($plainText, 'Decode Tail DecodeParms Inline Noise')
    && !str_contains($plainText, 'rawtail')
    && !str_contains($plainText, 'ZZ EI')
    && !str_contains($plainText, '99 0 R');

if (
    !$previewFailedClosed
    || !$payloadExcluded
    || ($review['image_filters'] ?? []) !== ['FlateDecode']
    || ($decodeParms['type'] ?? null) !== 'FlateDecode'
    || ($decodeParms['predictor'] ?? null) !== 1
    || ($decodeParms['valid_decode_parms'] ?? null) !== true
    || ($review['image_decode']['source'] ?? null) !== 'invalid'
    || ($review['image_decode_component_mismatch'] ?? false) !== true
    || ($review['inline_image']['native_raster_decode'] ?? true) !== false
) {
    throw new RuntimeException('Inline Decode tail DecodeParms smoke did not fail closed.');
}

$metadata = [
    'source' => 'native-pdf-inline-image-decode-tail-decodeparms-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.images.extract.extract_images',
    'inline_decode_tail_rejected' => true,
    'decodeparms_preserved_after_decode_tail' => true,
    'inline_filter_details' => $review['image_filter_details'],
    'image_decode_source' => $review['image_decode']['source'],
    'image_decode_valid_for_components' => $review['image_decode']['valid_for_components'],
    'preview_failed_closed' => $previewFailedClosed,
    'native_raster_decode' => $review['inline_image']['native_raster_decode'],
    'inline_payload_excluded_from_text' => $payloadExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-inline-image-decode-tail-decodeparms-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
