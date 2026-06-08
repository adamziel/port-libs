<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Current Ambiguous Image Do Intro) Tj ET\n"
    . "q 20 0 0 10 72 690 cm /Decoy#20Image /Hero#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Current Ambiguous Image Do Outro) Tj ET';
$decoyPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Decoy Image Payload Noise) Tj ET';
$heroPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Hero Image Payload Noise) Tj ET';
$decoyCompressed = gzcompress($decoyPayload);
$heroCompressed = gzcompress($heroPayload);
if (!is_string($decoyCompressed) || !is_string($heroCompressed)) {
    throw new RuntimeException('Unable to compress ambiguous image XObject Do operand smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 8 0 R >> /XObject << /Decoy#20Image 5 0 R /Hero#20Image 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($decoyCompressed) . " >>\nstream\n{$decoyCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($heroCompressed) . " >>\nstream\n{$heroCompressed}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

if (
    ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 0
    || ($review['uninvoked_image_xobject_count'] ?? 0) !== 2
    || ($entriesByName['Decoy Image']['malformed_do_operands'][0]['reason'] ?? null) !== 'ambiguous_do_resource_operands'
    || ($entriesByName['Hero Image']['malformed_do_operands'][0]['reason'] ?? null) !== 'ambiguous_do_resource_operands'
    || ($entriesByName['Decoy Image']['malformed_do_operands'][0]['resource_names'] ?? null) !== ['Decoy Image', 'Hero Image']
    || ($entriesByName['Hero Image']['malformed_do_operands'][0]['resource_operand_indexes'] ?? null) !== [0, 1]
    || str_contains($plainText, 'WordPress Decoy Image Payload Noise')
    || str_contains($plainText, 'WordPress Hero Image Payload Noise')
) {
    throw new RuntimeException('Ambiguous Image XObject Do operand boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-ambiguous-do-operand-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; Image XObject Do has one name operand, so multi-name operands remain review-only and unpainted',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'],
    'ambiguous_do_operand_policy' => $entriesByName['Decoy Image']['malformed_do_operand_policy'] ?? null,
    'ambiguous_do_operand_reason' => $entriesByName['Decoy Image']['malformed_do_operands'][0]['reason'] ?? null,
    'ambiguous_do_operand_indexes' => $entriesByName['Decoy Image']['malformed_do_operands'][0]['resource_operand_indexes'] ?? null,
    'ambiguous_do_resource_names' => $entriesByName['Decoy Image']['malformed_do_operands'][0]['resource_names'] ?? null,
    'decoy_image_unpainted' => ($entriesByName['Decoy Image']['invoked'] ?? true) === false,
    'hero_image_unpainted' => ($entriesByName['Hero Image']['invoked'] ?? true) === false,
    'payload_in_visible_text' => str_contains($plainText, 'WordPress Decoy Image Payload Noise')
        || str_contains($plainText, 'WordPress Hero Image Payload Noise'),
];

echo '<!-- markerpdf:pdf-image-xobject-ambiguous-do-operand-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
