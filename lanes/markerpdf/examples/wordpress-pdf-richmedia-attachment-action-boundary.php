<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfRichMediaAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Article Body) Tj ET';
$attachmentPayload = 'BT /F1 12 Tf 72 720 Td (Attachment Payload Leak) Tj ET';
$attachmentChecksum = strtoupper(hash('md5', $attachmentPayload));
$staleAttachmentPayload = 'BT /F1 12 Tf 72 720 Td (Stale Attachment Payload Leak) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 60 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Annots [5 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Annot /Subtype /RichMedia /Rect [72 520 320 700] /T (Attachment player) /Contents (Embedded document action requires review) /RichMediaContent 30 0 R /A 12 0 R /AA << /PV 13 0 R >> >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToE /F 20 0 R /D [0 /FitH 612] /NewWindow true /T << /R /C /N (review-pack.pdf) /P 0 >> /Next 14 0 R >>\nendobj\n"
    . "13 0 obj\n<< /S /GoToE /D (chapter-one) /T 25 0 R >>\nendobj\n"
    . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('attachment action blocked'\\)) >>\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (review-pack.pdf) /UF <FEFF007200650076006900650077002D007000610063006B002E007000640066> /Desc (Embedded review packet) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fpdf /Params << /Size " . strlen($attachmentPayload) . " /CheckSum <{$attachmentChecksum}> >> /Length " . strlen($attachmentPayload) . " >>\nstream\n{$attachmentPayload}\nendstream\nendobj\n"
    . "25 0 obj\n<< /R /C /N (chapter-notes.pdf) /P 2 >>\nendobj\n"
    . "30 0 obj\n<< /RichMediaContent << /Assets << /Names [(current-training.mp4) 31 0 R] >> >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /Filespec /F (current-training.mp4) >>\nendobj\n"
    . "50 0 obj\n<< /Type /Filespec /F (stale-attachment.pdf) /EF << /F 51 0 R >> >>\nendobj\n"
    . "51 0 obj\n<< /Type /EmbeddedFile /Length " . strlen($staleAttachmentPayload) . " >>\nstream\n{$staleAttachmentPayload}\nendstream\nendobj\n"
    . "60 0 obj\n<< /Names [(stale-attachment.pdf) 50 0 R] >>\nendobj\n"
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
$goToEmbedded = array_values(array_filter(
    $actions,
    static fn (array $action): bool => ($action['action_type'] ?? null) === 'GoToE'
));
$attachmentObjects = [];
$targetNames = [];
foreach ($goToEmbedded as $action) {
    foreach (($action['attachment']['embedded_file_objects'] ?? []) as $objectNumber) {
        $attachmentObjects[] = $objectNumber;
    }
    if (is_string($action['target']['name'] ?? null)) {
        $targetNames[] = $action['target']['name'];
    }
}

if (count($annotations) !== 1 || count($goToEmbedded) !== 2 || $attachmentObjects !== [21]) {
    throw new RuntimeException('Expected one rich-media annotation and two embedded-document action review rows.');
}

$fileNames = is_array($annotation['file_names'] ?? null) ? $annotation['file_names'] : [];

echo '<!-- markerpdf-pdf-richmedia-attachment-action-boundary ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_media' => false,
    'executes_javascript' => false,
    'native_boundary' => 'RichMedia /A and /AA GoToE attachment actions are review-only metadata; Filespec /EF payloads are not visible page text',
    'review_annotation_count' => count($annotations),
    'review_action_count' => count($actions),
    'embedded_action_count' => count($goToEmbedded),
    'go_to_e_files' => array_values(array_filter(array_map(
        static fn (array $action): ?string => $action['file'] ?? null,
        $goToEmbedded
    ))),
    'target_names' => $targetNames,
    'attachment_embedded_file_objects' => $attachmentObjects,
    'stale_attachment_not_promoted' => !in_array('stale-attachment.pdf', $fileNames, true),
    'attachment_payload_text_excluded' => !str_contains($plainText, 'Attachment Payload Leak')
        && !str_contains($plainText, 'Stale Attachment Payload Leak'),
    'javascript_action_blocked' => in_array('blocked-javascript', array_column($actions, 'safety'), true),
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
        $detail = $action['file']
            ?? $action['target']['name']
            ?? $action['script_preview']
            ?? $action['action_type'];
        echo '<li data-marker-action-type="' . htmlspecialchars((string) $action['action_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ' data-marker-action-safety="' . htmlspecialchars((string) $action['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ' data-marker-action-event="' . htmlspecialchars((string) ($action['event'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ' data-marker-action-chained="' . htmlspecialchars($action['chained'] ? 'true' : 'false', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . htmlspecialchars((string) $detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . "</li>\n";
    }
}
echo "</ul>\n<!-- /wp:list -->\n";
