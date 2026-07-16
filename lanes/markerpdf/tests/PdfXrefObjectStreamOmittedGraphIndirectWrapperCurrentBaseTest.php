<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamOmittedGraphIndirectWrapperCurrentBasePdf = static function (): string {
    $guardContent = 'BT /F1 12 Tf 72 720 Td (Current wrapper guard page) Tj ET';
    $leakContent = 'BT /F1 12 Tf 72 700 Td (Wrapped omitted graph leak) Tj T* (Indirect wrapper graph ignored) Tj ET';

    $pdf = "%PDF-1.7\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
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
            throw new RuntimeException('Unable to compress omitted graph indirect-wrapper object stream fixture.');
        }

        return [
            'count' => count($members),
            'first' => strlen($header) + 1,
            'indexes' => $indexes,
            'content' => $compressed,
        ];
    };

    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset,
        $generation,
        $state
    );

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 1\n"
        . $xrefTableRow(0, 65535, 'f')
        . "trailer\n<< /Size 1 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $guardPageOffset = $addObject(8, 0, '<< /Type /Page /Resources << /Font << /F1 5 0 R >> >> /Contents 9 0 R >>');
    $guardContentOffset = $addObject(9, 0, "<< /Length " . strlen($guardContent) . " >>\nstream\n{$guardContent}\nendstream");
    $leakContentOffset = $addObject(10, 0, "<< /Length " . strlen($leakContent) . " >>\nstream\n{$leakContent}\nendstream");
    $carrier = $objectStream([
        1 => '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 10 0 R >>',
    ]);
    $carrierOffset = $addObject(
        20,
        0,
        '<< /Type /ObjStm /N ' . $carrier['count'] . ' /First ' . $carrier['first'] . ' /Filter /FlateDecode /Length ' . strlen($carrier['content']) . " >>\nstream\n{$carrier['content']}\nendstream"
    );

    $currentRows = ''
        . $xrefStreamRow(2, 20, $carrier['indexes'][1])
        . $xrefStreamRow(1, $fontOffset, 0)
        . $xrefStreamRow(1, $guardPageOffset, 0)
        . $xrefStreamRow(1, $guardContentOffset, 0)
        . $xrefStreamRow(1, $leakContentOffset, 0)
        . $xrefStreamRow(1, $carrierOffset, 0);
    $compressedRows = gzcompress($currentRows);
    if (!is_string($compressedRows)) {
        throw new RuntimeException('Unable to compress omitted graph indirect-wrapper xref-stream rows.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [1 1 5 1 8 3 20 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
        . "stream\n{$compressedRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rejects indirect-object wrapper members before omitted graph repair expands page trees' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamOmittedGraphIndirectWrapperCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamOmittedGraphIndirectWrapperCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $entry = $entries[1] ?? [];

        $t->same(['Current wrapper guard page'], $extractor->extractTextLines($pdf));
        $t->same(['Current wrapper guard page'], $extractor->extractTextRuns($pdf));
        $t->same('Current wrapper guard page', $text);
        $t->same("Current wrapper guard page\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Wrapped omitted graph leak'));
        $t->true(!str_contains($text, 'Indirect wrapper graph ignored'));
        $t->true(!str_contains($text, '1 0 obj'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(1, $review['compressed_entry_count']);
        $t->same(1, $review['indirect_member_wrapper_rejection_count']);
        $t->same(1, $entry['object_number'] ?? null);
        $t->same(20, $entry['object_stream'] ?? null);
        $t->same(0, $entry['xref_member_index'] ?? null);
        $t->same(0, $entry['actual_member_index'] ?? null);
        $t->same(true, $entry['indirect_object_wrapper_rejected'] ?? null);
        $t->same('indirect_object_wrapper_member', $entry['selection_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
