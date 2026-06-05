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

$relativeTdContent = 'BT /Ftd 12 Tf '
    . '1 0 0 1 72 720 Tm <4142> Tj 24 0 Td <4344> Tj '
    . '1 0 0 1 72 704 Tm <4142> Tj 48 0 Td <4344> Tj ET';
$relativeTdPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftd 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+RelativeTdAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($relativeTdContent) . " >>\nstream\n{$relativeTdContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

$scaledTdContent = 'BT /Fscale 12 Tf '
    . '0.5 0 0 1 72 720 Tm <4142> Tj 24 0 Td <4344> Tj '
    . 'T* 0.5 0 0 1 72 704 Tm <4142> Tj 48 0 Td <4344> Tj ET';
$scaledTdPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fscale 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+ScaledTdAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($scaledTdContent) . " >>\nstream\n{$scaledTdContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

$textMatrixScaleContent = 'BT /Fmatrix 12 Tf '
    . '1 0 0 0.5 72 720 Tm <4142> Tj '
    . '1 0 0 2 96 720 Tm <4344> Tj ET';
$textMatrixScalePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fmatrix 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TextMatrixScale /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($textMatrixScaleContent) . " >>\nstream\n{$textMatrixScaleContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

$negativeTextMatrixContent = 'BT /Fneg 12 Tf -1 0 0 1 72 720 Tm <4142> Tj 1 0 0 1 100 720 Tm <4344> Tj ET';
$negativeTextMatrixPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fneg 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+NegativeAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($negativeTextMatrixContent) . " >>\nstream\n{$negativeTextMatrixContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

$textRiseContent = 'BT /Frise 12 Tf 1 0 0 1 72 720 Tm <4142> Tj 6 Ts <4344> Tj -3 Ts <4546> Tj 0 Ts <4748> Tj ET';
$textRisePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Frise 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TextRiseAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 72 /Widths [1000 1000 1000 1000 1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($textRiseContent) . " >>\nstream\n{$textRiseContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D /E /F /G /H] >>\nendobj\n%%EOF";

$tjBacktrackContent = 'BT /Ftj 12 Tf 1 0 0 1 72 720 Tm [(AB) 3000 (CD)] TJ ET';
$tjBacktrackWidths = implode(' ', array_fill(0, 36, '1000'));
$tjBacktrackPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ftj 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+TjBacktrackAdvance /Encoding /WinAnsiEncoding /FirstChar 32 /LastChar 67 /Widths [{$tjBacktrackWidths}] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($tjBacktrackContent) . " >>\nstream\n{$tjBacktrackContent}\nendstream\nendobj\n%%EOF";

$sparseWidthContent = 'BT /Fsparse 12 Tf 1 0 0 1 72 720 Tm <43> Tj 1 0 0 1 87 720 Tm <44> Tj ET';
$sparseWidthPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fsparse 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+SparseAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 68 /Widths [1000 1000 99 0 R 250] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($sparseWidthContent) . " >>\nstream\n{$sparseWidthContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D] >>\nendobj\n%%EOF";

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
$relativeTdLines = $extractor->extractTextLines($relativeTdPdf);
$relativeTdPlainText = implode("\n", $relativeTdLines);
$relativeTdPages = $extractor->extractStyledTextPages($relativeTdPdf);
$relativeTdSpans = $relativeTdPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$relativeTdSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $relativeTdSpans
);
$scaledTdLines = $extractor->extractTextLines($scaledTdPdf);
$scaledTdPlainText = implode("\n", $scaledTdLines);
$scaledTdPages = $extractor->extractStyledTextPages($scaledTdPdf);
$scaledTdSpans = $scaledTdPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$scaledTdSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $scaledTdSpans
);
$textMatrixScaleLines = $extractor->extractTextLines($textMatrixScalePdf);
$textMatrixScalePlainText = implode("\n", $textMatrixScaleLines);
$textMatrixScalePages = $extractor->extractStyledTextPages($textMatrixScalePdf);
$textMatrixScaleSpans = $textMatrixScalePages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$textMatrixScaleSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $textMatrixScaleSpans
);
$negativeTextMatrixLines = $extractor->extractTextLines($negativeTextMatrixPdf);
$negativeTextMatrixPages = $extractor->extractStyledTextPages($negativeTextMatrixPdf);
$negativeTextMatrixSpans = $negativeTextMatrixPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$negativeTextMatrixSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $negativeTextMatrixSpans
);
$textRiseLines = $extractor->extractTextLines($textRisePdf);
$textRisePlainText = implode("\n", $textRiseLines);
$textRisePages = $extractor->extractStyledTextPages($textRisePdf);
$textRiseLine = $textRisePages[0]['blocks'][0]['lines'][0] ?? [];
$textRiseSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $textRiseLine['spans'] ?? []
);
$tjBacktrackLines = $extractor->extractTextLines($tjBacktrackPdf);
$tjBacktrackPlainText = implode("\n", $tjBacktrackLines);
$tjBacktrackPages = $extractor->extractStyledTextPages($tjBacktrackPdf);
$tjBacktrackLine = $tjBacktrackPages[0]['blocks'][0]['lines'][0] ?? [];
$tjBacktrackSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $tjBacktrackLine['spans'] ?? []
);
$sparseWidthLines = $extractor->extractTextLines($sparseWidthPdf);
$sparseWidthPlainText = implode("\n", $sparseWidthLines);
$sparseWidthPages = $extractor->extractStyledTextPages($sparseWidthPdf);
$sparseWidthSpans = $sparseWidthPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$sparseWidthSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $sparseWidthSpans
);
$verticalLines = $extractor->extractTextLines($verticalPdf);
$verticalPages = $extractor->extractStyledTextPages($verticalPdf);
$verticalLine = $verticalPages[0]['blocks'][0]['lines'][0] ?? [];
$verticalSpanBboxes = array_map(
    static fn (array $span): array => $span['bbox'] ?? [],
    $verticalLine['spans'] ?? []
);

