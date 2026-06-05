<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm object-stream fields body) Tj ET';
$compressedMembers = [
    6 => '<< /FT /Tx /T (compressed.email) /TU (Compressed email label) /TM (compressed.email.export) /V (editor@example.test) /DV (draft@example.test) /MaxLen 80 /Kids [8 0 R] >>',
    8 => '<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>',
    10 => '<< /FT /Ch /T (compressed.status) /TU (Compressed status label) /V (publish) /Opt [(draft) (publish)] /Kids [14 0 R] >>',
    14 => '<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 260 624] /P 3 0 R /F 4 >>',
    30 => '<< /FT /Tx /T (detached.objectstream.decoy) /V (Detached object-stream decoy must not surface) >>',
];

$memberData = '';
$headerPairs = [];
foreach ($compressedMembers as $objectNumber => $body) {
    $headerPairs[] = $objectNumber . ' ' . strlen($memberData);
    $memberData .= $body . "\n";
}

$header = implode(' ', $headerPairs) . ' ';
$payload = $header . $memberData;
$compressedPayload = gzcompress($payload);
if (!is_string($compressedPayload)) {
    throw new RuntimeException('Unable to compress AcroForm object-stream smoke fixture.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 14 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "20 0 obj\n<< /Type /ObjStm /N " . count($compressedMembers) . ' /First ' . strlen($header) . ' /Filter /FlateDecode /Length ' . strlen($compressedPayload) . " >>\nstream\n{$compressedPayload}\nendstream\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['compressed.email', 'compressed.status'] as $fieldName) {
    if (!isset($fieldsByName[$fieldName])) {
        throw new RuntimeException("Missing expected object-stream AcroForm field {$fieldName}.");
    }
}
if (isset($fieldsByName['detached.objectstream.decoy'])) {
    throw new RuntimeException('Detached object-stream field decoy must not be imported.');
}

$encodedForm = json_encode($form, JSON_UNESCAPED_SLASHES);
if (!is_string($encodedForm)) {
    throw new RuntimeException('Unable to encode AcroForm smoke review.');
}
foreach (['editor@example.test', 'publish', 'Compressed email label', 'Detached object-stream decoy must not surface'] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException("AcroForm object-stream review text leaked into visible WordPress text: {$reviewOnlyText}");
    }
}

$email = $fieldsByName['compressed.email'];
$status = $fieldsByName['compressed.status'];
$smoke = [
    'source' => 'native-pdf-acroform-object-stream-fields-boundary',
    'native_boundary' => 'Compressed AcroForm field and widget dictionaries from direct /ObjStm carriers are expanded as review metadata while field values stay out of visible WordPress text.',
    'field_count' => count($form['fields']),
    'field_names' => array_keys($fieldsByName),
    'object_stream_field_dictionaries_recovered' => ($email['object'] ?? null) === 6 && ($status['object'] ?? null) === 10,
    'object_stream_widget_dictionaries_recovered' => array_column($email['widgets'] ?? [], 'object') === [8]
        && array_column($status['widgets'] ?? [], 'object') === [14],
    'page_annotation_indexes_preserved' => array_column($email['widgets'] ?? [], 'page_annotation_index') === [0]
        && array_column($status['widgets'] ?? [], 'page_annotation_index') === [1],
    'choice_options_preserved' => $status['options'] ?? [],
    'detached_object_stream_decoy_excluded' => !isset($fieldsByName['detached.objectstream.decoy'])
        && !str_contains($encodedForm, 'Detached object-stream decoy must not surface'),
    'field_values_review_only' => !str_contains($visibleText, 'editor@example.test')
        && !str_contains($visibleText, 'publish')
        && !str_contains($visibleText, 'Compressed email label'),
    'visible_text' => $visibleText,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-acroform-fields-object-stream-currentbase ' . htmlspecialchars(json_encode($smoke, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . " -->\n";
