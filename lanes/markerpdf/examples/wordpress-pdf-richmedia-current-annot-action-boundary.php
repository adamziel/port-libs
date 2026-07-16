<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfRichMediaAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Article Body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 20 0 R >> >> /Annots [<< /Type /Annot /Subtype /RichMedia /Rect [72 520 320 700] /T (Current inline player) /Contents (Only this inline annotation belongs to the page) /RichMediaContent 30 0 R /A << /S /Rendition /R << /C 31 0 R /OP 0 >> /Next 14 0 R >> /AA << /PV [12 0 R << /S /RichMediaExecute /AN 50 0 R /CMD << /C (targetStalePlayer) >> >>] /PI 13 0 R >> /Popup << /Type /Annot /Subtype /Popup /Rect [200 540 380 620] /Open false /Contents (Inline popup metadata) >> >>] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "12 0 obj\n<< /S /URI /URI (https://cdn.example.com/current-inline.mp4) >>\nendobj\n"
    . "13 0 obj\n<< /S /Launch /F (current-helper.exe) /Win << /F (current-setup.exe) /O (open) >> >>\nendobj\n"
    . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('current only'\\)) >>\nendobj\n"
    . "30 0 obj\n<< /RichMediaContent << /Assets << /Names [(current-inline.mp4) 31 0 R] >> >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /Filespec /F (current-rendition.mp4) >>\nendobj\n"
    . "50 0 obj\n<< /Type /Annot /Subtype /RichMedia /Rect [10 10 20 20] /T (Stale detached player) /Contents (Detached target must not become a page annotation) /RichMediaContent 51 0 R /A << /S /URI /URI (https://cdn.example.com/stale-detached.mp4) >> >>\nendobj\n"
    . "51 0 obj\n<< /RichMediaContent << /Assets << /Names [(stale-detached.mp4) 52 0 R] >> >> >>\nendobj\n"
    . "52 0 obj\n<< /Type /Filespec /F (stale-detached.mp4) >>\nendobj\n"
    . "20 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
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
$fileNames = is_array($annotation['file_names'] ?? null) ? $annotation['file_names'] : [];
$assetNames = is_array($annotation['asset_names'] ?? null) ? $annotation['asset_names'] : [];

echo '<!-- markerpdf-pdf-richmedia-current-annot-action-boundary ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_media' => false,
    'executes_javascript' => false,
    'native_boundary' => 'page /Annots arrays are parsed as top-level entries; nested /A and /AA references remain action metadata',
    'review_annotation_count' => count($annotations),
    'review_action_count' => count($actions),
    'target_annotation_object' => $actions[3]['target_annotation_object'] ?? null,
    'stale_target_not_promoted' => count($annotations) === 1 && (($annotation['title'] ?? null) === 'Current inline player'),
    'stale_media_files_excluded' => !in_array('stale-detached.mp4', $fileNames, true) && !in_array('stale-detached.mp4', $assetNames, true),
    'popup_review_present' => isset($annotation['popup']),
    'popup_and_stale_text_excluded' => !str_contains($plainText, 'Inline popup metadata')
        && !str_contains($plainText, 'Detached target must not become a page annotation'),
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
        . ' data-marker-popup="' . htmlspecialchars((string) ($annotation['popup']['contents'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-executes-media="false" data-marker-executes-javascript="false">'
        . htmlspecialchars((string) ($annotation['title'] ?? $annotation['contents'] ?? $annotation['subtype']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";

    foreach ($annotation['actions'] as $action) {
        $detail = $action['uri'] ?? $action['file'] ?? $action['command'] ?? $action['script_preview'] ?? $action['action_type'];
        echo '<li data-marker-action-type="' . htmlspecialchars((string) $action['action_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ' data-marker-action-safety="' . htmlspecialchars((string) $action['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ' data-marker-action-event="' . htmlspecialchars((string) ($action['event'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ' data-marker-action-chained="' . htmlspecialchars($action['chained'] ? 'true' : 'false', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . htmlspecialchars((string) $detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . "</li>\n";
    }
}
echo "</ul>\n<!-- /wp:list -->\n";
