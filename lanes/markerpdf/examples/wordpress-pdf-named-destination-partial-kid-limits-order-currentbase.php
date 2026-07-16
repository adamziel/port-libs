<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$alphaContent = 'BT /F1 12 Tf 72 720 Td (Alpha partial-limits destination page) Tj ET';
$reviewContent = 'BT /F1 12 Tf 72 720 Td (Review partial-limits destination page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyTail [4 0 R /FitV 144] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Limits [(Alpha Partial) (Review Partial)] /Kids [14 0 R 10 0 R 9 0 R] >>\nendobj\n"
    . "9 0 obj\n<< /Limits [(Alpha Partial) (Alpha Partial)] /Names [(Alpha Partial) [3 0 R /FitH 710]] >>\nendobj\n"
    . "10 0 obj\n<< /Limits [(Review Partial) (Review Partial)] /Names [(Review Partial) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "14 0 obj\n<< /Names [(Zulu Stale Partial) [4 0 R /Fit] (zz-partial-decoy) [3 0 R /Fit]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($alphaContent) . " >>\nstream\n{$alphaContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($reviewContent) . " >>\nstream\n{$reviewContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$expectedNames = ['Alpha Partial', 'Review Partial', 'LegacyTail'];
$destinationNames = array_column($destinations, 'name');
$metadataNames = $metadata['document_destinations']['names'] ?? [];
if ($destinationNames !== $expectedNames || $metadataNames !== $expectedNames) {
    throw new RuntimeException('Expected bounded destination kids to stay ordered when a malformed sibling has no local Limits.');
}

foreach (['Alpha Partial', 'Review Partial', 'LegacyTail', 'Zulu Stale Partial', 'zz-partial-decoy'] as $hiddenName) {
    if (str_contains($plainText, $hiddenName)) {
        throw new RuntimeException('Expected named-destination labels to remain review metadata only.');
    }
}

echo '<!-- markerpdf-pdf-named-destination-partial-kid-limits-order-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'kid_order_from_pdf' => ['malformed-no-local-limits', 'Review Partial', 'Alpha Partial'],
    'review_order_from_bounded_limits' => $destinationNames,
    'metadata_order_matches_review_order' => $metadataNames === $destinationNames,
    'malformed_no_limits_child_excluded_by_inherited_limits' => true,
    'visible_text_excludes_destination_labels' => true,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($destinations as $destination) {
    echo '<li data-marker-destination-source="' . htmlspecialchars($destination['source'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination-page="' . htmlspecialchars((string) (($destination['page'] ?? -1) + 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination-fit="' . htmlspecialchars($destination['fit'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars($destination['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
