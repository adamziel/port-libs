<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserObjectStreamTypeNameEscapeCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Escaped type object stream page) Tj T* (Name escaped xref selected) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Escaped type stale fallback leak) Tj ET';

    $members = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [4 0 R] /Count 1 >>',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
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
        throw new RuntimeException('Unable to compress escaped-type object-stream fixture.');
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

    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Type /Obj#53tm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(8, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $xrefRows = ''
        . $xrefRow(2, 6, $memberIndexes[1])
        . $xrefRow(2, 6, $memberIndexes[2])
        . $xrefRow(2, 6, $memberIndexes[3])
        . $xrefRow(2, 6, $memberIndexes[4])
        . $xrefRow(1, $offsets['5:0'])
        . $xrefRow(1, $offsets['6:0'])
        . $xrefRow(0, 0, 65535);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress escaped-type xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XR#65f /Size 21 /Root 1 0 R /Index [1 6 8 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'decodes escaped xref and object-stream Type names before current-base text extraction' => static function (
        TestRunner $t
    ) use ($parserObjectStreamTypeNameEscapeCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserObjectStreamTypeNameEscapeCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');

        $expected = ['Escaped type object stream page', 'Name escaped xref selected'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Escaped type stale fallback leak'));
        $t->true(!str_contains($text, "\0"));
        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(4, $review['compressed_entry_count']);
        $t->same('xref_selected_object_stream_carrier', $entries[4]['object_stream_owner_policy'] ?? null);
        $t->same('explicit_member_index', $entries[4]['selection_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
