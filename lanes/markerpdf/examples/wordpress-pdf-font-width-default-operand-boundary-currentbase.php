<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$buildPdf = static function (string $defaultWidthOperand, string $extraObjects = ''): string {
    $toUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "9 beginbfchar\n"
        . "<0001> <0057>\n"
        . "<0002> <0069>\n"
        . "<0003> <0064>\n"
        . "<0004> <0065>\n"
        . "<0005> <0042>\n"
        . "<0006> <006C>\n"
        . "<0007> <006F>\n"
        . "<0008> <0063>\n"
        . "<0009> <006B>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $content = 'BT /Fdw 12 Tf '
        . '1 0 0 1 72 720 Tm <0001000200030004> Tj '
        . '1 0 0 1 96 720 Tm <00050006000700080009> Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fdw 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DefaultOperandCID /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DefaultOperandCID /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW {$defaultWidthOperand} >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . $extraObjects
        . "%%EOF";
};

$extractor = new PdfTextExtractor();
$directLines = $extractor->extractTextLines($buildPdf('250 1000'));
$indirectLines = $extractor->extractTextLines($buildPdf('7 0 R 1000', "7 0 obj\n250\nendobj\n"));

if ($directLines !== ['WideBlock'] || $indirectLines !== ['WideBlock']) {
    throw new RuntimeException('Expected malformed CIDFont DW operands to fall back to safe default-width advance grouping.');
}

echo '<!-- markerpdf:pdf-font-width-default-operand-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-cidfont-dw-default-operand-boundary-currentbase',
    'direct_tailed_dw_rejected' => true,
    'indirect_tailed_dw_rejected' => true,
    'safe_default_width_used_for_wordpress_paragraphs' => true,
    'font_resource_payload_visible_text_leaked' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($directLines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
