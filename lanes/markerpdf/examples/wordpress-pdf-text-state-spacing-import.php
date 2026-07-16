<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 2 Tc 120 Tz 1 0 0 1 72 720 Tm (Data) Tj 1 0 0 1 112 720 Tm (base) Tj ET '
    . 'BT /F1 12 Tf 100 Tz 16 TL 72 720 Td (Intro) Tj 18 2 (Import Profile) " 1 0 0 1 182 704 Tm (s) Tj ET '
    . 'BT /F1 12 Tf 18 Tw 1 0 0 1 72 688 Tm (Media Import) Tj 1 0 0 1 170 688 Tm (er) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

echo '<!-- markerpdf:pdf-text-state-spacing ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-text-state-spacing-advance',
    'spacing_operators' => ['Tc', 'Tw', 'Tz', '"'],
    'positioned_words' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
