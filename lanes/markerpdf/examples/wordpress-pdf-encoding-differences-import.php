<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$content = 'BT /Fdiff 12 Tf 72 720 Td <202122232425262728292A2B2C2D2E2F> Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fdiff 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /CustomSubset /Encoding << /Type /Encoding /Differences [32 /W /P /space /I /m /p /o /r /t /space /B /l /o /c /k /s] >> >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

echo "<!-- markerpdf-encoding-differences-smoke " . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'simple font Encoding Differences glyph-name decoding before Gutenberg paragraph rendering',
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
