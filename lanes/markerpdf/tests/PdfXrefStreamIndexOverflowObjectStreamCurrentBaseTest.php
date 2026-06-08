<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefStreamIndexOverflowObjectStreamCurrentBasePdf = static function (): string {
    $hugeObjectNumber = '922337203685477580899';
    $leakingContent = 'BT /F1 12 Tf 72 720 Td (Oversized Index object-stream root leak) Tj T* (Integer alias page imported) Tj ET';
    $compressedCatalog = '<< /Type /Catalog /Pages 2 0 R /ViewerPreferences << /DisplayDocTitle true >> >>';
    $header = $hugeObjectNumber . ' 0';
    $objectStream = gzcompress($header . "\n" . $compressedCatalog . "\n");
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress overflow xref-index object stream.');
    }

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($leakingContent) . " >>\nstream\n{$leakingContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

    $compressedXref = gzcompress($xrefRow(2, 6, 0));
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress overflow xref-index rows.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root ' . $hugeObjectNumber . ' 0 R /Index [' . $hugeObjectNumber . ' 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rejects overflowing xref-stream Index object numbers before object-stream root aliasing' => static function (
        TestRunner $t
    ) use ($xrefStreamIndexOverflowObjectStreamCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefStreamIndexOverflowObjectStreamCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $indexEntry = $review['malformed_xref_stream_index_entries'][0] ?? [];

        $t->same([], $extractor->extractTextLines($pdf));
        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same('', $text);
        $t->same('', $extractor->naiveGetText($pdf));
        $t->same(0, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same([], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Oversized Index object-stream root leak'));
        $t->true(!str_contains($text, 'Integer alias page imported'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(0, $review['compressed_entry_count']);
        $t->same(1, $review['malformed_xref_stream_index_count']);
        $t->same('non_integer_xref_stream_index_value', $indexEntry['owner_policy'] ?? null);
        $t->same([1], $indexEntry['indexes'] ?? null);
        $t->same([0], $indexEntry['malformed_index_indexes'] ?? null);
        $t->same(true, $indexEntry['rejected_before_row_decode'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
