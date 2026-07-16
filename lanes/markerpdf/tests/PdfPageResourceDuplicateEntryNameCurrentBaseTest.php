<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceDuplicateEntryNameCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode duplicate resource entry-name CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceDuplicateEntryNameCurrentBaseCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceDuplicateEntryNamePdf = static function () use ($pageResourceDuplicateEntryNameCMap): string {
    $content = 'BT /Fdup 12 Tf 72 720 Td <41> Tj T* '
        . '/Fvalid 12 Tf <42> Tj T* '
        . '/Span /DupActual BDC <43> Tj EMC T* '
        . '/Span /ValidActual BDC <44> Tj EMC ET';
    $staleCMap = $pageResourceDuplicateEntryNameCMap([
        '41' => 'Stale duplicate-entry font leak',
    ]);
    $currentCMap = $pageResourceDuplicateEntryNameCMap([
        '41' => 'Current duplicate-entry font leak',
    ]);
    $validCMap = $pageResourceDuplicateEntryNameCMap([
        '42' => 'Valid duplicate-entry font text',
        '43' => 'Duplicate property raw glyph',
        '44' => 'Valid duplicate-entry actual glyph',
    ]);
    $staleImagePayload = 'stale duplicate image payload';
    $currentImagePayload = 'current duplicate image payload';
    $validImagePayload = 'valid image payload';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StaleDuplicateEntryFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($staleCMap) . " >>\nstream\n{$staleCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CurrentDuplicateEntryFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($currentCMap) . " >>\nstream\n{$currentCMap}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ValidDuplicateEntryFont /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($validCMap) . " >>\nstream\n{$validCMap}\nendstream\nendobj\n"
        . "12 0 obj\n<< /ActualText (Stale duplicate-entry ActualText leak) >>\nendobj\n"
        . "13 0 obj\n<< /ActualText (Current duplicate-entry ActualText leak) >>\nendobj\n"
        . "14 0 obj\n<< /ActualText (Valid duplicate-entry ActualText) >>\nendobj\n"
        . "15 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Length " . strlen($staleImagePayload) . " >>\nstream\n{$staleImagePayload}\nendstream\nendobj\n"
        . "16 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length " . strlen($currentImagePayload) . " >>\nstream\n{$currentImagePayload}\nendstream\nendobj\n"
        . "17 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Length " . strlen($validImagePayload) . " >>\nstream\n{$validImagePayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< "
        . "/Font << /Fdup 5 0 R /Fdup 7 0 R /Fvalid 9 0 R >> "
        . "/Properties << /DupActual 12 0 R /DupActual 13 0 R /ValidActual 14 0 R >> "
        . "/XObject << /DupImage 15 0 R /DupImage 16 0 R /ValidImage 17 0 R >> "
        . ">>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects duplicate inherited resource entry names before font maps ActualText and page metadata' => static function (
        TestRunner $t
    ) use ($pageResourceDuplicateEntryNamePdf): void {
        $pdf = $pageResourceDuplicateEntryNamePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'A',
            'Valid duplicate-entry font text',
            'Duplicate property raw glyph',
            'Valid duplicate-entry ActualText',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(2, $resources['resource_owner_object'] ?? null);
        $t->same(10, $resources['resource_object'] ?? null);
        $t->same(0, $resources['resource_generation'] ?? null);
        $t->same(['Font', 'Properties', 'XObject'], $resources['categories'] ?? null);
        $t->same(['Fvalid'], $resources['font_names'] ?? null);
        $t->same(['ValidActual'], $resources['properties_names'] ?? null);
        $t->same(['ValidImage'], $resources['xobject_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Stale duplicate-entry font leak'));
        $t->same(false, str_contains($plainText, 'Current duplicate-entry font leak'));
        $t->same(false, str_contains($plainText, 'Stale duplicate-entry ActualText leak'));
        $t->same(false, str_contains($plainText, 'Current duplicate-entry ActualText leak'));
        $t->same(false, str_contains(json_encode($resources, JSON_UNESCAPED_SLASHES) ?: '', 'DupImage'));
        $t->same(false, str_contains(json_encode($resources, JSON_UNESCAPED_SLASHES) ?: '', 'DupActual'));
        $t->same(false, str_contains(json_encode($resources, JSON_UNESCAPED_SLASHES) ?: '', 'Fdup'));
    },
];
