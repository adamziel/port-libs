<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Alpha duplicate jump Safe URI) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Alpha replacement and middle target page) Tj ET';
$thirdPageContent = 'BT /F1 12 Tf 72 720 Td (Zulu destination target page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyTail [5 0 R /FitV 144] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 32 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(Alpha Start) (Zulu Appendix)] /Names [(Zulu Appendix) [5 0 R /Fit] (Alpha Start) [3 0 R /FitH 710] (Middle Review) [4 0 R /XYZ 72 650 0] (Alpha Start) [4 0 R /FitBH 620]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Length " . strlen($thirdPageContent) . " >>\nstream\n{$thirdPageContent}\nendstream\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$expectedNames = ['Alpha Start', 'Middle Review', 'Zulu Appendix', 'LegacyTail'];
$destinationNames = array_column($destinations, 'name');
$metadataNames = $metadata['document_destinations']['names'] ?? [];
if ($destinationNames !== $expectedNames || $metadataNames !== $expectedNames) {
    throw new RuntimeException('Expected leaf destination names to be ordered by source bytes before WordPress review.');
}

if (($destinations[0]['fit'] ?? null) !== 'FitBH' || ($destinations[0]['page'] ?? null) !== 1) {
    throw new RuntimeException('Expected same-name duplicate destination to keep the later physical target.');
}

foreach ($expectedNames as $hiddenName) {
    if (str_contains($plainText, $hiddenName)) {
        throw new RuntimeException('Expected named-destination labels to stay out of visible WordPress text.');
    }
}

echo '<!-- markerpdf-pdf-named-destination-leaf-order-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'leaf_order_from_pdf' => ['Zulu Appendix', 'Alpha Start', 'Middle Review', 'Alpha Start'],
    'review_order_from_source_bytes' => $destinationNames,
    'metadata_order_matches_review_order' => $metadataNames === $destinationNames,
    'duplicate_name_later_target_preserved' => ($destinations[0]['fit'] ?? null) === 'FitBH',
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
