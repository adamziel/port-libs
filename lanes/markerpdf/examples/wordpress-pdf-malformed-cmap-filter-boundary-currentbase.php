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

$buildNullFilterDecodeParmsCMapPdf = static function () use ($utf16beHex): string {
    $mappedText = 'Null Slot CMap Import';
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPNullFilterDecodeParmsBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0001> <0001>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<0001> <" . $utf16beHex($mappedText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress null-filter DecodeParms CMap fixture.');
    }

    $content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPNullFilterDecodeParmsBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /WPNullFilterDecodeParmsBoundary-H /Filter [ null /FlateDecode ] /DecodeParms [ 99 0 R << /Predictor 1 >> ] /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$buildIndirectNullFilterDecodeParmsCMapPdf = static function () use ($utf16beHex): string {
    $mappedText = 'Indirect Null Slot CMap Import';
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPIndirectNullFilterDecodeParmsBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0001> <0001>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<0001> <" . $utf16beHex($mappedText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress indirect null-filter DecodeParms CMap fixture.');
    }

    $content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';
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
    $addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /WPIndirectNullFilterDecodeParmsBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /CMap /CMapName /WPIndirectNullFilterDecodeParmsBoundary-H /Filter [ null /FlateDecode ] /DecodeParms 8 0 R /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
    $addObject(8, 0, '[ 99 0 R << /Predictor 1 >> ]');

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 9\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 8; $objectNumber++) {
        $pdf .= $xrefRow($offsets[$objectNumber] ?? null);
    }
    $pdf .= "trailer\n<< /Size 9 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
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

