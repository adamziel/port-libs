<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$baseCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPLateBaseSourceWidth-H def\n"
    . "1 begincodespacerange\n"
    . "<20> <27>\n"
    . "endcodespacerange\n"
    . "1 begincidrange\n"
    . "<20> <27> 14\n"
    . "endcidrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPLateUseCMapSourceWidth-H def\n"
    . "1 begincodespacerange\n"
    . "<20> <23>\n"
    . "endcodespacerange\n"
    . "1 begincidrange\n"
    . "<20> <23> 100\n"
    . "endcidrange\n"
    . "/WPLateBaseSourceWidth-H usecmap\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<20> <27>\n"
    . "endcodespacerange\n"
    . "8 beginbfchar\n"
    . "<20> <0041>\n"
    . "<21> <0042>\n"
    . "<22> <0043>\n"
    . "<23> <0044>\n"
    . "<24> <0045>\n"
    . "<25> <0046>\n"
    . "<26> <0047>\n"
    . "<27> <0048>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <20212223> Tj '
    . '1 0 0 1 120 720 Tm <24252627> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPLateUseCMapSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 7 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPLateUseCMapSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [18 21 250 100 103 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($baseCMap) . " >>\nstream\n{$baseCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$runs = $extractor->extractTextRuns($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$spanBboxes = array_column($spans, 'bbox');

$flags = [
    'source' => 'native-pdf-cmap-late-usecmap-source-width-failclosed',
    'support_component' => 'pdf-text-dictionary-core',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'late_usecmap_ignored' => $spanBboxes === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 96.0, 12.0]],
    'local_cid_widths_preserved' => ($spans[0]['bbox'] ?? null) === [0.0, 0.0, 48.0, 12.0],
    'late_base_cid_widths_excluded' => ($spans[1]['bbox'] ?? null) !== [48.0, 0.0, 60.0, 12.0],
    'default_width_fallback_applied' => ($spans[1]['bbox'] ?? null) === [48.0, 0.0, 96.0, 12.0],
    'text_runs_preserved' => $runs === ['ABCD', 'EFGH'],
    'false_gap_excluded' => !str_contains($plainText, 'ABCD EFGH'),
    'raw_nul_bytes_excluded' => !str_contains($plainText, "\0"),
];

$behaviorFlags = array_diff_key($flags, [
    'source' => true,
    'support_component' => true,
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);

if ($lines !== ['ABCDEFGH'] || in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected late-usecmap source-width CMap smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-cmap-late-usecmap-source-width-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
