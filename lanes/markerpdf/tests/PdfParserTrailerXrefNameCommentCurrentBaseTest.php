<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserTrailerXrefNameCommentCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale comment trailer page) Tj T* (Comment Root wins leak) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current escaped trailer page) Tj T* (Token trailer wins) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 6\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0'])
        . $xrefRow($offsets['2:0'])
        . $xrefRow($offsets['3:0'])
        . $xrefRow($offsets['4:0'])
        . $xrefRow($offsets['5:0'])
        . "trailer\n<< /Size 15 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(10, 0, '<< /Type /Catalog /Pages 11 0 R >>');
    $addObject(11, 0, '<< /Type /Pages /Kids [12 0 R] /Count 1 >>');
    $addObject(12, 0, '<< /Type /Page /Parent 11 0 R /Resources << /Font << /F1 14 0 R >> >> /Contents 13 0 R >>');
    $addObject(13, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(14, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 1\n"
        . $xrefRow(0, 65535, 'f')
        . "10 5\n"
        . $xrefRow($offsets['10:0'])
        . $xrefRow($offsets['11:0'])
        . $xrefRow($offsets['12:0'])
        . $xrefRow($offsets['13:0'])
        . $xrefRow($offsets['14:0'])
        . "% trailer << /Root 1 0 R /Prev {$previousXrefOffset} /CommentOnly /Stale#52oot >>\n"
        . "trailer\n<< /Size 15 /Ro#6ft 10 0 R /Pre#76 {$previousXrefOffset} >>\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'skips comment-only trailer dictionaries and decodes escaped xref trailer names before WordPress text extraction' => static function (TestRunner $t) use ($parserTrailerXrefNameCommentCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserTrailerXrefNameCommentCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current escaped trailer page', 'Token trailer wins'], $extractor->extractTextLines($pdf));
        $t->same(['Current escaped trailer page', 'Token trailer wins'], $extractor->extractTextRuns($pdf));
        $t->same("Current escaped trailer page\nToken trailer wins", $text);
        $t->same("Current escaped trailer page\nToken trailer wins\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale comment trailer page'));
        $t->true(!str_contains($text, 'Comment Root wins leak'));
        $t->true(!str_contains($text, 'Stale#52oot'));
        $t->true(!str_contains($text, "\0"));
    },
];
