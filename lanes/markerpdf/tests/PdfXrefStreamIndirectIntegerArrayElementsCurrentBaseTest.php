<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefStreamIndirectIntegerArrayElementsCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Indirect xref integer array page) Tj T* (Object stream rows selected) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale direct xref integer page) Tj T* (Indirect integer array fallback leak) Tj ET';

    $members = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [4 0 R] /Count 1 >>',
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
    ];
    $objectData = '';
    $headerPairs = [];
    $memberIndexes = [];
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $objectData .= $body . "\n";
    }

    $header = implode(' ', $headerPairs);
    $objectStream = gzcompress($header . "\n" . $objectData);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress indirect xref integer object-stream fixture.');
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

    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(30, 0, '1');
    $addObject(31, 0, '4');
    $addObject(32, 0, '1');
    $addObject(40, 0, '1');
    $addObject(41, 0, '6');

    $xrefRows = ''
        . $xrefRow(2, 6, $memberIndexes[1])
        . $xrefRow(2, 6, $memberIndexes[2])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(2, 6, $memberIndexes[4])
        . $xrefRow(1, $offsets['5:0'])
        . $xrefRow(1, $offsets['6:0']);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress indirect xref integer xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 42 /Root 1 0 R /Index [40 0 R 41 0 R] /W [30 0 R 31 0 R 32 0 R] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF\n";

    $addObject(1, 0, '<< /Type /Catalog /Pages 8 0 R >>');
    $addObject(8, 0, '<< /Type /Pages /Kids [9 0 R] /Count 1 >>');
    $addObject(9, 0, '<< /Type /Page /Parent 8 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 10 0 R >>');
    $addObject(10, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    return $pdf;
};

return [
    'resolves indirect integer elements in xref-stream W and Index arrays before object-stream selection' => static function (
        TestRunner $t
    ) use ($xrefStreamIndirectIntegerArrayElementsCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefStreamIndirectIntegerArrayElementsCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');

        $expected = [
            'Indirect xref integer array page',
            'Object stream rows selected',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale direct xref integer page'));
        $t->true(!str_contains($text, 'Indirect integer array fallback leak'));
        $t->true(!str_contains($text, "\0"));
        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(3, $review['compressed_entry_count']);
        $t->same(0, $review['malformed_xref_stream_width_count']);
        $t->same(0, $review['malformed_xref_stream_index_count']);
        $t->same('explicit_member_index', $entries[1]['selection_policy'] ?? null);
        $t->same('explicit_member_index', $entries[2]['selection_policy'] ?? null);
        $t->same('explicit_member_index', $entries[4]['selection_policy'] ?? null);
        $t->same('xref_selected_object_stream_carrier', $entries[4]['object_stream_owner_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
