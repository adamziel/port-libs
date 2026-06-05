<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserMalformedCMapFilterBoundaryCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $leakingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /MalformedFilterBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "2 beginbfchar\n"
        . "<01> <" . $utf16beHex('Decoded CMap Leak') . ">\n"
        . "<02> <" . $utf16beHex('Dictionary Filter Leak') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($leakingCMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress focused malformed CMap filter fixture.');
    }

    $safeText = 'Safe Import';
    $safeHex = '';
    for ($index = 0, $length = strlen($safeText); $index < $length; $index++) {
        $safeHex .= sprintf('%04X', ord($safeText[$index]));
    }
    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MalformedFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /MalformedFilterBoundary-H /Filter [ << /Owner (Filter dictionary is not a decoder) /Fake [ /Nested ] >> /FlateDecode ] /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$parserMalformedCMapLiteralFilterBoundaryCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $leakingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /LiteralFilterBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<01> <" . $utf16beHex('Literal Filter Leak') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($leakingCMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress focused literal-filter CMap fixture.');
    }

    $safeText = 'Literal Safe Import';
    $safeHex = '';
    for ($index = 0, $length = strlen($safeText); $index < $length; $index++) {
        $safeHex .= sprintf('%04X', ord($safeText[$index]));
    }
    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LiteralFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /LiteralFilterBoundary-H /Filter [ (literal filter is not a decoder) /FlateDecode ] /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$parserMalformedCMapIndirectLiteralFilterBoundaryCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $leakingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /IndirectLiteralFilterBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<01> <" . $utf16beHex('Indirect Literal Filter Leak') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($leakingCMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress focused indirect-literal-filter CMap fixture.');
    }

    $safeText = 'Indirect Literal Safe Import';
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
    $addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /IndirectLiteralFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /CMap /CMapName /IndirectLiteralFilterBoundary-H /Filter [ 7 0 R /FlateDecode ] /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
    $addObject(7, 0, '(indirect literal filter is not a decoder)');

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 8\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 7; $objectNumber++) {
        $pdf .= $xrefRow($offsets[$objectNumber] ?? null);
    }
    $pdf .= "trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$parserMalformedCMapIndirectArrayDictionaryFilterBoundaryCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $leakingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /IndirectArrayDictionaryFilterBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<01> <" . $utf16beHex('Indirect Array Dictionary Leak') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($leakingCMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress focused indirect-array dictionary CMap fixture.');
    }

    $safeText = 'Indirect Array Safe Import';
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
    $addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /IndirectArrayDictionaryFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /CMap /CMapName /IndirectArrayDictionaryFilterBoundary-H /Filter 7 0 R /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
    $addObject(7, 0, '[ << /Owner (indirect array dictionary is not a decoder) /Fake [ /Nested ] >> /FlateDecode ]');

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 8\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 7; $objectNumber++) {
        $pdf .= $xrefRow($offsets[$objectNumber] ?? null);
    }
    $pdf .= "trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$parserMalformedCMapGenerationFilterBoundaryCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $safeText = 'Generation Safe Import';
    $safeHex = $utf16beHex($safeText);
    $leakingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /GenerationFilterBoundary-H def\n"
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
        throw new RuntimeException('Unable to compress focused generation-filter CMap fixture.');
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
    $addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /GenerationFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /CMap /CMapName /GenerationFilterBoundary-H /Filter 7 1 R /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
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

$parserMalformedCMapDecodeParmsBoundaryCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $safeText = 'DecodeParms Safe Import';
    $safeHex = $utf16beHex($safeText);
    $leakingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /DecodeParmsBoundary-H def\n"
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
        throw new RuntimeException('Unable to compress focused DecodeParms CMap fixture.');
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
    $addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /DecodeParmsBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /CMap /CMapName /DecodeParmsBoundary-H /Filter /FlateDecode /DecodeParms 8 1 R /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
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

