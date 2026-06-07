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

$mappedText = 'Comment Name UseCMap Import';
$baseCMapName = 'CommentSplitUseCMapBase-H';
$derivedCMapName = 'CommentSplitUseCMapDerived-H';

$derivedCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /{$derivedCMapName} def\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
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
    . "<0002> <" . $utf16beHex($mappedText) . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedBaseCMap = gzcompress($baseCMap, 0);
if (!is_string($compressedBaseCMap)) {
    throw new RuntimeException('Unable to compress WordPress CMap UseCMap fixture.');
}

$content = 'BT /Fcid 12 Tf 72 720 Td <0002> Tj ET';
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CommentSplitUseCMap /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /{$derivedCMapName} /UseCMap 8 % comment splits the indirect name reference\n 0 R /Length " . strlen($derivedCMap) . " >>\nstream\n{$derivedCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /CMap /CMapName /{$baseCMapName} /Filter /FlateDecode /Length " . strlen($compressedBaseCMap) . " >>\nstream\n{$compressedBaseCMap}\nendstream\nendobj\n"
    . "8 0 obj\n/{$baseCMapName}\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$baseEntry = null;
foreach ($review['entries'] as $entry) {
    if (($entry['object_number'] ?? null) === 7) {
        $baseEntry = $entry;
        break;
    }
}
$baseUsage = is_array($baseEntry) ? ($baseEntry['reference_usages'][0] ?? []) : [];

$result = [
    'comment_split_usecmap_name_resolved' => ($baseUsage['reference'] ?? null) === $baseCMapName,
    'filtered_base_cmap_inherited' => $plainText === $mappedText,
    'visible_text' => $plainText,
    'base_filters' => is_array($baseEntry) ? ($baseEntry['filters'] ?? []) : [],
    'base_usage' => $baseUsage['usage'] ?? null,
    'base_reference_kind' => $baseUsage['reference_kind'] ?? null,
    'cmap_stream_count' => $review['cmap_stream_count'] ?? null,
    'use_cmap_stream_count' => $review['use_cmap_stream_count'] ?? null,
    'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
    'executes_python_or_models' => $review['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $review['executes_external_pdf_tools'] ?? null,
];

$ok = $result['comment_split_usecmap_name_resolved'] === true
    && $result['filtered_base_cmap_inherited'] === true
    && $result['base_filters'] === ['FlateDecode']
    && $result['base_usage'] === 'use_cmap'
    && $result['base_reference_kind'] === 'named_usecmap'
    && $result['use_cmap_stream_count'] === 1
    && $result['executes_python_or_models'] === false
    && $result['executes_external_pdf_tools'] === false;

echo "<!-- markerpdf-cmap-indirect-usecmap-name-filter-currentbase "
    . json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    . " -->\n";

if (!$ok) {
    fwrite(STDERR, "CMap indirect UseCMap name filter smoke failed\n");
    exit(1);
}
