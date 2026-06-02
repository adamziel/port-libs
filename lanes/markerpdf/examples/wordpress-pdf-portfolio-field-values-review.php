<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceXml = '<wp-export><post id="509"/></wp-export>';
$pageContent = 'BT /F1 12 Tf 72 720 Td (Portfolio Field Review) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /AF [10 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Collection /View /T /D (source-unicode.xml) /Schema << /NameField << /Subtype /F /N (Filename) /O 1 >> /DescriptionField << /Subtype /Desc /N (Description) /O 2 /V true /E false >> /CreatedField << /Subtype /CreationDate /N (Created) /O 3 >> /ModifiedField << /Subtype /ModDate /N (Modified) /O 4 >> /BytesField << /Subtype /Size /N (Bytes) /O 5 >> /Subject << /Subtype /S /N (Subject) /O 6 >> /Priority << /Subtype /N /N (Priority) /O 7 >> /ReviewDate << /Subtype /D /N (Reviewed) /O 8 >> >> /Sort << /S [/Priority /ModifiedField] /A [true false] >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (legacy-source.xml) /UF (source-unicode.xml) /Desc (Original WordPress export) /AFRelationship /Source /CI 30 0 R /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourceXml) . " /CreationDate (D:20260602113600Z) /ModDate (D:20260602113700Z) >> /Length " . strlen($sourceXml) . " >>\nstream\n{$sourceXml}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /CollectionItem /Subject (Migration Packet) /Priority << /Type /CollectionSubitem /D 2 /P (Priority ) >> /ReviewDate (D:20260602113800Z) /Stale (not in schema) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$attachments = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));

if (count($attachments) !== 1) {
    throw new RuntimeException('Expected one portfolio attachment.');
}

$attachment = $attachments[0];
$fields = $attachment['portfolio_field_values'] ?? [];
if (($fields['NameField']['source'] ?? null) !== 'file_spec') {
    throw new RuntimeException('Expected filename collection field to derive from the FileSpec.');
}
if (($fields['BytesField']['value'] ?? null) !== strlen($sourceXml)) {
    throw new RuntimeException('Expected size collection field to derive from embedded-file Params.');
}
if (($fields['Priority']['display_value'] ?? null) !== 'Priority 2') {
    throw new RuntimeException('Expected CollectionSubitem prefix display value.');
}
if (($fields['Priority']['label'] ?? null) !== 'Priority') {
    throw new RuntimeException('Expected numeric collection field label to survive /Subtype /N.');
}
if (array_key_exists('Stale', $fields)) {
    throw new RuntimeException('Unexpected unschematized portfolio field in review output.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-pdf-portfolio-field-values-smoke ' . $htmlJson([
    'support_component' => 'native-pdf-portfolio-field-value-review',
    'native_boundary' => 'PDF Portfolio /Collection schema subtypes applied to FileSpec and /CI field values before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'attachment_count' => count($attachments),
    'field_sources' => array_map(
        static fn (array $field): ?string => $field['source'] ?? null,
        $fields
    ),
    'field_value_types' => array_map(
        static fn (array $field): ?string => $field['value_type'] ?? null,
        $fields
    ),
    'priority_display_value' => $fields['Priority']['display_value'] ?? null,
    'file_related_values' => [
        'filename' => $fields['NameField']['value'] ?? null,
        'description' => $fields['DescriptionField']['value'] ?? null,
        'created_at' => $fields['CreatedField']['value'] ?? null,
        'modified_at' => $fields['ModifiedField']['value'] ?? null,
        'declared_size' => $fields['BytesField']['value'] ?? null,
    ],
    'excluded_attachment_payload_text' => !str_contains($plainText, '<wp-export>'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- wp:file {"href":"media/' . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n\n";

echo '<!-- markerpdf:portfolio-field-values ' . $htmlJson([
    'filename' => $attachment['filename'],
    'relationship' => $attachment['relationship'] ?? null,
    'portfolio' => $attachment['portfolio'] ?? [],
    'portfolio_item' => $attachment['portfolio_item'] ?? [],
    'portfolio_field_values' => $fields,
]) . " -->\n";
