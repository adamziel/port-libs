<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';
require_once __DIR__ . '/../src/PdfTextBlockConverter.php';

$content = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <0041004200430044> Tj '
    . '1 0 0 1 132 720 Tm <0045004600470048> Tj ET';
$cmap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "8 beginbfchar\n"
    . "<41> <0041>\n"
    . "<42> <0042>\n"
    . "<43> <0043>\n"
    . "<44> <0044>\n"
    . "<45> <0045>\n"
    . "<46> <0046>\n"
    . "<47> <0047>\n"
    . "<48> <0048>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /SourceWidthFallbackSubset /Encoding /Identity-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /SourceWidthFallbackSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 >>\nendobj\n%%EOF";
$metricMissContent = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 108 720 Tm <45464748> Tj ET';
$metricMissPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IdentityMetricMissFallback /Encoding /Identity-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($metricMissContent) . " >>\nstream\n{$metricMissContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IdentityMetricMissFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [65 68 1000 69 72 250] >>\nendobj\n%%EOF";
$defaultMetricMissPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IdentityDefaultMetricMissFallback /Encoding /Identity-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($metricMissContent) . " >>\nstream\n{$metricMissContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /IdentityDefaultMetricMissFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 >>\nendobj\n%%EOF";
$tjGapContent = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm [<41424344> -1000 <45464748>] TJ ET';
$tjGapPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /TjSourceWidthFallback /Encoding /Identity-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($tjGapContent) . " >>\nstream\n{$tjGapContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /TjSourceWidthFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [65 68 1000 69 72 250] >>\nendobj\n%%EOF";
$verticalTjGapCmap = "/CIDInit /ProcSet findresource begin\n"
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
$verticalTjGapContent = 'BT /Fv 12 Tf 1 0 0 1 72 720 Tm [<0001000200030004> 1000 <00050006000700080009000A>] TJ ET';
$verticalTjGapPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fv 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /VerticalTjSourceWidthFallback /Encoding /Identity-V /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($verticalTjGapCmap) . " >>\nstream\n{$verticalTjGapCmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($verticalTjGapContent) . " >>\nstream\n{$verticalTjGapContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /VerticalTjSourceWidthFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [1 4 -500 500 880 5 10 -250 500 880] >>\nendobj\n%%EOF";
$oddHexCmap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "8 beginbfchar\n"
    . "<40> <0044>\n"
    . "<41> <0041>\n"
    . "<42> <0042>\n"
    . "<43> <0043>\n"
    . "<45> <0045>\n"
    . "<46> <0046>\n"
    . "<47> <0047>\n"
    . "<48> <0048>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$oddHexContent = 'BT /Fcid 12 Tf '
    . '1 0 0 1 72 720 Tm <4142434> Tj '
    . '1 0 0 1 108 720 Tm <45464748> Tj ET';
$oddHexPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /OddHexSourceWidthFallback /Encoding /Identity-H /DescendantFonts [5 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($oddHexCmap) . " >>\nstream\n{$oddHexCmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($oddHexContent) . " >>\nstream\n{$oddHexContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /OddHexSourceWidthFallback /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [64 67 1000 69 72 250] >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$metricMissLines = $extractor->extractTextLines($metricMissPdf);
$metricMissPages = $extractor->extractStyledTextPages($metricMissPdf);
$metricMissSpans = $metricMissPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$defaultMetricMissLines = $extractor->extractTextLines($defaultMetricMissPdf);
$defaultMetricMissPages = $extractor->extractStyledTextPages($defaultMetricMissPdf);
$defaultMetricMissSpans = $defaultMetricMissPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$tjGapLines = $extractor->extractTextLines($tjGapPdf);
$tjGapRuns = $extractor->extractTextRuns($tjGapPdf);
$tjGapPages = $extractor->extractStyledTextPages($tjGapPdf);
$tjGapSpans = $tjGapPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$verticalTjGapLines = $extractor->extractTextLines($verticalTjGapPdf);
$verticalTjGapRuns = $extractor->extractTextRuns($verticalTjGapPdf);
$verticalTjGapPages = $extractor->extractStyledTextPages($verticalTjGapPdf);
$verticalTjGapSpans = $verticalTjGapPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$oddHexLines = $extractor->extractTextLines($oddHexPdf);
$oddHexPages = $extractor->extractStyledTextPages($oddHexPdf);
$oddHexSpans = $oddHexPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

echo "<!-- markerpdf-cmap-source-width-fallback-smoke " . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'predefined Identity-H source-width fallback with CIDFont default, metric-miss ToUnicode width fallback, DW-only metric-miss fallback, horizontal and vertical TJ adjustment gap recovery, and odd hex right-padding before Gutenberg paragraph rendering',
    'default_width_source_fallback_applied' => $lines === ['ABCD EFGH'],
    'predefined_identity_source_width_applied' => $lines === ['ABCD EFGH'],
    'padding_bytes_not_counted_as_glyphs' => ($spans[0]['bbox'][2] ?? null) === 48.0,
    'identity_metric_miss_tounicode_widths_applied' => $metricMissLines === ['ABCDEFGH'],
    'identity_metric_miss_false_gap_excluded' => !in_array('ABCD EFGH', $metricMissLines, true),
    'identity_metric_miss_span_widths' => array_column($metricMissSpans, 'bbox') === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
    'identity_default_metric_miss_tounicode_widths_applied' => $defaultMetricMissLines === ['ABCDEFGH'],
    'identity_default_metric_miss_false_gap_excluded' => !in_array('ABCD EFGH', $defaultMetricMissLines, true),
    'identity_default_metric_miss_span_widths' => array_column($defaultMetricMissSpans, 'bbox') === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 96.0, 12.0]],
    'tj_adjustment_source_width_gap_applied' => $tjGapLines === ['ABCD EFGH'],
    'tj_adjustment_source_width_runs_gap_applied' => $tjGapRuns === ['ABCD EFGH'],
    'tj_adjustment_false_join_excluded' => !in_array('ABCDEFGH', $tjGapLines, true),
    'tj_adjustment_runs_false_join_excluded' => !in_array('ABCDEFGH', $tjGapRuns, true),
    'tj_adjustment_span_bbox_preserved' => ($tjGapSpans[0]['bbox'] ?? null) === [0.0, 0.0, 72.0, 12.0],
    'vertical_tj_adjustment_source_width_gap_applied' => $verticalTjGapLines === ['Vert Import'],
    'vertical_tj_adjustment_source_width_runs_gap_applied' => $verticalTjGapRuns === ['Vert Import'],
    'vertical_tj_adjustment_false_join_excluded' => !in_array('VertImport', $verticalTjGapLines, true),
    'vertical_tj_adjustment_runs_false_join_excluded' => !in_array('VertImport', $verticalTjGapRuns, true),
    'vertical_tj_adjustment_span_bbox_preserved' => ($verticalTjGapSpans[0]['bbox'] ?? null) === [0.0, 0.0, 12.0, 54.0],
    'odd_hex_operand_right_padding_applied' => $oddHexLines === ['ABCDEFGH'],
    'odd_hex_operand_false_gap_excluded' => !in_array('ABCD EFGH', $oddHexLines, true),
    'odd_hex_operand_span_widths' => array_column($oddHexSpans, 'bbox') === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach (array_merge($lines, $metricMissLines, $defaultMetricMissLines, $tjGapLines, $verticalTjGapLines, $oddHexLines) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
