<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserXrefStreamObjectOwnerCycleCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current xref owner cycle page) Tj T* (Direct xref stream preserved) Tj ET';
    $compressedXrefMemberLeak = 'BT /F1 12 Tf 72 700 Td (Compressed xref owner cycle leak) Tj ET';

    $compressedOwnerMembers = [
        20 => '<< /Type /XRef /Length ' . strlen($compressedXrefMemberLeak) . " >>\nstream\n{$compressedXrefMemberLeak}\nendstream",
    ];
    $compressedOwnerData = '';
    $compressedOwnerHeaderPairs = [];
    $compressedOwnerMemberIndexes = [];
    foreach ($compressedOwnerMembers as $objectNumber => $body) {
        $compressedOwnerHeaderPairs[] = $objectNumber . ' ' . strlen($compressedOwnerData);
        $compressedOwnerMemberIndexes[$objectNumber] = count($compressedOwnerMemberIndexes);
        $compressedOwnerData .= $body . "\n";
    }

    $compressedOwnerHeader = implode(' ', $compressedOwnerHeaderPairs);
    $compressedOwnerStream = gzcompress($compressedOwnerHeader . "\n" . $compressedOwnerData);
    if (!is_string($compressedOwnerStream)) {
        throw new RuntimeException('Unable to compress xref owner-cycle object stream fixture.');
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
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($compressedOwnerHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedOwnerStream) . " >>\nstream\n{$compressedOwnerStream}\nendstream");

    $xrefRows = ''
        . $xrefRow(1, $offsets['1:0'])
        . $xrefRow(1, $offsets['2:0'])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(1, $offsets['4:0'])
        . $xrefRow(1, $offsets['5:0'])
        . $xrefRow(1, $offsets['6:0'])
        . $xrefRow(2, 6, $compressedOwnerMemberIndexes[20]);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress current xref owner-cycle stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 20 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'preserves direct xref stream owners when decoded rows form a compressed owner cycle' => static function (TestRunner $t) use ($parserXrefStreamObjectOwnerCycleCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserXrefStreamObjectOwnerCycleCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');

        $t->same(['Current xref owner cycle page', 'Direct xref stream preserved'], $extractor->extractTextLines($pdf));
        $t->same(['Current xref owner cycle page', 'Direct xref stream preserved'], $extractor->extractTextRuns($pdf));
        $t->same("Current xref owner cycle page\nDirect xref stream preserved", $text);
        $t->same("Current xref owner cycle page\nDirect xref stream preserved\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Compressed xref owner cycle leak'));
        $t->true(!str_contains($text, 'XRef'));
        $t->true(!str_contains($text, "\0"));

        $t->same(1, $review['direct_xref_stream_owner_cycle_count'] ?? null);
        $t->same('direct_xref_stream_owner_preserved', $entries[20]['owner_policy'] ?? null);
        $t->true($entries[20]['direct_xref_stream_owner'] ?? false);
        $t->true($entries[20]['owner_cycle_rejected'] ?? false);
        $t->same('explicit_member_index', $entries[20]['selection_policy'] ?? null);
        $t->same(20, $entries[20]['object_number'] ?? null);
        $t->same(6, $entries[20]['object_stream'] ?? null);
    },
];
