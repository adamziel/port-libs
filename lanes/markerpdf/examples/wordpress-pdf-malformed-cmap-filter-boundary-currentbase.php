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

$buildMalformedCMapFilterPdf = static function (
    string $cMapName,
    string $baseFont,
    string $safeText,
    string $leakingText,
    string $filterOperand
) use ($utf16beHex): string {
    $leakingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /{$cMapName} def\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<01> <" . $utf16beHex($leakingText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($leakingCMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress CMap filter-boundary fixture.');
    }

    $safeHex = '';
    for ($index = 0, $length = strlen($safeText); $index < $length; $index++) {
        $safeHex .= sprintf('%04X', ord($safeText[$index]));
    }
    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /{$baseFont} /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /{$cMapName} /Filter [ {$filterOperand} /FlateDecode ] /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$buildIndirectLiteralCMapFilterPdf = static function () use ($utf16beHex): string {
    $leakingText = 'Indirect Literal Filter Leak';
    $safeText = 'Indirect Literal Safe Import';
    $leakingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPIndirectLiteralFilterBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<01> <" . $utf16beHex($leakingText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($leakingCMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress indirect CMap filter-boundary fixture.');
    }

    $safeHex = '';
    for ($index = 0, $length = strlen($safeText); $index < $length; $index++) {
        $safeHex .= sprintf('%04X', ord($safeText[$index]));
    }
    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (?int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset ?? 0,
        $generation,
        $state
    );

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /WPIndirectLiteralFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /CMap /CMapName /WPIndirectLiteralFilterBoundary-H /Filter [ 7 0 R /FlateDecode ] /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
    $addObject(7, 0, '(indirect literal filter is not a decoder)');

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 8\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 7; $objectNumber++) {
        $pdf .= $xrefRow($offsets[$objectNumber] ?? null);
    }
    $pdf .= "trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$buildIndirectArrayDictionaryCMapFilterPdf = static function () use ($utf16beHex): string {
    $leakingText = 'Indirect Array Dictionary Leak';
    $safeText = 'Indirect Array Safe Import';
    $leakingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPIndirectArrayDictionaryFilterBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<01> <" . $utf16beHex($leakingText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($leakingCMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress indirect-array dictionary CMap filter-boundary fixture.');
    }

    $safeHex = '';
    for ($index = 0, $length = strlen($safeText); $index < $length; $index++) {
        $safeHex .= sprintf('%04X', ord($safeText[$index]));
    }
    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (?int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset ?? 0,
        $generation,
        $state
    );

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /WPIndirectArrayDictionaryFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /CMap /CMapName /WPIndirectArrayDictionaryFilterBoundary-H /Filter 7 0 R /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
    $addObject(7, 0, '[ << /Owner (indirect array dictionary is not a decoder) /Fake [ /Nested ] >> /FlateDecode ]');

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 8\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 7; $objectNumber++) {
        $pdf .= $xrefRow($offsets[$objectNumber] ?? null);
    }
    $pdf .= "trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$buildGenerationCMapFilterPdf = static function () use ($utf16beHex): string {
    $safeText = 'Generation Safe Import';
    $safeHex = $utf16beHex($safeText);
    $leakingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPGenerationFilterBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<" . substr($safeHex, 0, 4) . "> <" . $utf16beHex('Stale Generation CMap Leak') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($leakingCMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress generation CMap filter-boundary fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (?int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset ?? 0,
        $generation,
        $state
    );

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /WPGenerationFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /CMap /CMapName /WPGenerationFilterBoundary-H /Filter 7 1 R /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
    $addObject(7, 0, '/FlateDecode');
    $addObject(7, 1, '<< /Owner (current generation dictionary is not a decoder) /StaleValidFilter 7 0 R >>');

    $selected = [
        1 => ['generation' => 0, 'offset' => $offsets['1:0']],
        2 => ['generation' => 0, 'offset' => $offsets['2:0']],
        3 => ['generation' => 0, 'offset' => $offsets['3:0']],
        4 => ['generation' => 0, 'offset' => $offsets['4:0']],
        5 => ['generation' => 0, 'offset' => $offsets['5:0']],
        6 => ['generation' => 0, 'offset' => $offsets['6:0']],
        7 => ['generation' => 1, 'offset' => $offsets['7:1']],
    ];

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 8\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 7; $objectNumber++) {
        $row = $selected[$objectNumber] ?? null;
        $pdf .= $row === null
            ? $xrefRow(0, 65535, 'f')
            : $xrefRow($row['offset'], $row['generation']);
    }
    $pdf .= "trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$buildDecodeParmsCMapFilterPdf = static function () use ($utf16beHex): string {
    $safeText = 'DecodeParms Safe Import';
    $safeHex = $utf16beHex($safeText);
    $leakingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPDecodeParmsBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<" . substr($safeHex, 0, 4) . "> <" . $utf16beHex('DecodeParms CMap Leak') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($leakingCMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress DecodeParms CMap filter-boundary fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (?int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset ?? 0,
        $generation,
        $state
    );

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /WPDecodeParmsBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /CMap /CMapName /WPDecodeParmsBoundary-H /Filter /FlateDecode /DecodeParms 8 1 R /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
    $addObject(8, 0, '<< /Predictor 1 >>');
    $addObject(8, 1, '<< /Predictor /Twelve /Columns 1 >>');

    $selected = [
        1 => ['generation' => 0, 'offset' => $offsets['1:0']],
        2 => ['generation' => 0, 'offset' => $offsets['2:0']],
        3 => ['generation' => 0, 'offset' => $offsets['3:0']],
        4 => ['generation' => 0, 'offset' => $offsets['4:0']],
        5 => ['generation' => 0, 'offset' => $offsets['5:0']],
        6 => ['generation' => 0, 'offset' => $offsets['6:0']],
        8 => ['generation' => 1, 'offset' => $offsets['8:1']],
    ];

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 9\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 8; $objectNumber++) {
        $row = $selected[$objectNumber] ?? null;
        $pdf .= $row === null
            ? $xrefRow(0, 65535, 'f')
            : $xrefRow($row['offset'], $row['generation']);
    }
    $pdf .= "trailer\n<< /Size 9 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$buildTrailingDecodeParmsCMapFilterPdf = static function () use ($utf16beHex): string {
    $safeText = 'Trailing DecodeParms Safe Import';
    $safeHex = $utf16beHex($safeText);
    $leakingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPTrailingDecodeParmsBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<" . substr($safeHex, 0, 4) . "> <" . $utf16beHex('Trailing DecodeParms CMap Leak') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($leakingCMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress trailing DecodeParms CMap filter-boundary fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPTrailingDecodeParmsBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /WPTrailingDecodeParmsBoundary-H /Filter /FlateDecode /DecodeParms [ null << /Predictor /Twelve /Columns 1 >> ] /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$buildStaleReferenceCMapFilterPdf = static function () use ($utf16beHex): string {
    $safeText = 'Stale Reference Safe Import';
    $safeHex = $utf16beHex($safeText);
    $leakingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPStaleReferenceFilterBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<" . substr($safeHex, 0, 4) . "> <" . $utf16beHex('Stale Reference CMap Leak') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($leakingCMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress stale-reference CMap filter-boundary fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (?int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset ?? 0,
        $generation,
        $state
    );

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /WPStaleReferenceFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /CMap /CMapName /WPStaleReferenceFilterBoundary-H /Filter 7 0 R /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
    $addObject(7, 0, '/FlateDecode');
    $addObject(7, 1, '<< /Owner (xref-selected dictionary is not a decoder) /StaleValidFilter 7 0 R >>');

    $selected = [
        1 => ['generation' => 0, 'offset' => $offsets['1:0']],
        2 => ['generation' => 0, 'offset' => $offsets['2:0']],
        3 => ['generation' => 0, 'offset' => $offsets['3:0']],
        4 => ['generation' => 0, 'offset' => $offsets['4:0']],
        5 => ['generation' => 0, 'offset' => $offsets['5:0']],
        6 => ['generation' => 0, 'offset' => $offsets['6:0']],
        7 => ['generation' => 1, 'offset' => $offsets['7:1']],
    ];

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 8\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 7; $objectNumber++) {
        $row = $selected[$objectNumber] ?? null;
        $pdf .= $row === null
            ? $xrefRow(0, 65535, 'f')
            : $xrefRow($row['offset'], $row['generation']);
    }
    $pdf .= "trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$dictionaryPdf = $buildMalformedCMapFilterPdf(
    'WPMalformedFilterBoundary-H',
    'WPMalformedFilterBoundary',
    'Safe Import',
    'Dictionary Filter Leak',
    '<< /Owner (Filter dictionary is not a decoder) /Fake [ /Nested ] >>'
);
$literalPdf = $buildMalformedCMapFilterPdf(
    'WPLiteralFilterBoundary-H',
    'WPLiteralFilterBoundary',
    'Literal Safe Import',
    'Literal Filter Leak',
    '(literal filter is not a decoder)'
);
$indirectLiteralPdf = $buildIndirectLiteralCMapFilterPdf();
$indirectArrayDictionaryPdf = $buildIndirectArrayDictionaryCMapFilterPdf();
$generationPdf = $buildGenerationCMapFilterPdf();
$decodeParmsPdf = $buildDecodeParmsCMapFilterPdf();
$trailingDecodeParmsPdf = $buildTrailingDecodeParmsCMapFilterPdf();
$staleReferencePdf = $buildStaleReferenceCMapFilterPdf();

