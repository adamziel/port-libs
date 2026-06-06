<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextBlockConverter.php';
require_once __DIR__ . '/../src/PdfTextExtractor.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPDelayedCodespaceCIDRange-H def\n"
    . "1 begincodespacerange\n"
    . "<200000> <200001>\n"
    . "endcodespacerange\n"
    . "1 begincidrange\n"
    . "<000000> <200001> 32\n"
    . "endcidrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<200000> <200001>\n"
    . "endcodespacerange\n"
    . "2 beginbfchar\n"
    . "<200000> <0041>\n"
    . "<200001> <0042>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf 24 Tw 1 0 0 1 72 720 Tm <200000200001> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPDelayedCodespaceCIDRange /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPDelayedCodespaceCIDRange /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 250 /W [32 32 1000 33 33 250] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$runs = $extractor->extractTextRuns($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$span = $line['spans'][0] ?? [];

$flags = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'delayed_codespace_cid_range_resolved' => ($span['bbox'] ?? null) === [0.0, 0.0, 39.0, 12.0],
    'source_space_word_spacing_applied' => ($line['bbox'] ?? null) === [0.0, 0.0, 39.0, 12.0],
    'text_runs_preserved' => $runs === ['AB'],
    'visible_text_clean' => $plainText === 'AB' && !str_contains($plainText, "\0"),
];

$behaviorFlags = array_diff_key($flags, [
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);

if (in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected delayed code-space CMap source-width smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo "<!-- markerpdf-cmap-delayed-codespace-source-width-smoke " . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
