<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$g4Marker = "\x00\x10\x01";
$rowEolMarker = "\x00\x10\x01";
$firstPayload = "\x11\x22{$g4Marker}";
$secondPayload = "\x33\x44{$rowEolMarker}";
$rawPayload = "\xff";
$content = "BT /F1 12 Tf 72 720 Td (Before inline CCITT markers) Tj ET\n"
    . "BI /W 1728 /H 0 /IM true /F /CCF /DP << /K -1 /Columns 1728 /Rows 0 /EndOfBlock true >> ID\n"
    . "{$firstPayload}\nEI\n"
    . "BT /F1 12 Tf 72 700 Td (Between inline CCITT markers) Tj ET\n"
    . "BI /W 1728 /H 1 /IM true /F /CCITTFaxDecode /DP << /K 0 /Columns 1728 /Rows 1 /EndOfLine true /EndOfBlock false >> ID\n"
    . "{$secondPayload}\nEI\n"
    . "BT /F1 12 Tf 72 680 Td (After inline CCITT markers) Tj ET\n"
    . "BI /W 1 /H 1 /CS /G /BPC 8 ID{$rawPayload}EI\n"
    . "BT /F1 12 Tf 72 660 Td (After raw inline image) Tj ET";
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$expected = [
    'Before inline CCITT markers',
    'Between inline CCITT markers',
    'After inline CCITT markers',
    'After raw inline image',
];

if (
    $lines !== $expected
    || str_contains($plainText, $firstPayload)
    || str_contains($plainText, $secondPayload)
    || str_contains($plainText, 'CCITTFaxDecode')
    || str_contains($plainText, 'CCF')
) {
    throw new RuntimeException('Inline CCITT Fax marker boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-inline-ccitt-fax-marker-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image inline image parser handoff',
    'ccitt_eofb_inline_boundary_preserved_text_after_image' => true,
    'ccitt_eol_inline_boundary_preserved_text_after_image' => true,
    'inline_fax_payload_in_visible_text' => false,
    'native_raster_decode' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-inline-ccitt-fax-marker-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
