<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="annotation-action"/></wp-export>';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Review attached action) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Local action target page) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 13 0 R >> /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 15 0 R /Annots [6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 16 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Type /Annot /Subtype /Link /StructParent 17 /Rect [72 700 230 724] /Contents (Action attachment review note) /A << /S /URI /URI (https://example.com/attachment-action) /Next 12 0 R >> /AA << /E << /S /JavaScript /JS (actionHoverReview\\(\\)) >> /U << /S /GoToR /F 20 0 R /D (remote-action-target) >> >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (annotation-action-source.xml) /Desc (Annotation action source file) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602205800Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /S /GoTo /D (local-action-target) >>\nendobj\n"
    . "13 0 obj\n<< /Names [(local-action-target) 14 0 R] >>\nendobj\n"
    . "14 0 obj\n[4 0 R /FitH 720]\nendobj\n"
    . "15 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "16 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (related-action.pdf) >>\nendobj\n"
    . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /ReviewActionLink /Link >> /ParentTree 31 0 R /K [40 0 R] >>\nendobj\n"
    . "31 0 obj\n<< /Nums [17 40 0 R] >>\nendobj\n"
    . "40 0 obj\n<< /Type /StructElem /S /ReviewActionLink /Pg 3 0 R /T (Annotation action structure) /Alt (Annotation action alternate review) /AF [10 0 R] /K << /Type /OBJR /Obj 6 0 R >> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 230.0, 724.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 230.0, 724.0],
            'spans' => [[
                'text' => 'Review attached action',
                'bbox' => [72.0, 700.0, 230.0, 724.0],
                'font' => 'Helvetica',
            ]],
        ]],
    ]],
]];

$annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$annotation = $annotationPages[0]['annotations'][0] ?? [];
$action = $annotation['actions'][0] ?? [];
$chainedAction = $annotation['actions'][1] ?? [];
$additionalActions = $annotation['additional_actions'] ?? [];
$file = $action['annotation_associated_files'][0] ?? null;
$span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0] ?? [];

if (($action['annotation_struct_parent'] ?? null) !== 17
    || ($action['annotation_structure_parent']['struct_object'] ?? null) !== 40
    || !is_array($file)
    || ($file['checksum_matches'] ?? null) !== true
    || array_key_exists('content', $file)
) {
    throw new RuntimeException('Expected annotation action rows to carry StructParent associated-file review metadata without payload bytes.');
}
if (($chainedAction['destination'] ?? null) !== 'local-action-target' || ($chainedAction['destination_page'] ?? null) !== 1) {
    throw new RuntimeException('Expected chained local GoTo action review metadata.');
}
if (array_column($additionalActions, 'safety') !== ['blocked-javascript', 'remote-document-review']) {
    throw new RuntimeException('Expected additional annotation actions to remain review-only.');
}
if (($span['link_actions_review'][0]['annotation_associated_files'][0]['filename'] ?? null) !== 'annotation-action-source.xml') {
    throw new RuntimeException('Expected WordPress link span action review to preserve StructElem associated-file context.');
}
if (str_contains($plainText, '<wp-export>')
    || str_contains($plainText, 'annotation-action-source.xml')
    || str_contains($plainText, 'Annotation action structure')
    || str_contains($plainText, 'Annotation action alternate review')
    || str_contains($plainText, 'https://example.com/attachment-action')
    || str_contains($plainText, 'actionHoverReview')
    || str_contains($plainText, 'related-action.pdf')
) {
    throw new RuntimeException('Expected action, structure, and attachment review metadata to stay out of visible WordPress text.');
}

$json = static fn (array $value): string => htmlspecialchars(
    json_encode($value, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-page-annotation-structparent-associated-action-currentbase ' . $json([
    'support_component' => 'native-pdf-page-annotation-structparent-associated-action-review',
    'native_boundary' => 'current page annotation action rows carry StructParent, StructElem, and associated FileSpec context before WordPress rendering',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'annotation_object' => $annotation['annotation_object'] ?? null,
    'struct_parent' => $annotation['struct_parent'] ?? null,
    'action_safety' => array_column($annotation['actions'] ?? [], 'safety'),
    'additional_action_safety' => array_column($additionalActions, 'safety'),
    'associated_filename' => $file['filename'] ?? null,
    'associated_checksum_matches' => $file['checksum_matches'] ?? null,
    'associated_payload_content_omitted' => is_array($file) && !array_key_exists('content', $file),
    'span_action_struct_parent' => $span['link_actions_review'][0]['annotation_struct_parent'] ?? null,
    'visible_text_excludes_review_metadata' => !str_contains($plainText, '<wp-export>')
        && !str_contains($plainText, 'annotation-action-source.xml')
        && !str_contains($plainText, 'Annotation action structure')
        && !str_contains($plainText, 'https://example.com/attachment-action')
        && !str_contains($plainText, 'actionHoverReview'),
]) . " -->\n";

foreach ($blocks as $block) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf:annotation-action-review ' . $json([
    'actions' => array_map(static fn (array $row): array => [
        'action_type' => $row['action_type'] ?? null,
        'safety' => $row['safety'] ?? null,
        'annotation_struct_parent' => $row['annotation_struct_parent'] ?? null,
        'source_annotation_object' => $row['source_annotation_object'] ?? null,
        'annotation_associated_file_count' => $row['annotation_associated_file_count'] ?? null,
    ], $annotation['actions'] ?? []),
    'additional_actions' => array_map(static fn (array $row): array => [
        'event' => $row['event'] ?? null,
        'action_type' => $row['action_type'] ?? null,
        'safety' => $row['safety'] ?? null,
        'annotation_struct_parent' => $row['annotation_struct_parent'] ?? null,
        'annotation_associated_file_count' => $row['annotation_associated_file_count'] ?? null,
    ], $additionalActions),
]) . " -->\n";
