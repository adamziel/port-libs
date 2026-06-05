<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Current structure link) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Stale structure link) Tj ET';

$pdf = "%PDF-1.7\n"
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

$pages = [
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

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$extractor = new PdfLinkAnnotationExtractor();
$links = $extractor->extractPageLinks($pdf);
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$currentSpan = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0] ?? [];
$encodedCurrentSpan = json_encode($currentSpan, JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-link-annotation-struct-tree-generation-boundary',
    'native_boundary' => 'Link annotation StructTree OBJR /Obj references are matched by object and generation before WordPress span promotion',
    'annotation_generations' => array_map(
        static fn (array $page): ?int => $page['annotations'][0]['annotation_generation'] ?? null,
        $annotations
    ),
    'link_generations' => array_map(
        static fn (array $page): ?int => $page['links'][0]['annotation_generation'] ?? null,
        $links
    ),
    'current_generation_structure_matched' => ($currentSpan['link_structure_parent']['title'] ?? null) === 'Current generation structure',
    'current_generation_action_context_matched' => ($currentSpan['link_actions_review'][0]['annotation_structure_parent']['actual_text'] ?? null) === 'current generation actual review',
    'stale_generation_structure_excluded_from_current_link' => !str_contains($encodedCurrentSpan, 'Stale generation structure')
        && !str_contains($encodedCurrentSpan, 'stale generation actual review'),
    'markdown' => $blocks[0]['text'] ?? '',
    'visible_text_excludes_structure_review' => !str_contains($plainText, 'Current generation structure')
        && !str_contains($plainText, 'Stale generation structure')
        && !str_contains($plainText, 'current generation actual review')
        && !str_contains($plainText, 'stale generation actual review'),
    'visible_text_excludes_link_targets' => !str_contains($plainText, 'current-structure-link')
        && !str_contains($plainText, 'stale-structure-link'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-link-annotation-struct-tree-generation-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . nl2br(htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
