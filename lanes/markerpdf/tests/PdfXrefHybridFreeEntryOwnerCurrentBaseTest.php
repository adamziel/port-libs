<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefHybridFreeEntryOwnerCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale hybrid table direct page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current hybrid free owner page) Tj T* (Hybrid xref stream free row wins) Tj ET';

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
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R 8 0 R] /Count 2 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (stale table row should be freed by hybrid stream) >>');
    $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $hybridRows = $xrefStreamRow(0, 0, 2);
    $hybridXrefStream = gzcompress($hybridRows);
    if (!is_string($hybridXrefStream)) {
        throw new RuntimeException('Unable to compress hybrid free-entry xref stream fixture.');
    }

    $hybridXrefOffset = $addObject(
        20,
        0,
        '<< /Type /XRef /Size 21 /Index [4 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($hybridXrefStream) . " >>\nstream\n{$hybridXrefStream}\nendstream"
    );

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 10\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['1:0'])
        . $xrefTableRow($offsets['2:0'])
        . $xrefTableRow($offsets['3:0'])
        . $xrefTableRow($offsets['4:0'])
        . $xrefTableRow($offsets['5:0'])
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($offsets['8:0'])
        . $xrefTableRow($offsets['9:0'])
        . "trailer\n<< /Size 21 /Root 1 0 R /XRefStm {$hybridXrefOffset} >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'lets current hybrid xref-stream free rows own stale table direct objects before WordPress text extraction' => static function (
        TestRunner $t
    ) use ($xrefHybridFreeEntryOwnerCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefHybridFreeEntryOwnerCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $freeEntry = $review['free_entries'][0] ?? [];

        $t->same(['Current hybrid free owner page', 'Hybrid xref stream free row wins'], $extractor->extractTextLines($pdf));
        $t->same(['Current hybrid free owner page', 'Hybrid xref stream free row wins'], $extractor->extractTextRuns($pdf));
        $t->same("Current hybrid free owner page\nHybrid xref stream free row wins", $text);
        $t->same("Current hybrid free owner page\nHybrid xref stream free row wins\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale hybrid table direct page'));
        $t->true(!str_contains($text, 'stale table row should be freed by hybrid stream'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(0, $review['compressed_entry_count']);
        $t->same(1, $review['xref_stream_free_entry_count']);
        $t->same(1, $review['xref_stream_free_owner_count']);
        $t->same(4, $freeEntry['object_number'] ?? null);
        $t->same(2, $freeEntry['free_generation'] ?? null);
        $t->same(0, $freeEntry['next_free_object'] ?? null);
        $t->same(1, $freeEntry['direct_definition_count'] ?? null);
        $t->true($freeEntry['direct_object_suppressed'] ?? false);
        $t->true($freeEntry['table_entry_suppressed'] ?? false);
        $t->same(1, $freeEntry['table_entry_type'] ?? null);
        $t->same(0, $freeEntry['table_generation'] ?? null);
        $t->true(is_int($freeEntry['hybrid_xref_stream_offset'] ?? null) && ($freeEntry['hybrid_xref_stream_offset'] ?? 0) > 0);
        $t->same('hybrid_xref_stream_free_entry_suppressed_table_direct_object', $freeEntry['owner_policy'] ?? null);
        $t->true($freeEntry['suppressed_by_free_entry'] ?? false);
        $t->true($freeEntry['review_only'] ?? false);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
