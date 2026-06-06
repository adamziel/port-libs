<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$utf16beHex = static function (string $ascii): string {
    $hex = '';
    for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($ascii[$index]));
    }

    return $hex;
};

$safeText = 'Dangling Filter Safe Import';
$leakingText = 'Dangling Filter CMap Leak';
$cMapName = 'DanglingFilterNameBoundary-H';
$danglingName = 'DanglingFilterName';
$safeHex = $utf16beHex($safeText);
$sourceCode = substr($safeHex, 0, 4);
$cMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /{$cMapName} def\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<{$sourceCode}> <" . $utf16beHex($leakingText) . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedCMap = gzcompress($cMap, 0);
if (!is_string($compressedCMap)) {
    throw new RuntimeException('Unable to compress dangling CMap filter example fixture.');
}

$content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DanglingFilterNameBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Length " . strlen($compressedCMap) . " /Filter /FlateDecode /{$danglingName} >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];
$filterOperand = $entry['filter_operands'][0] ?? [];

$lines = [
    'visible_text=' . $plainText,
    'dangling_filter_rejected=' . (($entry['filter_resolution_failed'] ?? null) === true ? 'true' : 'false'),
    'filter_operand_policy=' . (string) ($entry['filter_operand_policy'] ?? ''),
    'extra_filter_name=' . (string) ($filterOperand['extra_filter_name'] ?? ''),
    'decoded_cmap_count=' . (string) ($review['decoded_cmap_count'] ?? ''),
    'cmap_payload_excluded=' . (!str_contains($plainText, $leakingText) && !str_contains($plainText, $cMapName) ? 'true' : 'false'),
    'executes_python_or_models=' . (($review['executes_python_or_models'] ?? true) ? 'true' : 'false'),
    'executes_external_pdf_tools=' . (($review['executes_external_pdf_tools'] ?? true) ? 'true' : 'false'),
];

echo implode("\n", $lines) . "\n";
