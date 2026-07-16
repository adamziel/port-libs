<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$namesValidPayload = '<wp-export><post id="smoke-valid-indirect-names-array"/></wp-export>';
$namesMalformedPayload = '<wp-export><post id="smoke-malformed-indirect-names-array"/></wp-export>';
$namesDecoyPayload = '<wp-export><post id="smoke-trailing-indirect-names-decoy"/></wp-export>';
$namesValidChecksum = md5($namesValidPayload);
$namesMalformedChecksum = md5($namesMalformedPayload);
$namesDecoyChecksum = md5($namesDecoyPayload);
$namesContent = 'BT /F1 12 Tf 72 720 Td (Indirect NameTree Names Array Smoke Body) Tj ET';

$namesPdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($namesContent) . " >>\nstream\n{$namesContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Limits [(malformed-indirect-names-smoke.xml) (valid-indirect-names-smoke.xml)] /Kids [7 0 R 8 0 R] >>\nendobj\n"
    . "7 0 obj\n<< /Limits [(valid-indirect-names-smoke.xml) (valid-indirect-names-smoke.xml)] /Names [(valid-indirect-names-smoke.xml) 10 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Limits [(malformed-indirect-names-smoke.xml) (malformed-indirect-names-smoke.xml)] /Names 50 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (valid-indirect-names-smoke.xml) /Desc (Valid sibling EmbeddedFiles smoke source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($namesValidPayload) . " /CheckSum <{$namesValidChecksum}> /ModDate (D:20260608095832Z) >> /Length " . strlen($namesValidPayload) . " >>\n"
    . "stream\n{$namesValidPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (malformed-indirect-names-smoke.xml) /Desc (Malformed indirect Names smoke source) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($namesMalformedPayload) . " /CheckSum <{$namesMalformedChecksum}> >> /Length " . strlen($namesMalformedPayload) . " >>\n"
    . "stream\n{$namesMalformedPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /F (trailing-indirect-names-smoke-decoy.xml) /Desc (Trailing indirect Names smoke decoy) /AFRelationship /Alternative /EF << /F 31 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($namesDecoyPayload) . " /CheckSum <{$namesDecoyChecksum}> >> /Length " . strlen($namesDecoyPayload) . " >>\n"
    . "stream\n{$namesDecoyPayload}\nendstream\nendobj\n"
    . "50 0 obj\n[(malformed-indirect-names-smoke.xml) 20 0 R] 30 0 R\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$kidsCatalogPayload = '<wp-export><post id="smoke-catalog-af-after-rejected-kids"/></wp-export>';
$kidsMalformedPayload = '<wp-export><post id="smoke-malformed-indirect-kids-array"/></wp-export>';
$kidsDecoyPayload = '<wp-export><post id="smoke-trailing-indirect-kids-decoy"/></wp-export>';
$kidsCatalogChecksum = md5($kidsCatalogPayload);
$kidsMalformedChecksum = md5($kidsMalformedPayload);
$kidsDecoyChecksum = md5($kidsDecoyPayload);
$kidsContent = 'BT /F1 12 Tf 72 720 Td (Indirect NameTree Kids Array Smoke Body) Tj ET';

$kidsPdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($kidsContent) . " >>\nstream\n{$kidsContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Limits [(malformed-kids-smoke.xml) (malformed-kids-smoke.xml)] /Kids 50 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Limits [(malformed-kids-smoke.xml) (malformed-kids-smoke.xml)] /Names [(malformed-kids-smoke.xml) 20 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (catalog-af-after-rejected-kids-smoke.xml) /Desc (Catalog AF fallback after rejected indirect Kids smoke array) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($kidsCatalogPayload) . " /CheckSum <{$kidsCatalogChecksum}> /ModDate (D:20260608095833Z) >> /Length " . strlen($kidsCatalogPayload) . " >>\n"
    . "stream\n{$kidsCatalogPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (malformed-kids-smoke.xml) /Desc (Malformed indirect Kids smoke source) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($kidsMalformedPayload) . " /CheckSum <{$kidsMalformedChecksum}> >> /Length " . strlen($kidsMalformedPayload) . " >>\n"
    . "stream\n{$kidsMalformedPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /F (trailing-kids-smoke-decoy.xml) /Desc (Trailing indirect Kids smoke decoy) /AFRelationship /Alternative /EF << /F 31 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($kidsDecoyPayload) . " /CheckSum <{$kidsDecoyChecksum}> >> /Length " . strlen($kidsDecoyPayload) . " >>\n"
    . "stream\n{$kidsDecoyPayload}\nendstream\nendobj\n"
    . "50 0 obj\n[7 0 R] 30 0 R\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$attachmentExtractor = new PdfAttachmentExtractor();
$embeddedFileExtractor = new PdfEmbeddedFileExtractor();
$textExtractor = new PdfTextExtractor();

