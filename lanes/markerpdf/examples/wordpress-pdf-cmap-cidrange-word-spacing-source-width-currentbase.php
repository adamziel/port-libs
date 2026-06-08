<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$buildPdf = static function (string $kind): array {
    if ($kind === 'raw-space-remapped') {
        $cMapName = 'WPRawSpaceRemappedCIDRange-H';
        $baseFont = 'WPRawSpaceRemappedCIDRange';
        $codeSpace = "<20> <21>\n";
        $cidRange = "<20> <21> 65\n";
        $toUnicodeRows = "<20> <0041>\n"
            . "<21> <0042>\n";
        $content = 'BT /Fcid 12 Tf 24 Tw 1 0 0 1 72 720 Tm <2021> Tj ET';
        $widths = '[65 66 500]';
        $expectedText = 'AB';
    } else {
        $cMapName = 'WPCidSpaceRangeAdvance-H';
        $baseFont = 'WPCidSpaceRangeAdvance';
        $codeSpace = "<30> <31>\n";
        $cidRange = "<30> <31> 32\n";
        $toUnicodeRows = "<30> <0043>\n"
            . "<31> <0044>\n";
        $content = 'BT /Fcid 12 Tf 24 Tw 1 0 0 1 72 720 Tm <3031> Tj ET';
        $widths = '[32 33 1000]';
        $expectedText = 'CD';
    }

    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . $codeSpace
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . $cidRange
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . $codeSpace
        . "endcodespacerange\n"
        . "2 beginbfchar\n"
        . $toUnicodeRows
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    return [
        "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /{$baseFont} /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W {$widths} >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF",
        $expectedText,
    ];
};

$extractor = new PdfTextExtractor();
[$rawSpacePdf, $rawSpaceText] = $buildPdf('raw-space-remapped');
[$cidSpacePdf, $cidSpaceText] = $buildPdf('cid-space');

$rawSpaceLines = $extractor->extractTextLines($rawSpacePdf);
$rawSpacePlain = $extractor->extractPlainText($rawSpacePdf);
$rawSpaceSpans = $extractor->extractStyledTextPages($rawSpacePdf)[0]['blocks'][0]['lines'][0]['spans'] ?? [];

$cidSpaceLines = $extractor->extractTextLines($cidSpacePdf);
$cidSpacePlain = $extractor->extractPlainText($cidSpacePdf);
$cidSpaceSpans = $extractor->extractStyledTextPages($cidSpacePdf)[0]['blocks'][0]['lines'][0]['spans'] ?? [];

$flags = [
    'source' => 'native-pdf-cmap-cidrange-word-spacing-source-width-currentbase',
    'support_component' => 'pdf-text-dictionary-core',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'raw_source_0x20_uses_cidrange_nonspace' => $rawSpaceLines === [$rawSpaceText] && $rawSpacePlain === $rawSpaceText,
    'raw_source_0x20_false_word_gap_excluded' => !str_contains($rawSpacePlain, 'A B'),
    'raw_source_0x20_word_spacing_not_applied_to_bbox' => array_column($rawSpaceSpans, 'bbox') === [[0.0, 0.0, 12.0, 12.0]],
    'cid32_range_word_spacing_bbox_applied' => $cidSpaceLines === [$cidSpaceText]
        && $cidSpacePlain === $cidSpaceText
        && array_column($cidSpaceSpans, 'bbox') === [[0.0, 0.0, 48.0, 12.0]],
    'raw_nul_bytes_excluded' => !str_contains($rawSpacePlain . $cidSpacePlain, "\0"),
];

$behaviorFlags = array_diff_key($flags, [
    'source' => true,
    'support_component' => true,
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);

if (in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected CMap CID-range word-spacing source-width smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-cmap-cidrange-word-spacing-source-width-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ([$rawSpaceText, $cidSpaceText] as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
