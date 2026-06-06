<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm object-stream array boundary body) Tj ET';
$compressedMembers = [
    20 => '[6 0 R (40 0 R literal decoy) [41 0 R] << /Nested 42 0 R >> % 43 0 R comment decoy' . "\n" . ']',
    21 => '[10 0 R]',
    22 => '[12 0 R]',
    30 => '[44 0 R]',
];
$memberData = '';
$headerPairs = [];
foreach ($compressedMembers as $objectNumber => $body) {
    $headerPairs[] = $objectNumber . ' ' . strlen($memberData);
    $memberData .= $body . "\n";
}
$header = implode(' ', $headerPairs) . ' ';
$compressedPayload = gzcompress($header . $memberData);
if (!is_string($compressedPayload)) {
    throw new RuntimeException('Unable to compress AcroForm object-stream array fixture.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields 20 0 R /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (profile) /TU (Profile parent label) /TM (profile-parent-map) /V (parent@example.test) /DV (parent-draft@example.test) /MaxLen 80 /Kids 21 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Parent 6 0 R /T (email) /TU (Public editor email) /TM (profile.email.export) /V (editor@example.test) /DV (draft@example.test) /Kids 22 0 R >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "40 0 obj\n<< /FT /Tx /T (literal.decoy) /V (Literal array decoy value must not surface) >>\nendobj\n"
    . "41 0 obj\n<< /FT /Tx /T (nested.array.decoy) /V (Nested array decoy value must not surface) >>\nendobj\n"
    . "42 0 obj\n<< /FT /Tx /T (nested.dictionary.decoy) /V (Nested dictionary decoy value must not surface) >>\nendobj\n"
    . "43 0 obj\n<< /FT /Tx /T (comment.decoy) /V (Comment array decoy value must not surface) >>\nendobj\n"
    . "44 0 obj\n<< /FT /Tx /T (unreferenced.compressed.array.decoy) /V (Unreferenced array decoy value must not surface) >>\nendobj\n"
    . "50 0 obj\n<< /Type /ObjStm /N " . count($compressedMembers) . ' /First ' . strlen($header) . ' /Filter /FlateDecode /Length ' . strlen($compressedPayload) . " >>\nstream\n{$compressedPayload}\nendstream\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

$field = $fieldsByName['profile.email'] ?? null;
if (!is_array($field)) {
    throw new RuntimeException('Compressed AcroForm /Fields array did not recover profile.email.');
}
if (($field['object'] ?? null) !== 10 || ($field['value'] ?? null) !== 'editor@example.test') {
    throw new RuntimeException('Compressed AcroForm child field value review was not preserved.');
}
if (array_column($field['field_hierarchy']['path'] ?? [], 'object') !== [6, 10]) {
    throw new RuntimeException('Compressed AcroForm /Kids array did not preserve the field hierarchy.');
}
if (array_column($field['widgets'] ?? [], 'object') !== [12]
    || array_column($field['widgets'] ?? [], 'referenced_from_page_annots') !== [true]
) {
    throw new RuntimeException('Compressed AcroForm /Kids widget array did not attach the page widget.');
}
foreach (['literal.decoy', 'nested.array.decoy', 'nested.dictionary.decoy', 'comment.decoy', 'unreferenced.compressed.array.decoy'] as $decoyName) {
    if (isset($fieldsByName[$decoyName])) {
        throw new RuntimeException("Compressed AcroForm array decoy {$decoyName} was promoted.");
    }
}
foreach (['editor@example.test', 'parent@example.test', 'Public editor email', 'Literal array decoy value must not surface'] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException("Review-only AcroForm text leaked into visible WordPress content: {$reviewOnlyText}");
    }
}

echo '<!-- markerpdf:pdf-acroform-fields-object-stream-array-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-object-stream-array-boundary',
    'field_names' => array_keys($fieldsByName),
    'compressed_fields_array_recovered' => isset($fieldsByName['profile.email']),
    'compressed_kids_arrays_recovered' => array_column($field['field_hierarchy']['path'] ?? [], 'object') === [6, 10]
        && array_column($field['widgets'] ?? [], 'object') === [12],
    'array_decoys_excluded' => !isset($fieldsByName['literal.decoy'])
        && !isset($fieldsByName['nested.array.decoy'])
        && !isset($fieldsByName['nested.dictionary.decoy'])
        && !isset($fieldsByName['comment.decoy'])
        && !isset($fieldsByName['unreferenced.compressed.array.decoy']),
    'field_values_review_only' => !str_contains($visibleText, 'editor@example.test')
        && !str_contains($visibleText, 'parent@example.test'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($visibleText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
