<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';
require_once __DIR__ . '/../src/PdfTextBlockConverter.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /ZeroSourceBroadTail-H def\n"
    . "2 begincodespacerange\n"
    . "<00> <7F>\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "2 begincidchar\n"
    . "<00> 300\n"
    . "<41> 301\n"
    . "endcidchar\n"
    . "1 begincidrange\n"
    . "<0045> <0047> 400\n"
    . "endcidrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "2 begincodespacerange\n"
    . "<00> <7F>\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "2 beginbfchar\n"
    . "<00> <005A>\n"
    . "<41> <0041>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <00410045> Tj '
    . '1 0 0 1 105 720 Tm <0046> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ZeroSourceBroadTail /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /ZeroSourceBroadTail /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [300 301 250 400 402 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$runs = $extractor->extractTextRuns($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$spans = $line['spans'] ?? [];

echo "<!-- markerpdf-cmap-zero-source-broad-tail-currentbase "
    . htmlspecialchars(json_encode([
        'scenario' => 'WordPress searchable PDF import preserves explicit zero CMap source rows before unmapped broad fallback tails',
        'native_boundary' => 'ToUnicode CMap direct <00> source mapping, direct <41> mapping, broad two-byte code-space fallback, Encoding CMap cidchar/cidrange source-width evidence',
        'explicit_zero_source_mapping_preserved' => $lines === ['ZAE F'],
        'text_runs_preserved' => $runs === ['ZAE', 'F'],
        'broad_tail_width_fallback_preserved' => array_column($spans, 'bbox') === [[0.0, 0.0, 18.0, 12.0], [18.0, 0.0, 30.0, 12.0]],
        'line_bbox_preserved' => ($line['bbox'] ?? null) === [0.0, 0.0, 30.0, 12.0],
        'stale_zero_padding_drop_excluded' => $plainText !== 'AE F',
        'false_zero_tail_split_excluded' => $plainText !== 'ZAZE ZF',
        'raw_null_bytes_excluded' => !str_contains($plainText, "\0"),
        'executes_pdf_actions' => false,
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
    ], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $textLine) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($textLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
