<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserXrefStreamFilterLengthOwnerReviewPdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current xref operand owner page) Tj T* (Filter length owners reviewed) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale xref operand owner page) Tj ET';

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $addObject(9, 0, '<< /Type /Catalog /Pages 10 0 R >>');
    $addObject(10, 0, '<< /Type /Pages /Kids [11 0 R] /Count 1 >>');
    $addObject(11, 0, '<< /Type /Page /Parent 10 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 12 0 R >>');
    $addObject(12, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $filterObjectOffset = $addObject(30, 0, '/FlateDecode');
    $lengthObjectOffset = $addObject(31, 0, '0');

    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);
    $xrefRows = ''
        . $xrefRow(1, $offsets['1:0'])
        . $xrefRow(1, $offsets['2:0'])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(1, $offsets['4:0'])
        . $xrefRow(1, $offsets['5:0'])
        . $xrefRow(0, 0, 1)
        . $xrefRow(0, 0, 1)
        . $xrefRow(0, 0, 1)
        . $xrefRow(0, 0, 1)
        . $xrefRow(1, $filterObjectOffset)
        . $xrefRow(1, $lengthObjectOffset);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress focused xref-stream operand owner fixture.');
    }

    $pdf = substr($pdf, 0, $lengthObjectOffset)
        . "31 0 obj\n" . strlen($compressedXref) . "\nendobj\n"
        . substr($pdf, $lengthObjectOffset + strlen("31 0 obj\n0\nendobj\n"));

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 32 /Root 1 0 R /Index [1 9 30 2] /W [1 4 1] /Filter 30 0 R /Length 31 0 R >>' . "\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'reviews xref-stream indirect Filter and Length owners before current-base WordPress text extraction' => static function (TestRunner $t) use ($parserXrefStreamFilterLengthOwnerReviewPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserXrefStreamFilterLengthOwnerReviewPdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefStreamFilterLengthOwnerReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $filterOperand = $entry['filter_operands'][0] ?? [];
        $lengthOperand = $entry['length_operand'] ?? [];

        $t->same(['Current xref operand owner page', 'Filter length owners reviewed'], $extractor->extractTextLines($pdf));
        $t->same(['Current xref operand owner page', 'Filter length owners reviewed'], $extractor->extractTextRuns($pdf));
        $t->same("Current xref operand owner page\nFilter length owners reviewed", $text);
        $t->same("Current xref operand owner page\nFilter length owners reviewed\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale xref operand owner page'));

        $t->same('pdf_xref_stream_filter_length_owner_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(1, $review['xref_stream_count']);
        $t->same(1, $review['indirect_filter_count']);
        $t->same(1, $review['indirect_length_count']);
        $t->same(2, $review['xref_selected_operand_count']);
        $t->same(0, $review['unresolved_operand_count']);
        $t->same(20, $entry['object_number']);
        $t->true($entry['startxref_selected']);
        $t->same(['FlateDecode'], $entry['filters']);
        $t->same('xref_selected_indirect_operands', $entry['owner_policy']);
        $t->true($entry['decoded_with_current_operands']);
        $t->same(11, $entry['decoded_entry_count']);
        $t->same('xref_selected_direct_object', $filterOperand['owner_policy']);
        $t->same(30, $filterOperand['object_number']);
        $t->true($filterOperand['xref_selected']);
        $t->same('/FlateDecode', $filterOperand['value_preview']);
        $t->same('xref_selected_direct_object', $lengthOperand['owner_policy']);
        $t->same(31, $lengthOperand['object_number']);
        $t->true($lengthOperand['xref_selected']);
        $t->same((string) $entry['declared_length'], $lengthOperand['value_preview']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
