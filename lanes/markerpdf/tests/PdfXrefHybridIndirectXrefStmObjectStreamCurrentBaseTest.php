<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefHybridIndirectXrefStmObjectStreamCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale indirect XRefStm table page) Tj T* (Hybrid stream offset helper ignored) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current indirect XRefStm object-stream page) Tj T* (Hybrid stream offset helper resolved) Tj ET';

    $members = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
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
        throw new RuntimeException('Unable to compress indirect-XRefStm object-stream fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $fontOffset = $addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $currentContentOffset = $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $carrierOffset = $addObject(
        20,
        0,
        '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream"
    );

    $xrefRows = ''
        . $xrefStreamRow(2, 20, $memberIndexes[1])
        . $xrefStreamRow(2, 20, $memberIndexes[2])
        . $xrefStreamRow(2, 20, $memberIndexes[3])
        . $xrefStreamRow(1, $fontOffset)
        . $xrefStreamRow(1, $currentContentOffset)
        . $xrefStreamRow(1, $carrierOffset);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress indirect-XRefStm xref stream fixture.');
    }

    $xrefStreamOffset = strlen($pdf);
    $pdf .= "21 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /Index [1 5 20 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n";
    $xrefStmHelperOffset = $addObject(30, 0, (string) $xrefStreamOffset);

    $xrefTableOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 1\n"
        . $xrefTableRow(0, 65535, 'f')
        . "4 1\n"
        . $xrefTableRow($fontOffset)
        . "20 2\n"
        . $xrefTableRow($carrierOffset)
        . $xrefTableRow($xrefStreamOffset)
        . "30 1\n"
        . $xrefTableRow($xrefStmHelperOffset)
        . "trailer\n<< /Size 31 /Root 1 0 R /XRefStm 30 0 R >>\n"
        . "startxref\n{$xrefTableOffset}\n%%EOF";

    return $pdf;
};

return [
    'resolves indirect hybrid XRefStm offsets before object-stream member extraction' => static function (
        TestRunner $t
    ) use ($xrefHybridIndirectXrefStmObjectStreamCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefHybridIndirectXrefStmObjectStreamCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');

        $expected = [
            'Current indirect XRefStm object-stream page',
            'Hybrid stream offset helper resolved',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(3, $review['compressed_entry_count']);
        $t->same(20, $entries[1]['object_stream'] ?? null);
        $t->same(20, $entries[2]['object_stream'] ?? null);
        $t->same(20, $entries[3]['object_stream'] ?? null);
        $t->same('xref_selected_object_stream_carrier', $entries[1]['object_stream_owner_policy'] ?? null);
        $t->same('xref_selected_object_stream_carrier', $entries[2]['object_stream_owner_policy'] ?? null);
        $t->same('xref_selected_object_stream_carrier', $entries[3]['object_stream_owner_policy'] ?? null);
        $t->same('explicit_member_index', $entries[1]['selection_policy'] ?? null);
        $t->same('explicit_member_index', $entries[2]['selection_policy'] ?? null);
        $t->same('explicit_member_index', $entries[3]['selection_policy'] ?? null);
        $t->true(str_contains($pdf, '/XRefStm 30 0 R'));
        $t->true(!str_contains($text, 'Stale indirect XRefStm table page'));
        $t->true(!str_contains($text, 'Hybrid stream offset helper ignored'));
        $t->same(0, $review['unresolved_object_stream_carrier_count']);
        $t->same(0, $review['strict_dependency_rejection_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
