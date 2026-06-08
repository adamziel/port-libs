<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserXrefStreamIndexWidthOwnerReviewCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current indirect xref array page) Tj T* (Indirect W Index owners reviewed) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale indirect xref array leak) Tj T* (Fallback object stream expanded) Tj ET';

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
            throw new RuntimeException('Unable to compress xref array owner review object-stream fixture.');
        }

        return [$header, $compressed];
    };

    $currentMemberIndexes = [];
    $staleMemberIndexes = [];
    [$currentHeader, $currentCompressedObjectStream] = $objectStream([
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
    ], $currentMemberIndexes);
    [$staleHeader, $staleCompressedObjectStream] = $objectStream([
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R /Note (stale fallback object stream page) >>',
    ], $staleMemberIndexes);

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $row = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($currentHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($currentCompressedObjectStream) . " >>\nstream\n{$currentCompressedObjectStream}\nendstream");
    $addObject(7, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($staleHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($staleCompressedObjectStream) . " >>\nstream\n{$staleCompressedObjectStream}\nendstream");
    $addObject(9, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $wOffset = $addObject(30, 0, '[1 4 1]');
    $indexOffset = $addObject(31, 0, '[1 6 9 1 30 3]');
    $sizeOffset = $addObject(32, 0, '33');

    $xrefRows = ''
        . $row(1, $offsets['1:0'])
        . $row(1, $offsets['2:0'])
        . $row(1, $offsets['3:0'])
        . $row(2, 6, $currentMemberIndexes[4])
        . $row(1, $offsets['5:0'])
        . $row(1, $offsets['6:0'])
        . $row(1, $offsets['9:0'])
        . $row(1, $wOffset)
        . $row(1, $indexOffset)
        . $row(1, $sizeOffset);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress indirect W/Index owner review xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 32 0 R /Root 1 0 R /Index 31 0 R /W 30 0 R /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'reviews indirect xref-stream W and Index owners before object-stream current-base selection' => static function (TestRunner $t) use ($parserXrefStreamIndexWidthOwnerReviewCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserXrefStreamIndexWidthOwnerReviewCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $objectStreamReview = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($objectStreamReview['entries'], null, 'object_number');
        $objectStreamEntry = $entries[4] ?? [];
        $operandReview = $extractor->extractXrefStreamFilterLengthOwnerReview($pdf);
        $operandEntry = $operandReview['entries'][0] ?? [];
        $wOperand = $operandEntry['w_operand'] ?? [];
        $indexOperand = $operandEntry['index_operand'] ?? [];
        $sizeOperand = $operandEntry['size_operand'] ?? [];

        $expected = ['Current indirect xref array page', 'Indirect W Index owners reviewed'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale indirect xref array leak'));
        $t->true(!str_contains($text, 'Fallback object stream expanded'));
        $t->true(!str_contains($text, 'stale fallback object stream page'));
        $t->same('pdf_xref_object_stream_index_review', $objectStreamReview['source']);
        $t->same(1, $objectStreamReview['compressed_entry_count']);
        $t->same(4, $objectStreamEntry['object_number'] ?? null);
        $t->same(6, $objectStreamEntry['object_stream'] ?? null);
        $t->same('xref_selected_object_stream_carrier', $objectStreamEntry['object_stream_owner_policy'] ?? null);
        $t->same('explicit_member_index', $objectStreamEntry['selection_policy'] ?? null);

        $t->same('pdf_xref_stream_filter_length_owner_review', $operandReview['source']);
        $t->true($operandReview['review_only']);
        $t->same(1, $operandReview['xref_stream_count']);
        $t->same(0, $operandReview['indirect_filter_count']);
        $t->same(0, $operandReview['indirect_length_count']);
        $t->same(1, $operandReview['indirect_size_count']);
        $t->same(1, $operandReview['indirect_w_count']);
        $t->same(1, $operandReview['indirect_index_count']);
        $t->same(3, $operandReview['xref_selected_operand_count']);
        $t->same(0, $operandReview['unresolved_operand_count']);
        $t->same(20, $operandEntry['object_number'] ?? null);
        $t->true($operandEntry['startxref_selected'] ?? false);
        $t->same(10, $operandEntry['decoded_entry_count'] ?? null);
        $t->true($operandEntry['decoded_with_current_operands'] ?? false);
        $t->same('xref_selected_indirect_operands', $operandEntry['owner_policy'] ?? null);
        $t->same(1, $operandEntry['indirect_w_count'] ?? null);
        $t->same(1, $operandEntry['indirect_index_count'] ?? null);
        $t->same('xref_selected_direct_object', $wOperand['owner_policy'] ?? null);
        $t->same(30, $wOperand['object_number'] ?? null);
        $t->true($wOperand['xref_selected'] ?? false);
        $t->same('[1 4 1]', $wOperand['value_preview'] ?? null);
        $t->same('xref_selected_direct_object', $indexOperand['owner_policy'] ?? null);
        $t->same(31, $indexOperand['object_number'] ?? null);
        $t->true($indexOperand['xref_selected'] ?? false);
        $t->same('[1 6 9 1 30 3]', $indexOperand['value_preview'] ?? null);
        $t->same('xref_selected_direct_object', $sizeOperand['owner_policy'] ?? null);
        $t->same(32, $sizeOperand['object_number'] ?? null);
        $t->true($sizeOperand['xref_selected'] ?? false);
        $t->same('33', $sizeOperand['value_preview'] ?? null);
        $t->same(false, $operandReview['executes_python_or_models']);
        $t->same(false, $operandReview['executes_external_pdf_tools']);
    },
];
