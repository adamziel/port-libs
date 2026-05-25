<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$utf16Be = "\xfe\xff\x00W\x00P\x00 \x00I\x00m\x00p\x00o\x00r\x00t";
$utf16Le = "\xff\xfeB\x00l\x00o\x00c\x00k\x00s\x00";
$content = 'BT /F1 12 Tf 72 720 Td (' . $utf16Be . ') Tj T* (' . $utf16Le . ') Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

echo "<!-- markerpdf-literal-utf16-smoke " . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF literal string UTF-16 BOM decoding before Gutenberg paragraph rendering',
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
