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
    . "9 beginbfchar\n"
    . "<0001> <0057>\n"
    . "<0002> <0069>\n"
    . "<0003> <0064>\n"
    . "<0004> <0065>\n"
    . "<0005> <0042>\n"
    . "<0006> <006C>\n"
    . "<0007> <006F>\n"
    . "<0008> <0063>\n"
    . "<0009> <006B>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcidtail 12 Tf '
    . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
    . '1 0 0 1 120 720 Tm <00050006000700080009> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcidtail 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IndirectCidWidthTail /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IndirectCidWidthTail /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [1 6 0 R 5 9 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n[1000 1000 1000 1000] /Tail\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$firstSpans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

if ($lines !== ['Wide Block'] || $plainText !== 'Wide Block') {
    throw new RuntimeException('Expected tailed indirect CIDFont W helper to fall back to DW word-gap advances.');
}

echo '<!-- markerpdf:pdf-font-width-indirect-cid-array-tail-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-cidfont-indirect-w-array-tail-currentbase',
    'indirect_cid_w_array_tail_rejected' => true,
    'default_dw_word_gap_preserved_for_wordpress_paragraph' => true,
    'width_payload_visible_text_leaked' => false,
    'styled_span_widths' => array_column($firstSpans, 'bbox') === [[0.0, 0.0, 24.0, 12.0], [48.0, 0.0, 108.0, 12.0]],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
