<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$zlibStored = static function (string $bytes): string {
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

$before = 'BT /F1 12 Tf 72 720 Td (CCITT Boundary) Tj ET';
$after = 'BT /F1 12 Tf 72 688 Td (Native Import) Tj ET';
$scanNoise = 'BT /F1 12 Tf 72 704 Td (Raster Fax Noise) Tj ET';
$aliasNoise = 'BT /F1 12 Tf 72 672 Td (Alias Fax Noise) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 5 0 R 6 0 R 7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /CCITTFaxDecode /DecodeParms << /K -1 /Columns 1728 /Rows 1 /BlackIs1 true >> /Length " . strlen($scanNoise) . " >>\nstream\n{$scanNoise}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Filter /CCF /DecodeParms << /K 0 /Columns 8 /Rows 1 /EncodedByteAlign true >> /Length " . strlen($aliasNoise) . " >>\nstream\n{$aliasNoise}\nendstream\nendobj\n"
    . "%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$inlineReview = (new PdfImageRenderer())->inlineImageReviewPlan(
    '/W 16 /H 1 /IM true /F /CCF /DP << /K 0 /Columns 16 /Rows 1 /BlackIs1 false /EncodedByteAlign true /EndOfLine false /EndOfBlock true >> /D [1 0]',
    "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline CCITT fax payload noise) Tj ET final"
);
$invalidInlinePayload = "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline invalid CCITT fax payload noise) Tj ET final";
$invalidInlineReview = (new PdfImageRenderer())->inlineImageReviewPlan(
    '/W 8 /H 1 /IM true /F /CCF /DP << /K /TwoD /Columns 0 /Rows -1 /BlackIs1 /Maybe /EncodedByteAlign true /EndOfLine /No /EndOfBlock true /DamagedRowsBeforeError -2 >> /D [1 0]',
    $invalidInlinePayload
);
$inlineGeometryReview = (new PdfImageRenderer())->inlineImageReviewPlan(
    '/W 8 /H 3 /IM true /F /CCF /DP << /Columns 16 /Rows 4 /BlackIs1 true >>',
    "fax bytes EI BT /F1 12 Tf 72 640 Td (Inline CCITT geometry payload noise) Tj ET final"
);
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress Flate CCITT prefix leak) Tj ET';
$flateWrappedFaxPayload = "\x00\x11\x22\x33\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\x44\x55\x66";
$compressedFaxPayload = $zlibStored($flateWrappedFaxPayload);
$staleLength = strpos($compressedFaxPayload, "\nendstream\n");
if ($staleLength === false) {
    throw new RuntimeException('Unable to build CCITT Flate-prefix stale-length fixture.');
}
$boundaryBefore = 'BT /F1 12 Tf 72 720 Td (Before Flate CCITT import) Tj ET';
$boundaryAfter = 'BT /F1 12 Tf 72 680 Td (After Flate CCITT import) Tj ET';
$boundaryPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Fax#20Flate 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($boundaryBefore) . " >>\nstream\n{$boundaryBefore}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter [/FlateDecode /CCITTFaxDecode] /DecodeParms [null << /K 0 /Columns 16 /Rows 1 /EndOfBlock false >>] /Length {$staleLength} >>\nstream\n{$compressedFaxPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($boundaryAfter) . " >>\nstream\n{$boundaryAfter}\nendstream\nendobj\n%%EOF";
$boundaryExtractor = new PdfTextExtractor();
$boundaryLines = $boundaryExtractor->extractTextLines($boundaryPdf);
$boundaryReview = $boundaryExtractor->extractImageXObjectBoundaryReview($boundaryPdf);
$geometryBefore = 'BT /F1 12 Tf 72 720 Td (Before CCITT geometry import) Tj ET';
$geometryAfter = 'BT /F1 12 Tf 72 680 Td (After CCITT geometry import) Tj ET';
$geometryPayload = 'BT /F1 12 Tf 72 700 Td (WordPress CCITT geometry payload noise) Tj ET';
$geometryPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxGeometry 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($geometryBefore) . " >>\nstream\n{$geometryBefore}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /Columns 16 /Rows 4 /BlackIs1 true >> /Length " . strlen($geometryPayload) . " >>\nstream\n{$geometryPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($geometryAfter) . " >>\nstream\n{$geometryAfter}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";
$geometryReview = $boundaryExtractor->extractImageXObjectBoundaryReview($geometryPdf);
$inlineNotes = $inlineReview['notes'] ?? [];
$invalidInlineParms = $invalidInlineReview['image_filter_details'][0]['decode_parms'] ?? [];
$inlineGeometryBoundary = $inlineGeometryReview['ccitt_fax_decode_boundary'] ?? [];
$geometryBoundary = $geometryReview['entries'][0]['ccitt_fax_decode_boundary'] ?? [];
if (!in_array('inline_ccitt_fax_image_filter_review_only', $inlineNotes, true)) {
    throw new RuntimeException('Inline CCITT Fax review boundary smoke failed.');
}
if (
    ($invalidInlineParms['valid_decode_parms'] ?? null) !== false
    || !in_array('columns', $invalidInlineParms['invalid_decode_parms_fields'] ?? [], true)
    || !in_array('damaged_rows_before_error', $invalidInlineParms['invalid_decode_parms_fields'] ?? [], true)
) {
    throw new RuntimeException('Inline CCITT Fax invalid DecodeParms smoke failed.');
}
if (
    $boundaryLines !== ['Before Flate CCITT import', 'After Flate CCITT import']
    || str_contains($boundaryExtractor->extractPlainText($boundaryPdf), 'WordPress Flate CCITT prefix leak')
    || (($boundaryReview['entries'][0]['raw_length'] ?? null) !== strlen($compressedFaxPayload))
) {
    throw new RuntimeException('Flate-wrapped CCITT Fax stale-length boundary smoke failed.');
}
if (
    ($inlineGeometryBoundary['effective_decode_parms']['end_of_block'] ?? null) !== true
    || ($inlineGeometryBoundary['dimension_mismatch'] ?? null) !== true
    || ($geometryBoundary['effective_width'] ?? null) !== 16
    || ($geometryBoundary['effective_height'] ?? null) !== 4
    || ($geometryBoundary['width_source'] ?? null) !== 'decodeparms_columns'
    || str_contains($boundaryExtractor->extractPlainText($geometryPdf), 'WordPress CCITT geometry payload noise')
) {
    throw new RuntimeException('CCITT effective DecodeParms geometry boundary smoke failed.');
}

