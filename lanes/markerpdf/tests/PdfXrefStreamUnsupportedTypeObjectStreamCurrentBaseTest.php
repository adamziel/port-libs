<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefStreamUnsupportedTypeObjectStreamCurrentBasePdf = static function (): string {
    $staleCompressedContent = 'BT /F1 12 Tf 72 720 Td (Stale unsupported-type compressed page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current unsupported-type guard page) Tj T* (Unknown xref row suppresses stale member) Tj ET';

    $compressedPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (unsupported type stale compressed page dictionary) >>';
    $header = '4 0';
    $objectStream = gzcompress($header . "\n" . $compressedPage . "\n");
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress unsupported-type object-stream fixture.');
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
    $addObject(5, 0, "<< /Length " . strlen($staleCompressedContent) . " >>\nstream\n{$staleCompressedContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

    $previousRows = ''
        . $xrefRow(1, $offsets['1:0'], 0)
        . $xrefRow(1, $offsets['2:0'], 0)
        . $xrefRow(1, $offsets['3:0'], 0)
        . $xrefRow(2, 6, 0)
        . $xrefRow(1, $offsets['5:0'], 0)
        . $xrefRow(1, $offsets['6:0'], 0);
    $previousCompressedXref = gzcompress($previousRows);
    if (!is_string($previousCompressedXref)) {
        throw new RuntimeException('Unable to compress previous unsupported-type xref fixture.');
    }

    $previousXrefOffset = $addObject(
        20,
        0,
        '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressedXref) . " >>\nstream\n{$previousCompressedXref}\nendstream"
    );
    $pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
    $addObject(8, 0, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentRows = ''
        . $xrefRow(1, $offsets['1:1'], 1)
        . $xrefRow(1, $offsets['2:1'], 1)
        . $xrefRow(3, 0, 0)
        . $xrefRow(1, $offsets['8:0'], 0)
        . $xrefRow(1, $offsets['9:0'], 0);
    $currentCompressedXref = gzcompress($currentRows);
    if (!is_string($currentCompressedXref)) {
        throw new RuntimeException('Unable to compress current unsupported-type xref fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "21 0 obj\n"
        . '<< /Type /XRef /Size 22 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 2 4 1 8 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentCompressedXref) . " >>\n"
        . "stream\n{$currentCompressedXref}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'treats unsupported xref-stream entry types as current null owners before object-stream Prev replay' => static function (
        TestRunner $t
    ) use ($xrefStreamUnsupportedTypeObjectStreamCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefStreamUnsupportedTypeObjectStreamCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $freeEntry = $review['free_entries'][0] ?? [];

        $expected = [
            'Current unsupported-type guard page',
            'Unknown xref row suppresses stale member',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale unsupported-type compressed page'));
        $t->true(!str_contains($text, 'unsupported type stale compressed page dictionary'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(0, $review['compressed_entry_count']);
        $t->same(1, $review['xref_stream_free_entry_count']);
        $t->same(1, $review['xref_stream_free_owner_count']);
        $t->same(4, $freeEntry['object_number'] ?? null);
        $t->same(2, $freeEntry['previous_entry_type'] ?? null);
        $t->same(6, $freeEntry['previous_object_stream'] ?? null);
        $t->same(0, $freeEntry['previous_member_index'] ?? null);
        $t->same(true, $freeEntry['previous_entry_suppressed'] ?? null);
        $t->same('xref_stream_free_entry_suppressed_prev_compressed_object', $freeEntry['owner_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
