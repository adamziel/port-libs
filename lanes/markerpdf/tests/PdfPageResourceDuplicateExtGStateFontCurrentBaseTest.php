<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceDuplicateExtGStateFontCurrentBaseCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode duplicate ExtGState font CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceDuplicateExtGStateFontCurrentBaseCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceDuplicateExtGStateFontCurrentBasePdf = static function () use ($pageResourceDuplicateExtGStateFontCurrentBaseCMap): string {
    $content = '/Dup#20Text gs BT 72 720 Td <41> Tj ET '
        . '/Valid#20Text gs BT 72 700 Td <42> Tj ET';
    $staleCMap = $pageResourceDuplicateExtGStateFontCurrentBaseCMap([
        '41' => 'Stale duplicate ExtGState font leak',
    ]);
    $currentCMap = $pageResourceDuplicateExtGStateFontCurrentBaseCMap([
        '41' => 'Current duplicate ExtGState font leak',
    ]);
    $validCMap = $pageResourceDuplicateExtGStateFontCurrentBaseCMap([
        '42' => 'Valid inherited ExtGState font text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 20 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StaleDuplicateExtGStateFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CurrentDuplicateExtGStateFont /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ValidExtGStateFont /Encoding /Identity-H /ToUnicode 10 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($staleCMap) . " >>\nstream\n{$staleCMap}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($currentCMap) . " >>\nstream\n{$currentCMap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Length " . strlen($validCMap) . " >>\nstream\n{$validCMap}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Type /ExtGState /Font [/Fstale 12] >>\nendobj\n"
        . "12 0 obj\n<< /Type /ExtGState /Font [/Fcurrent 13] >>\nendobj\n"
        . "13 0 obj\n<< /Type /ExtGState /Font [/Fvalid 14] >>\nendobj\n"
        . "20 0 obj\n<< /Font << /Fstale 5 0 R /Fcurrent 6 0 R /Fvalid 7 0 R >> /ExtGState << /Dup#20Text 11 0 R /Dup#20Text 12 0 R /Valid#20Text 13 0 R >> >>\nendobj\n"
        . "%%EOF";
};

return [
    'rejects duplicate inherited ExtGState font names before gs text-state leaks stale mappings' => static function (
        TestRunner $t
    ) use ($pageResourceDuplicateExtGStateFontCurrentBasePdf): void {
        $pdf = $pageResourceDuplicateExtGStateFontCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $styledPages = $extractor->extractStyledTextPages($pdf);
        $styledText = array_map(
            static fn (array $block): string => implode('', array_column($block['lines'][0]['spans'] ?? [], 'text')),
            $styledPages[0]['blocks'] ?? []
        );
        $resources = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf)[0]['resources'] ?? [];
        $expected = [
            'A',
            'Valid inherited ExtGState font text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same($expected, $styledText);
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(2, $resources['resource_owner_object'] ?? null);
        $t->same(20, $resources['resource_object'] ?? null);
        $t->same(['ExtGState', 'Font'], array_values(array_intersect(['ExtGState', 'Font'], $resources['categories'] ?? [])));
        $t->same(['Fstale', 'Fcurrent', 'Fvalid'], $resources['font_names'] ?? null);
        $t->same(['Valid Text'], $resources['extgstate_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Stale duplicate ExtGState font leak'));
        $t->same(false, str_contains($plainText, 'Current duplicate ExtGState font leak'));
        $t->same(false, str_contains(json_encode($resources, JSON_UNESCAPED_SLASHES) ?: '', 'Dup Text'));
    },
];
