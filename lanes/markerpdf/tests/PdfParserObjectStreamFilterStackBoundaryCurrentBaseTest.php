<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$objectStreamFilterStackBoundaryAscii85 = static function (string $bytes): string {
    $encoded = '';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $chunkLength = strlen($chunk);
        if ($chunkLength < 4) {
            $chunk = str_pad($chunk, 4, "\0");
        }

        $value = unpack('N', $chunk)[1];
        if ($value === 0 && $chunkLength === 4) {
            $encoded .= 'z';
            continue;
        }

        $chars = '';
        for ($index = 0; $index < 5; $index++) {
            $chars = chr(($value % 85) + 33) . $chars;
            $value = intdiv($value, 85);
        }
        $encoded .= substr($chars, 0, $chunkLength + 1);
    }

    return $encoded;
};

$objectStreamFilterStackBoundaryPdf = static function () use ($objectStreamFilterStackBoundaryAscii85): string {
    $directContent = 'BT /F1 12 Tf 72 720 Td (Direct object-stream boundary guard page) Tj ET';
    $compressedContent = 'BT /F1 12 Tf 72 700 Td (Tailed object-stream compressed page leak) Tj ET';
    $compressedPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>';
    $memberHeader = '4 0';
    $objectStreamBytes = $memberHeader . "\n" . $compressedPage . "\n";
    $compressedObjectStream = gzcompress($objectStreamBytes);
    if (!is_string($compressedObjectStream)) {
        throw new RuntimeException('Unable to compress focused object-stream filter-stack boundary fixture.');
    }

    $tailedObjectStream = $objectStreamFilterStackBoundaryAscii85($compressedObjectStream)
        . '~>BT /F1 12 Tf 72 680 Td (Post EOD object-stream tail leak) Tj ET';

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($compressedContent) . " >>\nstream\n{$compressedContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($memberHeader) + 1)
        . ' /Filter [ /ASCII85Decode /FlateDecode ] /Length ' . strlen($tailedObjectStream)
        . " >>\nstream\n{$tailedObjectStream}\nendstream");
    $addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($directContent) . " >>\nstream\n{$directContent}\nendstream");

    $xrefRows = ''
        . $xrefRow(1, $offsets['1:0'])
        . $xrefRow(1, $offsets['2:0'])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(2, 6, 0)
        . $xrefRow(1, $offsets['5:0'])
        . $xrefRow(1, $offsets['6:0'])
        . $xrefRow(1, $offsets['8:0'])
        . $xrefRow(1, $offsets['9:0']);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress focused xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rejects non-whitespace bytes after object-stream filter EOD before xref member expansion' => static function (
        TestRunner $t
    ) use ($objectStreamFilterStackBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $objectStreamFilterStackBoundaryPdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $entry = $entries[4] ?? [];

        $expected = ['Direct object-stream boundary guard page'];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->true(!str_contains($text, 'Tailed object-stream compressed page leak'));
        $t->true(!str_contains($text, 'Post EOD object-stream tail leak'));
        $t->true(!str_contains($text, 'ASCII85Decode'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(1, $review['compressed_entry_count']);
        $t->same(1, $review['unresolved_object_stream_carrier_count']);
        $t->same(4, $entry['object_number'] ?? null);
        $t->same(6, $entry['object_stream'] ?? null);
        $t->same(false, $entry['object_stream_carrier_resolved'] ?? null);
        $t->same(true, $entry['object_stream_carrier_has_filter'] ?? null);
        $t->same(true, $entry['invalid_object_stream_carrier_rejected'] ?? null);
        $t->same(0, $entry['object_stream_member_count'] ?? null);
        $t->same(0, $entry['matching_header_object_number_count'] ?? null);
        $t->same('missing_object_stream_member', $entry['selection_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
