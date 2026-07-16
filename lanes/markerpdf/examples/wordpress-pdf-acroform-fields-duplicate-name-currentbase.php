<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm duplicate full-name boundary body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.title) /TU (Stale duplicate title label) /TM (stale-title-export) /V (stale title value must not surface) /DV (stale title draft) /MaxLen 12 /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 600 300 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Tx /T (article.title) /TU (Current duplicate title label) /TM (current-title-export) /V (current title value) /DV (current title draft) /MaxLen 96 /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

if (array_keys($fieldsByName) !== ['article.title'] || count($form['fields']) !== 1) {
    throw new RuntimeException('Expected duplicate full AcroForm names to collapse to one WordPress review field.');
}

$field = $fieldsByName['article.title'];
if (($field['object'] ?? null) !== 10 || ($field['value'] ?? null) !== 'current title value') {
    throw new RuntimeException('Expected the later duplicate full-name field to provide current review metadata.');
}

$encodedForm = json_encode($form, JSON_UNESCAPED_SLASHES);
if (!is_string($encodedForm)) {
    throw new RuntimeException('Unable to encode AcroForm metadata.');
}

foreach ([
    'Stale duplicate title label',
    'stale-title-export',
    'stale title value must not surface',
    'stale title draft',
] as $staleText) {
    if (str_contains($encodedForm, $staleText) || str_contains($visibleText, $staleText)) {
        throw new RuntimeException('Stale duplicate AcroForm full-name metadata leaked into WordPress review output.');
    }
}

foreach ([
    'current title value',
    'current title draft',
    'Current duplicate title label',
] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException('AcroForm review metadata must not become visible Gutenberg paragraph text.');
    }
}

echo '<!-- markerpdf:pdf-acroform-fields-duplicate-name-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-fields-duplicate-full-name-boundary',
    'native_boundary' => 'AcroForm terminal fields with duplicate fully qualified names collapse to the later current field before WordPress review metadata, while field values stay review-only.',
    'field_names' => array_keys($fieldsByName),
    'field_object' => $field['object'] ?? null,
    'field_value' => $field['value'] ?? null,
    'field_value_review_only' => !str_contains($visibleText, (string) ($field['value'] ?? '')),
    'stale_duplicate_metadata_imported' => str_contains($encodedForm, 'stale title value must not surface'),
    'visible_text_imported' => str_contains($visibleText, 'Visible AcroForm duplicate full-name boundary body'),
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li>' . htmlspecialchars(
    (string) ($field['name'] ?? '') . ': ' . (string) ($field['value'] ?? '') . ' [review metadata]',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
