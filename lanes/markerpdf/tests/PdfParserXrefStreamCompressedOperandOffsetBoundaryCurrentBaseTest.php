<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserXrefStreamCompressedOperandOffsetBoundaryCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Malformed compressed operand page) Tj T* (Object stream helper offset rejected) Tj ET';
    $hiddenFilterCarrier = '% ignored /FlateDecode';
    $helperObjectData = $hiddenFilterCarrier . "\nnull\n";
    $hiddenFilterOffset = strpos($helperObjectData, '/FlateDecode');
    $nullOffset = strlen($hiddenFilterCarrier) + 1;
    if ($hiddenFilterOffset === false) {
        throw new RuntimeException('Focused xref helper fixture is missing hidden filter token.');
    }

    $helperHeader = '30 ' . $hiddenFilterOffset . ' 31 ' . $nullOffset;
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

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 2 /First ' . (strlen($helperHeader) + 1) . ' /Length ' . strlen($helperObjectStream) . " >>\nstream\n{$helperObjectStream}\nendstream");

    $xrefRows = ''
        . $xrefRow(1, $offsets['1:0'])
        . $xrefRow(1, $offsets['2:0'])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(1, $offsets['4:0'])
        . $xrefRow(1, $offsets['5:0'])
        . $xrefRow(1, $offsets['6:0']);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress malformed compressed xref operand fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /Index [1 6] /W [1 4 1] /Filter 30 0 R /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rejects compressed xref-stream Filter helpers whose object-stream offsets start inside comments' => static function (TestRunner $t) use ($parserXrefStreamCompressedOperandOffsetBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserXrefStreamCompressedOperandOffsetBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $filterOperand = $entry['filter_operands'][0] ?? [];

        $t->same([], $extractor->extractTextLines($pdf));
        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same('', $text);
        $t->same('', $extractor->naiveGetText($pdf));
        $t->same(0, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same([], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Malformed compressed operand page'));
        $t->true(!str_contains($text, 'Object stream helper offset rejected'));
        $t->true(!str_contains($text, 'FlateDecode helper'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_stream_filter_length_owner_review', $review['source']);
        $t->same(1, $review['xref_stream_count']);
        $t->same(1, $review['indirect_filter_count']);
        $t->same(0, $review['xref_selected_operand_count']);
        $t->same(1, $review['unresolved_operand_count']);
        $t->same(20, $entry['object_number'] ?? null);
        $t->true($entry['startxref_selected'] ?? false);
        $t->same([], $entry['filters'] ?? null);
        $t->same(true, $entry['filter_resolution_failed'] ?? null);
        $t->same(0, $entry['decoded_entry_count'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
        $t->same('unresolved_or_unselected_indirect_operands', $entry['owner_policy'] ?? null);
        $t->same('indirect', $filterOperand['kind'] ?? null);
        $t->same(30, $filterOperand['object_number'] ?? null);
        $t->same(0, $filterOperand['generation'] ?? null);
        $t->same(false, $filterOperand['resolved'] ?? null);
        $t->same(false, $filterOperand['xref_selected'] ?? null);
        $t->same('missing_object', $filterOperand['owner_policy'] ?? null);
        $t->same(null, $filterOperand['value_preview'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
