<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$buildPdf = static function (string $dw2Operand): string {
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

    $content = 'BT /Fdw2 12 Tf '
        . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
        . '1 0 0 1 72 696 Tm <00050006000700080009000A> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fdw2 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DW2OperandBoundaryCID /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /CMap /CMapName /DW2OperandBoundaryCID-V /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DW2OperandBoundaryCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW2 {$dw2Operand} >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n%%EOF";
};

$extractor = new PdfTextExtractor();
$cases = [
    'name_tail' => '[880 -250 /Tail]',
    'numeric_tail' => '[880 -250 1000]',
];
$review = [];

foreach ($cases as $name => $dw2Operand) {
    $pdf = $buildPdf($dw2Operand);
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

    if ($spanBboxes !== [[0.0, 0.0, 12.0, 48.0], [12.0, 0.0, 24.0, 72.0]]) {
        throw new RuntimeException("Expected {$name} malformed /DW2 metrics to fall back before styled bboxes.");
    }

    if (($line['bbox'] ?? null) !== [0.0, 0.0, 24.0, 72.0]) {
        throw new RuntimeException("Expected {$name} line bbox to use safe default vertical advances.");
    }

    if (str_contains($plainText, 'DW2OperandBoundaryCID') || str_contains($plainText, 'Fdw2') || str_contains($plainText, "\0")) {
        throw new RuntimeException("Expected {$name} import text to exclude font resource payloads.");
    }

    $review[$name] = [
        'wordpress_import_text' => $plainText,
        'malformed_dw2_default_rejected' => true,
        'safe_default_vertical_displacement_used' => true,
        'styled_span_bboxes_preserved' => true,
        'font_resource_payload_visible_text_leaked' => false,
    ];
}

echo '<!-- markerpdf:pdf-font-width-advance-dw2-operand-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-cidfont-dw2-default-operand-boundary-currentbase',
    'cases' => $review,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($review as $case) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($case['wordpress_import_text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
