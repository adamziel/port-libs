<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefHybridObjectStreamFreeOwnerCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale hybrid free-owner object stream page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current hybrid free owner page) Tj T* (Companion type two row suppressed) Tj ET';

    $members = [
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (hybrid companion member suppressed by current free row) >>',
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
        throw new RuntimeException('Unable to compress hybrid free-owner object-stream fixture.');
    }

    $hybridRows = chr(2) . chr(6) . chr($memberIndexes[4]);
    $hybridXrefStream = gzcompress($hybridRows);
    if (!is_string($hybridXrefStream)) {
        throw new RuntimeException('Unable to compress hybrid free-owner xref-stream fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R 8 0 R] /Count 2 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $hybridXrefOffset = $addObject(7, 0, '<< /Type /XRef /Size 10 /Index [4 1] /W [1 1 1] /Filter /FlateDecode /Length ' . strlen($hybridXrefStream) . " >>\nstream\n{$hybridXrefStream}\nendstream");
    $addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 10\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['1:0'])
        . $xrefTableRow($offsets['2:0'])
        . $xrefTableRow($offsets['3:0'])
        . $xrefTableRow(0, 2, 'f')
        . $xrefTableRow($offsets['5:0'])
        . $xrefTableRow($offsets['6:0'])
        . $xrefTableRow($offsets['7:0'])
        . $xrefTableRow($offsets['8:0'])
        . $xrefTableRow($offsets['9:0'])
        . "trailer\n<< /Size 10 /Root 1 0 R /XRefStm {$hybridXrefOffset} >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'exposes current hybrid table free owners before suppressed companion object-stream rows' => static function (TestRunner $t) use ($xrefHybridObjectStreamFreeOwnerCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefHybridObjectStreamFreeOwnerCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $suppressed = $review['suppressed_hybrid_entries'][0] ?? [];

        $t->same(['Current hybrid free owner page', 'Companion type two row suppressed'], $extractor->extractTextLines($pdf));
        $t->same(['Current hybrid free owner page', 'Companion type two row suppressed'], $extractor->extractTextRuns($pdf));
        $t->same("Current hybrid free owner page\nCompanion type two row suppressed", $text);
        $t->same("Current hybrid free owner page\nCompanion type two row suppressed\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale hybrid free-owner object stream page'));
        $t->true(!str_contains($text, 'hybrid companion member suppressed by current free row'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(0, $review['compressed_entry_count']);
        $t->same(1, $review['suppressed_hybrid_type2_entry_count']);
        $t->same(1, $review['hybrid_table_free_owner_count']);
        $t->same(4, $suppressed['object_number'] ?? null);
        $t->same(6, $suppressed['object_stream'] ?? null);
        $t->same(0, $suppressed['xref_member_index'] ?? null);
        $t->same(0, $suppressed['table_entry_type'] ?? null);
        $t->same(2, $suppressed['table_generation'] ?? null);
        $t->same('hybrid_table_free_entry_preserved', $suppressed['owner_policy'] ?? null);
        $t->true($suppressed['suppressed_by_table_entry'] ?? false);
        $t->true($suppressed['review_only'] ?? false);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
