<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$imagePayload = 'BT /F1 12 Tf 72 720 Td (Image metadata filter operand image payload noise) Tj ET';
$metadataPayload = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Image XObject Hidden Metadata Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Image-local metadata filter helpers stay review-only.</rdf:li></rdf:Alt></dc:description>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$compressedImagePayload = gzcompress($imagePayload);
$compressedMetadataPayload = gzcompress($metadataPayload);
if (!is_string($compressedImagePayload) || !is_string($compressedMetadataPayload)) {
    throw new RuntimeException('Unable to compress image metadata filter operand smoke fixture.');
}

$pageContent = "BT /F1 12 Tf 72 720 Td (Before image metadata filter operand) Tj ET\n"
    . "q 24 0 0 12 72 690 cm /Meta#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (After image metadata filter operand) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Meta#20Image 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Metadata 6 0 R /Filter /FlateDecode /Length " . strlen($compressedImagePayload) . " >>\nstream\n{$compressedImagePayload}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Metadata /Subtype /XML /Filter 7 0 R /Length " . strlen($compressedMetadataPayload) . " >>\nstream\n{$compressedMetadataPayload}\nendstream\nendobj\n"
    . "7 0 obj\n/FlateDecode /Crypt 8 0 R\nendobj\n"
    . "8 0 obj\n<< /S /JavaScript /JS (app.alert\\('image metadata filter operand tail'\\)) >>\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$metadata = $review['entries'][0]['metadata_stream'] ?? [];
$filterOperand = $metadata['filter_operands'][0] ?? [];
$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';

$metadataRejected = ($metadata['status'] ?? null) === 'rejected_malformed_image_xobject_metadata_stream_filter_operand'
    && ($metadata['filter_operand_policy'] ?? null) === 'reject_malformed_filter_operands'
    && ($metadata['filters'] ?? []) === ['FlateDecode']
    && ($metadata['decoded_with_current_filters'] ?? true) === false
    && array_key_exists('decoded_sha256', $metadata)
    && $metadata['decoded_sha256'] === null;
$tailDetected = ($filterOperand['kind'] ?? null) === 'indirect'
    && ($filterOperand['object_number'] ?? null) === 7
    && ($filterOperand['extra_filter_name'] ?? null) === 'Crypt'
    && ($filterOperand['valid_filter_operand'] ?? true) === false;
$payloadExcluded = $plainText === "Before image metadata filter operand\nAfter image metadata filter operand"
    && !str_contains($plainText, 'Image XObject Hidden Metadata Title')
    && !str_contains($plainText, 'image metadata filter operand tail')
    && !str_contains($encodedReview, $metadataPayload)
    && !str_contains($encodedReview, $imagePayload)
    && !str_contains($encodedReview, 'image metadata filter operand tail');

if (!$metadataRejected || !$tailDetected || !$payloadExcluded) {
    throw new RuntimeException('Image XObject metadata filter operand boundary smoke failed.');
}

$summary = [
    'source' => 'native-pdf-image-xobject-metadata-filter-operand-currentbase',
    'upstream_boundary' => 'Image XObject metadata remains review-only; indirect Filter helper tails are rejected before metadata stream decode',
    'image_metadata_stream_rejected' => $metadataRejected,
    'filter_operand_policy' => $metadata['filter_operand_policy'] ?? null,
    'filters' => $metadata['filters'] ?? [],
    'invalid_filter_operand_count' => $metadata['invalid_filter_operand_count'] ?? null,
    'malformed_filter_operand_count' => $metadata['malformed_filter_operand_count'] ?? null,
    'extra_filter_name' => $filterOperand['extra_filter_name'] ?? null,
    'decoded_with_current_filters' => $metadata['decoded_with_current_filters'] ?? null,
    'payload_excluded_from_text_and_review' => $payloadExcluded,
    'paragraphs' => explode("\n", $plainText),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-image-xobject-metadata-filter-operand-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach (explode("\n", $plainText) as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
