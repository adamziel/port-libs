<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /Fshort 12 Tf '
    . '1 0 0 1 72 720 Tm (A) Tj '
    . '1 0 0 1 94 720 Tm (B) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fshort 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /FirstChar 65 /LastChar 66 /Widths [1000] >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$spans = $extractor->extractStyledTextPages($pdf)[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$firstBbox = $spans[0]['bbox'] ?? [];
$secondBbox = $spans[1]['bbox'] ?? [];

$summary = [
    'scenario' => 'wordpress-pdf-font-width-short-array-currentbase',
    'source' => 'native-pdf-simple-font-underdeclared-widths-boundary',
    'underdeclared_widths_rejected' => $plainText === 'A B',
    'base14_advance_fallback' => isset($firstBbox[2]) && abs(((float) $firstBbox[2]) - 8.004) < 0.001,
    'positioned_word_gap_preserved' => isset($secondBbox[0], $firstBbox[2]) && ((float) $secondBbox[0]) - ((float) $firstBbox[2]) >= 12.0,
    'font_payload_hidden_from_visible_text' => !str_contains($plainText, 'Fshort') && !str_contains($plainText, 'Widths'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$summary['underdeclared_widths_rejected']
    || !$summary['base14_advance_fallback']
    || !$summary['positioned_word_gap_preserved']
    || !$summary['font_payload_hidden_from_visible_text']
) {
    throw new RuntimeException('Expected underdeclared simple-font Widths array to fail closed before WordPress paragraph extraction.');
}

echo '<!-- markerpdf-font-width-short-array-currentbase-smoke ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
