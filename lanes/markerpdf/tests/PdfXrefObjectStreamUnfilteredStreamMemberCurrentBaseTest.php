<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamUnfilteredStreamMemberCurrentBasePdf = static function (): string {
    $directContent = 'BT /F1 12 Tf 72 720 Td (Current unfiltered stream-member guard page) Tj ET';
    $compressedContent = 'BT /F1 12 Tf 72 700 Td (Unfiltered stream member leak) Tj T* (Object-stream stream member rejected) Tj ET';

    $compressedStreamObject = "<< /Length " . strlen($compressedContent) . " >>\nstream\n{$compressedContent}\nendstream";
    $objectData = $compressedStreamObject . "\n";
    $header = '5 0';

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
    $addObject(2, 0, '<< /Type /Pages /Kids [8 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Length ' . strlen($header . "\n" . $objectData) . " >>\nstream\n{$header}\n{$objectData}endstream");
    $addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents [9 0 R 5 0 R] >>');
    $addObject(9, 0, "<< /Length " . strlen($directContent) . " >>\nstream\n{$directContent}\nendstream");

    $xrefRows = ''
        . $xrefRow(1, $offsets['1:0'])
        . $xrefRow(1, $offsets['2:0'])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(2, 6, 0)
        . $xrefRow(1, $offsets['6:0'])
        . $xrefRow(1, $offsets['8:0'])
        . $xrefRow(1, $offsets['9:0']);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress unfiltered stream-member xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 3 5 2 8 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rejects stream-object members inside unfiltered xref-selected object streams before WordPress text extraction' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamUnfilteredStreamMemberCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamUnfilteredStreamMemberCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');

        $t->same(['Current unfiltered stream-member guard page'], $extractor->extractTextLines($pdf));
        $t->same(['Current unfiltered stream-member guard page'], $extractor->extractTextRuns($pdf));
        $t->same('Current unfiltered stream-member guard page', $text);
        $t->same("Current unfiltered stream-member guard page\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Unfiltered stream member leak'));
        $t->true(!str_contains($text, 'Object-stream stream member rejected'));
        $t->true(!str_contains($text, "\0"));
        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(1, $review['compressed_entry_count']);
        $t->same(1, $review['stream_member_rejection_count']);
        $t->same('explicit_member_index', $entries[5]['selection_policy'] ?? null);
        $t->same(1, $entries[5]['object_stream_member_count'] ?? null);
        $t->same(false, $entries[5]['object_stream_carrier_has_filter'] ?? null);
        $t->same(true, $entries[5]['object_stream_member_is_stream'] ?? null);
        $t->same(true, $entries[5]['stream_member_rejected'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
