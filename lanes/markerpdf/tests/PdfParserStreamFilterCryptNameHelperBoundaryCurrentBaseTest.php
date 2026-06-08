<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserStreamFilterCryptNameHelperBoundaryCurrentBasePdf = static function (
    string $helperBody,
    string $filteredText,
    string $visibleAfter
): string {
    $filteredContent = 'BT /F1 12 Tf 72 720 Td (' . $filteredText . ') Tj ET';
    $compressed = gzcompress($filteredContent);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress focused Crypt filter helper stream.');
    }

    $visibleContent = 'BT /F1 12 Tf 72 700 Td (' . $visibleAfter . ') Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Filter [ /Crypt /FlateDecode ] /DecodeParms [ << /Type /CryptFilterDecodeParms /Name 10 0 R >> null ] /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
        . "10 0 obj\n{$helperBody}\nendobj\n"
        . "%%EOF";
};

return [
    'rejects Crypt DecodeParms indirect Name helpers with trailing operands before page text import' => static function (
        TestRunner $t
    ) use ($parserStreamFilterCryptNameHelperBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterCryptNameHelperBoundaryCurrentBasePdf(
            "/Identity /PrivateCF\n",
            'Malformed Crypt Name Helper Leak',
            'Visible After Crypt Name Helper'
        );
        $text = $extractor->extractPlainText($pdf);

        $expected = ['Visible After Crypt Name Helper'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same('Visible After Crypt Name Helper', $text);
        $t->same("Visible After Crypt Name Helper\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Malformed Crypt Name Helper Leak'));
        $t->true(!str_contains($text, 'PrivateCF'));
        $t->true(!str_contains($text, 'CryptFilterDecodeParms'));
        $t->true(!str_contains($text, 'FlateDecode'));
        $t->true(!str_contains($text, "\0"));
    },
    'accepts standalone indirect Identity Crypt Name helpers with trailing comments' => static function (
        TestRunner $t
    ) use ($parserStreamFilterCryptNameHelperBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterCryptNameHelperBoundaryCurrentBasePdf(
            "/Identity % standalone helper comment\n",
            'Standalone Identity Helper Import',
            'Visible After Standalone Identity Helper'
        );
        $text = $extractor->extractPlainText($pdf);

        $expected = [
            'Standalone Identity Helper Import',
            'Visible After Standalone Identity Helper',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Standalone Identity Helper Import\nVisible After Standalone Identity Helper", $text);
        $t->same("Standalone Identity Helper Import\nVisible After Standalone Identity Helper\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'CryptFilterDecodeParms'));
        $t->true(!str_contains($text, 'FlateDecode'));
        $t->true(!str_contains($text, "\0"));
    },
];
