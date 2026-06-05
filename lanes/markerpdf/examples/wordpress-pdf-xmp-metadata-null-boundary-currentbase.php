<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Null Metadata Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata null >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Title (Null Metadata Info Title) /Author (Null Metadata Author) /Producer (Null Metadata Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 5 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['source'] ?? null) !== ['info']) {
    throw new RuntimeException('Expected null catalog Metadata to behave as absent and use Info-only metadata.');
}
if (($metadata['title'] ?? null) !== 'Null Metadata Info Title') {
    throw new RuntimeException('Expected trailer Info title fallback for null catalog Metadata.');
}
if (isset($metadata['catalog']['metadata_stream_review'])) {
    throw new RuntimeException('Expected no catalog metadata stream review for the PDF null object.');
}
if ($plainText !== 'Null Metadata Boundary Body') {
    throw new RuntimeException('Expected page text to remain the only visible import text.');
}
if (!is_string($encoded) || str_contains($encoded, 'catalog_metadata_stream_boundary')) {
    throw new RuntimeException('Null catalog Metadata should not emit a malformed-stream boundary row.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-metadata-null-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-catalog-metadata-null-boundary',
    'native_boundary' => 'Catalog /Metadata null is the PDF null object and behaves as absent before document XMP promotion',
    'source' => $metadata['source'],
    'info_fallback_title' => $metadata['title'] ?? null,
    'metadata_null_treated_as_absent' => true,
    'catalog_metadata_review_absent' => !isset($metadata['catalog']['metadata_stream_review']),
    'xmp_not_promoted' => $metadata['xmp'] === [],
    'visible_text_is_page_content_only' => $plainText === 'Null Metadata Boundary Body',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
