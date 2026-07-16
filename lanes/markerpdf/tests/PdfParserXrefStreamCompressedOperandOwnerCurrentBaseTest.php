<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserXrefStreamCompressedOperandOwnerCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current compressed xref operand page) Tj T* (Compressed Filter operand selected) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 704 Td (Stale compressed xref operand leak) Tj ET';

    $helperMembers = [
        30 => '/FlateDecode',
        31 => 'null',
    ];
    $helperObjectData = '';
    $helperHeaderPairs = [];
    $helperMemberIndexes = [];
    foreach ($helperMembers as $objectNumber => $body) {
        $helperHeaderPairs[] = $objectNumber . ' ' . strlen($helperObjectData);
        $helperMemberIndexes[$objectNumber] = count($helperMemberIndexes);
        $helperObjectData .= $body . "\n";
    }
    $helperHeader = implode(' ', $helperHeaderPairs);
    $helperObjectStream = $helperHeader . "\n" . $helperObjectData;

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(30, 0, '/ASCIIHexDecode');
    $addObject(31, 0, '<< /Predictor /Twelve /Columns 1 >>');
    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N ' . count($helperMembers) . ' /First ' . (strlen($helperHeader) + 1) . ' /Length ' . strlen($helperObjectStream) . " >>\nstream\n{$helperObjectStream}\nendstream");
    $addObject(9, 0, '<< /Type /Catalog /Pages 10 0 R >>');
    $addObject(10, 0, '<< /Type /Pages /Kids [11 0 R] /Count 1 >>');
    $addObject(11, 0, '<< /Type /Page /Parent 10 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 12 0 R >>');
    $addObject(12, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $xrefRows = ''
        . $xrefRow(1, $offsets['1:0'])
        . $xrefRow(1, $offsets['2:0'])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(1, $offsets['4:0'])
        . $xrefRow(1, $offsets['5:0'])
        . $xrefRow(1, $offsets['6:0'])
        . $xrefRow(2, 6, $helperMemberIndexes[30])
        . $xrefRow(2, 6, $helperMemberIndexes[31]);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress focused xref-stream compressed operand fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 32 /Root 1 0 R /Index [1 6 30 2] /W [1 4 1] /Filter 30 0 R /DecodeParms 31 0 R /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'resolves xref-stream Filter operands from current compressed object-stream helpers' => static function (TestRunner $t) use ($parserXrefStreamCompressedOperandOwnerCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserXrefStreamCompressedOperandOwnerCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $filterOperand = $entry['filter_operands'][0] ?? [];

        $expected = [
            'Current compressed xref operand page',
            'Compressed Filter operand selected',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale compressed xref operand leak'));
        $t->true(!str_contains($text, 'ASCIIHexDecode'));
        $t->true(!str_contains($text, 'Twelve'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_stream_filter_length_owner_review', $review['source']);
        $t->same(1, $review['xref_stream_count']);
        $t->same(1, $review['indirect_filter_count']);
        $t->same(0, $review['indirect_length_count']);
        $t->same(1, $review['xref_selected_operand_count']);
        $t->same(0, $review['unresolved_operand_count']);
        $t->same(20, $entry['object_number'] ?? null);
        $t->true($entry['startxref_selected'] ?? false);
        $t->same(['FlateDecode'], $entry['filters'] ?? null);
        $t->same(8, $entry['decoded_entry_count'] ?? null);
        $t->true($entry['decoded_with_current_operands'] ?? false);
        $t->same('xref_selected_indirect_operands', $entry['owner_policy'] ?? null);
        $t->same('indirect', $filterOperand['kind'] ?? null);
        $t->same(30, $filterOperand['object_number'] ?? null);
        $t->same(0, $filterOperand['generation'] ?? null);
        $t->true($filterOperand['resolved'] ?? false);
        $t->true($filterOperand['xref_selected'] ?? false);
        $t->same(2, $filterOperand['xref_entry_type'] ?? null);
        $t->same('compressed_operand_after_xref_decode', $filterOperand['owner_policy'] ?? null);
        $t->same('/FlateDecode', $filterOperand['value_preview'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
