<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamDeclaredCountBoundaryCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current declared-count page) Tj T* (Ignored header overrun) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 700 Td (Overrun object-stream page leak) Tj ET';

    $decoyMember = '<< /Type /Review /Note (declared-count first member decoy) >>';
    $stalePage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>';
    $currentPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>';
    $extraOffset = strlen($decoyMember . "\n");
    $currentOffset = strlen($decoyMember . "\n" . $stalePage . "\n");
    $header = '12 0 4 ' . $currentOffset . ' 8 ' . $extraOffset;
    $objectData = $decoyMember . "\n" . $stalePage . "\n" . $currentPage . "\n";
    $objectStream = gzcompress($header . "\n" . $objectData);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress declared-count object-stream fixture.');
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
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R 8 0 R] /Count 2 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(9, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $xrefRows = ''
        . $xrefRow(1, $offsets['1:0'])
        . $xrefRow(1, $offsets['2:0'])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(2, 6, 1)
        . $xrefRow(1, $offsets['5:0'])
        . $xrefRow(1, $offsets['6:0'])
        . $xrefRow(0, 0)
        . $xrefRow(2, 6, 2)
        . $xrefRow(1, $offsets['9:0']);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress declared-count xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 9] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'uses declared object-stream count while ignoring complete overrun header pairs' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamDeclaredCountBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamDeclaredCountBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $currentEntry = $entries[4] ?? [];
        $overrunEntry = $entries[8] ?? [];

        $t->same(['Current declared-count page', 'Ignored header overrun'], $extractor->extractTextLines($pdf));
        $t->same(['Current declared-count page', 'Ignored header overrun'], $extractor->extractTextRuns($pdf));
        $t->same("Current declared-count page\nIgnored header overrun", $text);
        $t->same("Current declared-count page\nIgnored header overrun\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Overrun object-stream page leak'));
        $t->true(!str_contains($text, 'declared-count first member decoy'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(2, $review['compressed_entry_count']);
        $t->same(1, $review['strict_dependency_rejection_count']);
        $t->same(1, $review['out_of_range_member_index_rejection_count']);
        $t->same(4, $currentEntry['object_number'] ?? null);
        $t->same(6, $currentEntry['object_stream'] ?? null);
        $t->same(1, $currentEntry['xref_member_index'] ?? null);
        $t->same(1, $currentEntry['actual_member_index'] ?? null);
        $t->same(2, $currentEntry['object_stream_member_count'] ?? null);
        $t->same(true, $currentEntry['strict_member_match'] ?? null);
        $t->same('explicit_member_index', $currentEntry['selection_policy'] ?? null);
        $t->same(8, $overrunEntry['object_number'] ?? null);
        $t->same(2, $overrunEntry['xref_member_index'] ?? null);
        $t->same(null, $overrunEntry['actual_member_index'] ?? null);
        $t->same(2, $overrunEntry['object_stream_member_count'] ?? null);
        $t->same(true, $overrunEntry['member_index_out_of_range'] ?? null);
        $t->same(true, $overrunEntry['out_of_range_member_index_rejected'] ?? null);
        $t->same('out_of_range_object_stream_member_index', $overrunEntry['selection_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
