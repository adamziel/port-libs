<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Marked Operand Image Intro) Tj ET\n"
    . "/Artifact 99 << /Subtype /Background /MCID 3 >> BDC q 12 0 0 6 72 690 cm /Malformed#20Artifact Do Q EMC\n"
    . "/Artifact << /Subtype /Background /MCID 4 >> BDC q 10 0 0 5 96 690 cm /Valid#20Artifact Do Q EMC\n"
    . "/Figure 99 << /MCID 5 /Alt (Malformed Figure Alt Review) >> BDC q 14 0 0 7 120 690 cm /Malformed#20Figure Do Q EMC\n"
    . "777 /OC /LayerOff BDC q 8 0 0 4 150 690 cm /Malformed#20OC Do Q EMC\n"
    . 'BT /F1 12 Tf 72 660 Td (Marked Operand Image Outro) Tj ET';
$malformedArtifactPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Malformed Artifact Image Payload Noise) Tj ET';
$validArtifactPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Valid Artifact Image Payload Noise) Tj ET';
$malformedFigurePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Malformed Figure Image Payload Noise) Tj ET';
$malformedOptionalContentPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Malformed Optional Content Image Payload Noise) Tj ET';
$malformedArtifactCompressed = gzcompress($malformedArtifactPayload);
$validArtifactCompressed = gzcompress($validArtifactPayload);
$malformedFigureCompressed = gzcompress($malformedFigurePayload);
$malformedOptionalContentCompressed = gzcompress($malformedOptionalContentPayload);
if (
    !is_string($malformedArtifactCompressed)
    || !is_string($validArtifactCompressed)
    || !is_string($malformedFigureCompressed)
    || !is_string($malformedOptionalContentCompressed)
) {
    throw new RuntimeException('Unable to compress marked-content operand image smoke payloads.');
}

$pdf = "%PDF-1.5\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OCProperties << /OCGs [20 0 R] /D << /BaseState /OFF /Order [20 0 R] >> >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 10 0 R >> /Properties << /LayerOff 20 0 R >> /XObject << /Malformed#20Artifact 5 0 R /Valid#20Artifact 6 0 R /Malformed#20Figure 7 0 R /Malformed#20OC 8 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($malformedArtifactCompressed) . " >>\nstream\n{$malformedArtifactCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($validArtifactCompressed) . " >>\nstream\n{$validArtifactCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 3 /Height 2 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($malformedFigureCompressed) . " >>\nstream\n{$malformedFigureCompressed}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($malformedOptionalContentCompressed) . " >>\nstream\n{$malformedOptionalContentCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n<< /Type /OCG /Name (Hidden Image Layer) >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$malformedArtifact = $entriesByName['Malformed Artifact'] ?? [];
$validArtifact = $entriesByName['Valid Artifact'] ?? [];
$malformedFigure = $entriesByName['Malformed Figure'] ?? [];
$malformedOptionalContent = $entriesByName['Malformed OC'] ?? [];
$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';

$metadata = [
    'source' => 'native-pdf-image-xobject-marked-content-operand-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text searchable text plus marker.pdf.images.render_image image handoff; malformed BMC/BDC operands do not suppress, hide, or tag image XObject review rows',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'] ?? null,
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'] ?? null,
    'uninvoked_image_xobject_count' => $review['uninvoked_image_xobject_count'] ?? null,
    'malformed_artifact_invoked' => ($malformedArtifact['invoked'] ?? null) === true,
    'valid_artifact_suppressed' => ($validArtifact['invoked'] ?? null) === false,
    'malformed_figure_marked_content_ignored' => ($malformedFigure['marked_content_review_only'] ?? null) === false,
    'malformed_optional_content_invoked' => ($malformedOptionalContent['invoked'] ?? null) === true,
    'malformed_optional_content_visible' => ($malformedOptionalContent['optional_content_visible'] ?? null) === true,
    'malformed_figure_alt_excluded' => !str_contains($encodedReview, 'Malformed Figure Alt Review'),
    'payloads_excluded_from_text' => !str_contains($plainText, 'WordPress Malformed Artifact Image Payload Noise')
        && !str_contains($plainText, 'WordPress Valid Artifact Image Payload Noise')
        && !str_contains($plainText, 'WordPress Malformed Figure Image Payload Noise')
        && !str_contains($plainText, 'WordPress Malformed Optional Content Image Payload Noise'),
    'payloads_excluded_from_review_json' => !str_contains($encodedReview, $malformedArtifactPayload)
        && !str_contains($encodedReview, $validArtifactPayload)
        && !str_contains($encodedReview, $malformedFigurePayload)
        && !str_contains($encodedReview, $malformedOptionalContentPayload),
];

if (
    $metadata['image_xobject_count'] !== 4
    || $metadata['invoked_image_xobject_count'] !== 3
    || $metadata['uninvoked_image_xobject_count'] !== 1
    || $metadata['malformed_artifact_invoked'] !== true
    || $metadata['valid_artifact_suppressed'] !== true
    || $metadata['malformed_figure_marked_content_ignored'] !== true
    || $metadata['malformed_optional_content_invoked'] !== true
    || $metadata['malformed_optional_content_visible'] !== true
    || $metadata['malformed_figure_alt_excluded'] !== true
    || $metadata['payloads_excluded_from_text'] !== true
    || $metadata['payloads_excluded_from_review_json'] !== true
) {
    throw new RuntimeException('Image XObject marked-content operand boundary smoke failed.');
}

echo '<!-- markerpdf:pdf-image-xobject-marked-content-operand-boundary-currentbase '
    . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
