<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$buildPdf = static function (string $mappingKind): string {
    if ($mappingKind === 'char') {
        $decoyBlock = "begincidchar\n"
            . "<10> 40\n"
            . "<11> 41\n"
            . "<12> 42\n"
            . "<13> 43\n"
            . "endcidchar\n";
        $cMapName = 'MissingDeclaredCountCidCharSourceWidth-H';
        $baseFont = 'MissingDeclaredCountCidCharSourceWidth';
    } else {
        $decoyBlock = "begincidrange\n"
            . "<10> <13> 40\n"
            . "endcidrange\n";
        $cMapName = 'MissingDeclaredCountCidRangeSourceWidth-H';
        $baseFont = 'MissingDeclaredCountCidRangeSourceWidth';
    }

    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<10> <13>\n"
        . "endcodespacerange\n"
        . "4 begincidchar\n"
        . "<10> 60\n"
        . "<11> 61\n"
        . "<12> 62\n"
        . "<13> 63\n"
        . "endcidchar\n"
        . $decoyBlock
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<10> <13>\n"
        . "endcodespacerange\n"
        . "4 beginbfchar\n"
        . "<10> <0057>\n"
        . "<11> <0069>\n"
        . "<12> <0064>\n"
        . "<13> <0065>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf '
        . '1 0 0 1 72 720 Tm <10111213> Tj '
        . '1 0 0 1 96 720 Tm <10111213> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /{$baseFont} /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [40 43 1000 60 63 250] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$extractor = new PdfTextExtractor();
$results = [];
foreach (['char', 'range'] as $kind) {
    $pdf = $buildPdf($kind);
    $pages = $extractor->extractStyledTextPages($pdf);
    $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
    $spans = $line['spans'] ?? [];

    $results[$kind] = [
        'lines' => $extractor->extractTextLines($pdf),
        'plain_text' => $extractor->extractPlainText($pdf),
        'span_texts' => array_column($spans, 'text'),
        'first_span_bbox' => $spans[0]['bbox'] ?? null,
        'second_span_bbox' => $spans[1]['bbox'] ?? null,
        'line_bbox' => $line['bbox'] ?? null,
    ];
}

$charOk = ($results['char']['lines'] ?? null) === ['Wide Wide']
    && ($results['char']['span_texts'] ?? null) === ['Wide', 'Wide']
    && ($results['char']['first_span_bbox'] ?? null) === [0.0, 0.0, 12.0, 12.0]
    && ($results['char']['second_span_bbox'] ?? null) === [12.0, 0.0, 24.0, 12.0];
$rangeOk = ($results['range']['lines'] ?? null) === ['Wide Wide']
    && ($results['range']['span_texts'] ?? null) === ['Wide', 'Wide']
    && ($results['range']['first_span_bbox'] ?? null) === [0.0, 0.0, 12.0, 12.0]
    && ($results['range']['second_span_bbox'] ?? null) === [12.0, 0.0, 24.0, 12.0];

if (!$charOk || !$rangeOk) {
    throw new RuntimeException('Missing declared-count CID CMap source-width fixture did not import as bounded WordPress text.');
}

$metadata = [
    'source' => 'native-pdf-cmap-missing-declared-count-cid-source-width-currentbase',
    'missing_count_cidchar_block_rejected' => $charOk,
    'missing_count_cidrange_block_rejected' => $rangeOk,
    'source_width_word_gap_preserved' => ($results['char']['plain_text'] ?? '') === 'Wide Wide'
        && ($results['range']['plain_text'] ?? '') === 'Wide Wide',
    'wide_decoy_widths_excluded' => ($results['char']['line_bbox'] ?? null) === [0.0, 0.0, 24.0, 12.0]
        && ($results['range']['line_bbox'] ?? null) === [0.0, 0.0, 24.0, 12.0],
    'false_join_excluded' => !str_contains((string) ($results['char']['plain_text'] ?? ''), 'WideWide')
        && !str_contains((string) ($results['range']['plain_text'] ?? ''), 'WideWide'),
    'cmap_program_bytes_visible_text_excluded' => !str_contains((string) ($results['char']['plain_text'] ?? ''), 'MissingDeclaredCountCid')
        && !str_contains((string) ($results['range']['plain_text'] ?? ''), 'MissingDeclaredCountCid'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-cmap-missing-declared-count-cid-source-width-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($results['char']['lines'] as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
