<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$extractor = new PdfTextExtractor();
$eol = "\x00\x10\x01";
$rtc = $eol . $eol . $eol;

$before = 'BT /F1 12 Tf 72 720 Td (Before invalid-height CCITT import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After invalid-height CCITT import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress invalid-height CCITT leak) Tj ET';
$faxPayload = "\x01\x02{$eol}\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\x03\x04{$rtc}";
$staleLength = strpos($faxPayload, "\nendstream\n");
if ($staleLength === false) {
    throw new RuntimeException('Unable to build invalid-height CCITT stale-boundary fixture.');
}

$xobjectPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxBadHeight 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 9 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height -1 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K 0 /Columns 16 /EndOfLine true /EndOfBlock false >> /Length {$staleLength} >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$inlineContent = "BT /F1 12 Tf 72 720 Td (Before inline invalid-height CCITT import) Tj ET\n"
    . "BI /W 16 /H -1 /IM true /F /CCF /DP << /K 0 /Columns 16 /EndOfLine true /EndOfBlock false >> ID\n"
    . "\x01\x02{$eol}\nEI\n"
    . "BT /F1 12 Tf 72 700 Td (Inline invalid-height CCITT leak) Tj ET\n"
    . "\x03\x04{$rtc}\nEI\n"
    . "BT /F1 12 Tf 72 680 Td (After inline invalid-height CCITT import) Tj ET";
$inlinePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($inlineContent) . " >>\nstream\n{$inlineContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$xobjectLines = $extractor->extractTextLines($xobjectPdf);
$xobjectText = $extractor->extractPlainText($xobjectPdf);
$xobjectReview = $extractor->extractImageXObjectBoundaryReview($xobjectPdf);
$xobjectEntry = $xobjectReview['entries'][0] ?? [];
$decodeBoundary = $xobjectEntry['ccitt_fax_decode_boundary'] ?? [];
$effectiveDecodeParms = $decodeBoundary['effective_decode_parms'] ?? [];

$inlineLines = $extractor->extractTextLines($inlinePdf);
$inlineText = $extractor->extractPlainText($inlinePdf);

if ($xobjectLines !== ['Before invalid-height CCITT import', 'After invalid-height CCITT import']) {
    throw new RuntimeException('Invalid-height CCITT XObject leaked stale object text.');
}
if (str_contains($xobjectText, 'WordPress invalid-height CCITT leak') || str_contains($xobjectText, 'endstream')) {
    throw new RuntimeException('Invalid-height CCITT XObject payload leaked into WordPress text.');
}
if (($xobjectEntry['raw_length'] ?? null) !== strlen($faxPayload)) {
    throw new RuntimeException('Invalid-height CCITT XObject stream boundary was not repaired to the terminal marker.');
}
if (
    ($effectiveDecodeParms['rows'] ?? null) !== 0
    || !array_key_exists('effective_height', $decodeBoundary)
    || $decodeBoundary['effective_height'] !== null
) {
    throw new RuntimeException('Invalid-height CCITT boundary metadata did not preserve unbounded row state.');
}
if (str_contains(json_encode($xobjectReview, JSON_UNESCAPED_SLASHES) ?: '', $faxPayload)) {
    throw new RuntimeException('Invalid-height CCITT payload bytes leaked into review JSON.');
}
if ($inlineLines !== ['Before inline invalid-height CCITT import', 'After inline invalid-height CCITT import']) {
    throw new RuntimeException('Invalid-height inline CCITT tokenizer accepted the stale row EOL boundary.');
}
if (str_contains($inlineText, 'Inline invalid-height CCITT leak')) {
    throw new RuntimeException('Invalid-height inline CCITT payload leaked into WordPress text.');
}

echo '<!-- markerpdf:pdf-ccitt-fax-invalid-height ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-ccitt-fax-invalid-height-row-boundary',
    'xobject_lines' => $xobjectLines,
    'inline_lines' => $inlineLines,
    'xobject_height_source' => $decodeBoundary['height_source'] ?? null,
    'xobject_effective_height' => $decodeBoundary['effective_height'] ?? null,
    'xobject_rows_default' => $effectiveDecodeParms['rows'] ?? null,
    'xobject_boundary_repaired_to_terminal_marker' => ($xobjectEntry['raw_length'] ?? null) === strlen($faxPayload),
    'xobject_payload_excluded_from_text' => !str_contains($xobjectText, 'WordPress invalid-height CCITT leak'),
    'xobject_payload_excluded_from_review' => !str_contains(json_encode($xobjectReview, JSON_UNESCAPED_SLASHES) ?: '', $faxPayload),
    'inline_payload_excluded_from_text' => !str_contains($inlineText, 'Inline invalid-height CCITT leak'),
    'decoded_with_current_filters' => $xobjectEntry['decoded_with_current_filters'] ?? null,
    'native_raster_decode' => $xobjectEntry['native_raster_decode'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($xobjectLines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
