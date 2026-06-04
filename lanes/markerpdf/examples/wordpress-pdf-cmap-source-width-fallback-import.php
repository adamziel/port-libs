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

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$metricMissLines = $extractor->extractTextLines($metricMissPdf);
$metricMissPages = $extractor->extractStyledTextPages($metricMissPdf);
$metricMissSpans = $metricMissPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

echo "<!-- markerpdf-cmap-source-width-fallback-smoke " . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'predefined Identity-H source-width fallback with CIDFont default and metric-miss ToUnicode width fallback before Gutenberg paragraph rendering',
    'default_width_source_fallback_applied' => $lines === ['ABCD EFGH'],
    'predefined_identity_source_width_applied' => $lines === ['ABCD EFGH'],
    'padding_bytes_not_counted_as_glyphs' => ($spans[0]['bbox'][2] ?? null) === 48.0,
    'identity_metric_miss_tounicode_widths_applied' => $metricMissLines === ['ABCDEFGH'],
    'identity_metric_miss_false_gap_excluded' => !in_array('ABCD EFGH', $metricMissLines, true),
    'identity_metric_miss_span_widths' => array_column($metricMissSpans, 'bbox') === [[0.0, 0.0, 48.0, 12.0], [48.0, 0.0, 60.0, 12.0]],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach (array_merge($lines, $metricMissLines) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
