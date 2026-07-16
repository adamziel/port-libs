<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceDirectDuplicateEntryCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode direct duplicate resource-entry CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceDirectDuplicateEntryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceDirectDuplicateEntryPdf = static function () use ($pageResourceDirectDuplicateEntryCMap): string {
    $content = 'BT /Fdup 12 Tf 72 720 Td <41> Tj T* '
        . '/Fvalid 12 Tf <42> Tj T* '
        . '/Span /DupActual BDC <43> Tj EMC T* '
        . '/Span /ValidActual BDC <44> Tj EMC ET';
    $staleCMap = $pageResourceDirectDuplicateEntryCMap([
        '41' => 'Stale direct duplicate font leak',
    ]);
    $currentCMap = $pageResourceDirectDuplicateEntryCMap([
        '41' => 'Current direct duplicate font leak',
    ]);
    $validCMap = $pageResourceDirectDuplicateEntryCMap([
        '42' => 'Valid direct duplicate boundary font text',
        '43' => 'Direct duplicate property raw glyph',
        '44' => 'Valid direct duplicate boundary actual glyph',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($staleCMap) . " >>\nstream\n{$staleCMap}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($currentCMap) . " >>\nstream\n{$currentCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($validCMap) . " >>\nstream\n{$validCMap}\nendstream\nendobj\n"
        . "10 0 obj\n<< "
        . "/Font << "
        . "/Fdup << /Type /Font /Subtype /Type0 /BaseFont /StaleDirectDuplicate /Encoding /Identity-H /ToUnicode 5 0 R >> "
        . "/Fdup << /Type /Font /Subtype /Type0 /BaseFont /CurrentDirectDuplicate /Encoding /Identity-H /ToUnicode 6 0 R >> "
        . "/Fvalid << /Type /Font /Subtype /Type0 /BaseFont /ValidDirectDuplicate /Encoding /Identity-H /ToUnicode 7 0 R >> "
        . ">> "
        . "/Properties << "
        . "/DupActual << /ActualText (Stale direct duplicate ActualText leak) >> "
        . "/DupActual << /ActualText (Current direct duplicate ActualText leak) >> "
        . "/ValidActual << /ActualText (Valid direct duplicate boundary ActualText) >> "
        . ">> "
        . ">>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects duplicate direct inherited resource entry names before font maps ActualText and metadata' => static function (
        TestRunner $t
    ) use ($pageResourceDirectDuplicateEntryPdf): void {
        $pdf = $pageResourceDirectDuplicateEntryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'A',
            'Valid direct duplicate boundary font text',
            'Direct duplicate property raw glyph',
            'Valid direct duplicate boundary ActualText',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(2, $resources['resource_owner_object'] ?? null);
        $t->same(10, $resources['resource_object'] ?? null);
        $t->same(['Font', 'Properties'], $resources['categories'] ?? null);
        $t->same(['Fvalid'], $resources['font_names'] ?? null);
        $t->same(['ValidActual'], $resources['properties_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Stale direct duplicate font leak'));
        $t->same(false, str_contains($plainText, 'Current direct duplicate font leak'));
        $t->same(false, str_contains($plainText, 'Stale direct duplicate ActualText leak'));
        $t->same(false, str_contains($plainText, 'Current direct duplicate ActualText leak'));
        $t->same(false, str_contains(json_encode($resources, JSON_UNESCAPED_SLASHES) ?: '', 'Fdup'));
        $t->same(false, str_contains(json_encode($resources, JSON_UNESCAPED_SLASHES) ?: '', 'DupActual'));
    },
];
