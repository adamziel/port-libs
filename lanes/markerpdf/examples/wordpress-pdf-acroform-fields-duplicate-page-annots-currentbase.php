<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible duplicate page Annots key AcroForm body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [18 0 R] /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (listed.email) /TU (Listed email label) /TM (listed-email-export) /V (listed@example.test) /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Ch /T (current.category) /TU (Current category label) /TM (current-category-export) /V (page) /Opt [(post) (page)] /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 280 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /FT /Tx /T (current.inline) /TU (Current inline label) /TM (current-inline-export) /V (inline current value) /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "18 0 obj\n<< /Subtype /Widget /FT /Tx /T (stale.first.annots) /TU (Stale first Annots label must not surface) /TM (stale-first-annots-export) /V (stale first Annots value must not surface) /Rect [72 520 320 544] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['listed.email', 'current.category', 'current.inline'] as $fieldName) {
    if (!isset($fieldsByName[$fieldName])) {
        throw new RuntimeException("Missing expected AcroForm field {$fieldName}.");
    }
}
if (isset($fieldsByName['stale.first.annots'])) {
    throw new RuntimeException('Stale first page Annots key must not promote its widget field.');
}
if (($fieldsByName['current.category']['widgets'][0]['page_annotation_index'] ?? null) !== 1) {
    throw new RuntimeException('Current category widget did not preserve the last page Annots order.');
}
if (($fieldsByName['current.inline']['widgets'][0]['page_annotation_index'] ?? null) !== 2) {
    throw new RuntimeException('Current inline widget did not preserve the last page Annots order.');
}
foreach ([
    'listed@example.test',
    'inline current value',
    'Current category label',
    'Current inline label',
    'stale first Annots value must not surface',
] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException("Review-only AcroForm text leaked into visible WordPress content: {$reviewOnlyText}");
    }
}

$summary = [
    'source' => 'native-pdf-acroform-page-annots-duplicate-key-boundary',
    'native_boundary' => 'last top-level page /Annots key selected before AcroForm page-widget repair',
    'field_count' => count($form['fields']),
    'field_names' => array_keys($fieldsByName),
    'last_page_annots_selected' => array_keys($fieldsByName) === ['listed.email', 'current.category', 'current.inline'],
    'promoted_omitted_parent_field' => isset($fieldsByName['current.category'])
        && ($fieldsByName['current.category']['object'] ?? null) === 10
        && array_column($fieldsByName['current.category']['widgets'] ?? [], 'object') === [12],
    'promoted_inline_widget_field' => isset($fieldsByName['current.inline'])
        && ($fieldsByName['current.inline']['object'] ?? null) === 16
        && array_column($fieldsByName['current.inline']['widgets'] ?? [], 'object') === [16],
    'stale_first_annots_widget_excluded' => !isset($fieldsByName['stale.first.annots']),
    'page_annotation_indexes' => [
        'listed.email' => array_column($fieldsByName['listed.email']['widgets'] ?? [], 'page_annotation_index'),
        'current.category' => array_column($fieldsByName['current.category']['widgets'] ?? [], 'page_annotation_index'),
        'current.inline' => array_column($fieldsByName['current.inline']['widgets'] ?? [], 'page_annotation_index'),
    ],
    'visible_text_imported' => $visibleText === 'Visible duplicate page Annots key AcroForm body',
    'review_values_visible_in_wordpress_text' => false,
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-acroform-fields-duplicate-page-annots-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8')
    . " -->\n";
