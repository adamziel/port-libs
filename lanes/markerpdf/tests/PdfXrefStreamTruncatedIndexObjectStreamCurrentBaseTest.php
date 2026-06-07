<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefStreamTruncatedIndexObjectStreamCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Truncated xref index page leak) Tj T* (Partial xref rows trusted) Tj ET';

    $compressedPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>';
    $objectStreamHeader = '4 0';
    $objectStream = gzcompress($objectStreamHeader . "\n" . $compressedPage . "\n");
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress truncated-index object-stream fixture.');
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

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($objectStreamHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

    $xrefRows = ''
        . $xrefRow(1, $offsets['1:0'])
        . $xrefRow(1, $offsets['2:0'])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(2, 6, 0)
        . $xrefRow(1, $offsets['5:0']);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress truncated-index xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'fails closed when xref-stream Index declares more rows than decoded before object-stream expansion' => static function (
        TestRunner $t
    ) use ($xrefStreamTruncatedIndexObjectStreamCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefStreamTruncatedIndexObjectStreamCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $alignmentEntry = $review['malformed_xref_stream_row_alignment_entries'][0] ?? [];

        $t->same([], $extractor->extractTextLines($pdf));
        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same('', $text);
        $t->same('', $extractor->naiveGetText($pdf));
        $t->same(0, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same([], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Truncated xref index page leak'));
        $t->true(!str_contains($text, 'Partial xref rows trusted'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(0, $review['compressed_entry_count']);
        $t->same(1, $review['malformed_xref_stream_row_alignment_count'] ?? null);
        $t->same('truncated_xref_stream_index_rows', $alignmentEntry['owner_policy'] ?? null);
        $t->same(5, $alignmentEntry['decoded_entry_count'] ?? null);
        $t->same(6, $alignmentEntry['expected_entry_count'] ?? null);
        $t->same(0, $alignmentEntry['trailing_byte_count'] ?? null);
        $t->same(true, $alignmentEntry['rejected_before_row_decode'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
