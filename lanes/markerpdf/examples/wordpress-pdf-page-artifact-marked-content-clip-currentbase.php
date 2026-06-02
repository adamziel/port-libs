<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'q 72 650 240 50 re W n '
    . 'BT /F1 12 Tf '
    . '/Artifact << /Type /Pagination /ActualText (Clipped Header Replacement) >> BDC 1 0 0 1 72 724 Tm (Clipped Header Glyphs) Tj EMC '
    . '1 0 0 1 72 684 Tm (Body inside clipped region) Tj '
    . '/Artifact << /Type /Layout /Alt (Visible Artifact Caption) >> BDC 1 0 0 1 72 668 Tm (Caption Glyphs) Tj EMC '
    . '1 0 0 1 72 620 Tm (Clipped Footer Body) Tj '
    . 'ET Q '
    . 'BT /F1 12 Tf 1 0 0 1 72 600 Tm (Tail outside clip) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-page-artifact-marked-content-clip ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-artifact-marked-content-clip',
    'clip_operator' => 'W',
    'path_operator' => 're',
    'artifact_actual_text_clipped' => !str_contains($plainText, 'Clipped Header Replacement'),
    'artifact_glyph_text_clipped' => !str_contains($plainText, 'Clipped Header Glyphs'),
    'non_artifact_text_clipped' => !str_contains($plainText, 'Clipped Footer Body'),
    'artifact_alt_text_inside_clip_imported' => str_contains($plainText, 'Visible Artifact Caption'),
    'artifact_glyph_text_inside_clip_suppressed' => !str_contains($plainText, 'Caption Glyphs'),
    'imported_lines' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
