<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$extractor = new PdfTextExtractor();
$eol = "\x00\x10\x01";
$rtc = $eol . $eol . $eol;

$before = 'BT /F1 12 Tf 72 720 Td (Before invalid-column CCITT import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After invalid-column CCITT import) Tj ET';
$fakeObject = 'BT /F1 12 Tf 72 700 Td (WordPress invalid-column CCITT leak) Tj ET';
$faxPayload = "\x01\x02{$eol}\n"
    . "endstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
    . "\x03\x04{$rtc}";
$staleLength = strpos($faxPayload, "\nendstream\n");
if ($staleLength === false) {
    throw new RuntimeException('Unable to build invalid-column CCITT stale-boundary fixture.');
}

$xobjectPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /FaxBadColumns 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 9 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /CCITTFaxDecode /DecodeParms << /K 0 /Columns 0 /Rows 1 /EndOfLine true /EndOfBlock false >> /Length {$staleLength} >>\nstream\n{$faxPayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$inlineContent = "BT /F1 12 Tf 72 720 Td (Before inline invalid-column CCITT import) Tj ET\n"
    . "BI /W 16 /H 1 /IM true /F /CCF /DP << /K 0 /Columns 0 /Rows 1 /EndOfLine true /EndOfBlock false >> ID\n"
    . "\x01\x02{$eol}\nEI\n"
    . "BT /F1 12 Tf 72 700 Td (Inline invalid-column CCITT leak) Tj ET\n"
    . "\x03\x04{$rtc}\nEI\n"
    . "BT /F1 12 Tf 72 680 Td (After inline invalid-column CCITT import) Tj ET";
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
$decodeParms = $xobjectEntry['filter_details'][0]['decode_parms'] ?? [];
$decodeBoundary = $xobjectEntry['ccitt_fax_decode_boundary'] ?? [];

$inlineLines = $extractor->extractTextLines($inlinePdf);
$inlineText = $extractor->extractPlainText($inlinePdf);

if ($xobjectLines !== ['Before invalid-column CCITT import', 'After invalid-column CCITT import']) {
    throw new RuntimeException('Invalid-column CCITT XObject leaked stale object text.');
}
if (str_contains($xobjectText, 'WordPress invalid-column CCITT leak') || str_contains($xobjectText, 'endstream')) {
    throw new RuntimeException('Invalid-column CCITT XObject payload leaked into WordPress text.');
}
if (($xobjectEntry['raw_length'] ?? null) !== strlen($faxPayload)) {
    throw new RuntimeException('Invalid-column CCITT XObject stream boundary was not repaired to the terminal marker.');
}
if (($decodeParms['valid_decode_parms'] ?? null) !== false || ($decodeParms['invalid_decode_parms_fields'] ?? null) !== ['columns']) {
    throw new RuntimeException('Invalid-column CCITT DecodeParms were not marked fail-closed.');
}
if (($decodeBoundary['invalid_decode_parms'] ?? null) !== true || ($decodeBoundary['invalid_decode_parms_fields'] ?? null) !== ['columns']) {
    throw new RuntimeException('Invalid-column CCITT boundary metadata did not carry fail-closed state.');
}
if (str_contains(json_encode($xobjectReview, JSON_UNESCAPED_SLASHES) ?: '', $faxPayload)) {
    throw new RuntimeException('Invalid-column CCITT payload bytes leaked into review JSON.');
}
if ($inlineLines !== ['Before inline invalid-column CCITT import', 'After inline invalid-column CCITT import']) {
    throw new RuntimeException('Invalid-column inline CCITT tokenizer accepted the stale row EOL boundary.');
}
if (str_contains($inlineText, 'Inline invalid-column CCITT leak')) {
    throw new RuntimeException('Invalid-column inline CCITT payload leaked into WordPress text.');
}

echo '<!-- markerpdf:pdf-ccitt-fax-invalid-columns ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-ccitt-fax-invalid-columns-boundary',
    'xobject_lines' => $xobjectLines,
    'inline_lines' => $inlineLines,
    'xobject_invalid_decode_parms_valid' => $decodeParms['valid_decode_parms'] ?? null,
    'xobject_invalid_decode_parms_fields' => $decodeParms['invalid_decode_parms_fields'] ?? [],
    'xobject_invalid_decode_parms_review' => $decodeParms['decode_parms_review'] ?? null,
    'xobject_invalid_columns_boundary_repaired' => ($xobjectEntry['raw_length'] ?? null) === strlen($faxPayload),
    'xobject_payload_excluded_from_text' => !str_contains($xobjectText, 'WordPress invalid-column CCITT leak'),
    'xobject_payload_excluded_from_review' => !str_contains(json_encode($xobjectReview, JSON_UNESCAPED_SLASHES) ?: '', $faxPayload),
    'inline_payload_excluded_from_text' => !str_contains($inlineText, 'Inline invalid-column CCITT leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($xobjectLines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
