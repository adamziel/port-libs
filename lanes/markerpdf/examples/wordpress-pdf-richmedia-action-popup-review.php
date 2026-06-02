<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfRichMediaAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Article Body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 20 0 R >> >> /Annots [5 0 R 7 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Annot /Subtype /RichMedia /Rect [72 520 320 700] /T (Training player) /Contents (Embedded player requires review) /RichMediaContent 18 0 R /A 12 0 R /AA << /PV [13 0 R 14 0 R] /PI 16 0 R >> >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Popup /Parent 5 0 R /Rect [200 540 380 620] /Open true /Contents (Reviewer popup stays metadata) >>\nendobj\n"
    . "12 0 obj\n<< /S /RichMediaExecute /AN 5 0 R /CMD << /C (playVideo) >> /Next [13 0 R 15 0 R] >>\nendobj\n"
    . "13 0 obj\n<< /S /JavaScript /JS (app.alert\\('blocked media script'\\)) >>\nendobj\n"
    . "14 0 obj\n<< /S /URI /URI (https://cdn.example.com/training.mp4) /Next 15 0 R >>\nendobj\n"
    . "15 0 obj\n<< /S /Launch /F (helper.exe) /Win << /F (setup.exe) /O (open) >> /NewWindow true >>\nendobj\n"
    . "16 0 obj\n<< /S /URI /URI (javascript:alert\\(1\\)) /Next 16 0 R >>\nendobj\n"
    . "18 0 obj\n<< /RichMediaContent << /Assets << /Names [(training.mp4) 19 0 R] >> >> >>\nendobj\n"
    . "19 0 obj\n<< /Type /Filespec /F (training.mp4) >>\nendobj\n"
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

echo '<!-- markerpdf-pdf-richmedia-action-popup-review ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_media' => false,
    'executes_javascript' => false,
    'native_boundary' => 'rich-media annotation activation, additional actions, chained actions, and popup dictionaries are WordPress review metadata only',
    'review_annotation_count' => count($annotations),
    'review_action_count' => count($actions),
    'popup_review_present' => isset($annotation['popup']),
    'cycle_edges_blocked' => $annotation['action_chain_safety']['cycle_edges_blocked'] ?? 0,
    'unsafe_uri_blocked' => in_array('blocked-unsafe-uri', array_column($actions, 'safety'), true),
    'popup_and_script_text_excluded' => !str_contains($plainText, 'Reviewer popup stays metadata')
        && !str_contains($plainText, 'blocked media script'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($textExtractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
foreach ($annotations as $annotation) {
    $label = $annotation['title'] ?? $annotation['contents'] ?? $annotation['subtype'];
    $popup = $annotation['popup']['contents'] ?? '';
    echo '<li data-marker-annotation-subtype="' . htmlspecialchars((string) $annotation['subtype'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-page="' . htmlspecialchars((string) $annotation['page'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-action-count="' . htmlspecialchars((string) count($annotation['actions']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-popup="' . htmlspecialchars((string) $popup, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-executes-media="false" data-marker-executes-javascript="false">'
        . htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
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
