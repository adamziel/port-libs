<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamStaleCarrierReplacementCurrentBasePdf = static function (): string {
    $guardContent = 'BT /F1 12 Tf 72 720 Td (Current carrier replacement guard page) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 700 Td (Stale resurrected carrier page leak) Tj T* (Old ObjStm must stay suppressed) Tj ET';
    $compressedPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (stale carrier replacement page) >>';

    $header = '4 0';
    $objectStream = gzcompress($header . "\n" . $compressedPage . "\n");
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress stale carrier replacement object stream fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation][] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(6, 0, '<< /Type /Metadata /Subtype /XML /Note (new non object-stream carrier replacement) >>');
    $addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($guardContent) . " >>\nstream\n{$guardContent}\nendstream");

    $xrefRows = ''
        . $xrefRow(1, $offsets['1:0'][0])
        . $xrefRow(1, $offsets['2:0'][0])
        . $xrefRow(1, $offsets['3:0'][0])
        . $xrefRow(2, 6, 0)
        . $xrefRow(1, $offsets['5:0'][0])
        . $xrefRow(2, 6, 0)
        . $xrefRow(1, $offsets['8:0'][0])
        . $xrefRow(1, $offsets['9:0'][0]);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress stale carrier replacement xref rows.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'does not resurrect an older object-stream carrier after a same-number direct replacement' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamStaleCarrierReplacementCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamStaleCarrierReplacementCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $entry = $entries[4] ?? [];

        $t->same(['Current carrier replacement guard page'], $extractor->extractTextLines($pdf));
        $t->same(['Current carrier replacement guard page'], $extractor->extractTextRuns($pdf));
        $t->same('Current carrier replacement guard page', $text);
        $t->same("Current carrier replacement guard page\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale resurrected carrier page leak'));
        $t->true(!str_contains($text, 'Old ObjStm must stay suppressed'));
        $t->true(!str_contains($text, 'stale carrier replacement page'));
        $t->true(!str_contains($text, 'new non object-stream carrier replacement'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(2, $review['compressed_entry_count']);
        $t->same(2, $review['unresolved_object_stream_carrier_count']);
        $t->same(4, $entry['object_number'] ?? null);
        $t->same(6, $entry['object_stream'] ?? null);
        $t->same(false, $entry['object_stream_carrier_resolved'] ?? null);
        $t->same('compressed_object_stream_carrier_unavailable', $entry['object_stream_owner_policy'] ?? null);
        $t->same('missing_object_stream_member', $entry['selection_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
