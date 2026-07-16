<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress alternates operand intro) Tj ET\n"
    . "q 18 0 0 9 72 690 cm /Direct#20Tail#20Image Do Q\n"
    . "q 16 0 0 8 100 690 cm /Indirect#20Tail#20Image Do Q\n"
    . "q 14 0 0 7 126 690 cm /Valid#20Alternate#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress alternates operand outro) Tj ET';

$directPrimaryPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Direct Tail Primary Image Noise) Tj ET';
$directAlternatePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Direct Tail Alternate Must Not Be Selected) Tj ET';
$indirectPrimaryPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Indirect Tail Primary Image Noise) Tj ET';
$indirectAlternatePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Indirect Tail Alternate Must Not Be Selected) Tj ET';
$validPrimaryPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Valid Alternate Primary Image Noise) Tj ET';
$validAlternatePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Valid Alternate Review Only) Tj ET';

$directPrimaryCompressed = gzcompress($directPrimaryPayload);
$directAlternateCompressed = gzcompress($directAlternatePayload);
$indirectPrimaryCompressed = gzcompress($indirectPrimaryPayload);
$indirectAlternateCompressed = gzcompress($indirectAlternatePayload);
$validPrimaryCompressed = gzcompress($validPrimaryPayload);
$validAlternateCompressed = gzcompress($validAlternatePayload);
if (
    !is_string($directPrimaryCompressed)
    || !is_string($directAlternateCompressed)
    || !is_string($indirectPrimaryCompressed)
    || !is_string($indirectAlternateCompressed)
    || !is_string($validPrimaryCompressed)
    || !is_string($validAlternateCompressed)
) {
    throw new RuntimeException('Unable to compress Image XObject Alternates operand smoke payloads.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Direct#20Tail#20Image 5 0 R /Indirect#20Tail#20Image 6 0 R /Valid#20Alternate#20Image 7 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($directPrimaryCompressed) . " /Alternates [<< /Image 8 0 R /DefaultForPrinting true >>] 99 0 R >>\nstream\n{$directPrimaryCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Alternates 20 0 R /Length " . strlen($indirectPrimaryCompressed) . " >>\nstream\n{$indirectPrimaryCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Alternates [<< /Image 10 0 R /DefaultForPrinting false >>] /Length " . strlen($validPrimaryCompressed) . " >>\nstream\n{$validPrimaryCompressed}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($directAlternateCompressed) . " >>\nstream\n{$directAlternateCompressed}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($indirectAlternateCompressed) . " >>\nstream\n{$indirectAlternateCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($validAlternateCompressed) . " >>\nstream\n{$validAlternateCompressed}\nendstream\nendobj\n"
    . "20 0 obj\n[<< /Image 9 0 R /DefaultForPrinting true >>] 99 0 R\nendobj\n"
    . "99 0 obj\n<< /S /JavaScript /JS (app.alert\\('tailed alternates operand'\\)) >>\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);
$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$direct = $entriesByName['Direct Tail Image'] ?? [];
$indirect = $entriesByName['Indirect Tail Image'] ?? [];
$valid = $entriesByName['Valid Alternate Image'] ?? [];
$validAlternate = $valid['alternate_images'][0] ?? [];
$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';

$directRejected = ($direct['alternate_image_count'] ?? null) === 0
    && ($direct['alternates_operand_boundary']['reason'] ?? null) === 'trailing_top_level_operand'
    && ($direct['alternates_operand_boundary']['native_raster_decode_blocked'] ?? true) === false;
$indirectRejected = ($indirect['alternate_image_count'] ?? null) === 0
    && ($indirect['alternates_operand_boundary']['reason'] ?? null) === 'trailing_indirect_array_operand'
    && ($indirect['alternates_operand_boundary']['tailed_object_number'] ?? null) === 20;
$validAlternatePreserved = ($valid['alternate_image_count'] ?? null) === 1
    && ($validAlternate['object_number'] ?? null) === 10
    && ($validAlternate['decoded_sha256'] ?? null) === hash('sha256', $validAlternatePayload);
$payloadsExcluded = $plainText === "WordPress alternates operand intro\nWordPress alternates operand outro"
    && !str_contains($plainText, 'WordPress Direct Tail Primary Image Noise')
    && !str_contains($plainText, 'WordPress Direct Tail Alternate Must Not Be Selected')
    && !str_contains($plainText, 'WordPress Indirect Tail Primary Image Noise')
    && !str_contains($plainText, 'WordPress Indirect Tail Alternate Must Not Be Selected')
    && !str_contains($plainText, 'WordPress Valid Alternate Primary Image Noise')
    && !str_contains($plainText, 'WordPress Valid Alternate Review Only')
    && !str_contains($encodedReview, hash('sha256', $directAlternatePayload))
    && !str_contains($encodedReview, hash('sha256', $indirectAlternatePayload))
    && !str_contains($encodedReview, $directAlternatePayload)
    && !str_contains($encodedReview, $indirectAlternatePayload)
    && !str_contains($encodedReview, 'tailed alternates operand');

if (!$directRejected || !$indirectRejected || !$validAlternatePreserved || !$payloadsExcluded) {
    throw new RuntimeException('Image XObject Alternates operand boundary smoke failed.');
}

$summary = [
    'source' => 'native-pdf-image-xobject-alternates-operand-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image alternate image handoff; malformed /Alternates operands remain review-only and do not select hidden streams',
    'image_xobject_count' => $review['image_xobject_count'],
    'direct_tailed_alternates_rejected' => $directRejected,
    'indirect_tailed_alternates_rejected' => $indirectRejected,
    'valid_alternate_preserved' => $validAlternatePreserved,
    'payload_excluded_from_text_and_review' => $payloadsExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-image-xobject-alternates-operand-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach (explode("\n", $plainText) as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
