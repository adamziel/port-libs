<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamIndirectWrapperCurrentBasePdf = static function (): string {
    $guardContent = 'BT /F1 12 Tf 72 720 Td (Current indirect-wrapper guard page) Tj ET';
    $wrappedContent = 'BT /F1 12 Tf 72 700 Td (Wrapped object-stream page leak) Tj T* (Indirect obj wrapper rejected) Tj ET';

    $wrappedPage = "4 0 obj\n"
        . '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>'
        . "\nendobj";
    $header = '4 0';
    $objectStream = gzcompress($header . "\n" . $wrappedPage . "\n");
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress indirect-wrapper object-stream fixture.');
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
    $addObject(5, 0, "<< /Length " . strlen($wrappedContent) . " >>\nstream\n{$wrappedContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($guardContent) . " >>\nstream\n{$guardContent}\nendstream");

    $xrefRows = ''
        . $xrefRow(1, $offsets['1:0'])
        . $xrefRow(1, $offsets['2:0'])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(2, 6, 0)
        . $xrefRow(1, $offsets['5:0'])
        . $xrefRow(1, $offsets['6:0'])
        . $xrefRow(1, $offsets['8:0'])
        . $xrefRow(1, $offsets['9:0']);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress indirect-wrapper xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 7 9 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rejects indirect object wrappers inside xref-selected object streams before WordPress text extraction' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamIndirectWrapperCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamIndirectWrapperCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $entry = $entries[4] ?? [];

        $t->same(['Current indirect-wrapper guard page'], $extractor->extractTextLines($pdf));
        $t->same(['Current indirect-wrapper guard page'], $extractor->extractTextRuns($pdf));
        $t->same('Current indirect-wrapper guard page', $text);
        $t->same("Current indirect-wrapper guard page\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Wrapped object-stream page leak'));
        $t->true(!str_contains($text, 'Indirect obj wrapper rejected'));
        $t->true(!str_contains($text, "\0"));
        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(1, $review['compressed_entry_count']);
        $t->same(1, $review['indirect_member_wrapper_rejection_count'] ?? null);
        $t->same(1, $entry['object_stream_member_count'] ?? null);
        $t->same(0, $entry['xref_member_index'] ?? null);
        $t->same(0, $entry['actual_member_index'] ?? null);
        $t->same(true, $entry['strict_member_match'] ?? null);
        $t->same(true, $entry['indirect_object_wrapper_member'] ?? null);
        $t->same(true, $entry['indirect_object_wrapper_rejected'] ?? null);
        $t->same('indirect_object_wrapper_member', $entry['selection_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
