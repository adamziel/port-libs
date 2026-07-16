<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$buildPdf = static function (string $mappingKind): string {
    if ($mappingKind === 'char') {
        $mappingBlock = "1 begincidrange\n"
            . "<10> <13> 60\n"
            . "endcidrange\n"
            . "1 begincidchar\n"
            . "<< /BadRow true >>\n"
            . "<10> 40\n"
            . "endcidchar\n";
        $cMapName = 'MalformedDeclaredCountCidCharSourceWidth-H';
        $baseFont = 'MalformedDeclaredCountCidCharSourceWidth';
    } else {
        $mappingBlock = "1 begincidrange\n"
            . "<10> <13> 60\n"
            . "endcidrange\n"
            . "1 begincidrange\n"
            . "<< /BadRow true >>\n"
            . "<10> <13> 40\n"
            . "endcidrange\n";
        $cMapName = 'MalformedDeclaredCountCidRangeSourceWidth-H';
        $baseFont = 'MalformedDeclaredCountCidRangeSourceWidth';
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
$results = [];
foreach (['char', 'range'] as $kind) {
    $pdf = $buildPdf($kind);
    $lines = $extractor->extractTextLines($pdf);
    $plainText = $extractor->extractPlainText($pdf);
    $pages = $extractor->extractStyledTextPages($pdf);
    $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
    $spans = $line['spans'] ?? [];

    $results[$kind] = [
        'lines' => $lines,
        'plain_text' => $plainText,
        'span_texts' => array_column($spans, 'text'),
        'first_span_bbox' => $spans[0]['bbox'] ?? null,
        'second_span_bbox' => $spans[1]['bbox'] ?? null,
        'line_bbox' => $line['bbox'] ?? null,
    ];
}

$charOk = ($results['char']['lines'] ?? null) === ['Wide Thin']
    && ($results['char']['span_texts'] ?? null) === ['Wide', 'Thin']
    && ($results['char']['first_span_bbox'] ?? null) === [0.0, 0.0, 12.0, 12.0]
    && ($results['char']['second_span_bbox'] ?? null) === [12.0, 0.0, 60.0, 12.0];
$rangeOk = ($results['range']['lines'] ?? null) === ['Wide Thin']
    && ($results['range']['span_texts'] ?? null) === ['Wide', 'Thin']
    && ($results['range']['first_span_bbox'] ?? null) === [0.0, 0.0, 12.0, 12.0]
    && ($results['range']['second_span_bbox'] ?? null) === [12.0, 0.0, 60.0, 12.0];

if (!$charOk || !$rangeOk) {
    throw new RuntimeException('Malformed declared-count CMap CID source-width fixture did not import as bounded WordPress text.');
}

$metadata = [
    'source' => 'native-pdf-cmap-malformed-declared-count-cid-source-width-currentbase',
    'malformed_declared_count_cidchar_slot_consumed' => $charOk,
    'malformed_declared_count_cidrange_slot_consumed' => $rangeOk,
    'decoy_cid_rows_excluded_from_widths' => ($results['char']['plain_text'] ?? '') === 'Wide Thin'
        && ($results['range']['plain_text'] ?? '') === 'Wide Thin',
    'false_join_excluded' => !str_contains((string) ($results['char']['plain_text'] ?? ''), 'WideThin')
        && !str_contains((string) ($results['range']['plain_text'] ?? ''), 'WideThin'),
    'cmap_program_bytes_visible_text_excluded' => !str_contains((string) ($results['char']['plain_text'] ?? ''), 'BadRow')
        && !str_contains((string) ($results['range']['plain_text'] ?? ''), 'BadRow'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-cmap-malformed-declared-count-cid-source-width-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($results['char']['lines'] as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
