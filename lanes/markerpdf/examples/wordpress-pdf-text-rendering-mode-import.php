<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 1 0 0 1 72 720 Tm (Visible Before) Tj '
    . '3 Tr 1 0 0 1 72 704 Tm (Invisible OCR Noise) Tj '
    . '7 Tr 1 0 0 1 72 688 Tm (Clip Only Noise) Tj '
    . '/Span << /ActualText (Clip Alt Noise) >> BDC (Clip Glyph Noise) Tj EMC '
    . '4 Tr 1 0 0 1 72 672 Tm (Filled Clip Visible) Tj '
    . '5 Tr 1 0 0 1 72 656 Tm (Stroked Clip Visible) Tj '
    . '6 Tr 1 0 0 1 72 640 Tm (Fill Stroke Clip Visible) Tj '
    . 'q 3 Tr 1 0 0 1 72 624 Tm (Scoped Hidden Noise) Tj Q '
    . '1 0 0 1 72 608 Tm (Scoped Restore Visible) Tj '
    . '0 Tr 1 0 0 1 72 592 Tm (Visible After) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

echo '<!-- markerpdf:pdf-text-rendering-mode ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-text-rendering-mode-clipping',
    'rendering_mode_operator' => 'Tr',
    'suppressed_rendering_modes' => [3, 7],
    'visible_clipping_modes' => [4, 5, 6],
    'imported_lines' => $lines,
    'excluded_hidden_text' => true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
