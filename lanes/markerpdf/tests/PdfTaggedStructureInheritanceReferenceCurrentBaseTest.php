<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$taggedStructureInheritanceReferencePdf = static function (): string {
    $content = 'BT /F1 12 Tf '
        . '/Body << /MCID 0 /Alt (Direct marked alternate text) >> BDC '
        . '72 720 Td (Glyph noise should not win) Tj EMC ET';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /MarkInfo << /Marked true /UserProperties true >> /StructTreeRoot 30 0 R /PageLabels << /Nums [0 << /P (inherit-) /S /D /St 4 >>] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 9 /Contents 5 0 R /Annots [7 0 R] >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /StructParent 12 /Rect [72 700 238 720] /Contents (Private link review text) /A << /S /URI /URI (https://example.com/structure-inheritance) >> >>\nendobj\n"
        . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Article /Sect /Body /P /LinkRef /Link /FigureRef /Figure >> /ClassMap << /parentClass 80 0 R /childClass 81 0 R >> /ParentTree 31 0 R /K 40 0 R >>\nendobj\n"
        . "31 0 obj\n<< /Nums [9 [41 0 R] 12 42 0 R] >>\nendobj\n"
        . "40 0 obj\n<< /Type /StructElem /S /Article /Pg 3 0 R /Lang (es-MX) /C /parentClass /A << /O /Layout /TextAlign /Center >> /K [41 0 R 42 0 R] >>\nendobj\n"
        . "41 0 obj\n<< /Type /StructElem /S /Body /Pg 3 0 R /C /childClass /K 0 /Ref [43 0 R] >>\nendobj\n"
        . "42 0 obj\n<< /Type /StructElem /S /LinkRef /Pg 3 0 R /K << /Type /OBJR /Obj 7 0 R >> /Ref 43 0 R >>\nendobj\n"
        . "43 0 obj\n<< /Type /StructElem /S /FigureRef /Pg 3 0 R /Alt (Referenced figure alternate) /ActualText (Referenced actual review) /C /childClass /A << /O /Layout /BBox [10 20 30 40] >> >>\nendobj\n"
        . "80 0 obj\n<< /O /Layout /Placement /Block /WritingMode /LrTb >>\nendobj\n"
        . "81 0 obj\n<< /O /UserProperties /P [<< /N (WP Block) /V (core/paragraph) /F (Paragraph block) >>] >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

$taggedStructureInheritanceReferencePages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 238.0, 720.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 238.0, 720.0],
                'spans' => [[
                    'text' => 'Direct marked alternate text',
                    'bbox' => [72.0, 700.0, 238.0, 720.0],
                    'font' => 'Helvetica',
                ]],
            ]],
        ]],
    ]];
};

return [
    'propagates inherited StructElem language classes attributes and references into review output' => static function (TestRunner $t) use (
        $taggedStructureInheritanceReferencePdf,
        $taggedStructureInheritanceReferencePages
    ): void {
        $pdf = $taggedStructureInheritanceReferencePdf();
        $textExtractor = new PdfTextExtractor();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $pages = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $linkedPages = (new PdfLinkAnnotationExtractor())->applyLinksToPages(
            $taggedStructureInheritanceReferencePages(),
            $pdf
        );

        $elementsByObject = [];
        foreach ($metadata['structure_tree']['elements'] as $element) {
            $elementsByObject[$element['object']] = $element;
        }

        $body = $elementsByObject[41];
        $t->same('es-MX', $body['language']);
        $t->same(true, $body['language_inherited']);
        $t->same(['parentClass', 'childClass'], $body['classes']);
        $t->same(['parentClass'], $body['inherited_classes']);
        $t->same(true, $body['classes_inherited']);
        $t->same(3, $body['attribute_count']);
        $t->same(true, $body['attributes_inherited']);
        $t->same('parentClass', $body['attributes'][0]['class']);
        $t->same(true, $body['attributes'][0]['inherited']);
        $t->same('Center', $body['attributes'][1]['values']['TextAlign']);
        $t->same(true, $body['attributes'][1]['inherited']);
        $t->same('childClass', $body['attributes'][2]['class']);
        $t->same('WP Block', $body['attributes'][2]['properties'][0]['name']);
        $t->same('core/paragraph', $body['attributes'][2]['properties'][0]['value']);
        $t->same(1, $body['reference_count']);
        $t->same('Referenced figure alternate', $body['references'][0]['alternate_text']);
        $t->same('Referenced actual review', $body['references'][0]['actual_text']);
        $t->same([10, 20, 30, 40], $body['references'][0]['attributes'][1]['values']['BBox']);

        $marked = $pages[0]['structure_marked_content'][0];
        $t->same(['parentClass', 'childClass'], $marked['classes']);
        $t->same(true, $marked['attributes_inherited']);
        $t->same('Referenced figure alternate', $marked['references'][0]['alternate_text']);

        $linkStructure = $annotations[0]['annotations'][0]['structure_parent'];
        $t->same('LinkRef', $linkStructure['raw_role']);
        $t->same('Link', $linkStructure['role']);
        $t->same('es-MX', $linkStructure['language']);
        $t->same(['parentClass'], $linkStructure['classes']);
        $t->same(true, $linkStructure['attributes_inherited']);
        $t->same('Referenced figure alternate', $linkStructure['references'][0]['alternate_text']);
        $t->same(
            'Referenced actual review',
            $annotations[0]['annotations'][0]['actions'][0]['annotation_structure_parent']['references'][0]['actual_text']
        );

        $linkedSpan = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $t->same('https://example.com/structure-inheritance', $linkedSpan['link_uri']);
        $t->same('Referenced figure alternate', $linkedSpan['link_structure_parent']['references'][0]['alternate_text']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('[Direct marked alternate text](https://example.com/structure-inheritance)', $blocks[0]['text']);
        $t->same(['Direct marked alternate text'], $textExtractor->extractTextLines($pdf));
        $plainText = $textExtractor->extractPlainText($pdf);
        $t->contains('Direct marked alternate text', $plainText);
        foreach ([
            'Glyph noise should not win',
            'Private link review text',
            'Referenced figure alternate',
            'Referenced actual review',
            'core/paragraph',
            'Paragraph block',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }
    },
];
