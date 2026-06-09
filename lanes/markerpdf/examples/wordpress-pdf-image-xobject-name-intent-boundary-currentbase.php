<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress image metadata boundary intro) Tj ET\n"
    . "q 10 0 0 5 72 690 cm /Private#20Metadata#20Image Do Q\n"
    . "q 10 0 0 5 88 690 cm /Tailed#20Metadata#20Image Do Q\n"
    . "q 10 0 0 5 104 690 cm /Duplicate#20Metadata#20Image Do Q\n"
    . "q 10 0 0 5 120 690 cm /Valid#20Metadata#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress image metadata boundary outro) Tj ET';

$privatePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Private Image Metadata Payload Noise) Tj ET';
$tailedPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Tailed Image Metadata Payload Noise) Tj ET';
$duplicatePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Duplicate Image Metadata Payload Noise) Tj ET';
$validPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Valid Image Metadata Payload Noise) Tj ET';

$compressedPrivatePayload = gzcompress($privatePayload);
$compressedTailedPayload = gzcompress($tailedPayload);
$compressedDuplicatePayload = gzcompress($duplicatePayload);
$compressedValidPayload = gzcompress($validPayload);
if (
    !is_string($compressedPrivatePayload)
    || !is_string($compressedTailedPayload)
    || !is_string($compressedDuplicatePayload)
    || !is_string($compressedValidPayload)
) {
    throw new RuntimeException('Unable to compress WordPress image metadata boundary smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Private#20Metadata#20Image 5 0 R /Tailed#20Metadata#20Image 6 0 R /Duplicate#20Metadata#20Image 7 0 R /Valid#20Metadata#20Image 8 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Private << /Intent /AbsoluteColorimetric /Name /Private#20Decoy#20Review#20Name >> /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedPrivatePayload) . " >>\nstream\n{$compressedPrivatePayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Intent /RelativeColorimetric 99 0 R /Name /Tailed#20Review#20Name 99 0 R /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedTailedPayload) . " >>\nstream\n{$compressedTailedPayload}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Intent /Perceptual /Intent /Saturation /Name /First#20Duplicate#20Review#20Name /Name /Second#20Duplicate#20Review#20Name /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedDuplicatePayload) . " >>\nstream\n{$compressedDuplicatePayload}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Image /Intent /Saturation /Name /Valid#20Review#20Name /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($compressedValidPayload) . " >>\nstream\n{$compressedValidPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
if (
    ($review['image_xobject_count'] ?? 0) !== 4
    || ($review['invoked_image_xobject_count'] ?? 0) !== 4
    || ($entriesByName['Private Metadata Image']['rendering_intent'] ?? null) !== null
    || ($entriesByName['Private Metadata Image']['image_name'] ?? null) !== null
    || ($entriesByName['Tailed Metadata Image']['rendering_intent'] ?? null) !== null
    || ($entriesByName['Tailed Metadata Image']['image_name'] ?? null) !== null
    || ($entriesByName['Duplicate Metadata Image']['rendering_intent'] ?? null) !== null
    || ($entriesByName['Duplicate Metadata Image']['image_name'] ?? null) !== null
    || ($entriesByName['Valid Metadata Image']['rendering_intent'] ?? null) !== 'Saturation'
    || ($entriesByName['Valid Metadata Image']['image_name'] ?? null) !== 'Valid Review Name'
    || str_contains($plainText, 'WordPress Private Image Metadata Payload Noise')
    || str_contains($plainText, 'WordPress Tailed Image Metadata Payload Noise')
    || str_contains($plainText, 'WordPress Duplicate Image Metadata Payload Noise')
    || str_contains($plainText, 'WordPress Valid Image Metadata Payload Noise')
    || str_contains($encoded, 'Private Decoy Review Name')
    || str_contains($encoded, 'Tailed Review Name')
    || str_contains($encoded, 'First Duplicate Review Name')
    || str_contains($encoded, 'Second Duplicate Review Name')
) {
    throw new RuntimeException('WordPress Image XObject name/intent boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-name-intent-boundary-currentbase',
    'upstream_boundary' => 'PDF Image XObject /Intent and /Name are top-level stream dictionary metadata hints; nested private dictionaries, tailed operands, and duplicate declarations stay out of WordPress media review metadata.',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'private_metadata_null' => ($entriesByName['Private Metadata Image']['image_name'] ?? null) === null,
    'tailed_metadata_null' => ($entriesByName['Tailed Metadata Image']['image_name'] ?? null) === null,
    'duplicate_metadata_null' => ($entriesByName['Duplicate Metadata Image']['image_name'] ?? null) === null,
    'valid_rendering_intent' => $entriesByName['Valid Metadata Image']['rendering_intent'] ?? null,
    'valid_image_name' => $entriesByName['Valid Metadata Image']['image_name'] ?? null,
    'payload_in_visible_text' => false,
    'dependency_closure' => 'reuses native PHP PDF dictionary/name parsing and FlateDecode support; no OCR, model, GPU, or external PDF tooling required',
];

echo "<!-- " . json_encode($metadata, JSON_UNESCAPED_SLASHES) . " -->\n";
echo $plainText . "\n";
