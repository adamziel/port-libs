<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfRichMediaAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Article Body) Tj ET';
$movieAppearance = 'BT /F1 12 Tf 0 0 Td (Movie Popup Payload Noise) Tj ET';
$soundBytes = "WAVE bytes with (Sound Action Payload Noise) Tj ET";

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Annots [5 0 R 6 0 R 7 0 R 8 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Annot /Subtype /Movie /Rect [320 700 72 540] /T (Inline movie) /Contents (Movie annotation requires review) /Movie 20 0 R /A 21 0 R /AA << /U 30 0 R >> /Popup 23 0 R /AP << /N 24 0 R >> >>\nendobj\n"
    . "6 0 obj\n<< /Type /Annot /Subtype /Sound /Rect [72 500 180 535] /T (Narration) /Contents (Sound annotation requires review) /Name /Speaker /Sound 22 0 R /A 31 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Screen /Rect [72 420 360 500] /T (Rendition player) /Contents (Rendition requires review) /A 32 0 R /Popup << /Type /Annot /Subtype /Popup /Rect [210 420 390 500] /Open false /Contents (Screen rendition popup) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Popup /Parent 6 0 R /Rect [180 500 340 560] /Open true /Contents (Sound popup stays metadata) >>\nendobj\n"
    . "20 0 obj\n<< /F 25 0 R /T (Movie dictionary title) /Aspect [1280 720] /Rotate 180 /Poster 26 0 R >>\nendobj\n"
    . "21 0 obj\n<< /Start 2 /Duration 30 /Rate 1 /Volume 0.8 /ShowControls false /Mode /Palindrome /Synchronous true /FWScale [2 2] /FWPosition [0.25 0.75] >>\nendobj\n"
    . "22 0 obj\n<< /R 22050 /C 1 /B 8 /E /Raw /Length " . strlen($soundBytes) . " >>\nstream\n{$soundBytes}\nendstream\nendobj\n"
    . "23 0 obj\n<< /Type /Annot /Subtype /Popup /Parent 5 0 R /Rect [300 548 460 628] /Open true /Contents (Movie popup stays metadata) >>\nendobj\n"
    . "24 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 40 0 R >> >> /Length " . strlen($movieAppearance) . " >>\nstream\n{$movieAppearance}\nendstream\nendobj\n"
    . "25 0 obj\n<< /Type /Filespec /F (movie-action.mov) /UF <FEFF006D006F007600690065002D0061006300740069006F006E002E006D006F0076> >>\nendobj\n"
    . "26 0 obj\n<< /Subtype /Image /Width 16 /Height 16 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "30 0 obj\n<< /S /Movie /Annotation 5 0 R /T (Inline movie action) /Operation /Play >>\nendobj\n"
    . "31 0 obj\n<< /S /Sound /Sound 22 0 R /Volume .4 /Synchronous false /Repeat true /Mix false >>\nendobj\n"
    . "32 0 obj\n<< /S /Rendition /OP 0 /AN 7 0 R /R 33 0 R >>\nendobj\n"
    . "33 0 obj\n<< /S /MR /N (Training rendition) /C 34 0 R >>\nendobj\n"
    . "34 0 obj\n<< /S /MCD /N (Training movie clip) /D 35 0 R /CT (video/mp4) /Alt [(en-US) (Training video)] >>\nendobj\n"
    . "35 0 obj\n<< /Type /Filespec /F (training-rendition.mp4) /UF <FEFF0074007200610069006E0069006E0067002D00720065006E0064006900740069006F006E002E006D00700034> >>\nendobj\n"
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

$actions = [];
foreach ($annotations as $annotation) {
    foreach ($annotation['actions'] as $action) {
        $actions[] = $action;
    }
}

$actionTypes = array_values(array_unique(array_column($actions, 'action_type')));
$popupContents = array_values(array_filter(array_map(
    static fn (array $annotation): ?string => $annotation['popup']['contents'] ?? null,
    $annotations
)));
$rendition = $actions[2]['rendition'] ?? [];

echo '<!-- markerpdf-pdf-movie-sound-rendition-popup-boundaries ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_media' => false,
    'executes_javascript' => false,
    'native_boundary' => 'Movie, Sound, and Rendition annotation actions plus popups are WordPress review metadata only',
    'review_annotation_count' => count($annotations),
    'action_types' => $actionTypes,
    'popup_contents' => $popupContents,
    'movie_action_target' => $actions[0]['target_annotation_object'] ?? null,
    'sound_repeat' => $actions[1]['repeat'] ?? null,
    'rendition_operation_label' => $actions[2]['operation_label'] ?? null,
    'rendition_media_clip_type' => $rendition['media_clip']['content_type'] ?? null,
    'media_payload_text_excluded' => !str_contains($plainText, 'Movie Popup Payload Noise')
        && !str_contains($plainText, 'Sound Action Payload Noise')
        && !str_contains($plainText, 'Movie popup stays metadata')
        && !str_contains($plainText, 'Sound popup stays metadata'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($textExtractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
foreach ($annotations as $annotation) {
    $label = $annotation['title'] ?? $annotation['contents'] ?? $annotation['subtype'];
    echo '<li data-marker-annotation-subtype="' . htmlspecialchars((string) $annotation['subtype'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-page="' . htmlspecialchars((string) $annotation['page'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-popup="' . htmlspecialchars((string) ($annotation['popup']['contents'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-actions="' . htmlspecialchars(implode(',', $annotation['action_types']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-executes-media="false">'
        . htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