$extractor = new PdfTextExtractor();
$dictionaryLines = $extractor->extractTextLines($dictionaryPdf);
$literalLines = $extractor->extractTextLines($literalPdf);
$indirectLiteralLines = $extractor->extractTextLines($indirectLiteralPdf);
$indirectArrayDictionaryLines = $extractor->extractTextLines($indirectArrayDictionaryPdf);
$generationLines = $extractor->extractTextLines($generationPdf);
$decodeParmsLines = $extractor->extractTextLines($decodeParmsPdf);
$trailingDecodeParmsLines = $extractor->extractTextLines($trailingDecodeParmsPdf);
$staleReferenceLines = $extractor->extractTextLines($staleReferencePdf);
$dictionaryPlainText = implode("\n", $dictionaryLines);
$literalPlainText = implode("\n", $literalLines);
$indirectLiteralPlainText = implode("\n", $indirectLiteralLines);
$indirectArrayDictionaryPlainText = implode("\n", $indirectArrayDictionaryLines);
$generationPlainText = implode("\n", $generationLines);
$decodeParmsPlainText = implode("\n", $decodeParmsLines);
$trailingDecodeParmsPlainText = implode("\n", $trailingDecodeParmsLines);
$staleReferencePlainText = implode("\n", $staleReferenceLines);
$dictionaryReview = $extractor->extractCMapStreamFilterLengthOwnerReview($dictionaryPdf);
$literalReview = $extractor->extractCMapStreamFilterLengthOwnerReview($literalPdf);
$indirectLiteralReview = $extractor->extractCMapStreamFilterLengthOwnerReview($indirectLiteralPdf);
$indirectArrayDictionaryReview = $extractor->extractCMapStreamFilterLengthOwnerReview($indirectArrayDictionaryPdf);
$generationReview = $extractor->extractCMapStreamFilterLengthOwnerReview($generationPdf);
$decodeParmsReview = $extractor->extractCMapStreamFilterLengthOwnerReview($decodeParmsPdf);
$trailingDecodeParmsReview = $extractor->extractCMapStreamFilterLengthOwnerReview($trailingDecodeParmsPdf);
$staleReferenceReview = $extractor->extractCMapStreamFilterLengthOwnerReview($staleReferencePdf);
$dictionaryEntry = $dictionaryReview['entries'][0] ?? [];
$literalEntry = $literalReview['entries'][0] ?? [];
$indirectLiteralEntry = $indirectLiteralReview['entries'][0] ?? [];
$indirectArrayDictionaryEntry = $indirectArrayDictionaryReview['entries'][0] ?? [];
$generationEntry = $generationReview['entries'][0] ?? [];
$decodeParmsEntry = $decodeParmsReview['entries'][0] ?? [];
$trailingDecodeParmsEntry = $trailingDecodeParmsReview['entries'][0] ?? [];
$staleReferenceEntry = $staleReferenceReview['entries'][0] ?? [];

