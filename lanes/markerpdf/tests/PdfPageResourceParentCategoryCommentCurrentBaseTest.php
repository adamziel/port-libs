<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceParentCategoryCommentCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode parent/category comment resource CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceParentCategoryCommentCurrentBaseCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceParentCategoryCommentPdf = static function () use ($pageResourceParentCategoryCommentCMap): string {
    $content = 'BT /Fcat 12 Tf 72 720 Td <41> Tj T* /Span /CommentActual BDC <42> Tj EMC ET q /CommentForm Do Q';
    $form = 'BT /Fcat 12 Tf 12 24 Td <43> Tj ET';
    $cmap = $pageResourceParentCategoryCommentCMap([
        '41' => 'Comment parent inherited font text',
        '42' => 'Comment parent raw ActualText glyph',
        '43' => 'Comment parent inherited form text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 % parent object/generation split by PDF comment\n 0 % parent generation/R split by PDF comment\n R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CommentParentFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
        . "8 0 obj\n<< /ActualText 9 0 R >>\nendobj\n"
        . "9 0 obj\n(Comment parent inherited actual text)\nendobj\n"
        . "10 0 obj\n<< "
        . "/Font 20 % font category object/generation split by PDF comment\n 0 % font category generation/R split by PDF comment\n R "
        . "/XObject 21 % xobject category object/generation split by PDF comment\n 0 % xobject category generation/R split by PDF comment\n R "
        . "/Properties 22 % properties category object/generation split by PDF comment\n 0 % properties category generation/R split by PDF comment\n R "
        . ">>\nendobj\n"
        . "20 0 obj\n<< /Fcat 5 0 R >>\nendobj\n"
        . "21 0 obj\n<< /CommentForm 7 0 R >>\nendobj\n"
        . "22 0 obj\n<< /CommentActual 8 0 R >>\nendobj\n"
        . "%%EOF";
};

return [
    'treats comments as whitespace in page Parent and inherited resource category references' => static function (
        TestRunner $t
    ) use ($pageResourceParentCategoryCommentPdf): void {
        $pdf = $pageResourceParentCategoryCommentPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resources = $boundary[0]['resources'] ?? [];
        $expected = [
            'Comment parent inherited font text',
            'Comment parent inherited actual text',
            'Comment parent inherited form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
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
        $t->same(['Fcat'], $resources['font_names'] ?? null);
        $t->same(['CommentForm'], $resources['xobject_names'] ?? null);
        $t->same(['CommentActual'], $resources['properties_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Comment parent raw ActualText glyph'));
        $t->same(false, str_contains($plainText, 'CommentForm'));
        $t->same(false, str_contains($plainText, 'PageResourceParentCategoryCommentCurrentBaseCMap'));
    },
];
