<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$notePayload = '<wp-export><post id="annotation-objr-note"/></wp-export>';
$linkPayload = '<wp-export><post id="annotation-objr-link"/></wp-export>';
$noteChecksum = strtoupper(hash('md5', $notePayload));
$linkChecksum = strtoupper(hash('md5', $linkPayload));
$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Visible associated OBJR source) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Visible associated OBJR target) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 15 0 R >> /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R /PageLabels << /Nums [0 << /P (objr-) /S /D /St 4 >> 1 << /P (target-) /S /D /St 8 >>] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 14 0 R /Annots [6 0 R 7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 17 0 R /Dur 2.5 /Trans << /S /Fade /D 0.5 >> >>\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Type /Annot /Subtype /Text /Rect [72 690 260 730] /Contents (OBJR-only note review text) /T (OBJR QA) /NM (objr-note) >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /StructParent 23 /Rect [72 650 260 675] /Contents (OBJR fallback link review text) /A << /S /GoTo /D (objr-target) >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (objr-note-source.xml) /Desc (OBJR note source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($notePayload) . " /CheckSum <{$noteChecksum}> /ModDate (D:20260602222500Z) >> /Length " . strlen($notePayload) . " >>\nstream\n{$notePayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Filespec /F (objr-link-source.xml) /Desc (OBJR link source export) /AFRelationship /Source /EF << /F 13 0 R >> >>\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($linkPayload) . " /CheckSum <{$linkChecksum}> /ModDate (D:20260602222600Z) >> /Length " . strlen($linkPayload) . " >>\nstream\n{$linkPayload}\nendstream\nendobj\n"
    . "14 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "15 0 obj\n<< /Names [(objr-target) 16 0 R] >>\nendobj\n"
    . "16 0 obj\n[4 0 R /FitH 700]\nendobj\n"
    . "17 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /ReviewNote /Span /ReviewLink /Link >> /ParentTree 31 0 R /K [40 0 R 41 0 R] >>\nendobj\n"
    . "31 0 obj\n<< /Nums [] >>\nendobj\n"
    . "40 0 obj\n<< /Type /StructElem /S /ReviewNote /Pg 3 0 R /T (OBJR-only annotation structure) /Alt (OBJR-only alternate review) /AF [10 0 R] /K << /Type /OBJR /Obj 6 0 R >> >>\nendobj\n"
    . "41 0 obj\n<< /Type /StructElem /S /ReviewLink /Pg 3 0 R /T (OBJR fallback link structure) /ActualText (OBJR fallback actual review) /AF [12 0 R] /K [<< /Type /OBJR /Obj 7 0 R >>] >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 650.0, 260.0, 675.0],
        'lines' => [[
            'bbox' => [72.0, 650.0, 260.0, 675.0],
            'spans' => [[
                'text' => 'Visible associated OBJR source',
                'bbox' => [72.0, 650.0, 260.0, 675.0],
                'font' => 'Helvetica',
            ]],
        ]],
    ]],
]];

$annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

$note = $annotationPages[0]['annotations'][0] ?? [];
$link = $annotationPages[0]['annotations'][1] ?? [];
$linkAction = $link['actions'][0] ?? [];
$noteFile = $note['structure_parent']['associated_files'][0] ?? null;
$linkFile = $linkAction['annotation_associated_files'][0] ?? null;
$span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0] ?? [];

if (($note['structure_parent']['source'] ?? null) !== 'annotation_struct_tree_objr'
    || !is_array($noteFile)
    || ($noteFile['filename'] ?? null) !== 'objr-note-source.xml'
    || ($noteFile['checksum_matches'] ?? null) !== true
    || array_key_exists('content', $noteFile)
) {
    throw new RuntimeException('Expected OBJR-only annotation StructElem associated-file review metadata without payload bytes.');
}
if (($link['structure_parent']['source'] ?? null) !== 'annotation_struct_tree_objr_parent_tree_fallback'
    || ($linkAction['annotation_struct_parent'] ?? null) !== 23
    || !is_array($linkFile)
    || ($linkFile['filename'] ?? null) !== 'objr-link-source.xml'
    || ($linkFile['checksum_matches'] ?? null) !== true
    || ($span['link_destination'] ?? null) !== 'objr-target'
) {
    throw new RuntimeException('Expected ParentTree-missing link annotation to carry StructTree OBJR review metadata.');
}
if (str_contains($plainText, '<wp-export>')
    || str_contains($plainText, 'objr-note-source.xml')
    || str_contains($plainText, 'objr-link-source.xml')
    || str_contains($plainText, 'OBJR-only annotation structure')
    || str_contains($plainText, 'OBJR fallback link structure')
    || str_contains($plainText, 'OBJR-only note review text')
    || str_contains($plainText, 'OBJR fallback link review text')
) {
    throw new RuntimeException('Expected StructTree, annotation, and attachment review metadata to stay out of visible WordPress text.');
}

$json = static fn (array $value): string => htmlspecialchars(
    json_encode($value, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-page-annotation-associated-structtree-review-currentbase ' . $json([
    'support_component' => 'native-pdf-page-annotation-associated-structtree-review',
    'native_boundary' => 'current page annotation objects referenced by StructElem OBJR rows carry StructTree and associated FileSpec review metadata when StructParent ParentTree rows are missing',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'note_annotation_object' => $note['annotation_object'] ?? null,
    'note_structure_source' => $note['structure_parent']['source'] ?? null,
    'note_associated_filename' => $noteFile['filename'] ?? null,
    'note_payload_content_omitted' => is_array($noteFile) && !array_key_exists('content', $noteFile),
    'link_annotation_object' => $link['annotation_object'] ?? null,
    'link_struct_parent' => $link['struct_parent'] ?? null,
    'link_structure_source' => $link['structure_parent']['source'] ?? null,
    'link_parent_tree_key_missing' => $link['structure_parent']['parent_tree_key_missing'] ?? null,
    'link_destination' => $span['link_destination'] ?? null,
    'link_destination_page' => $span['link_destination_page'] ?? null,
    'link_associated_filename' => $linkFile['filename'] ?? null,
    'link_payload_content_omitted' => is_array($linkFile) && !array_key_exists('content', $linkFile),
    'visible_text_excludes_review_metadata' => !str_contains($plainText, '<wp-export>')
        && !str_contains($plainText, 'objr-note-source.xml')
        && !str_contains($plainText, 'objr-link-source.xml')
        && !str_contains($plainText, 'OBJR-only annotation structure')
        && !str_contains($plainText, 'OBJR fallback link structure'),
]) . " -->\n";

foreach ($blocks as $block) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

foreach ($lines as $line) {
    if ($line === ($blocks[0]['text'] ?? null)) {
        continue;
    }
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf:annotation-associated-structtree-review ' . $json([
    'note_structure_parent' => $note['structure_parent'] ?? [],
    'link_actions' => array_map(static fn (array $row): array => [
        'action_type' => $row['action_type'] ?? null,
        'safety' => $row['safety'] ?? null,
        'destination' => $row['destination'] ?? null,
        'destination_page' => $row['destination_page'] ?? null,
        'annotation_struct_parent' => $row['annotation_struct_parent'] ?? null,
        'annotation_associated_file_count' => $row['annotation_associated_file_count'] ?? null,
    ], $link['actions'] ?? []),
]) . " -->\n";