if ($dictionaryLines !== ['Safe Import']) {
    throw new RuntimeException('Expected malformed dictionary CMap filter fallback text.');
}

if ($literalLines !== ['Literal Safe Import']) {
    throw new RuntimeException('Expected malformed literal CMap filter fallback text.');
}

if ($indirectLiteralLines !== ['Indirect Literal Safe Import']) {
    throw new RuntimeException('Expected malformed indirect literal CMap filter fallback text.');
}

if ($indirectArrayDictionaryLines !== ['Indirect Array Safe Import']) {
    throw new RuntimeException('Expected malformed indirect-array dictionary CMap filter fallback text.');
}

if ($generationLines !== ['Generation Safe Import']) {
    throw new RuntimeException('Expected current-generation malformed CMap filter fallback text.');
}

if ($decodeParmsLines !== ['DecodeParms Safe Import']) {
    throw new RuntimeException('Expected current-generation malformed CMap DecodeParms fallback text.');
}

if ($trailingDecodeParmsLines !== ['Trailing DecodeParms Safe Import']) {
    throw new RuntimeException('Expected trailing malformed CMap DecodeParms fallback text.');
}

if ($staleReferenceLines !== ['Stale Reference Safe Import']) {
    throw new RuntimeException('Expected stale-reference malformed CMap filter fallback text.');
}

