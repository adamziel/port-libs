<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$buildPdf = static function (string $mappingKind): string {
    if ($mappingKind === 'char') {
        $mappingBlock = "4 begincidchar\n"
            . "<10> 60\n"
            . "<11> 61\n"
            . "<12> 62\n"
            . "<13> 63\n"
            . "endcidchar\n";
        $cMapName = 'WPMissingCodespaceCountCidChar-H';
        $baseFont = 'WPMissingCodespaceCountCidChar';
    } else {
        $mappingBlock = "1 begincidrange\n"
            . "<10> <13> 60\n"
            . "endcidrange\n";
        $cMapName = 'WPMissingCodespaceCountCidRange-H';
        $baseFont = 'WPMissingCodespaceCountCidRange';
    }

    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "begincodespacerange\n"
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
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /{$baseFont} /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [16 19 250 32 35 1000 60 63 1000] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$extractor = new PdfTextExtractor();
$charPdf = $buildPdf('char');
$rangePdf = $buildPdf('range');
$charPages = $extractor->extractStyledTextPages($charPdf);
$rangePages = $extractor->extractStyledTextPages($rangePdf);
$charSpans = $charPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$rangeSpans = $rangePages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$charPlain = $extractor->extractPlainText($charPdf);
$rangePlain = $extractor->extractPlainText($rangePdf);
$lines = $extractor->extractTextLines($charPdf);

$flags = [
    'source' => 'native-pdf-cmap-missing-codespace-count-source-width-currentbase',
    'support_component' => 'pdf-text-dictionary-core',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'missing_codespace_count_cidchar_rejected' => $charPlain === 'Wide Thin',
    'missing_codespace_count_cidrange_rejected' => $rangePlain === 'Wide Thin',
    'source_width_word_gap_preserved' => $lines === ['Wide Thin'],
    'cid_decoy_widths_excluded' => array_column($charSpans, 'bbox') === [[0.0, 0.0, 12.0, 12.0], [12.0, 0.0, 60.0, 12.0]]
        && array_column($rangeSpans, 'bbox') === [[0.0, 0.0, 12.0, 12.0], [12.0, 0.0, 60.0, 12.0]],
    'false_join_excluded' => !str_contains($charPlain, 'WideThin') && !str_contains($rangePlain, 'WideThin'),
    'encoding_cmap_program_visible_text_excluded' => !str_contains($charPlain, 'WPMissingCodespaceCount')
        && !str_contains($rangePlain, 'WPMissingCodespaceCount'),
    'raw_nul_bytes_excluded' => !str_contains($charPlain, "\0") && !str_contains($rangePlain, "\0"),
];

$behaviorFlags = array_diff_key($flags, [
    'source' => true,
    'support_component' => true,
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);

if (in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected missing codespace-count CMap source-width flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-cmap-missing-codespace-count-source-width-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
