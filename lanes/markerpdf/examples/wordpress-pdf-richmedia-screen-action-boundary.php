<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfRichMediaAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Article Body) Tj ET';
$screenAppearance = 'BT /F1 12 Tf 0 0 Td (Current Screen Appearance Noise) Tj ET';
$staleMovieAppearance = 'BT /F1 12 Tf 0 0 Td (Detached Movie Appearance Noise) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Annots [5 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Annot /Subtype /Screen /Rect [72 500 360 650] /T (Current screen player) /Contents (Only this screen annotation belongs to the page) /A 10 0 R /AA << /PV 12 0 R /PI 13 0 R >> /AP << /N 6 0 R >> >>\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 30 0 R >> >> /Length " . strlen($screenAppearance) . " >>\nstream\n{$screenAppearance}\nendstream\nendobj\n"
    . "10 0 obj\n<< /S /Movie /Annotation 50 0 R /T (Detached screen movie action) /Operation /Play /Next 14 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /URI /URI (https://cdn.example.com/current-screen.mp4) >>\nendobj\n"
    . "13 0 obj\n<< /S /Launch /F (screen-helper.exe) /Win << /F (screen-setup.exe) /O (open) >> >>\nendobj\n"
    . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('screen action stays review only'\\)) >>\nendobj\n"
    . "50 0 obj\n<< /Type /Annot /Subtype /Movie /Rect [10 10 20 20] /T (Detached movie target) /Contents (Detached screen target must not become current media) /Movie 51 0 R /A << /S /URI /URI (https://cdn.example.com/stale-screen-target.mov) >> /AP << /N 53 0 R >> >>\nendobj\n"
    . "51 0 obj\n<< /F 52 0 R /T (Detached target movie title) /Aspect [320 180] /Poster true >>\nendobj\n"
    . "52 0 obj\n<< /Type /Filespec /F (stale-screen-target.mov) >>\nendobj\n"
    . "53 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 30 0 R >> >> /Length " . strlen($staleMovieAppearance) . " >>\nstream\n{$staleMovieAppearance}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
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
$movieAction = $actions[0] ?? [];
$fileNames = is_array($annotation['file_names'] ?? null) ? $annotation['file_names'] : [];

echo '<!-- markerpdf-pdf-richmedia-screen-action-boundary ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_media' => false,
    'executes_javascript' => false,
    'native_boundary' => 'Screen annotation actions name detached targets as review metadata but do not import target media dictionaries',
    'review_annotation_count' => count($annotations),
    'review_action_count' => count($actions),
    'target_annotation_object' => $movieAction['target_annotation_object'] ?? null,
    'target_annotation_is_page_annotation' => $movieAction['target_annotation_is_page_annotation'] ?? null,
    'stale_target_movie_not_promoted' => !isset($movieAction['movie']),
    'stale_media_file_excluded' => !in_array('stale-screen-target.mov', $fileNames, true),
    'current_uri_reviewed' => in_array('https://cdn.example.com/current-screen.mp4', $annotation['action_uris'] ?? [], true),
    'appearance_and_stale_text_excluded' => !str_contains($plainText, 'Current Screen Appearance Noise')
        && !str_contains($plainText, 'Detached Movie Appearance Noise')
        && !str_contains($plainText, 'Detached screen target must not become current media'),
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
        $detail = $action['uri'] ?? $action['file'] ?? $action['title'] ?? $action['script_preview'] ?? $action['action_type'];
        echo '<li data-marker-action-type="' . htmlspecialchars((string) $action['action_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ' data-marker-action-safety="' . htmlspecialchars((string) $action['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ' data-marker-action-event="' . htmlspecialchars((string) ($action['event'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ' data-marker-target-page-annotation="' . htmlspecialchars(($action['target_annotation_is_page_annotation'] ?? true) ? 'true' : 'false', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . htmlspecialchars((string) $detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . "</li>\n";
    }
}
echo "</ul>\n<!-- /wp:list -->\n";
