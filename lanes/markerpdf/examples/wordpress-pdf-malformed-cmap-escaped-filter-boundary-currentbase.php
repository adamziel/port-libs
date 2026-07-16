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

$buildEscapedFilterCMapPdf = static function (
    string $cMapName,
    string $baseFont,
    string $contentHex,
    string $cMapBytes,
    string $filterOperand
): string {
    $content = "BT /Fcid 12 Tf 72 720 Td <{$contentHex}> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter {$filterOperand} /Length " . strlen($cMapBytes) . " >>\nstream\n{$cMapBytes}\nendstream\nendobj\n"
        . "%%EOF";
};

$validMappedText = 'Escaped Filter CMap Import';
$validCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPEscapedFilterNameBoundary-H def\n"
    . "1 begincodespacerange\n"
    . "<0001> <0001>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<0001> <" . $utf16beHex($validMappedText) . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedValidCMap = gzcompress($validCMap, 0);
if (!is_string($compressedValidCMap)) {
    throw new RuntimeException('Unable to compress escaped valid CMap fixture.');
}

$safeFallbackText = 'Escaped Unsupported Safe Import';
$safeFallbackHex = $utf16beHex($safeFallbackText);
$unsupportedCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPEscapedUnsupportedFilterBoundary-H def\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<" . substr($safeFallbackHex, 0, 4) . "> <" . $utf16beHex('Escaped Unsupported CMap Leak') . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$validPdf = $buildEscapedFilterCMapPdf(
    'WPEscapedFilterNameBoundary-H',
    'WPEscapedFilterNameBoundary',
    '0001',
    $compressedValidCMap,
    '[/Fl#61teDecode]'
);
$unsupportedPdf = $buildEscapedFilterCMapPdf(
    'WPEscapedUnsupportedFilterBoundary-H',
    'WPEscapedUnsupportedFilterBoundary',
    $safeFallbackHex,
    $unsupportedCMap,
    '/DCT#44ecode'
);

$extractor = new PdfTextExtractor();
$validLines = $extractor->extractTextLines($validPdf);
$unsupportedLines = $extractor->extractTextLines($unsupportedPdf);
$validReview = $extractor->extractCMapStreamFilterLengthOwnerReview($validPdf);
$unsupportedReview = $extractor->extractCMapStreamFilterLengthOwnerReview($unsupportedPdf);
$validEntry = $validReview['entries'][0] ?? [];
$unsupportedEntry = $unsupportedReview['entries'][0] ?? [];

if ($validLines !== [$validMappedText]) {
    throw new RuntimeException('Expected escaped FlateDecode CMap filter name to decode before WordPress import.');
}

if ($unsupportedLines !== [$safeFallbackText]) {
    throw new RuntimeException('Expected escaped unsupported CMap filter name to fail closed before WordPress import.');
}

$validText = implode("\n", $validLines);
$unsupportedText = implode("\n", $unsupportedLines);
if (
    str_contains($validText, 'WPEscapedFilterNameBoundary-H')
    || str_contains($validText, '/Fl#61teDecode')
    || str_contains($unsupportedText, 'Escaped Unsupported CMap Leak')
    || str_contains($unsupportedText, 'WPEscapedUnsupportedFilterBoundary-H')
    || str_contains($unsupportedText, '/DCT#44ecode')
) {
    throw new RuntimeException('Expected escaped CMap filter implementation details to stay out of visible WordPress text.');
}

if (($validReview['escaped_filter_name_operand_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected escaped valid CMap filter name to be counted in review metadata.');
}

if (($validReview['decoded_cmap_count'] ?? null) !== 1 || ($validEntry['filters'][0] ?? null) !== 'FlateDecode') {
    throw new RuntimeException('Expected escaped valid CMap filter name to normalize to FlateDecode.');
}

if (($unsupportedReview['escaped_filter_name_operand_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected escaped unsupported CMap filter name to be counted in review metadata.');
}

if (($unsupportedReview['unsupported_filter_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected escaped unsupported CMap filter name to remain fail-closed metadata.');
}

if (($unsupportedEntry['filter_operand_policy'] ?? null) !== 'reject_unsupported_filter_names') {
    throw new RuntimeException('Expected escaped unsupported CMap filter name policy to reject unsupported filter names.');
}

echo '<!-- markerpdf-malformed-cmap-escaped-filter-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'escaped ToUnicode CMap Filter names normalize for supported filters but remain fail-closed for unsupported filters before WordPress text import',
    'valid_visible_text' => $validLines,
    'unsupported_visible_text' => $unsupportedLines,
    'valid_filters' => $validEntry['filters'] ?? [],
    'unsupported_filters' => $unsupportedEntry['filters'] ?? [],
    'valid_escaped_filter_name_operand_count' => $validReview['escaped_filter_name_operand_count'] ?? null,
    'unsupported_escaped_filter_name_operand_count' => $unsupportedReview['escaped_filter_name_operand_count'] ?? null,
    'unsupported_filter_count' => $unsupportedReview['unsupported_filter_count'] ?? null,
    'unsupported_filter_operand_policy' => $unsupportedEntry['filter_operand_policy'] ?? null,
    'valid_operand_marked_escaped' => ($validEntry['filter_operands'][0]['escaped_name_operand'] ?? null) === true,
    'unsupported_operand_marked_escaped' => ($unsupportedEntry['filter_operands'][0]['escaped_name_operand'] ?? null) === true,
]) ?: '{}', ENT_NOQUOTES, 'UTF-8') . " -->\n";