$parserMalformedCMapTrailingDecodeParmsBoundaryCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $safeText = 'Trailing DecodeParms Safe Import';
    $safeHex = $utf16beHex($safeText);
    $leakingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /TrailingDecodeParmsBoundary-H def\n"
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
        throw new RuntimeException('Unable to compress focused trailing DecodeParms CMap fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /TrailingDecodeParmsBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /TrailingDecodeParmsBoundary-H /Filter /FlateDecode /DecodeParms [ null << /Predictor /Twelve /Columns 1 >> ] /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$parserMalformedCMapNullFilterDecodeParmsBoundaryCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $mappedText = 'Null Slot CMap Import';
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /NullFilterDecodeParmsBoundary-H def\n"
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
        throw new RuntimeException('Unable to compress focused null-filter DecodeParms CMap fixture.');
    }

    $content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NullFilterDecodeParmsBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /NullFilterDecodeParmsBoundary-H /Filter [ null /FlateDecode ] /DecodeParms [ 99 0 R << /Predictor 1 >> ] /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$parserMalformedCMapIndirectNullFilterDecodeParmsBoundaryCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $mappedText = 'Indirect Null Slot CMap Import';
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /IndirectNullFilterDecodeParmsBoundary-H def\n"
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
        throw new RuntimeException('Unable to compress focused indirect null-filter DecodeParms CMap fixture.');
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
    $addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /IndirectNullFilterDecodeParmsBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /CMap /CMapName /IndirectNullFilterDecodeParmsBoundary-H /Filter [ null /FlateDecode ] /DecodeParms 8 0 R /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
    $addObject(8, 0, '[ 99 0 R << /Predictor 1 >> ]');

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 9\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 8; $objectNumber++) {
        $pdf .= $xrefRow($offsets[$objectNumber] ?? null);
    }
    $pdf .= "trailer\n<< /Size 9 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$parserMalformedUseCMapDecodeParmsBoundaryCurrentBasePdf = static function (): string {
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
        . "/CMapName /DerivedUseCMapBoundary-H def\n"
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
        . "/CMapName /InheritedUseCMapMalformedDecodeParms-H def\n"
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
        throw new RuntimeException('Unable to compress focused UseCMap DecodeParms fixture.');
    }

    $content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /UseCMapDecodeParmsBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /UseCMap 7 0 R /Length " . strlen($derivedCMap) . " >>\nstream\n{$derivedCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor /Twelve /Columns 1 >> /Length " . strlen($compressedInheritedCMap) . " >>\nstream\n{$compressedInheritedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$parserMalformedCMapStaleReferenceFilterBoundaryCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $safeText = 'Stale Reference Safe Import';
    $safeHex = $utf16beHex($safeText);
    $leakingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /StaleReferenceFilterBoundary-H def\n"
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
        throw new RuntimeException('Unable to compress focused stale-reference CMap fixture.');
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
    $addObject(4, 0, '<< /Type /Font /Subtype /Type0 /BaseFont /StaleReferenceFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /CMap /CMapName /StaleReferenceFilterBoundary-H /Filter 7 0 R /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream");
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

$parserMalformedCMapNestedArrayDictionaryFilterBoundaryCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $safeText = 'Nested Array Safe Import';
    $safeHex = $utf16beHex($safeText);
    $leakingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /NestedArrayFilterBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<" . substr($safeHex, 0, 4) . "> <" . $utf16beHex('Nested Array CMap Leak') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($leakingCMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress focused nested-array CMap fixture.');
    }

    $content = "BT /Fcid 12 Tf 72 720 Td <{$safeHex}> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NestedArrayFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /NestedArrayFilterBoundary-H /Filter [ [ [ << /Owner (nested dictionary is not a decoder) /Fake /Nested >> ] ] /FlateDecode ] /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$parserMalformedCMapPostEndOperatorBoundaryCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /PostEndCMapBoundary-H def\n"
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
        . "/CMapName /PostEndCMapDecoy-H def\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress focused post-endcmap CMap fixture.');
    }

    $content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PostEndCMapBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /PostEndCMapBoundary-H /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$parserMalformedCMapSecondProgramBoundaryCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /SecondProgramBoundary-H def\n"
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
        . "/CMapName /SecondProgramDecoy-H def\n"
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
        throw new RuntimeException('Unable to compress focused second-program CMap fixture.');
    }

    $content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /SecondProgramBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /SecondProgramBoundary-H /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$parserMalformedCMapLiteralOperatorBoundaryCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /LiteralOperatorBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0001> <0001>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<0001> <" . $utf16beHex('Literal Operator Safe Import') . ">\n"
        . "endbfchar\n"
        . "(1 beginbfchar\n"
        . "<0001> <" . $utf16beHex('Literal Operator CMap Leak') . ">\n"
        . "endbfchar)\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedCMap = gzcompress($cMap, 0);
    if (!is_string($compressedCMap)) {
        throw new RuntimeException('Unable to compress focused literal-operator CMap fixture.');
    }

    $content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LiteralOperatorBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /LiteralOperatorBoundary-H /Filter /FlateDecode /Length " . strlen($compressedCMap) . " >>\nstream\n{$compressedCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$parserMalformedCMapLiteralNameUseCMapBoundaryCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $baseCMap = "(/CMapName /FakeBase-H def)\n"
        . "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /RealBase-H def\n"
        . "1 begincodespacerange\n"
        . "<0001> <0001>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<0001> <" . $utf16beHex('Literal Name Safe Import') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedBaseCMap = gzcompress($baseCMap, 0);
    if (!is_string($compressedBaseCMap)) {
        throw new RuntimeException('Unable to compress focused literal-name CMap fixture.');
    }

    $derivedCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /DerivedLiteralName-H def\n"
        . "1 begincodespacerange\n"
        . "<0001> <0001>\n"
        . "endcodespacerange\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";

    $content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DerivedLiteralName /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /UseCMap /RealBase-H /Length " . strlen($derivedCMap) . " >>\nstream\n{$derivedCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /CMap /Filter /FlateDecode /Length " . strlen($compressedBaseCMap) . " >>\nstream\n{$compressedBaseCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$parserMalformedCMapLiteralUseCMapOperatorBoundaryCurrentBasePdf = static function (): string {
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
        . "/CMapName /LiteralUseCMapRealBase-H def\n"
        . "1 begincodespacerange\n"
        . "<0001> <0001>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<0001> <" . $utf16beHex('Literal UseCMap Safe Import') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedRealBaseCMap = gzcompress($realBaseCMap, 0);
    if (!is_string($compressedRealBaseCMap)) {
        throw new RuntimeException('Unable to compress focused real base literal-usecmap CMap fixture.');
    }

    $decoyBaseCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /LiteralUseCMapDecoyBase-H def\n"
        . "1 begincodespacerange\n"
        . "<0001> <0001>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<0001> <" . $utf16beHex('Literal UseCMap Leak') . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedDecoyBaseCMap = gzcompress($decoyBaseCMap, 0);
    if (!is_string($compressedDecoyBaseCMap)) {
        throw new RuntimeException('Unable to compress focused decoy base literal-usecmap CMap fixture.');
    }

    $derivedCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /LiteralUseCMapDerived-H def\n"
        . "/LiteralUseCMapRealBase-H usecmap\n"
        . "(/LiteralUseCMapDecoyBase-H usecmap)\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $compressedDerivedCMap = gzcompress($derivedCMap, 0);
    if (!is_string($compressedDerivedCMap)) {
        throw new RuntimeException('Unable to compress focused derived literal-usecmap CMap fixture.');
    }

    $content = 'BT /Fcid 12 Tf 72 720 Td <0001> Tj ET';

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LiteralUseCMapDerived /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /LiteralUseCMapDerived-H /Filter /FlateDecode /Length " . strlen($compressedDerivedCMap) . " >>\nstream\n{$compressedDerivedCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /CMap /CMapName /LiteralUseCMapRealBase-H /Filter /FlateDecode /Length " . strlen($compressedRealBaseCMap) . " >>\nstream\n{$compressedRealBaseCMap}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /CMap /CMapName /LiteralUseCMapDecoyBase-H /Filter /FlateDecode /Length " . strlen($compressedDecoyBaseCMap) . " >>\nstream\n{$compressedDecoyBaseCMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$parserMalformedCMapUnsupportedFilterBoundaryCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /UnsupportedFilterBoundary-H def\n"
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
    $contentText = 'Unsupported Filter Safe Import';
    $content = "BT /Fcid 12 Tf 72 720 Td <" . $utf16beHex($contentText) . "> Tj ET";

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /Fcid 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /UnsupportedFilterBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /UnsupportedFilterBoundary-H /Filter /DCTDecode /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$parserMalformedCMapCryptIdentityFilterBoundaryCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $mappedText = 'Identity Crypt CMap Import';
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /CryptIdentityBoundary-H def\n"
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
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CryptIdentityBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /CryptIdentityBoundary-H /Filter /Crypt /DecodeParms << /Name /Identity >> /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
        . "%%EOF";
};

$parserMalformedCMapCryptPrivateFilterBoundaryCurrentBasePdf = static function (): string {
    $utf16beHex = static function (string $ascii): string {
        $hex = '';
        for ($index = 0, $length = strlen($ascii); $index < $length; $index++) {
            $hex .= sprintf('%04X', ord($ascii[$index]));
        }

        return $hex;
    };

    $safeText = 'Private Crypt Safe Import';
    $leakingText = 'Private Crypt CMap Leak';
    $safeHex = $utf16beHex($safeText);
    $cMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /CryptPrivateBoundary-H def\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<" . substr($safeHex, 0, 4) . "> <" . $utf16beHex($leakingText) . ">\n"
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
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CryptPrivateBoundary /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /CMap /CMapName /CryptPrivateBoundary-H /Filter /Crypt /DecodeParms << /Name /PrivateCF >> /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'fails closed on malformed CMap Filter array operands before current-base text extraction' => static function (TestRunner $t) use ($parserMalformedCMapFilterBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapFilterBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $filterOperands = $entry['filter_operands'] ?? [];

        $t->same(['Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('Safe Import', $text);
        $t->same("Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Decoded CMap Leak'));
        $t->true(!str_contains($text, 'Dictionary Filter Leak'));
        $t->true(!str_contains($text, 'Filter dictionary is not a decoder'));
        $t->true(!str_contains($text, 'MalformedFilterBoundary-H'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(0, $review['decoded_cmap_count']);
        $t->same(1, $review['invalid_filter_operand_count']);
        $t->same(1, $review['dictionary_filter_operand_count']);
        $t->same(6, $entry['object_number'] ?? null);
        $t->same(0, $entry['generation'] ?? null);
        $t->same('MalformedFilterBoundary-H', $entry['cmap_name'] ?? null);
        $t->same([], $entry['filters'] ?? null);
        $t->same(true, $entry['filter_resolution_failed'] ?? null);
        $t->same(false, $entry['decodeparms_resolution_failed'] ?? null);
        $t->same(1, $entry['invalid_filter_operand_count'] ?? null);
        $t->same(1, $entry['dictionary_filter_operand_count'] ?? null);
        $t->same('reject_dictionary_filter_operands', $entry['filter_operand_policy'] ?? null);
        $t->same(null, $entry['decoded_cmap_length'] ?? null);
        $t->same(null, $entry['decoded_cmap_sha256'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
        $t->same('direct_operands', $entry['owner_policy'] ?? null);
        $t->same('to_unicode', $entry['reference_usages'][0]['usage'] ?? null);
        $t->same('direct', $filterOperands[0]['kind'] ?? null);
        $t->same('<< /Owner (Filter dictionary is not a decoder) /Fake [ /Nested ] >>', $filterOperands[0]['value'] ?? null);
        $t->same('direct', $filterOperands[1]['kind'] ?? null);
        $t->same('FlateDecode', $filterOperands[1]['value'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'classifies literal CMap Filter operands as malformed before current-base text extraction' => static function (TestRunner $t) use ($parserMalformedCMapLiteralFilterBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapLiteralFilterBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $filterOperands = $entry['filter_operands'] ?? [];

        $t->same(['Literal Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['Literal Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('Literal Safe Import', $text);
        $t->same("Literal Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Literal Filter Leak'));
        $t->true(!str_contains($text, 'literal filter is not a decoder'));
        $t->true(!str_contains($text, 'LiteralFilterBoundary-H'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(0, $review['decoded_cmap_count']);
        $t->same(1, $review['invalid_filter_operand_count']);
        $t->same(0, $review['dictionary_filter_operand_count']);
        $t->same(1, $review['malformed_filter_operand_count']);
        $t->same(6, $entry['object_number'] ?? null);
        $t->same('LiteralFilterBoundary-H', $entry['cmap_name'] ?? null);
        $t->same([], $entry['filters'] ?? null);
        $t->same(true, $entry['filter_resolution_failed'] ?? null);
        $t->same(1, $entry['invalid_filter_operand_count'] ?? null);
        $t->same(0, $entry['dictionary_filter_operand_count'] ?? null);
        $t->same(1, $entry['malformed_filter_operand_count'] ?? null);
        $t->same('reject_malformed_filter_operands', $entry['filter_operand_policy'] ?? null);
        $t->same(null, $entry['decoded_cmap_length'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
        $t->same('direct_operands', $entry['owner_policy'] ?? null);
        $t->same('direct', $filterOperands[0]['kind'] ?? null);
        $t->same('literal', $filterOperands[0]['token_type'] ?? null);
        $t->same('(literal filter is not a decoder)', $filterOperands[0]['value'] ?? null);
        $t->same(false, $filterOperands[0]['valid_filter_operand'] ?? null);
        $t->same('direct', $filterOperands[1]['kind'] ?? null);
        $t->same('name', $filterOperands[1]['token_type'] ?? null);
        $t->same('FlateDecode', $filterOperands[1]['value'] ?? null);
        $t->same(true, $filterOperands[1]['valid_filter_operand'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'classifies selected indirect literal CMap Filter operands as malformed before current-base text extraction' => static function (TestRunner $t) use ($parserMalformedCMapIndirectLiteralFilterBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapIndirectLiteralFilterBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $filterOperands = $entry['filter_operands'] ?? [];

        $t->same(['Indirect Literal Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['Indirect Literal Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('Indirect Literal Safe Import', $text);
        $t->same("Indirect Literal Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Indirect Literal Filter Leak'));
        $t->true(!str_contains($text, 'indirect literal filter is not a decoder'));
        $t->true(!str_contains($text, 'IndirectLiteralFilterBoundary-H'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(1, $review['indirect_filter_count']);
        $t->same(1, $review['xref_selected_operand_count']);
        $t->same(0, $review['unresolved_operand_count']);
        $t->same(0, $review['decoded_cmap_count']);
        $t->same(1, $review['invalid_filter_operand_count']);
        $t->same(0, $review['dictionary_filter_operand_count']);
        $t->same(1, $review['malformed_filter_operand_count']);
        $t->same(6, $entry['object_number'] ?? null);
        $t->same('IndirectLiteralFilterBoundary-H', $entry['cmap_name'] ?? null);
        $t->same([], $entry['filters'] ?? null);
        $t->same(true, $entry['filter_resolution_failed'] ?? null);
        $t->same(1, $entry['indirect_filter_count'] ?? null);
        $t->same(1, $entry['xref_selected_operand_count'] ?? null);
        $t->same(0, $entry['unresolved_operand_count'] ?? null);
        $t->same(1, $entry['invalid_filter_operand_count'] ?? null);
        $t->same(0, $entry['dictionary_filter_operand_count'] ?? null);
        $t->same(1, $entry['malformed_filter_operand_count'] ?? null);
        $t->same('reject_malformed_filter_operands', $entry['filter_operand_policy'] ?? null);
        $t->same(null, $entry['decoded_cmap_length'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
        $t->same('xref_selected_indirect_operands', $entry['owner_policy'] ?? null);
        $t->same('indirect', $filterOperands[0]['kind'] ?? null);
        $t->same(7, $filterOperands[0]['object_number'] ?? null);
        $t->same(0, $filterOperands[0]['generation'] ?? null);
        $t->same(true, $filterOperands[0]['resolved'] ?? null);
        $t->same(true, $filterOperands[0]['xref_selected'] ?? null);
        $t->same('xref_selected_direct_object', $filterOperands[0]['owner_policy'] ?? null);
        $t->same('literal', $filterOperands[0]['token_type'] ?? null);
        $t->same(false, $filterOperands[0]['valid_filter_operand'] ?? null);
        $t->same('(indirect literal filter is not a decoder)', $filterOperands[0]['value_preview'] ?? null);
        $t->same('direct', $filterOperands[1]['kind'] ?? null);
        $t->same('name', $filterOperands[1]['token_type'] ?? null);
        $t->same('FlateDecode', $filterOperands[1]['value'] ?? null);
        $t->same(true, $filterOperands[1]['valid_filter_operand'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'classifies selected indirect CMap Filter arrays with dictionary operands before current-base text extraction' => static function (TestRunner $t) use ($parserMalformedCMapIndirectArrayDictionaryFilterBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapIndirectArrayDictionaryFilterBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $filterOperands = $entry['filter_operands'] ?? [];

        $t->same(['Indirect Array Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['Indirect Array Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('Indirect Array Safe Import', $text);
        $t->same("Indirect Array Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Indirect Array Dictionary Leak'));
        $t->true(!str_contains($text, 'indirect array dictionary is not a decoder'));
        $t->true(!str_contains($text, 'IndirectArrayDictionaryFilterBoundary-H'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(1, $review['indirect_filter_count']);
        $t->same(1, $review['xref_selected_operand_count']);
        $t->same(0, $review['unresolved_operand_count']);
        $t->same(0, $review['decoded_cmap_count']);
        $t->same(1, $review['invalid_filter_operand_count']);
        $t->same(1, $review['dictionary_filter_operand_count']);
        $t->same(0, $review['malformed_filter_operand_count']);
        $t->same(6, $entry['object_number'] ?? null);
        $t->same('IndirectArrayDictionaryFilterBoundary-H', $entry['cmap_name'] ?? null);
        $t->same([], $entry['filters'] ?? null);
        $t->same(true, $entry['filter_resolution_failed'] ?? null);
        $t->same(1, $entry['indirect_filter_count'] ?? null);
        $t->same(1, $entry['xref_selected_operand_count'] ?? null);
        $t->same(0, $entry['unresolved_operand_count'] ?? null);
        $t->same(1, $entry['invalid_filter_operand_count'] ?? null);
        $t->same(1, $entry['dictionary_filter_operand_count'] ?? null);
        $t->same(0, $entry['malformed_filter_operand_count'] ?? null);
        $t->same('reject_dictionary_filter_operands', $entry['filter_operand_policy'] ?? null);
        $t->same(null, $entry['decoded_cmap_length'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
        $t->same('xref_selected_indirect_operands', $entry['owner_policy'] ?? null);
        $t->same('indirect', $filterOperands[0]['kind'] ?? null);
        $t->same(7, $filterOperands[0]['object_number'] ?? null);
        $t->same(0, $filterOperands[0]['generation'] ?? null);
        $t->same(true, $filterOperands[0]['resolved'] ?? null);
        $t->same(true, $filterOperands[0]['xref_selected'] ?? null);
        $t->same('xref_selected_direct_object', $filterOperands[0]['owner_policy'] ?? null);
        $t->same('array', $filterOperands[0]['token_type'] ?? null);
        $t->same(true, $filterOperands[0]['dictionary_filter_operand'] ?? null);
        $t->same(false, $filterOperands[0]['valid_filter_operand'] ?? null);
        $t->same('[ << /Owner (indirect array dictionary is not a decoder) /Fake [ /Nested ] >>...', $filterOperands[0]['value_preview'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'rejects current-generation indirect CMap Filter dictionaries instead of stale valid filters' => static function (TestRunner $t) use ($parserMalformedCMapGenerationFilterBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapGenerationFilterBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $filterOperand = $entry['filter_operands'][0] ?? [];

        $t->same(['Generation Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['Generation Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('Generation Safe Import', $text);
        $t->same("Generation Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale Generation CMap Leak'));
        $t->true(!str_contains($text, 'current generation dictionary is not a decoder'));
        $t->true(!str_contains($text, 'GenerationFilterBoundary-H'));
        $t->true(!str_contains($text, 'FlateDecode'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(1, $review['indirect_filter_count']);
        $t->same(1, $review['xref_selected_operand_count']);
        $t->same(0, $review['unresolved_operand_count']);
        $t->same(0, $review['decoded_cmap_count']);
        $t->same(1, $review['invalid_filter_operand_count']);
        $t->same(1, $review['dictionary_filter_operand_count']);
        $t->same(0, $review['malformed_filter_operand_count']);
        $t->same(6, $entry['object_number'] ?? null);
        $t->same(0, $entry['generation'] ?? null);
        $t->same('GenerationFilterBoundary-H', $entry['cmap_name'] ?? null);
        $t->same([], $entry['filters'] ?? null);
        $t->same(true, $entry['filter_resolution_failed'] ?? null);
        $t->same(1, $entry['indirect_filter_count'] ?? null);
        $t->same(1, $entry['xref_selected_operand_count'] ?? null);
        $t->same(0, $entry['unresolved_operand_count'] ?? null);
        $t->same(1, $entry['invalid_filter_operand_count'] ?? null);
        $t->same(1, $entry['dictionary_filter_operand_count'] ?? null);
        $t->same(0, $entry['malformed_filter_operand_count'] ?? null);
        $t->same('reject_dictionary_filter_operands', $entry['filter_operand_policy'] ?? null);
        $t->same(null, $entry['decoded_cmap_length'] ?? null);
        $t->same(null, $entry['decoded_cmap_sha256'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
        $t->same('xref_selected_indirect_operands', $entry['owner_policy'] ?? null);
        $t->same('indirect', $filterOperand['kind'] ?? null);
        $t->same(7, $filterOperand['object_number'] ?? null);
        $t->same(1, $filterOperand['generation'] ?? null);
        $t->same(true, $filterOperand['resolved'] ?? null);
        $t->same(true, $filterOperand['xref_selected'] ?? null);
        $t->same(1, $filterOperand['xref_entry_type'] ?? null);
        $t->same($filterOperand['definition_offset'] ?? null, $filterOperand['selected_offset'] ?? null);
        $t->same('xref_selected_direct_object', $filterOperand['owner_policy'] ?? null);
        $t->same('dictionary', $filterOperand['token_type'] ?? null);
        $t->same(true, $filterOperand['dictionary_filter_operand'] ?? null);
        $t->same(false, $filterOperand['valid_filter_operand'] ?? null);
        $t->true(str_starts_with((string) ($filterOperand['value_preview'] ?? ''), '<< /Owner (current generation dictionary is not a decoder)'));
        $t->true(str_ends_with((string) ($filterOperand['value_preview'] ?? ''), '...'));
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'rejects current-generation malformed CMap DecodeParms parameters before ToUnicode decoding' => static function (TestRunner $t) use ($parserMalformedCMapDecodeParmsBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapDecodeParmsBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $decodeParmsOperand = $entry['decodeparms_operands'][0] ?? [];

        $t->same(['DecodeParms Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['DecodeParms Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('DecodeParms Safe Import', $text);
        $t->same("DecodeParms Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'DecodeParms CMap Leak'));
        $t->true(!str_contains($text, 'Twelve'));
        $t->true(!str_contains($text, 'DecodeParmsBoundary-H'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(0, $review['indirect_filter_count']);
        $t->same(1, $review['xref_selected_operand_count']);
        $t->same(0, $review['unresolved_operand_count']);
        $t->same(0, $review['decoded_cmap_count']);
        $t->same(0, $review['invalid_filter_operand_count']);
        $t->same(0, $review['dictionary_filter_operand_count']);
        $t->same(0, $review['malformed_filter_operand_count']);
        $t->same(0, $review['invalid_decodeparms_operand_count']);
        $t->same(0, $review['malformed_decodeparms_operand_count']);
        $t->same(1, $review['invalid_decodeparms_parameter_count']);
        $t->same(6, $entry['object_number'] ?? null);
        $t->same(0, $entry['generation'] ?? null);
        $t->same('DecodeParmsBoundary-H', $entry['cmap_name'] ?? null);
        $t->same(['FlateDecode'], $entry['filters'] ?? null);
        $t->same(false, $entry['filter_resolution_failed'] ?? null);
        $t->same(false, $entry['decodeparms_resolution_failed'] ?? null);
        $t->same(0, $entry['invalid_filter_operand_count'] ?? null);
        $t->same(0, $entry['invalid_decodeparms_operand_count'] ?? null);
        $t->same(0, $entry['malformed_decodeparms_operand_count'] ?? null);
        $t->same(1, $entry['invalid_decodeparms_parameter_count'] ?? null);
        $t->same('filters_resolved', $entry['filter_operand_policy'] ?? null);
        $t->same('reject_malformed_decodeparms_parameters', $entry['decodeparms_operand_policy'] ?? null);
        $t->same(null, $entry['decoded_cmap_length'] ?? null);
        $t->same(null, $entry['decoded_cmap_sha256'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
        $t->same('xref_selected_indirect_operands', $entry['owner_policy'] ?? null);
        $t->same('indirect', $decodeParmsOperand['kind'] ?? null);
        $t->same(8, $decodeParmsOperand['object_number'] ?? null);
        $t->same(1, $decodeParmsOperand['generation'] ?? null);
        $t->same(true, $decodeParmsOperand['resolved'] ?? null);
        $t->same(true, $decodeParmsOperand['xref_selected'] ?? null);
        $t->same(1, $decodeParmsOperand['xref_entry_type'] ?? null);
        $t->same($decodeParmsOperand['definition_offset'] ?? null, $decodeParmsOperand['selected_offset'] ?? null);
        $t->same('xref_selected_direct_object', $decodeParmsOperand['owner_policy'] ?? null);
        $t->same('dictionary', $decodeParmsOperand['token_type'] ?? null);
        $t->same(true, $decodeParmsOperand['valid_decodeparms_operand'] ?? null);
        $t->same('<< /Predictor /Twelve /Columns 1 >>', $decodeParmsOperand['value_preview'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'rejects trailing malformed CMap DecodeParms array entries before ToUnicode decoding' => static function (TestRunner $t) use ($parserMalformedCMapTrailingDecodeParmsBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapTrailingDecodeParmsBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $decodeParmsOperands = $entry['decodeparms_operands'] ?? [];

        $t->same(['Trailing DecodeParms Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['Trailing DecodeParms Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('Trailing DecodeParms Safe Import', $text);
        $t->same("Trailing DecodeParms Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Trailing DecodeParms CMap Leak'));
        $t->true(!str_contains($text, 'Twelve'));
        $t->true(!str_contains($text, 'TrailingDecodeParmsBoundary-H'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(0, $review['indirect_filter_count']);
        $t->same(0, $review['xref_selected_operand_count']);
        $t->same(0, $review['unresolved_operand_count']);
        $t->same(0, $review['decoded_cmap_count']);
        $t->same(0, $review['invalid_filter_operand_count']);
        $t->same(0, $review['dictionary_filter_operand_count']);
        $t->same(0, $review['malformed_filter_operand_count']);
        $t->same(0, $review['invalid_decodeparms_operand_count']);
        $t->same(0, $review['malformed_decodeparms_operand_count']);
        $t->same(1, $review['invalid_decodeparms_parameter_count']);
        $t->same(6, $entry['object_number'] ?? null);
        $t->same('TrailingDecodeParmsBoundary-H', $entry['cmap_name'] ?? null);
        $t->same(['FlateDecode'], $entry['filters'] ?? null);
        $t->same(false, $entry['filter_resolution_failed'] ?? null);
        $t->same(false, $entry['decodeparms_resolution_failed'] ?? null);
        $t->same(1, $entry['invalid_decodeparms_parameter_count'] ?? null);
        $t->same('filters_resolved', $entry['filter_operand_policy'] ?? null);
        $t->same('reject_malformed_decodeparms_parameters', $entry['decodeparms_operand_policy'] ?? null);
        $t->same(null, $entry['decoded_cmap_length'] ?? null);
        $t->same(null, $entry['decoded_cmap_sha256'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
        $t->same('direct_operands', $entry['owner_policy'] ?? null);
        $t->same('direct', $decodeParmsOperands[0]['kind'] ?? null);
        $t->same('null', $decodeParmsOperands[0]['token_type'] ?? null);
        $t->same(true, $decodeParmsOperands[0]['valid_decodeparms_operand'] ?? null);
        $t->same('direct', $decodeParmsOperands[1]['kind'] ?? null);
        $t->same('dictionary', $decodeParmsOperands[1]['token_type'] ?? null);
        $t->same('<< /Predictor /Twelve /Columns 1 >>', $decodeParmsOperands[1]['value'] ?? null);
        $t->same(true, $decodeParmsOperands[1]['valid_decodeparms_operand'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'ignores malformed DecodeParms operands aligned to null CMap filters before ToUnicode decoding' => static function (TestRunner $t) use ($parserMalformedCMapNullFilterDecodeParmsBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapNullFilterDecodeParmsBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $filterOperands = $entry['filter_operands'] ?? [];
        $decodeParmsOperands = $entry['decodeparms_operands'] ?? [];

        $t->same(['Null Slot CMap Import'], $extractor->extractTextLines($pdf));
        $t->same(['Null Slot CMap Import'], $extractor->extractTextRuns($pdf));
        $t->same('Null Slot CMap Import', $text);
        $t->same("Null Slot CMap Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, '99 0 R'));
        $t->true(!str_contains($text, 'Predictor'));
        $t->true(!str_contains($text, 'NullFilterDecodeParmsBoundary-H'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(1, $review['decoded_cmap_count']);
        $t->same(0, $review['invalid_filter_operand_count']);
        $t->same(0, $review['dictionary_filter_operand_count']);
        $t->same(0, $review['malformed_filter_operand_count']);
        $t->same(0, $review['unsupported_filter_count']);
        $t->same(0, $review['invalid_decodeparms_operand_count']);
        $t->same(0, $review['malformed_decodeparms_operand_count']);
        $t->same(0, $review['invalid_decodeparms_parameter_count']);
        $t->same(6, $entry['object_number'] ?? null);
        $t->same(0, $entry['generation'] ?? null);
        $t->same('NullFilterDecodeParmsBoundary-H', $entry['cmap_name'] ?? null);
        $t->same([null, 'FlateDecode'], $entry['filters'] ?? null);
        $t->same(false, $entry['filter_resolution_failed'] ?? null);
        $t->same(false, $entry['decodeparms_resolution_failed'] ?? null);
        $t->same('filters_resolved', $entry['filter_operand_policy'] ?? null);
        $t->same('decodeparms_resolved', $entry['decodeparms_operand_policy'] ?? null);
        $t->same(true, $entry['decoded_with_current_operands'] ?? null);
        $t->true(($entry['decoded_cmap_length'] ?? 0) > 0);
        $t->true(is_string($entry['decoded_cmap_sha256'] ?? null));
        $t->same('direct_operands', $entry['owner_policy'] ?? null);
        $t->same('direct', $filterOperands[0]['kind'] ?? null);
        $t->same('null', $filterOperands[0]['token_type'] ?? null);
        $t->same(true, $filterOperands[0]['valid_filter_operand'] ?? null);
        $t->same('direct', $filterOperands[1]['kind'] ?? null);
        $t->same('name', $filterOperands[1]['token_type'] ?? null);
        $t->same('FlateDecode', $filterOperands[1]['value'] ?? null);
        $t->same(true, $filterOperands[1]['valid_filter_operand'] ?? null);
        $t->same('indirect', $decodeParmsOperands[0]['kind'] ?? null);
        $t->same(99, $decodeParmsOperands[0]['object_number'] ?? null);
        $t->same(false, $decodeParmsOperands[0]['resolved'] ?? null);
        $t->same(false, $decodeParmsOperands[0]['xref_selected'] ?? null);
        $t->same('missing_object', $decodeParmsOperands[0]['owner_policy'] ?? null);
        $t->same('direct', $decodeParmsOperands[1]['kind'] ?? null);
        $t->same('dictionary', $decodeParmsOperands[1]['token_type'] ?? null);
        $t->same('<< /Predictor 1 >>', $decodeParmsOperands[1]['value'] ?? null);
        $t->same(true, $decodeParmsOperands[1]['valid_decodeparms_operand'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'ignores malformed operands inside indirect DecodeParms arrays aligned to null CMap filters' => static function (TestRunner $t) use ($parserMalformedCMapIndirectNullFilterDecodeParmsBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapIndirectNullFilterDecodeParmsBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $filterOperands = $entry['filter_operands'] ?? [];
        $decodeParmsOperand = $entry['decodeparms_operands'][0] ?? [];

        $t->same(['Indirect Null Slot CMap Import'], $extractor->extractTextLines($pdf));
        $t->same(['Indirect Null Slot CMap Import'], $extractor->extractTextRuns($pdf));
        $t->same('Indirect Null Slot CMap Import', $text);
        $t->same("Indirect Null Slot CMap Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, '99 0 R'));
        $t->true(!str_contains($text, 'Predictor'));
        $t->true(!str_contains($text, 'IndirectNullFilterDecodeParmsBoundary-H'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(1, $review['decoded_cmap_count']);
        $t->same(0, $review['invalid_filter_operand_count']);
        $t->same(0, $review['dictionary_filter_operand_count']);
        $t->same(0, $review['malformed_filter_operand_count']);
        $t->same(0, $review['unsupported_filter_count']);
        $t->same(0, $review['invalid_decodeparms_operand_count']);
        $t->same(0, $review['malformed_decodeparms_operand_count']);
        $t->same(0, $review['invalid_decodeparms_parameter_count']);
        $t->same(6, $entry['object_number'] ?? null);
        $t->same(0, $entry['generation'] ?? null);
        $t->same('IndirectNullFilterDecodeParmsBoundary-H', $entry['cmap_name'] ?? null);
        $t->same([null, 'FlateDecode'], $entry['filters'] ?? null);
        $t->same(false, $entry['filter_resolution_failed'] ?? null);
        $t->same(false, $entry['decodeparms_resolution_failed'] ?? null);
        $t->same('filters_resolved', $entry['filter_operand_policy'] ?? null);
        $t->same('decodeparms_resolved', $entry['decodeparms_operand_policy'] ?? null);
        $t->same(true, $entry['decoded_with_current_operands'] ?? null);
        $t->true(($entry['decoded_cmap_length'] ?? 0) > 0);
        $t->true(is_string($entry['decoded_cmap_sha256'] ?? null));
        $t->same('xref_selected_indirect_operands', $entry['owner_policy'] ?? null);
        $t->same('direct', $filterOperands[0]['kind'] ?? null);
        $t->same('null', $filterOperands[0]['token_type'] ?? null);
        $t->same(true, $filterOperands[0]['valid_filter_operand'] ?? null);
        $t->same('direct', $filterOperands[1]['kind'] ?? null);
        $t->same('name', $filterOperands[1]['token_type'] ?? null);
        $t->same('FlateDecode', $filterOperands[1]['value'] ?? null);
        $t->same(true, $filterOperands[1]['valid_filter_operand'] ?? null);
        $t->same('indirect', $decodeParmsOperand['kind'] ?? null);
        $t->same(8, $decodeParmsOperand['object_number'] ?? null);
        $t->same(0, $decodeParmsOperand['generation'] ?? null);
        $t->same(true, $decodeParmsOperand['resolved'] ?? null);
        $t->same(true, $decodeParmsOperand['xref_selected'] ?? null);
        $t->same('xref_selected_direct_object', $decodeParmsOperand['owner_policy'] ?? null);
        $t->same('array', $decodeParmsOperand['token_type'] ?? null);
        $t->same('[ 99 0 R << /Predictor 1 >> ]', $decodeParmsOperand['value_preview'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'reviews malformed inherited UseCMap DecodeParms before current-base text extraction' => static function (TestRunner $t) use ($parserMalformedUseCMapDecodeParmsBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedUseCMapDecodeParmsBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $derivedEntry = null;
        $inheritedEntry = null;
        foreach ($review['entries'] as $entry) {
            if (($entry['object_number'] ?? null) === 6) {
                $derivedEntry = $entry;
            } elseif (($entry['object_number'] ?? null) === 7) {
                $inheritedEntry = $entry;
            }
        }
        $inheritedUsage = $inheritedEntry['reference_usages'][0] ?? [];

        $t->same(['UseCMap Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['UseCMap Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('UseCMap Safe Import', $text);
        $t->same("UseCMap Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'UseCMap DecodeParms Leak'));
        $t->true(!str_contains($text, 'Twelve'));
        $t->true(!str_contains($text, 'InheritedUseCMapMalformedDecodeParms-H'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(2, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(1, $review['use_cmap_stream_count']);
        $t->same(1, $review['decoded_cmap_count']);
        $t->same(0, $review['invalid_filter_operand_count']);
        $t->same(0, $review['dictionary_filter_operand_count']);
        $t->same(0, $review['malformed_filter_operand_count']);
        $t->same(1, $review['invalid_decodeparms_parameter_count']);
        $t->true(is_array($derivedEntry));
        $t->true(is_array($inheritedEntry));
        $t->same(6, $derivedEntry['object_number'] ?? null);
        $t->same('DerivedUseCMapBoundary-H', $derivedEntry['cmap_name'] ?? null);
        $t->same('to_unicode', $derivedEntry['reference_usages'][0]['usage'] ?? null);
        $t->same(7, $inheritedEntry['object_number'] ?? null);
        $t->same(null, $inheritedEntry['cmap_name'] ?? null);
        $t->same(['FlateDecode'], $inheritedEntry['filters'] ?? null);
        $t->same(false, $inheritedEntry['filter_resolution_failed'] ?? null);
        $t->same(false, $inheritedEntry['decodeparms_resolution_failed'] ?? null);
        $t->same(1, $inheritedEntry['invalid_decodeparms_parameter_count'] ?? null);
        $t->same('filters_resolved', $inheritedEntry['filter_operand_policy'] ?? null);
        $t->same('reject_malformed_decodeparms_parameters', $inheritedEntry['decodeparms_operand_policy'] ?? null);
        $t->same(null, $inheritedEntry['decoded_cmap_length'] ?? null);
        $t->same(null, $inheritedEntry['decoded_cmap_sha256'] ?? null);
        $t->same(false, $inheritedEntry['decoded_with_current_operands'] ?? null);
        $t->same('use_cmap', $inheritedUsage['usage'] ?? null);
        $t->same(6, $inheritedUsage['source_object'] ?? null);
        $t->same('7 0 R', $inheritedUsage['reference'] ?? null);
        $t->same('<< /Predictor /Twelve /Columns 1 >>', $inheritedEntry['decodeparms_operands'][0]['value'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'classifies stale-generation CMap Filter references by the current xref-selected malformed owner' => static function (TestRunner $t) use ($parserMalformedCMapStaleReferenceFilterBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapStaleReferenceFilterBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $filterOperand = $entry['filter_operands'][0] ?? [];

        $t->same(['Stale Reference Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['Stale Reference Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('Stale Reference Safe Import', $text);
        $t->same("Stale Reference Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale Reference CMap Leak'));
        $t->true(!str_contains($text, 'xref-selected dictionary is not a decoder'));
        $t->true(!str_contains($text, 'StaleReferenceFilterBoundary-H'));
        $t->true(!str_contains($text, 'FlateDecode'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(1, $review['indirect_filter_count']);
        $t->same(0, $review['xref_selected_operand_count']);
        $t->same(1, $review['unresolved_operand_count']);
        $t->same(0, $review['decoded_cmap_count']);
        $t->same(1, $review['invalid_filter_operand_count']);
        $t->same(1, $review['dictionary_filter_operand_count']);
        $t->same(0, $review['malformed_filter_operand_count']);
        $t->same(6, $entry['object_number'] ?? null);
        $t->same(0, $entry['generation'] ?? null);
        $t->same('StaleReferenceFilterBoundary-H', $entry['cmap_name'] ?? null);
        $t->same([], $entry['filters'] ?? null);
        $t->same(true, $entry['filter_resolution_failed'] ?? null);
        $t->same(1, $entry['indirect_filter_count'] ?? null);
        $t->same(0, $entry['xref_selected_operand_count'] ?? null);
        $t->same(1, $entry['unresolved_operand_count'] ?? null);
        $t->same(1, $entry['invalid_filter_operand_count'] ?? null);
        $t->same(1, $entry['dictionary_filter_operand_count'] ?? null);
        $t->same(0, $entry['malformed_filter_operand_count'] ?? null);
        $t->same('reject_dictionary_filter_operands', $entry['filter_operand_policy'] ?? null);
        $t->same(null, $entry['decoded_cmap_length'] ?? null);
        $t->same(null, $entry['decoded_cmap_sha256'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
        $t->same('unresolved_or_unselected_indirect_operands', $entry['owner_policy'] ?? null);
        $t->same('indirect', $filterOperand['kind'] ?? null);
        $t->same(7, $filterOperand['object_number'] ?? null);
        $t->same(0, $filterOperand['generation'] ?? null);
        $t->same(true, $filterOperand['resolved'] ?? null);
        $t->same(false, $filterOperand['xref_selected'] ?? null);
        $t->same(1, $filterOperand['xref_entry_type'] ?? null);
        $t->same(1, $filterOperand['selected_generation'] ?? null);
        $t->true(($filterOperand['definition_offset'] ?? null) !== ($filterOperand['selected_offset'] ?? null));
        $t->same('xref_entry_points_elsewhere', $filterOperand['owner_policy'] ?? null);
        $t->same('dictionary', $filterOperand['token_type'] ?? null);
        $t->same(true, $filterOperand['dictionary_filter_operand'] ?? null);
        $t->same(false, $filterOperand['valid_filter_operand'] ?? null);
        $t->same(
            '<< /Owner (xref-selected dictionary is not a decoder) /StaleValidFilter 7 0 R >>',
            $filterOperand['value_preview'] ?? null
        );
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'classifies nested-array CMap Filter dictionaries before current-base text extraction' => static function (TestRunner $t) use ($parserMalformedCMapNestedArrayDictionaryFilterBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapNestedArrayDictionaryFilterBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $filterOperands = $entry['filter_operands'] ?? [];

        $t->same(['Nested Array Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['Nested Array Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('Nested Array Safe Import', $text);
        $t->same("Nested Array Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Nested Array CMap Leak'));
        $t->true(!str_contains($text, 'nested dictionary is not a decoder'));
        $t->true(!str_contains($text, 'NestedArrayFilterBoundary-H'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(0, $review['decoded_cmap_count']);
        $t->same(1, $review['invalid_filter_operand_count']);
        $t->same(1, $review['dictionary_filter_operand_count']);
        $t->same(0, $review['malformed_filter_operand_count']);
        $t->same(6, $entry['object_number'] ?? null);
        $t->same(0, $entry['generation'] ?? null);
        $t->same('NestedArrayFilterBoundary-H', $entry['cmap_name'] ?? null);
        $t->same([], $entry['filters'] ?? null);
        $t->same(true, $entry['filter_resolution_failed'] ?? null);
        $t->same(1, $entry['invalid_filter_operand_count'] ?? null);
        $t->same(1, $entry['dictionary_filter_operand_count'] ?? null);
        $t->same(0, $entry['malformed_filter_operand_count'] ?? null);
        $t->same('reject_dictionary_filter_operands', $entry['filter_operand_policy'] ?? null);
        $t->same(null, $entry['decoded_cmap_length'] ?? null);
        $t->same(null, $entry['decoded_cmap_sha256'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
        $t->same('direct_operands', $entry['owner_policy'] ?? null);
        $t->same('direct', $filterOperands[0]['kind'] ?? null);
        $t->same('array', $filterOperands[0]['token_type'] ?? null);
        $t->same('[ [ << /Owner (nested dictionary is not a decoder) /Fake /Nested >> ] ]', $filterOperands[0]['value'] ?? null);
        $t->same(true, $filterOperands[0]['dictionary_filter_operand'] ?? null);
        $t->same(false, $filterOperands[0]['valid_filter_operand'] ?? null);
        $t->same('direct', $filterOperands[1]['kind'] ?? null);
        $t->same('name', $filterOperands[1]['token_type'] ?? null);
        $t->same('FlateDecode', $filterOperands[1]['value'] ?? null);
        $t->same(true, $filterOperands[1]['valid_filter_operand'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'ignores decoded CMap operators after endcmap before current-base text extraction' => static function (TestRunner $t) use ($parserMalformedCMapPostEndOperatorBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapPostEndOperatorBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];

        $t->same(['PostEnd Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['PostEnd Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('PostEnd Safe Import', $text);
        $t->same("PostEnd Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'PostEnd CMap Leak'));
        $t->true(!str_contains($text, 'PostEndCMapDecoy-H'));
        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->same(1, $review['decoded_cmap_count']);
        $t->same(6, $entry['object_number'] ?? null);
        $t->same('PostEndCMapBoundary-H', $entry['cmap_name'] ?? null);
        $t->same(['FlateDecode'], $entry['filters'] ?? null);
        $t->same(false, $entry['filter_resolution_failed'] ?? null);
        $t->same('filters_resolved', $entry['filter_operand_policy'] ?? null);
        $t->same('decodeparms_resolved', $entry['decodeparms_operand_policy'] ?? null);
        $t->true(($entry['post_endcmap_bytes_excluded'] ?? false) === true);
        $t->true(($entry['post_endcmap_byte_count'] ?? 0) > 0);
        $t->true(($entry['bounded_cmap_length'] ?? 0) < ($entry['decoded_cmap_length'] ?? 0));
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'ignores complete decoded CMap programs after the first stream endcmap before current-base text extraction' => static function (TestRunner $t) use ($parserMalformedCMapSecondProgramBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapSecondProgramBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];

        $t->same(['SecondProgram Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['SecondProgram Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('SecondProgram Safe Import', $text);
        $t->same("SecondProgram Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Second Program CMap Leak'));
        $t->true(!str_contains($text, 'SecondProgramDecoy-H'));
        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->same(1, $review['decoded_cmap_count']);
        $t->same(6, $entry['object_number'] ?? null);
        $t->same('SecondProgramBoundary-H', $entry['cmap_name'] ?? null);
        $t->same(['FlateDecode'], $entry['filters'] ?? null);
        $t->same(false, $entry['filter_resolution_failed'] ?? null);
        $t->same('filters_resolved', $entry['filter_operand_policy'] ?? null);
        $t->same('decodeparms_resolved', $entry['decodeparms_operand_policy'] ?? null);
        $t->true(($entry['post_endcmap_bytes_excluded'] ?? false) === true);
        $t->true(($entry['post_endcmap_byte_count'] ?? 0) > 0);
        $t->true(($entry['parser_bounded_cmap_bytes_excluded'] ?? false) === true);
        $t->true(($entry['parser_excluded_cmap_byte_count'] ?? 0) > ($entry['post_endcmap_byte_count'] ?? 0));
        $t->true(($entry['parser_bounded_cmap_length'] ?? 0) < ($entry['bounded_cmap_length'] ?? 0));
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'ignores CMap block operators inside decoded literal strings before current-base text extraction' => static function (TestRunner $t) use ($parserMalformedCMapLiteralOperatorBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapLiteralOperatorBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];

        $t->same(['Literal Operator Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['Literal Operator Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('Literal Operator Safe Import', $text);
        $t->same("Literal Operator Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Literal Operator CMap Leak'));
        $t->true(!str_contains($text, 'beginbfchar'));
        $t->true(!str_contains($text, "\0"));
        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->same(1, $review['decoded_cmap_count']);
        $t->same(6, $entry['object_number'] ?? null);
        $t->same('LiteralOperatorBoundary-H', $entry['cmap_name'] ?? null);
        $t->same(['FlateDecode'], $entry['filters'] ?? null);
        $t->same(false, $entry['filter_resolution_failed'] ?? null);
        $t->same('filters_resolved', $entry['filter_operand_policy'] ?? null);
        $t->same('decodeparms_resolved', $entry['decodeparms_operand_policy'] ?? null);
        $t->same(true, $entry['decoded_with_current_operands'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'ignores decoded CMapName declarations inside literal strings before UseCMap inheritance' => static function (TestRunner $t) use ($parserMalformedCMapLiteralNameUseCMapBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapLiteralNameUseCMapBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $derivedEntry = null;
        $baseEntry = null;
        foreach ($review['entries'] as $entry) {
            if (($entry['object_number'] ?? null) === 6) {
                $derivedEntry = $entry;
            } elseif (($entry['object_number'] ?? null) === 7) {
                $baseEntry = $entry;
            }
        }

        $t->same(['Literal Name Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['Literal Name Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('Literal Name Safe Import', $text);
        $t->same("Literal Name Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'FakeBase-H'));
        $t->true(!str_contains($text, 'CMapName'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(2, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(2, $review['decoded_cmap_count']);
        $t->true(is_array($derivedEntry));
        $t->true(is_array($baseEntry));
        $t->same(6, $derivedEntry['object_number'] ?? null);
        $t->same('DerivedLiteralName-H', $derivedEntry['cmap_name'] ?? null);
        $t->same(['to_unicode'], array_column($derivedEntry['reference_usages'] ?? [], 'usage'));
        $t->same('direct_operands', $derivedEntry['owner_policy'] ?? null);
        $t->same(7, $baseEntry['object_number'] ?? null);
        $t->same('RealBase-H', $baseEntry['cmap_name'] ?? null);
        $t->same([], $baseEntry['reference_usages'] ?? null);
        $t->same(['FlateDecode'], $baseEntry['filters'] ?? null);
        $t->same(false, $baseEntry['filter_resolution_failed'] ?? null);
        $t->same('filters_resolved', $baseEntry['filter_operand_policy'] ?? null);
        $t->same(true, $baseEntry['decoded_with_current_operands'] ?? null);
        $t->true(($baseEntry['decoded_cmap_length'] ?? 0) > 0);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'ignores decoded usecmap operators inside literal strings before inherited CMap parsing' => static function (TestRunner $t) use ($parserMalformedCMapLiteralUseCMapOperatorBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapLiteralUseCMapOperatorBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $derivedEntry = null;
        $realBaseEntry = null;
        $decoyBaseEntry = null;
        foreach ($review['entries'] as $entry) {
            if (($entry['object_number'] ?? null) === 6) {
                $derivedEntry = $entry;
            } elseif (($entry['object_number'] ?? null) === 7) {
                $realBaseEntry = $entry;
            } elseif (($entry['object_number'] ?? null) === 8) {
                $decoyBaseEntry = $entry;
            }
        }

        $t->same(['Literal UseCMap Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['Literal UseCMap Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('Literal UseCMap Safe Import', $text);
        $t->same("Literal UseCMap Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Literal UseCMap Leak'));
        $t->true(!str_contains($text, 'LiteralUseCMapDecoyBase-H'));
        $t->true(!str_contains($text, 'usecmap'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(3, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(3, $review['decoded_cmap_count']);
        $t->true(is_array($derivedEntry));
        $t->true(is_array($realBaseEntry));
        $t->true(is_array($decoyBaseEntry));
        $t->same(6, $derivedEntry['object_number'] ?? null);
        $t->same('LiteralUseCMapDerived-H', $derivedEntry['cmap_name'] ?? null);
        $t->same(['to_unicode'], array_column($derivedEntry['reference_usages'] ?? [], 'usage'));
        $t->same(['FlateDecode'], $derivedEntry['filters'] ?? null);
        $t->same('filters_resolved', $derivedEntry['filter_operand_policy'] ?? null);
        $t->same(true, $derivedEntry['decoded_with_current_operands'] ?? null);
        $t->same(7, $realBaseEntry['object_number'] ?? null);
        $t->same('LiteralUseCMapRealBase-H', $realBaseEntry['cmap_name'] ?? null);
        $t->same([], $realBaseEntry['reference_usages'] ?? null);
        $t->same(['FlateDecode'], $realBaseEntry['filters'] ?? null);
        $t->same(true, $realBaseEntry['decoded_with_current_operands'] ?? null);
        $t->same(8, $decoyBaseEntry['object_number'] ?? null);
        $t->same('LiteralUseCMapDecoyBase-H', $decoyBaseEntry['cmap_name'] ?? null);
        $t->same([], $decoyBaseEntry['reference_usages'] ?? null);
        $t->same(['FlateDecode'], $decoyBaseEntry['filters'] ?? null);
        $t->same(true, $decoyBaseEntry['decoded_with_current_operands'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'treats identity Crypt CMap filters as pass-through while rejecting named crypt filters' => static function (TestRunner $t) use (
        $parserMalformedCMapCryptIdentityFilterBoundaryCurrentBasePdf,
        $parserMalformedCMapCryptPrivateFilterBoundaryCurrentBasePdf
    ): void {
        $extractor = new PdfTextExtractor();
        $identityPdf = $parserMalformedCMapCryptIdentityFilterBoundaryCurrentBasePdf();
        $privatePdf = $parserMalformedCMapCryptPrivateFilterBoundaryCurrentBasePdf();
        $identityText = $extractor->extractPlainText($identityPdf);
        $privateText = $extractor->extractPlainText($privatePdf);
        $identityReview = $extractor->extractCMapStreamFilterLengthOwnerReview($identityPdf);
        $privateReview = $extractor->extractCMapStreamFilterLengthOwnerReview($privatePdf);
        $identityEntry = $identityReview['entries'][0] ?? [];
        $privateEntry = $privateReview['entries'][0] ?? [];

        $t->same(['Identity Crypt CMap Import'], $extractor->extractTextLines($identityPdf));
        $t->same(['Identity Crypt CMap Import'], $extractor->extractTextRuns($identityPdf));
        $t->same('Identity Crypt CMap Import', $identityText);
        $t->same("Identity Crypt CMap Import\n", $extractor->naiveGetText($identityPdf));
        $t->same(1, $extractor->extractOutlineMetadata($identityPdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($identityPdf));
        $t->true(!str_contains($identityText, 'CryptIdentityBoundary-H'));
        $t->true(!str_contains($identityText, '/Name'));
        $t->true(!str_contains($identityText, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $identityReview['source']);
        $t->true($identityReview['review_only']);
        $t->same(false, $identityReview['encrypted']);
        $t->same(1, $identityReview['cmap_stream_count']);
        $t->same(1, $identityReview['to_unicode_cmap_stream_count']);
        $t->same(1, $identityReview['decoded_cmap_count']);
        $t->same(0, $identityReview['invalid_filter_operand_count']);
        $t->same(0, $identityReview['malformed_filter_operand_count']);
        $t->same(0, $identityReview['unsupported_filter_count']);
        $t->same(0, $identityReview['invalid_decodeparms_parameter_count']);
        $t->same(6, $identityEntry['object_number'] ?? null);
        $t->same('CryptIdentityBoundary-H', $identityEntry['cmap_name'] ?? null);
        $t->same(['Crypt'], $identityEntry['filters'] ?? null);
        $t->same(false, $identityEntry['filter_resolution_failed'] ?? null);
        $t->same(false, $identityEntry['decodeparms_resolution_failed'] ?? null);
        $t->same(0, $identityEntry['unsupported_filter_count'] ?? null);
        $t->same('filters_resolved', $identityEntry['filter_operand_policy'] ?? null);
        $t->same('decodeparms_resolved', $identityEntry['decodeparms_operand_policy'] ?? null);
        $t->same(true, $identityEntry['decoded_with_current_operands'] ?? null);
        $t->true(($identityEntry['decoded_cmap_length'] ?? 0) > 0);
        $t->true(is_string($identityEntry['decoded_cmap_sha256'] ?? null));
        $t->same('direct_operands', $identityEntry['owner_policy'] ?? null);
        $t->same('Crypt', $identityEntry['filter_operands'][0]['value'] ?? null);
        $t->same('<< /Name /Identity >>', $identityEntry['decodeparms_operands'][0]['value'] ?? null);

        $t->same(['Private Crypt Safe Import'], $extractor->extractTextLines($privatePdf));
        $t->same(['Private Crypt Safe Import'], $extractor->extractTextRuns($privatePdf));
        $t->same('Private Crypt Safe Import', $privateText);
        $t->same("Private Crypt Safe Import\n", $extractor->naiveGetText($privatePdf));
        $t->same(1, $extractor->extractOutlineMetadata($privatePdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($privatePdf));
        $t->true(!str_contains($privateText, 'Private Crypt CMap Leak'));
        $t->true(!str_contains($privateText, 'CryptPrivateBoundary-H'));
        $t->true(!str_contains($privateText, 'PrivateCF'));
        $t->true(!str_contains($privateText, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $privateReview['source']);
        $t->true($privateReview['review_only']);
        $t->same(false, $privateReview['encrypted']);
        $t->same(1, $privateReview['cmap_stream_count']);
        $t->same(1, $privateReview['to_unicode_cmap_stream_count']);
        $t->same(0, $privateReview['decoded_cmap_count']);
        $t->same(0, $privateReview['invalid_filter_operand_count']);
        $t->same(0, $privateReview['malformed_filter_operand_count']);
        $t->same(1, $privateReview['unsupported_filter_count']);
        $t->same(0, $privateReview['invalid_decodeparms_parameter_count']);
        $t->same(6, $privateEntry['object_number'] ?? null);
        $t->same('CryptPrivateBoundary-H', $privateEntry['cmap_name'] ?? null);
        $t->same(['Crypt'], $privateEntry['filters'] ?? null);
        $t->same(false, $privateEntry['filter_resolution_failed'] ?? null);
        $t->same(false, $privateEntry['decodeparms_resolution_failed'] ?? null);
        $t->same(1, $privateEntry['unsupported_filter_count'] ?? null);
        $t->same('reject_unsupported_filter_names', $privateEntry['filter_operand_policy'] ?? null);
        $t->same('decodeparms_resolved', $privateEntry['decodeparms_operand_policy'] ?? null);
        $t->same(null, $privateEntry['decoded_cmap_length'] ?? null);
        $t->same(null, $privateEntry['decoded_cmap_sha256'] ?? null);
        $t->same(false, $privateEntry['decoded_with_current_operands'] ?? null);
        $t->same('direct_operands', $privateEntry['owner_policy'] ?? null);
        $t->same('Crypt', $privateEntry['filter_operands'][0]['value'] ?? null);
        $t->same('<< /Name /PrivateCF >>', $privateEntry['decodeparms_operands'][0]['value'] ?? null);
        $t->same(false, $identityReview['executes_python_or_models']);
        $t->same(false, $identityReview['executes_external_pdf_tools']);
        $t->same(false, $privateReview['executes_python_or_models']);
        $t->same(false, $privateReview['executes_external_pdf_tools']);
    },
    'classifies unsupported CMap Filter names as fail-closed before current-base text extraction' => static function (TestRunner $t) use ($parserMalformedCMapUnsupportedFilterBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserMalformedCMapUnsupportedFilterBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractCMapStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $filterOperand = $entry['filter_operands'][0] ?? [];

        $t->same(['Unsupported Filter Safe Import'], $extractor->extractTextLines($pdf));
        $t->same(['Unsupported Filter Safe Import'], $extractor->extractTextRuns($pdf));
        $t->same('Unsupported Filter Safe Import', $text);
        $t->same("Unsupported Filter Safe Import\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Unsupported Filter CMap Leak'));
        $t->true(!str_contains($text, 'UnsupportedFilterBoundary-H'));
        $t->true(!str_contains($text, 'DCTDecode'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_cmap_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(false, $review['encrypted']);
        $t->same(1, $review['cmap_stream_count']);
        $t->same(1, $review['to_unicode_cmap_stream_count']);
        $t->same(0, $review['encoding_cmap_stream_count']);
        $t->same(0, $review['decoded_cmap_count']);
        $t->same(0, $review['invalid_filter_operand_count']);
        $t->same(0, $review['dictionary_filter_operand_count']);
        $t->same(0, $review['malformed_filter_operand_count']);
        $t->same(1, $review['unsupported_filter_count']);
        $t->same(6, $entry['object_number'] ?? null);
        $t->same(0, $entry['generation'] ?? null);
        $t->same('UnsupportedFilterBoundary-H', $entry['cmap_name'] ?? null);
        $t->same(['DCTDecode'], $entry['filters'] ?? null);
        $t->same(false, $entry['filter_resolution_failed'] ?? null);
        $t->same('reject_unsupported_filter_names', $entry['filter_operand_policy'] ?? null);
        $t->same(1, $entry['unsupported_filter_count'] ?? null);
        $t->same(null, $entry['decoded_cmap_length'] ?? null);
        $t->same(null, $entry['decoded_cmap_sha256'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
        $t->same('direct_operands', $entry['owner_policy'] ?? null);
        $t->same('direct', $filterOperand['kind'] ?? null);
        $t->same('name', $filterOperand['token_type'] ?? null);
        $t->same('DCTDecode', $filterOperand['value'] ?? null);
        $t->same(true, $filterOperand['valid_filter_operand'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
