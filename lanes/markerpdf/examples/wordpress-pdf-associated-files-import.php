<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceXml = '<wp-export><post id="7"/></wp-export>';
$previewText = 'Rendered preview notes';
$pageContent = 'BT /F1 12 Tf 72 720 Td (Visible Associated File Review) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AF [10 0 R << /Type /Filespec /UF (preview.pdf) /Desc (Rendered preview) /AFRelationship /Alternative /EF << /UF 15 0 R >> >>] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Original WordPress export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourceXml) . " >> /Length " . strlen($sourceXml) . " >>\nstream\n{$sourceXml}\nendstream\nendobj\n"
    . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fpdf /Length " . strlen($previewText) . " >>\nstream\n{$previewText}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$attachments = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$relationships = array_map(static fn (array $attachment): ?string => $attachment['relationship'] ?? null, $attachments);
if (count($attachments) !== 2 || $relationships !== ['Source', 'Alternative']) {
    throw new RuntimeException('Expected Source and Alternative associated-file attachments.');
}

echo '<!-- markerpdf-pdf-associated-files-smoke ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-associated-files-filespec-parser',
    'native_boundary' => 'catalog /AF Filespec /AFRelationship review before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'attachment_count' => count($attachments),
    'sources' => array_column($attachments, 'source'),
    'relationships' => $relationships,
    'excluded_attachment_payload_text' => !str_contains($plainText, 'wp-export') && !str_contains($plainText, 'Rendered preview'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($attachments as $attachment) {
    echo '<!-- wp:file {"href":"media/' . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
    echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</a></div>\n";
    echo "<!-- /wp:file -->\n\n";
    echo '<!-- markerpdf:associated-file ' . htmlspecialchars(json_encode([
        'name' => $attachment['name'],
        'filename' => $attachment['filename'],
        'description' => $attachment['description'] ?? null,
        'relationship' => $attachment['relationship'] ?? null,
        'mime_type' => $attachment['mime_type'] ?? null,
        'size' => $attachment['size'],
        'declared_size' => $attachment['declared_size'] ?? null,
        'associated_file_index' => $attachment['associated_file_index'] ?? null,
    ], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";
}
