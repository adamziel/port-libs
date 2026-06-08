<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceCommentNullCMap = static function (array $entries): string {
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
            throw new RuntimeException('Unable to encode comment-null resource CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceCommentNullCurrentBaseCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceCommentNullInheritancePdf = static function () use ($pageResourceCommentNullCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj T* /Span /NullActual BDC <42> Tj EMC ET q /ParentForm Do Q';
    $form = 'BT /F1 12 Tf 12 24 Td <43> Tj ET';
    $cmap = $pageResourceCommentNullCMap([
        '41' => 'Comment null inherited font text',
        '42' => 'Comment null physical glyph leak',
        '43' => 'Comment null inherited form text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources 11 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CommentNullResource /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 260 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
        . "8 0 obj\n<< /ActualText (Comment null inherited actual text) >>\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /ParentForm 7 0 R >> /Properties << /NullActual 8 0 R >> >>\nendobj\n"
        . "11 0 obj\n% page-local resource null wrapper emitted by an incremental producer\nnull\n% trailing resource-null comment\nendobj\n"
        . "%%EOF";
};

return [
    'inherits ancestor resources through comment-wrapped null page Resources objects' => static function (
        TestRunner $t
    ) use ($pageResourceCommentNullInheritancePdf): void {
        $pdf = $pageResourceCommentNullInheritancePdf();
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
            'Comment null inherited font text',
            'Comment null inherited actual text',
            'Comment null inherited form text',
        ];

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same($expected, $styledLines);
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, count($boundary));
        $t->same('resolved', $resources['status'] ?? null);
        $t->same(true, $resources['resolved'] ?? null);
        $t->same(true, $resources['inherited'] ?? null);
        $t->same(2, $resources['resource_owner_object'] ?? null);
        $t->same(10, $resources['resource_object'] ?? null);
        $t->same(0, $resources['resource_generation'] ?? null);
        $t->same([3, 2], $resources['resource_lookup_objects'] ?? null);
        $t->same(['Font', 'XObject', 'Properties'], $resources['categories'] ?? null);
        $t->same(['F1'], $resources['font_names'] ?? null);
        $t->same(['ParentForm'], $resources['xobject_names'] ?? null);
        $t->same(['NullActual'], $resources['properties_names'] ?? null);
        $t->same(false, str_contains($plainText, 'Comment null physical glyph leak'));
        $t->same(false, str_contains($plainText, 'ParentForm'));
        $t->same(false, str_contains($plainText, 'PageResourceCommentNullCurrentBaseCMap'));
    },
];
