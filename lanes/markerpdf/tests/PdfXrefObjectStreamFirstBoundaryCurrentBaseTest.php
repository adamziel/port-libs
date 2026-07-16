<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamFirstBoundaryCurrentBasePdf = static function (): string {
    $directContent = 'BT /F1 12 Tf 72 720 Td (Current First-boundary guard page) Tj ET';
    $leakContent = 'BT /F1 12 Tf 72 700 Td (First-boundary compressed leak) Tj T* (Malformed First member ignored) Tj ET';
    $fakePage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (fake page after malformed First) >>';
    $carrierPrefix = '<< /Type /Catalog /Pages 2 0 R /Note (';
    $carrierSuffix = ') >>';
    $header = '4 0';
    $decodedObjectStream = $header . "\n" . $carrierPrefix . $fakePage . $carrierSuffix . "\n";
    $firstOffset = strlen($header . "\n" . $carrierPrefix);
    $objectStream = gzcompress($decodedObjectStream);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress malformed First object-stream fixture.');
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
    $addObject(5, 0, "<< /Length " . strlen($leakContent) . " >>\nstream\n{$leakContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . $firstOffset . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($directContent) . " >>\nstream\n{$directContent}\nendstream");

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
        throw new RuntimeException('Unable to compress malformed First xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rejects object streams whose First offset points into a member body' => static function (TestRunner $t) use ($xrefObjectStreamFirstBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamFirstBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $entry = $entries[4] ?? [];

        $t->same(['Current First-boundary guard page'], $extractor->extractTextLines($pdf));
        $t->same(['Current First-boundary guard page'], $extractor->extractTextRuns($pdf));
        $t->same('Current First-boundary guard page', $text);
        $t->same("Current First-boundary guard page\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'First-boundary compressed leak'));
        $t->true(!str_contains($text, 'Malformed First member ignored'));
        $t->true(!str_contains($text, 'fake page after malformed First'));
        $t->true(!str_contains($text, "\0"));
        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(1, $review['compressed_entry_count']);
        $t->same(1, $review['strict_dependency_rejection_count']);
        $t->same(0, $entry['object_stream_member_count'] ?? null);
        $t->same(0, $entry['matching_header_object_number_count'] ?? null);
        $t->same('missing_object_stream_member', $entry['selection_policy'] ?? null);
        $t->same('compressed_object_stream_member', $entry['owner_policy'] ?? null);
        $t->true($entry['strict_dependency_would_reject'] ?? false);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
