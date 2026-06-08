<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceAppearanceGenerationCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode annotation appearance generation CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceAnnotationAppearanceGenerationCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceAppearanceBasePdf = static function (
    string $annotationAppearance,
    string $extraObjects
) use ($pageResourceAppearanceGenerationCMap): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
    $cMap = $pageResourceAppearanceGenerationCMap([
        '41' => 'Page inherited resource text',
        '42' => 'Current appearance text',
        '43' => 'Stale appearance resource leak',
        '44' => 'Stale appearance dictionary leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [20 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /AppearanceGeneration /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 7 0 R >> >>\nendobj\n"
        . "20 0 obj\n<< /Type /Annot /Subtype /FreeText /Rect [72 660 240 700] /AP {$annotationAppearance} >>\nendobj\n"
        . $extraObjects
        . "%%EOF";
};

$staleNormalAppearancePdf = static function () use ($pageResourceAppearanceBasePdf): string {
    $staleAppearance = 'BT /F1 10 Tf 0 18 Td <43> Tj ET';

    return $pageResourceAppearanceBasePdf(
        '<< /N 30 1 R >>',
        "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 48] /Length " . strlen($staleAppearance) . " >>\nstream\n{$staleAppearance}\nendstream\nendobj\n"
    );
};

$validNonzeroNormalAppearancePdf = static function () use ($pageResourceAppearanceBasePdf): string {
    $staleAppearance = 'BT /F1 10 Tf 0 18 Td <43> Tj ET';
    $currentAppearance = 'BT /F1 10 Tf 0 18 Td <42> Tj ET';

    return $pageResourceAppearanceBasePdf(
        '<< /N 30 1 R >>',
        "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 48] /Length " . strlen($staleAppearance) . " >>\nstream\n{$staleAppearance}\nendstream\nendobj\n"
        . "30 1 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 48] /Length " . strlen($currentAppearance) . " >>\nstream\n{$currentAppearance}\nendstream\nendobj\n"
    );
};

$staleAppearanceDictionaryPdf = static function () use ($pageResourceAppearanceBasePdf): string {
    $staleAppearance = 'BT /F1 10 Tf 0 18 Td <44> Tj ET';

    return $pageResourceAppearanceBasePdf(
        '40 1 R',
        "40 0 obj\n<< /N 31 0 R >>\nendobj\n"
        . "31 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 48] /Length " . strlen($staleAppearance) . " >>\nstream\n{$staleAppearance}\nendstream\nendobj\n"
    );
};

return [
    'rejects stale annotation appearance N generations before inheriting page resources' => static function (
        TestRunner $t
    ) use ($staleNormalAppearancePdf): void {
        $pdf = $staleNormalAppearancePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];

        $t->same(['Page inherited resource text'], $extractor->extractTextLines($pdf));
        $t->same(['Page inherited resource text'], $extractor->extractTextRuns($pdf));
        $t->same('Page inherited resource text', $plainText);
        $t->same("Page inherited resource text\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same('resolved', $resources['status'] ?? null);
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(2, $resources['resource_owner_object'] ?? null);
        $t->same(10, $resources['resource_object'] ?? null);
        $t->same(['Font'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Stale appearance resource leak'));
    },
    'accepts current nonzero-generation annotation appearances with inherited page resources' => static function (
        TestRunner $t
    ) use ($validNonzeroNormalAppearancePdf): void {
        $pdf = $validNonzeroNormalAppearancePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $expected = [
            'Page inherited resource text',
            'Current appearance text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, substr_count($plainText, 'Current appearance text'));
        $t->same(false, str_contains($plainText, 'Stale appearance resource leak'));
    },
    'rejects stale indirect annotation AP dictionaries before N appearance lookup' => static function (
        TestRunner $t
    ) use ($staleAppearanceDictionaryPdf): void {
        $pdf = $staleAppearanceDictionaryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);

        $t->same(['Page inherited resource text'], $extractor->extractTextLines($pdf));
        $t->same(['Page inherited resource text'], $extractor->extractTextRuns($pdf));
        $t->same('Page inherited resource text', $plainText);
        $t->same("Page inherited resource text\n", $extractor->naiveGetText($pdf));
        $t->same(false, str_contains($plainText, 'Stale appearance dictionary leak'));
    },
];
