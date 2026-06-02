<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserXrefObjectStreamFilterCurrentBasePdf = static function (): string {
    $runLengthEncode = static function (string $bytes): string {
        $encoded = '';
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 128) {
            $chunk = substr($bytes, $offset, 128);
            $encoded .= chr(strlen($chunk) - 1) . $chunk;
        }

        return $encoded . chr(128);
    };

    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current repaired filtered object stream page) Tj T* (RunLength carrier expanded after repair) Tj ET';
    $fakeOwnerBoundary = "carrier bytes before fake owner boundary\nendstream\nendobj\nobject scanner decoy";

    $members = [
        1 => '<< /Type /Catalog /Note (' . $fakeOwnerBoundary . ') /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
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
    $plainObjectStream = $header . "\n" . $objectData;
    $encodedObjectStream = $runLengthEncode($plainObjectStream);

    $helperMembers = [
        30 => (string) strlen($encodedObjectStream),
        31 => '/RunLengthDecode',
    ];
    $helperData = '';
    $helperHeaderPairs = [];
    $helperMemberIndexes = [];
    foreach ($helperMembers as $objectNumber => $body) {
        $helperHeaderPairs[] = $objectNumber . ' ' . strlen($helperData);
        $helperMemberIndexes[$objectNumber] = count($helperMemberIndexes);
        $helperData .= $body . "\n";
    }
    $helperHeader = implode(' ', $helperHeaderPairs);
    $helperObjectStream = $helperHeader . "\n" . $helperData;

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };

    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, "<< /Type /ObjStm /N " . count($members) . ' /First ' . (strlen($header) + 1) . " /Length 30 0 R /Filter 31 0 R >>\nstream\n{$encodedObjectStream}\nendstream");
    $addObject(7, 0, "<< /Type /ObjStm /N " . count($helperMembers) . ' /First ' . (strlen($helperHeader) + 1) . ' /Length ' . strlen($helperObjectStream) . " >>\nstream\n{$helperObjectStream}\nendstream");

    $xrefRows = ''
        . chr(2) . pack('N', 6) . chr($memberIndexes[1])
        . chr(2) . pack('N', 6) . chr($memberIndexes[2])
        . chr(2) . pack('N', 6) . chr($memberIndexes[3])
        . chr(2) . pack('N', 6) . chr($memberIndexes[4])
        . chr(1) . pack('N', $offsets['5:0']) . chr(0)
        . chr(1) . pack('N', $offsets['6:0']) . chr(0)
        . chr(1) . pack('N', $offsets['7:0']) . chr(0)
        . chr(2) . pack('N', 7) . chr($helperMemberIndexes[30])
        . chr(2) . pack('N', 7) . chr($helperMemberIndexes[31]);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress focused current-base xref stream.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 32 /Root 1 0 R /Index [1 7 30 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'expands repaired xref-selected object streams with compressed filter operands on current base' => static function (TestRunner $t) use ($parserXrefObjectStreamFilterCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserXrefObjectStreamFilterCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current repaired filtered object stream page', 'RunLength carrier expanded after repair'], $extractor->extractTextLines($pdf));
        $t->same(['Current repaired filtered object stream page', 'RunLength carrier expanded after repair'], $extractor->extractTextRuns($pdf));
        $t->same("Current repaired filtered object stream page\nRunLength carrier expanded after repair", $text);
        $t->same("Current repaired filtered object stream page\nRunLength carrier expanded after repair\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'object scanner decoy'));
        $t->true(!str_contains($text, 'RunLengthDecode'));
        $t->true(!str_contains($text, 'endobj'));
        $t->true(!str_contains($text, "\0"));
    },
];
