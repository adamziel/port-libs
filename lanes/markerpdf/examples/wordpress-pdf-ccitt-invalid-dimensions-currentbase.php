<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$renderer = new PdfImageRenderer();
$inlinePlan = $renderer->inlineImageReviewPlan(
    '/W 0 /H -1 /IM true /F /CCF /DP << /K -1 /Columns 16 /Rows 2 /BlackIs1 true /EndOfBlock true >>',
    "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline invalid-dimension CCITT payload noise) Tj ET final"
);
$inlineBoundary = $inlinePlan['ccitt_fax_decode_boundary'] ?? [];

$before = 'BT /F1 12 Tf 72 720 Td (Before invalid CCITT geometry) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After invalid CCITT geometry) Tj ET';
$payload = 'BT /F1 12 Tf 72 700 Td (WordPress invalid-dimension CCITT payload noise) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxInvalidGeometry 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 0 /Height -1 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K -1 /Columns 16 /Rows 2 /BlackIs1 true /EndOfBlock true >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$xobjectBoundary = $entry['ccitt_fax_decode_boundary'] ?? [];
$payloadExcluded = !str_contains($plainText, 'WordPress invalid-dimension CCITT payload noise');

if (
    ($inlineBoundary['dictionary_width'] ?? null) !== 0
    || ($inlineBoundary['dictionary_height'] ?? null) !== -1
    || ($inlineBoundary['effective_width'] ?? null) !== 16
    || ($inlineBoundary['effective_height'] ?? null) !== 2
    || ($inlineBoundary['width_source'] ?? null) !== 'decodeparms_columns'
    || ($inlineBoundary['height_source'] ?? null) !== 'decodeparms_rows'
    || ($xobjectBoundary['dictionary_width'] ?? null) !== 0
    || ($xobjectBoundary['effective_width'] ?? null) !== 16
    || ($xobjectBoundary['effective_height'] ?? null) !== 2
    || ($xobjectBoundary['width_source'] ?? null) !== 'decodeparms_columns'
    || ($xobjectBoundary['height_source'] ?? null) !== 'decodeparms_rows'
    || ($xobjectBoundary['dimension_mismatch'] ?? null) !== true
    || $lines !== ['Before invalid CCITT geometry', 'After invalid CCITT geometry']
    || !$payloadExcluded
) {
    throw new RuntimeException('Invalid CCITT image dictionary dimension fallback smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-invalid-dimensions-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image review-only CCITT handoff',
    'inline_dictionary_width' => $inlineBoundary['dictionary_width'] ?? null,
    'inline_dictionary_height' => $inlineBoundary['dictionary_height'] ?? null,
    'inline_effective_width' => $inlineBoundary['effective_width'] ?? null,
    'inline_effective_height' => $inlineBoundary['effective_height'] ?? null,
    'inline_width_source' => $inlineBoundary['width_source'] ?? null,
    'inline_height_source' => $inlineBoundary['height_source'] ?? null,
    'xobject_dictionary_width' => $xobjectBoundary['dictionary_width'] ?? null,
    'xobject_dictionary_height' => $xobjectBoundary['dictionary_height'] ?? null,
    'xobject_effective_width' => $xobjectBoundary['effective_width'] ?? null,
    'xobject_effective_height' => $xobjectBoundary['effective_height'] ?? null,
    'xobject_width_source' => $xobjectBoundary['width_source'] ?? null,
    'xobject_height_source' => $xobjectBoundary['height_source'] ?? null,
    'xobject_dimension_mismatch' => $xobjectBoundary['dimension_mismatch'] ?? null,
    'payload_in_visible_text' => !$payloadExcluded,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-ccitt-invalid-dimensions-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
