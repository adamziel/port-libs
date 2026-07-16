<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Generation nested jump Safe URI) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Generation metadata target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyFallback [4 0 R /FitV 130] >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Annots [7 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 210 718] /Dest (Current Review) >>\nendobj\n"
    . "8 0 obj\n<< /Kids [9 0 R 10 0 R] /Limits [(Current Review) (Summary Review)] >>\nendobj\n"
    . "9 0 obj\n<< /Limits [(Current Review) (Current Review)] /Kids [9 1 R] >>\nendobj\n"
    . "9 1 obj\n<< /Limits [(Current Review) (Current Review)] /Names [(Current Review) [3 0 R /XYZ 72 700 1]] >>\nendobj\n"
    . "10 0 obj\n<< /Limits [(Summary Review) (Summary Review)] /Names [(Summary Review) 11 0 R] >>\nendobj\n"
    . "11 0 obj\n<< /D [4 0 R /FitBH 640] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Current Review Outline) /Parent 50 0 R /Dest (Current Review) /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (Summary Review Outline) /Parent 50 0 R /Dest (Summary Review) /Prev 51 0 R >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outline = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$links = (new PdfLinkAnnotationExtractor())->extractPageLinks($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$documentDestinations = $metadata['document_destinations'] ?? [];
$documentDestinationNames = $documentDestinations['names'] ?? [];
$outlineDestinations = array_column($outline, 'destination');
$linkDestinations = $links[0]['links'] ?? [];

if ($documentDestinationNames !== ['Current Review', 'Summary Review', 'LegacyFallback']) {
    throw new RuntimeException('Expected document metadata to preserve generation-distinct destination kids.');
}
if ($outlineDestinations !== ['Current Review', 'Summary Review']) {
    throw new RuntimeException('Expected outline navigation to resolve generation-distinct destination kids.');
}
if (($linkDestinations[0]['destination'] ?? null) !== 'Current Review') {
    throw new RuntimeException('Expected link promotion to preserve the generation-distinct destination.');
}
foreach (['Current Review', 'Summary Review', 'LegacyFallback', 'Current Review Outline', 'Summary Review Outline'] as $hidden) {
    if (str_contains($plainText, $hidden)) {
        throw new RuntimeException("Expected review-only destination label {$hidden} to stay out of visible text.");
    }
}

echo '<!-- markerpdf-pdf-named-destination-metadata-kid-generation-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'no-xref generation-distinct name-tree Kids feed document metadata and outline navigation before WordPress import',
    'destination_count' => count($destinations),
    'document_destination_count' => $documentDestinations['count'] ?? 0,
    'outline_destination_count' => count($outline),
    'metadata_generation_kid_preserved' => $documentDestinationNames[0] === 'Current Review',
    'outline_generation_kid_preserved' => $outlineDestinations[0] === 'Current Review',
    'link_generation_kid_preserved' => ($linkDestinations[0]['destination'] ?? null) === 'Current Review',
    'destination_labels_excluded_from_visible_text' => true,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($documentDestinations['destinations'] ?? [] as $destination) {
    $metadataPayload = [
        'markerDestination' => $destination['name'] ?? null,
        'markerPageIndex' => $destination['page'] ?? null,
        'markerPageObjectId' => $destination['page_object'] ?? null,
        'markerViewMode' => $destination['view_mode'] ?? null,
        'markerViewParameters' => $destination['view_parameters'] ?? [],
        'markerSource' => $destination['source'] ?? null,
    ];

    echo '<li data-marker-named-destination="'
        . htmlspecialchars(json_encode($metadataPayload, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars((string) ($destination['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
