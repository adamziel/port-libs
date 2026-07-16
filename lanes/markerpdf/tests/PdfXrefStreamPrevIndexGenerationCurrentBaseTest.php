<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefStreamPrevIndexGenerationCurrentBasePdf = static function (): string {
    $previousContent = 'BT /F1 12 Tf 72 720 Td (Prev compressed same-offset generation page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current sparse Index generation page) Tj T* (Same carrier offset preserved) Tj ET';

    $objectData = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>' . "\n";
    $header = '4 0';
    $compressedObjectStream = gzcompress($header . "\n" . $objectData);
    if (!is_string($compressedObjectStream)) {
        throw new RuntimeException('Unable to compress same-offset object-stream fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $row = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");

    $previousRows = ''
        . $row(1, $offsets['1:0'], 0)
        . $row(1, $offsets['2:0'], 0)
        . $row(1, $offsets['3:0'], 0)
        . $row(2, 6, 0)
        . $row(1, $offsets['5:0'], 0)
        . $row(1, $offsets['6:0'], 0);
    $previousCompressedXref = gzcompress($previousRows);
    if (!is_string($previousCompressedXref)) {
        throw new RuntimeException('Unable to compress previous xref-stream fixture.');
    }

    $previousXrefOffset = $addObject(
        20,
        0,
        '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressedXref) . " >>\nstream\n{$previousCompressedXref}\nendstream"
    );
    $pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
    $addObject(8, 0, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentRows = ''
        . $row(1, $offsets['1:1'], 1)
        . $row(1, $offsets['2:1'], 1)
        . $row(1, $offsets['6:0'], 1)
        . $row(1, $offsets['8:0'], 0)
        . $row(1, $offsets['9:0'], 0);
    $currentCompressedXref = gzcompress($currentRows);
    if (!is_string($currentCompressedXref)) {
        throw new RuntimeException('Unable to compress current xref-stream fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "21 0 obj\n"
        . '<< /Type /XRef /Size 22 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 2 6 1 8 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentCompressedXref) . " >>\n"
        . "stream\n{$currentCompressedXref}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'preserves Prev type-2 rows when current sparse Index carrier row keeps the same offset despite generation noise' => static function (TestRunner $t) use ($xrefStreamPrevIndexGenerationCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefStreamPrevIndexGenerationCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current sparse Index generation page', 'Same carrier offset preserved', 'Prev compressed same-offset generation page'], $extractor->extractTextLines($pdf));
        $t->same(['Current sparse Index generation page', 'Same carrier offset preserved', 'Prev compressed same-offset generation page'], $extractor->extractTextRuns($pdf));
        $t->same("Current sparse Index generation page\nSame carrier offset preserved\nPrev compressed same-offset generation page", $text);
        $t->same("Current sparse Index generation page\nSame carrier offset preserved\nPrev compressed same-offset generation page\n", $extractor->naiveGetText($pdf));
        $t->same(2, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1', '2'], $extractor->extractPageLabels($pdf));
        $t->true(str_contains($text, 'Prev compressed same-offset generation page'));
        $t->true(!str_contains($text, "\0"));
    },
];
