<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePageContent = 'BT /F1 12 Tf 72 720 Td (Legacy jump Unique jump Tree jump Safe URI) Tj ET';
$targetPageContent = 'BT /F1 12 Tf 72 720 Td (Legacy duplicate target body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests << /Names [(Tree Target) [4 0 R /FitBH 600]] >> >> /Dests << /LegacyReview [3 0 R /FitH 700] /#4cegacyReview [4 0 R /XYZ 72 640 0] /UniqueLegacy [4 0 R /FitV 120] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 158 718] /Dest /LegacyReview >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [168 700 252 718] /Dest /UniqueLegacy >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [262 700 330 718] /Dest (Tree Target) >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [340 700 410 718] /A << /S /URI /URI (https://example.com/legacy-duplicate-safe) >> >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($sourcePageContent) . " >>\nstream\n{$sourcePageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetPageContent) . " >>\nstream\n{$targetPageContent}\nendstream\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$links = (new PdfLinkAnnotationExtractor())->extractPageLinks($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$destinationNames = array_column($destinations, 'name');
$promotedObjects = $links[0]['links'] ?? [];
$promotedAnnotationObjects = array_column($promotedObjects, 'annotation_object');

if ($destinationNames !== ['Tree Target', 'UniqueLegacy']) {
    throw new RuntimeException('Expected duplicate legacy /Dests key to stay out of WordPress destination review.');
}
if (($metadata['document_destinations']['names'] ?? null) !== ['Tree Target', 'UniqueLegacy']) {
    throw new RuntimeException('Expected duplicate legacy /Dests key to stay out of document metadata.');
}
if ($promotedAnnotationObjects !== [8, 9, 10]) {
    throw new RuntimeException('Expected duplicate legacy destination link to stay unpromoted.');
}
foreach (['LegacyReview', 'FitH', 'XYZ', 'legacy-duplicate-safe'] as $hidden) {
    if (str_contains($plainText, $hidden)) {
        throw new RuntimeException('Expected destination/action review metadata to stay out of visible text.');
    }
}

echo '<!-- markerpdf-named-destination-legacy-duplicate-key ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'duplicate decoded keys inside legacy catalog /Dests are skipped before WordPress destination metadata and link promotion',
    'destination_names' => $destinationNames,
    'metadata_destination_names' => $metadata['document_destinations']['names'] ?? [],
    'duplicate_legacy_key_rejected' => !in_array('LegacyReview', $destinationNames, true),
    'unique_legacy_key_preserved' => in_array('UniqueLegacy', $destinationNames, true),
    'name_tree_destination_preserved' => in_array('Tree Target', $destinationNames, true),
    'promoted_annotation_objects' => $promotedAnnotationObjects,
    'visible_text_excludes_destination_names' => !str_contains($plainText, 'LegacyReview')
        && !str_contains($plainText, 'UniqueLegacy')
        && !str_contains($plainText, 'Tree Target'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($destinations as $destination) {
    $metadata = [
        'markerDestination' => $destination['name'],
        'markerPageIndex' => $destination['page'],
        'markerFit' => $destination['fit'],
        'markerCoordinates' => $destination['coordinates'],
        'markerSource' => $destination['source'],
    ];
    echo '<li data-marker-named-destination="' . htmlspecialchars(json_encode($metadata, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars($destination['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
