<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserObjectStreamInlineImageFilterCurrentBasePdf = static function (): string {
    $imageRow = 'raw EI BT /F1 12 Tf 72 680 Td (Inline Object Stream Filter Noise) Tj ET';
    $compressedImage = gzcompress("\0" . $imageRow, 0);
    if (!is_string($compressedImage) || !str_contains($compressedImage, ' EI ')) {
        throw new RuntimeException('Unable to build focused inline-image object-stream fixture.');
    }

    $content = "BT /F1 12 Tf 72 720 Td (Object Stream Filter Inline Before) Tj ET\n"
        . 'BI /W ' . strlen($imageRow) . ' /H 1 /CS /G /BPC 8 /F [ null /Fl ] '
        . '/DP [ null << /Predictor 12 /Columns ' . strlen($imageRow) . " /Colors 1 /BitsPerComponent 8 >> ] ID "
        . $compressedImage . "\nEI\n"
        . 'BT /F1 12 Tf 72 704 Td (Object Stream Filter Inline After) Tj ET';
    $storedPrefix = "% Stored filtered content contains stream-owner decoys.\n"
        . "endstream\nendobj\n"
        . "44 0 obj\n<< /Producer (Fake stream owner) >>\nendobj\n";
    $compressedContent = gzcompress($storedPrefix . $content, 0);
    if (!is_string($compressedContent) || !str_contains($compressedContent, "\nendstream\nendobj\n44 0 obj")) {
        throw new RuntimeException('Unable to build focused current-base content stream fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };

    $members = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
        5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        30 => '/FlateDecode',
        31 => '<< /Predictor 1 >>',
        32 => (string) strlen($compressedContent),
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
    $objectStream = gzcompress($objectStreamPlain);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress focused object-stream fixture.');
    }

    $addObject(4, 0, "<< /Filter 30 0 R /DecodeParms 31 0 R /Length 32 0 R >>\nstream\n{$compressedContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

    $xrefRows = '';
    foreach ([1, 2, 3, 4, 5, 6, 30, 31, 32] as $objectNumber) {
        if ($objectNumber === 4 || $objectNumber === 6) {
            $xrefRows .= chr(1) . pack('N', $offsets[$objectNumber . ':0']) . chr(0);
        } else {
            $xrefRows .= chr(2) . pack('N', 6) . chr($memberIndexes[$objectNumber]);
        }
    }
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress focused xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "40 0 obj\n"
        . '<< /Type /XRef /Size 41 /Root 1 0 R /Index [1 6 30 3] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'repairs object-stream filter helpers before inline image WordPress text extraction' => static function (TestRunner $t) use ($parserObjectStreamInlineImageFilterCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserObjectStreamInlineImageFilterCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['Object Stream Filter Inline Before', 'Object Stream Filter Inline After'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Object Stream Filter Inline Before\nObject Stream Filter Inline After", $text);
        $t->same("Object Stream Filter Inline Before\nObject Stream Filter Inline After\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Inline Object Stream Filter Noise'));
        $t->true(!str_contains($text, 'Fake stream owner'));
        $t->true(!str_contains($text, '44 0 obj'));
        $t->true(!str_contains($text, 'endstream'));
        $t->true(!str_contains($text, "\0"));
    },
];
