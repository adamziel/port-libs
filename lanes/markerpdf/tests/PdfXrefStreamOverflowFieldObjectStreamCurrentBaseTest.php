<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefStreamOverflowFieldObjectStreamCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current oversized xref field guard page) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 700 Td (Stale oversized xref field fallback leak) Tj T* (Overflow object stream leak) Tj ET';

    $members = [
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>',
    ];
    $headerPairs = [];
    $memberIndexes = [];
    $objectData = '';
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $objectData .= $body . "\n";
    }

    $header = implode(' ', $headerPairs);
    $objectStream = gzcompress($header . "\n" . $objectData);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress oversized xref-field object-stream fixture.');
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
    $wideSafeField = static fn (int $value): string => "\0\0\0\0" . pack('N', $value);
    $wideXrefRow = static fn (int $type, string $fieldTwo, int $fieldThree = 0): string => chr($type) . $fieldTwo . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(9, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $previousXrefRows = ''
        . $xrefRow(1, $offsets['1:0'])
        . $xrefRow(1, $offsets['2:0'])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(2, 6, $memberIndexes[4])
        . $xrefRow(1, $offsets['6:0'])
        . $xrefRow(1, $offsets['9:0']);
    $previousCompressed = gzcompress($previousXrefRows);
    if (!is_string($previousCompressed)) {
        throw new RuntimeException('Unable to compress previous oversized xref-field stream fixture.');
    }
    $previousXrefOffset = $addObject(
        20,
        0,
        '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 4 6 1 9 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressed) . " >>\nstream\n{$previousCompressed}\nendstream"
    );

    $addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentXrefRows = ''
        . $wideXrefRow(1, $wideSafeField($offsets['1:0']))
        . $wideXrefRow(1, $wideSafeField($offsets['2:0']))
        . $wideXrefRow(1, $wideSafeField($offsets['3:0']))
        . $wideXrefRow(1, $wideSafeField($offsets['4:0']))
        . $wideXrefRow(1, $wideSafeField($offsets['5:0']))
        . $wideXrefRow(1, $wideSafeField($offsets['6:0']))
        . $wideXrefRow(1, $wideSafeField($offsets['9:0']))
        . $wideXrefRow(1, str_repeat("\xff", 8));
    $currentCompressed = gzcompress($currentXrefRows);
    if (!is_string($currentCompressed)) {
        throw new RuntimeException('Unable to compress current oversized xref-field stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [1 6 9 1 12 1] /W [1 8 1] /Filter /FlateDecode /Length ' . strlen($currentCompressed) . " >>\n"
        . "stream\n{$currentCompressed}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rejects overflowing xref-stream row fields before object-stream fallback text' => static function (
        TestRunner $t
    ) use ($xrefStreamOverflowFieldObjectStreamCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefStreamOverflowFieldObjectStreamCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $widthEntry = $review['malformed_xref_stream_width_entries'][0] ?? [];

        $t->same([], $extractor->extractTextLines($pdf));
        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same('', $text);
        $t->same('', $extractor->naiveGetText($pdf));
        $t->same(0, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same([], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Current oversized xref field guard page'));
        $t->true(!str_contains($text, 'Stale oversized xref field fallback leak'));
        $t->true(!str_contains($text, 'Overflow object stream leak'));
        $t->true(!str_contains($text, "\0"));
        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(0, $review['compressed_entry_count']);
        $t->same(1, $review['malformed_xref_stream_width_count']);
        $t->same(0, $review['xref_stream_free_entry_count']);
        $t->same('overflowing_xref_stream_field_value', $widthEntry['owner_policy'] ?? null);
        $t->same([1, 8, 1], $widthEntry['widths'] ?? null);
        $t->same([1], $widthEntry['malformed_width_indexes'] ?? null);
        $t->same(12, $widthEntry['overflow_object_number'] ?? null);
        $t->same(7, $widthEntry['overflow_row_index'] ?? null);
        $t->same(1, $widthEntry['overflow_field_index'] ?? null);
        $t->same(8, $widthEntry['overflow_field_width'] ?? null);
        $t->same(true, $widthEntry['rejected_before_row_decode'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
