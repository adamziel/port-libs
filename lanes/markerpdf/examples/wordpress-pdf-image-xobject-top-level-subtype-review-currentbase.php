<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Current Image XObject Top-Level Subtype Intro) Tj ET\n"
    . "q 14 0 0 7 72 690 cm /Primary#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Current Image XObject Top-Level Subtype Outro) Tj ET';

$primaryPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Primary Top Level Subtype Image Payload Noise) Tj ET';
$softMaskPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Soft Mask Top Level Subtype Payload Noise) Tj ET';
$maskPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Mask Top Level Subtype Payload Noise) Tj ET';
$alternatePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Alternate Top Level Subtype Payload Noise) Tj ET';
$metadataPayload = '<x:xmpmeta><dc:title>WordPress Top Level Subtype Metadata Noise</dc:title></x:xmpmeta>';

$primaryCompressed = gzcompress($primaryPayload);
$softMaskCompressed = gzcompress($softMaskPayload);
$maskCompressed = gzcompress($maskPayload);
$alternateCompressed = gzcompress($alternatePayload);
$metadataCompressed = gzcompress($metadataPayload);
if (
    !is_string($primaryCompressed)
    || !is_string($softMaskCompressed)
    || !is_string($maskCompressed)
    || !is_string($alternateCompressed)
    || !is_string($metadataCompressed)
) {
    throw new RuntimeException('Unable to compress Image XObject top-level subtype smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Primary#20Image 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Private << /Subtype /PS /Type /Metadata >> /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMask 6 0 R /Mask 7 0 R /Alternates [<< /Image 8 0 R /DefaultForPrinting true >>] /Metadata 9 0 R /Filter /FlateDecode /Length " . strlen($primaryCompressed) . " >>\nstream\n{$primaryCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Private << /Subtype /Form >> /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode [0 1] /Filter /FlateDecode /Length " . strlen($softMaskCompressed) . " >>\nstream\n{$softMaskCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Private << /Subtype /PS >> /Subtype /Image /Width 2 /Height 1 /ImageMask true /BitsPerComponent 1 /Decode [1 0] /Filter /FlateDecode /Length " . strlen($maskCompressed) . " >>\nstream\n{$maskCompressed}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Private << /Subtype /Metadata >> /Subtype /Image /Width 4 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($alternateCompressed) . " >>\nstream\n{$alternateCompressed}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /Metadata /Private << /Subtype /Image >> /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataCompressed) . " >>\nstream\n{$metadataCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entry = $review['entries'][0] ?? [];
$softMask = is_array($entry) ? ($entry['soft_mask_review'] ?? []) : [];
$mask = is_array($entry) ? ($entry['mask_review'] ?? []) : [];
$alternate = is_array($entry) ? (($entry['alternate_images'][0] ?? []) ?: []) : [];
$metadataStream = is_array($entry) ? ($entry['metadata_stream'] ?? []) : [];

if (
    ($review['image_xobject_count'] ?? 0) !== 1
    || ($review['invoked_image_xobject_count'] ?? 0) !== 1
    || ($entry['subtype'] ?? null) !== 'Image'
    || ($softMask['subtype'] ?? null) !== 'Image'
    || ($mask['subtype'] ?? null) !== 'Image'
    || ($alternate['subtype'] ?? null) !== 'Image'
    || ($metadataStream['subtype'] ?? null) !== 'XML'
    || ($entry['decoded_sha256'] ?? null) !== hash('sha256', $primaryPayload)
    || ($softMask['decoded_sha256'] ?? null) !== hash('sha256', $softMaskPayload)
    || ($mask['decoded_sha256'] ?? null) !== hash('sha256', $maskPayload)
    || ($alternate['decoded_sha256'] ?? null) !== hash('sha256', $alternatePayload)
    || ($metadataStream['decoded_sha256'] ?? null) !== hash('sha256', $metadataPayload)
    || str_contains($plainText, 'WordPress Primary Top Level Subtype Image Payload Noise')
    || str_contains($plainText, 'WordPress Soft Mask Top Level Subtype Payload Noise')
    || str_contains($plainText, 'WordPress Mask Top Level Subtype Payload Noise')
    || str_contains($plainText, 'WordPress Alternate Top Level Subtype Payload Noise')
    || str_contains($plainText, 'WordPress Top Level Subtype Metadata Noise')
) {
    throw new RuntimeException('Image XObject top-level subtype review smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-top-level-subtype-review-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; Image XObject review subtype metadata must use top-level stream dictionaries',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'primary_subtype' => $entry['subtype'] ?? null,
    'soft_mask_subtype' => $softMask['subtype'] ?? null,
    'mask_subtype' => $mask['subtype'] ?? null,
    'alternate_subtype' => $alternate['subtype'] ?? null,
    'metadata_stream_subtype' => $metadataStream['subtype'] ?? null,
    'nested_private_subtype_ignored' => ($entry['subtype'] ?? null) === 'Image'
        && ($metadataStream['subtype'] ?? null) === 'XML',
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Primary Top Level Subtype Image Payload Noise')
        || str_contains($plainText, 'WordPress Soft Mask Top Level Subtype Payload Noise')
        || str_contains($plainText, 'WordPress Mask Top Level Subtype Payload Noise')
        || str_contains($plainText, 'WordPress Alternate Top Level Subtype Payload Noise')
        || str_contains($plainText, 'WordPress Top Level Subtype Metadata Noise'),
];

echo '<!-- markerpdf:pdf-image-xobject-top-level-subtype-review-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
