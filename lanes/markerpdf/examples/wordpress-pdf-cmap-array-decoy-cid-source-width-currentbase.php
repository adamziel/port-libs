<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';
require_once __DIR__ . '/../src/PdfTextBlockConverter.php';

$buildArrayDecoyCidSourceWidthPdf = static function (string $mappingKind): string {
    if ($mappingKind === 'char') {
        $mappingBlock = "4 begincidchar\n"
            . "[<10> 40 <11> 41 <12> 42 <13> 43]\n"
            . "<10> 60\n"
            . "<11> 61\n"
            . "<12> 62\n"
            . "<13> 63\n"
            . "endcidchar\n";
        $cMapName = 'WPArrayDecoyCidCharSourceWidth-H';
        $baseFont = 'WPArrayDecoyCidCharSourceWidth';
    } else {
        $mappingBlock = "1 begincidrange\n"
            . "[<10> <13> 40]\n"
            . "<10> <13> 60\n"
            . "endcidrange\n";
        $cMapName = 'WPArrayDecoyCidRangeSourceWidth-H';
        $baseFont = 'WPArrayDecoyCidRangeSourceWidth';
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

$inspect = static function (string $pdf): array {
    $extractor = new PdfTextExtractor();
    $pages = $extractor->extractStyledTextPages($pdf);
    $spans = $pages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

    return [
        'lines' => $extractor->extractTextLines($pdf),
        'runs' => $extractor->extractTextRuns($pdf),
        'plain_text' => $extractor->extractPlainText($pdf),
        'span_bboxes' => array_column($spans, 'bbox'),
    ];
};

$range = $inspect($buildArrayDecoyCidSourceWidthPdf('range'));
$char = $inspect($buildArrayDecoyCidSourceWidthPdf('char'));

$expectedSpanBboxes = [[0.0, 0.0, 12.0, 12.0], [12.0, 0.0, 60.0, 12.0]];
$flags = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'array_decoy_cidrange_ignored' => $range['lines'] === ['Wide Thin'],
    'array_decoy_cidchar_ignored' => $char['lines'] === ['Wide Thin'],
    'source_width_spans_applied' => $range['span_bboxes'] === $expectedSpanBboxes
        && $char['span_bboxes'] === $expectedSpanBboxes,
    'word_gap_preserved' => $range['plain_text'] === 'Wide Thin' && $char['plain_text'] === 'Wide Thin',
    'array_payload_excluded' => !str_contains($range['plain_text'], '[')
        && !str_contains($char['plain_text'], '['),
    'wide_decoy_metrics_excluded' => !str_contains($range['plain_text'], 'WideThin')
        && !str_contains($char['plain_text'], 'WideThin'),
];

$behaviorFlags = array_diff_key($flags, [
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);

if (in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected array-decoy CID source-width smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf-cmap-array-decoy-cid-source-width-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($range['lines'] as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
