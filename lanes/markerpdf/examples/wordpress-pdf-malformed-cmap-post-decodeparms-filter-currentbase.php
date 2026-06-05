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

$safeText = 'Post DecodeParms Safe Import';
$leakingText = 'Post DecodeParms CMap Leak';
$safeHex = $utf16beHex($safeText);
$sourceCode = substr($safeHex, 0, 4);
$cMapName = 'WPPostDecodeParmsExtraBoundary-H';
$extraFilterName = 'ASCIIHexDecode';
$cMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /{$cMapName} def\n"
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
$compressedCMap = gzcompress($cMap, 0);
if (!is_string($compressedCMap)) {
    throw new RuntimeException('Unable to compress post-DecodeParms extra-filter CMap smoke fixture.');
}

$content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";
$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPPostDecodeParmsExtraBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter /FlateDecode /DecodeParms << /Predictor 1 >> /{$extraFilterName} /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];
$filterOperand = $entry['filter_operands'][0] ?? [];
$decodeParmsOperand = $entry['decodeparms_operands'][0] ?? [];

$flags = [
    'source' => 'native-pdf-malformed-cmap-post-decodeparms-filter-currentbase',
    'safe_import_text_preserved' => $lines === [$safeText] && $plainText === $safeText,
    'cmap_payload_excluded' => !str_contains($plainText, $leakingText) && !str_contains($plainText, $cMapName),
    'extra_filter_name_excluded' => !str_contains($plainText, $extraFilterName),
    'post_decodeparms_extra_decoder_rejected' => ($entry['filter_operand_policy'] ?? null) === 'reject_malformed_filter_operands'
        && ($filterOperand['extra_filter_name'] ?? null) === $extraFilterName
        && ($filterOperand['valid_filter_operand'] ?? null) === false,
    'decodeparms_operand_preserved' => ($entry['decodeparms_operand_policy'] ?? null) === 'decodeparms_resolved'
        && ($decodeParmsOperand['valid_decodeparms_operand'] ?? null) === true,
    'decoded_cmap_count' => $review['decoded_cmap_count'] ?? null,
    'executes_python_or_models' => $review['executes_python_or_models'] ?? true,
    'executes_external_pdf_tools' => $review['executes_external_pdf_tools'] ?? true,
    'paragraphs' => $lines,
];

foreach ([
    'safe_import_text_preserved',
    'cmap_payload_excluded',
    'extra_filter_name_excluded',
    'post_decodeparms_extra_decoder_rejected',
    'decodeparms_operand_preserved',
] as $requiredFlag) {
    if (($flags[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Expected post-DecodeParms extra CMap filter smoke flag to pass: ' . $requiredFlag);
    }
}

if (($flags['decoded_cmap_count'] ?? null) !== 0) {
    throw new RuntimeException('Expected post-DecodeParms extra CMap filter stream to remain undecoded.');
}

echo '<!-- markerpdf:pdf-malformed-cmap-post-decodeparms-filter-currentbase '
    . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo "<p>Post-DecodeParms extra CMap filter operands are rejected before WordPress PDF text import.</p>\n";
