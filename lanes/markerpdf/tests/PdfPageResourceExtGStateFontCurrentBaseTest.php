<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceExtGStateFontCurrentBaseCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode focused ExtGState font CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceExtGStateFontCurrentBaseCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceExtGStateFontCurrentBasePdf = static function () use ($pageResourceExtGStateFontCurrentBaseCMap): string {
    $content = '/Parent#20Text gs BT 72 720 Td <41> Tj ET '
        . 'q /Private#20Text gs BT 72 700 Td <42> Tj ET Q '
        . 'BT 72 680 Td <43> Tj ET';
    $parentCMap = $pageResourceExtGStateFontCurrentBaseCMap([
        '41' => 'Inherited ExtGState font first',
        '43' => 'Inherited ExtGState font restored',
    ]);
    $privateCMap = $pageResourceExtGStateFontCurrentBaseCMap([
        '42' => 'Private ExtGState scoped text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /InheritedExtGStateFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($parentCMap) . " >>\nstream\n{$parentCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PrivateExtGStateFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($privateCMap) . " >>\nstream\n{$privateCMap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /Fparent 5 0 R /Fprivate 7 0 R >> /ExtGState << /Parent#20Text 11 0 R /Private#20Text 12 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /ExtGState /Font [/Fparent 12] >>\nendobj\n"
        . "12 0 obj\n<< /Type /ExtGState /Font [/Fprivate 9] >>\nendobj\n"
        . "%%EOF";
};

$pageResourceExtGStateFormFontCurrentBasePdf = static function () use ($pageResourceExtGStateFontCurrentBaseCMap): string {
    $content = 'q /ExtForm Do Q';
    $formContent = '/Form#20Text gs BT 12 24 Td <41> Tj ET';
    $staleCMap = $pageResourceExtGStateFontCurrentBaseCMap([
        '41' => 'Stale page ExtGState font leak',
    ]);
    $formCMap = $pageResourceExtGStateFontCurrentBaseCMap([
        '41' => 'Form-local ExtGState font text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /StalePageExtGStateFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($staleCMap) . " >>\nstream\n{$staleCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /FormExtGStateFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($formCMap) . " >>\nstream\n{$formCMap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /Fform 5 0 R >> /XObject << /ExtForm 20 0 R >> >>\nendobj\n"
        . "20 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 200 80] /Resources << /Font << /Fform 7 0 R >> /ExtGState << /Form#20Text << /Type /ExtGState /Font [/Fform 13] >> >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'applies inherited ExtGState Font arrays before page text extraction and restores q state' => static function (
        TestRunner $t
    ) use ($pageResourceExtGStateFontCurrentBasePdf): void {
        $pdf = $pageResourceExtGStateFontCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $expected = [
            'Inherited ExtGState font first',
            'Private ExtGState scoped text',
            'Inherited ExtGState font restored',
        ];
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $styledPages = $extractor->extractStyledTextPages($pdf);
        $styledText = array_map(
            static fn (array $block): string => implode('', array_column($block['lines'][0]['spans'] ?? [], 'text')),
            $styledPages[0]['blocks'] ?? []
        );
        $resources = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf)[0]['resources'] ?? [];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $runs);
        $t->same($expected, $styledText);
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(['ExtGState', 'Font'], array_values(array_intersect(['ExtGState', 'Font'], $resources['categories'] ?? [])));
        $t->same(['Parent Text', 'Private Text'], $resources['extgstate_names'] ?? null);
        $t->same(false, str_contains($plainText, '<41>'));
        $t->same(false, str_contains($plainText, '<42>'));
        $t->same(false, str_contains($plainText, '<43>'));
    },
    'aliases form-local ExtGState Font arrays before inherited page fonts can leak' => static function (
        TestRunner $t
    ) use ($pageResourceExtGStateFormFontCurrentBasePdf): void {
        $pdf = $pageResourceExtGStateFormFontCurrentBasePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $styledPages = $extractor->extractStyledTextPages($pdf);
        $styledText = array_map(
            static fn (array $block): string => implode('', array_column($block['lines'][0]['spans'] ?? [], 'text')),
            $styledPages[0]['blocks'] ?? []
        );

        $t->same(['Form-local ExtGState font text'], $extractor->extractTextLines($pdf));
        $t->same(['Form-local ExtGState font text'], $extractor->extractTextRuns($pdf));
        $t->same(['Form-local ExtGState font text'], $styledText);
        $t->same("Form-local ExtGState font text", $plainText);
        $t->same("Form-local ExtGState font text\n", $extractor->naiveGetText($pdf));
        $t->same(false, str_contains($plainText, 'Stale page ExtGState font leak'));
        $t->same(false, str_contains($plainText, '<41>'));
    },
];
