<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<F000> <F0FF>\n"
    . "endcodespacerange\n"
    . "7 beginbfchar\n"
    . "<F020> <2060>\n"
    . "<F041> <0041>\n"
    . "<F042> <0042>\n"
    . "<F043> <0043>\n"
    . "<F044> <0044>\n"
    . "<F045> <0045>\n"
    . "<F046> <0046>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /SimpleType3CMapSpacingCurrentBase-H def\n"
    . "1 begincodespacerange\n"
    . "<F000> <F0FF>\n"
    . "endcodespacerange\n"
    . "7 begincidchar\n"
    . "<F020> 32\n"
    . "<F041> 65\n"
    . "<F042> 66\n"
    . "<F043> 67\n"
    . "<F044> 68\n"
    . "<F045> 69\n"
    . "<F046> 70\n"
    . "endcidchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$widths = array_fill(0, 39, 500.0);
$widthArray = implode(' ', array_map(static fn (float $width): string => rtrim(rtrim(sprintf('%.1F', $width), '0'), '.'), $widths));
$content = 'BT /Ft3 12 Tf 18 Tw 1 0 0 1 72 720 Tm <F041F020F042> Tj '
    . '1 0 0 1 119 720 Tm <F043> Tj '
    . 'T* 1 0 0 1 72 704 Tm [<F044F020F045>] TJ '
    . '1 0 0 1 119 704 Tm <F046> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 25 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CMapSpacing /BaseFont /T3CMapSpacing /FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 32 /LastChar 70 /Widths 22 0 R /Encoding 19 0 R /CharProcs << >> /ToUnicode 20 0 R >>\nendobj\n"
    . "19 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "22 0 obj\n[{$widthArray}]\nendobj\n"
    . "25 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-simple-type3-cmap-spacing-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-simple-type3-cmap-spacing-currentbase',
    'font_spacing_sources' => [
        'Type3 object-valued /Encoding CMap source CIDs',
        'CID 32 word-spacing boundary for current text state Tw',
        'ToUnicode source-code text mapping',
        'Tj and TJ same-line Tm grouping',
    ],
    'type3_cmap_cid_32_used_for_word_spacing' => str_contains($plainText, "A\u{2060}BC"),
    'tj_array_spacing_preserved' => str_contains($plainText, "D\u{2060}EF"),
    'false_tm_spaces_excluded' => !str_contains($plainText, 'B C') && !str_contains($plainText, 'E F'),
    'raw_source_codes_excluded' => !str_contains($plainText, 'F020'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
