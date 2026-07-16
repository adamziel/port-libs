<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceAnnotationAppearanceCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode annotation appearance resource CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceAnnotationAppearanceCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceAnnotationAppearancePdf = static function () use ($pageResourceAnnotationAppearanceCMap): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
    $emptyPageContent = '';
    $inheritedAppearance = 'BT /F1 10 Tf 0 18 Td <42> Tj ET q /InheritedAppearanceForm Do Q';
    $emptyResourceAppearance = 'q /EmptyAppearanceForm Do Q';
    $inheritedForm = 'BT /F1 9 Tf 0 0 Td <43> Tj ET';
    $emptyResourceForm = 'BT /F1 9 Tf 0 0 Td <44> Tj ET';
    $cMap = $pageResourceAnnotationAppearanceCMap([
        '41' => 'Page inherited resource text',
        '42' => 'Appearance direct inherited text',
        '43' => 'Appearance nested inherited form text',
        '44' => 'Explicit empty appearance resource leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [20 0 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [21 0 R] /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($emptyPageContent) . " >>\nstream\n{$emptyPageContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /AnnotationAppearanceInherited /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
        . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 160 40] /Length " . strlen($inheritedForm) . " >>\nstream\n{$inheritedForm}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /InheritedAppearanceForm 9 0 R /EmptyAppearanceForm 12 0 R >> >>\nendobj\n"
        . "12 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 160 40] /Length " . strlen($emptyResourceForm) . " >>\nstream\n{$emptyResourceForm}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Annot /Subtype /FreeText /Rect [72 660 240 700] /AP << /N 30 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /Annot /Subtype /FreeText /Rect [72 620 240 650] /AP << /N 31 0 R >> >>\nendobj\n"
        . "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 48] /Length " . strlen($inheritedAppearance) . " >>\nstream\n{$inheritedAppearance}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 48] /Resources << >> /Length " . strlen($emptyResourceAppearance) . " >>\nstream\n{$emptyResourceAppearance}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'inherits page resources into annotation appearance Forms that omit Resources without merging explicit empty appearances' => static function (
        TestRunner $t
    ) use ($pageResourceAnnotationAppearancePdf): void {
        $pdf = $pageResourceAnnotationAppearancePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $firstResources = $boundary[0]['resources'] ?? [];
        $secondResources = $boundary[1]['resources'] ?? [];
        $expected = [
            'Page inherited resource text',
            'Appearance direct inherited text',
            'Appearance nested inherited form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n\n", $extractor->naiveGetText($pdf));
        $t->same(2, count($boundary));
        $t->same(true, $firstResources['inherited'] ?? null);
        $t->same(2, $firstResources['resource_owner_object'] ?? null);
        $t->same(10, $firstResources['resource_object'] ?? null);
        $t->same(['Font', 'XObject'], $firstResources['categories'] ?? null);
        $t->same(['F1'], $firstResources['font_names'] ?? null);
        $t->same(['InheritedAppearanceForm', 'EmptyAppearanceForm'], $firstResources['xobject_names'] ?? null);
        $t->same(true, $secondResources['inherited'] ?? null);
        $t->same(2, $secondResources['resource_owner_object'] ?? null);
        $t->same(10, $secondResources['resource_object'] ?? null);
        $t->same(1, substr_count($plainText, 'Appearance nested inherited form text'));
        $t->same(false, str_contains($plainText, 'Explicit empty appearance resource leak'));
        $t->same(false, str_contains($plainText, 'InheritedAppearanceForm'));
        $t->same(false, str_contains($plainText, 'EmptyAppearanceForm'));
    },
];
