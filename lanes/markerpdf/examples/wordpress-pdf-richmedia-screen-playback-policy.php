<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfRichMediaAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Article Body) Tj ET';
$screenAppearance = 'BT /F1 12 Tf 0 0 Td (Playback Policy Noise) Tj ET';
$mediaBytes = "MP3 bytes with (Leaked Playback Payload) Tj ET";

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Annots [5 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Annot /Subtype /Screen /Rect [72 500 360 650] /T (Screen training audio) /Contents (Playback settings require review) /A 10 0 R /AP << /N 6 0 R >> >>\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 30 0 R >> >> /Length " . strlen($screenAppearance) . " >>\nstream\n{$screenAppearance}\nendstream\nendobj\n"
    . "10 0 obj\n<< /S /Rendition /OP 0 /AN 5 0 R /R 11 0 R >>\nendobj\n"
    . "11 0 obj\n<< /S /MR /N (Policy rendition) /C 12 0 R /P 15 0 R /SP 18 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /MCD /N (Policy audio clip) /D 13 0 R /CT (audio/mpeg) /Alt [(en-US) (Training narration)] >>\nendobj\n"
    . "13 0 obj\n<< /Type /Filespec /F (training-audio.mp3) /EF << /F 14 0 R >> >>\nendobj\n"
    . "14 0 obj\n<< /Type /EmbeddedFile /Subtype /audio#2Fmpeg /Length " . strlen($mediaBytes) . " >>\nstream\n{$mediaBytes}\nendstream\nendobj\n"
    . "15 0 obj\n<< /MH 16 0 R /BE << /C false /V 0.4 /Mode /Once /T (Best effort playback) /Dur [0 15] >> >>\nendobj\n"
    . "16 0 obj\n<< /C true /V .85 /R false /T (Must honor playback) /Lang (en-US) >>\nendobj\n"
    . "18 0 obj\n<< /MH 19 0 R /BE 20 0 R >>\nendobj\n"
    . "19 0 obj\n<< /W /Window /O 0.9 /Title (Floating review player) >>\nendobj\n"
    . "20 0 obj\n<< /W /FullScreen /O 0.5 /C false >>\nendobj\n"
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
$action = $annotation['actions'][0] ?? [];
$rendition = $action['rendition'] ?? [];
$play = $rendition['play_parameters'] ?? [];
$screen = $rendition['screen_parameters'] ?? [];

echo '<!-- markerpdf-pdf-richmedia-screen-playback-policy ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_media' => false,
    'executes_javascript' => false,
    'native_boundary' => 'screen Rendition /P and /SP /MH /BE dictionaries are WordPress review metadata only',
    'review_annotation_count' => count($annotations),
    'play_must_honor_controls' => $play['must_honor']['booleans']['C'] ?? null,
    'play_best_effort_mode' => $play['best_effort']['names']['Mode'] ?? null,
    'screen_must_honor_window' => $screen['must_honor']['names']['W'] ?? null,
    'screen_best_effort_window' => $screen['best_effort']['names']['W'] ?? null,
    'media_clip_type' => $rendition['media_clip']['content_type'] ?? null,
    'media_payload_text_excluded' => !str_contains($plainText, 'Playback Policy Noise')
        && !str_contains($plainText, 'Leaked Playback Payload'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($textExtractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
foreach ($annotations as $annotation) {
    $label = $annotation['title'] ?? $annotation['contents'] ?? $annotation['subtype'];
    $action = $annotation['actions'][0] ?? [];
    $rendition = $action['rendition'] ?? [];
    $play = $rendition['play_parameters'] ?? [];
    $screen = $rendition['screen_parameters'] ?? [];

    echo '<li data-marker-annotation-subtype="' . htmlspecialchars((string) $annotation['subtype'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-page="' . htmlspecialchars((string) $annotation['page'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-action-type="' . htmlspecialchars((string) ($action['action_type'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-play-controls="' . htmlspecialchars(($play['must_honor']['booleans']['C'] ?? false) ? 'true' : 'false', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-screen-window="' . htmlspecialchars((string) ($screen['must_honor']['names']['W'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-executes-media="false">'
        . htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
