<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Legacy action alias source page) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Action alias target destination page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyTarget [3 0 R /FitH 710] /LegacyActionCycle 17 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Names [(Actual Target) [4 0 R /XYZ 72 640 0] (Chained Action Alias) 10 0 R (Direct Action Alias) << /S /GoTo /D (Actual Target) >> (Names To Legacy Chain) 13 0 R (Self Action Cycle) 11 0 R (Mutual Action Cycle) 12 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /S /GoTo /D 14 0 R >>\nendobj\n"
    . "11 0 obj\n<< /S /GoTo /D 11 0 R /Title (hidden self action cycle) >>\nendobj\n"
    . "12 0 obj\n<< /S /GoTo /D 16 0 R >>\nendobj\n"
    . "13 0 obj\n<< /S /GoTo /D /LegacyTarget >>\nendobj\n"
    . "14 0 obj\n<< /S /GoTo /D (Actual Target) >>\nendobj\n"
    . "16 0 obj\n<< /S /GoTo /D 12 0 R /Title (hidden mutual action cycle) >>\nendobj\n"
    . "17 0 obj\n<< /S /GoTo /D 17 0 R /F (legacy-action-cycle.bin) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$destinationNames = array_column($destinations, 'name');
$encodedReview = json_encode([$destinations, $metadata['document_destinations'] ?? []], JSON_UNESCAPED_SLASHES) ?: '';

$expectedNames = [
    'Actual Target',
    'Chained Action Alias',
    'Direct Action Alias',
    'Names To Legacy Chain',
    'LegacyTarget',
];
$cyclicAliasesExcluded = true;
foreach ([
    'Self Action Cycle',
    'Mutual Action Cycle',
    'LegacyActionCycle',
    'hidden self action cycle',
    'hidden mutual action cycle',
    'legacy-action-cycle.bin',
] as $hidden) {
    $cyclicAliasesExcluded = $cyclicAliasesExcluded
        && !str_contains($encodedReview, $hidden)
        && !str_contains($plainText, $hidden);
}

$visibleTextExcludesDestinationMetadata = true;
foreach ($expectedNames as $reviewOnly) {
    $visibleTextExcludesDestinationMetadata = $visibleTextExcludesDestinationMetadata
        && !str_contains($plainText, $reviewOnly);
}

if ($destinationNames !== $expectedNames
    || ($metadata['document_destinations']['names'] ?? null) !== $expectedNames
    || array_column($destinations, 'page') !== [1, 1, 1, 0, 0]
    || !$cyclicAliasesExcluded
    || !$visibleTextExcludesDestinationMetadata
    || !str_contains($plainText, 'Legacy action alias source page')
    || !str_contains($plainText, 'Action alias target destination page')
) {
    throw new RuntimeException('Expected chained GoTo destination aliases to resolve while cyclic action aliases stay out of review and visible text.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog destination /S /GoTo /D action aliases unwrap with object-generation cycle guards',
    'destination_count' => count($destinations),
    'destination_names' => $destinationNames,
    'destination_pages' => array_column($destinations, 'page'),
    'chained_action_alias_resolved' => in_array('Chained Action Alias', $destinationNames, true),
    'direct_action_alias_resolved' => in_array('Direct Action Alias', $destinationNames, true),
    'cross_source_action_alias_resolved' => in_array('Names To Legacy Chain', $destinationNames, true),
    'cyclic_action_aliases_excluded' => $cyclicAliasesExcluded,
    'visible_text_excludes_destination_metadata' => $visibleTextExcludesDestinationMetadata,
];

echo '<!-- markerpdf-pdf-named-destination-action-alias-cycle-boundary-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($destinations as $destination) {
    $metadata = [
        'markerDestination' => $destination['name'],
        'markerPageIndex' => $destination['page'],
        'markerPageObjectId' => $destination['page_object_id'],
        'markerFit' => $destination['fit'],
        'markerCoordinates' => $destination['coordinates'],
        'markerSource' => $destination['source'],
    ];

    echo '<li data-marker-named-destination="'
        . htmlspecialchars(json_encode($metadata, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">'
        . htmlspecialchars($destination['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
