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

$buildPdf = static function () use ($utf16beHex): string {
    $safeText = 'Named UseCMap Safe Import';
    $leakingText = 'Named UseCMap Filter Leak';
    $derivedCMapName = 'WPNamedUseCMapDerived-H';
    $baseCMapName = 'WPNamedMalformedBase-H';
    $derivedCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$derivedCMapName} def\n"
        . "/{$baseCMapName} usecmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<0001> <" . $utf16beHex($safeText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $baseCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$baseCMapName} def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<0002> <" . $utf16beHex($leakingText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedBaseCMap = gzcompress($baseCMap, 0);
    if (!is_string($compressedBaseCMap)) {
        throw new RuntimeException('Unable to compress named UseCMap base smoke fixture.');
    }

    $content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj T* <0002> Tj ET';

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPNamedUseCMapFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$derivedCMapName} /Length " . strlen($derivedCMap) . " >>\nstream\n{$derivedCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /CMap /CMapName /{$baseCMapName} /Filter [ << /Owner (WordPress named UseCMap base dictionary is not a decoder) >> /FlateDecode ] /Length " . strlen($compressedBaseCMap) . " >>\nstream\n{$compressedBaseCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$extractor = new PdfTextExtractor();
$pdf = $buildPdf();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entries = [];
foreach ($review['entries'] as $entry) {
    $entries[$entry['object_number'] ?? -1] = $entry;
}
$baseEntry = $entries[7] ?? [];
$baseUsage = $baseEntry['reference_usages'][0] ?? [];

$accepted = $lines === ['Named UseCMap Safe Import']
    && $plainText === 'Named UseCMap Safe Import'
    && !str_contains($plainText, 'Named UseCMap Filter Leak')
    && !str_contains($plainText, 'WPNamedMalformedBase-H')
    && (($review['cmap_stream_count'] ?? null) === 2)
    && (($review['to_unicode_cmap_stream_count'] ?? null) === 1)
    && (($review['use_cmap_stream_count'] ?? null) === 1)
    && (($review['decoded_cmap_count'] ?? null) === 1)
    && (($review['dictionary_filter_operand_count'] ?? null) === 1)
    && (($baseEntry['filter_operand_policy'] ?? null) === 'reject_dictionary_filter_operands')
    && (($baseEntry['decoded_with_current_operands'] ?? null) === false)
    && (($baseUsage['usage'] ?? null) === 'use_cmap')
    && (($baseUsage['source_object'] ?? null) === 6)
    && (($baseUsage['reference_kind'] ?? null) === 'named_usecmap')
    && (($review['executes_python_or_models'] ?? null) === false)
    && (($review['executes_external_pdf_tools'] ?? null) === false);

if (!$accepted) {
    throw new RuntimeException('Named UseCMap malformed base smoke failed before WordPress import.');
}

echo '<!-- markerpdf-malformed-cmap-named-usecmap-filter-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'named usecmap inheritance records malformed filtered base CMap streams while WordPress text import stays fail-closed',
    'fallback_text' => implode(' | ', $lines),
    'cmap_stream_count' => $review['cmap_stream_count'] ?? null,
    'to_unicode_cmap_stream_count' => $review['to_unicode_cmap_stream_count'] ?? null,
    'use_cmap_stream_count' => $review['use_cmap_stream_count'] ?? null,
    'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
    'dictionary_filter_operand_count' => $review['dictionary_filter_operand_count'] ?? null,
    'base_filter_operand_policy' => $baseEntry['filter_operand_policy'] ?? null,
    'base_reference_kind' => $baseUsage['reference_kind'] ?? null,
    'malformed_base_text_excluded' => !str_contains($plainText, 'Named UseCMap Filter Leak')
        && !str_contains($plainText, 'WPNamedMalformedBase-H'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
