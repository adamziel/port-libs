<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress Q Path Image Intro) Tj ET\n"
    . "q q 10 10 m 40 10 l 40 30 l 10 30 l h Q W n 50 0 0 40 0 0 cm /QPath#20Image Do Q\n"
    . "20 20 m 30 20 l 30 30 l 20 30 l h q W n Q W n 50 0 0 40 60 0 cm /Cleared#20QPath#20Image Do\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress Q Path Image Outro) Tj ET';
$qPathPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Q Path Image Payload Noise) Tj ET';
$clearedPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Cleared Q Path Image Payload Noise) Tj ET';
$qPathCompressed = gzcompress($qPathPayload);
$clearedCompressed = gzcompress($clearedPayload);
if (!is_string($qPathCompressed) || !is_string($clearedCompressed)) {
    throw new RuntimeException('Unable to compress q/Q path image XObject smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /QPath#20Image 5 0 R /Cleared#20QPath#20Image 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 50 /Height 40 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($qPathCompressed) . " >>\nstream\n{$qPathCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 50 /Height 40 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($clearedCompressed) . " >>\nstream\n{$clearedCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$qPath = $entriesByName['QPath Image'] ?? [];
$cleared = $entriesByName['Cleared QPath Image'] ?? [];
$metadata = [
    'source' => 'native-pdf-image-xobject-qpath-boundary-currentbase',
    'upstream_boundary' => 'PDF graphics-state q/Q restores clipping and CTM but not the current path; Image XObject review uses that parser boundary before media handoff',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'] ?? null,
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'] ?? null,
    'q_path_image_visible_bbox' => $qPath['image_visible_bbox'] ?? null,
    'q_path_clip_applied' => $qPath['clip_applied'] ?? false,
    'cleared_q_path_image_visible_bbox' => $cleared['image_visible_bbox'] ?? null,
    'cleared_q_path_clip_applied' => $cleared['clip_applied'] ?? true,
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Q Path Image Payload Noise')
        || str_contains($plainText, 'WordPress Cleared Q Path Image Payload Noise'),
];

if (
    $metadata['image_xobject_count'] !== 2
    || $metadata['invoked_image_xobject_count'] !== 2
    || $metadata['q_path_image_visible_bbox'] !== [10.0, 10.0, 40.0, 30.0]
    || $metadata['q_path_clip_applied'] !== true
    || $metadata['cleared_q_path_image_visible_bbox'] !== [60.0, 0.0, 110.0, 40.0]
    || $metadata['cleared_q_path_clip_applied'] !== false
    || $metadata['payload_in_visible_text'] !== false
) {
    throw new RuntimeException('q/Q current-path image XObject boundary smoke failed.');
}

echo '<!-- markerpdf:pdf-image-xobject-qpath-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
