<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = "BT /FObj 12 Tf 72 720 Td (Compressed object stream page resource text) Tj ET\n"
    . "q 1 0 0 1 72 690 cm /CompressedForm Do Q";
$formContent = 'BT /FObj 10 Tf 0 0 Td (Compressed object stream form text) Tj ET';
$resourceBody = '<< /Font << /FObj 5 0 R >> /XObject << /CompressedForm 6 0 R >> /ProcSet [/PDF /Text] >>';
$decoyResourceBody = '<< /Font << /DecoyFont 5 0 R >> /XObject << /DecoyForm 6 0 R >> >>';
$members = [
    10 => $resourceBody,
    11 => $decoyResourceBody,
];

$objectData = '';
$headerPairs = [];
$memberIndexes = [];
$memberIndex = 0;
foreach ($members as $objectNumber => $body) {
    $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
    $memberIndexes[$objectNumber] = $memberIndex;
    $objectData .= $body . "\n";
    $memberIndex++;
}

$header = implode(' ', $headerPairs);
$objectStreamPlain = $header . "\n" . $objectData;
$compressedObjectStream = gzcompress($objectStreamPlain);
if (!is_string($compressedObjectStream)) {
    throw new RuntimeException('Unable to compress page-resource object stream smoke payload.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(6, 0, "<< /Type /XObject /Subtype /Form /BBox [0 0 200 50] /Resources << /Font << /FObj 5 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream");
$addObject(20, 0, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");

$row = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);
$xrefRows = ''
    . $row(1, $offsets['1:0'])
    . $row(1, $offsets['2:0'])
    . $row(1, $offsets['3:0'])
    . $row(1, $offsets['4:0'])
    . $row(1, $offsets['5:0'])
    . $row(1, $offsets['6:0'])
    . $row(2, 20, $memberIndexes[10])
    . $row(1, $offsets['20:0']);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress page-resource xref stream smoke payload.');
}

$xrefOffset = strlen($pdf);
$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /Index [1 6 10 1 20 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$textExtractor = new PdfTextExtractor();
$metadata = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$resources = $metadata[0]['resources'] ?? [];

$flags = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'xref-stream selected object-stream page resource dictionary before WordPress import',
    'compressed_resource_dictionary_selected' => ($resources['resource_object'] ?? null) === 10
        && ($resources['resource_generation'] ?? null) === 0,
    'inherited_page_tree_resource_owner' => ($resources['inherited'] ?? null) === true
        && ($resources['resource_owner_object'] ?? null) === 2,
    'font_and_form_resources_visible_to_import' => ($resources['font_names'] ?? null) === ['FObj']
        && ($resources['xobject_names'] ?? null) === ['CompressedForm'],
    'unselected_object_stream_decoy_excluded' => !str_contains(json_encode($metadata, JSON_THROW_ON_ERROR), 'Decoy')
        && !str_contains($plainText, 'Decoy'),
    'visible_paragraph_count' => count($lines),
];

if (
    $flags['compressed_resource_dictionary_selected'] !== true
    || $flags['inherited_page_tree_resource_owner'] !== true
    || $flags['font_and_form_resources_visible_to_import'] !== true
    || $flags['unselected_object_stream_decoy_excluded'] !== true
    || $lines !== [
        'Compressed object stream page resource text',
        'Compressed object stream form text',
    ]
) {
    throw new RuntimeException('Expected page-resource object-stream inheritance smoke flags to pass.');
}

echo '<!-- markerpdf-page-resource-object-stream-inheritance-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
