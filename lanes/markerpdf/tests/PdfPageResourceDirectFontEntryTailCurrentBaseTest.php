<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceDirectFontEntryTailCMap = static function (array $entries): string {
    $body = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . count($entries) . " beginbfchar\n";

    foreach ($entries as $sourceHex => $text) {
        $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', (string) $text);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode direct font resource entry-tail CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceDirectFontEntryTailCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceDirectFontEntryTailPdf = static function () use ($pageResourceDirectFontEntryTailCMap): string {
    $content = 'BT /Ftailed 12 Tf 72 720 Td <41> Tj T* /Fvalid 12 Tf <42> Tj ET';
    $tailedCMap = $pageResourceDirectFontEntryTailCMap([
        '41' => 'Direct tailed font dictionary leak',
    ]);
    $validCMap = $pageResourceDirectFontEntryTailCMap([
        '42' => 'Valid direct font dictionary text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($tailedCMap) . " >>\nstream\n{$tailedCMap}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($validCMap) . " >>\nstream\n{$validCMap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << "
        . "/Ftailed << /Type /Font /Subtype /Type0 /BaseFont /DirectTailedFont /Encoding /Identity-H /ToUnicode 5 0 R >> 99 0 R "
        . "/Fvalid << /Type /Font /Subtype /Type0 /BaseFont /ValidDirectFont /Encoding /Identity-H /ToUnicode 6 0 R >> "
        . ">> >>\nendobj\n"
        . "99 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects tailed direct Font resource dictionaries before ToUnicode lookup while preserving valid siblings' => static function (
        TestRunner $t
    ) use ($pageResourceDirectFontEntryTailPdf): void {
        $pdf = $pageResourceDirectFontEntryTailPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'A',
            'Valid direct font dictionary text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, count($boundary));
        $t->same('resolved', $resources['status'] ?? null);
        $t->same(true, $resources['resolved'] ?? null);
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(2, $resources['resource_owner_object'] ?? null);
        $t->same(10, $resources['resource_object'] ?? null);
        $t->same(['Font'], $resources['categories'] ?? null);
        $t->same(['Fvalid'], $resources['font_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Direct tailed font dictionary leak'));
        $t->same(false, str_contains($plainText, 'DirectTailedFont'));
        $t->same(false, str_contains($plainText, 'Ftailed'));
    },
];
