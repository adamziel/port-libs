<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$linkStructTreeGenerationBoundaryPdf = static function (): string {
    $pageOneContent = 'BT /F1 12 Tf 72 720 Td (Current structure link) Tj ET';
    $pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Stale structure link) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [7 1 R] /Contents 5 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [7 0 R] /Contents 6 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /StructParent 30 /Rect [72 700 214 718] /Contents (Stale generation link review) /A << /S /URI /URI (https://example.com/stale-structure-link) >> >>\nendobj\n"
        . "7 1 obj\n<< /Type /Annot /Subtype /Link /StructParent 31 /Rect [72 700 222 718] /Contents (Current generation link review) /A << /S /URI /URI (https://example.com/current-structure-link) >> >>\nendobj\n"
        . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /ReviewLink /Link >> /K [40 0 R 41 0 R] >>\nendobj\n"
        . "40 0 obj\n<< /Type /StructElem /S /ReviewLink /Pg 4 0 R /T (Stale generation structure) /ActualText (stale generation actual review) /K << /Type /OBJR /Obj 7 0 R >> >>\nendobj\n"
        . "41 0 obj\n<< /Type /StructElem /S /ReviewLink /Pg 3 0 R /T (Current generation structure) /ActualText (current generation actual review) /K << /Type /OBJR /Obj 7 1 R >> >>\nendobj\n"
        . "%%EOF";
};

$linkStructTreeGenerationBoundaryPages = static function (): array {
    return [
        [
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 222.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 222.0, 718.0],
                    'spans' => [
                        ['text' => 'Current structure link', 'bbox' => [72.0, 700.0, 222.0, 718.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
        [
            'pnum' => 1,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 214.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 214.0, 718.0],
                    'spans' => [
                        ['text' => 'Stale structure link', 'bbox' => [72.0, 700.0, 214.0, 718.0], 'font' => 'Helvetica'],
                    ],
                ]],
            ]],
        ],
    ];
};

return [
    'keeps Link annotation StructTree OBJR references generation-exact before WordPress span promotion' => static function (TestRunner $t) use (
        $linkStructTreeGenerationBoundaryPdf,
        $linkStructTreeGenerationBoundaryPages
    ): void {
        $pdf = $linkStructTreeGenerationBoundaryPdf();
        $encoded = static fn (array $rows): string => json_encode($rows, JSON_UNESCAPED_SLASHES) ?: '';

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(2, count($annotations));
        $t->same([7, 7], [
            $annotations[0]['annotations'][0]['annotation_object'],
            $annotations[1]['annotations'][0]['annotation_object'],
        ]);
        $t->same([1, 0], [
            $annotations[0]['annotations'][0]['annotation_generation'],
            $annotations[1]['annotations'][0]['annotation_generation'],
        ]);
        $t->same('Current generation structure', $annotations[0]['annotations'][0]['structure_parent']['title']);
        $t->same(31, $annotations[0]['annotations'][0]['struct_parent']);
        $t->same(31, $annotations[0]['annotations'][0]['structure_parent']['key']);
        $t->same(true, $annotations[0]['annotations'][0]['structure_parent']['parent_tree_key_missing']);
        $t->same('current generation actual review', $annotations[0]['annotations'][0]['structure_parent']['actual_text']);
        $t->same(true, $annotations[0]['annotations'][0]['structure_parent']['current_annotation_object_ref_matched']);
        $t->same('Stale generation structure', $annotations[1]['annotations'][0]['structure_parent']['title']);
        $t->same(30, $annotations[1]['annotations'][0]['struct_parent']);
        $t->same(30, $annotations[1]['annotations'][0]['structure_parent']['key']);
        $t->same('stale generation actual review', $annotations[1]['annotations'][0]['structure_parent']['actual_text']);
        $t->same(true, $annotations[1]['annotations'][0]['structure_parent']['current_annotation_object_ref_matched']);

        $extractor = new PdfLinkAnnotationExtractor();
        $links = $extractor->extractPageLinks($pdf);
        $t->same(2, count($links));
        $currentLink = $links[0]['links'][0];
        $staleLink = $links[1]['links'][0];

        $t->same(1, $currentLink['annotation_generation']);
        $t->same('https://example.com/current-structure-link', $currentLink['uri']);
        $t->same('Current generation structure', $currentLink['structure_parent']['title']);
        $t->same('current generation actual review', $currentLink['actions'][0]['annotation_structure_parent']['actual_text']);
        $t->same(true, $currentLink['structure_parent']['current_annotation_object_ref_matched']);
        $t->same(0, $staleLink['annotation_generation']);
        $t->same('https://example.com/stale-structure-link', $staleLink['uri']);
        $t->same('Stale generation structure', $staleLink['structure_parent']['title']);

        $linkedPages = $extractor->applyLinksToPages($linkStructTreeGenerationBoundaryPages(), $pdf);
        $currentSpan = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $staleSpan = $linkedPages[1]['blocks'][0]['lines'][0]['spans'][0];
        $t->same(1, $currentSpan['link_annotation_generation']);
        $t->same('https://example.com/current-structure-link', $currentSpan['link_uri']);
        $t->same('Current generation structure', $currentSpan['link_structure_parent']['title']);
        $t->same('current generation actual review', $currentSpan['link_actions_review'][0]['annotation_structure_parent']['actual_text']);
        $t->same(false, str_contains($encoded([$currentSpan]), 'Stale generation structure'));
        $t->same(0, $staleSpan['link_annotation_generation']);
        $t->same('Stale generation structure', $staleSpan['link_structure_parent']['title']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same(
            "[Current structure link](https://example.com/current-structure-link)\n"
                . '[Stale structure link](https://example.com/stale-structure-link)',
            $blocks[0]['text']
        );

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Current structure link', $plainText);
        $t->contains('Stale structure link', $plainText);
        foreach ([
            'current-structure-link',
            'stale-structure-link',
            'Current generation structure',
            'Stale generation structure',
            'current generation actual review',
            'stale generation actual review',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
