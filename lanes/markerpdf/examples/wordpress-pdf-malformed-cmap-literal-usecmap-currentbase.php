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

$realBaseCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPLiteralUseCMapRealBase-H def\n"
    . "1 begincodespacerange\n"
    . "<0001> <0001>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<0001> <" . $utf16beHex('WordPress Literal UseCMap Safe Import') . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedRealBaseCMap = gzcompress($realBaseCMap, 0);
if (!is_string($compressedRealBaseCMap)) {
    throw new RuntimeException('Unable to compress real base CMap fixture.');
}

$decoyBaseCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPLiteralUseCMapDecoyBase-H def\n"
    . "1 begincodespacerange\n"
    . "<0001> <0001>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<0001> <" . $utf16beHex('WordPress Literal UseCMap Leak') . ">\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedDecoyBaseCMap = gzcompress($decoyBaseCMap, 0);
if (!is_string($compressedDecoyBaseCMap)) {
    throw new RuntimeException('Unable to compress decoy base CMap fixture.');
}

$derivedCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPLiteralUseCMapDerived-H def\n"
    . "/WPLiteralUseCMapRealBase-H usecmap\n"
    . "(/WPLiteralUseCMapDecoyBase-H usecmap)\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$compressedDerivedCMap = gzcompress($derivedCMap, 0);
if (!is_string($compressedDerivedCMap)) {
    throw new RuntimeException('Unable to compress derived CMap fixture.');
}

$content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPLiteralUseCMapDerived /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /WPLiteralUseCMapDerived-H /Filter /FlateDecode /Length " . strlen($compressedDerivedCMap) . " >>\nstream\n{$compressedDerivedCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /CMap /CMapName /WPLiteralUseCMapRealBase-H /Filter /FlateDecode /Length " . strlen($compressedRealBaseCMap) . " >>\nstream\n{$compressedRealBaseCMap}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /CMap /CMapName /WPLiteralUseCMapDecoyBase-H /Filter /FlateDecode /Length " . strlen($compressedDecoyBaseCMap) . " >>\nstream\n{$compressedDecoyBaseCMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);

if ($lines !== ['WordPress Literal UseCMap Safe Import']) {
    throw new RuntimeException('Expected real top-level usecmap mapping to own WordPress-visible text.');
}

if (
    str_contains($plainText, 'WordPress Literal UseCMap Leak')
    || str_contains($plainText, 'WPLiteralUseCMapDecoyBase-H')
    || str_contains($plainText, 'usecmap')
) {
    throw new RuntimeException('Expected literal-string usecmap decoy to stay out of WordPress paragraphs.');
}

echo '<!-- markerpdf-malformed-cmap-literal-usecmap-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-filtered-tounicode-cmap-usecmap-token-boundary',
    'support_component' => 'pdf-text-dictionary-core',
    'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
    'literal_usecmap_decoy_excluded' => true,
    'real_base_mapping_preserved' => $plainText === 'WordPress Literal UseCMap Safe Import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
