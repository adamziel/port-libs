<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamOutOfRangeIndexCurrentBasePdf = static function (): string {
    $guardContent = 'BT /F1 12 Tf 72 720 Td (Current out-of-range index guard page) Tj ET';
    $currentCompressedContent = 'BT /F1 12 Tf 72 700 Td (Out-of-range compressed member leak) Tj T* (Explicit bad index ignored) Tj ET';
    $staleDirectContent = 'BT /F1 12 Tf 72 680 Td (Stale direct fallback leak) Tj ET';

    $decoyMember = '<< /Type /Page /Parent 2 0 R /Note (decoy first object stream member) >>';
    $currentMember = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>';
    $header = '12 0 4 ' . strlen($decoyMember . "\n");
    $objectStreamBytes = $header . "\n" . $decoyMember . "\n" . $currentMember . "\n";
    $objectStream = gzcompress($objectStreamBytes);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress out-of-range-index object-stream fixture.');
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
    $addObject(2, 0, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($currentCompressedContent) . " >>\nstream\n{$currentCompressedContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 10 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($staleDirectContent) . " >>\nstream\n{$staleDirectContent}\nendstream");
    $addObject(10, 0, "<< /Length " . strlen($guardContent) . " >>\nstream\n{$guardContent}\nendstream");

    $xrefRows = ''
        . $xrefRow(1, $offsets['1:0'])
        . $xrefRow(1, $offsets['2:0'])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(2, 6, 9)
        . $xrefRow(1, $offsets['5:0'])
        . $xrefRow(1, $offsets['6:0'])
        . $xrefRow(1, $offsets['8:0'])
        . $xrefRow(1, $offsets['9:0'])
        . $xrefRow(1, $offsets['10:0']);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress out-of-range-index xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 3] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rejects explicit object-stream member indexes outside the header range before WordPress text extraction' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamOutOfRangeIndexCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamOutOfRangeIndexCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $entry = $entries[4] ?? [];

        $t->same(['Current out-of-range index guard page'], $extractor->extractTextLines($pdf));
        $t->same(['Current out-of-range index guard page'], $extractor->extractTextRuns($pdf));
        $t->same('Current out-of-range index guard page', $text);
        $t->same("Current out-of-range index guard page\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Out-of-range compressed member leak'));
        $t->true(!str_contains($text, 'Explicit bad index ignored'));
        $t->true(!str_contains($text, 'Stale direct fallback leak'));
        $t->true(!str_contains($text, 'decoy first object stream member'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(1, $review['compressed_entry_count']);
        $t->same(1, $review['strict_dependency_rejection_count']);
        $t->same(1, $review['out_of_range_member_index_rejection_count']);
        $t->same(4, $entry['object_number'] ?? null);
        $t->same(6, $entry['object_stream'] ?? null);
        $t->same(9, $entry['xref_member_index'] ?? null);
        $t->same(1, $entry['actual_member_index'] ?? null);
        $t->same(2, $entry['object_stream_member_count'] ?? null);
        $t->same(1, $entry['matching_header_object_number_count'] ?? null);
        $t->same(false, $entry['strict_member_match'] ?? null);
        $t->same(true, $entry['member_index_out_of_range'] ?? null);
        $t->same(true, $entry['out_of_range_member_index_rejected'] ?? null);
        $t->same('out_of_range_object_stream_member_index', $entry['selection_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
