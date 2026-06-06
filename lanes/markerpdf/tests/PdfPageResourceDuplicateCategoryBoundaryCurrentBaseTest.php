<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceDuplicateCategoryCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode duplicate resource category CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceDuplicateCategoryCurrentBaseCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceDuplicateCategoryPdf = static function () use ($pageResourceDuplicateCategoryCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj T* /Span /DupActual BDC <42> Tj EMC ET q /CurrentForm Do Q q /StaleForm Do Q';
    $staleForm = 'BT /F1 12 Tf 12 24 Td <43> Tj ET';
    $currentForm = 'BT /F1 12 Tf 12 24 Td <44> Tj ET';
    $staleCMap = $pageResourceDuplicateCategoryCMap([
        '41' => 'Stale duplicate category font leak',
        '42' => 'Stale duplicate category actual glyph leak',
        '43' => 'Stale duplicate category form leak',
    ]);
    $currentCMap = $pageResourceDuplicateCategoryCMap([
        '41' => 'Current duplicate category font text',
        '42' => 'Current duplicate category actual glyph',
        '44' => 'Current duplicate category form text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StaleDuplicateCategoryFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($staleCMap) . " >>\nstream\n{$staleCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
        . "8 0 obj\n<< /ActualText (Stale duplicate category ActualText leak) >>\nendobj\n"
        . "9 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CurrentDuplicateCategoryFont /Encoding /Identity-H /ToUnicode 12 0 R >>\nendobj\n"
        . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Length " . strlen($currentCMap) . " >>\nstream\n{$currentCMap}\nendstream\nendobj\n"
        . "13 0 obj\n<< /ActualText (Current duplicate category ActualText) >>\nendobj\n"
        . "10 0 obj\n<< "
        . "/Font << /F1 5 0 R >> "
        . "/Font << /F1 9 0 R >> "
        . "/XObject << /CurrentForm 7 0 R /StaleForm 7 0 R >> "
        . "/XObject << /CurrentForm 11 0 R >> "
        . "/Properties << /DupActual 8 0 R >> "
        . "/Properties << /DupActual 13 0 R >> "
        . ">>\nendobj\n"
        . "%%EOF";
};

return [
    'uses current duplicate resource categories before inherited page text and form lookup' => static function (TestRunner $t) use ($pageResourceDuplicateCategoryPdf): void {
        $pdf = $pageResourceDuplicateCategoryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $styledPages = $extractor->extractStyledTextPages($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Current duplicate category font text',
            'Current duplicate category ActualText',
            'Current duplicate category form text',
        ];
        $styledLines = array_map(
            static fn (array $block): string => implode('', array_column($block['lines'][0]['spans'], 'text')),
            $styledPages[0]['blocks'] ?? []
        );

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same($expected, $styledLines);
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(2, $resources['resource_owner_object'] ?? null);
        $t->same(10, $resources['resource_object'] ?? null);
        $t->same(0, $resources['resource_generation'] ?? null);
        $t->same(['Font', 'XObject', 'Properties'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(['CurrentForm'], $resources['xobject_names'] ?? null);
        $t->same(['DupActual'], $resources['properties_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Stale duplicate category'));
        $t->same(false, str_contains($plainText, 'StaleForm'));
        $t->same(false, str_contains($plainText, 'Current duplicate category actual glyph'));
        $t->same(false, str_contains($plainText, 'CurrentForm'));
    },
];
