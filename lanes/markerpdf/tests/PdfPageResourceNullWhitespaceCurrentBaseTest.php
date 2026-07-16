<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceNullWhitespaceCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode null-whitespace page resource CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceNullWhitespaceCurrentBaseCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceNullWhitespacePdf = static function () use ($pageResourceNullWhitespaceCMap): string {
    $nul = "\0";
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET '
        . 'q /NullWsForm Do Q '
        . '/Span /NullActual BDC BT /F1 12 Tf 72 680 Td (Actual glyph leak) Tj ET EMC';
    $form = 'BT /F1 12 Tf 12 24 Td (Null whitespace inherited form text) Tj ET';
    $cmap = $pageResourceNullWhitespaceCMap([
        '41' => 'Null whitespace inherited font text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10{$nul}0{$nul}R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NullWhitespaceResource /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 260 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
        . "8 0 obj\n<< /ActualText (Null whitespace inherited actual text) >>\nendobj\n"
        . "10 0 obj\n<< "
        . "/Font << /F1 5{$nul}0{$nul}R >> "
        . "/XObject << /NullWsForm 7{$nul}0{$nul}R >> "
        . "/Properties << /NullActual 8{$nul}0{$nul}R >> "
        . ">>\nendobj\n"
        . "%%EOF";
};

return [
    'treats PDF null bytes as whitespace in inherited page resource references' => static function (
        TestRunner $t
    ) use ($pageResourceNullWhitespacePdf): void {
        $pdf = $pageResourceNullWhitespacePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $styledPages = $extractor->extractStyledTextPages($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $styledLines = array_map(
            static fn (array $block): string => implode('', array_column($block['lines'][0]['spans'] ?? [], 'text')),
            $styledPages[0]['blocks'] ?? []
        );
        $expected = [
            'Null whitespace inherited font text',
            'Null whitespace inherited form text',
            'Null whitespace inherited actual text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same($expected, $styledLines);
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same('resolved', $resources['status'] ?? null);
        $t->same(true, $resources['resolved'] ?? null);
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(2, $resources['resource_owner_object'] ?? null);
        $t->same(10, $resources['resource_object'] ?? null);
        $t->same(0, $resources['resource_generation'] ?? null);
        $t->same(['Font', 'XObject', 'Properties'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(['NullWsForm'], $resources['xobject_names'] ?? null);
        $t->same(['NullActual'], $resources['properties_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Actual glyph leak'));
        $t->same(false, str_contains($plainText, 'NullWsForm'));
        $t->same(false, str_contains($plainText, 'PageResourceNullWhitespaceCurrentBaseCMap'));
    },
];
