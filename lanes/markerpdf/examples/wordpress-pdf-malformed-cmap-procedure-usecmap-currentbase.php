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

$safeText = 'WP Procedure UseCMap Safe Import';
$leakingText = 'WP Procedure UseCMap Leak';
$safeHex = $utf16beHex($safeText);
$sourceCode = substr($safeHex, 0, 4);
$baseCMapName = 'WPProcedureUseCMapDecoy-H';

$derivedCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPProcedureUseCMapDerived-H def\n"
    . "{ /{$baseCMapName} usecmap } bind def\n"
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
    . "<{$sourceCode}> <" . $utf16beHex($leakingText) . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$compressedDerivedCMap = gzcompress($derivedCMap, 0);
$compressedBaseCMap = gzcompress($baseCMap, 0);
if (!is_string($compressedDerivedCMap) || !is_string($compressedBaseCMap)) {
    throw new RuntimeException('Unable to compress WordPress procedure-usecmap CMap fixture.');
}

$content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPProcedureUseCMapBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /WPProcedureUseCMapDerived-H /Filter /FlateDecode /Length " . strlen($compressedDerivedCMap) . " >>\nstream\n{$compressedDerivedCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /CMap /CMapName /{$baseCMapName} /Filter /FlateDecode /Length " . strlen($compressedBaseCMap) . " >>\nstream\n{$compressedBaseCMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entries = $review['entries'] ?? [];
$derivedEntry = $entries[0] ?? [];
$baseEntry = $entries[1] ?? [];

$flags = [
    'source' => 'native-pdf-malformed-cmap-procedure-usecmap-boundary',
    'support_component' => 'pdf-text-dictionary-core',
    'native_boundary' => 'usecmap tokens inside filtered ToUnicode CMap procedure bodies are procedure data, not inheritance operators',
    'safe_text_imported' => $lines === [$safeText],
    'procedure_usecmap_decoy_excluded' => !str_contains($plainText, $leakingText)
        && !str_contains($plainText, $baseCMapName)
        && !str_contains($plainText, 'usecmap'),
    'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
    'use_cmap_stream_count' => $review['use_cmap_stream_count'] ?? null,
    'derived_filter_operand_policy' => $derivedEntry['filter_operand_policy'] ?? null,
    'derived_filter_decode_policy' => $derivedEntry['filter_decode_policy'] ?? null,
    'base_reference_usages' => $baseEntry['reference_usages'] ?? null,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

$behaviorFlags = array_diff_key($flags, [
    'source' => true,
    'support_component' => true,
    'native_boundary' => true,
    'decoded_cmap_count' => true,
    'use_cmap_stream_count' => true,
    'derived_filter_operand_policy' => true,
    'derived_filter_decode_policy' => true,
    'base_reference_usages' => true,
    'paragraphs' => true,
    'executes_python_or_models' => true,
    'executes_external_pdf_tools' => true,
]);

if (
    in_array(false, $behaviorFlags, true)
    || ($flags['decoded_cmap_count'] ?? null) !== 2
    || ($flags['use_cmap_stream_count'] ?? null) !== 0
    || ($flags['derived_filter_operand_policy'] ?? null) !== 'filters_resolved'
    || ($flags['derived_filter_decode_policy'] ?? null) !== 'filter_decoders_resolved'
    || ($flags['base_reference_usages'] ?? null) !== []
) {
    throw new RuntimeException('Expected malformed CMap procedure usecmap smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

if (in_array('--self-test', $argv, true)) {
    echo "OK markerpdf-malformed-cmap-procedure-usecmap-currentbase\n";
}

echo '<!-- markerpdf-malformed-cmap-procedure-usecmap-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
