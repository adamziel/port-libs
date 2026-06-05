<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline limit fallback start page) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline limit fallback review page) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyOnly [4 0 R /FitV 120] >> /Outlines 50 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(Current Start) (Review Summary)] /Kids [21 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Limits [(zz-stale) (zz-stale)] /Names [(Current Start) [3 0 R /FitH 700] (Review Summary) 22 0 R (zz-stale) [4 0 R /FitH 111]] >>\nendobj\n"
    . "22 0 obj\n<< /D [4 0 R /XYZ 72 640 0] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 53 0 R /Count 3 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Current Start Outline) /Parent 50 0 R /Dest (Current Start) /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (Review Summary Outline) /Parent 50 0 R /Dest (Review Summary) /Prev 51 0 R /Next 53 0 R >>\nendobj\n"
    . "53 0 obj\n<< /Title (Stale Decoy Outline) /Parent 50 0 R /Dest (zz-stale) /Prev 52 0 R >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$toc = (new PdfOutlineExtractor())->getPdfTocWithDestinationViews($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$encoded = json_encode([$destinations, $metadata['document_destinations'] ?? [], $toc], JSON_UNESCAPED_SLASHES) ?: '';
$tocTitles = array_column($toc, 'title');

foreach (['Current Start', 'Review Summary', 'LegacyOnly'] as $requiredDestination) {
    if (!in_array($requiredDestination, array_column($destinations, 'name'), true)) {
        throw new RuntimeException("Expected recovered destination {$requiredDestination} in document metadata.");
    }
}
foreach (['Current Start Outline', 'Review Summary Outline'] as $requiredTitle) {
    if (!in_array($requiredTitle, $tocTitles, true)) {
        throw new RuntimeException("Expected recovered outline title {$requiredTitle} in navigation review metadata.");
    }
}
foreach (['Stale Decoy Outline', 'zz-stale', 'FitH 111'] as $hidden) {
    if (str_contains($encoded, $hidden) || str_contains($plainText, $hidden)) {
        throw new RuntimeException("Expected malformed limit decoy {$hidden} to stay hidden from WordPress import output.");
    }
}

echo '<!-- markerpdf-pdf-named-destination-outline-limits-fallback-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-named-destination-outline-limits-fallback-currentbase',
    'support_component' => 'native-pdf-outline-name-tree-destination-parser',
    'native_boundary' => 'malformed child destination /Limits fall back to inherited catalog name-tree bounds before outline navigation review',
    'destination_names' => array_column($destinations, 'name'),
    'document_destination_names' => $metadata['document_destinations']['names'] ?? [],
    'outline_titles' => $tocTitles,
    'outline_destinations' => array_column($toc, 'destination'),
    'outline_view_modes' => array_column($toc, 'view_mode'),
    'stale_decoy_omitted' => !str_contains($encoded, 'Stale Decoy Outline') && !str_contains($encoded, 'zz-stale'),
    'visible_text_excludes_destinations' => !str_contains($plainText, 'Current Start')
        && !str_contains($plainText, 'Review Summary')
        && !str_contains($plainText, 'Stale Decoy Outline'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";

echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline destination review\"><ul>\n";
foreach ($toc as $row) {
    echo '<li data-marker-outline-destination="' . htmlspecialchars((string) ($row['destination'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($row['page'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-view="' . htmlspecialchars((string) ($row['view_mode'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
