<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress titleless outline metadata fallback visible body) Tj ET';
$metadataPayload = 'BT /F1 12 Tf 72 720 Td (WordPress titleless outline metadata payload must stay hidden) Tj ET';
$metadataStream = gzcompress($metadataPayload);
if (!is_string($metadataStream)) {
    throw new RuntimeException('Unable to compress WordPress titleless outline metadata stream.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Parent 5 0 R /Metadata 8 0 R /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (WordPress Safe Titleless Metadata Appendix) /Parent 5 0 R /Prev 6 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataStream) . " >>\nstream\n{$metadataStream}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "%%EOF";

$textExtractor = new PdfTextExtractor();
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$lightweight = $textExtractor->extractOutlineMetadata($pdf);
$outline = $metadata['document_outline'] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedLightweight = json_encode($lightweight, JSON_UNESCAPED_SLASHES);

if (($outline['titles'] ?? []) !== ['WordPress Safe Titleless Metadata Appendix']) {
    throw new RuntimeException('Expected only the titled sibling to remain in outline review metadata.');
}
if (($outline['items'][0]['previous_object'] ?? null) !== 6) {
    throw new RuntimeException('Expected sibling traversal to continue across the titleless outline row.');
}
if (isset($outline['items'][0]['metadata_stream_review'])) {
    throw new RuntimeException('Expected titleless row metadata not to attach to the titled sibling.');
}
if ($plainText !== 'WordPress titleless outline metadata fallback visible body') {
    throw new RuntimeException('Expected fallback text to exclude titleless outline Metadata stream payload.');
}
if (!is_string($encodedMetadata)
    || !is_string($encodedLightweight)
    || str_contains($encodedMetadata, $metadataPayload)
    || str_contains($encodedLightweight, $metadataPayload)
    || str_contains($plainText, 'WordPress titleless outline metadata payload must stay hidden')
) {
    throw new RuntimeException('Expected titleless outline Metadata payload to stay out of metadata and visible text.');
}

echo '<!-- markerpdf-outline-metadata-titleless-fallback-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-titleless-fallback-currentbase',
    'support_component' => 'native-pdf-outline-metadata-fallback-exclusion',
    'native_boundary' => 'titleless outline rows still exclude their local /Metadata streams from lightweight fallback WordPress text',
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'titleless_outline_object' => 6,
    'safe_sibling_outline_object' => $outline['items'][0]['outline_object'] ?? null,
    'safe_sibling_previous_object' => $outline['items'][0]['previous_object'] ?? null,
    'outline_titles' => $outline['titles'] ?? [],
    'fallback_text' => $plainText,
    'metadata_payload_excluded_from_document_metadata' => !str_contains($encodedMetadata, $metadataPayload),
    'metadata_payload_excluded_from_lightweight_metadata' => !str_contains($encodedLightweight, $metadataPayload),
    'metadata_payload_excluded_from_visible_text' => !str_contains($plainText, 'metadata payload must stay hidden'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
