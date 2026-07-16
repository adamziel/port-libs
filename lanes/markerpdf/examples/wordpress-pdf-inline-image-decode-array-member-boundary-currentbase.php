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
$compressedSample = gzcompress('Z', 0);
if (!is_string($compressedSample)) {
    throw new RuntimeException('Unable to build inline Decode array member WordPress fixture.');
}

$dictionary = '/W 1 /H 1 /CS /G /BPC 8 /F /Fl /D [0 null]';
$surplus = 'ZZ EI BT /F1 12 Tf 72 690 Td (Null Decode Array Member WordPress Noise) Tj ET rawtail';
$content = "BT /F1 12 Tf 72 720 Td (Before Null Decode Array Member Inline) Tj ET\n"
    . "BI {$dictionary} ID\n" . $compressedSample . $surplus . "\nEI\n"
    . "BT /F1 12 Tf 72 704 Td (After Null Decode Array Member Inline) Tj ET";

$pdf = $pdfWithContent($content);
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$review = $renderer->inlineImageReviewPlan($dictionary, $compressedSample);

$previewFailedClosed = false;
try {
    $renderer->inlineImageColorSpaceMaskOutputPreviewRows($dictionary, $compressedSample, [], 1);
} catch (InvalidArgumentException) {
    $previewFailedClosed = true;
}

$payloadExcluded = $lines === ['Before Null Decode Array Member Inline', 'After Null Decode Array Member Inline']
    && !str_contains($plainText, 'Null Decode Array Member WordPress Noise')
    && !str_contains($plainText, 'rawtail')
    && !str_contains($plainText, 'ZZ EI');
$invalidDecodeSource = ($review['image_decode']['source'] ?? null) === 'invalid'
    && ($review['image_decode']['component_count'] ?? null) === 0
    && ($review['image_decode_component_mismatch'] ?? false) === true;
$decodeReviewOnly = ($review['inline_image_review_only'] ?? false) === true
    && ($review['inline_image']['native_raster_decode'] ?? true) === false
    && in_array('inline_image_decode_operand_review_only', $review['notes'] ?? [], true);

if (!$payloadExcluded || !$invalidDecodeSource || !$decodeReviewOnly || !$previewFailedClosed) {
    throw new RuntimeException('Inline Decode array member boundary did not fail closed before WordPress import.');
}

$metadata = [
    'source' => 'native-pdf-inline-image-decode-array-member-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image review handoff',
    'canonical_dictionary' => $review['inline_image']['canonical_dictionary'] ?? null,
    'filters' => $review['image_filters'] ?? [],
    'decode_source' => $review['image_decode']['source'] ?? null,
    'decode_component_count' => $review['image_decode']['component_count'] ?? null,
    'decode_component_mismatch' => $review['image_decode_component_mismatch'] ?? null,
    'decode_operand_review_only' => $decodeReviewOnly,
    'preview_failed_closed' => $previewFailedClosed,
    'inline_payload_excluded_from_text' => $payloadExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-inline-image-decode-array-member-boundary-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
