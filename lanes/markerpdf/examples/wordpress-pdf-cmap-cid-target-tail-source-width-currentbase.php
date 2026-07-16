<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$buildPdf = static function (string $mappingKind): string {
    if ($mappingKind === 'char') {
        $mappingBlock = "1 begincidrange\n"
            . "<10> <13> 60\n"
            . "endcidrange\n"
            . "4 begincidchar\n"
            . "<10> 40.5\n"
            . "<11> 41tail\n"
            . "<12> 42.5\n"
            . "<13> 43tail\n"
            . "endcidchar\n";
        $cMapName = 'WPCidCharTargetTailSourceWidth-H';
        $baseFont = 'WPCidCharTargetTailSourceWidth';
    } else {
        $mappingBlock = "1 begincidrange\n"
            . "<10> <13> 60\n"
            . "endcidrange\n"
            . "1 begincidrange\n"
            . "<10> <13> 40.5\n"
            . "endcidrange\n";
        $cMapName = 'WPCidRangeTargetTailSourceWidth-H';
        $baseFont = 'WPCidRangeTargetTailSourceWidth';
    }

    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . $mappingBlock
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "8 beginbfchar\n"
        . "<10> <0057>\n"
        . "<11> <0069>\n"
        . "<12> <0064>\n"
        . "<13> <0065>\n"
        . "<20> <0054>\n"
        . "<21> <0068>\n"
        . "<22> <0069>\n"
        . "<23> <006E>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <10111213> Tj '
        . '1 0 0 1 96 720 Tm <20212223> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /{$baseFont} /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [40 43 1000 60 63 250 32 35 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$extractor = new PdfTextExtractor();
$charPdf = $buildPdf('char');
$rangePdf = $buildPdf('range');
$charText = $extractor->extractPlainText($charPdf);
$rangeText = $extractor->extractPlainText($rangePdf);
$charPages = $extractor->extractStyledTextPages($charPdf);
$rangePages = $extractor->extractStyledTextPages($rangePdf);
$charSpans = $charPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$rangeSpans = $rangePages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

$flags = [
    'source' => 'native-pdf-cmap-cid-target-tail-source-width-currentbase',
    'support_component' => 'pdf-text-dictionary-core',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'visible_text_preserved' => $charText === 'Wide Thin' && $rangeText === 'Wide Thin',
    'text_runs_preserved' => $extractor->extractTextRuns($charPdf) === ['Wide', 'Thin']
        && $extractor->extractTextRuns($rangePdf) === ['Wide', 'Thin'],
    'malformed_cidchar_targets_rejected' => array_column($charSpans, 'bbox') === [[0.0, 0.0, 12.0, 12.0], [12.0, 0.0, 60.0, 12.0]],
    'malformed_cidrange_target_rejected' => array_column($rangeSpans, 'bbox') === [[0.0, 0.0, 12.0, 12.0], [12.0, 0.0, 60.0, 12.0]],
    'false_join_excluded' => !str_contains($charText, 'WideThin') && !str_contains($rangeText, 'WideThin'),
    'cmap_program_bytes_visible_text_excluded' => !str_contains($charText . $rangeText, 'tail')
        && !str_contains($charText . $rangeText, 'begincidchar')
        && !str_contains($charText . $rangeText, 'begincidrange'),
    'raw_nul_bytes_excluded' => !str_contains($charText . $rangeText, "\0"),
];

$behaviorFlags = array_diff_key($flags, [
    'source' => true,
    'support_component' => true,
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);

if (in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected CMap CID target-tail source-width smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-cmap-cid-target-tail-source-width-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($charText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
