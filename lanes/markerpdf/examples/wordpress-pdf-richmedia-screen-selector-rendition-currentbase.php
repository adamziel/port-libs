<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfRichMediaAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Article Body) Tj ET';
$screenAppearance = 'BT /F1 12 Tf 0 0 Td (Selector Rendition Appearance Noise) Tj ET';
$primaryMediaBytes = "MP4 bytes with (Selector Primary Payload Leak) Tj ET";
$primaryChecksum = strtoupper(hash('md5', $primaryMediaBytes));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 90 0 R >> >> /Annots [5 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Annot /Subtype /Screen /Rect [72 500 360 650] /T (Selector rendition screen) /Contents (Selector rendition alternatives require review) /A 10 0 R /AP << /N 6 0 R >> >>\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 90 0 R >> >> /Length " . strlen($screenAppearance) . " >>\nstream\n{$screenAppearance}\nendstream\nendobj\n"
    . "10 0 obj\n<< /S /Rendition /OP 0 /AN 5 0 R /R 20 0 R >>\nendobj\n"
    . "20 0 obj\n<< /S /SR /N (Adaptive selector) /R [21 0 R 30 0 R] /MH 40 0 R /BE << /Lang (en-US) /V 0.5 /D [640 360] >> >>\nendobj\n"
    . "21 0 obj\n<< /S /MR /N (Primary HD rendition) /C 22 0 R /MH << /Lang (en-US) /D [1920 1080] /C true >> >>\nendobj\n"
    . "22 0 obj\n<< /S /MCD /N (Primary media clip) /D 23 0 R /CT (video/mp4) /Alt [(en-US) (HD training video)] >>\nendobj\n"
    . "23 0 obj\n<< /Type /Filespec /F (hd-training.mp4) /Desc (Primary training clip) /AFRelationship /Data /EF << /F 24 0 R >> >>\nendobj\n"
    . "24 0 obj\n<< /Type /EmbeddedFile /Subtype /video#2Fmp4 /Params << /Size " . strlen($primaryMediaBytes) . " /CheckSum <{$primaryChecksum}> /CreationDate (D:20260602211500Z) /ModDate (D:20260602211600Z) >> /Length " . strlen($primaryMediaBytes) . " >>\nstream\n{$primaryMediaBytes}\nendstream\nendobj\n"
    . "30 0 obj\n<< /S /MR /N (Fallback caption rendition) /C 31 0 R /BE 32 0 R >>\nendobj\n"
    . "31 0 obj\n<< /S /MCD /N (Fallback media clip) /D (fallback-captions.webm) /CT (video/webm) /Alt [(en-GB) (Captioned fallback)] >>\nendobj\n"
    . "32 0 obj\n<< /Type /MediaCriteria /Lang (en-GB) /V 0.2 /D [640 360] /C false >>\nendobj\n"
    . "40 0 obj\n<< /Type /MediaCriteria /A true /Lang (en-US) /P /Speaker >>\nendobj\n"
    . "60 0 obj\n<< /Type /Filespec /F (stale-selector-media.mp4) >>\nendobj\n"
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
$action = $actions[0] ?? [];
$selector = is_array($action['rendition'] ?? null) ? $action['rendition'] : [];
$alternatives = is_array($selector['renditions'] ?? null) ? $selector['renditions'] : [];
$primary = $alternatives[0] ?? [];
$fallback = $alternatives[1] ?? [];
$primaryClip = is_array($primary['media_clip'] ?? null) ? $primary['media_clip'] : [];
$fallbackClip = is_array($fallback['media_clip'] ?? null) ? $fallback['media_clip'] : [];
$primaryFileSpec = is_array($primaryClip['file_spec'] ?? null) ? $primaryClip['file_spec'] : [];
$primaryStreams = is_array($primaryFileSpec['embedded_file_streams'] ?? null) ? $primaryFileSpec['embedded_file_streams'] : [];
$primaryStream = $primaryStreams[0] ?? [];
$fileNames = is_array($annotation['file_names'] ?? null) ? $annotation['file_names'] : [];

if (
    count($annotations) !== 1
    || count($actions) !== 1
    || ($action['operation_label'] ?? null) !== 'play'
    || ($selector['subtype'] ?? null) !== 'SR'
    || count($alternatives) !== 2
    || ($primaryFileSpec['filename'] ?? null) !== 'hd-training.mp4'
    || ($primaryStream['checksum_matches'] ?? null) !== true
    || ($fallbackClip['file'] ?? null) !== 'fallback-captions.webm'
    || !in_array('hd-training.mp4', $fileNames, true)
    || !in_array('fallback-captions.webm', $fileNames, true)
    || in_array('stale-selector-media.mp4', $fileNames, true)
) {
    throw new RuntimeException('Expected one review-only Screen selector rendition with primary and fallback media clips.');
}

if (
    !str_contains($plainText, 'Article Body')
    || str_contains($plainText, 'Selector Rendition Appearance Noise')
    || str_contains($plainText, 'Selector Primary Payload Leak')
    || str_contains($plainText, 'stale-selector-media.mp4')
) {
    throw new RuntimeException('Expected media payloads and stale Filespec text to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-pdf-richmedia-screen-selector-rendition-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_media' => false,
    'executes_javascript' => false,
    'support_component' => 'native-pdf-richmedia-annotation-review-parser',
    'native_boundary' => 'Screen /Rendition selector dictionaries expose /S /SR alternatives, /MH and /BE media criteria, and media clip FileSpec streams as review-only metadata',
    'review_annotation_count' => count($annotations),
    'review_action_count' => count($actions),
    'selector_object' => $selector['dictionary_object'] ?? null,
    'selector_name' => $selector['name'] ?? null,
    'selector_must_honor_keys' => $selector['must_honor']['keys'] ?? [],
    'selector_best_effort_keys' => $selector['best_effort']['keys'] ?? [],
    'alternative_count' => count($alternatives),
    'primary_clip_file' => $primaryClip['file'] ?? null,
    'primary_file_spec' => $primaryFileSpec['filename'] ?? null,
    'primary_stream_sha256' => $primaryStream['content_sha256'] ?? null,
    'primary_stream_checksum_matches' => $primaryStream['checksum_matches'] ?? null,
    'fallback_clip_file' => $fallbackClip['file'] ?? null,
    'fallback_best_effort_lang' => $fallback['best_effort']['strings']['Lang'] ?? null,
    'file_names' => $fileNames,
    'stale_selector_media_excluded' => !in_array('stale-selector-media.mp4', $fileNames, true),
    'payload_text_excluded' => !str_contains($plainText, 'Selector Rendition Appearance Noise')
        && !str_contains($plainText, 'Selector Primary Payload Leak')
        && !str_contains($plainText, 'stale-selector-media.mp4'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($textExtractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
echo '<li data-marker-annotation-subtype="' . htmlspecialchars((string) ($annotation['subtype'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
    . ' data-marker-page="' . htmlspecialchars((string) ($annotation['page'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
    . ' data-marker-action-count="' . htmlspecialchars((string) count($actions), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
    . ' data-marker-executes-media="false" data-marker-executes-javascript="false">'
    . htmlspecialchars((string) ($annotation['title'] ?? $annotation['contents'] ?? $annotation['subtype'] ?? 'Screen'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</li>\n";

foreach ($alternatives as $alternative) {
    $clip = is_array($alternative['media_clip'] ?? null) ? $alternative['media_clip'] : [];
    echo '<li data-marker-rendition-subtype="' . htmlspecialchars((string) ($alternative['subtype'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-rendition-file="' . htmlspecialchars((string) ($clip['file'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-rendition-content-type="' . htmlspecialchars((string) ($clip['content_type'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars((string) ($alternative['name'] ?? $clip['name'] ?? 'rendition'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
