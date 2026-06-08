<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm detached owner boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [14 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (profile) /TU (Reachable profile label) /TM (reachable-profile-map) /V (Reachable parent value) /DV (Reachable draft value) /MaxLen 64 /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /T (email) /TU (Reachable email label) /TM (reachable.email.export) /V (reachable@example.test) /Kids [14 0 R] >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 12 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "80 0 obj\n<< /FT /Tx /T (detached.owner) /TU (Detached owner label) /TM (detached-owner-map) /V (Detached owner value must not surface) /DV (Detached owner draft must not surface) /MaxLen 4 /Kids [12 0 R] >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

if (array_keys($fieldsByName) !== ['profile.email']) {
    throw new RuntimeException('Reachable AcroForm parentless child field was not preserved as profile.email.');
}

$field = $fieldsByName['profile.email'];
if (($field['object'] ?? null) !== 12 || ($field['value'] ?? null) !== 'reachable@example.test') {
    throw new RuntimeException('Reachable AcroForm child value was not preserved for review.');
}
if (array_column($field['field_hierarchy']['path'] ?? [], 'object') !== [6, 12]) {
    throw new RuntimeException('Reachable AcroForm field hierarchy path was not preserved.');
}
if (array_column($field['widgets'] ?? [], 'object') !== [14]) {
    throw new RuntimeException('Reachable AcroForm child widget was not preserved.');
}

$encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);
foreach ([
    'detached.owner',
    'Detached owner label',
    'detached-owner-map',
    'Detached owner value must not surface',
    'Detached owner draft must not surface',
] as $detachedLeak) {
    if (str_contains($encoded, $detachedLeak) || str_contains($visibleText, $detachedLeak)) {
        throw new RuntimeException("Detached AcroForm owner leaked into WordPress review metadata: {$detachedLeak}");
    }
}
foreach (['reachable@example.test', 'Reachable email label'] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException("AcroForm review-only field text leaked into visible WordPress paragraphs: {$reviewOnlyText}");
    }
}

echo '<!-- markerpdf:pdf-acroform-fields-detached-owner-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-fields-reachable-parentless-child-boundary',
    'native_boundary' => 'Parentless AcroForm child ownership is scoped to the reachable catalog AcroForm Fields tree, so detached field-like objects cannot steal the child or leak stale form values into WordPress review metadata',
    'field_names' => array_keys($fieldsByName),
    'field_objects' => array_column($form['fields'], 'object'),
    'widget_objects' => array_column($field['widgets'] ?? [], 'object'),
    'hierarchy_path_objects' => array_column($field['field_hierarchy']['path'] ?? [], 'object'),
    'detached_owner_excluded' => !str_contains($encoded, 'detached.owner'),
    'detached_owner_value_excluded' => !str_contains($encoded, 'Detached owner value must not surface'),
    'visible_text_preserved' => $visibleText === 'Visible AcroForm detached owner boundary body',
    'field_values_hidden_from_visible_text' => !str_contains($visibleText, 'reachable@example.test'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES) . ' -->' . PHP_EOL;
