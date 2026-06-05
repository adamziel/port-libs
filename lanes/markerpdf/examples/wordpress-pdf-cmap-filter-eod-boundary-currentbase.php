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

$pdfWithCMapAsciiHexEodBoundary = static function (bool $includeAsciiHexEod) use ($utf16beHex): array {
    $safeText = $includeAsciiHexEod ? 'ASCIIHex EOD CMap Import' : 'Missing EOD Safe Import';
    $mappedText = $includeAsciiHexEod ? 'ASCIIHex EOD CMap Import' : 'Missing EOD CMap Leak';
    $sourceHex = $includeAsciiHexEod ? '0001' : $utf16beHex($safeText);
    $cMapSourceHex = $includeAsciiHexEod ? '0001' : substr($sourceHex, 0, 4);
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WordPressCMapFilterEodBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<{$cMapSourceHex}> <" . $utf16beHex($mappedText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $encodedCMap = strtoupper(bin2hex($cMap)) . ($includeAsciiHexEod ? '>' : '');
    $content = "BT /Fcid 12 Tf 72 720 Td <{$sourceHex}> Tj ET";

    $pdf = "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WordPressCMapFilterEodBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /WordPressCMapFilterEodBoundary-H /Filter /ASCIIHexDecode /Length " . strlen($encodedCMap) . " >>\nstream\n{$encodedCMap}\nendstream\nendobj\n"
        . "%%EOF";

    return [$pdf, $safeText, $mappedText];
};

$extractor = new PdfTextExtractor();
[$missingEodPdf, $safeText, $leakingText] = $pdfWithCMapAsciiHexEodBoundary(false);
[$validEodPdf, $validMappedText] = $pdfWithCMapAsciiHexEodBoundary(true);
$missingLines = $extractor->extractTextLines($missingEodPdf);
$validLines = $extractor->extractTextLines($validEodPdf);
$missingReview = $extractor->extractCMapStreamFilterLengthOwnerReview($missingEodPdf);
$validReview = $extractor->extractCMapStreamFilterLengthOwnerReview($validEodPdf);

echo '<!-- markerpdf:pdf-cmap-filter-eod-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-cmap-filter-explicit-eod-boundary',
    'support_component' => 'pdf-text-dictionary-core',
    'filter' => 'ASCIIHexDecode',
    'missing_eod_cmap_rejected' => $missingLines === [$safeText]
        && !str_contains(implode("\n", $missingLines), $leakingText)
        && ($missingReview['decoded_cmap_count'] ?? null) === 0,
    'valid_eod_cmap_accepted' => $validLines === [$validMappedText]
        && ($validReview['decoded_cmap_count'] ?? null) === 1,
    'visible_text_excludes_cmap_program' => !str_contains(implode("\n", $missingLines), 'beginbfchar')
        && !str_contains(implode("\n", $missingLines), 'WordPressCMapFilterEodBoundary-H'),
    'paragraphs' => array_merge($missingLines, $validLines),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach (array_merge($missingLines, $validLines) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