$buildPostEndCMapOperatorPdf = static function () use ($utf16beHex): string {
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPPostEndCMapBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0001> <0001>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<0001> <" . $utf16beHex('PostEnd Safe Import') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n"
        . "1 beginbfchar\n"
        . "<0001> <" . $utf16beHex('PostEnd CMap Leak') . ">\n"
        . "endbfchar\n"
        . "/CMapName /WPPostEndCMapDecoy-H def\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress post-endcmap CMap filter-boundary fixture.');
    }

    $content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPPostEndCMapBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /WPPostEndCMapBoundary-H /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$buildSecondProgramCMapPdf = static function () use ($utf16beHex): string {
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPSecondProgramBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0001> <0001>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<0001> <" . $utf16beHex('SecondProgram Safe Import') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n"
        . "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPSecondProgramDecoy-H def\n"
        . "1 begincodespacerange\n"
        . "<0001> <0001>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<0001> <" . $utf16beHex('Second Program CMap Leak') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress second-program CMap filter-boundary fixture.');
    }

    $content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPSecondProgramBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /WPSecondProgramBoundary-H /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$buildUnsupportedCMapFilterPdf = static function () use ($utf16beHex): string {
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPUnsupportedFilterBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<0001> <" . $utf16beHex('Unsupported Filter CMap Leak') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $safeText = 'Unsupported Filter Safe Import';
    $content = "BT /Fcid 12 Tf 72 720 Td <" . $utf16beHex($safeText) . "> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPUnsupportedFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /WPUnsupportedFilterBoundary-H /Filter /DCTDecode /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$buildCryptIdentityCMapFilterPdf = static function () use ($utf16beHex): string {
    $mappedText = 'Identity Crypt CMap Import';
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPCryptIdentityBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0001> <0001>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<0001> <" . $utf16beHex($mappedText) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPCryptIdentityBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /WPCryptIdentityBoundary-H /Filter /Crypt /DecodeParms << /Name /Identity >> /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$buildCryptPrivateCMapFilterPdf = static function () use ($utf16beHex): string {
    $safeText = 'Private Crypt Safe Import';
    $safeHex = $utf16beHex($safeText);
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /WPCryptPrivateBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<" . substr($safeHex, 0, 4) . "> <" . $utf16beHex('Private Crypt CMap Leak') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPCryptPrivateBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /WPCryptPrivateBoundary-H /Filter /Crypt /DecodeParms << /Name /PrivateCF >> /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
        . "%%EOF";
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
$nullFilterDecodeParmsPdf = $buildNullFilterDecodeParmsCMapPdf();
$indirectNullFilterDecodeParmsPdf = $buildIndirectNullFilterDecodeParmsCMapPdf();
$staleReferencePdf = $buildStaleReferenceCMapFilterPdf();
$postEndPdf = $buildPostEndCMapOperatorPdf();
$secondProgramPdf = $buildSecondProgramCMapPdf();
$unsupportedFilterPdf = $buildUnsupportedCMapFilterPdf();
$cryptIdentityPdf = $buildCryptIdentityCMapFilterPdf();
$cryptPrivatePdf = $buildCryptPrivateCMapFilterPdf();

$extractor = new PdfTextExtractor();
$dictionaryLines = $extractor->extractTextLines($dictionaryPdf);
$literalLines = $extractor->extractTextLines($literalPdf);
$indirectLiteralLines = $extractor->extractTextLines($indirectLiteralPdf);
$indirectArrayDictionaryLines = $extractor->extractTextLines($indirectArrayDictionaryPdf);
$generationLines = $extractor->extractTextLines($generationPdf);
$decodeParmsLines = $extractor->extractTextLines($decodeParmsPdf);
$trailingDecodeParmsLines = $extractor->extractTextLines($trailingDecodeParmsPdf);
$nullFilterDecodeParmsLines = $extractor->extractTextLines($nullFilterDecodeParmsPdf);
$indirectNullFilterDecodeParmsLines = $extractor->extractTextLines($indirectNullFilterDecodeParmsPdf);
$staleReferenceLines = $extractor->extractTextLines($staleReferencePdf);
$postEndLines = $extractor->extractTextLines($postEndPdf);
$secondProgramLines = $extractor->extractTextLines($secondProgramPdf);
$unsupportedFilterLines = $extractor->extractTextLines($unsupportedFilterPdf);
$cryptIdentityLines = $extractor->extractTextLines($cryptIdentityPdf);
$cryptPrivateLines = $extractor->extractTextLines($cryptPrivatePdf);
$dictionaryPlainText = implode("\n", $dictionaryLines);
$literalPlainText = implode("\n", $literalLines);
$indirectLiteralPlainText = implode("\n", $indirectLiteralLines);
$indirectArrayDictionaryPlainText = implode("\n", $indirectArrayDictionaryLines);
$generationPlainText = implode("\n", $generationLines);
$decodeParmsPlainText = implode("\n", $decodeParmsLines);
$trailingDecodeParmsPlainText = implode("\n", $trailingDecodeParmsLines);
$nullFilterDecodeParmsPlainText = implode("\n", $nullFilterDecodeParmsLines);
$indirectNullFilterDecodeParmsPlainText = implode("\n", $indirectNullFilterDecodeParmsLines);
$staleReferencePlainText = implode("\n", $staleReferenceLines);
$postEndPlainText = implode("\n", $postEndLines);
$secondProgramPlainText = implode("\n", $secondProgramLines);
$unsupportedFilterPlainText = implode("\n", $unsupportedFilterLines);
$cryptIdentityPlainText = implode("\n", $cryptIdentityLines);
$cryptPrivatePlainText = implode("\n", $cryptPrivateLines);
$dictionaryReview = $extractor->extractCMapStreamFilterLengthOwnerReview($dictionaryPdf);
$literalReview = $extractor->extractCMapStreamFilterLengthOwnerReview($literalPdf);
$indirectLiteralReview = $extractor->extractCMapStreamFilterLengthOwnerReview($indirectLiteralPdf);
$indirectArrayDictionaryReview = $extractor->extractCMapStreamFilterLengthOwnerReview($indirectArrayDictionaryPdf);
$generationReview = $extractor->extractCMapStreamFilterLengthOwnerReview($generationPdf);
$decodeParmsReview = $extractor->extractCMapStreamFilterLengthOwnerReview($decodeParmsPdf);
$trailingDecodeParmsReview = $extractor->extractCMapStreamFilterLengthOwnerReview($trailingDecodeParmsPdf);
$nullFilterDecodeParmsReview = $extractor->extractCMapStreamFilterLengthOwnerReview($nullFilterDecodeParmsPdf);
$indirectNullFilterDecodeParmsReview = $extractor->extractCMapStreamFilterLengthOwnerReview($indirectNullFilterDecodeParmsPdf);
$staleReferenceReview = $extractor->extractCMapStreamFilterLengthOwnerReview($staleReferencePdf);
$postEndReview = $extractor->extractCMapStreamFilterLengthOwnerReview($postEndPdf);
$secondProgramReview = $extractor->extractCMapStreamFilterLengthOwnerReview($secondProgramPdf);
$unsupportedFilterReview = $extractor->extractCMapStreamFilterLengthOwnerReview($unsupportedFilterPdf);
$cryptIdentityReview = $extractor->extractCMapStreamFilterLengthOwnerReview($cryptIdentityPdf);
$cryptPrivateReview = $extractor->extractCMapStreamFilterLengthOwnerReview($cryptPrivatePdf);
$dictionaryEntry = $dictionaryReview['entries'][0] ?? [];
$literalEntry = $literalReview['entries'][0] ?? [];
$indirectLiteralEntry = $indirectLiteralReview['entries'][0] ?? [];
$indirectArrayDictionaryEntry = $indirectArrayDictionaryReview['entries'][0] ?? [];
$generationEntry = $generationReview['entries'][0] ?? [];
$decodeParmsEntry = $decodeParmsReview['entries'][0] ?? [];
$trailingDecodeParmsEntry = $trailingDecodeParmsReview['entries'][0] ?? [];
$nullFilterDecodeParmsEntry = $nullFilterDecodeParmsReview['entries'][0] ?? [];
$indirectNullFilterDecodeParmsEntry = $indirectNullFilterDecodeParmsReview['entries'][0] ?? [];
$staleReferenceEntry = $staleReferenceReview['entries'][0] ?? [];
$postEndEntry = $postEndReview['entries'][0] ?? [];
$secondProgramEntry = $secondProgramReview['entries'][0] ?? [];
$unsupportedFilterEntry = $unsupportedFilterReview['entries'][0] ?? [];
$cryptIdentityEntry = $cryptIdentityReview['entries'][0] ?? [];
$cryptPrivateEntry = $cryptPrivateReview['entries'][0] ?? [];

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

if ($nullFilterDecodeParmsLines !== ['Null Slot CMap Import']) {
    throw new RuntimeException('Expected null-filter DecodeParms CMap text to decode.');
}

if ($indirectNullFilterDecodeParmsLines !== ['Indirect Null Slot CMap Import']) {
    throw new RuntimeException('Expected indirect null-filter DecodeParms CMap text to decode.');
}

if ($staleReferenceLines !== ['Stale Reference Safe Import']) {
    throw new RuntimeException('Expected stale-reference malformed CMap filter fallback text.');
}

if ($postEndLines !== ['PostEnd Safe Import']) {
    throw new RuntimeException('Expected post-endcmap CMap operator payload to stay excluded from WordPress text.');
}

if ($secondProgramLines !== ['SecondProgram Safe Import']) {
    throw new RuntimeException('Expected complete second CMap program to stay excluded from WordPress text.');
}

if ($unsupportedFilterLines !== ['Unsupported Filter Safe Import']) {
    throw new RuntimeException('Expected unsupported CMap filter fallback text.');
}

if ($cryptIdentityLines !== ['Identity Crypt CMap Import']) {
    throw new RuntimeException('Expected identity Crypt CMap filter text to decode.');
}

if ($cryptPrivateLines !== ['Private Crypt Safe Import']) {
    throw new RuntimeException('Expected private Crypt CMap filter fallback text.');
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
    || str_contains($nullFilterDecodeParmsPlainText, '99 0 R')
    || str_contains($nullFilterDecodeParmsPlainText, 'Predictor')
    || str_contains($nullFilterDecodeParmsPlainText, 'WPNullFilterDecodeParmsBoundary-H')
    || str_contains($indirectNullFilterDecodeParmsPlainText, '99 0 R')
    || str_contains($indirectNullFilterDecodeParmsPlainText, 'Predictor')
    || str_contains($indirectNullFilterDecodeParmsPlainText, 'WPIndirectNullFilterDecodeParmsBoundary-H')
    || str_contains($staleReferencePlainText, 'Stale Reference CMap Leak')
    || str_contains($staleReferencePlainText, 'xref-selected dictionary is not a decoder')
    || str_contains($postEndPlainText, 'PostEnd CMap Leak')
    || str_contains($postEndPlainText, 'WPPostEndCMapDecoy-H')
    || str_contains($secondProgramPlainText, 'Second Program CMap Leak')
    || str_contains($secondProgramPlainText, 'WPSecondProgramDecoy-H')
    || str_contains($unsupportedFilterPlainText, 'Unsupported Filter CMap Leak')
    || str_contains($unsupportedFilterPlainText, 'WPUnsupportedFilterBoundary-H')
    || str_contains($unsupportedFilterPlainText, 'DCTDecode')
    || str_contains($cryptIdentityPlainText, 'WPCryptIdentityBoundary-H')
    || str_contains($cryptIdentityPlainText, '/Name')
    || str_contains($cryptPrivatePlainText, 'Private Crypt CMap Leak')
    || str_contains($cryptPrivatePlainText, 'WPCryptPrivateBoundary-H')
    || str_contains($cryptPrivatePlainText, 'PrivateCF')
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

if (($nullFilterDecodeParmsReview['decoded_cmap_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected null-filter DecodeParms CMap stream to decode.');
}

if (($nullFilterDecodeParmsReview['invalid_decodeparms_operand_count'] ?? null) !== 0) {
    throw new RuntimeException('Expected DecodeParms operands aligned to null CMap filters to be ignored.');
}

if (($nullFilterDecodeParmsReview['invalid_decodeparms_parameter_count'] ?? null) !== 0) {
    throw new RuntimeException('Expected null-filter DecodeParms parameter slot not to fail CMap decoding.');
}

if (($nullFilterDecodeParmsEntry['decodeparms_operand_policy'] ?? null) !== 'decodeparms_resolved') {
    throw new RuntimeException('Expected null-filter DecodeParms CMap review metadata to stay resolved.');
}

if (($nullFilterDecodeParmsEntry['decoded_with_current_operands'] ?? null) !== true) {
    throw new RuntimeException('Expected null-filter DecodeParms CMap to decode with current required operands.');
}

if (($nullFilterDecodeParmsEntry['decodeparms_operands'][0]['object_number'] ?? null) !== 99) {
    throw new RuntimeException('Expected ignored null-filter DecodeParms reference to remain review-visible.');
}

if (($nullFilterDecodeParmsEntry['decodeparms_operands'][0]['resolved'] ?? null) !== false) {
    throw new RuntimeException('Expected ignored null-filter DecodeParms reference to remain unresolved metadata.');
}

if (($nullFilterDecodeParmsEntry['decodeparms_operands'][1]['value'] ?? null) !== '<< /Predictor 1 >>') {
    throw new RuntimeException('Expected real FlateDecode CMap DecodeParms to remain review-visible.');
}

if (($indirectNullFilterDecodeParmsReview['decoded_cmap_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected indirect null-filter DecodeParms CMap stream to decode.');
}

if (($indirectNullFilterDecodeParmsReview['invalid_decodeparms_operand_count'] ?? null) !== 0) {
    throw new RuntimeException('Expected indirect DecodeParms arrays aligned to null CMap filters to be ignored.');
}

if (($indirectNullFilterDecodeParmsReview['invalid_decodeparms_parameter_count'] ?? null) !== 0) {
    throw new RuntimeException('Expected indirect null-filter DecodeParms slot not to fail CMap decoding.');
}

if (($indirectNullFilterDecodeParmsEntry['decodeparms_operand_policy'] ?? null) !== 'decodeparms_resolved') {
    throw new RuntimeException('Expected indirect null-filter DecodeParms CMap review metadata to stay resolved.');
}

if (($indirectNullFilterDecodeParmsEntry['decoded_with_current_operands'] ?? null) !== true) {
    throw new RuntimeException('Expected indirect null-filter DecodeParms CMap to decode with current required operands.');
}

if (($indirectNullFilterDecodeParmsEntry['decodeparms_operands'][0]['object_number'] ?? null) !== 8) {
    throw new RuntimeException('Expected indirect DecodeParms array object to remain review-visible.');
}

if (($indirectNullFilterDecodeParmsEntry['decodeparms_operands'][0]['token_type'] ?? null) !== 'array') {
    throw new RuntimeException('Expected indirect DecodeParms array to remain review-visible.');
}

if (($indirectNullFilterDecodeParmsEntry['decodeparms_operands'][0]['value_preview'] ?? null) !== '[ 99 0 R << /Predictor 1 >> ]') {
    throw new RuntimeException('Expected indirect DecodeParms array preview to remain review-visible.');
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

if (($postEndReview['decoded_cmap_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected post-endcmap CMap stream to decode before parser-boundary filtering.');
}

if (($postEndEntry['cmap_name'] ?? null) !== 'WPPostEndCMapBoundary-H') {
    throw new RuntimeException('Expected post-endcmap decoy CMapName to stay excluded.');
}

if (($postEndEntry['post_endcmap_bytes_excluded'] ?? null) !== true) {
    throw new RuntimeException('Expected post-endcmap decoded CMap bytes to be marked excluded.');
}

if (($postEndEntry['post_endcmap_byte_count'] ?? 0) <= 0) {
    throw new RuntimeException('Expected nonzero post-endcmap decoded CMap byte count.');
}

if (($secondProgramReview['decoded_cmap_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected second-program CMap stream to decode before parser-boundary filtering.');
}

if (($secondProgramEntry['cmap_name'] ?? null) !== 'WPSecondProgramBoundary-H') {
    throw new RuntimeException('Expected second-program decoy CMapName to stay excluded.');
}

if (($secondProgramEntry['post_endcmap_bytes_excluded'] ?? null) !== true) {
    throw new RuntimeException('Expected complete second CMap program bytes to be marked excluded.');
}

if (($secondProgramEntry['parser_bounded_cmap_bytes_excluded'] ?? null) !== true) {
    throw new RuntimeException('Expected parser-bounded CMap body to exclude the complete second program.');
}

if (($secondProgramEntry['parser_excluded_cmap_byte_count'] ?? 0) <= ($secondProgramEntry['post_endcmap_byte_count'] ?? 0)) {
    throw new RuntimeException('Expected parser-bounded CMap exclusion to exceed trailing post-endcmap cleanup bytes.');
}

if (($unsupportedFilterReview['decoded_cmap_count'] ?? null) !== 0) {
    throw new RuntimeException('Expected unsupported-filter CMap stream not to decode.');
}

if (($unsupportedFilterReview['unsupported_filter_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected unsupported CMap filter to be counted.');
}

if (($unsupportedFilterEntry['filter_operand_policy'] ?? null) !== 'reject_unsupported_filter_names') {
    throw new RuntimeException('Expected unsupported CMap filter operand review metadata.');
}

if (($unsupportedFilterEntry['filters'][0] ?? null) !== 'DCTDecode') {
    throw new RuntimeException('Expected unsupported CMap filter name to remain review-visible metadata.');
}

if (($cryptIdentityReview['decoded_cmap_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected identity Crypt CMap stream to decode.');
}

if (($cryptIdentityReview['unsupported_filter_count'] ?? null) !== 0) {
    throw new RuntimeException('Expected identity Crypt CMap filter not to be counted as unsupported.');
}

if (($cryptIdentityEntry['filter_operand_policy'] ?? null) !== 'filters_resolved') {
    throw new RuntimeException('Expected identity Crypt CMap filter to remain resolved review metadata.');
}

if (($cryptIdentityEntry['decodeparms_operands'][0]['value'] ?? null) !== '<< /Name /Identity >>') {
    throw new RuntimeException('Expected identity Crypt DecodeParms to remain review-visible.');
}

if (($cryptPrivateReview['decoded_cmap_count'] ?? null) !== 0) {
    throw new RuntimeException('Expected named Crypt CMap stream not to decode.');
}

if (($cryptPrivateReview['unsupported_filter_count'] ?? null) !== 1) {
    throw new RuntimeException('Expected named Crypt CMap filter to be counted as unsupported.');
}

if (($cryptPrivateEntry['filter_operand_policy'] ?? null) !== 'reject_unsupported_filter_names') {
    throw new RuntimeException('Expected named Crypt CMap filter operand review metadata.');
}

if (($cryptPrivateEntry['decodeparms_operands'][0]['value'] ?? null) !== '<< /Name /PrivateCF >>') {
    throw new RuntimeException('Expected named Crypt DecodeParms to remain review-visible.');
}

$lines = array_merge(
    $dictionaryLines,
    $literalLines,
    $indirectLiteralLines,
    $indirectArrayDictionaryLines,
    $generationLines,
    $decodeParmsLines,
    $trailingDecodeParmsLines,
    $nullFilterDecodeParmsLines,
    $indirectNullFilterDecodeParmsLines,
    $staleReferenceLines,
    $postEndLines,
    $secondProgramLines,
    $cryptIdentityLines,
    $cryptPrivateLines,
    $unsupportedFilterLines
);

echo '<!-- markerpdf-malformed-cmap-filter-boundary-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'malformed, unsupported, and identity Crypt ToUnicode CMap Filter operands, direct and indirect null-filter DecodeParms slots, and post-endcmap decoded operators stay bounded before WordPress text import',
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
    'null_filter_decodeparms_decoded_cmap_count' => $nullFilterDecodeParmsReview['decoded_cmap_count'] ?? null,
    'null_filter_decodeparms_invalid_operand_count' => $nullFilterDecodeParmsReview['invalid_decodeparms_operand_count'] ?? null,
    'null_filter_decodeparms_invalid_parameter_count' => $nullFilterDecodeParmsReview['invalid_decodeparms_parameter_count'] ?? null,
    'null_filter_decodeparms_operand_policy' => $nullFilterDecodeParmsEntry['decodeparms_operand_policy'] ?? null,
    'null_filter_decodeparms_owner_policy' => $nullFilterDecodeParmsEntry['owner_policy'] ?? null,
    'null_filter_decodeparms_ignored_reference' => $nullFilterDecodeParmsEntry['decodeparms_operands'][0]['object_number'] ?? null,
    'null_filter_decodeparms_real_parameter' => $nullFilterDecodeParmsEntry['decodeparms_operands'][1]['value'] ?? null,
    'null_filter_decodeparms_slot_ignored' => ($nullFilterDecodeParmsReview['decoded_cmap_count'] ?? null) === 1
        && (($nullFilterDecodeParmsReview['invalid_decodeparms_operand_count'] ?? null) === 0)
        && (($nullFilterDecodeParmsReview['invalid_decodeparms_parameter_count'] ?? null) === 0)
        && (($nullFilterDecodeParmsEntry['decodeparms_operands'][0]['resolved'] ?? null) === false),
    'indirect_null_filter_decodeparms_decoded_cmap_count' => $indirectNullFilterDecodeParmsReview['decoded_cmap_count'] ?? null,
    'indirect_null_filter_decodeparms_invalid_operand_count' => $indirectNullFilterDecodeParmsReview['invalid_decodeparms_operand_count'] ?? null,
    'indirect_null_filter_decodeparms_invalid_parameter_count' => $indirectNullFilterDecodeParmsReview['invalid_decodeparms_parameter_count'] ?? null,
    'indirect_null_filter_decodeparms_operand_policy' => $indirectNullFilterDecodeParmsEntry['decodeparms_operand_policy'] ?? null,
    'indirect_null_filter_decodeparms_owner_policy' => $indirectNullFilterDecodeParmsEntry['owner_policy'] ?? null,
    'indirect_null_filter_decodeparms_array_object' => $indirectNullFilterDecodeParmsEntry['decodeparms_operands'][0]['object_number'] ?? null,
    'indirect_null_filter_decodeparms_array_preview' => $indirectNullFilterDecodeParmsEntry['decodeparms_operands'][0]['value_preview'] ?? null,
    'indirect_null_filter_decodeparms_slot_ignored' => ($indirectNullFilterDecodeParmsReview['decoded_cmap_count'] ?? null) === 1
        && (($indirectNullFilterDecodeParmsReview['invalid_decodeparms_operand_count'] ?? null) === 0)
        && (($indirectNullFilterDecodeParmsReview['invalid_decodeparms_parameter_count'] ?? null) === 0)
        && (($indirectNullFilterDecodeParmsEntry['decodeparms_operands'][0]['token_type'] ?? null) === 'array'),
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
    'post_endcmap_decoded_cmap_count' => $postEndReview['decoded_cmap_count'] ?? null,
    'post_endcmap_cmap_name' => $postEndEntry['cmap_name'] ?? null,
    'post_endcmap_bounded_cmap_length' => $postEndEntry['bounded_cmap_length'] ?? null,
    'post_endcmap_decoded_cmap_length' => $postEndEntry['decoded_cmap_length'] ?? null,
    'post_endcmap_byte_count' => $postEndEntry['post_endcmap_byte_count'] ?? null,
    'post_endcmap_bytes_excluded' => ($postEndEntry['post_endcmap_bytes_excluded'] ?? null) === true,
    'post_endcmap_operator_payload_excluded' => !str_contains($postEndPlainText, 'PostEnd CMap Leak')
        && !str_contains($postEndPlainText, 'WPPostEndCMapDecoy-H'),
    'second_program_decoded_cmap_count' => $secondProgramReview['decoded_cmap_count'] ?? null,
    'second_program_cmap_name' => $secondProgramEntry['cmap_name'] ?? null,
    'second_program_bounded_cmap_length' => $secondProgramEntry['bounded_cmap_length'] ?? null,
    'second_program_decoded_cmap_length' => $secondProgramEntry['decoded_cmap_length'] ?? null,
    'second_program_post_endcmap_byte_count' => $secondProgramEntry['post_endcmap_byte_count'] ?? null,
    'second_program_parser_bounded_cmap_length' => $secondProgramEntry['parser_bounded_cmap_length'] ?? null,
    'second_program_parser_excluded_byte_count' => $secondProgramEntry['parser_excluded_cmap_byte_count'] ?? null,
    'second_program_trailing_cleanup_bytes_excluded' => ($secondProgramEntry['post_endcmap_bytes_excluded'] ?? null) === true,
    'second_program_parser_bytes_excluded' => ($secondProgramEntry['parser_bounded_cmap_bytes_excluded'] ?? null) === true,
    'second_program_payload_excluded' => !str_contains($secondProgramPlainText, 'Second Program CMap Leak')
        && !str_contains($secondProgramPlainText, 'WPSecondProgramDecoy-H'),
    'unsupported_filter_decoded_cmap_count' => $unsupportedFilterReview['decoded_cmap_count'] ?? null,
    'unsupported_filter_count' => $unsupportedFilterReview['unsupported_filter_count'] ?? null,
    'unsupported_filter_names' => $unsupportedFilterEntry['filters'] ?? [],
    'unsupported_filter_operand_policy' => $unsupportedFilterEntry['filter_operand_policy'] ?? null,
    'unsupported_filter_payload_excluded' => !str_contains($unsupportedFilterPlainText, 'Unsupported Filter CMap Leak')
        && !str_contains($unsupportedFilterPlainText, 'WPUnsupportedFilterBoundary-H')
        && !str_contains($unsupportedFilterPlainText, 'DCTDecode'),
    'crypt_identity_decoded_cmap_count' => $cryptIdentityReview['decoded_cmap_count'] ?? null,
    'crypt_identity_unsupported_filter_count' => $cryptIdentityReview['unsupported_filter_count'] ?? null,
    'crypt_identity_filter_operand_policy' => $cryptIdentityEntry['filter_operand_policy'] ?? null,
    'crypt_identity_decodeparms' => $cryptIdentityEntry['decodeparms_operands'][0]['value'] ?? null,
    'crypt_identity_filter_supported' => ($cryptIdentityReview['decoded_cmap_count'] ?? null) === 1
        && (($cryptIdentityReview['unsupported_filter_count'] ?? null) === 0)
        && (($cryptIdentityEntry['filter_operand_policy'] ?? null) === 'filters_resolved'),
    'crypt_identity_payload_excluded' => !str_contains($cryptIdentityPlainText, 'WPCryptIdentityBoundary-H')
        && !str_contains($cryptIdentityPlainText, '/Name'),
    'crypt_private_decoded_cmap_count' => $cryptPrivateReview['decoded_cmap_count'] ?? null,
    'crypt_private_unsupported_filter_count' => $cryptPrivateReview['unsupported_filter_count'] ?? null,
    'crypt_private_filter_operand_policy' => $cryptPrivateEntry['filter_operand_policy'] ?? null,
    'crypt_private_decodeparms' => $cryptPrivateEntry['decodeparms_operands'][0]['value'] ?? null,
    'crypt_private_filter_rejected' => ($cryptPrivateReview['decoded_cmap_count'] ?? null) === 0
        && (($cryptPrivateReview['unsupported_filter_count'] ?? null) === 1)
        && (($cryptPrivateEntry['filter_operand_policy'] ?? null) === 'reject_unsupported_filter_names'),
    'crypt_private_payload_excluded' => !str_contains($cryptPrivatePlainText, 'Private Crypt CMap Leak')
        && !str_contains($cryptPrivatePlainText, 'WPCryptPrivateBoundary-H')
        && !str_contains($cryptPrivatePlainText, 'PrivateCF'),
    'leaking_cmap_text_excluded' => !str_contains($dictionaryPlainText, 'Dictionary Filter Leak')
        && !str_contains($literalPlainText, 'Literal Filter Leak')
        && !str_contains($indirectLiteralPlainText, 'Indirect Literal Filter Leak')
        && !str_contains($indirectArrayDictionaryPlainText, 'Indirect Array Dictionary Leak')
        && !str_contains($generationPlainText, 'Stale Generation CMap Leak')
        && !str_contains($decodeParmsPlainText, 'DecodeParms CMap Leak')
        && !str_contains($trailingDecodeParmsPlainText, 'Trailing DecodeParms CMap Leak')
        && !str_contains($nullFilterDecodeParmsPlainText, '99 0 R')
        && !str_contains($nullFilterDecodeParmsPlainText, 'Predictor')
        && !str_contains($indirectNullFilterDecodeParmsPlainText, '99 0 R')
        && !str_contains($indirectNullFilterDecodeParmsPlainText, 'Predictor')
        && !str_contains($staleReferencePlainText, 'Stale Reference CMap Leak')
        && !str_contains($postEndPlainText, 'PostEnd CMap Leak')
        && !str_contains($secondProgramPlainText, 'Second Program CMap Leak')
        && !str_contains($cryptIdentityPlainText, 'WPCryptIdentityBoundary-H')
        && !str_contains($cryptPrivatePlainText, 'Private Crypt CMap Leak')
        && !str_contains($unsupportedFilterPlainText, 'Unsupported Filter CMap Leak'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
