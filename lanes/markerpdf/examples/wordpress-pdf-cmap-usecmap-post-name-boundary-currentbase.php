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

$safeText = 'Safe Import';
$sourceHex = strtoupper(bin2hex($safeText));
$derivedCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /DerivedPostEndNameBoundary-H def\n"
    . "/PostEndNamedBase-H usecmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$baseCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<53> <" . $utf16beHex('PostEnd Named Base Leak') . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n"
    . "/CMapName /PostEndNamedBase-H def\n"
    . "1 beginbfchar\n"
    . "<61> <" . $utf16beHex('PostEnd Trailing Mapping Leak') . ">\n"
    . "endbfchar\n";
$compressedBaseCMap = gzcompress($baseCMap, 0);
if (!is_string($compressedBaseCMap)) {
    throw new RuntimeException('Unable to compress post-end CMap-name smoke fixture.');
}

$content = "BT /Fcid 12 Tf 72 720 Td <{$sourceHex}> Tj ET";
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PostEndNameBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /DerivedPostEndNameBoundary-H /Length " . strlen($derivedCMap) . " >>\nstream\n{$derivedCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /CMap /Filter /FlateDecode /Length " . strlen($compressedBaseCMap) . " >>\nstream\n{$compressedBaseCMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entries = [];
foreach ($review['entries'] as $entry) {
    $entries[$entry['object_number']] = $entry;
}

$derivedEntry = $entries[6] ?? [];
$baseEntry = $entries[7] ?? [];
$flags = [
    'source' => 'native-pdf-cmap-usecmap-post-end-name-boundary',
    'support_component' => 'pdf-text-dictionary-core',
    'paragraphs' => $lines,
    'cmap_stream_count' => $review['cmap_stream_count'] ?? null,
    'to_unicode_cmap_stream_count' => $review['to_unicode_cmap_stream_count'] ?? null,
    'post_end_named_base_excluded_from_usecmap' => $plainText === $safeText,
    'post_end_cmap_name_excluded_from_review' => ($baseEntry['cmap_name'] ?? null) === null,
    'base_post_endcmap_bytes_excluded' => $baseEntry['post_endcmap_bytes_excluded'] ?? false,
    'base_parser_bounded_cmap_bytes_excluded' => $baseEntry['parser_bounded_cmap_bytes_excluded'] ?? false,
    'derived_cmap_name' => $derivedEntry['cmap_name'] ?? null,
    'base_cmap_name' => $baseEntry['cmap_name'] ?? null,
    'visible_text_excludes_cmap_program' => !str_contains($plainText, 'PostEnd Named Base Leak')
        && !str_contains($plainText, 'PostEnd Trailing Mapping Leak')
        && !str_contains($plainText, 'PostEndNamedBase-H')
        && !str_contains($plainText, 'beginbfchar'),
    'executes_python_or_models' => $review['executes_python_or_models'] ?? null,
    'executes_external_pdf_tools' => $review['executes_external_pdf_tools'] ?? null,
];

if ($lines !== [$safeText] || $plainText !== $safeText) {
    throw new RuntimeException('Post-end CMap-name usecmap boundary leaked into imported text.');
}
if ($flags['post_end_cmap_name_excluded_from_review'] !== true || $flags['visible_text_excludes_cmap_program'] !== true) {
    throw new RuntimeException('Post-end CMap-name boundary evidence did not match expected review flags.');
}

echo '<!-- markerpdf-pdf-cmap-usecmap-post-name-boundary-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