echo '<!-- markerpdf:pdf-ccitt-fax-filter ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter-boundary',
    'stream_filters' => ['CCITTFaxDecode', 'CCF'],
    'inline_image_filters' => $inlineReview['image_filters'] ?? [],
    'inline_review_only_filters' => $inlineReview['inline_image']['review_only_filters'] ?? [],
    'inline_ccitt_review_only' => $inlineReview['inline_image_review_only'] ?? null,
    'inline_ccitt_note' => 'inline_ccitt_fax_image_filter_review_only',
    'inline_invalid_decode_parms_valid' => $invalidInlineParms['valid_decode_parms'] ?? null,
    'inline_invalid_decode_parms_fields' => $invalidInlineParms['invalid_decode_parms_fields'] ?? [],
    'inline_invalid_payload_excluded_from_review' => !str_contains(json_encode($invalidInlineReview, JSON_UNESCAPED_SLASHES) ?: '', $invalidInlinePayload),
    'inline_effective_decode_parms' => $inlineGeometryBoundary['effective_decode_parms'] ?? [],
    'inline_geometry_dimension_mismatch' => $inlineGeometryBoundary['dimension_mismatch'] ?? null,
    'inline_geometry_defaults_applied' => $inlineGeometryBoundary['defaults_applied'] ?? [],
    'xobject_geometry_effective_width' => $geometryBoundary['effective_width'] ?? null,
    'xobject_geometry_effective_height' => $geometryBoundary['effective_height'] ?? null,
    'xobject_geometry_width_source' => $geometryBoundary['width_source'] ?? null,
    'xobject_geometry_payload_excluded' => !str_contains($boundaryExtractor->extractPlainText($geometryPdf), 'WordPress CCITT geometry payload noise'),
    'flate_wrapped_ccitt_stale_length_repaired' => true,
    'flate_wrapped_ccitt_payload_excluded' => !str_contains($boundaryExtractor->extractPlainText($boundaryPdf), 'WordPress Flate CCITT prefix leak'),
    'flate_wrapped_ccitt_raw_length' => $boundaryReview['entries'][0]['raw_length'] ?? null,
    'decode_parms' => [
        ['K' => -1, 'Columns' => 1728, 'Rows' => 1, 'BlackIs1' => true],
        ['K' => 0, 'Columns' => 8, 'Rows' => 1, 'EncodedByteAlign' => true],
    ],
    'paragraphs' => $lines,
    'image_only_filter_skipped' => true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
