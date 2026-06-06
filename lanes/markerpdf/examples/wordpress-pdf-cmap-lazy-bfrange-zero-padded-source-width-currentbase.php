<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "1 beginbfrange\n"
    . "<1000> <3007> <0041>\n"
    . "endbfrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <00002000000020010000200200002003> Tj '
    . '1 0 0 1 120 720 Tm <00002004000020050000200600002007> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LazyBfrangeZeroPaddedSourceWidth /Encoding /MissingCustom-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /LazyBfrangeZeroPaddedSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [8192 8195 1000 8196 8199 250] >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$runs = $extractor->extractTextRuns($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$spans = $line['spans'] ?? [];
$spanBboxes = array_column($spans, 'bbox');
$firstRun = "\u{1041}\u{1042}\u{1043}\u{1044}";
$secondRun = "\u{1045}\u{1046}\u{1047}\u{1048}";
$expectedText = $firstRun . $secondRun;

if (
    $lines !== [$expectedText]
    || $runs !== [$firstRun, $secondRun]
    || $plainText !== $expectedText
    || $spanBboxes !== [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]]
) {
    throw new RuntimeException('Expected lazy ToUnicode bfrange suffixes to preserve zero-padded source widths before WordPress import.');
}

$metadata = [
    'scenario' => 'wordpress-pdf-cmap-lazy-bfrange-zero-padded-source-width-currentbase',
    'source' => 'native-pdf-cmap-lazy-bfrange-zero-padded-source-width',
    'lazy_bfrange_text_preserved' => $lines === [$expectedText],
    'zero_padded_width_suffixes_collapsed' => $spanBboxes === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
    'false_wide_padding_bbox_excluded' => $spanBboxes !== [[0.0, 0.0, 72.0, 12.0], [72.0, 0.0, 108.0, 12.0]],
    'cmap_program_bytes_visible_text_excluded' => !str_contains($plainText, 'LazyBfrangeZeroPaddedSourceWidth'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-cmap-lazy-bfrange-zero-padded-source-width-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