$namesSummary = $attachmentExtractor->attachmentSummary($namesPdf);
$namesFiles = $embeddedFileExtractor->extractEmbeddedFiles($namesPdf);
$namesText = trim($textExtractor->extractPlainText($namesPdf));
$namesSummaryJson = json_encode($namesSummary, JSON_UNESCAPED_SLASHES) ?: '';
$namesFilesJson = json_encode($namesFiles, JSON_UNESCAPED_SLASHES) ?: '';

$kidsSummary = $attachmentExtractor->attachmentSummary($kidsPdf);
$kidsFiles = $embeddedFileExtractor->extractEmbeddedFiles($kidsPdf);
$kidsText = trim($textExtractor->extractPlainText($kidsPdf));
$kidsSummaryJson = json_encode($kidsSummary, JSON_UNESCAPED_SLASHES) ?: '';
$kidsFilesJson = json_encode($kidsFiles, JSON_UNESCAPED_SLASHES) ?: '';

$namesAttachment = $namesSummary['attachments'][0] ?? null;
$kidsAttachment = $kidsSummary['attachments'][0] ?? null;

if (!is_array($namesAttachment)
    || !is_array($kidsAttachment)
    || ($namesSummary['attachment_count'] ?? null) !== 1
    || ($kidsSummary['attachment_count'] ?? null) !== 1
    || count($namesFiles) !== 1
    || count($kidsFiles) !== 1
    || ($namesAttachment['filename'] ?? null) !== 'valid-indirect-names-smoke.xml'
    || ($kidsAttachment['filename'] ?? null) !== 'catalog-af-after-rejected-kids-smoke.xml'
    || ($namesFiles[0]['content'] ?? null) !== $namesValidPayload
    || ($kidsFiles[0]['content'] ?? null) !== $kidsCatalogPayload
    || str_contains($namesSummaryJson, 'malformed-indirect-names-smoke.xml')
    || str_contains($namesFilesJson, 'malformed-indirect-names-smoke.xml')
    || str_contains($namesSummaryJson, 'trailing-indirect-names-smoke-decoy.xml')
    || str_contains($namesFilesJson, 'trailing-indirect-names-smoke-decoy.xml')
    || str_contains($kidsSummaryJson, 'malformed-kids-smoke.xml')
    || str_contains($kidsFilesJson, 'malformed-kids-smoke.xml')
    || str_contains($kidsSummaryJson, 'trailing-kids-smoke-decoy.xml')
    || str_contains($kidsFilesJson, 'trailing-kids-smoke-decoy.xml')
    || str_contains($namesText, '<wp-export>')
    || str_contains($kidsText, '<wp-export>')
) {
    throw new RuntimeException('Expected indirect EmbeddedFiles name-tree arrays with trailing operands to fail closed.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-pdf-attachment-indirect-nametree-array-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-embeddedfiles-name-tree-array-boundary',
    'native_boundary' => 'EmbeddedFiles /Names and /Kids indirect arrays must resolve to one top-level array object',
    'names_attachment_count' => $namesSummary['attachment_count'],
    'kids_attachment_count' => $kidsSummary['attachment_count'],
    'valid_sibling_preserved' => ($namesAttachment['filename'] ?? null) === 'valid-indirect-names-smoke.xml',
    'catalog_af_fallback_preserved' => ($kidsAttachment['filename'] ?? null) === 'catalog-af-after-rejected-kids-smoke.xml',
    'indirect_names_array_rejected' => !str_contains($namesSummaryJson, 'malformed-indirect-names-smoke.xml')
        && !str_contains($namesFilesJson, 'malformed-indirect-names-smoke.xml'),
    'indirect_kids_array_rejected' => !str_contains($kidsSummaryJson, 'malformed-kids-smoke.xml')
        && !str_contains($kidsFilesJson, 'malformed-kids-smoke.xml'),
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $namesAttachment)
        && !array_key_exists('bytes', $kidsAttachment),
    'payload_text_excluded_from_visible_text' => !str_contains($namesText, '<wp-export>')
        && !str_contains($kidsText, '<wp-export>'),
    'executes_python_or_models' => $namesSummary['executes_python_or_models'] || $kidsSummary['executes_python_or_models'],
    'executes_external_pdf_tools' => $namesSummary['executes_external_pdf_tools'] || $kidsSummary['executes_external_pdf_tools'],
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($namesText . ' / ' . $kidsText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ([$namesAttachment, $kidsAttachment] as $attachment) {
    $storageName = (string) ($attachment['filename_storage_name'] ?? $attachment['filename']);
    echo '<!-- wp:file {"href":"media/' . htmlspecialchars($storageName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
    echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars($storageName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</a></div>\n";
    echo "<!-- /wp:file -->\n";
}
