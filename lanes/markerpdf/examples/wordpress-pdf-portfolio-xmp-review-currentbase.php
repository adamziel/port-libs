<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Attachment XMP Hidden Title</rdf:li></rdf:Alt></dc:title>'
    . '<xmp:CreateDate>2026-06-02T15:57:00Z</xmp:CreateDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$iccProfile = 'Attachment ICC bytes should stay out of document PDF/A roots';
$sourcePayload = '<wp-export><post id="1557"/></wp-export>';
$previewPayload = 'Preview attachment bytes';
$compressedXmp = gzcompress($xmp);
$compressedProfile = gzcompress($iccProfile);
if (!is_string($compressedXmp) || !is_string($compressedProfile)) {
    throw new RuntimeException('Unable to compress FileSpec metadata fixture.');
}

$pageContent = 'BT /F1 12 Tf 72 720 Td (Portfolio XMP Review Body) Tj ET';
$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [20 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Collection /View /T /D (source.xml) /Schema << /Subject << /Subtype /S /N (Subject) /O 1 >> /Bytes << /Subtype /Size /N (Bytes) /O 2 >> >> >>\nendobj\n"
    . "6 0 obj\n<< /Names [(source.xml) 10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Original WordPress export) /AFRelationship /Source /Metadata 30 0 R /OutputIntents [40 0 R] /CI << /Subject (Migration Source) >> /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (preview.pdf) /Desc (Rendered preview) /AFRelationship /Alternative /Metadata 30 0 R /OutputIntents [40 0 R] /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fpdf /Length " . strlen($previewPayload) . " >>\nstream\n{$previewPayload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (Attachment sRGB) /Info (Attachment PDF/A profile) /DestOutputProfile 41 0 R >>\nendobj\n"
    . "41 0 obj\n<< /N 3 /Alternate /DeviceRGB /Filter /FlateDecode /Length " . strlen($compressedProfile) . " >>\nstream\n{$compressedProfile}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$attachments = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$documentMetadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encodedAttachments = json_encode($attachments, JSON_UNESCAPED_SLASHES);

if (count($attachments) !== 2) {
    throw new RuntimeException('Expected name-tree and catalog-associated Portfolio attachments.');
}
if (($attachments[0]['metadata_review']['Type'] ?? null) !== 'Metadata') {
    throw new RuntimeException('Expected FileSpec XMP metadata review dictionary.');
}
if (($attachments[0]['output_intents_review'][0]['OutputConditionIdentifier'] ?? null) !== 'Attachment sRGB') {
    throw new RuntimeException('Expected FileSpec OutputIntent review dictionary.');
}
if (($documentMetadata['output_intents'] ?? []) !== [] || isset($documentMetadata['title']) || isset($documentMetadata['pdfa'])) {
    throw new RuntimeException('Expected attachment-local metadata to stay out of document roots.');
}
if (!is_string($encodedAttachments) || str_contains($encodedAttachments, 'Attachment XMP Hidden Title') || str_contains($encodedAttachments, $iccProfile)) {
    throw new RuntimeException('Attachment metadata stream payload leaked into review output.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-pdf-portfolio-xmp-review-smoke ' . $htmlJson([
    'support_component' => 'native-pdf-filespec-metadata-outputintent-review',
    'native_boundary' => 'Portfolio FileSpec /Metadata XMP and /OutputIntents stay attachment-local review metadata before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'attachment_count' => count($attachments),
    'metadata_review_types' => array_map(
        static fn (array $attachment): ?string => $attachment['metadata_review']['Type'] ?? null,
        $attachments
    ),
    'outputintent_identifiers' => array_map(
        static fn (array $attachment): ?string => $attachment['output_intents_review'][0]['OutputConditionIdentifier'] ?? null,
        $attachments
    ),
    'attachment_xmp_not_promoted_to_document_title' => !isset($documentMetadata['title']),
    'attachment_outputintent_not_promoted_to_pdfa' => !isset($documentMetadata['pdfa']) && ($documentMetadata['output_intents'] ?? []) === [],
    'xmp_stream_payload_omitted' => !str_contains($encodedAttachments, 'Attachment XMP Hidden Title'),
    'icc_stream_payload_omitted' => !str_contains($encodedAttachments, $iccProfile),
    'visible_text' => $plainText,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($attachments as $attachment) {
    echo '<!-- wp:file {"href":"media/' . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
    echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars((string) $attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</a></div>\n";
    echo "<!-- /wp:file -->\n";
    echo '<!-- markerpdf:portfolio-attachment-metadata ' . $htmlJson([
        'filename' => $attachment['filename'] ?? null,
        'relationship' => $attachment['relationship'] ?? null,
        'portfolio_item' => $attachment['portfolio_item'] ?? [],
        'metadata_review' => $attachment['metadata_review'] ?? [],
        'output_intents_review' => $attachment['output_intents_review'] ?? [],
    ]) . " -->\n\n";
}
