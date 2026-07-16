<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamDuplicateZeroWidthCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current duplicate zero-width guard) Tj T* (Ambiguous compressed page ignored) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale duplicate member page) Tj ET';
    $duplicateLeakContent = 'BT /F1 12 Tf 72 720 Td (Duplicate zero-width member leak) Tj ET';

    $members = [
        [12, '<< /Type /Page /Parent 2 0 R /Note (strict default-index decoy member) >>'],
        [4, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (first duplicate page member) >>'],
        [4, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 10 0 R /Note (second duplicate page member) >>'],
    ];
    $objectData = '';
    $headerPairs = [];
    foreach ($members as [$objectNumber, $body]) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $objectData .= $body . "\n";
    }
    $header = implode(' ', $headerPairs);
    $objectStream = gzcompress($header . "\n" . $objectData);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress duplicate zero-width object-stream fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(10, 0, "<< /Length " . strlen($duplicateLeakContent) . " >>\nstream\n{$duplicateLeakContent}\nendstream");

    $xrefRows = ''
        . chr(1) . pack('N', $offsets['1:0'])
        . chr(1) . pack('N', $offsets['2:0'])
        . chr(1) . pack('N', $offsets['3:0'])
        . chr(2) . pack('N', 6)
        . chr(1) . pack('N', $offsets['5:0'])
        . chr(1) . pack('N', $offsets['6:0'])
        . chr(1) . pack('N', $offsets['8:0'])
        . chr(1) . pack('N', $offsets['9:0'])
        . chr(1) . pack('N', $offsets['10:0']);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress duplicate zero-width xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 3] /W [1 4 0] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'fails closed on duplicate object-stream header numbers when xref member indexes are zero-width' => static function (TestRunner $t) use ($xrefObjectStreamDuplicateZeroWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamDuplicateZeroWidthCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');

        $t->same(['Current duplicate zero-width guard', 'Ambiguous compressed page ignored'], $extractor->extractTextLines($pdf));
        $t->same(['Current duplicate zero-width guard', 'Ambiguous compressed page ignored'], $extractor->extractTextRuns($pdf));
        $t->same("Current duplicate zero-width guard\nAmbiguous compressed page ignored", $text);
        $t->same("Current duplicate zero-width guard\nAmbiguous compressed page ignored\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale duplicate member page'));
        $t->true(!str_contains($text, 'Duplicate zero-width member leak'));
        $t->true(!str_contains($text, 'strict default-index decoy member'));
        $t->true(!str_contains($text, 'first duplicate page member'));
        $t->true(!str_contains($text, 'second duplicate page member'));
        $t->true(!str_contains($text, "\0"));
        $t->same('ambiguous_zero_width_duplicate_header_object_number', $entries[4]['selection_policy'] ?? null);
        $t->same(2, $entries[4]['matching_header_object_number_count'] ?? null);
        $t->true($entries[4]['duplicate_header_object_number'] ?? false);
        $t->true($entries[4]['ambiguous_zero_width_member'] ?? false);
        $t->same(1, $review['ambiguous_zero_width_member_count'] ?? null);
    },
];
