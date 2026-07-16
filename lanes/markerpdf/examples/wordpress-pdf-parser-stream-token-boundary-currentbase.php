<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = "BT /F1 12 Tf 72 720 Td (Before nested token boundary) Tj ET\n"
    . "[ [ << /Trap (ignored nested dictionary) /Names [ /A /B ] >> ]\n"
    . "endstream\nendobj\n20 0 obj\n<< /Length 61 >>\nstream\n"
    . "BT /F1 12 Tf 72 650 Td (Nested array fake stream leak) Tj ET\n"
    . "]\n"
    . 'BT /F1 12 Tf 72 700 Td (After nested token boundary) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "5 0 obj\n<< >>\nstream\n{$content}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

if ($lines !== ['Before nested token boundary', 'After nested token boundary']) {
    throw new RuntimeException('Expected nested array stream-token boundary fixture to preserve text around decoy tokens.');
}

if (str_contains($plainText, 'Nested array fake stream leak') || str_contains($plainText, '20 0 obj')) {
    throw new RuntimeException('Expected nested array token payload bytes to stay out of WordPress paragraphs.');
}

echo '<!-- markerpdf-parser-stream-token-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-content-stream-token-parser',
    'native_boundary' => 'content stream endstream scanning preserves nested array tokens before object ownership',
    'visible_text_imported' => $lines === ['Before nested token boundary', 'After nested token boundary'],
    'nested_array_payload_excluded' => !str_contains($plainText, 'Nested array fake stream leak'),
    'fake_object_header_excluded' => !str_contains($plainText, '20 0 obj'),
    'fake_endstream_owner_excluded' => !str_contains($plainText, 'endstream'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
    'page_labels' => $extractor->extractPageLabels($pdf),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
