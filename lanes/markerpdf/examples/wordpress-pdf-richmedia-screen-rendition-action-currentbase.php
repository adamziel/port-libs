<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfRichMediaAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Article Body) Tj ET';
$screenAppearance = 'BT /F1 12 Tf 0 0 Td (Current Rendition Appearance Noise) Tj ET';
$mediaBytes = "MP4 bytes with (Current Rendition Payload Leak) Tj ET";

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Annots [5 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Annot /Subtype /Screen /Rect [72 500 360 650] /T (Current-base rendition screen) /Contents (Rendition actions stay review-only) /A 10 0 R /AA << /PO 12 0 R /PV 13 0 R /PI 14 0 R >> /AP << /N 6 0 R >> >>\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 40 0 R >> >> /Length " . strlen($screenAppearance) . " >>\nstream\n{$screenAppearance}\nendstream\nendobj\n"
    . "10 0 obj\n<< /S /Rendition /OP 4 /AN 5 0 R /R 20 0 R /JS (player.playOrResume\\(\\)) >>\nendobj\n"
    . "12 0 obj\n<< /S /Rendition /OP 2 /AN 5 0 R /JS (player.pause\\(\\)) >>\nendobj\n"
    . "13 0 obj\n<< /S /Rendition /OP 3 /AN 5 0 R /JS 16 0 R >>\nendobj\n"
    . "14 0 obj\n<< /S /Rendition /OP 1 /AN 5 0 R >>\nendobj\n"
    . "16 0 obj\n(player.resume\\(\\))\nendobj\n"
    . "20 0 obj\n<< /S /MR /N (Current-base training rendition) /C 21 0 R >>\nendobj\n"
    . "21 0 obj\n<< /S /MCD /N (Current-base clip) /D 22 0 R /CT (video/mp4) >>\nendobj\n"
    . "22 0 obj\n<< /Type /Filespec /F (current-base-rendition.mp4) /EF << /F 23 0 R >> >>\nendobj\n"
    . "23 0 obj\n<< /Type /EmbeddedFile /Subtype /video#2Fmp4 /Length " . strlen($mediaBytes) . " >>\nstream\n{$mediaBytes}\nendstream\nendobj\n"
    . "60 0 obj\n<< /Type /Filespec /F (stale-rendition.mp4) >>\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$textExtractor = new PdfTextExtractor();
$plainText = $textExtractor->extractPlainText($pdf);
$reviewPages = (new PdfRichMediaAnnotationExtractor())->extractReviewAnnotations($pdf);
$annotations = [];
foreach ($reviewPages as $page) {
    foreach ($page['annotations'] as $annotation) {
        $annotation['page'] = $page['pnum'] + 1;
        $annotations[] = $annotation;
    }
}

$annotation = $annotations[0] ?? [];
$actions = is_array($annotation['actions'] ?? null) ? $annotation['actions'] : [];
$operationLabels = array_values(array_filter(array_map(
    static fn (array $action): ?string => $action['operation_label'] ?? null,
    $actions
)));
$currentScopeEvents = array_values(array_filter(array_map(
    static fn (array $action): ?string => ($action['rendition_scope'] ?? null) === 'current-associated-rendition'
        ? (string) ($action['event'] ?? '')
        : null,
    $actions
)));
$scriptHashes = array_values(array_filter(array_map(
    static fn (array $action): ?string => $action['script_sha256'] ?? null,
    $actions
)));
$fileNames = is_array($annotation['file_names'] ?? null) ? $annotation['file_names'] : [];

echo '<!-- markerpdf-pdf-richmedia-screen-rendition-action-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_media' => false,
    'executes_javascript' => false,
    'native_boundary' => 'Screen rendition actions expose OP, AN, current-associated rendition scope, and JS review metadata without executing viewer behavior',
    'review_annotation_count' => count($annotations),
    'review_action_count' => count($actions),
    'operation_labels' => $operationLabels,
    'current_scope_events' => $currentScopeEvents,
    'script_hash_count' => count($scriptHashes),
    'play_or_resume_label_supported' => in_array('play_or_resume', $operationLabels, true),
    'current_rendition_actions_have_no_rendition_dictionary' => !isset($actions[1]['rendition'], $actions[2]['rendition'], $actions[3]['rendition']),
    'stale_rendition_file_excluded' => !in_array('stale-rendition.mp4', $fileNames, true),
    'appearance_payload_and_script_text_excluded' => !str_contains($plainText, 'Current Rendition Appearance Noise')
        && !str_contains($plainText, 'Current Rendition Payload Leak')
        && !str_contains($plainText, 'player.playOrResume'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($textExtractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
foreach ($annotations as $annotation) {
    echo '<li data-marker-annotation-subtype="' . htmlspecialchars((string) $annotation['subtype'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-page="' . htmlspecialchars((string) $annotation['page'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-action-count="' . htmlspecialchars((string) count($annotation['actions']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-executes-media="false" data-marker-executes-javascript="false">'
        . htmlspecialchars((string) ($annotation['title'] ?? $annotation['contents'] ?? $annotation['subtype']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";

    foreach ($annotation['actions'] as $action) {
        $detail = $action['operation_label'] ?? $action['script_preview'] ?? $action['action_type'];
        echo '<li data-marker-action-type="' . htmlspecialchars((string) $action['action_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ' data-marker-action-safety="' . htmlspecialchars((string) $action['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ' data-marker-action-event="' . htmlspecialchars((string) ($action['event'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ' data-marker-rendition-scope="' . htmlspecialchars((string) ($action['rendition_scope'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . htmlspecialchars((string) $detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . "</li>\n";
    }
}
echo "</ul>\n<!-- /wp:list -->\n";
