<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = "BT /F1 12 Tf 1 0 0 1 72 720 Tm [(Clean) % ] (Comment Noise) -5000\n"
    . " (Blocks)] TJ 1 0 0 1 150 720 Tm (Ready) Tj T* [(Second) % (Hidden Review Text) 900\n"
    . " (Line)] TJ ET";
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

echo '<!-- markerpdf:pdf-tj-comment-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-tj-array-comment-boundary',
    'text_showing_operator' => 'TJ',
    'positioned_words' => $lines,
    'comments_treated_as_whitespace' => true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
