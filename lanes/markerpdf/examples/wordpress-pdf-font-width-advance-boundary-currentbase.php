<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /Favg 12 Tf '
    . '1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 108 720 Tm <464748494A> Tj '
    . 'T* 1 0 0 1 72 704 Tm <464748> Tj '
    . '1 0 0 1 120 704 Tm <494A> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Favg 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+AverageAdvance /Encoding 6 0 R /FirstChar 33 /LastChar 36 /Widths [1000 1000 1000 0] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /W /i /d /e 70 /B /l /o /c /k] >>\nendobj\n%%EOF";

$quoteContent = 'BT /Fquote 12 Tf 16 TL 1 0 0 1 72 720 Tm (Lead) Tj 24 6 (A B) " ET';
$quoteWidths = implode(' ', array_fill(0, 35, '1000'));
$quotePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fquote 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+QuoteAdvance /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 66 /Widths [{$quoteWidths}] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($quoteContent) . " >>\nstream\n{$quoteContent}\nendstream\nendobj\n%%EOF";

$verticalEncodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/WMode 1 def\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "2 begincidrange\n"
    . "<0001> <0004> 40\n"
    . "<0005> <000A> 50\n"
    . "endcidrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$verticalToUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "10 beginbfchar\n"
    . "<0001> <0056>\n"
    . "<0002> <0065>\n"
    . "<0003> <0072>\n"
    . "<0004> <0074>\n"
    . "<0005> <0049>\n"
    . "<0006> <006D>\n"
    . "<0007> <0070>\n"
    . "<0008> <006F>\n"
    . "<0009> <0072>\n"
    . "<000A> <0074>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$verticalContent = 'BT /Fv 12 Tf 1 0 0 1 72 720 Tm <0001000200030004> Tj 0 -24 Td <00050006000700080009000A> Tj ET';
$verticalPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /VerticalAdvanceCID /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /CMap /CMapName /VerticalAdvanceCID-V /Length " . strlen($verticalEncodingCMap) . " >>\nstream\n{$verticalEncodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /VerticalAdvanceCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [40 43 -500 500 880 50 55 -250 500 880] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($verticalContent) . " >>\nstream\n{$verticalContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($verticalToUnicode) . " >>\nstream\n{$verticalToUnicode}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$pages = $extractor->extractStyledTextPages($pdf);
$spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$spanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $spans
);
$quoteLines = $extractor->extractTextLines($quotePdf);
$quotePages = $extractor->extractStyledTextPages($quotePdf);
$quoteSpan = $quotePages[0]['blocks'][1]['lines'][0]['spans'][0] ?? [];
$verticalLines = $extractor->extractTextLines($verticalPdf);
$verticalPages = $extractor->extractStyledTextPages($verticalPdf);
$verticalLine = $verticalPages[0]['blocks'][0]['lines'][0] ?? [];
$verticalSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $verticalLine['spans'] ?? []
);

echo '<!-- markerpdf-font-width-advance-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-font-width-advance-boundary-currentbase',
    'source' => 'native-pdf-simple-font-average-positive-quote-and-vertical-width-advance',
    'average_width_preserves_joined_word' => str_contains($plainText, 'WideBlock'),
    'generic_500_width_gap_excluded' => !str_contains($plainText, 'Wide Block'),
    'narrow_positioned_gap_still_preserved' => str_contains($plainText, 'Blo ck'),
    'quote_operator_text_lines_preserved' => $quoteLines === ['Lead', 'A B'],
    'quote_operator_spacing_styled_bbox_preserved' => ($quoteSpan['bbox'] ?? null) === [0.0, 0.0, 72.0, 12.0],
    'vertical_w2_text_line_preserved' => $verticalLines === ['VertImport'],
    'vertical_w2_styled_bboxes_preserved' => $verticalSpanBboxes === [[0.0, 0.0, 12.0, 24.0], [12.0, 0.0, 24.0, 18.0]],
    'vertical_horizontal_fallback_excluded' => $verticalSpanBboxes !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 60.0, 12.0]],
    'span_bboxes' => $spanBboxes,
    'quote_span_bbox' => $quoteSpan['bbox'] ?? null,
    'vertical_span_bboxes' => $verticalSpanBboxes,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach (array_merge($lines, $quoteLines, $verticalLines) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
