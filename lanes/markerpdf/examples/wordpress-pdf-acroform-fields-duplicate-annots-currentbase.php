<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneText = 'BT /F1 12 Tf 72 720 Td (Visible duplicate Annots widget page one body) Tj ET';
$pageTwoText = 'BT /F1 12 Tf 72 720 Td (Visible duplicate Annots widget page two body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Annots [12 0 R 14 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Annots [14 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (duplicate.annots.title) /TU (Duplicate Annots title label) /TM (duplicate-annots-title-export) /V (Duplicate Annots title value) /Kids [12 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (duplicate.annots.summary) /TU (Duplicate Annots summary label) /TM (duplicate-annots-summary-export) /V (Duplicate Annots summary value) /Kids [14 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 320 624] /F 4 >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageOneText) . " >>\nstream\n{$pageOneText}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($pageTwoText) . " >>\nstream\n{$pageTwoText}\nendstream\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['duplicate.annots.title', 'duplicate.annots.summary'] as $name) {
    if (!isset($fieldsByName[$name])) {
        throw new RuntimeException("Missing expected duplicate Annots AcroForm field {$name}.");
    }
}

$titleWidget = $fieldsByName['duplicate.annots.title']['widgets'][0] ?? null;
$summaryWidget = $fieldsByName['duplicate.annots.summary']['widgets'][0] ?? null;
if (!is_array($titleWidget) || !is_array($summaryWidget)) {
    throw new RuntimeException('Missing expected duplicate Annots AcroForm widget rows.');
}
if (($titleWidget['page_index'] ?? null) !== 0 || ($titleWidget['page_annotation_index'] ?? null) !== 0) {
    throw new RuntimeException('Duplicate Widget reference must preserve the first page Annots slot for the title field.');
}
if (($summaryWidget['page_index'] ?? null) !== 0 || ($summaryWidget['page_annotation_index'] ?? null) !== 1) {
    throw new RuntimeException('Duplicate no-P Widget reference must preserve the first page Annots occurrence.');
}

foreach (['Duplicate Annots title value', 'Duplicate Annots summary value'] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException('AcroForm field values must stay review-only and out of visible WordPress text.');
    }
}

echo '<!-- markerpdf:pdf-acroform-fields-duplicate-annots-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-duplicate-annots-boundary',
    'field_names' => array_keys($fieldsByName),
    'same_page_duplicate_first_slot_preserved' => ($titleWidget['page_annotation_index'] ?? null) === 0,
    'same_page_duplicate_last_slot_ignored' => ($titleWidget['page_annotation_index'] ?? null) !== 2,
    'cross_page_duplicate_first_page_preserved' => ($summaryWidget['page_index'] ?? null) === 0,
    'cross_page_duplicate_later_page_ignored' => ($summaryWidget['page_object'] ?? null) !== 4,
    'title_widget_object' => $titleWidget['object'] ?? null,
    'summary_widget_object' => $summaryWidget['object'] ?? null,
    'visible_text_imported' => str_contains($visibleText, 'Visible duplicate Annots widget page one body')
        && str_contains($visibleText, 'Visible duplicate Annots widget page two body'),
    'form_values_visible_in_text' => str_contains($visibleText, 'Duplicate Annots title value')
        || str_contains($visibleText, 'Duplicate Annots summary value'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]), ENT_QUOTES, 'UTF-8') . ' -->' . PHP_EOL;
