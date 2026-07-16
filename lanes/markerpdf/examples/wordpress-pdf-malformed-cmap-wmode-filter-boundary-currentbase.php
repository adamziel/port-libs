<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$buildPdf = static function (string $wModeFragment): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . $wModeFragment
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "2 begincidrange\n"
        . "<0001> <000A> 40\n"
        . "<0014> <001B> 60\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedEncodingCMap = gzcompress($encodingCMap, 0);
    if (!is_string($compressedEncodingCMap)) {
        throw new RuntimeException('Unable to compress WordPress WMode CMap fixture.');
    }

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "18 beginbfchar\n"
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
        . "<0014> <0044>\n"
        . "<0015> <0061>\n"
        . "<0016> <0074>\n"
        . "<0017> <0061>\n"
        . "<0018> <0046>\n"
        . "<0019> <006C>\n"
        . "<001A> <006F>\n"
        . "<001B> <0077>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fv 12 Tf '
        . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
        . '0 -24 Td <00050006000700080009000A> Tj '
        . '24 24 Td <0014001500160017> Tj '
        . '0 -24 Td <00180019001A001B> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fv 4 0 R >> >> /Contents 6 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPMalformedWModeCIDSubset /Encoding 5 0 R /DescendantFonts [7 0 R] /ToUnicode 8 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /CMap /CMapName /WPMalformedWModeBoundary-H /Filter /FlateDecode /Length " . strlen($compressedEncodingCMap) . " >>\nstream\n{$compressedEncodingCMap}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPMalformedWModeCIDSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [40 [500 500 500 500 500 500 500 500 500 500] 60 [500 500 500 500 500 500 500 500]] /DW2 [880 -1000] /W2 [40 49 -500 500 880 60 67 -250 500 880] >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$extractor = new PdfTextExtractor();
$pdf = $buildPdf("(/WMode 1 def) pop\n");
$lines = $extractor->extractTextLines($pdf);
$text = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];

$evidence = [
    'scenario' => 'wordpress_pdf_malformed_cmap_wmode_filter_boundary_currentbase',
    'safe_lines' => $lines,
    'plain_text' => $text,
    'filtered_cmap_decoded' => ($review['decoded_cmap_count'] ?? null) === 2
        && (($entry['filters'] ?? null) === ['FlateDecode'])
        && (($entry['filter_operand_policy'] ?? null) === 'filters_resolved'),
    'literal_wmode_decoy_ignored' => $lines === ['Vert', 'Import', 'Data', 'Flow']
        && !str_contains($text, 'VertImport')
        && !str_contains($text, 'Data Flow'),
    'review_source' => $review['source'] ?? null,
    'cmap_stream_count' => $review['cmap_stream_count'] ?? null,
    'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
    'filter_operand_policy' => $entry['filter_operand_policy'] ?? null,
    'filters' => $entry['filters'] ?? null,
    'executes_python_or_models' => $review['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $review['executes_external_pdf_tools'] ?? null,
];

if (in_array('--self-test', $argv, true)) {
    foreach ([
        'filtered_cmap_decoded',
        'literal_wmode_decoy_ignored',
    ] as $key) {
        if (($evidence[$key] ?? false) !== true) {
            throw new RuntimeException("Self-test failed: {$key}");
        }
    }

    echo json_encode(['self_test_passed' => true] + $evidence, JSON_UNESCAPED_SLASHES) . "\n";
    return;
}

echo "<!-- wp:comment {\"markerpdf_malformed_cmap_wmode_filter_boundary\":"
    . htmlspecialchars(json_encode($evidence, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "} -->\n";
echo "<!-- /wp:comment -->\n\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