echo '<!-- markerpdf-font-width-advance-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-font-width-advance-boundary-currentbase',
    'source' => 'native-pdf-simple-font-average-positive-quote-relative-scaled-td-text-matrix-vertical-negative-text-rise-and-vertical-width-advance',
    'average_width_preserves_joined_word' => str_contains($plainText, 'WideBlock'),
    'generic_500_width_gap_excluded' => !str_contains($plainText, 'Wide Block'),
    'narrow_positioned_gap_still_preserved' => str_contains($plainText, 'Blo ck'),
    'quote_operator_text_lines_preserved' => $quoteLines === ['Lead', 'A B'],
    'quote_operator_spacing_styled_bbox_preserved' => ($quoteSpan['bbox'] ?? null) === [0.0, 0.0, 72.0, 12.0],
    'relative_td_uses_font_width_current_end' => ($relativeTdLines[0] ?? null) === 'ABCD',
    'relative_td_larger_gap_still_preserved' => ($relativeTdLines[1] ?? null) === 'AB CD',
    'relative_td_false_gap_excluded' => !str_contains($relativeTdPlainText, 'AB CD' . "\n" . 'AB CD'),
    'relative_td_span_bboxes_preserved' => $relativeTdSpanBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'scaled_td_uses_text_matrix_current_end' => ($scaledTdLines[0] ?? null) === 'ABCD',
    'scaled_td_larger_gap_still_preserved' => ($scaledTdLines[1] ?? null) === 'AB CD',
    'scaled_td_false_gap_excluded' => !str_contains($scaledTdPlainText, 'AB CD' . "\n" . 'AB CD'),
    'scaled_td_span_bboxes_preserved' => $scaledTdSpanBboxes === [[0.0, 0.0, 12.0, 12.0], [12.0, 0.0, 24.0, 12.0]],
    'text_matrix_vertical_scale_line_preserved' => $textMatrixScaleLines === ['ABCD'],
    'text_matrix_vertical_scale_false_gap_excluded' => !str_contains($textMatrixScalePlainText, 'AB CD'),
    'text_matrix_vertical_scale_span_bboxes_preserved' => $textMatrixScaleSpanBboxes === [[0.0, 0.0, 24.0, 6.0], [24.0, 0.0, 48.0, 24.0]],
    'text_matrix_vertical_unscaled_height_excluded' => $textMatrixScaleSpanBboxes !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'negative_text_matrix_line_boundary_preserved' => $negativeTextMatrixLines === ['AB CD'],
    'negative_text_matrix_span_bboxes_preserved' => $negativeTextMatrixSpanBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]],
    'negative_text_matrix_collapsed_bbox_excluded' => $negativeTextMatrixSpanBboxes !== [[0.0, 0.0, 1.0, 12.0], [1.0, 0.0, 25.0, 12.0]],
    'text_rise_preserves_horizontal_advance' => $textRiseLines === ['ABCDEFGH'],
    'text_rise_span_bboxes_preserved' => $textRiseSpanBboxes === [[0.0, 0.0, 24.0, 12.0], [24.0, 6.0, 48.0, 18.0], [48.0, -3.0, 72.0, 9.0], [72.0, 0.0, 96.0, 12.0]],
    'text_rise_line_bbox_preserved' => ($textRiseLine['bbox'] ?? null) === [0.0, -3.0, 96.0, 18.0],
    'text_rise_zero_baseline_fallback_excluded' => $textRiseSpanBboxes !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0], [48.0, 0.0, 72.0, 12.0], [72.0, 0.0, 96.0, 12.0]],
    'tj_backtrack_text_preserved' => $tjBacktrackLines === ['ABCD'],
    'tj_backtrack_plain_text_preserved' => $tjBacktrackPlainText === 'ABCD',
    'tj_backtrack_span_bbox_preserved' => $tjBacktrackSpanBboxes === [[0.0, 0.0, 36.0, 12.0]],
    'tj_backtrack_line_bbox_preserved' => ($tjBacktrackLine['bbox'] ?? null) === [0.0, 0.0, 36.0, 12.0],
    'tj_backtrack_final_cursor_collapse_excluded' => $tjBacktrackSpanBboxes !== [[0.0, 0.0, 12.0, 12.0]],
    'unresolved_width_slot_preserved' => $sparseWidthLines === ['CD'],
    'unresolved_width_false_gap_excluded' => !str_contains($sparseWidthPlainText, 'C D'),
    'unresolved_width_slot_bboxes_preserved' => $sparseWidthSpanBboxes === [[0.0, 0.0, 9.0, 12.0], [9.0, 0.0, 12.0, 12.0]],
    'vertical_w2_text_line_preserved' => $verticalLines === ['VertImport'],
    'vertical_w2_styled_bboxes_preserved' => $verticalSpanBboxes === [[0.0, 0.0, 12.0, 24.0], [12.0, 0.0, 24.0, 18.0]],
    'vertical_horizontal_fallback_excluded' => $verticalSpanBboxes !== [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 60.0, 12.0]],
    'span_bboxes' => $spanBboxes,
    'quote_span_bbox' => $quoteSpan['bbox'] ?? null,
    'relative_td_lines' => $relativeTdLines,
    'relative_td_span_bboxes' => $relativeTdSpanBboxes,
    'scaled_td_lines' => $scaledTdLines,
    'scaled_td_span_bboxes' => $scaledTdSpanBboxes,
    'text_matrix_vertical_scale_lines' => $textMatrixScaleLines,
    'text_matrix_vertical_scale_span_bboxes' => $textMatrixScaleSpanBboxes,
    'negative_text_matrix_lines' => $negativeTextMatrixLines,
    'negative_text_matrix_span_bboxes' => $negativeTextMatrixSpanBboxes,
    'text_rise_lines' => $textRiseLines,
    'text_rise_plain_text' => $textRisePlainText,
    'text_rise_span_bboxes' => $textRiseSpanBboxes,
    'text_rise_line_bbox' => $textRiseLine['bbox'] ?? null,
    'tj_backtrack_lines' => $tjBacktrackLines,
    'tj_backtrack_span_bboxes' => $tjBacktrackSpanBboxes,
    'tj_backtrack_line_bbox' => $tjBacktrackLine['bbox'] ?? null,
    'sparse_width_lines' => $sparseWidthLines,
    'sparse_width_span_bboxes' => $sparseWidthSpanBboxes,
    'vertical_span_bboxes' => $verticalSpanBboxes,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach (array_merge($lines, $quoteLines, $relativeTdLines, $scaledTdLines, $textMatrixScaleLines, $negativeTextMatrixLines, $textRiseLines, $tjBacktrackLines, $sparseWidthLines, $verticalLines) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
