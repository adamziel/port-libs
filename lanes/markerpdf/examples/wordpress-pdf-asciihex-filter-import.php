<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Encoded PDF Import) Tj T* (Clean WordPress Blocks) Tj ET';
$encoded = chunk_split(strtoupper(bin2hex($content)), 24, "\n") . '>';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Filter /ASCIIHexDecode /Length " . strlen($encoded) . " >>\nstream\n{$encoded}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

echo '<!-- markerpdf:pdf-asciihex-filter ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter',
    'stream_filters' => ['ASCIIHexDecode'],
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
