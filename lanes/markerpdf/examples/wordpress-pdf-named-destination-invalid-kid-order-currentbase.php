<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Invalid kid stale jump Direct kid jump Safe URI) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Invalid kid current destination body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> /Dests << /LegacyTail [4 0 R /FitV 144] >> /Outlines 50 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 210 718] /Dest (DuplicateReview) >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [220 700 320 718] /Dest (Direct Kid Decoy) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [330 700 402 718] /A << /S /URI /URI (https://example.com/invalid-kid-order) >> >>\nendobj\n"
    . "20 0 obj\n<< /Limits [(A Broad) (DuplicateReview)] /Kids [21 0 R null 22 0 R << /Limits [(Direct Kid Decoy) (Direct Kid Decoy)] /Names [(Direct Kid Decoy) [3 0 R /FitH 333]] >> /ScalarKid] >>\nendobj\n"
    . "21 0 obj\n<< /Limits [(DuplicateReview) (DuplicateReview)] /Names [(DuplicateReview) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
    . "22 0 obj\n<< /Limits [(A Broad) (DuplicateReview)] /Names [(A Broad) [3 0 R /Fit] (DuplicateReview) [3 0 R /FitH 111]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /Outlines /First 51 0 R /Last 52 0 R /Count 2 >>\nendobj\n"
    . "51 0 obj\n<< /Title (Duplicate current outline) /Parent 50 0 R /Dest (DuplicateReview) /Next 52 0 R >>\nendobj\n"
    . "52 0 obj\n<< /Title (Direct kid decoy outline) /Parent 50 0 R /Dest (Direct Kid Decoy) /Prev 51 0 R >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$links = (new PdfLinkAnnotationExtractor())->extractPageLinks($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$destinationNames = array_column($destinations, 'name');
$documentDestinationNames = $metadata['document_destinations']['names'] ?? [];
$promotedLinkObjects = $links[0]['links'] ?? [];
$promotedLinkObjects = array_column($promotedLinkObjects, 'annotation_object');

if ($destinationNames !== ['A Broad', 'DuplicateReview', 'LegacyTail']) {
    throw new RuntimeException('Expected valid bounded Kids to be ordered by Limits around malformed Kids entries.');
}
if ($documentDestinationNames !== $destinationNames) {
    throw new RuntimeException('Expected document destination metadata to match named-destination review order.');
}
if ($promotedLinkObjects !== [7, 9]) {
    throw new RuntimeException('Expected only current named destination and safe URI links to be promoted.');
}
foreach (['A Broad', 'DuplicateReview', 'Direct Kid Decoy', 'invalid-kid-order'] as $hidden) {
    if (str_contains($plainText, $hidden)) {
        throw new RuntimeException('Expected destination labels and URI targets to remain review metadata only.');
    }
}

echo '<!-- markerpdf-pdf-named-destination-invalid-kid-order-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'raw_kid_order' => ['DuplicateReview', 'null', 'A Broad', 'direct-dictionary', 'scalar'],
    'review_order_from_limits' => $destinationNames,
    'document_destination_order_matches' => $documentDestinationNames === $destinationNames,
    'valid_bounded_kids_sorted_around_invalid_entries' => true,
    'duplicate_later_target_preserved' => ($destinations[1]['page'] ?? null) === 1
        && ($destinations[1]['fit'] ?? null) === 'XYZ',
    'invalid_kid_destinations_rejected' => !in_array('Direct Kid Decoy', $destinationNames, true),
    'promoted_link_objects' => $promotedLinkObjects,
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
