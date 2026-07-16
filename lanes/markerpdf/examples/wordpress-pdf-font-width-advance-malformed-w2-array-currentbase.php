<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$makeMalformedW2ArrayPdf = static function (string $w2ArraySegment): string {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
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

    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
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

    $content = 'BT /Fvbad 12 Tf '
        . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
        . '0 -24 Td <00050006000700080009000A> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fvbad 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MalformedVerticalArrayCID /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /CMap /CMapName /MalformedVerticalArrayCID-V /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /MalformedVerticalArrayCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 [880 -1000] /W2 [40 {$w2ArraySegment} 50 55 -250 500 880] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$extractor = new PdfTextExtractor();
$cases = [
    'bad_token' => '[-500 /Bad 880 -500 500 880 -500 500 880 -500 500 880]',
    'incomplete_triple' => '[-500 500 880 -500 500 880 -500 500 880 -500 500]',
];
$review = [];

foreach ($cases as $name => $w2ArraySegment) {
    $pdf = $makeMalformedW2ArrayPdf($w2ArraySegment);
    $plainText = $extractor->extractPlainText($pdf);
    $pages = $extractor->extractStyledTextPages($pdf);
    $line = $pages[0]['blocks'][0]['lines'][0] ?? [];
    $spans = $line['spans'] ?? [];
    $spanBboxes = array_column($spans, 'bbox');

    if ($plainText !== 'Vert Import') {
        throw new RuntimeException("Expected {$name} WordPress paragraph text to preserve the vertical word gap.");
    }

    if (array_column($spans, 'text') !== ['Vert', 'Import']) {
        throw new RuntimeException("Expected {$name} styled spans to remain separated before WordPress import.");
    }

    if ($spanBboxes !== [[0.0, 0.0, 12.0, 48.0], [12.0, 0.0, 24.0, 18.0]]) {
        throw new RuntimeException("Expected {$name} malformed /W2 array metrics to fall back before styled bboxes.");
    }

    if (($line['bbox'] ?? null) !== [0.0, 0.0, 24.0, 48.0]) {
        throw new RuntimeException("Expected {$name} line bbox to use safe fallback advances.");
    }

    if (str_contains($plainText, 'VertImport') || str_contains($plainText, 'MalformedVerticalArrayCID')) {
        throw new RuntimeException("Expected {$name} import text to exclude joined words and font resource payloads.");
    }

    $review[$name] = [
        'wordpress_import_text' => $plainText,
        'malformed_w2_array_rejected' => true,
        'word_gap_preserved' => true,
        'styled_span_bboxes_preserved' => true,
        'partial_vertical_metrics_excluded' => true,
    ];
}

echo '<!-- markerpdf:pdf-font-width-advance-malformed-w2-array-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-cidfont-vertical-w2-malformed-array-boundary-currentbase',
    'cases' => $review,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($review as $case) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($case['wordpress_import_text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
