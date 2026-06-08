<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (ASCII collision jump UTF16 collision jump Safe URI) Tj ET';
$asciiPageContent = 'BT /F1 12 Tf 72 720 Td (ASCII source-byte collision destination page) Tj ET';
$utf16PageContent = 'BT /F1 12 Tf 72 720 Td (UTF16 source-byte collision destination page) Tj ET';
$utf16Collision = '<FEFF0043006F006C006C006900730069006F006E>';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 32 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(Alias ASCII) {$utf16Collision}] /Names [{$utf16Collision} [5 0 R /XYZ 72 640 0] (Collision) [4 0 R /FitH 710] (Alias ASCII) (Collision) (Alias UTF16) {$utf16Collision}] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($asciiPageContent) . " >>\nstream\n{$asciiPageContent}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Length " . strlen($utf16PageContent) . " >>\nstream\n{$utf16PageContent}\nendstream\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$expectedNames = ['Collision', 'Collision', 'Alias ASCII', 'Alias UTF16'];
$destinationNames = array_column($destinations, 'name');
$metadataNames = $metadata['document_destinations']['names'] ?? [];
if ($destinationNames !== $expectedNames || $metadataNames !== $expectedNames) {
    throw new RuntimeException('Expected decoded-collision named destinations to be ordered by raw source bytes.');
}
if (($destinations[0]['name_bytes_hex'] ?? null) !== '436f6c6c6973696f6e') {
    throw new RuntimeException('Expected ASCII collision key before UTF-16 collision key.');
}

foreach (['Collision', 'Alias ASCII', 'Alias UTF16'] as $hiddenName) {
    if (str_contains($plainText, $hiddenName)) {
        throw new RuntimeException('Expected named-destination labels to remain review metadata only.');
    }
}

echo '<!-- markerpdf-pdf-named-destination-decoded-collision-source-order-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'leaf_order_from_pdf' => ['UTF16 Collision', 'ASCII Collision', 'Alias ASCII', 'Alias UTF16'],
    'review_order_from_source_bytes' => $destinationNames,
    'metadata_order_matches_review_order' => $metadataNames === $destinationNames,
    'ascii_collision_name_bytes_hex' => $destinations[0]['name_bytes_hex'] ?? null,
    'utf16_collision_name_bytes_hex' => $destinations[1]['name_bytes_hex'] ?? null,
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
