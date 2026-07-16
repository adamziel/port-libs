<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Internal node destination page) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Internal child summary page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyOnly [4 0 R /FitV 120] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Limits [(A) (Zzz)] /Kids [9 0 R] >>\nendobj\n"
    . "9 0 obj\n<< /Limits [(Current Child Start) (Review Child Summary)] /Names [(Z Parent Decoy) [4 0 R /FitH 111]] /Kids [10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Names [(Current Child Start) [3 0 R /FitH 700] (Review Child Summary) 11 0 R (Z Child Decoy) [4 0 R /FitBH 222]] >>\nendobj\n"
    . "11 0 obj\n<< /D [4 0 R /XYZ 72 640 0] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$documentDestinations = $metadata['document_destinations'] ?? [];
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$metadataEncoded = json_encode($documentDestinations, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

if (array_column($destinations, 'name') !== ['Current Child Start', 'Review Child Summary', 'LegacyOnly']) {
    throw new RuntimeException('Expected internal name-tree node limits to constrain child named destinations.');
}
if (($documentDestinations['names'] ?? []) !== ['Current Child Start', 'Review Child Summary', 'LegacyOnly']) {
    throw new RuntimeException('Expected metadata review destinations to preserve the same internal-node boundary.');
}
if (str_contains($encoded, 'Z Parent Decoy') || str_contains($encoded, 'Z Child Decoy') || str_contains($metadataEncoded, 'Z Parent Decoy') || str_contains($metadataEncoded, 'Z Child Decoy') || str_contains($plainText, 'Z Parent Decoy') || str_contains($plainText, 'Z Child Decoy')) {
    throw new RuntimeException('Expected stale mixed-node named destinations to stay out of WordPress text and review metadata.');
}

echo '<!-- markerpdf-pdf-named-destination-internal-node-boundary-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF /Names /Dests internal node with both /Names and /Kids preserves child /Limits while excluding stale local destination rows',
    'destination_names' => array_column($destinations, 'name'),
    'metadata_destination_names' => $documentDestinations['names'] ?? [],
    'destination_sources' => array_column($destinations, 'source'),
    'child_limits_preserved' => array_column($destinations, 'name') === ['Current Child Start', 'Review Child Summary', 'LegacyOnly'],
    'metadata_child_limits_preserved' => ($documentDestinations['names'] ?? []) === ['Current Child Start', 'Review Child Summary', 'LegacyOnly'],
    'stale_parent_decoy_excluded' => !str_contains($encoded, 'Z Parent Decoy'),
    'stale_child_decoy_excluded' => !str_contains($encoded, 'Z Child Decoy'),
    'stale_metadata_decoys_excluded' => !str_contains($metadataEncoded, 'Z Parent Decoy') && !str_contains($metadataEncoded, 'Z Child Decoy'),
    'visible_text_excludes_destination_operands' => !str_contains($plainText, 'Z Parent Decoy')
        && !str_contains($plainText, 'Z Child Decoy')
        && !str_contains($plainText, 'Current Child Start')
        && !str_contains($plainText, 'Review Child Summary')
        && !str_contains($plainText, 'LegacyOnly'),
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach (preg_split('/\R+/', trim($plainText)) ?: [] as $line) {
    if ($line === '') {
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
foreach ($destinations as $destination) {
    echo '<li data-marker-destination-name="' . htmlspecialchars($destination['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination-page="' . htmlspecialchars((string) ($destination['page'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination-fit="' . htmlspecialchars($destination['fit'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination-source="' . htmlspecialchars($destination['source'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">Named destination: ' . htmlspecialchars($destination['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
