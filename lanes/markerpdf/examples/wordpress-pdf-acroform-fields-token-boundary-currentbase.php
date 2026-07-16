<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible token-aware AcroForm field body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /DA (/Fields [99 0 R] literal only) /Fie#6Cds [6 0 R] /NeedAppearances true >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (article.token) /TU (Tooltip with /V (Decoy token title) and /Kids [99 0 R]) /V (Real token title) /Kids [8 0 R] /AA << /K << /S /Named /N /Print /Fields [99 0 R] >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "99 0 obj\n<< /FT /Tx /T (decoy.literal) /V (Decoy token title) >>\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

if (array_keys($fieldsByName) !== ['article.token']) {
    throw new RuntimeException('Expected token-aware AcroForm parser to ignore decoy literal /Fields entries.');
}
if (($fieldsByName['article.token']['value'] ?? null) !== 'Real token title') {
    throw new RuntimeException('Expected real top-level AcroForm /V value.');
}
if (isset($fieldsByName['decoy.literal'])) {
    throw new RuntimeException('Decoy literal field should not be imported.');
}

$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
if (!str_contains($visibleText, 'Visible token-aware AcroForm field body')) {
    throw new RuntimeException('Expected page text to remain visible.');
}
if (str_contains($visibleText, 'Decoy token title') || str_contains($visibleText, 'Real token title')) {
    throw new RuntimeException('AcroForm field values must remain review metadata, not visible paragraph text.');
}

$field = $fieldsByName['article.token'];
$widgetObjects = array_values(array_filter(array_map(
    static fn (array $widget): ?int => is_int($widget['object'] ?? null) ? $widget['object'] : null,
    $field['widgets'] ?? []
)));

echo '<!-- markerpdf:pdf-acroform-fields-token-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-token-aware-field-boundary',
    'native_boundary' => 'Escaped AcroForm field keys are decoded, while field-like names inside literal strings and nested action dictionaries stay review-only and cannot redirect WordPress field import.',
    'field_names' => array_keys($fieldsByName),
    'field_value' => $field['value'] ?? null,
    'widget_objects' => $widgetObjects,
    'need_appearances' => $form['need_appearances'],
    'decoy_literal_field_imported' => isset($fieldsByName['decoy.literal']),
    'visible_text_contains_form_value' => str_contains($visibleText, 'Real token title'),
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
