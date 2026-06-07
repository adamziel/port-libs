<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$buildPdf = static function (string $targetKind): array {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPLiteralTargetSourceWidth-H def\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 begincidrange\n"
        . "<10> <13> 60\n"
        . "endcidrange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    if ($targetKind === 'bfchar') {
        $mappingBlock = "8 beginbfchar\n"
            . "<10> (W)\n"
            . "<11> (i)\n"
            . "<12> (d)\n"
            . "<13> (e)\n"
            . "<20> (T)\n"
            . "<21> (h)\n"
            . "<22> (i)\n"
            . "<23> (n)\n"
            . "endbfchar\n";
        $expectedRuns = ['Wide', 'Thin'];
    } elseif ($targetKind === 'array') {
        $mappingBlock = "2 beginbfrange\n"
            . "<10> <13> [(W) (i) (d) (e)]\n"
            . "<20> <23> [(T) (h) (i) (n)]\n"
            . "endbfrange\n";
        $expectedRuns = ['Wide', 'Thin'];
    } else {
        $mappingBlock = "2 beginbfrange\n"
            . "<10> <13> (W)\n"
            . "<20> <23> (T)\n"
            . "endbfrange\n";
        $expectedRuns = ['WXYZ', 'TUVW'];
    }

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . $mappingBlock
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <10111213> Tj '
        . '1 0 0 1 96 720 Tm <20212223> Tj ET';

    return [
        "%PDF-1.4\n"
            . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPLiteralTargetSourceWidth /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
            . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /WPLiteralTargetSourceWidth /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [32 35 1000 60 63 250] >>\nendobj\n"
            . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF",
        $expectedRuns,
    ];
};

$extractor = new PdfTextExtractor();
$flags = [
    'source' => 'native-pdf-cmap-literal-target-source-width-currentbase',
    'support_component' => 'pdf-text-dictionary-core',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach (['bfchar', 'scalar', 'array'] as $kind) {
    [$pdf, $expectedRuns] = $buildPdf($kind);
    $expectedText = implode(' ', $expectedRuns);
    $plainText = $extractor->extractPlainText($pdf);
    $pages = $extractor->extractStyledTextPages($pdf);
    $spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

    $flags[$kind . '_literal_text_preserved'] = $plainText === $expectedText;
    $flags[$kind . '_literal_runs_preserved'] = $extractor->extractTextRuns($pdf) === $expectedRuns;
    $flags[$kind . '_source_width_spans_applied'] = array_column($spans, 'bbox') === [
        [0.0, 0.0, 12.0, 12.0],
        [12.0, 0.0, 60.0, 12.0],
    ];
    $flags[$kind . '_false_join_excluded'] = !str_contains($plainText, implode('', $expectedRuns));
}

$flags['cmap_program_bytes_visible_text_excluded'] = true;
$flags['raw_nul_bytes_excluded'] = true;
foreach (['bfchar', 'scalar', 'array'] as $kind) {
    [$pdf] = $buildPdf($kind);
    $plainText = $extractor->extractPlainText($pdf);
    $flags['cmap_program_bytes_visible_text_excluded'] = $flags['cmap_program_bytes_visible_text_excluded']
        && !str_contains($plainText, 'beginbfchar')
        && !str_contains($plainText, 'beginbfrange');
    $flags['raw_nul_bytes_excluded'] = $flags['raw_nul_bytes_excluded'] && !str_contains($plainText, "\0");
}

$behaviorFlags = array_diff_key($flags, [
    'source' => true,
    'support_component' => true,
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);

if (in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected CMap literal target source-width smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

[$paragraphPdf] = $buildPdf('bfchar');
$paragraphText = $extractor->extractPlainText($paragraphPdf);

echo '<!-- markerpdf-cmap-literal-target-source-width-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($paragraphText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
