<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserNameArrayCommentEscapeCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current escaped xref array page) Tj T* (Comment-safe Index wins) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale unreferenced direct page) Tj T* (Escaped array parser leak) Tj ET';

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

    $currentRows = ''
        . $xrefRow(1, $offsets['1:0'], 0)
        . $xrefRow(1, $offsets['2:0'], 0)
        . $xrefRow(1, $offsets['3:0'], 0)
        . $xrefRow(1, $offsets['4:0'], 0)
        . $xrefRow(1, $offsets['5:0'], 0);
    $compressedXref = gzcompress($currentRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress escaped xref array fixture.');
    }

    $currentXrefOffset = $addObject(
        20,
        0,
        "<< /Type /XRef /Si#7ae 21 /Ro#6ft 1 0 R\n"
            . "/In#64ex [ % 10 3 is comment-only stale direct range\n 1 5 ]\n"
            . "/#57 [ 1 4 % 9 9 is comment-only malformed width\n 1 ]\n"
            . "/Filter /FlateDecode /Length " . strlen($compressedXref) . " >>\n"
            . "stream\n{$compressedXref}\nendstream"
    );
    $pdf .= "startxref\n{$currentXrefOffset}\n%%EOF\n";

    $addObject(1, 0, '<< /Type /Catalog /Pages 10 0 R >>');
    $addObject(10, 0, '<< /Type /Pages /Kids [11 0 R] /Count 1 >>');
    $addObject(11, 0, '<< /Type /Page /Parent 10 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 12 0 R >>');
    $addObject(12, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    return $pdf;
};

return [
    'uses escaped xref-stream name arrays with comments before stale current-base direct objects' => static function (
        TestRunner $t
    ) use ($parserNameArrayCommentEscapeCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserNameArrayCommentEscapeCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current escaped xref array page', 'Comment-safe Index wins'], $extractor->extractTextLines($pdf));
        $t->same(['Current escaped xref array page', 'Comment-safe Index wins'], $extractor->extractTextRuns($pdf));
        $t->same("Current escaped xref array page\nComment-safe Index wins", $text);
        $t->same("Current escaped xref array page\nComment-safe Index wins\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale unreferenced direct page'));
        $t->true(!str_contains($text, 'Escaped array parser leak'));
        $t->true(!str_contains($text, '10 3'));
        $t->true(!str_contains($text, "\0"));
    },
];
