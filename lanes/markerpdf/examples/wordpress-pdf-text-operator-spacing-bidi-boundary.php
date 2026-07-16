<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /Fsur 12 Tf 16 TL 1 0 0 1 72 720 Tm (Lead) Tj 18 0 <01> " 1 0 0 1 90 704 Tm <02> Tj ET';
$cmapText = static function (string $text): string {
    $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', $text);
    if ($encoded === false) {
        throw new RuntimeException('Unable to encode CMap fixture text.');
    }

    return strtoupper(bin2hex($encoded));
};
$cmap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "2 beginbfchar\n"
    . "<01> <" . $cmapText("\u{2067}A B\u{2069}") . ">\n"
    . "<02> <" . $cmapText('C') . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fsur 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /CustomBidiSpacing /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$expected = "\u{2067}A B\u{2069} C";

echo '<!-- markerpdf:pdf-text-operator-spacing-bidi-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-text-showing-operator-source-space-boundary',
    'text_showing_operator' => '"',
    'spacing_operator' => 'Tw',
    'positioning_operator' => 'Tm',
    'bidi_isolate_controls_preserved' => isset($lines[1]) && str_contains($lines[1], "\u{2067}") && str_contains($lines[1], "\u{2069}"),
    'decoded_tounicode_space_preserved' => isset($lines[1]) && str_contains($lines[1], 'A B'),
    'source_space_count_used_for_word_spacing' => ($lines[1] ?? '') === $expected,
    'following_positioned_word_gap_preserved' => isset($lines[1]) && str_contains($lines[1], "\u{2069} C"),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
