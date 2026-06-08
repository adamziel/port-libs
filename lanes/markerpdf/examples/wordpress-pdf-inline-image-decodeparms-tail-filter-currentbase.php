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

$pngSubPredictorEncode = static function (string $rowBytes, int $columns): string {
    if (strlen($rowBytes) !== $columns) {
        throw new InvalidArgumentException('Inline DecodeParms tail smoke expects one complete row.');
    }

    $encoded = "\x01";
    for ($index = 0; $index < $columns; $index++) {
        $left = $index === 0 ? 0 : ord($rowBytes[$index - 1]);
        $encoded .= chr((ord($rowBytes[$index]) - $left + 256) % 256);
    }

    return $encoded;
};

$renderer = new PdfImageRenderer();
$extractor = new PdfTextExtractor();
$decodedSamples = 'ABC';
$compressedSamples = gzcompress($pngSubPredictorEncode($decodedSamples, 3), 0);
if (!is_string($compressedSamples)) {
    throw new RuntimeException('Unable to build inline DecodeParms tail filter smoke fixture.');
}

$dictionary = '/W 3 /H 1 /CS /G /BPC 8 '
    . '/DP << /Predictor 12 /Columns 3 /Colors 1 /BitsPerComponent 8 >> 99 0 R '
    . '/F /Fl /D [1 0]';
$surplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (DecodeParms Tail Inline Noise) Tj ET rawtail';
$content = "BT /F1 12 Tf 72 720 Td (Before DecodeParms Tail Inline) Tj ET\n"
    . "BI {$dictionary} ID {$compressedSamples}{$surplus}\nEI\n"
    . "BT /F1 12 Tf 72 704 Td (After DecodeParms Tail Inline) Tj ET";
$lines = $extractor->extractTextLines($pdfWithContent($content));
$plainText = implode("\n", $lines);
$review = $renderer->inlineImageReviewPlan($dictionary, $compressedSamples . $surplus);

$previewFailedClosed = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $compressedSamples, [], 3);
} catch (InvalidArgumentException) {
    $previewFailedClosed = true;
}

$decodeParms = $review['image_filter_details'][0]['decode_parms'] ?? [];
$payloadExcluded = $lines === ['Before DecodeParms Tail Inline', 'After DecodeParms Tail Inline']
    && !str_contains($plainText, 'DecodeParms Tail Inline Noise')
    && !str_contains($plainText, 'rawtail')
    && !str_contains($plainText, 'ZZ EI')
    && !str_contains($plainText, '99 0 R');

if (
    !$previewFailedClosed
    || !$payloadExcluded
    || ($review['image_filters'] ?? []) !== ['FlateDecode']
    || ($review['image_decode']['source'] ?? null) !== 'explicit'
    || ($review['image_decode']['valid_for_components'] ?? null) !== true
    || ($decodeParms['type'] ?? null) !== 'FlateDecode'
    || ($decodeParms['predictor'] ?? null) !== 12
    || ($decodeParms['columns'] ?? null) !== 3
    || ($decodeParms['valid_decode_parms'] ?? null) !== false
    || ($decodeParms['invalid_decode_parms_fields'] ?? []) !== ['decode_parms_operand']
    || ($review['inline_image_dictionary_operand_invalid'] ?? null) !== true
    || ($review['inline_image']['native_raster_decode'] ?? true) !== false
) {
    throw new RuntimeException('Inline DecodeParms tail filter smoke did not fail closed with recovered metadata.');
}

$metadata = [
    'source' => 'native-pdf-inline-image-decodeparms-tail-filter-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.images.extract.extract_images',
    'decodeparms_tail_review_only' => true,
    'filter_preserved_after_decodeparms_tail' => true,
    'decode_preserved_after_decodeparms_tail' => true,
    'inline_filter_details' => $review['image_filter_details'],
    'image_filters' => $review['image_filters'],
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

echo '<!-- markerpdf:pdf-inline-image-decodeparms-tail-filter-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
