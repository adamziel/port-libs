<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamOmittedGraphCompressedOperandCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale compressed operand graph page) Tj T* (Previous helper rows leaked) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current compressed operand graph page) Tj T* (Compressed N First helpers repaired) Tj ET';

    $pdf = "%PDF-1.7\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset,
        $generation,
        $state
    );
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);
    $objectStream = static function (array $members): array {
        $headerPairs = [];
        $indexes = [];
        $body = '';
        foreach ($members as $objectNumber => $memberBody) {
            $headerPairs[] = $objectNumber . ' ' . strlen($body);
            $indexes[$objectNumber] = count($indexes);
            $body .= $memberBody . "\n";
        }

        $header = implode(' ', $headerPairs);
        $compressed = gzcompress($header . "\n" . $body);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress omitted graph compressed-operand object stream fixture.');
        }

        return [
            'count' => count($members),
            'first' => strlen($header) + 1,
            'indexes' => $indexes,
            'content' => $compressed,
        ];
    };

    $stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 6\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($stalePagesOffset)
        . $xrefTableRow($stalePageOffset)
        . $xrefTableRow($staleContentOffset)
        . $xrefTableRow($fontOffset)
        . "trailer\n<< /Size 6 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $currentCarrier = $objectStream([
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
    ]);
    $helperCarrier = $objectStream([
        90 => (string) $currentCarrier['count'],
        91 => (string) $currentCarrier['first'],
    ]);
    $currentCarrierOffset = $addObject(
        20,
        0,
        '<< /Type /ObjStm /N 90 0 R /First 91 0 R /Filter /FlateDecode /Length ' . strlen($currentCarrier['content']) . " >>\nstream\n{$currentCarrier['content']}\nendstream"
    );
    $helperCarrierOffset = $addObject(
        21,
        0,
        '<< /Type /ObjStm /N ' . $helperCarrier['count'] . ' /First ' . $helperCarrier['first'] . ' /Filter /FlateDecode /Length ' . strlen($helperCarrier['content']) . " >>\nstream\n{$helperCarrier['content']}\nendstream"
    );

    $currentRows = ''
        . $xrefStreamRow(2, 20, $currentCarrier['indexes'][1])
        . $xrefStreamRow(1, $fontOffset, 0)
        . $xrefStreamRow(1, $currentCarrierOffset, 0)
        . $xrefStreamRow(1, $helperCarrierOffset, 0)
        . $xrefStreamRow(2, 21, $helperCarrier['indexes'][90])
        . $xrefStreamRow(2, 21, $helperCarrier['indexes'][91]);
    $compressedRows = gzcompress($currentRows);
    if (!is_string($compressedRows)) {
        throw new RuntimeException('Unable to compress omitted graph compressed-operand xref-stream rows.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 92 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [1 1 5 1 20 2 90 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
        . "stream\n{$compressedRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'repairs omitted object-stream graph rows whose carrier N and First are compressed helpers' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamOmittedGraphCompressedOperandCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamOmittedGraphCompressedOperandCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');

        $expected = ['Current compressed operand graph page', 'Compressed N First helpers repaired'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(5, $review['compressed_entry_count']);
        $t->same('xref_selected_object_stream_carrier', $entries[2]['object_stream_owner_policy'] ?? null);
        $t->same('explicit_member_index', $entries[2]['selection_policy'] ?? null);
        $t->same('xref_selected_object_stream_carrier', $entries[3]['object_stream_owner_policy'] ?? null);
        $t->same('explicit_member_index', $entries[3]['selection_policy'] ?? null);
        $t->same('xref_selected_object_stream_carrier', $entries[90]['object_stream_owner_policy'] ?? null);
        $t->same('explicit_member_index', $entries[90]['selection_policy'] ?? null);
        $t->same('xref_selected_object_stream_carrier', $entries[91]['object_stream_owner_policy'] ?? null);
        $t->same('explicit_member_index', $entries[91]['selection_policy'] ?? null);
        $t->true(!str_contains($text, 'Stale compressed operand graph page'));
        $t->true(!str_contains($text, 'Previous helper rows leaked'));
        $t->true(str_contains($pdf, '/N 90 0 R /First 91 0 R'));
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
