<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\MarkerPDF\PdfTextExtractor;

$before = 'BT /F1 12 Tf 72 720 Td (Before Height CCITT import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Height CCITT import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (Height CCITT leak) Tj ET';
$eol = "\x00\x10\x01";
$faxPayload = "\x01\x02{$eol}\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\x03\x04{$eol}";
$staleTerminatorOffset = strpos($faxPayload, "\nendstream\n");
if ($staleTerminatorOffset === false) {
    throw new RuntimeException('Height-derived CCITT smoke must expose a stale row-end terminator.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxHeight 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 9 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 2 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K 0 /Columns 16 /EndOfLine true /EndOfBlock false >> /Length {$staleTerminatorOffset} >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$boundary = is_array($entry) ? ($entry['ccitt_fax_decode_boundary'] ?? []) : [];
$effectiveDecodeParms = is_array($boundary) ? ($boundary['effective_decode_parms'] ?? []) : [];

if (
    $plainText !== "Before Height CCITT import\nAfter Height CCITT import"
    || str_contains($plainText, 'Height CCITT leak')
    || ($entry['raw_length'] ?? null) !== strlen($faxPayload)
    || ($effectiveDecodeParms['rows'] ?? null) !== 0
    || ($boundary['effective_height'] ?? null) !== 2
    || ($entry['decoded_with_current_filters'] ?? true) !== false
) {
    throw new RuntimeException('Height-derived CCITT row-boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-ccitt-fax-height-row-boundary-currentbase',
    'visible_text' => $plainText,
    'stream_filters' => $entry['filters'] ?? [],
    'preview_only_filters' => $entry['preview_only_filters'] ?? [],
    'decode_parms_rows_default' => $effectiveDecodeParms['rows'] ?? null,
    'image_height_used_for_row_ownership' => ($boundary['effective_height'] ?? null) === 2,
    'raw_length_after_boundary_recovery' => $entry['raw_length'] ?? null,
    'stale_owner_payload_excluded_from_visible_text' => !str_contains($plainText, 'Height CCITT leak'),
    'stale_owner_payload_excluded_from_review' => !str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', 'Height CCITT leak'),
    'decoded_with_current_filters' => $entry['decoded_with_current_filters'] ?? null,
    'native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-height-row-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach (explode("\n", $plainText) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
