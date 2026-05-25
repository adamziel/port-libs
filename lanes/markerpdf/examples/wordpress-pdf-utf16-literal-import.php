<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$utf16Be = hex2bin('FEFF0057006F0072006400500072006500730073');
$utf16Le = hex2bin('FFFE42006C006F0063006B007300');
if (!is_string($utf16Be) || !is_string($utf16Le)) {
    throw new RuntimeException('Unable to build UTF-16 literal PDF fixture.');
}

$content = "BT /F1 12 Tf 72 720 Td ({$utf16Be}) Tj T* ({$utf16Le}) Tj ET";
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

echo "<!-- markerpdf-utf16-literal-smoke " . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF literal-string UTF-16 BOM decoding before Gutenberg paragraph rendering',
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
