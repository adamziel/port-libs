<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "4 beginbfchar\n"
    . "<41> <0041>\n"
    . "<42> <0042>\n"
    . "<43> <0043>\n"
    . "<44> <0044>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$content = 'BT /Ft3tail 12 Tf '
    . '1 0 0 1 72 720 Tm <4142> Tj '
    . '1 0 0 1 108 720 Tm <4344> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3tail 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3FontMatrixArrayTail /BaseFont /T3FontMatrixArrayTail "
    . "/FontBBox [0 0 1000 700] /FontMatrix 7 0 R "
    . "/FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] /Encoding /WinAnsiEncoding "
    . "/CharProcs << >> /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "7 0 obj\n[0.002 0 0 0.001 0 0] /Tail\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$runs = $extractor->extractTextRuns($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$spans = $line['spans'] ?? [];
$spanBboxes = array_column($spans, 'bbox');

$summary = [
    'scenario' => 'wordpress-pdf-font-width-fontmatrix-array-tail-currentbase',
    'source' => 'native-pdf-type3-fontmatrix-indirect-array-tail-boundary',
    'visible_text' => $plainText,
    'lines' => $lines,
    'runs' => $runs,
    'fontmatrix_array_tail_rejected' => $lines === ['AB CD'] && $plainText === 'AB CD',
    'positioned_word_gap_preserved' => $spanBboxes === [[0.0, 0.0, 24.0, 12.0], [36.0, 0.0, 60.0, 12.0]],
    'styled_line_bbox' => $line['bbox'] ?? null,
    'helper_payload_hidden_from_visible_text' => !str_contains($plainText, 'Tail')
        && !str_contains($plainText, 'T3FontMatrixArrayTail')
        && !str_contains($plainText, 'Ft3tail')
        && !str_contains($plainText, "\0"),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $summary['fontmatrix_array_tail_rejected'] !== true
    || $summary['positioned_word_gap_preserved'] !== true
    || $summary['styled_line_bbox'] !== [0.0, 0.0, 60.0, 12.0]
    || $summary['helper_payload_hidden_from_visible_text'] !== true
) {
    throw new RuntimeException('Type3 FontMatrix array-tail boundary smoke failed: ' . json_encode($summary, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-font-width-fontmatrix-array-tail-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
