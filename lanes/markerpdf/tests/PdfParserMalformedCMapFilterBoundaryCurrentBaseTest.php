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
];
