<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf q 1 0 0 1 0 100 cm 72 620 Td (Translated ) Tj '
    . '1 0 0 1 140 620 Tm (Line) Tj Q 1 0 0 1 180 720 Tm (Restored) Tj '
    . '1 0 0 1 72 620 Tm (Untranslated) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-page-cm-text-operator-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-graphics-state-cm-text-operator-boundary',
    'graphics_state_operators' => ['q', 'cm', 'Q'],
    'text_operators' => ['BT', 'Td', 'Tm', 'Tj', 'ET'],
    'page_cm_transformed_text_line' => in_array('Translated Line Restored', $lines, true),
    'graphics_state_restored_after_Q' => in_array('Untranslated', $lines, true),
    'split_untransformed_line_excluded' => !str_contains($plainText, "Translated Line\nRestored"),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
