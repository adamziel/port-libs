<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageText = 'BT /F1 12 Tf 72 720 Td (Visible AcroForm scalar object tail body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R 10 0 R 14 0 R] /NeedAppearances true /DA (/Helv 9 Tf 0 0 0 rg) >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (safe.scalar) /TU 30 0 R /TM 31 0 R /V 32 0 R /DV 33 0 R /Kids [8 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Subtype /Widget /Parent 6 0 R /Rect [72 640 320 664] /P 3 0 R /F 4 >>\nendobj\n"
    . "10 0 obj\n<< /FT /Ch /T (safe.choice) /V (publish) /Opt [[34 0 R 35 0 R] [(publish) (Publish)]] /Kids [12 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /Subtype /Widget /Parent 10 0 R /Rect [72 600 280 624] /P 3 0 R /F 4 >>\nendobj\n"
    . "14 0 obj\n<< /FT /Tx /T 36 0 R /TM (safe.unnamed.export) /V (Direct safe unnamed value) /Kids [16 0 R] >>\nendobj\n"
    . "16 0 obj\n<< /Subtype /Widget /Parent 14 0 R /Rect [72 560 320 584] /P 3 0 R /F 4 >>\nendobj\n"
    . "30 0 obj\n(Tailed alternate label must not surface) 99 0 R\nendobj\n"
    . "31 0 obj\n(tailed-mapping-must-not-surface) /Bad\nendobj\n"
    . "32 0 obj\n(Tailed current value must not surface) 77\nendobj\n"
    . "33 0 obj\n(Tailed default value must not surface) false\nendobj\n"
    . "34 0 obj\n(draft-tailed-export-must-not-surface) 123\nendobj\n"
    . "35 0 obj\n(Draft tailed label must not surface) /Extra\nendobj\n"
    . "36 0 obj\n(tailed.partial.name.must.not.surface) 50 0 R\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$fieldsByName = [];
foreach ($form['fields'] as $field) {
    $fieldsByName[(string) ($field['name'] ?? '')] = $field;
}

foreach (['safe.scalar', 'safe.choice', '#14'] as $name) {
    if (!isset($fieldsByName[$name])) {
        throw new RuntimeException("Missing expected AcroForm field {$name}.");
    }
}

$scalar = $fieldsByName['safe.scalar'];
$choice = $fieldsByName['safe.choice'];
$unnamed = $fieldsByName['#14'];
if (($scalar['value'] ?? null) !== null || ($scalar['default_value'] ?? null) !== null || ($scalar['alternate_name'] ?? null) !== null) {
    throw new RuntimeException('Tailed scalar object values leaked into AcroForm review metadata.');
}
if (($choice['options'] ?? null) !== [['export' => 'publish', 'label' => 'Publish']]) {
    throw new RuntimeException('Tailed choice option scalar objects were not excluded.');
}
if (($unnamed['partial_name'] ?? null) !== null || ($unnamed['mapping_name'] ?? null) !== 'safe.unnamed.export') {
    throw new RuntimeException('Tailed partial-name scalar object leaked into AcroForm field naming.');
}

$tailedTexts = [
    'Tailed alternate label must not surface',
    'tailed-mapping-must-not-surface',
    'Tailed current value must not surface',
    'Tailed default value must not surface',
    'draft-tailed-export-must-not-surface',
    'Draft tailed label must not surface',
    'tailed.partial.name.must.not.surface',
];
$encoded = (string) json_encode($form, JSON_UNESCAPED_SLASHES);
foreach ($tailedTexts as $text) {
    if (str_contains($encoded, $text) || str_contains($visibleText, $text)) {
        throw new RuntimeException("Tailed AcroForm scalar leaked: {$text}");
    }
}

$rows = [];
foreach ($form['fields'] as $field) {
    $widgets = is_array($field['widgets'] ?? null) ? $field['widgets'] : [];
    $rows[] = [
        'name' => $field['name'] ?? null,
        'type' => $field['field_type_label'] ?? $field['field_type'] ?? null,
        'value' => $field['value_state']['display_value'] ?? $field['value'] ?? null,
        'widgets' => array_column($widgets, 'object'),
    ];
}

echo '<!-- markerpdf:pdf-acroform-fields-scalar-object-tail-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-acroform-scalar-object-tail-boundary',
    'native_boundary' => 'AcroForm indirect scalar objects are accepted only when the referenced object contains one complete scalar plus whitespace/comments; trailing operands stay excluded from WordPress form review.',
    'field_names' => array_column($rows, 'name'),
    'field_count' => count($form['fields']),
    'tailed_value_objects_rejected' => ($scalar['value'] ?? null) === null && ($scalar['default_value'] ?? null) === null,
    'tailed_label_objects_rejected' => ($scalar['alternate_name'] ?? null) === null && ($scalar['mapping_name'] ?? null) === 'safe.scalar',
    'tailed_option_objects_rejected' => ($choice['options'] ?? null) === [['export' => 'publish', 'label' => 'Publish']],
    'tailed_partial_name_rejected' => ($unnamed['partial_name'] ?? null) === null,
    'field_values_visible_in_text' => str_contains($visibleText, 'Direct safe unnamed value') || str_contains($visibleText, 'publish'),
    'tailed_scalar_text_visible' => count(array_filter($tailedTexts, static fn (string $text): bool => str_contains($visibleText, $text))) > 0,
    'executes_form_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:table -->\n";
echo "<figure class=\"wp-block-table\"><table><tbody>\n";
echo "<tr><th>Field</th><th>Type</th><th>Review value</th><th>Widgets</th></tr>\n";
foreach ($rows as $row) {
    echo '<tr><td>' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(implode(',', array_map('strval', $row['widgets'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td></tr>\n";
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
