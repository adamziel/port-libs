<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Current Image Resource Tail Intro) Tj ET\n"
    . "q 12 0 0 6 72 690 cm /Bad#20Tail#20Image Do Q\n"
    . "q 10 0 0 5 100 690 cm /Good#20Image Do Q\n"
    . "q 8 0 0 4 126 690 cm /Comment#20Tail#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Current Image Resource Tail Outro) Tj ET';
$badPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Bad Direct Resource Tail Image Payload Noise) Tj ET';
$goodPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Good Direct Resource Tail Image Payload Noise) Tj ET';
$commentPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Comment Direct Resource Tail Image Payload Noise) Tj ET';
$badCompressed = gzcompress($badPayload);
$goodCompressed = gzcompress($goodPayload);
$commentCompressed = gzcompress($commentPayload);
if (!is_string($badCompressed) || !is_string($goodCompressed) || !is_string($commentCompressed)) {
    throw new RuntimeException('Unable to compress image XObject resource-entry-tail smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Bad#20Tail#20Image 5 0 R 99 0 R /Good#20Image 6 0 R /Comment#20Tail#20Image 7 0 R % comment-only tail\n >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($badCompressed) . " >>\nstream\n{$badCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($goodCompressed) . " >>\nstream\n{$goodCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($commentCompressed) . " >>\nstream\n{$commentCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "99 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /Length 0 >>\nstream\n\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

if (
    ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 2
    || isset($entriesByName['Bad Tail Image'])
    || ($entriesByName['Good Image']['decoded_sha256'] ?? null) !== hash('sha256', $goodPayload)
    || ($entriesByName['Comment Tail Image']['decoded_sha256'] ?? null) !== hash('sha256', $commentPayload)
    || str_contains($plainText, 'WordPress Bad Direct Resource Tail Image Payload Noise')
    || str_contains($plainText, 'WordPress Good Direct Resource Tail Image Payload Noise')
    || str_contains($plainText, 'WordPress Comment Direct Resource Tail Image Payload Noise')
) {
    throw new RuntimeException('Image XObject resource-entry-tail boundary smoke failed.');
}

$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
$metadata = [
    'source' => 'native-pdf-image-xobject-resource-entry-tail-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable page text plus marker.pdf.images.render_image image handoff; XObject resource dictionary entries must end before the next name key',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'],
    'malformed_resource_entry_excluded' => !isset($entriesByName['Bad Tail Image']),
    'valid_sibling_image_painted' => ($entriesByName['Good Image']['invoked'] ?? false) === true,
    'comment_tail_image_painted' => ($entriesByName['Comment Tail Image']['invoked'] ?? false) === true,
    'bad_payload_hash_excluded_from_review' => !str_contains($encodedReview, hash('sha256', $badPayload)),
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Bad Direct Resource Tail Image Payload Noise')
        || str_contains($plainText, 'WordPress Good Direct Resource Tail Image Payload Noise')
        || str_contains($plainText, 'WordPress Comment Direct Resource Tail Image Payload Noise'),
];

echo '<!-- markerpdf:pdf-image-xobject-resource-entry-tail-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
