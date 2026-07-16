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

$safeText = 'UseCMap Safe Import';
$safeHex = $utf16beHex($safeText);
$derivedCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPDerivedUseCMapBoundary-H def\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<0001> <{$safeHex}>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$inheritedCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPInheritedUseCMapMalformedDecodeParms-H def\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<0002> <" . $utf16beHex('UseCMap DecodeParms Leak') . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedInheritedCMap = gzcompress($inheritedCMap, 0);
if (!is_string($compressedInheritedCMap)) {
    throw new RuntimeException('Unable to compress UseCMap DecodeParms fixture.');
}

$content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPUseCMapDecodeParmsBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /UseCMap 7 0 R /Length " . strlen($derivedCMap) . " >>\nstream\n{$derivedCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor /Twelve /Columns 1 >> /Length " . strlen($compressedInheritedCMap) . " >>\nstream\n{$compressedInheritedCMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$inheritedEntry = null;
foreach ($review['entries'] as $entry) {
    if (($entry['object_number'] ?? null) === 7) {
        $inheritedEntry = $entry;
        break;
    }
}

if ($lines !== [$safeText]) {
    throw new RuntimeException('Expected derived UseCMap-safe WordPress text.');
}

if (
    $inheritedEntry === null
    || ($review['use_cmap_stream_count'] ?? null) !== 1
    || ($inheritedEntry['decodeparms_operand_policy'] ?? null) !== 'reject_malformed_decodeparms_parameters'
) {
    throw new RuntimeException('Expected inherited UseCMap DecodeParms review metadata.');
}

if (
    str_contains($plainText, 'UseCMap DecodeParms Leak')
    || str_contains($plainText, 'Twelve')
    || str_contains($plainText, 'WPInheritedUseCMapMalformedDecodeParms-H')
) {
    throw new RuntimeException('Expected malformed inherited CMap payload to stay out of visible text.');
}

echo '<!-- markerpdf-malformed-usecmap-decodeparms-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'object-valued CMap UseCMap streams are reviewed before inherited ToUnicode mapping',
    'safe_wordpress_text' => $plainText,
    'use_cmap_stream_count' => $review['use_cmap_stream_count'] ?? null,
    'inherited_decodeparms_policy' => $inheritedEntry['decodeparms_operand_policy'] ?? null,
    'inherited_reference_usage' => $inheritedEntry['reference_usages'][0]['usage'] ?? null,
    'inherited_source_object' => $inheritedEntry['reference_usages'][0]['source_object'] ?? null,
    'malformed_cmap_payload_excluded' => !str_contains($plainText, 'UseCMap DecodeParms Leak'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
