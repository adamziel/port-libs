<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$utf16beHex = static function (string $text): string {
    $hex = '';
    for ($index = 0, $length = strlen($text); $index < $length; $index++) {
        $hex .= sprintf('%04X', ord($text[$index]));
    }

    return $hex;
};

$safeText = 'OK';
$leakingText = 'NO';
$safeHex = $utf16beHex($safeText);
$leakHex = $utf16beHex($leakingText);
$derivedName = 'WordPressUnterminatedDictionaryDerived-H';
$forgedBaseName = 'WordPressForgedUnterminatedDictionaryBase-H';

$derivedCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /{$derivedName} def\n"
    . "/{$forgedBaseName} usecmap\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "endcmap\n"
    . "end\n"
    . "end\n";
$baseCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "<< /Malformed <0000> /CMapName /{$forgedBaseName} def\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<{$safeHex}> <{$leakHex}>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "end\n"
    . "end\n";

$compressedDerived = gzcompress($derivedCMap, 0);
$compressedBase = gzcompress($baseCMap, 0);
if (!is_string($compressedDerived) || !is_string($compressedBase)) {
    throw new RuntimeException('Unable to compress malformed CMap WordPress smoke fixture.');
}

$content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WordPressUnterminatedDictionaryNamedBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /Filter /FlateDecode /Length " . strlen($compressedDerived) . " >>\nstream\n{$compressedDerived}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /CMap /Filter /FlateDecode /Length " . strlen($compressedBase) . " >>\nstream\n{$compressedBase}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entries = [];
foreach ($review['entries'] as $entry) {
    $entries[$entry['object_number']] = $entry;
}

$flags = [
    'source' => 'native-pdf-malformed-cmap-unterminated-dictionary-filter-currentbase',
    'support_component' => 'pdf-cmap-core',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'forged_cmap_name_rejected' => array_key_exists('cmap_name', $entries[7] ?? [])
        && $entries[7]['cmap_name'] === null,
    'forged_base_not_referenced' => ($entries[7]['reference_usages'] ?? null) === [],
    'source_width_fallback_preserved' => $lines === [$safeText],
    'visible_text' => $plainText,
    'leaking_mapping_excluded' => !str_contains($plainText, $leakingText),
    'raw_cmap_tokens_excluded' => !str_contains($plainText, 'beginbfchar')
        && !str_contains($plainText, $forgedBaseName),
];

$behaviorFlags = array_diff_key($flags, [
    'source' => true,
    'support_component' => true,
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
    'visible_text' => true,
]);

if (($review['decoded_cmap_count'] ?? null) !== 2 || in_array(false, $behaviorFlags, true)) {
    throw new RuntimeException('Expected malformed CMap unterminated-dictionary smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

echo '<!-- markerpdf:pdf-malformed-cmap-unterminated-dictionary-filter-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
