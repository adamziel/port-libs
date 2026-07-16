<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefStreamPrevIndexWidthCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale duplicate index width page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current duplicate-free page) Tj T* (Index width current row) Tj ET';

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRows = static function (array $offsets): string {
        $rows = '';
        foreach ($offsets as $offset) {
            $rows .= pack('N', $offset);
        }

        return $rows;
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $previousCompressed = gzcompress($xrefRows([
        $offsets['1:0'],
        $offsets['2:0'],
        $offsets['3:0'],
        $offsets['4:0'],
        $offsets['5:0'],
    ]));
    if (!is_string($previousCompressed)) {
        throw new RuntimeException('Unable to compress previous xref-stream fixture.');
    }

    $previousXrefOffset = $addObject(
        20,
        0,
        '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 5] /W [0 4 0] /Filter /FlateDecode /Length ' . strlen($previousCompressed) . " >>\nstream\n{$previousCompressed}\nendstream"
    );
    $pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [3 1 R] /Count 1 >>');
    $addObject(3, 1, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 1 R >>');
    $addObject(5, 1, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentCompressed = gzcompress($xrefRows([
        $offsets['1:1'],
        $offsets['2:1'],
        $offsets['3:1'],
        $offsets['5:1'],
        $offsets['5:0'],
    ]));
    if (!is_string($currentCompressed)) {
        throw new RuntimeException('Unable to compress current xref-stream fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "21 0 obj\n"
        . '<< /Type /XRef /Size 22 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 3 5 1 5 1] /W [0 4 0] /Filter /FlateDecode /Length ' . strlen($currentCompressed) . " >>\n"
        . "stream\n{$currentCompressed}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'keeps first current xref stream Index row before duplicate stale Prev row' => static function (TestRunner $t) use ($xrefStreamPrevIndexWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefStreamPrevIndexWidthCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current duplicate-free page', 'Index width current row'], $extractor->extractTextLines($pdf));
        $t->same(['Current duplicate-free page', 'Index width current row'], $extractor->extractTextRuns($pdf));
        $t->same("Current duplicate-free page\nIndex width current row", $text);
        $t->same("Current duplicate-free page\nIndex width current row\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale duplicate index width page'));
        $t->true(!str_contains($text, "\0"));
    },
];
