<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefHybridXrefStmWhitespaceObjectStreamCurrentBasePdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Current whitespace XRefStm object-stream page) Tj T* (Hybrid xref stream rows parsed) Tj ET';

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
        throw new RuntimeException('Unable to compress whitespace-XRefStm object-stream fixture.');
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
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

    $xrefRows = ''
        . $xrefStreamRow(2, 6, $memberIndexes[1])
        . $xrefStreamRow(2, 6, $memberIndexes[2])
        . $xrefStreamRow(1, $offsets['3:0'])
        . $xrefStreamRow(2, 6, $memberIndexes[4])
        . $xrefStreamRow(1, $offsets['5:0'])
        . $xrefStreamRow(1, $offsets['6:0']);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress whitespace-XRefStm xref stream fixture.');
    }

    $xrefStreamWhitespaceOffset = strlen($pdf);
    $pdf .= "\n  \t";
    $xrefStreamObjectOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n";

    $xrefTableOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 1\n"
        . $xrefTableRow(0, 65535, 'f')
        . "3 1\n"
        . $xrefTableRow($offsets['3:0'])
        . "5 2\n"
        . $xrefTableRow($offsets['5:0'])
        . $xrefTableRow($offsets['6:0'])
        . "20 1\n"
        . $xrefTableRow($xrefStreamObjectOffset)
        . "trailer\n<< /Size 21 /Root 1 0 R /XRefStm {$xrefStreamWhitespaceOffset} >>\n"
        . "startxref\n{$xrefTableOffset}\n%%EOF";

    return $pdf;
};

return [
    'normalizes hybrid XRefStm whitespace offsets before object-stream member extraction' => static function (
        TestRunner $t
    ) use ($xrefHybridXrefStmWhitespaceObjectStreamCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefHybridXrefStmWhitespaceObjectStreamCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');

        $expected = [
            'Current whitespace XRefStm object-stream page',
            'Hybrid xref stream rows parsed',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(3, $review['compressed_entry_count']);
        $t->same('explicit_member_index', $entries[1]['selection_policy'] ?? null);
        $t->same('explicit_member_index', $entries[2]['selection_policy'] ?? null);
        $t->same('explicit_member_index', $entries[4]['selection_policy'] ?? null);
        $t->same('xref_selected_object_stream_carrier', $entries[4]['object_stream_owner_policy'] ?? null);
        $t->same(0, $review['unresolved_object_stream_carrier_count']);
        $t->same(0, $review['strict_dependency_rejection_count']);
        $t->true(str_contains($pdf, '/XRefStm '));
        $t->true(!str_contains($text, "\0"));
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