if (
    str_contains($dictionaryPlainText, 'Dictionary Filter Leak')
    || str_contains($dictionaryPlainText, 'Filter dictionary is not a decoder')
    || str_contains($literalPlainText, 'Literal Filter Leak')
    || str_contains($literalPlainText, 'literal filter is not a decoder')
    || str_contains($indirectLiteralPlainText, 'Indirect Literal Filter Leak')
    || str_contains($indirectLiteralPlainText, 'indirect literal filter is not a decoder')
    || str_contains($indirectArrayDictionaryPlainText, 'Indirect Array Dictionary Leak')
    || str_contains($indirectArrayDictionaryPlainText, 'indirect array dictionary is not a decoder')
    || str_contains($generationPlainText, 'Stale Generation CMap Leak')
    || str_contains($generationPlainText, 'current generation dictionary is not a decoder')
    || str_contains($decodeParmsPlainText, 'DecodeParms CMap Leak')
    || str_contains($decodeParmsPlainText, 'Twelve')
    || str_contains($trailingDecodeParmsPlainText, 'Trailing DecodeParms CMap Leak')
    || str_contains($trailingDecodeParmsPlainText, 'Twelve')
    || str_contains($staleReferencePlainText, 'Stale Reference CMap Leak')
    || str_contains($staleReferencePlainText, 'xref-selected dictionary is not a decoder')
) {
    throw new RuntimeException('Expected malformed CMap filter payloads to stay excluded.');
}

if (($dictionaryEntry['filter_operand_policy'] ?? null) !== 'reject_dictionary_filter_operands') {
    throw new RuntimeException('Expected dictionary CMap filter operand review metadata.');
}

if (($literalEntry['filter_operand_policy'] ?? null) !== 'reject_malformed_filter_operands') {
    throw new RuntimeException('Expected literal CMap filter operand review metadata.');
}

