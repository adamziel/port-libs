<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$mirrorPayload = '<wp-export><post id="annotation-af-mirror-smoke"/></wp-export>';
$annotationOnlyPayload = '<wp-export><post id="annotation-af-only-smoke"/></wp-export>';
$duplicatePayload = '<wp-export><post id="annotation-af-duplicate-smoke"/></wp-export>';
$mirrorChecksum = md5($mirrorPayload);
$annotationOnlyChecksum = md5($annotationOnlyPayload);
$duplicateChecksum = md5($duplicatePayload);
$content = 'BT /F1 12 Tf 72 720 Td (Annotation Associated File Smoke Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(annotation-mirror-smoke.xml) 10 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Text /Rect [72 700 96 724] /F 4 /Contents (Mirror annotation associated source smoke) /NM (annot-af-mirror-smoke) /AF [10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (annotation-mirror-smoke.xml) /Desc (Mirrored annotation associated source smoke) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($mirrorPayload) . " /CheckSum <{$mirrorChecksum}> /ModDate (D:20260608065617Z) >> /Length " . strlen($mirrorPayload) . " >>\n"
    . "stream\n{$mirrorPayload}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [120 680 220 700] /Contents (Annotation-only source smoke) /AF [13 0 R] >>\nendobj\n"
    . "13 0 obj\n<< /Type /Filespec /F (annotation-only-smoke.xml) /Desc (Annotation-only associated source smoke) /AFRelationship /Supplement /EF << /F 14 0 R >> >>\nendobj\n"
    . "14 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($annotationOnlyPayload) . " /CheckSum <{$annotationOnlyChecksum}> /ModDate (D:20260608065618Z) >> /Length " . strlen($annotationOnlyPayload) . " >>\n"
    . "stream\n{$annotationOnlyPayload}\nendstream\nendobj\n"
    . "16 0 obj\n<< /Type /Annot /Subtype /Text /Rect [240 680 260 700] /Contents (Duplicate annotation AF smoke decoy) /AF [17 0 R] /#41F [17 0 R] >>\nendobj\n"
    . "17 0 obj\n<< /Type /Filespec /F (annotation-duplicate-smoke.xml) /Desc (Duplicate annotation AF smoke source) /AFRelationship /Alternative /EF << /F 18 0 R >> >>\nendobj\n"
    . "18 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicatePayload) . " /CheckSum <{$duplicateChecksum}> >> /Length " . strlen($duplicatePayload) . " >>\n"
    . "stream\n{$duplicatePayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

if (($summary['attachment_count'] ?? null) !== 2 || count($files) !== 2) {
    throw new RuntimeException('Expected annotation AF mirror plus annotation-only associated file rows.');
}

$mirror = $summary['attachments'][0] ?? [];
$annotationOnly = $summary['attachments'][1] ?? [];
if (
    !is_array($mirror)
    || !is_array($annotationOnly)
    || ($mirror['annotation_associated_file'] ?? null) !== true
    || ($mirror['annotation_associated_file_source'] ?? null) !== 'annotation_af'
    || ($mirror['source'] ?? null) !== 'embedded-files-name-tree'
    || ($annotationOnly['source'] ?? null) !== 'annotation-associated-file'
    || ($annotationOnly['annotation_subtype'] ?? null) !== 'Link'
    || ($annotationOnly['checksum_matches'] ?? null) !== true
    || array_key_exists('bytes', $mirror)
    || array_key_exists('bytes', $annotationOnly)
) {
    throw new RuntimeException('Expected annotation AF review metadata without summary payload bytes.');
}

foreach ([
    'annotation-duplicate-smoke.xml',
    'Duplicate annotation AF smoke source',
    $duplicatePayload,
    $duplicateChecksum,
] as $hidden) {
    if (str_contains($summaryJson, $hidden) || str_contains($filesJson, $hidden) || str_contains($plainText, $hidden)) {
        throw new RuntimeException('Expected malformed duplicate annotation AF rows to be suppressed.');
    }
}

if (
    str_contains($summaryJson, $mirrorPayload)
    || str_contains($summaryJson, $annotationOnlyPayload)
    || $plainText !== 'Annotation Associated File Smoke Body'
    || str_contains($plainText, '<wp-export>')
) {
    throw new RuntimeException('Expected visible text and attachment summary to exclude embedded payload bytes.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf:annotation-associated-file-boundary ' . $htmlJson([
    'support_component' => 'native-pdf-embedded-files-attachment-parser',
    'native_boundary' => 'annotation /AF associated FileSpec arrays are attachment review metadata',
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'mirror_source' => $mirror['source'] ?? null,
    'mirror_annotation_object_id' => $mirror['annotation_object_id'] ?? null,
    'annotation_only_source' => $annotationOnly['source'] ?? null,
    'annotation_only_subtype' => $annotationOnly['annotation_subtype'] ?? null,
    'annotation_af_mirror_merged' => ($mirror['annotation_associated_file_source'] ?? null) === 'annotation_af',
    'annotation_only_checksum_matches' => $annotationOnly['checksum_matches'] ?? null,
    'duplicate_annotation_af_suppressed' => !str_contains($summaryJson, 'annotation-duplicate-smoke'),
    'summary_exposes_payload_bytes' => array_key_exists('bytes', $mirror) || array_key_exists('bytes', $annotationOnly),
    'visible_text_excludes_attachment_payloads' => !str_contains($plainText, '<wp-export>'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($summary['attachments'] as $attachment) {
    $href = 'media/' . (string) ($attachment['filename_storage_name'] ?? $attachment['filename'] ?? 'attachment');
    echo '<!-- wp:file {"href":"' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
    echo '<div class="wp-block-file"><a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars((string) ($attachment['filename'] ?? 'attachment'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</a></div>\n";
    echo "<!-- /wp:file -->\n";
}
