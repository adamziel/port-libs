<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /Type3RawSpaceConflict-H def\n"
    . "1 begincodespacerange\n"
    . "<0000> <00FF>\n"
    . "endcodespacerange\n"
    . "2 begincidchar\n"
    . "<0020> 65\n"
    . "<0021> 66\n"
    . "endcidchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<0000> <00FF>\n"
    . "endcodespacerange\n"
    . "2 beginbfchar\n"
    . "<0020> <0041>\n"
    . "<0021> <0042>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Ft3 12 Tf 16 TL 1 0 0 1 72 720 Tm '
    . '20 0 <0020> " '
    . '1 0 0 1 91 704 Tm <0021> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 25 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3RawSpaceConflict /BaseFont /T3RawSpaceConflict /FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 65 /LastChar 66 /Widths [500 500] /Encoding 19 0 R /CharProcs << >> /ToUnicode 20 0 R >>\nendobj\n"
    . "19 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "25 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf-font-cmap-cid-type3-width-spacing-bundle-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-font-cmap-cid-type3-width-spacing-bundle-currentbase',
    'source' => 'native-pdf-type3-cmap-cid-width-quote-spacing-boundary',
    'font_width_sources' => [
        'Type3 /Encoding CMap source-to-CID rows',
        'Type3 simple-font /Widths array selected through mapped CIDs',
        'double-quote text-showing word-spacing operands',
        'positioned text gap grouping before Gutenberg paragraphs',
    ],
    'explicit_cid_overrides_raw_0x20_for_word_spacing' => $plainText === 'A B',
    'positioned_word_gap_preserved' => !str_contains($plainText, 'AB'),
    'raw_source_code_hidden_from_visible_text' => !str_contains($plainText, '0020'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
