<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$hiddenContent = "BT /F1 12 Tf 72 720 Td (Hidden WordPress content stream text) Tj ET\n"
    . 'q 16 0 0 8 72 690 cm /Hidden#20Stream Do Q';
$visibleContent = "BT /F1 12 Tf 72 700 Td (Visible WordPress content stream text) Tj ET\n"
    . 'q 16 0 0 8 96 690 cm /Visible#20Stream Do Q';
$hiddenPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Hidden Content Stream Image Noise) Tj ET';
$visiblePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Visible Content Stream Image Noise) Tj ET';
$hiddenCompressed = gzcompress($hiddenPayload);
$visibleCompressed = gzcompress($visiblePayload);
if (!is_string($hiddenCompressed) || !is_string($visibleCompressed)) {
    throw new RuntimeException('Unable to compress content-stream optional-content image smoke payloads.');
}

$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OCProperties << /OCGs [20 0 R 21 0 R] /D << /BaseState /OFF /ON [20 0 R] /Order [20 0 R 21 0 R] >> >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 10 0 R >> /XObject << /Hidden#20Stream 6 0 R /Visible#20Stream 7 0 R >> >> /Contents [4 0 R 5 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /OC 21 0 R /Length " . strlen($hiddenContent) . " >>\nstream\n{$hiddenContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /OC 20 0 R /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($hiddenCompressed) . " >>\nstream\n{$hiddenCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($visibleCompressed) . " >>\nstream\n{$visibleCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n<< /Type /OCG /Name (Visible WordPress Content Stream Layer) >>\nendobj\n"
    . "21 0 obj\n<< /Type /OCG /Name (Hidden WordPress Content Stream Layer) >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);

$entriesByName = [];
foreach ($review['entries'] ?? [] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$hiddenEntry = $entriesByName['Hidden Stream'] ?? [];
$visibleEntry = $entriesByName['Visible Stream'] ?? [];
$expectedLines = ['Visible WordPress content stream text'];
$payloadExcluded = !str_contains($plainText, 'WordPress Hidden Content Stream Image Noise')
    && !str_contains($plainText, 'WordPress Visible Content Stream Image Noise');

if (
    $lines !== $expectedLines
    || !$payloadExcluded
    || str_contains($plainText, 'Hidden WordPress content stream text')
    || ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || ($review['uninvoked_image_xobject_count'] ?? 0) !== 1
    || ($hiddenEntry['optional_content_visible'] ?? false) !== true
    || ($hiddenEntry['invoked'] ?? true) !== false
    || ($hiddenEntry['invocation_count'] ?? -1) !== 0
    || ($hiddenEntry['decoded_sha256'] ?? null) !== hash('sha256', $hiddenPayload)
    || ($visibleEntry['invoked'] ?? false) !== true
    || ($visibleEntry['invocation_count'] ?? 0) !== 1
    || ($visibleEntry['image_unit_bbox'] ?? null) !== [96.0, 690.0, 112.0, 698.0]
    || ($visibleEntry['decoded_sha256'] ?? null) !== hash('sha256', $visiblePayload)
) {
    throw new RuntimeException('Content-stream optional-content image XObject boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-content-stream-oc-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text text pages plus marker.pdf.images image handoff; page content stream /OC visibility gates painted image invocations before WordPress media review',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'],
    'content_stream_oc_hidden_invocation_excluded' => ($hiddenEntry['invoked'] ?? true) === false,
    'content_stream_oc_hidden_metadata_retained' => ($hiddenEntry['decoded_sha256'] ?? null) === hash('sha256', $hiddenPayload),
    'visible_content_stream_invocation_counted' => ($visibleEntry['invocation_count'] ?? 0) === 1,
    'visible_image_bbox' => $visibleEntry['image_unit_bbox'] ?? null,
    'hidden_text_excluded' => !str_contains($plainText, 'Hidden WordPress content stream text'),
    'hidden_payload_excluded_from_text' => !str_contains($plainText, 'WordPress Hidden Content Stream Image Noise'),
    'visible_payload_excluded_from_text' => !str_contains($plainText, 'WordPress Visible Content Stream Image Noise'),
    'paragraphs' => $lines,
];

echo '<!-- markerpdf:pdf-image-xobject-content-stream-oc-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
