<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Before inline OCMD import) Tj ET\n"
    . "/OC << /Type /OCMD /OCGs [20 0 R 21 0 R] /P /AllOn >> BDC q 16 0 0 8 72 690 cm /Inline#20Hidden Do Q EMC\n"
    . "/OC << /Type /OCMD /OCGs [20 0 R 22 0 R] /P /AllOn >> BDC q 16 0 0 8 96 690 cm /Inline#20Visible Do Q EMC\n"
    . "q 8 0 0 8 120 690 cm /Plain#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (After inline OCMD import) Tj ET';
$hiddenPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Hidden Inline OCMD Image Noise) Tj ET';
$visiblePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Visible Inline OCMD Image Noise) Tj ET';
$plainPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Plain Inline Image Noise) Tj ET';
$hiddenCompressed = gzcompress($hiddenPayload);
$visibleCompressed = gzcompress($visiblePayload);
$plainCompressed = gzcompress($plainPayload);
if (!is_string($hiddenCompressed) || !is_string($visibleCompressed) || !is_string($plainCompressed)) {
    throw new RuntimeException('Unable to compress inline OCMD image smoke payloads.');
}

$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OCProperties << /OCGs [20 0 R 21 0 R 22 0 R] /D << /BaseState /OFF /ON [20 0 R 22 0 R] /Order [20 0 R 21 0 R 22 0 R] >> >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 10 0 R >> /XObject << /Inline#20Hidden 5 0 R /Inline#20Visible 6 0 R /Plain#20Image 7 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($hiddenCompressed) . " >>\nstream\n{$hiddenCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($visibleCompressed) . " >>\nstream\n{$visibleCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($plainCompressed) . " >>\nstream\n{$plainCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n<< /Type /OCG /Name (Visible Shared Layer) >>\nendobj\n"
    . "21 0 obj\n<< /Type /OCG /Name (Hidden Inline Layer) >>\nendobj\n"
    . "22 0 obj\n<< /Type /OCG /Name (Visible Inline Layer) >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);

$entriesByName = [];
foreach ($review['entries'] ?? [] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$hiddenEntry = $entriesByName['Inline Hidden'] ?? [];
$visibleEntry = $entriesByName['Inline Visible'] ?? [];
$plainEntry = $entriesByName['Plain Image'] ?? [];
$expectedLines = ['Before inline OCMD import', 'After inline OCMD import'];
$payloadExcluded = !str_contains($plainText, 'WordPress Hidden Inline OCMD Image Noise')
    && !str_contains($plainText, 'WordPress Visible Inline OCMD Image Noise')
    && !str_contains($plainText, 'WordPress Plain Inline Image Noise');

if (
    $lines !== $expectedLines
    || !$payloadExcluded
    || ($review['image_xobject_count'] ?? 0) !== 3
    || ($review['invoked_image_xobject_count'] ?? 0) !== 2
    || ($review['uninvoked_image_xobject_count'] ?? 0) !== 1
    || ($hiddenEntry['optional_content_visible'] ?? false) !== true
    || ($hiddenEntry['invoked'] ?? true) !== false
    || ($hiddenEntry['invocation_count'] ?? -1) !== 0
    || ($hiddenEntry['decoded_sha256'] ?? null) !== hash('sha256', $hiddenPayload)
    || ($visibleEntry['invoked'] ?? false) !== true
    || ($visibleEntry['image_unit_bbox'] ?? null) !== [96.0, 690.0, 112.0, 698.0]
    || ($visibleEntry['decoded_sha256'] ?? null) !== hash('sha256', $visiblePayload)
    || ($plainEntry['invoked'] ?? false) !== true
    || ($plainEntry['image_unit_bbox'] ?? null) !== [120.0, 690.0, 128.0, 698.0]
    || ($plainEntry['decoded_sha256'] ?? null) !== hash('sha256', $plainPayload)
) {
    throw new RuntimeException('Inline OCMD image XObject boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-inline-ocmd-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text text pages plus marker.pdf.images.render_image review handoff; optional-content membership dictionaries gate painted image invocations',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'],
    'inline_ocmd_hidden_invocation_excluded' => ($hiddenEntry['invoked'] ?? true) === false,
    'inline_ocmd_hidden_metadata_retained' => ($hiddenEntry['decoded_sha256'] ?? null) === hash('sha256', $hiddenPayload),
    'inline_ocmd_visible_invocation_counted' => ($visibleEntry['invocation_count'] ?? 0) === 1,
    'plain_invocation_counted' => ($plainEntry['invocation_count'] ?? 0) === 1,
    'visible_image_bbox' => $visibleEntry['image_unit_bbox'] ?? null,
    'plain_image_bbox' => $plainEntry['image_unit_bbox'] ?? null,
    'hidden_payload_excluded_from_text' => !str_contains($plainText, 'WordPress Hidden Inline OCMD Image Noise'),
    'visible_payload_excluded_from_text' => !str_contains($plainText, 'WordPress Visible Inline OCMD Image Noise'),
    'plain_payload_excluded_from_text' => !str_contains($plainText, 'WordPress Plain Inline Image Noise'),
    'paragraphs' => $lines,
];

echo '<!-- markerpdf:pdf-image-xobject-inline-ocmd-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
