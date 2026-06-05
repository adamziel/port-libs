<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Artifact Image Boundary Intro) Tj ET\n"
    . "/Artifact BMC q 16 0 0 8 72 690 cm /Decorative#20Image Do Q EMC\n"
    . "/Artifact << /Subtype /Background /MCID 5 >> BDC q 12 0 0 6 96 690 cm /Background#20Image Do Q EMC\n"
    . "q 10 0 0 5 120 690 cm /Content#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Artifact Image Boundary Outro) Tj ET';
$decorativePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Decorative Artifact Image Noise) Tj ET';
$backgroundPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Background Artifact Image Noise) Tj ET';
$contentPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Content Image Noise) Tj ET';
$decorativeCompressed = gzcompress($decorativePayload);
$backgroundCompressed = gzcompress($backgroundPayload);
$contentCompressed = gzcompress($contentPayload);
if (!is_string($decorativeCompressed) || !is_string($backgroundCompressed) || !is_string($contentCompressed)) {
    throw new RuntimeException('Unable to compress artifact image XObject smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Decorative#20Image 5 0 R /Background#20Image 6 0 R /Content#20Image 7 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($decorativeCompressed) . " >>\nstream\n{$decorativeCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 2 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($backgroundCompressed) . " >>\nstream\n{$backgroundCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($contentCompressed) . " >>\nstream\n{$contentCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$metadata = [
    'source' => 'native-pdf-image-xobject-artifact-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text text separation plus marker.pdf.images.render_image media handoff with PDF /Artifact marked-content import filtering',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'] ?? null,
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'] ?? null,
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'] ?? null,
    'decorative_artifact_invoked' => $entriesByName['Decorative Image']['invoked'] ?? true,
    'decorative_artifact_invocation_count' => $entriesByName['Decorative Image']['invocation_count'] ?? null,
    'background_artifact_invoked' => $entriesByName['Background Image']['invoked'] ?? true,
    'background_artifact_invocation_count' => $entriesByName['Background Image']['invocation_count'] ?? null,
    'content_image_invoked' => $entriesByName['Content Image']['invoked'] ?? false,
    'content_image_bbox' => $entriesByName['Content Image']['image_unit_bbox'] ?? null,
    'artifact_payload_excluded_from_gutenberg_text' => !str_contains($plainText, 'WordPress Decorative Artifact Image Noise')
        && !str_contains($plainText, 'WordPress Background Artifact Image Noise'),
    'content_payload_excluded_from_gutenberg_text' => !str_contains($plainText, 'WordPress Content Image Noise'),
];

if (
    $metadata['image_xobject_count'] !== 3
    || $metadata['invoked_image_xobject_count'] !== 1
    || $metadata['uninvoked_image_xobject_count'] !== 2
    || $metadata['decorative_artifact_invoked'] !== false
    || $metadata['decorative_artifact_invocation_count'] !== 0
    || $metadata['background_artifact_invoked'] !== false
    || $metadata['background_artifact_invocation_count'] !== 0
    || $metadata['content_image_invoked'] !== true
    || $metadata['content_image_bbox'] !== [120.0, 690.0, 130.0, 695.0]
    || $metadata['artifact_payload_excluded_from_gutenberg_text'] !== true
    || $metadata['content_payload_excluded_from_gutenberg_text'] !== true
) {
    throw new RuntimeException('Artifact image XObject boundary smoke failed.');
}

echo '<!-- markerpdf:pdf-image-xobject-artifact-boundary-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
