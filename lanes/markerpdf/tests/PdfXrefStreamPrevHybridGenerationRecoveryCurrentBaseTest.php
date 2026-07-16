<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefStreamPrevHybridGenerationRecoveryCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale previous hybrid carrier page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current stream Prev hybrid page) Tj T* (Hybrid carrier generation recovered) Tj ET';
    $previousCompressedContent = 'BT /F1 12 Tf 72 720 Td (Previous hybrid compressed page recovered) Tj ET';

    $previousMembers = [
        4 => '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 10 0 R /Note (previous hybrid compressed member) >>',
    ];
    $previousObjectData = '';
    $previousHeaderPairs = [];
    $previousMemberIndexes = [];
    foreach ($previousMembers as $objectNumber => $body) {
        $previousHeaderPairs[] = $objectNumber . ' ' . strlen($previousObjectData);
        $previousMemberIndexes[$objectNumber] = count($previousMemberIndexes);
        $previousObjectData .= $body . "\n";
    }

    $previousHeader = implode(' ', $previousHeaderPairs);
    $previousObjectStream = gzcompress($previousHeader . "\n" . $previousObjectData);
    if (!is_string($previousObjectStream)) {
        throw new RuntimeException('Unable to compress previous hybrid object stream fixture.');
    }

    $previousHybridRows = chr(2) . chr(6) . chr($previousMemberIndexes[4]);
    $previousHybridXrefStream = gzcompress($previousHybridRows);
    if (!is_string($previousHybridXrefStream)) {
        throw new RuntimeException('Unable to compress previous hybrid xref-stream fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [8 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($previousHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($previousObjectStream) . " >>\nstream\n{$previousObjectStream}\nendstream");
    $previousHybridXrefOffset = $addObject(7, 0, '<< /Type /XRef /Size 11 /Index [4 1] /W [1 1 1] /Filter /FlateDecode /Length ' . strlen($previousHybridXrefStream) . " >>\nstream\n{$previousHybridXrefStream}\nendstream");
    $addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>');
    $addObject(10, 0, "<< /Length " . strlen($previousCompressedContent) . " >>\nstream\n{$previousCompressedContent}\nendstream");

    $previousTableOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 4\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['1:0'])
        . $xrefTableRow($offsets['2:0'])
        . $xrefTableRow($offsets['3:0'])
        . "5 4\n"
        . $xrefTableRow($offsets['5:0'])
        . $xrefTableRow($offsets['6:0'])
        . $xrefTableRow($offsets['7:0'])
        . $xrefTableRow($offsets['8:0'])
        . "10 1\n"
        . $xrefTableRow($offsets['10:0'])
        . "trailer\n<< /Size 11 /Root 1 0 R /XRefStm {$previousHybridXrefOffset} >>\n"
        . "startxref\n{$previousTableOffset}\n%%EOF\n";

    $currentCatalogOffset = $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
    $currentPagesOffset = $addObject(2, 1, '<< /Type /Pages /Kids [9 0 R 4 0 R] /Count 2 >>');
    $currentPageOffset = $addObject(9, 0, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 11 0 R >>');
    $currentContentOffset = $addObject(11, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentRows = ''
        . $xrefStreamRow(1, $currentCatalogOffset, 1)
        . $xrefStreamRow(1, $currentPagesOffset, 1)
        . $xrefStreamRow(1, 0, 1)
        . $xrefStreamRow(1, $currentPageOffset, 0)
        . $xrefStreamRow(1, $currentContentOffset, 0);
    $currentXrefStream = gzcompress($currentRows);
    if (!is_string($currentXrefStream)) {
        throw new RuntimeException('Unable to compress current xref stream fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "21 0 obj\n"
        . '<< /Type /XRef /Size 22 /Root 1 1 R /Prev ' . $previousTableOffset . ' /Index [1 2 6 1 9 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentXrefStream) . " >>\n"
        . "stream\n{$currentXrefStream}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'recovers Prev hybrid object-stream members when current xref-stream carrier row has generation noise' => static function (
        TestRunner $t
    ) use ($xrefStreamPrevHybridGenerationRecoveryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefStreamPrevHybridGenerationRecoveryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefPrevObjectStreamGenerationReview($pdf);

        $t->same(['Current stream Prev hybrid page', 'Hybrid carrier generation recovered', 'Previous hybrid compressed page recovered'], $extractor->extractTextLines($pdf));
        $t->same(['Current stream Prev hybrid page', 'Hybrid carrier generation recovered', 'Previous hybrid compressed page recovered'], $extractor->extractTextRuns($pdf));
        $t->same("Current stream Prev hybrid page\nHybrid carrier generation recovered\nPrevious hybrid compressed page recovered", $text);
        $t->same("Current stream Prev hybrid page\nHybrid carrier generation recovered\nPrevious hybrid compressed page recovered\n", $extractor->naiveGetText($pdf));
        $t->same(2, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1', '2'], $extractor->extractPageLabels($pdf));
        $t->same('pdf_xref_prev_object_stream_generation_review', $review['source']);
        $t->same(1, $review['inherited_type2_entry_count']);
        $t->same(1, $review['preserved_type2_entry_count']);
        $t->same(0, $review['skipped_type2_entry_count']);
        $t->same(1, $review['same_carrier_storage_count']);
        $t->same(true, $review['entries'][0]['current_carrier_invalid_generation_recovered'] ?? null);
        $t->same('preserved_previous_carrier_after_invalid_current_generation', $review['entries'][0]['owner_policy'] ?? null);
        $t->true(!str_contains($text, 'Stale previous hybrid carrier page'));
        $t->true(!str_contains($text, 'previous hybrid compressed member'));
        $t->true(!str_contains($text, "\0"));
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