if (($literalReview['malformed_filter_operand_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected literal CMap filter operand to be classified as malformed.');
}

if (($indirectLiteralEntry['filter_operand_policy'] ?? null) !== 'reject_malformed_filter_operands') {
    throw new RuntimeException('Expected indirect literal CMap filter operand review metadata.');
}

if (($indirectLiteralReview['malformed_filter_operand_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected indirect literal CMap filter operand to be classified as malformed.');
}

if (($indirectArrayDictionaryEntry['filter_operand_policy'] ?? null) !== 'reject_dictionary_filter_operands') {
    throw new RuntimeException('Expected indirect-array dictionary CMap filter operand review metadata.');
}

if (($indirectArrayDictionaryReview['dictionary_filter_operand_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected indirect-array dictionary CMap filter operand to be classified as a dictionary.');
}

if (($indirectArrayDictionaryReview['malformed_filter_operand_count'] ?? null) !== 0) {
    throw new RuntimeException('Expected indirect-array dictionary CMap filter operand not to be classified as generic malformed.');
}

if ((($indirectArrayDictionaryEntry['filter_operands'][0]['dictionary_filter_operand'] ?? null) !== true)) {
    throw new RuntimeException('Expected indirect-array filter operand to expose dictionary_filter_operand=true.');
}

if (($generationEntry['filter_operand_policy'] ?? null) !== 'reject_dictionary_filter_operands') {
    throw new RuntimeException('Expected current-generation CMap filter operand review metadata.');
}

if (($generationReview['decoded_cmap_count'] ?? null) !== 0) {
    throw new RuntimeException('Expected current-generation malformed CMap stream not to decode.');
}

if (($generationEntry['filter_operands'][0]['generation'] ?? null) !== 1) {
    throw new RuntimeException('Expected current-generation CMap filter operand to be selected.');
}

if (($generationEntry['filter_operands'][0]['dictionary_filter_operand'] ?? null) !== true) {
    throw new RuntimeException('Expected current-generation filter operand to expose dictionary_filter_operand=true.');
}

if (($decodeParmsEntry['decodeparms_operand_policy'] ?? null) !== 'reject_malformed_decodeparms_parameters') {
    throw new RuntimeException('Expected current-generation DecodeParms parameter review metadata.');
}

if (($decodeParmsReview['invalid_decodeparms_parameter_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected malformed DecodeParms parameter to fail CMap decoding.');
}

if (($decodeParmsEntry['decodeparms_operands'][0]['generation'] ?? null) !== 1) {
    throw new RuntimeException('Expected current-generation DecodeParms operand to be selected.');
}

if (($trailingDecodeParmsEntry['decodeparms_operand_policy'] ?? null) !== 'reject_malformed_decodeparms_parameters') {
    throw new RuntimeException('Expected trailing malformed DecodeParms parameter review metadata.');
}

if (($trailingDecodeParmsReview['invalid_decodeparms_parameter_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected trailing malformed DecodeParms parameter to fail CMap decoding.');
}

if (($trailingDecodeParmsReview['decoded_cmap_count'] ?? null) !== 0) {
    throw new RuntimeException('Expected trailing malformed DecodeParms CMap stream not to decode.');
}

if (($trailingDecodeParmsEntry['decodeparms_operands'][1]['token_type'] ?? null) !== 'dictionary') {
    throw new RuntimeException('Expected trailing malformed DecodeParms operand to remain review-visible metadata.');
}

if (($staleReferenceEntry['filter_operand_policy'] ?? null) !== 'reject_dictionary_filter_operands') {
    throw new RuntimeException('Expected stale-reference CMap filter operand review metadata.');
}

if (($staleReferenceReview['dictionary_filter_operand_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected stale-reference current xref-selected filter object to be classified as a dictionary.');
}

if (($staleReferenceEntry['owner_policy'] ?? null) !== 'unresolved_or_unselected_indirect_operands') {
    throw new RuntimeException('Expected stale-reference CMap filter operand to remain unselected.');
}

if (($staleReferenceEntry['filter_operands'][0]['generation'] ?? null) !== 0) {
    throw new RuntimeException('Expected stale-reference CMap filter operand to preserve the referenced generation.');
}

if (($staleReferenceEntry['filter_operands'][0]['selected_generation'] ?? null) !== 1) {
    throw new RuntimeException('Expected stale-reference CMap filter operand to report the xref-selected generation.');
}

if (($staleReferenceEntry['filter_operands'][0]['dictionary_filter_operand'] ?? null) !== true) {
    throw new RuntimeException('Expected stale-reference CMap filter operand to expose dictionary_filter_operand=true.');
}

$lines = array_merge(
    $dictionaryLines,
    $literalLines,
    $indirectLiteralLines,
    $indirectArrayDictionaryLines,
    $generationLines,
    $decodeParmsLines,
    $trailingDecodeParmsLines,
    $staleReferenceLines
);

echo '<!-- markerpdf-malformed-cmap-filter-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'malformed ToUnicode CMap Filter array operands fail closed before WordPress text import',
    'fallback_text' => implode(' | ', $lines),
    'dictionary_decoded_cmap_count' => $dictionaryReview['decoded_cmap_count'] ?? null,
    'dictionary_invalid_filter_operand_count' => $dictionaryReview['invalid_filter_operand_count'] ?? null,
    'dictionary_filter_operand_policy' => $dictionaryEntry['filter_operand_policy'] ?? null,
    'literal_decoded_cmap_count' => $literalReview['decoded_cmap_count'] ?? null,
    'literal_invalid_filter_operand_count' => $literalReview['invalid_filter_operand_count'] ?? null,
    'literal_malformed_filter_operand_count' => $literalReview['malformed_filter_operand_count'] ?? null,
    'literal_filter_operand_policy' => $literalEntry['filter_operand_policy'] ?? null,
    'indirect_literal_decoded_cmap_count' => $indirectLiteralReview['decoded_cmap_count'] ?? null,
    'indirect_literal_invalid_filter_operand_count' => $indirectLiteralReview['invalid_filter_operand_count'] ?? null,
    'indirect_literal_malformed_filter_operand_count' => $indirectLiteralReview['malformed_filter_operand_count'] ?? null,
    'indirect_literal_filter_operand_policy' => $indirectLiteralEntry['filter_operand_policy'] ?? null,
    'indirect_literal_owner_policy' => $indirectLiteralEntry['owner_policy'] ?? null,
    'indirect_array_dictionary_decoded_cmap_count' => $indirectArrayDictionaryReview['decoded_cmap_count'] ?? null,
    'indirect_array_dictionary_invalid_filter_operand_count' => $indirectArrayDictionaryReview['invalid_filter_operand_count'] ?? null,
    'indirect_array_dictionary_dictionary_filter_operand_count' => $indirectArrayDictionaryReview['dictionary_filter_operand_count'] ?? null,
    'indirect_array_dictionary_malformed_filter_operand_count' => $indirectArrayDictionaryReview['malformed_filter_operand_count'] ?? null,
    'indirect_array_dictionary_filter_operand_policy' => $indirectArrayDictionaryEntry['filter_operand_policy'] ?? null,
    'indirect_array_dictionary_owner_policy' => $indirectArrayDictionaryEntry['owner_policy'] ?? null,
    'indirect_array_dictionary_operand_classified' => ($indirectArrayDictionaryEntry['filter_operands'][0]['dictionary_filter_operand'] ?? null) === true,
    'generation_decoded_cmap_count' => $generationReview['decoded_cmap_count'] ?? null,
    'generation_invalid_filter_operand_count' => $generationReview['invalid_filter_operand_count'] ?? null,
    'generation_dictionary_filter_operand_count' => $generationReview['dictionary_filter_operand_count'] ?? null,
    'generation_filter_operand_policy' => $generationEntry['filter_operand_policy'] ?? null,
    'generation_owner_policy' => $generationEntry['owner_policy'] ?? null,
    'generation_filter_object_generation' => $generationEntry['filter_operands'][0]['generation'] ?? null,
    'generation_stale_valid_filter_rejected' => ($generationReview['decoded_cmap_count'] ?? null) === 0
        && (($generationEntry['filter_operands'][0]['generation'] ?? null) === 1),
    'decodeparms_decoded_cmap_count' => $decodeParmsReview['decoded_cmap_count'] ?? null,
    'decodeparms_invalid_parameter_count' => $decodeParmsReview['invalid_decodeparms_parameter_count'] ?? null,
    'decodeparms_operand_policy' => $decodeParmsEntry['decodeparms_operand_policy'] ?? null,
    'decodeparms_owner_policy' => $decodeParmsEntry['owner_policy'] ?? null,
    'decodeparms_object_generation' => $decodeParmsEntry['decodeparms_operands'][0]['generation'] ?? null,
    'decodeparms_stale_valid_parameters_rejected' => ($decodeParmsReview['decoded_cmap_count'] ?? null) === 0
        && (($decodeParmsEntry['decodeparms_operands'][0]['generation'] ?? null) === 1),
    'trailing_decodeparms_decoded_cmap_count' => $trailingDecodeParmsReview['decoded_cmap_count'] ?? null,
    'trailing_decodeparms_invalid_parameter_count' => $trailingDecodeParmsReview['invalid_decodeparms_parameter_count'] ?? null,
    'trailing_decodeparms_operand_policy' => $trailingDecodeParmsEntry['decodeparms_operand_policy'] ?? null,
    'trailing_decodeparms_unmatched_parameter_rejected' => ($trailingDecodeParmsReview['decoded_cmap_count'] ?? null) === 0
        && (($trailingDecodeParmsReview['invalid_decodeparms_parameter_count'] ?? null) === 1),
    'stale_reference_decoded_cmap_count' => $staleReferenceReview['decoded_cmap_count'] ?? null,
    'stale_reference_invalid_filter_operand_count' => $staleReferenceReview['invalid_filter_operand_count'] ?? null,
    'stale_reference_dictionary_filter_operand_count' => $staleReferenceReview['dictionary_filter_operand_count'] ?? null,
    'stale_reference_filter_operand_policy' => $staleReferenceEntry['filter_operand_policy'] ?? null,
    'stale_reference_owner_policy' => $staleReferenceEntry['owner_policy'] ?? null,
    'stale_reference_object_generation' => $staleReferenceEntry['filter_operands'][0]['generation'] ?? null,
    'stale_reference_selected_generation' => $staleReferenceEntry['filter_operands'][0]['selected_generation'] ?? null,
    'stale_reference_current_dictionary_classified' => ($staleReferenceEntry['filter_operands'][0]['dictionary_filter_operand'] ?? null) === true,
    'stale_reference_valid_filter_rejected' => ($staleReferenceReview['decoded_cmap_count'] ?? null) === 0
        && (($staleReferenceEntry['filter_operands'][0]['generation'] ?? null) === 0)
        && (($staleReferenceEntry['filter_operands'][0]['selected_generation'] ?? null) === 1),
    'leaking_cmap_text_excluded' => !str_contains($dictionaryPlainText, 'Dictionary Filter Leak')
        && !str_contains($literalPlainText, 'Literal Filter Leak')
        && !str_contains($indirectLiteralPlainText, 'Indirect Literal Filter Leak')
        && !str_contains($indirectArrayDictionaryPlainText, 'Indirect Array Dictionary Leak')
        && !str_contains($generationPlainText, 'Stale Generation CMap Leak')
        && !str_contains($decodeParmsPlainText, 'DecodeParms CMap Leak')
        && !str_contains($trailingDecodeParmsPlainText, 'Trailing DecodeParms CMap Leak')
        && !str_contains($staleReferencePlainText, 'Stale Reference CMap Leak'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
