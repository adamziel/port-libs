<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfRichMediaAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Article Body) Tj ET';
$appearanceText = 'BT /F1 12 Tf 0 0 Td (Embedded Action Appearance Noise) Tj ET';
$mediaBytes = "MP4 bytes with (Embedded Action Media Payload Leak) Tj ET";
$scriptBytes = "app.alert('embedded action script leak')";

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 60 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 90 0 R >> >> /Annots [5 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Annot /Subtype /RichMedia /Rect [72 500 360 650] /T (Embedded action player) /Contents (RichMediaExecute target instance requires review) /RichMediaContent 30 0 R /A 80 0 R /AA << /PV 81 0 R >> /AP << /N 6 0 R >> >>\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 90 0 R >> >> /Length " . strlen($appearanceText) . " >>\nstream\n{$appearanceText}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /RichMediaContent /Assets 35 0 R /Configurations [40 0 R] >>\nendobj\n"
    . "35 0 obj\n<< /Names [(action-video.mp4) 31 0 R (controller.js) 32 0 R] >>\nendobj\n"
    . "31 0 obj\n<< /Type /Filespec /F (action-video.mp4) /UF <FEFF0061006300740069006F006E002D0076006900640065006F002E006D00700034> /Desc (Current action video asset) /AFRelationship /Data /EF << /F 33 0 R >> >>\nendobj\n"
    . "32 0 obj\n<< /Type /Filespec /F (controller.js) /EF << /F 34 0 R >> >>\nendobj\n"
    . "33 0 obj\n<< /Type /EmbeddedFile /Subtype /video#2Fmp4 /Length " . strlen($mediaBytes) . " >>\nstream\n{$mediaBytes}\nendstream\nendobj\n"
    . "34 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjavascript /Length " . strlen($scriptBytes) . " >>\nstream\n{$scriptBytes}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /RichMediaConfiguration /Subtype /Video /Name (Primary video configuration) /Instances [41 0 R 42 0 R] >>\nendobj\n"
    . "41 0 obj\n<< /Type /RichMediaInstance /Subtype /Video /Asset 31 0 R /Params 43 0 R >>\nendobj\n"
    . "42 0 obj\n<< /Type /RichMediaInstance /Subtype /Flash /Asset 32 0 R /Params << /Binding /Foreground /FlashVars (controller=1) >> >>\nendobj\n"
    . "43 0 obj\n<< /Type /RichMediaParams /Binding /Foreground /FlashVars (src=action-video.mp4&autoplay=false) /Settings (quality=review) /CuePoints [(intro) 12 true] >>\nendobj\n"
    . "50 0 obj\n<< /Type /Filespec /F (stale-media.mov) /EF << /F 51 0 R >> >>\nendobj\n"
    . "51 0 obj\n<< /Type /EmbeddedFile /Length 44 >>\nstream\nBT (Stale RichMedia Payload Leak) Tj ET\nendstream\nendobj\n"
    . "60 0 obj\n<< /Names [(stale-media.mov) 50 0 R] >>\nendobj\n"
    . "80 0 obj\n<< /S /RichMediaExecute /TA 5 0 R /TI 41 0 R /C (cueChapter) /A [(intro) 12 true] /Next 82 0 R >>\nendobj\n"
    . "81 0 obj\n<< /S /RichMediaExecute /AN 5 0 R /CMD << /C (legacyCue) /A (outro) >> >>\nendobj\n"
    . "82 0 obj\n<< /S /JavaScript /JS (app.alert\\('embedded action blocked'\\)) >>\nendobj\n"
    . "90 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
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
$execute = $actions[0] ?? [];
$legacy = $actions[2] ?? [];
$targetInstance = is_array($execute['target_instance'] ?? null) ? $execute['target_instance'] : [];
$asset = is_array($targetInstance['asset'] ?? null) ? $targetInstance['asset'] : [];
$params = is_array($targetInstance['params'] ?? null) ? $targetInstance['params'] : [];
$fileNames = is_array($annotation['file_names'] ?? null) ? $annotation['file_names'] : [];

if (count($annotations) !== 1 || count($actions) !== 3 || ($execute['command'] ?? null) !== 'cueChapter') {
    throw new RuntimeException('Expected one RichMedia annotation with direct and legacy RichMediaExecute review rows.');
}

echo '<!-- markerpdf-pdf-richmedia-embedded-action-media-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_media' => false,
    'executes_javascript' => false,
    'native_boundary' => 'RichMediaExecute /TA, /TI, direct /C, /A, and target-instance media assets are review-only metadata',
    'review_annotation_count' => count($annotations),
    'review_action_count' => count($actions),
    'target_annotation_object' => $execute['target_annotation_object'] ?? null,
    'target_instance_object' => $execute['target_instance_object'] ?? null,
    'target_instance_asset' => $asset['filename'] ?? null,
    'target_instance_mime_types' => $asset['mime_types'] ?? [],
    'command_arguments' => $execute['command_arguments'] ?? null,
    'legacy_command_arguments' => $legacy['command_arguments'] ?? null,
    'flash_vars' => $params['flash_vars'] ?? null,
    'cue_points' => $params['cue_points'] ?? [],
    'stale_catalog_media_excluded' => !in_array('stale-media.mov', $fileNames, true),
    'payload_text_excluded' => !str_contains($plainText, 'Embedded Action Appearance Noise')
        && !str_contains($plainText, 'Embedded Action Media Payload Leak')
        && !str_contains($plainText, 'embedded action script leak')
        && !str_contains($plainText, 'embedded action blocked')
        && !str_contains($plainText, 'Stale RichMedia Payload Leak'),
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
        $detail = $action['command'] ?? $action['script_preview'] ?? $action['action_type'];
        echo '<li data-marker-action-type="' . htmlspecialchars((string) $action['action_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ' data-marker-action-safety="' . htmlspecialchars((string) $action['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ' data-marker-action-event="' . htmlspecialchars((string) ($action['event'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ' data-marker-target-instance="' . htmlspecialchars((string) ($action['target_instance_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . htmlspecialchars((string) $detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . "</li>\n";
    }
}
echo "</ul>\n<!-- /wp:list -->\n";
