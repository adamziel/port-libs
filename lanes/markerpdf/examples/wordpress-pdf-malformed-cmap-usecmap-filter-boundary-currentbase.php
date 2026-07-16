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
    $safeText = 'Object UseCMap Safe Import';
    $safeHex = $utf16beHex($safeText);
    $sourceCode = substr($safeHex, 0, 4);
    $forgedBaseName = 'WPForgedObjectBase-H';
    $leakingText = 'Forged Object UseCMap Leak';

    $derivedCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPObjectUseCMapDerived-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedDerivedCMap = gzcompress($derivedCMap, 0);
    if (!is_string($compressedDerivedCMap)) {
        throw new RuntimeException('Unable to compress derived object UseCMap fixture.');
    }

    $baseCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$forgedBaseName} def\n"
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
    $compressedBaseCMap = gzcompress($baseCMap, 0);
    if (!is_string($compressedBaseCMap)) {
        throw new RuntimeException('Unable to compress forged object UseCMap base fixture.');
    }

    $malformedBaseStream = strtoupper(bin2hex($baseCMap));
    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPObjectUseCMapBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /WPObjectUseCMapDerived-H /UseCMap 7 0 R /Filter /FlateDecode /Length " . strlen($compressedDerivedCMap) . " >>\nstream\n{$compressedDerivedCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /CMap /CMapName /{$forgedBaseName} /Filter /ASCIIHexDecode /Length " . strlen($malformedBaseStream) . " >>\nstream\n{$malformedBaseStream}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /CMap /CMapName /{$forgedBaseName} /Filter /FlateDecode /Length " . strlen($compressedBaseCMap) . " >>\nstream\n{$compressedBaseCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$extractor = new PdfTextExtractor();
$pdf = $buildPdf();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);

$derivedEntry = null;
$malformedBaseEntry = null;
$validBaseEntry = null;
foreach ($review['entries'] as $entry) {
    if (($entry['object_number'] ?? null) === 6) {
        $derivedEntry = $entry;
    } elseif (($entry['object_number'] ?? null) === 7) {
        $malformedBaseEntry = $entry;
    } elseif (($entry['object_number'] ?? null) === 8) {
        $validBaseEntry = $entry;
    }
}

if ($lines !== ['Object UseCMap Safe Import']) {
    throw new RuntimeException('Expected malformed object-valued UseCMap fallback text.');
}

if (
    str_contains($plainText, 'Forged Object UseCMap Leak')
    || str_contains($plainText, 'WPForgedObjectBase-H')
    || str_contains($plainText, 'ASCIIHexDecode')
) {
    throw new RuntimeException('Expected malformed object-valued UseCMap CMap payload to stay excluded.');
}

if (!is_array($malformedBaseEntry) || ($malformedBaseEntry['filter_end_marker_policy'] ?? null) !== 'reject_malformed_filter_end_markers') {
    throw new RuntimeException('Expected malformed object-valued UseCMap stream to fail closed.');
}

if (($malformedBaseEntry['filter_end_marker_problems'][0]['problem'] ?? null) !== 'missing_explicit_end_marker') {
    throw new RuntimeException('Expected missing ASCIIHex CMap terminator to remain review-visible.');
}

if (!is_array($derivedEntry) || ($derivedEntry['filter_operand_policy'] ?? null) !== 'filters_resolved') {
    throw new RuntimeException('Expected derived CMap filter to remain resolved.');
}

if (!is_array($validBaseEntry) || ($validBaseEntry['decoded_with_current_operands'] ?? null) !== true) {
    throw new RuntimeException('Expected same-name valid CMap to stay review-visible without inheritance through the malformed reference.');
}

echo '<!-- markerpdf-malformed-cmap-usecmap-filter-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'safe_wordpress_text' => $plainText,
    'object_usecmap_reference' => 7,
    'malformed_usecmap_cmap_name' => $malformedBaseEntry['cmap_name'] ?? null,
    'malformed_usecmap_filters' => $malformedBaseEntry['filters'] ?? [],
    'malformed_usecmap_end_marker_policy' => $malformedBaseEntry['filter_end_marker_policy'] ?? null,
    'malformed_usecmap_end_marker_problem' => $malformedBaseEntry['filter_end_marker_problems'][0]['problem'] ?? null,
    'same_name_valid_cmap_decoded' => ($validBaseEntry['decoded_with_current_operands'] ?? null) === true,
    'forged_name_not_inherited' => !str_contains($plainText, 'Forged Object UseCMap Leak'),
    'malformed_payload_excluded' => !str_contains($plainText, 'WPForgedObjectBase-H')
        && !str_contains($plainText, 'ASCIIHexDecode'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
