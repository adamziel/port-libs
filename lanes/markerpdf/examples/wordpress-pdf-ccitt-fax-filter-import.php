<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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
$inlineNotes = $inlineReview['notes'] ?? [];
$invalidInlineParms = $invalidInlineReview['image_filter_details'][0]['decode_parms'] ?? [];
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
