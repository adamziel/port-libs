<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /MalformedEncodingRefTailSourceWidth-H def\n"
    . "1 begincodespacerange\n"
    . "<10> <23>\n"
    . "endcodespacerange\n"
    . "1 begincidrange\n"
    . "<10> <13> 60\n"
    . "endcidrange\n"
    . "1 begincidrange\n"
    . "<20> <23> 32\n"
    . "endcidrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<10> <23>\n"
    . "endcodespacerange\n"
    . "8 beginbfchar\n"
    . "<10> <004A>\n"
    . "<11> <006F>\n"
    . "<12> <0069>\n"
    . "<13> <006E>\n"
    . "<20> <0053>\n"
    . "<21> <0061>\n"
    . "<22> <0066>\n"
    . "<23> <0065>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <10111213> Tj '
    . '1 0 0 1 120 720 Tm <20212223> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MalformedEncodingRefTailSourceWidth /Encoding 3 0 R 9 /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /MalformedEncodingRefTailSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [16 19 1000 32 35 250 60 63 250] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

$flags = [
    'source' => 'native-pdf-cmap-encoding-reference-tail-source-width-currentbase',
    'support_component' => 'pdf-text-dictionary-core',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'malformed_encoding_reference_tail_rejected' => $lines === ['JoinSafe'],
    'false_word_gap_excluded' => !str_contains($plainText, 'Join Safe'),
    'safe_tounicode_text_preserved' => $plainText === 'JoinSafe',
    'decoy_cmap_widths_excluded' => ($spans[0]['bbox'] ?? null) === [0.0, 0.0, 48.0, 12.0]
        && ($spans[1]['bbox'] ?? null) === [48.0, 0.0, 60.0, 12.0],
    'cmap_program_bytes_visible_text_excluded' => !str_contains($plainText, 'MalformedEncodingRefTailSourceWidth'),
    'nul_bytes_excluded' => !str_contains($plainText, "\0"),
];

$behaviorFlags = array_diff_key($flags, [
    'source' => true,
    'support_component' => true,
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);

if (in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected malformed Encoding reference tail smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-cmap-encoding-reference-tail-source-width-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
