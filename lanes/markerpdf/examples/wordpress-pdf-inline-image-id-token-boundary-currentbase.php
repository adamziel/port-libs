<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = "BT /F1 12 Tf 72 720 Td (Before ID Token Boundary) Tj ET\n"
    . "BI /W 1 /H 1 /CS /RGB /BPC 8 IDENTITY\n"
    . "BT /F1 12 Tf 72 704 Td (Recovered ID Prefix Text) Tj ET\n"
    . "EI\n"
    . "BI /W 18 /H 1 /CS /DeviceGray /BPC 8 ID\n"
    . "raw EI BT /F1 12 Tf 72 690 Td (Valid Inline Payload Noise) Tj ET tail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 688 Td (After ID Token Boundary) Tj ET";

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
    'Before ID Token Boundary',
    'Recovered ID Prefix Text',
    'After ID Token Boundary',
];

if ($lines !== $expected || str_contains($plainText, 'Valid Inline Payload Noise') || str_contains($plainText, 'IDENTITY')) {
    throw new RuntimeException('Inline image ID token boundary leaked or hid WordPress import text.');
}

$metadata = [
    'source' => 'native-pdf-inline-image-id-token-boundary-currentbase',
    'upstream_boundary' => 'pdftext content-stream inline image tokenizer: BI/ID/EI operators are token boundaries before markerPDF text extraction',
    'recovered_id_prefix_text' => str_contains($plainText, 'Recovered ID Prefix Text'),
    'valid_inline_image_payload_excluded' => !str_contains($plainText, 'Valid Inline Payload Noise'),
    'id_prefix_operator_rejected' => !str_contains($plainText, 'IDENTITY'),
    'visible_line_count' => count($lines),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:inline-image-id-token-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
