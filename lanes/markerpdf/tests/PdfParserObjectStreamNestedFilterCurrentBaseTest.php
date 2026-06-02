<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserObjectStreamNestedFilterCurrentBasePdf = static function (): string {
    $asciiHexEncode = static fn (string $bytes): string => strtoupper(bin2hex($bytes)) . '>';

    $safeFallbackContent = 'BT /F1 12 Tf 72 720 Td (Direct fallback survives nested filter review) Tj ET';

    $members = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> >>',
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
    $objectStreamPlain = $header . "\n" . $objectData;
    $compressedObjectStream = gzcompress($objectStreamPlain);
    if (!is_string($compressedObjectStream)) {
        throw new RuntimeException('Unable to compress focused nested-filter object-stream fixture.');
    }
    $encodedObjectStream = $asciiHexEncode($compressedObjectStream);

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };

    $addObject(6, 0, "<< /Type /ObjStm /N " . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter [ /ASCIIHexDecode [ /FlateDecode ] ] /Length ' . strlen($encodedObjectStream) . " >>\nstream\n{$encodedObjectStream}\nendstream");
    $addObject(9, 0, "<< /Length " . strlen($safeFallbackContent) . " >>\nstream\n{$safeFallbackContent}\nendstream");

    $xrefRows = ''
        . chr(2) . pack('N', 6) . chr($memberIndexes[1])
        . chr(2) . pack('N', 6) . chr($memberIndexes[2])
        . chr(2) . pack('N', 6) . chr($memberIndexes[3])
        . chr(2) . pack('N', 6) . chr($memberIndexes[4])
        . chr(1) . pack('N', $offsets[6]) . chr(0)
        . chr(1) . pack('N', $offsets[9]) . chr(0);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress focused nested-filter xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "10 0 obj\n"
        . '<< /Type /XRef /Size 11 /Root 1 0 R /Index [1 4 6 1 9 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rejects nested object stream filter arrays before WordPress fallback text extraction' => static function (TestRunner $t) use ($parserObjectStreamNestedFilterCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserObjectStreamNestedFilterCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Direct fallback survives nested filter review'], $extractor->extractTextLines($pdf));
        $t->same(['Direct fallback survives nested filter review'], $extractor->extractTextRuns($pdf));
        $t->same('Direct fallback survives nested filter review', $text);
        $t->same("Direct fallback survives nested filter review\n", $extractor->naiveGetText($pdf));
        $t->same(0, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'ObjStm'));
        $t->true(!str_contains($text, 'ASCIIHexDecode'));
        $t->true(!str_contains($text, 'FlateDecode'));
        $t->true(!str_contains($text, "\0"));
    },
];
