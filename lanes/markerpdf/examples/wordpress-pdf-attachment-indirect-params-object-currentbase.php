<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$badPrimaryPayload = '<wp-export><post id="bad-indirect-params-object"/></wp-export>';
$validPayload = '<wp-export><post id="valid-indirect-params-boundary"/></wp-export>';
$relatedPrimaryPayload = '<wp-export><post id="related-primary-indirect-params"/></wp-export>';
$badRelatedPayload = 'BAD_RELATED_INDIRECT_PARAMS_OBJECT_SHOULD_NOT_COUNT';
$validRelatedPayload = '{"manifest":"valid-indirect-params-related"}';
$badPrimaryChecksum = md5($badPrimaryPayload);
$validChecksum = md5($validPayload);
$relatedPrimaryChecksum = md5($relatedPrimaryPayload);
$badRelatedChecksum = md5($badRelatedPayload);
$validRelatedChecksum = md5($validRelatedPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Indirect Params Object Boundary Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R 30 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Names [(bad-indirect-params.xml) 10 0 R (valid-indirect-params.xml) 20 0 R (related-primary-params.xml) 30 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (bad-indirect-params.xml) /Desc (Bad indirect Params source) /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params 50 0 R /Length " . strlen($badPrimaryPayload) . " >>\nstream\n{$badPrimaryPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (valid-indirect-params.xml) /Desc (Valid indirect Params source) /AFRelationship /Source /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params 60 0 R /Length " . strlen($validPayload) . " >>\nstream\n{$validPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Filespec /F (related-primary-params.xml) /Desc (Primary with indirect Params related files) /AFRelationship /Supplement /EF << /F 31 0 R >> /RF << /F [(bad-related-params.bin) 32 0 R] /UF [(valid-related-params.json) 33 0 R] >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params 70 0 R /Length " . strlen($relatedPrimaryPayload) . " >>\nstream\n{$relatedPrimaryPayload}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Foctet-stream /Params 80 0 R /Length " . strlen($badRelatedPayload) . " >>\nstream\n{$badRelatedPayload}\nendstream\nendobj\n"
    . "33 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params 90 0 R /Length " . strlen($validRelatedPayload) . " >>\nstream\n{$validRelatedPayload}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Size " . strlen($badPrimaryPayload) . " /CheckSum <{$badPrimaryChecksum}> /ModDate (D:20260608191400Z) >> 99 0 R\nendobj\n"
    . "60 0 obj\n<< /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608191401Z) >>\nendobj\n"
    . "70 0 obj\n<< /Size " . strlen($relatedPrimaryPayload) . " /CheckSum <{$relatedPrimaryChecksum}> /CreationDate (D:20260608191402Z) >>\nendobj\n"
    . "80 0 obj\n<< /Size " . strlen($badRelatedPayload) . " /CheckSum <{$badRelatedChecksum}> /CreationDate (D:20260608191403Z) >> 98 0 R\nendobj\n"
    . "90 0 obj\n<< /Size " . strlen($validRelatedPayload) . " /CheckSum <{$validRelatedChecksum}> /CreationDate (D:20260608191404Z) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$valid = $summary['attachments'][0] ?? null;
$primary = $summary['attachments'][1] ?? null;
$related = is_array($primary) ? ($primary['related_files'][0] ?? null) : null;

if (!is_array($valid)
    || !is_array($primary)
    || !is_array($related)
    || ($summary['attachment_count'] ?? null) !== 2
    || ($valid['filename'] ?? null) !== 'valid-indirect-params.xml'
    || ($valid['checksum_matches'] ?? null) !== true
    || ($primary['filename'] ?? null) !== 'related-primary-params.xml'
    || ($primary['checksum_matches'] ?? null) !== true
    || ($related['related_filename'] ?? null) !== 'valid-related-params.json'
    || ($related['checksum_matches'] ?? null) !== true
    || count($files) !== 2
    || str_contains($summaryJson, 'bad-indirect-params.xml')
    || str_contains($summaryJson, 'bad-related-params.bin')
    || str_contains($summaryJson, $badPrimaryPayload)
    || str_contains($summaryJson, $badRelatedPayload)
    || str_contains($summaryJson, $badPrimaryChecksum)
    || str_contains($summaryJson, $badRelatedChecksum)
    || str_contains($summaryJson, $validPayload)
    || str_contains($summaryJson, $relatedPrimaryPayload)
    || str_contains($summaryJson, $validRelatedPayload)
    || str_contains($filesJson, $badPrimaryPayload)
    || str_contains($filesJson, $badRelatedPayload)
    || $plainText !== 'Indirect Params Object Boundary Body'
) {
    throw new RuntimeException('Expected tailed indirect EmbeddedFile /Params helper objects to fail closed before WordPress attachment review.');
}

echo '<!-- markerpdf-pdf-attachment-indirect-params-object-boundary ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-embeddedfile-params-boundary',
    'native_boundary' => 'indirect EmbeddedFile /Params helper objects must resolve to exactly one dictionary',
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'related_filename' => $related['related_filename'],
    'valid_checksum_matches' => $valid['checksum_matches'],
    'related_primary_checksum_matches' => $primary['checksum_matches'],
    'valid_related_checksum_matches' => $related['checksum_matches'],
    'bad_primary_indirect_params_excluded' => !str_contains($summaryJson, 'bad-indirect-params.xml')
        && !str_contains($filesJson, $badPrimaryPayload),
    'bad_related_indirect_params_excluded' => !str_contains($summaryJson, 'bad-related-params.bin')
        && !str_contains($filesJson, $badRelatedPayload),
    'attachment_payloads_omitted_from_summary' => !str_contains($summaryJson, $validPayload)
        && !str_contains($summaryJson, $relatedPrimaryPayload)
        && !str_contains($summaryJson, $validRelatedPayload),
    'visible_text_excludes_attachment_payloads' => $plainText === 'Indirect Params Object Boundary Body',
    'executes_python_or_models' => $summary['executes_python_or_models'],
    'executes_external_pdf_tools' => $summary['executes_external_pdf_tools'],
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- wp:file {"href":"media/' . htmlspecialchars((string) $valid['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $valid['filename_storage_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars((string) $valid['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n";
