<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf 72 720 Td (Legacy alias source page) Tj ET';
$secondPageContent = 'BT /F1 12 Tf 72 720 Td (Alias target destination page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyTarget [3 0 R /FitH 700] /LegacyAlias /LegacyTarget /LegacyCycleA /LegacyCycleB /LegacyCycleB /LegacyCycleA >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Names [(Actual Target) [4 0 R /XYZ 72 640 0] (String Alias) (Actual Target) (Name Alias) /Actual#20Target (Action Alias) << /S /GoTo /D (Actual Target) >> (Names To Legacy) /LegacyTarget (Missing Alias) (Not Present) (Cycle A) (Cycle B) (Cycle B) (Cycle A)] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF\n";

$destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$destinationNames = array_column($destinations, 'name');
$expectedNames = [
    'Actual Target',
    'String Alias',
    'Name Alias',
    'Action Alias',
    'Names To Legacy',
    'LegacyTarget',
    'LegacyAlias',
];
$encodedReview = json_encode([$destinations, $metadata['document_destinations'] ?? []], JSON_UNESCAPED_SLASHES) ?: '';
$missingAndCyclicAliasesExcluded = true;
foreach (['Missing Alias', 'Not Present', 'Cycle A', 'Cycle B', 'LegacyCycleA', 'LegacyCycleB'] as $hidden) {
    $missingAndCyclicAliasesExcluded = $missingAndCyclicAliasesExcluded
        && !str_contains($encodedReview, $hidden)
        && !str_contains($plainText, $hidden);
}

if ($destinationNames !== $expectedNames
    || ($metadata['document_destinations']['names'] ?? null) !== $expectedNames
    || array_column($destinations, 'page') !== [1, 1, 1, 1, 0, 0, 0]
    || !$missingAndCyclicAliasesExcluded
    || !str_contains($plainText, 'Legacy alias source page')
    || !str_contains($plainText, 'Alias target destination page')
    || str_contains($plainText, 'Actual Target')
) {
    throw new RuntimeException('Expected named-destination aliases to resolve before WordPress review output while hidden alias operands stay non-visible.');
}

$summary = [
    'support_component' => 'native-pdf-named-destination-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /Names /Dests and legacy /Dests aliases resolve through string/name /D values with cycle and missing-target guards',
    'destination_count' => count($destinations),
    'destination_names' => $destinationNames,
    'destination_pages' => array_column($destinations, 'page'),
    'string_alias_resolved' => in_array('String Alias', $destinationNames, true),
    'name_alias_resolved' => in_array('Name Alias', $destinationNames, true),
    'goto_dictionary_alias_resolved' => in_array('Action Alias', $destinationNames, true),
    'cross_source_alias_resolved' => in_array('Names To Legacy', $destinationNames, true),
    'legacy_alias_resolved' => in_array('LegacyAlias', $destinationNames, true),
    'missing_and_cyclic_aliases_excluded' => $missingAndCyclicAliasesExcluded,
    'visible_text_excludes_destination_metadata' => !str_contains($plainText, 'Actual Target')
        && !str_contains($plainText, 'String Alias')
        && !str_contains($plainText, 'LegacyAlias'),
];

echo '<!-- markerpdf-pdf-named-destination-alias-boundary-currentbase '
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
