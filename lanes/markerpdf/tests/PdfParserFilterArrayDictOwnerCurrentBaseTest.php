<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserFilterArrayDictOwnerCurrentBasePdf = static function (): string {
    $hiddenContent = "% Malformed filter-array bytes contain stream owner decoys.\n"
        . "endstream\nendobj\n"
        . "20 0 obj\n<< /Length 57 >>\nstream\nBT /F1 12 Tf 72 640 Td (Fake dictionary owner leak) Tj ET\nendstream\nendobj\n"
        . "BT /F1 12 Tf 72 720 Td (Malformed filter array leak) Tj ET";
    $compressed = gzcompress($hiddenContent, 0);
    if (!is_string($compressed) || !str_contains($compressed, "\nendstream\nendobj\n20 0 obj")) {
        throw new RuntimeException('Unable to build focused malformed filter-array owner fixture.');
    }

    $visibleContent = 'BT /F1 12 Tf 72 680 Td (Safe current page text) Tj ET';

    return "%PDF-1.5\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Filter [ << /Owner (Filter dictionary is not a decoder) /Fake [ /Nested ] >> /FlateDecode ] >>\nstream\n{$compressed}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'fails closed on dictionary entries inside stream Filter arrays before current-base text extraction' => static function (TestRunner $t) use ($parserFilterArrayDictOwnerCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserFilterArrayDictOwnerCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Safe current page text'], $extractor->extractTextLines($pdf));
        $t->same(['Safe current page text'], $extractor->extractTextRuns($pdf));
        $t->same('Safe current page text', $text);
        $t->same("Safe current page text\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Malformed filter array leak'));
        $t->true(!str_contains($text, 'Fake dictionary owner leak'));
        $t->true(!str_contains($text, 'Filter dictionary is not a decoder'));
        $t->true(!str_contains($text, '20 0 obj'));
        $t->true(!str_contains($text, 'endstream'));
        $t->true(!str_contains($text, "\0"));
    },
];
