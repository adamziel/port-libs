<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserXrefStreamCompressedSizeDefaultRangeCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current compressed Size page) Tj T* (Default xref range recovered) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale compressed Size fallback leak) Tj ET';

    $objectStream = static function (array $members, array &$memberIndexes): array {
        $headerPairs = [];
        $memberIndexes = [];
        $objectData = '';
        foreach ($members as $objectNumber => $body) {
            $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
            $memberIndexes[$objectNumber] = count($memberIndexes);
            $objectData .= $body . "\n";
        }

        $header = implode(' ', $headerPairs);
        $plain = $header . "\n" . $objectData;
        $compressed = gzcompress($plain);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress compressed-Size object-stream fixture.');
        }

        return [$header, $compressed];
    };

    $currentMemberIndexes = [];
    [$currentHeader, $currentCompressedObjectStream] = $objectStream([
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
    ], $currentMemberIndexes);

    $sizeHelperIndexes = [];
    [$sizeHelperHeader, $sizeHelperObjectStream] = $objectStream([
        30 => '31',
    ], $sizeHelperIndexes);

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $row = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N ' . count($currentMemberIndexes) . ' /First ' . (strlen($currentHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($currentCompressedObjectStream) . " >>\nstream\n{$currentCompressedObjectStream}\nendstream");
    $addObject(7, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($sizeHelperHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($sizeHelperObjectStream) . " >>\nstream\n{$sizeHelperObjectStream}\nendstream");
    $addObject(9, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $xrefRows = '';
    for ($objectNumber = 0; $objectNumber <= 30; $objectNumber++) {
        $xrefRows .= match ($objectNumber) {
            1, 2, 3, 4 => $row(2, 6, $currentMemberIndexes[$objectNumber]),
            5 => $row(1, $offsets['5:0']),
            6 => $row(1, $offsets['6:0']),
            7 => $row(1, $offsets['7:0']),
            9 => $row(1, $offsets['9:0']),
            30 => $row(2, 7, $sizeHelperIndexes[30]),
            default => $row(0, 0, 255),
        };
    }
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress compressed-Size xref stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 30 0 R /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'resolves compressed xref-stream Size helper before default row-range object-stream selection' => static function (
        TestRunner $t
    ) use ($parserXrefStreamCompressedSizeDefaultRangeCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserXrefStreamCompressedSizeDefaultRangeCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $operandReview = $extractor->extractXrefStreamFilterLengthOwnerReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $operandEntry = $operandReview['entries'][0] ?? [];
        $sizeOperand = $operandEntry['size_operand'] ?? [];

        $expected = ['Current compressed Size page', 'Default xref range recovered'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale compressed Size fallback leak'));
        $t->true(!str_contains($text, "\0"));
        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(5, $review['compressed_entry_count']);
        $t->same(1, $entries[1]['object_number'] ?? null);
        $t->same(6, $entries[1]['object_stream'] ?? null);
        $t->same('xref_selected_object_stream_carrier', $entries[1]['object_stream_owner_policy'] ?? null);
        $t->same('explicit_member_index', $entries[1]['selection_policy'] ?? null);
        $t->same(30, $entries[30]['object_number'] ?? null);
        $t->same(7, $entries[30]['object_stream'] ?? null);
        $t->same('pdf_xref_stream_filter_length_owner_review', $operandReview['source']);
        $t->true($operandReview['review_only']);
        $t->same(1, $operandReview['xref_stream_count']);
        $t->same(0, $operandReview['indirect_filter_count']);
        $t->same(0, $operandReview['indirect_length_count']);
        $t->same(1, $operandReview['indirect_size_count']);
        $t->same(1, $operandReview['xref_selected_operand_count']);
        $t->same(0, $operandReview['unresolved_operand_count']);
        $t->same(20, $operandEntry['object_number'] ?? null);
        $t->same(31, $operandEntry['decoded_entry_count'] ?? null);
        $t->same('xref_selected_indirect_operands', $operandEntry['owner_policy'] ?? null);
        $t->true($operandEntry['decoded_with_current_operands'] ?? false);
        $t->same(1, $operandEntry['indirect_size_count'] ?? null);
        $t->same('indirect', $sizeOperand['kind'] ?? null);
        $t->same(30, $sizeOperand['object_number'] ?? null);
        $t->same(0, $sizeOperand['generation'] ?? null);
        $t->true($sizeOperand['resolved'] ?? false);
        $t->true($sizeOperand['xref_selected'] ?? false);
        $t->same(2, $sizeOperand['xref_entry_type'] ?? null);
        $t->same('compressed_operand_after_xref_decode', $sizeOperand['owner_policy'] ?? null);
        $t->same('31', $sizeOperand['value_preview'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
        $t->same(false, $operandReview['executes_python_or_models']);
        $t->same(false, $operandReview['executes_external_pdf_tools']);
    },
];
