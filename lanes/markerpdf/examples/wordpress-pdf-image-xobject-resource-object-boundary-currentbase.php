<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$malformedContent = "BT /F1 12 Tf 72 720 Td (Current malformed XObject resource tail intro) Tj ET\n"
    . "q 16 0 0 8 72 690 cm /Bad#20Tail#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Current malformed XObject resource tail outro) Tj ET';
$commentContent = "BT /F1 12 Tf 72 720 Td (Current comment XObject resource tail intro) Tj ET\n"
    . "q 12 0 0 6 72 690 cm /Comment#20Only#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Current comment XObject resource tail outro) Tj ET';
$badPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Bad Resource Tail Image Payload Noise) Tj ET';
$commentPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Comment Resource Tail Image Payload Noise) Tj ET';
$badCompressed = gzcompress($badPayload);
$commentCompressed = gzcompress($commentPayload);
if (!is_string($badCompressed) || !is_string($commentCompressed)) {
    throw new RuntimeException('Unable to compress image XObject resource-tail smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 10 0 R >> /XObject 20 0 R >> /Contents 11 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 10 0 R >> /XObject 21 0 R >> /Contents 12 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($badCompressed) . " >>\nstream\n{$badCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($commentCompressed) . " >>\nstream\n{$commentCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($malformedContent) . " >>\nstream\n{$malformedContent}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Length " . strlen($commentContent) . " >>\nstream\n{$commentContent}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Bad#20Tail#20Image 5 0 R >> /PrivateTail 99 0 R\nendobj\n"
    . "21 0 obj\n<< /Comment#20Only#20Image 6 0 R >> % comment-only tail remains PDF whitespace\nendobj\n"
    . "99 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /Length 0 >>\nstream\n\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

if (
    ($review['image_xobject_count'] ?? 0) !== 1
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || ($review['uninvoked_image_xobject_count'] ?? 0) !== 0
    || isset($entriesByName['Bad Tail Image'])
    || ($entriesByName['Comment Only Image']['object_number'] ?? null) !== 6
    || ($entriesByName['Comment Only Image']['invoked'] ?? false) !== true
    || ($entriesByName['Comment Only Image']['decoded_sha256'] ?? null) !== hash('sha256', $commentPayload)
    || str_contains($plainText, 'WordPress Bad Resource Tail Image Payload Noise')
    || str_contains($plainText, 'WordPress Comment Resource Tail Image Payload Noise')
) {
    throw new RuntimeException('Image XObject resource dictionary object boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-resource-object-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; indirect /XObject resource dictionary objects must be a single dictionary token',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'malformed_resource_object_tail_rejected' => !isset($entriesByName['Bad Tail Image']),
    'comment_only_resource_object_tail_accepted' => ($entriesByName['Comment Only Image']['invoked'] ?? false) === true,
    'comment_only_image_bbox' => $entriesByName['Comment Only Image']['image_unit_bbox'] ?? null,
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Bad Resource Tail Image Payload Noise')
        || str_contains($plainText, 'WordPress Comment Resource Tail Image Payload Noise'),
];

echo '<!-- markerpdf:pdf-image-xobject-resource-object-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
