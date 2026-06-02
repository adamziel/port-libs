<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="annotation-transition"/></wp-export>';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Transition annotated link) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Transition target page visible) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 13 0 R >> /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R /PageLabels << /Nums [0 << /P (Source ) /S /D /St 2 >> 1 << /P (Target ) /S /D /St 7 >>] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 15 0 R /Annots [6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Dur 4.5 /Trans 16 0 R /AA << /O 17 0 R /C 18 0 R >> /Contents 19 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Type /Annot /Subtype /Link /StructParent 22 /Rect [72 700 240 724] /Contents (Transition action review note) /A << /S /GoTo /D (transition-target) /Next 12 0 R >> /AA << /U << /S /GoTo /D [4 0 R /FitH 620] >> >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (annotation-transition-source.xml) /Desc (Annotation transition source file) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602220500Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /S /URI /URI (https://example.com/transition-followup) >>\nendobj\n"
    . "13 0 obj\n<< /Names [(transition-target) 14 0 R] >>\nendobj\n"
    . "14 0 obj\n[4 0 R /XYZ 72 680 0]\nendobj\n"
    . "15 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "16 0 obj\n<< /S /Dissolve /D .75 >>\nendobj\n"
    . "17 0 obj\n<< /S /URI /URI (https://example.com/page-open-transition-review) >>\nendobj\n"
    . "18 0 obj\n<< /S /JavaScript /JS (targetCloseReview\\(\\)) >>\nendobj\n"
    . "19 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /TransitionLink /Link >> /ParentTree 31 0 R /K [40 0 R] >>\nendobj\n"
    . "31 0 obj\n<< /Nums [22 40 0 R] >>\nendobj\n"
    . "40 0 obj\n<< /Type /StructElem /S /TransitionLink /Pg 3 0 R /T (Annotation transition structure) /Alt (Annotation transition alternate review) /AF [10 0 R] /K << /Type /OBJR /Obj 6 0 R >> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

$annotation = $annotationPages[0]['annotations'][0] ?? [];
$primaryAction = $annotation['actions'][0] ?? [];
$followupAction = $annotation['actions'][1] ?? [];
$additionalAction = $annotation['additional_actions'][0] ?? [];
$file = $primaryAction['annotation_associated_files'][0] ?? null;

if (($primaryAction['destination_page_label'] ?? null) !== 'Target 7'
    || ($primaryAction['target_page_transition']['style'] ?? null) !== 'Dissolve'
    || array_column($primaryAction['target_page_actions'] ?? [], 'safety') !== ['review-uri', 'blocked-javascript']
) {
    throw new RuntimeException('Expected structured annotation action rows to carry target page transition/action context.');
}
if (($additionalAction['target_page_transition']['style'] ?? null) !== 'Dissolve'
    || ($additionalAction['annotation_struct_parent'] ?? null) !== 22
) {
    throw new RuntimeException('Expected additional annotation GoTo action to carry transition and StructParent context.');
}
if (!is_array($file)
    || ($file['filename'] ?? null) !== 'annotation-transition-source.xml'
    || ($file['checksum_matches'] ?? null) !== true
    || array_key_exists('content', $file)
) {
    throw new RuntimeException('Expected StructElem associated file review metadata without payload bytes.');
}
if (($followupAction['safety'] ?? null) !== 'review-uri' || array_key_exists('target_page_transition', $followupAction)) {
    throw new RuntimeException('Expected non-destination follow-up URI to remain review-only without target page transition context.');
}
if (str_contains($plainText, '<wp-export>')
    || str_contains($plainText, 'annotation-transition-source.xml')
    || str_contains($plainText, 'Annotation transition structure')
    || str_contains($plainText, 'Annotation transition alternate review')
    || str_contains($plainText, 'Transition action review note')
    || str_contains($plainText, 'transition-followup')
    || str_contains($plainText, 'page-open-transition-review')
    || str_contains($plainText, 'targetCloseReview')
) {
    throw new RuntimeException('Expected action, structure, transition, and attachment review metadata to stay out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-page-annotation-structtree-associated-transition-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-page-annotation-structtree-associated-transition-review',
    'native_boundary' => 'annotation GoTo action rows carry target page labels, transitions, page actions, StructParent, and associated FileSpec review metadata before WordPress rendering',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'annotation_object' => $annotation['annotation_object'] ?? null,
    'struct_parent' => $annotation['struct_parent'] ?? null,
    'destination_page_label' => $primaryAction['destination_page_label'] ?? null,
    'target_transition_style' => $primaryAction['target_page_transition']['style'] ?? null,
    'target_page_action_safety' => array_column($primaryAction['target_page_actions'] ?? [], 'safety'),
    'additional_action_target_transition_style' => $additionalAction['target_page_transition']['style'] ?? null,
    'associated_filename' => $file['filename'] ?? null,
    'associated_checksum_matches' => $file['checksum_matches'] ?? null,
    'associated_payload_content_omitted' => is_array($file) && !array_key_exists('content', $file),
    'visible_text_excludes_review_metadata' => !str_contains($plainText, '<wp-export>')
        && !str_contains($plainText, 'annotation-transition-source.xml')
        && !str_contains($plainText, 'Annotation transition structure')
        && !str_contains($plainText, 'transition-followup')
        && !str_contains($plainText, 'targetCloseReview'),
]) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf:annotation-transition-review ' . $htmlJson([
    'actions' => array_map(static fn (array $row): array => [
        'action_type' => $row['action_type'] ?? null,
        'safety' => $row['safety'] ?? null,
        'destination_page_label' => $row['destination_page_label'] ?? null,
        'target_display_duration' => $row['target_display_duration'] ?? null,
        'target_page_transition' => $row['target_page_transition'] ?? null,
        'target_page_action_safety' => array_column($row['target_page_actions'] ?? [], 'safety'),
        'annotation_struct_parent' => $row['annotation_struct_parent'] ?? null,
        'annotation_associated_file_count' => $row['annotation_associated_file_count'] ?? null,
    ], $annotation['actions'] ?? []),
    'additional_actions' => array_map(static fn (array $row): array => [
        'event' => $row['event'] ?? null,
        'action_type' => $row['action_type'] ?? null,
        'safety' => $row['safety'] ?? null,
        'destination_page_label' => $row['destination_page_label'] ?? null,
        'target_page_transition' => $row['target_page_transition'] ?? null,
        'annotation_struct_parent' => $row['annotation_struct_parent'] ?? null,
    ], $annotation['additional_actions'] ?? []),
]) . " -->\n";
