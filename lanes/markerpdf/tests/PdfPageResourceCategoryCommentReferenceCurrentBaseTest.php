<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceCategoryCommentReferenceCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode page resource category comment-reference CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceCategoryCommentReferenceCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceCategoryCommentReferencePdf = static function () use ($pageResourceCategoryCommentReferenceCMap): string {
    $content = 'BT /Fcat 12 Tf 72 720 Td <41> Tj T* /Span /CatActual BDC <42> Tj EMC ET q /CatForm Do Q';
    $form = 'BT /Fcat 12 Tf 12 24 Td <43> Tj ET';
    $cmap = $pageResourceCategoryCommentReferenceCMap([
        '41' => 'Category comment inherited font text',
        '42' => 'Category comment raw property glyph',
        '43' => 'Category comment inherited form text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CategoryCommentFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
        . "8 0 obj\n<< /ActualText 33 % actual text object/generation split by PDF comment\n 0 % actual text generation/R split by PDF comment\n R >>\nendobj\n"
        . "10 0 obj\n<< "
        . "/Font 30 % font category object/generation split by PDF comment\n 0 % font category generation/R split by PDF comment\n R "
        . "/XObject 31 % xobject category object/generation split by PDF comment\n 0 % xobject category generation/R split by PDF comment\n R "
        . "/Properties 32 % properties category object/generation split by PDF comment\n 0 % properties category generation/R split by PDF comment\n R "
        . ">>\nendobj\n"
        . "30 0 obj\n<< /Fcat 5 0 R >>\nendobj\n"
        . "31 0 obj\n<< /CatForm 7 0 R >>\nendobj\n"
        . "32 0 obj\n<< /CatActual 8 0 R >>\nendobj\n"
        . "33 0 obj\n(Category comment inherited actual text)\nendobj\n"
        . "%%EOF";
};

return [
    'treats PDF comments as whitespace inside inherited resource category references' => static function (
        TestRunner $t
    ) use ($pageResourceCategoryCommentReferencePdf): void {
        $pdf = $pageResourceCategoryCommentReferencePdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Category comment inherited font text',
            'Category comment inherited actual text',
            'Category comment inherited form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, count($boundary));
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(2, $resources['resource_owner_object'] ?? null);
        $t->same(10, $resources['resource_object'] ?? null);
        $t->same(['Font', 'XObject', 'Properties'], $resources['categories'] ?? null);
        $t->same(['Fcat'], $resources['font_names'] ?? null);
        $t->same(['CatForm'], $resources['xobject_names'] ?? null);
        $t->same(['CatActual'], $resources['properties_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Category comment raw property glyph'));
        $t->same(false, str_contains($plainText, 'CatForm'));
        $t->same(false, str_contains($plainText, 'CatActual'));
    },
];
